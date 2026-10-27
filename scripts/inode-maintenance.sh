#!/usr/bin/env bash
# Run on SiteGround. Audit and optionally clean WorxEvents inodes only.
# Hard scope: $HOME/www/app.worxevents.com/laravel and its children.
# Never deletes or walks other sites under $HOME/www.
set -euo pipefail

MODE="${MODE:-report}"
BASE="${HOME}/www/app.worxevents.com"
LARAVEL="${BASE}/laravel"
RELEASES="${LARAVEL}/releases"
SHARED="${LARAVEL}/shared"
CURRENT="${LARAVEL}/current"
PREVIOUS="${LARAVEL}/previous"
INCOMING="${LARAVEL}/incoming"

if [[ "$MODE" != "report" && "$MODE" != "cleanup" ]]; then
  echo "MODE must be report or cleanup." >&2
  exit 1
fi

if [[ ! -d "$LARAVEL" ]]; then
  echo "missing=worxevents_laravel path=${LARAVEL}"
  exit 2
fi

count_paths() {
  local target="$1"
  if [[ -e "$target" ]]; then
    find "$target" 2>/dev/null | wc -l
  else
    echo 0
  fi
}

release_realpath() {
  local link="$1"
  if [[ -L "$link" ]]; then
    readlink -f "$link"
  fi
}

assert_under_laravel() {
  local path="$1"
  local root
  root="$(readlink -f "$LARAVEL")"
  case "$path" in
    "$root"|"$root"/*) ;;
    *)
      echo "Refusing path outside WorxEvents laravel: ${path}" >&2
      exit 1
      ;;
  esac
}

report_inodes() {
  local label="$1"
  echo "=== inode_report label=${label} ==="
  # SiteGround often restricts a full $HOME walk; prefer www + quota/df hints.
  echo "home_www_inodes=$(count_paths "${HOME}/www")"
  if command -v quota >/dev/null 2>&1; then
    quota -s 2>/dev/null | sed 's/^/quota_line=/' || true
  fi
  if df -i "$HOME" >/dev/null 2>&1; then
    df -i "$HOME" 2>/dev/null | sed 's/^/df_i_line=/' || true
  fi
  echo "worxevents_site_inodes=$(count_paths "$BASE")"
  echo "worxevents_laravel_inodes=$(count_paths "$LARAVEL")"
  echo "releases_total_inodes=$(count_paths "$RELEASES")"
  echo "shared_inodes=$(count_paths "$SHARED")"
  echo "incoming_inodes=$(count_paths "$INCOMING")"
  echo "shared_storage_logs=$(count_paths "${SHARED}/storage/logs")"
  echo "shared_framework_cache=$(count_paths "${SHARED}/storage/framework/cache")"
  echo "shared_framework_views=$(count_paths "${SHARED}/storage/framework/views")"
  echo "shared_framework_sessions=$(count_paths "${SHARED}/storage/framework/sessions")"
  echo "shared_backups=$(count_paths "${SHARED}/backups")"

  local curr prev
  curr="$(release_realpath "$CURRENT" || true)"
  prev="$(release_realpath "$PREVIOUS" || true)"
  echo "current_release=${curr:-none}"
  echo "previous_release=${prev:-none}"

  if [[ -d "$RELEASES" ]]; then
    local dir name vendor_n node_n total_n
    shopt -s nullglob
    for dir in "$RELEASES"/*; do
      [[ -d "$dir" ]] || continue
      name="$(basename "$dir")"
      total_n="$(count_paths "$dir")"
      vendor_n="$(count_paths "${dir}/vendor")"
      node_n="$(count_paths "${dir}/node_modules")"
      echo "release name=${name} inodes=${total_n} vendor=${vendor_n} node_modules=${node_n}"
    done
    shopt -u nullglob
  fi

  # Surfacing node_modules anywhere under laravel (should be zero after cleanup).
  # SiteGround bash cannot open /dev/fd from process substitution; use a temp file.
  if [[ -d "$LARAVEL" ]]; then
    local nm listing
    listing="$(mktemp)"
    find "$LARAVEL" -type d -name node_modules 2>/dev/null > "$listing" || true
    while IFS= read -r nm; do
      [[ -n "$nm" ]] || continue
      echo "node_modules_path=${nm} inodes=$(count_paths "$nm")"
    done < "$listing"
    rm -f "$listing"
  fi
  echo "=== end_inode_report label=${label} ==="
}

cleanup_worxevents() {
  local curr prev dir full removed=0
  curr="$(release_realpath "$CURRENT" || true)"
  prev="$(release_realpath "$PREVIOUS" || true)"

  echo "=== cleanup start ==="
  echo "keep_current=${curr:-none}"
  echo "keep_previous=${prev:-none}"

  if [[ -z "$curr" ]]; then
    echo "held_back=no_current_release (refusing to prune releases without a live current symlink)"
    echo "=== cleanup end ==="
    return 0
  fi

  assert_under_laravel "$(readlink -f "$LARAVEL")"

  # 1) Keep current + one previous; delete older release directories.
  if [[ -d "$RELEASES" ]]; then
    shopt -s nullglob
    for dir in "$RELEASES"/*; do
      [[ -d "$dir" ]] || continue
      full="$(readlink -f "$dir")"
      assert_under_laravel "$full"
      if [[ "$full" == "$curr" || ( -n "$prev" && "$full" == "$prev" ) ]]; then
        echo "kept_release=${full}"
        continue
      fi
      echo "removing_old_release=${full} inodes_before=$(count_paths "$full")"
      rm -rf "$full"
      removed=$((removed + 1))
      echo "removed_old_release=${full}"
    done
    shopt -u nullglob
  fi

  # 2) Delete node_modules anywhere under WorxEvents laravel (should never ship).
  if [[ -d "$LARAVEL" ]]; then
    local nm listing
    listing="$(mktemp)"
    find "$LARAVEL" -type d -name node_modules 2>/dev/null > "$listing" || true
    while IFS= read -r nm; do
      [[ -n "$nm" ]] || continue
      assert_under_laravel "$(readlink -f "$nm")"
      echo "removing_node_modules=${nm} inodes_before=$(count_paths "$nm")"
      rm -rf "$nm"
      echo "removed_node_modules=${nm}"
    done < "$listing"
    rm -f "$listing"
  fi

  # 3) Clear stale framework views and cache data (rebuilds on demand).
  if [[ -d "${SHARED}/storage/framework/views" ]]; then
    find "${SHARED}/storage/framework/views" -type f ! -name '.gitignore' -delete
    echo "cleared=framework_views"
  fi
  if [[ -d "${SHARED}/storage/framework/cache/data" ]]; then
    find "${SHARED}/storage/framework/cache/data" -type f -delete
    echo "cleared=framework_cache_data"
  fi

  # 4) Rotated/daily logs older than 7 days. Keep laravel.log / recent dailies.
  if [[ -d "${SHARED}/storage/logs" ]]; then
    find "${SHARED}/storage/logs" -type f \( -name '*.log' -o -name '*.log.*' \) -mtime +7 -print -delete | sed 's/^/removed_old_log=/'
    echo "cleared=logs_older_than_7_days"
  fi

  # 5) Expired file sessions (mtime older than 2 days). DB sessions are untouched.
  if [[ -d "${SHARED}/storage/framework/sessions" ]]; then
    find "${SHARED}/storage/framework/sessions" -type f ! -name '.gitignore' -mtime +2 -delete
    echo "cleared=expired_file_sessions_mtime_gt_2d"
  fi

  # 6) Incoming release archives left behind.
  if [[ -d "$INCOMING" ]]; then
    find "$INCOMING" -maxdepth 1 -type f \( -name '*.tar.gz' -o -name '*.tgz' \) -print -delete | sed 's/^/removed_incoming=/'
    echo "cleared=incoming_archives"
  fi

  # 7) Force daily logs with 7-day retention on shared .env (do not print values).
  if [[ -f "${SHARED}/.env" ]]; then
    local tmp
    tmp="$(mktemp)"
    # Drop prior LOG_CHANNEL / LOG_DAILY_DAYS / LOG_STACK lines, then append desired ones.
    grep -E -v '^(LOG_CHANNEL|LOG_DAILY_DAYS|LOG_STACK)=' "${SHARED}/.env" > "$tmp" || true
    {
      cat "$tmp"
      echo 'LOG_CHANNEL=daily'
      echo 'LOG_DAILY_DAYS=7'
      echo 'LOG_STACK=daily'
    } > "${SHARED}/.env"
    rm -f "$tmp"
    chmod 600 "${SHARED}/.env"
    echo "updated_env=LOG_CHANNEL=daily LOG_DAILY_DAYS=7"
  else
    echo "held_back=shared_env_missing"
  fi

  # Never delete current release vendor, shared/.env, or shared/storage tree itself.
  echo "held_back=current_vendor shared_.env shared_storage_tree"
  echo "old_releases_removed_count=${removed}"
  echo "=== cleanup end ==="
}

report_inodes "before"

if [[ "$MODE" == "cleanup" ]]; then
  cleanup_worxevents
  report_inodes "after"
fi
