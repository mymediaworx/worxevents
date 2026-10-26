#!/usr/bin/env bash
# The server activate script can deploy without database credentials and
# must not print the application key it generates.
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

mkdir -p "${tmp}/bin"
cat > "${tmp}/bin/crontab" << 'EOF'
#!/bin/sh
printf '%s\n' "$*" >> "${CRONTAB_STUB_LOG}"
exit 1
EOF
chmod +x "${tmp}/bin/crontab"
export PATH="${tmp}/bin:${PATH}"
export CRONTAB_STUB_LOG="${tmp}/crontab-invocations.txt"
touch "$CRONTAB_STUB_LOG"

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
grep -q "manual=cron" "$log"
grep -q "cron_line=" "$log"
[[ -s "$CRONTAB_STUB_LOG" ]]

env_file="${base}/laravel/shared/.env"
key_before="$(sed -n 's/^APP_KEY=//p' "$env_file")"
cp "${tmp}/release.tar.gz" "${base}/laravel/incoming/def456.tar.gz"
bad_log="${tmp}/bad.txt"
set +e
DB_HOST=127.0.0.1 \
DB_PORT=1 \
DB_DATABASE=worxevents_absent \
DB_USERNAME=worxevents_absent \
DB_PASSWORD='s3cret-quote"slash\tail' \
APP_KEY='base64:should-not-replace' \
WORXEVENTS_BASE="$base" ACTION=deploy RELEASE=def456 \
  bash "${root}/scripts/remote-activate.sh" > "$bad_log"
status=$?
set -e
[[ "$status" -eq 1 ]]
grep -q "db_login=failed" "$bad_log"
grep -q "migrated=skipped" "$bad_log"
grep -q "deployed=def456" "$bad_log"
grep -q "manual=cron" "$bad_log"
if grep -F 's3cret-quote' "$bad_log" >/dev/null; then
  echo "The database password was printed." >&2
  exit 1
fi
if grep -F 'base64:should-not-replace' "$env_file" >/dev/null; then
  echo "An existing application key was replaced." >&2
  exit 1
fi
[[ "$(sed -n 's/^APP_KEY=//p' "$env_file")" == "$key_before" ]]
grep -F 'DB_DATABASE="worxevents_absent"' "$env_file" >/dev/null
grep -F 'DB_PASSWORD="s3cret-quote\"slash\\tail"' "$env_file" >/dev/null
grep -F 'SESSION_DRIVER="file"' "$env_file" >/dev/null
grep -F 'MAIL_MAILER="log"' "$env_file" >/dev/null
grep -F 'MAIL_LOG_CHANNEL="mail"' "$env_file" >/dev/null
password_line="$(grep -F 'DB_PASSWORD=' "$env_file")"

cp "${tmp}/release.tar.gz" "${base}/laravel/incoming/ghi789.tar.gz"
set +e
DB_HOST=127.0.0.1 \
DB_PORT=1 \
DB_DATABASE=worxevents_absent \
DB_USERNAME=worxevents_absent \
DB_PASSWORD='s3cret-quote"slash\tail' \
APP_KEY='' \
WORXEVENTS_BASE="$base" ACTION=deploy RELEASE=ghi789 \
  bash "${root}/scripts/remote-activate.sh" > "${tmp}/bad-again.txt"
status=$?
set -e
[[ "$status" -eq 1 ]]
[[ "$(grep -F 'DB_PASSWORD=' "$env_file")" == "$password_line" ]]
[[ "$(sed -n 's/^APP_KEY=//p' "$env_file")" == "$key_before" ]]
if grep -F 's3cret-quote' "${tmp}/bad-again.txt" >/dev/null; then
  echo "The database password was printed on the second write." >&2
  exit 1
fi

echo "remote_activate_test=ok"
