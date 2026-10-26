#!/usr/bin/env bash
# The server activate script can deploy without database credentials and
# must not print the application key it generates.
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

base="${tmp}/app.worxevents.com"
mkdir -p "$base"

src="${tmp}/src"
mkdir -p "${src}/public" "${src}/scripts"
printf '<?php echo "ok";\n' > "${src}/public/index.php"
cp "${root}/public/.htaccess" "${src}/public/.htaccess"
cp "${root}/scripts/release.sh" "${src}/scripts/release.sh"
chmod +x "${src}/scripts/release.sh"
tar -czf "${tmp}/release.tar.gz" -C "$src" .

mkdir -p "${base}/laravel/incoming"
cp "${tmp}/release.tar.gz" "${base}/laravel/incoming/abc123.tar.gz"

log="${tmp}/out.txt"
WORXEVENTS_BASE="$base" ACTION=deploy RELEASE=abc123 bash "${root}/scripts/remote-activate.sh" > "$log"

grep -q "missing=DB_DATABASE" "$log"
grep -q "missing=DB_USERNAME" "$log"
grep -q "missing=DB_PASSWORD" "$log"
grep -q "db_login=skipped" "$log"
grep -q "migrated=skipped" "$log"
grep -q "deployed=abc123" "$log"
grep -q "laravel/current/public/index.php" "${base}/public_html/index.php"
[[ -f "${base}/laravel/shared/.env" ]]
key="$(sed -n 's/^APP_KEY="//p' "${base}/laravel/shared/.env" | sed 's/"$//')"
[[ -n "$key" ]]
if grep -F "$key" "$log" >/dev/null; then
  echo "The generated application key was printed." >&2
  exit 1
fi
if grep -E 'DB_PASSWORD=.' "$log" >/dev/null; then
  echo "A database password was printed." >&2
  exit 1
fi

set +e
WORXEVENTS_BASE="${tmp}/missing-site" ACTION=deploy RELEASE=abc123 bash "${root}/scripts/remote-activate.sh" > "${tmp}/missing.txt" 2>&1
status=$?
set -e
[[ "$status" -eq 2 ]]
grep -q "missing=document_root" "${tmp}/missing.txt"

echo "remote_activate_test=ok"
