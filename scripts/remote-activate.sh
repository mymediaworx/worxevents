#!/usr/bin/env bash
# Run on the server. Creates what it can under the site directory and deploys
# one archive. Secrets are read from the environment and written to
# laravel/shared/.env. This script does not print those values.
set -euo pipefail

ACTION="${ACTION:-deploy}"
RELEASE="${RELEASE:?RELEASE is required}"
BASE="${WORXEVENTS_BASE:-${HOME}/www/app.worxevents.com}"

if [[ "$ACTION" != "deploy" && "$ACTION" != "rollback" ]]; then
  echo "ACTION must be deploy or rollback." >&2
  exit 1
fi

if [[ ! "$RELEASE" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$ ]]; then
  echo "RELEASE must be a short file-safe name." >&2
  exit 1
fi

case "$BASE" in
  *public_html*)
    echo "Refusing to deploy because the base path contains public_html." >&2
    exit 1
    ;;
esac

if [[ ! -d "$BASE" ]]; then
  echo "missing=document_root"
  echo "Create the app.worxevents.com site in Site Tools so ${BASE} exists. This workflow does not create a SiteGround site."
  exit 2
fi

shared="${BASE}/laravel/shared"
incoming="${BASE}/laravel/incoming"
umask 077
mkdir -p \
  "$incoming" \
  "${shared}/storage/framework/cache/data" \
  "${shared}/storage/framework/sessions" \
  "${shared}/storage/framework/views" \
  "${shared}/storage/logs" \
  "${shared}/storage/app/public" \
  "${shared}/backups"

find_php() {
  local candidate version major minor
  local candidates=()
  shopt -s nullglob
  candidates+=(
    /usr/local/php82/bin/php
    /usr/bin/php8.2
    /usr/local/php83/bin/php
    /usr/bin/php8.3
    /usr/bin/php
  )
  shopt -u nullglob
  for candidate in "${candidates[@]}"; do
    [[ -x "$candidate" ]] || continue
    version="$("$candidate" -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;' 2>/dev/null)" || continue
    [[ "$version" =~ ^[0-9]+\.[0-9]+$ ]] || continue
    major="${version%%.*}"
    minor="${version#*.}"
    if [[ "$major" -gt 8 ]] || [[ "$major" -eq 8 && "$minor" -ge 2 ]]; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done
  return 1
}

php_bin="$(find_php || true)"
if [[ -z "$php_bin" ]]; then
  echo "missing=php"
  exit 1
fi

write_env() {
  WE_ENV_PATH="${shared}/.env" "$php_bin" << 'PHP'
<?php

$path = getenv('WE_ENV_PATH');
$existing = [];

if (is_file($path)) {
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fwrite(STDERR, "shared_env=unreadable\n");
        exit(1);
    }
    foreach ($lines as $line) {
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $existing[$key] = trim($value, " \t\"'");
    }
}

$incoming = [
    'APP_KEY' => (string) getenv('APP_KEY'),
    'DB_HOST' => (string) getenv('DB_HOST'),
    'DB_PORT' => (string) getenv('DB_PORT'),
    'DB_DATABASE' => (string) getenv('DB_DATABASE'),
    'DB_USERNAME' => (string) getenv('DB_USERNAME'),
    'DB_PASSWORD' => (string) getenv('DB_PASSWORD'),
];

foreach ($incoming as $key => $value) {
    if ($value !== '' && ($existing[$key] ?? '') === '') {
        $existing[$key] = $value;
    }
}

if (($existing['APP_KEY'] ?? '') === '') {
    $existing['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
}

$dbReady = ($existing['DB_DATABASE'] ?? '') !== ''
    && ($existing['DB_USERNAME'] ?? '') !== ''
    && ($existing['DB_PASSWORD'] ?? '') !== '';

$defaults = [
    'APP_NAME' => 'WorxEvents',
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_URL' => 'https://app.worxevents.com',
    'LOG_CHANNEL' => 'stack',
    'LOG_LEVEL' => 'error',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'SESSION_DRIVER' => $dbReady ? 'database' : 'file',
    'SESSION_LIFETIME' => '120',
    'SESSION_SECURE_COOKIE' => 'true',
    'SESSION_HTTP_ONLY' => 'true',
    'SESSION_SAME_SITE' => 'lax',
    'QUEUE_CONNECTION' => $dbReady ? 'database' : 'sync',
    'DB_QUEUE_TABLE' => 'background_jobs',
    'QUEUE_WORK_MAX_TIME' => '20',
    'CACHE_STORE' => $dbReady ? 'database' : 'file',
    'MAIL_MAILER' => 'log',
    'PLATFORM_DOMAINS' => 'app.worxevents.com',
    'WORXEVENTS_APEX' => 'worxevents.com',
    'PAYMENTS_GATEWAY' => 'stripe',
];

foreach ($defaults as $key => $value) {
    if (! array_key_exists($key, $existing) || $existing[$key] === '') {
        $existing[$key] = $value;
    }
}

if (! $dbReady) {
    $existing['SESSION_DRIVER'] = 'file';
    $existing['CACHE_STORE'] = 'file';
    $existing['QUEUE_CONNECTION'] = 'sync';
}

$lines = [];
foreach ($existing as $key => $value) {
    if (! preg_match('/^[A-Z0-9_]+$/', $key)) {
        continue;
    }
    $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);
    $lines[] = $key.'="'.$escaped.'"';
}

file_put_contents($path, implode("\n", $lines)."\n");
chmod($path, 0600);

foreach (['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    if (($existing[$key] ?? '') === '') {
        echo "missing={$key}\n";
    }
}

if (($existing['MAIL_PASSWORD'] ?? '') === '' && (string) getenv('MAIL_PASSWORD') === '') {
    echo "manual=smtp\n";
}

if (! $dbReady) {
    echo "db_login=skipped\n";
    echo "shared_env=ready\n";
    exit(0);
}

try {
    $dsn = 'mysql:host='.$existing['DB_HOST'].';port='.$existing['DB_PORT'].';dbname='.$existing['DB_DATABASE'].';charset=utf8mb4';
    new PDO($dsn, $existing['DB_USERNAME'], $existing['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "db_login=ok\n";
} catch (PDOException $exception) {
    $state = $exception->errorInfo[0] ?? 'unknown';
    echo "db_login=failed sqlstate={$state}\n";
    echo "shared_env=ready\n";
    exit(0);
}

echo "shared_env=ready\n";
PHP
}

env_log="$(write_env)"
printf '%s\n' "$env_log"
db_failed=0
if grep -q '^db_login=failed' <<< "$env_log"; then
  db_failed=1
fi

if [[ "$ACTION" == "rollback" ]]; then
  script="${BASE}/laravel/current/scripts/release.sh"
  if [[ ! -f "$script" ]]; then
    echo "missing=current_release" >&2
    exit 1
  fi
  WORXEVENTS_BASE="$BASE" ACTION=rollback bash "$script"
  echo "rolled_back=yes"
  exit 0
fi

archive="${incoming}/${RELEASE}.tar.gz"
if [[ ! -f "$archive" ]]; then
  echo "missing=release_archive" >&2
  exit 1
fi

work="$(mktemp -d)"
cleanup() {
  rm -rf "$work"
}
trap cleanup EXIT
tar -xzf "$archive" -C "$work"

if [[ ! -f "${work}/scripts/release.sh" ]]; then
  echo "missing=release_script" >&2
  exit 1
fi

WORXEVENTS_BASE="$BASE" ACTION=deploy RELEASE="$RELEASE" SOURCE="$work" bash "${work}/scripts/release.sh"

if grep -q '^db_login=ok$' <<< "$env_log"; then
  if ! command -v mysqldump >/dev/null 2>&1 || ! command -v gzip >/dev/null 2>&1; then
    echo "missing=mysqldump"
    echo "migrated=skipped"
    exit 1
  fi
  cnf="$(mktemp)"
  chmod 600 "$cnf"
  WE_ENV_PATH="${shared}/.env" WE_CNF="$cnf" "$php_bin" << 'PHP'
<?php
$path = getenv('WE_ENV_PATH');
$values = [];
foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
    if ($line === '' || ! str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $values[$key] = trim($value, " \t\"'");
}
$cnf = getenv('WE_CNF');
$body = "[client]\nuser=\"".$values['DB_USERNAME']."\"\npassword=\"".$values['DB_PASSWORD']."\"\nhost=\"".($values['DB_HOST'] ?? 'localhost')."\"\nport=".($values['DB_PORT'] ?? '3306')."\n";
file_put_contents($cnf, $body);
chmod($cnf, 0600);
PHP
  database_name="$(WE_ENV_PATH="${shared}/.env" WE_CNF="$cnf" "$php_bin" << 'PHP'
<?php
$path = getenv('WE_ENV_PATH');
foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'DB_DATABASE=')) {
        echo trim(substr($line, 12), " \t\"'");
        exit(0);
    }
}
PHP
)"
  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  dump="${shared}/backups/${stamp}.sql.gz"
  if ! mysqldump --defaults-extra-file="$cnf" --single-transaction --no-tablespaces "$database_name" | gzip > "$dump"; then
    rm -f "$cnf" "$dump"
    echo "backup=failed"
    exit 1
  fi
  rm -f "$cnf"
  if ! gzip -t "$dump"; then
    rm -f "$dump"
    echo "backup=failed"
    exit 1
  fi
  echo "backup=ok"
  "$php_bin" "${BASE}/laravel/current/artisan" migrate --force --no-interaction
  echo "migrated=yes"
else
  echo "migrated=skipped"
fi

echo "manual=cron"
echo "manual=https_enforce"
echo "deployed=${RELEASE}"
if [[ "$db_failed" -eq 1 ]]; then
  exit 1
fi
