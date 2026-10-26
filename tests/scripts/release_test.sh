#!/usr/bin/env bash
# Proves the release script keeps current plus one rollback and never copies secrets into public_html.
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

base="${tmp}/app.worxevents.com"
shared="${base}/laravel/shared"
script="${root}/scripts/release.sh"

mkdir -p \
  "${shared}/backups" \
  "${shared}/storage/logs" \
  "${shared}/storage/framework/cache/data" \
  "${shared}/storage/framework/views"

printf '%s\n' 'APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' 'SECRET_SENTINEL=shared-only' > "${shared}/.env"

printf '%s\n' 'old' > "${shared}/backups/old.sql.gz"
printf '%s\n' 'mid' > "${shared}/backups/mid.sql.gz"
printf '%s\n' 'new' > "${shared}/backups/new.sql.gz"
touch -d '2020-01-01 00:00:00' "${shared}/backups/old.sql.gz"
touch -d '2022-01-01 00:00:00' "${shared}/backups/mid.sql.gz"
touch -d '2024-01-01 00:00:00' "${shared}/backups/new.sql.gz"

printf '%s\n' 'stale' > "${shared}/storage/framework/cache/data/stale.php"
printf '%s\n' 'stale' > "${shared}/storage/framework/views/stale.php"
printf '%s\n' 'one' > "${shared}/storage/logs/one.log"
printf '%s\n' 'two' > "${shared}/storage/logs/two.log"
printf '%s\n' 'three' > "${shared}/storage/logs/three.log"
touch -d '2019-01-01 00:00:00' "${shared}/storage/logs/one.log"
touch -d '2021-01-01 00:00:00' "${shared}/storage/logs/two.log"
touch -d '2023-01-01 00:00:00' "${shared}/storage/logs/three.log"

src=""
stage() {
  local name="$1"
  src="${tmp}/src-${name}"
  mkdir -p "${src}/public"
  printf '<?php echo "%s";\n' "$name" > "${src}/public/index.php"
  cp "${root}/public/.htaccess" "${src}/public/.htaccess"
  printf '%s\n' 'SECRET_SENTINEL=should-not-ship' > "${src}/.env"
}

deploy() {
  local name="$1"
  stage "$name"
  WORXEVENTS_BASE="$base" ACTION=deploy RELEASE="$name" SOURCE="$src" bash "$script"
}

deploy 20261026a
deploy 20261026b
deploy 20261026c

count="$(find "${base}/laravel/releases" -mindepth 1 -maxdepth 1 -type d | wc -l)"
count="${count//[[:space:]]/}"
[[ "$count" == "2" ]]
[[ "$(readlink -f "${base}/laravel/current")" == "${base}/laravel/releases/20261026c" ]]
[[ "$(readlink -f "${base}/laravel/previous")" == "${base}/laravel/releases/20261026b" ]]
[[ ! -d "${base}/laravel/releases/20261026a" ]]
grep -q "laravel/current/public/index.php" "${base}/public_html/index.php"
grep -q "Require all denied" "${base}/public_html/.htaccess"
[[ ! -e "${base}/public_html/.env" ]]
[[ -L "${base}/laravel/current/.env" ]]
[[ "$(readlink -f "${base}/laravel/current/.env")" == "${shared}/.env" ]]
if grep -R "should-not-ship" "$base" >/dev/null; then
  echo "A release secret was copied into the site tree." >&2
  exit 1
fi
if grep -R "SECRET_SENTINEL" "${base}/public_html" >/dev/null; then
  echo "public_html contains the shared sentinel." >&2
  exit 1
fi
[[ ! -f "${shared}/storage/framework/cache/data/stale.php" ]]
[[ ! -f "${shared}/storage/framework/views/stale.php" ]]
[[ ! -f "${shared}/backups/old.sql.gz" ]]
[[ -f "${shared}/backups/mid.sql.gz" && -f "${shared}/backups/new.sql.gz" ]]
[[ ! -f "${shared}/storage/logs/one.log" ]]
[[ -f "${shared}/storage/logs/two.log" && -f "${shared}/storage/logs/three.log" ]]
[[ -f "${shared}/.env" ]]

WORXEVENTS_BASE="$base" ACTION=rollback bash "$script"
[[ "$(readlink -f "${base}/laravel/current")" == "${base}/laravel/releases/20261026b" ]]
[[ "$(readlink -f "${base}/laravel/previous")" == "${base}/laravel/releases/20261026c" ]]

deploy 20261026d
[[ "$(readlink -f "${base}/laravel/current")" == "${base}/laravel/releases/20261026d" ]]
[[ "$(readlink -f "${base}/laravel/previous")" == "${base}/laravel/releases/20261026b" ]]
[[ ! -d "${base}/laravel/releases/20261026c" ]]

ln -s "$base" "${tmp}/via-link"
WORXEVENTS_BASE="${tmp}/via-link" ACTION=deploy RELEASE=20261026e SOURCE="$src" bash "$script"
[[ "$(readlink -f "${base}/laravel/current")" == "${base}/laravel/releases/20261026e" ]]
[[ ! -d "${base}/laravel/releases/20261026b" ]]

mkdir -p "${src}/public/css" "${src}/public/js" "${src}/public/images/demo"
printf '%s\n' 'body{color:#0F5C4D}' > "${src}/public/css/worxevents.css"
printf '%s\n' '/* scan */' > "${src}/public/js/check-in.js"
printf '%s\n' '<svg xmlns="http://www.w3.org/2000/svg"></svg>' > "${src}/public/images/demo/demo-community-night.svg"
WORXEVENTS_BASE="$base" ACTION=deploy RELEASE=20261026f SOURCE="$src" bash "$script"
grep -q 'body{color:#0F5C4D}' "${base}/public_html/css/worxevents.css"
grep -q 'scan' "${base}/public_html/js/check-in.js"
[[ -f "${base}/public_html/images/demo/demo-community-night.svg" ]]

set +e
WORXEVENTS_BASE="${tmp}/public_html/site" ACTION=deploy RELEASE=nope SOURCE="$src" bash "$script" >/dev/null 2>&1
status=$?
set -e
[[ "$status" -ne 0 ]]

bare="${tmp}/bare-site"
mkdir -p "${bare}/laravel/shared"
set +e
WORXEVENTS_BASE="$bare" ACTION=deploy RELEASE=20261026e SOURCE="$src" bash "$script" >/dev/null 2>&1
status=$?
set -e
[[ "$status" -ne 0 ]]
[[ ! -f "${bare}/public_html/index.php" ]]

echo "release_test=ok"
