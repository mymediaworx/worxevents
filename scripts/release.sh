#!/usr/bin/env bash
# Activate one built release, or roll back to the single previous release.
# Dependencies are installed before upload. This script does not install them.
# Secrets stay in laravel/shared/.env, outside public_html.
# Requires GNU mv (-T) and GNU find.
set -euo pipefail

ACTION="${ACTION:?ACTION is required}"
BASE="${WORXEVENTS_BASE:-${HOME}/www/app.worxevents.com}"

if [[ "$ACTION" != "deploy" && "$ACTION" != "rollback" ]]; then
  echo "ACTION must be deploy or rollback." >&2
  exit 1
fi

case "$BASE" in
  *public_html*)
    echo "Refusing to deploy because the base path contains public_html." >&2
    exit 1
    ;;
esac

if [[ "$BASE" != /* ]]; then
  echo "WORXEVENTS_BASE must be an absolute path." >&2
  exit 1
fi

mkdir -p "$BASE"
BASE="$(cd "$BASE" && pwd -P)"

laravel="${BASE}/laravel"
releases="${laravel}/releases"
shared="${laravel}/shared"
current="${laravel}/current"
previous="${laravel}/previous"
docroot="${BASE}/public_html"
incoming="${laravel}/incoming"

mkdir -p \
  "$releases" \
  "$incoming" \
  "$docroot" \
  "${shared}/storage/framework/cache/data" \
  "${shared}/storage/framework/sessions" \
  "${shared}/storage/framework/views" \
  "${shared}/storage/logs" \
  "${shared}/storage/app/public" \
  "${shared}/backups"

release_realpath() {
  local link="$1"
  if [[ -L "$link" ]]; then
    readlink -f "$link"
  fi
}

assert_under_releases() {
  local path="$1"
  local root
  root="$(readlink -f "$releases")"
  case "$path" in
    "$root"/*) ;;
    *)
      echo "Refusing to remove a path outside releases." >&2
      exit 1
      ;;
  esac
}

require_symlink_or_absent() {
  local link="$1"
  local label="$2"
  if [[ -e "$link" && ! -L "$link" ]]; then
    echo "${label} must be a symlink." >&2
    exit 1
  fi
}

drop_old_rollback() {
  local prev curr
  prev="$(release_realpath "$previous" || true)"
  curr="$(release_realpath "$current" || true)"
  if [[ -n "$prev" && "$prev" != "$curr" ]]; then
    assert_under_releases "$prev"
    rm -rf "$prev"
    echo "removed_old_rollback=${prev}"
  fi
  rm -f "$previous"
}

point_symlink() {
  local target="$1"
  local link="$2"
  ln -sfn "$target" "${link}.next"
  mv -Tf "${link}.next" "$link"
}

write_docroot() {
  cat > "${docroot}/index.php" <<'PHP'
<?php

declare(strict_types=1);

require __DIR__.'/../laravel/current/public/index.php';
PHP
  cp "${current}/public/.htaccess" "${docroot}/.htaccess"
  rm -f "${docroot}/.env" "${docroot}/.env.example"
  # Apache serves a real file before the front controller. Copy the UI into
  # public_html so the stylesheet is not rewritten into an HTML 404.
  local asset
  for asset in css js images; do
    if [[ -d "${current}/public/${asset}" ]]; then
      rm -rf "${docroot}/${asset}"
      cp -a "${current}/public/${asset}" "${docroot}/${asset}"
    fi
  done
  echo "docroot=written"
}

prune_newest() {
  local dir="$1"
  local keep="$2"
  local index=0
  local stamp path
  local listing
  # A temporary file, not a process substitution. SiteGround bash cannot open
  # the /dev/fd entry that process substitution uses, and the deploy stops.
  listing="$(mktemp)"
  find "$dir" -maxdepth 1 -type f ! -name '.gitignore' -printf '%T@\t%p\n' | sort -nr > "$listing"
  while IFS=$'\t' read -r stamp path; do
    [[ -n "$path" ]] || continue
    index=$((index + 1))
    if [[ "$index" -gt "$keep" ]]; then
      rm -f "$path"
      echo "pruned_file=${path}"
    fi
  done < "$listing"
  rm -f "$listing"
}

prune_runtime() {
  local keep_current keep_previous dir full
  keep_current="$(release_realpath "$current" || true)"
  keep_previous="$(release_realpath "$previous" || true)"

  shopt -s nullglob
  for dir in "$releases"/*; do
    [[ -d "$dir" ]] || continue
    full="$(readlink -f "$dir")"
    if [[ "$full" == "$keep_current" || "$full" == "$keep_previous" ]]; then
      continue
    fi
    assert_under_releases "$full"
    rm -rf "$full"
    echo "pruned=${full}"
  done
  rm -f "$incoming"/*.tar.gz "$incoming"/*.tgz
  shopt -u nullglob

  find "${shared}/storage/framework/cache/data" -type f -delete
  find "${shared}/storage/framework/views" -type f ! -name '.gitignore' -delete
  echo "caches=pruned"

  prune_newest "${shared}/backups" 2
  prune_newest "${shared}/storage/logs" 2
}

unlink_storage() {
  local path="$1"
  if [[ -L "$path" ]]; then
    rm -f "$path"
  elif [[ -d "$path" ]]; then
    rm -rf "$path"
  fi
}

deploy_release() {
  local id="${RELEASE:?RELEASE is required}"
  local source="${SOURCE:?SOURCE is required}"
  local dest curr

  if [[ ! "$id" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$ ]]; then
    echo "RELEASE must be a short file-safe name." >&2
    exit 1
  fi

  if [[ ! -f "${shared}/.env" ]]; then
    echo "shared_env=missing" >&2
    exit 1
  fi

  if [[ ! -d "$source" ]]; then
    echo "SOURCE must be a directory." >&2
    exit 1
  fi

  require_symlink_or_absent "$current" "current"
  require_symlink_or_absent "$previous" "previous"

  source="$(readlink -f "$source")"
  dest="${releases}/${id}"

  if [[ -e "$dest" ]]; then
    echo "Release ${id} already exists." >&2
    exit 1
  fi

  mkdir -p "$dest"
  cp -a "${source}/." "$dest/"
  rm -rf "${dest}/.git" "${dest}/node_modules"
  rm -f "${dest}/.env"
  unlink_storage "${dest}/storage"
  ln -s "${shared}/storage" "${dest}/storage"
  ln -s "${shared}/.env" "${dest}/.env"

  if [[ ! -f "${dest}/public/index.php" || ! -f "${dest}/public/.htaccess" ]]; then
    rm -rf "$dest"
    echo "Release is missing public/index.php or public/.htaccess." >&2
    exit 1
  fi

  if [[ -e "${docroot}/.env" ]]; then
    rm -rf "$dest"
    echo "Refusing to deploy while public_html/.env exists." >&2
    exit 1
  fi

  drop_old_rollback
  if [[ -L "$current" ]]; then
    curr="$(readlink -f "$current")"
    point_symlink "$curr" "$previous"
  fi
  point_symlink "$dest" "$current"
  write_docroot
  prune_runtime
  echo "deployed=${id}"
}

rollback_release() {
  local prev curr
  require_symlink_or_absent "$current" "current"
  require_symlink_or_absent "$previous" "previous"

  if [[ ! -L "$previous" || ! -L "$current" ]]; then
    echo "No previous release to roll back to." >&2
    exit 1
  fi

  prev="$(readlink -f "$previous")"
  curr="$(readlink -f "$current")"
  assert_under_releases "$prev"
  assert_under_releases "$curr"
  point_symlink "$prev" "$current"
  point_symlink "$curr" "$previous"
  write_docroot
  echo "rolled_back=$(basename "$prev")"
}

if [[ "$ACTION" == "deploy" ]]; then
  deploy_release
else
  rollback_release
fi
