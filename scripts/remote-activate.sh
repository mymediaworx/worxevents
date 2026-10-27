#!/usr/bin/env bash
# Run on the server. Creates what it can under the site directory and deploys
# one archive. Secrets are read from the environment and written to
# laravel/shared/.env. This script does not print those values.
set -euo pipefail

# An empty APP_KEY in the process environment hides the key in shared/.env.
# Keep a copy for the writer, then drop it so artisan and HTTP use the file.
incoming_app_key="${APP_KEY:-}"
unset APP_KEY || true

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

# One test message and one password reset the first time SMTP is active.
# Later deploys leave the existing link alone.
probe_mail() {
  local mailer probe mail_status reset_status
  mailer="$(sed -n 's/^MAIL_MAILER=//p' "${shared}/.env" | head -n 1)"
  mailer="${mailer%\"}"
  mailer="${mailer#\"}"
  if [[ "$mailer" != "smtp" ]]; then
    return 0
  fi
  probe="${shared}/mail.probe"
  if [[ -f "$probe" ]]; then
    echo "mail_probe=already"
    return 0
  fi
  set +e
  "$php_bin" "${BASE}/laravel/current/artisan" worxevents:mail-test michael@brandworx.ai
  mail_status=$?
  "$php_bin" "${BASE}/laravel/current/artisan" worxevents:password-reset michael@brandworx.ai
  reset_status=$?
  set -e
  if [[ "$mail_status" -eq 0 && "$reset_status" -eq 0 ]]; then
    date -u +%Y-%m-%dT%H:%M:%SZ > "$probe"
    chmod 600 "$probe"
    echo "mail_probe=sent"
    return 0
  fi
  echo "mail_probe=failed"
  return 1
}

write_env() {
  WE_ENV_PATH="${shared}/.env" "$php_bin" << 'PHP'
<?php

function we_unescape(string $raw): string
{
    $raw = trim($raw);
    if (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"')) {
        $inner = substr($raw, 1, -1);
        $out = '';
        $length = strlen($inner);
        for ($i = 0; $i < $length; $i++) {
            if ($inner[$i] === '\\' && $i + 1 < $length) {
                $next = $inner[++$i];
                $out .= match ($next) {
                    'n' => "\n",
                    'r' => "\r",
                    default => $next,
                };
                continue;
            }
            $out .= $inner[$i];
        }

        return $out;
    }

    return trim($raw, " \t'");
}

function we_quote(string $value): string
{
    return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value).'"';
}

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
        $existing[$key] = we_unescape($value);
    }
}

// Database and mail secrets replace the previous values whenever they are
// provided. An empty value is ignored so a missing secret cannot blank the
// server. APP_KEY is filled only when the server does not already have one,
// so a later deploy does not invalidate sessions or encrypted columns.
foreach ([
    'DB_HOST' => (string) getenv('DB_HOST'),
    'DB_PORT' => (string) getenv('DB_PORT'),
    'DB_DATABASE' => (string) getenv('DB_DATABASE'),
    'DB_USERNAME' => (string) getenv('DB_USERNAME'),
    'DB_PASSWORD' => (string) getenv('DB_PASSWORD'),
    'MAIL_HOST' => (string) getenv('MAIL_HOST'),
    'MAIL_PORT' => (string) getenv('MAIL_PORT'),
    'MAIL_USERNAME' => (string) getenv('MAIL_USERNAME'),
    'MAIL_PASSWORD' => (string) getenv('MAIL_PASSWORD'),
    'MAIL_FROM_ADDRESS' => (string) getenv('MAIL_FROM_ADDRESS'),
    'MAIL_FROM_NAME' => (string) getenv('MAIL_FROM_NAME'),
    'MAIL_SCHEME' => (string) getenv('MAIL_SCHEME'),
] as $key => $value) {
    if ($value !== '') {
        $existing[$key] = $value;
    }
}

$incomingKey = (string) getenv('WE_APP_KEY');
if (($existing['APP_KEY'] ?? '') === '' && $incomingKey !== '') {
    $existing['APP_KEY'] = $incomingKey;
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
    'LOG_CHANNEL' => 'daily',
    'LOG_STACK' => 'daily',
    'LOG_DAILY_DAYS' => '7',
    'LOG_LEVEL' => 'error',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'SESSION_DRIVER' => 'file',
    'SESSION_LIFETIME' => '120',
    'SESSION_SECURE_COOKIE' => 'true',
    'SESSION_HTTP_ONLY' => 'true',
    'SESSION_SAME_SITE' => 'lax',
    'QUEUE_CONNECTION' => 'sync',
    'DB_QUEUE_TABLE' => 'background_jobs',
    'QUEUE_WORK_MAX_TIME' => '20',
    'CACHE_STORE' => 'file',
    'MAIL_MAILER' => 'log',
    'MAIL_LOG_CHANNEL' => 'mail',
    'MAIL_FROM_NAME' => 'WorxEvents',
    'PLATFORM_DOMAINS' => 'app.worxevents.com',
    'WORXEVENTS_APEX' => 'worxevents.com',
    'PAYMENTS_GATEWAY' => 'stripe',
];

$smtpReady = ($existing['MAIL_HOST'] ?? '') !== ''
    && ($existing['MAIL_PORT'] ?? '') !== ''
    && ($existing['MAIL_USERNAME'] ?? '') !== ''
    && ($existing['MAIL_PASSWORD'] ?? '') !== ''
    && ($existing['MAIL_FROM_ADDRESS'] ?? '') !== '';

if ($smtpReady) {
    $existing['MAIL_MAILER'] = 'smtp';
    $scheme = $existing['MAIL_SCHEME'] ?? '';
    if ($scheme === '' || $scheme === 'null') {
        $existing['MAIL_SCHEME'] = ((string) $existing['MAIL_PORT'] === '465') ? 'smtps' : 'smtp';
    }
}

// Inode retention: always prefer daily logs with a 7-day window on the server.
$existing['LOG_CHANNEL'] = 'daily';
$existing['LOG_STACK'] = 'daily';
$existing['LOG_DAILY_DAYS'] = '7';

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
    $lines[] = $key.'='.we_quote((string) $value);
}

file_put_contents($path, implode("\n", $lines)."\n");
chmod($path, 0600);

foreach (['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    if (($existing[$key] ?? '') === '') {
        echo "missing={$key}\n";
    }
}

echo $smtpReady ? "mail=smtp\n" : "manual=smtp\n";

if (! $dbReady) {
    echo "db_login=skipped\n";
    echo "shared_env=ready\n";
    exit(0);
}

$write = static function () use (&$existing, $path): void {
    $lines = [];
    foreach ($existing as $key => $value) {
        if (! preg_match('/^[A-Z0-9_]+$/', $key)) {
            continue;
        }
        $lines[] = $key.'='.we_quote((string) $value);
    }
    file_put_contents($path, implode("\n", $lines)."\n");
    chmod($path, 0600);
};

try {
    $dsn = 'mysql:host='.$existing['DB_HOST'].';port='.$existing['DB_PORT'].';dbname='.$existing['DB_DATABASE'].';charset=utf8mb4';
    new PDO($dsn, $existing['DB_USERNAME'], $existing['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $existing['SESSION_DRIVER'] = 'database';
    $existing['CACHE_STORE'] = 'database';
    $existing['QUEUE_CONNECTION'] = 'database';
    $write();
    echo "db_login=ok\n";
} catch (PDOException $exception) {
    $write();
    $state = $exception->errorInfo[0] ?? 'unknown';
    echo "db_login=failed sqlstate={$state}\n";
    echo "shared_env=ready\n";
    exit(0);
}

echo "shared_env=ready\n";
PHP
}

env_log="$(WE_APP_KEY="$incoming_app_key" write_env)"
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

prototype_failed=0
if grep -q '^db_login=ok$' <<< "$env_log"; then
  if ! command -v mysqldump >/dev/null 2>&1 || ! command -v gzip >/dev/null 2>&1; then
    echo "missing=mysqldump"
    echo "migrated=skipped"
    prototype_failed=1
  else
  cnf="$(mktemp)"
  chmod 600 "$cnf"
  set +e
  database_name="$(WE_ENV_PATH="${shared}/.env" WE_CNF="$cnf" "$php_bin" << 'PHP'
<?php
function we_unescape(string $raw): string
{
    $raw = trim($raw);
    if (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"')) {
        $inner = substr($raw, 1, -1);
        $out = '';
        $length = strlen($inner);
        for ($i = 0; $i < $length; $i++) {
            if ($inner[$i] === '\\' && $i + 1 < $length) {
                $next = $inner[++$i];
                $out .= match ($next) {
                    'n' => "\n",
                    'r' => "\r",
                    default => $next,
                };
                continue;
            }
            $out .= $inner[$i];
        }

        return $out;
    }

    return trim($raw, " \t'");
}

function we_quote(string $value): string
{
    return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value).'"';
}

$path = getenv('WE_ENV_PATH');
$values = [];
foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $values[$key] = we_unescape($value);
}
$name = (string) ($values['DB_DATABASE'] ?? '');
if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
    fwrite(STDERR, "backup=failed\n");
    exit(1);
}
$port = (string) ($values['DB_PORT'] ?? '3306');
if (! preg_match('/^[0-9]+$/', $port)) {
    fwrite(STDERR, "backup=failed\n");
    exit(1);
}
$cnf = getenv('WE_CNF');
$body = "[client]\nuser=".we_quote((string) ($values['DB_USERNAME'] ?? ''))."\npassword=".we_quote((string) ($values['DB_PASSWORD'] ?? ''))."\nhost=".we_quote((string) ($values['DB_HOST'] ?? 'localhost'))."\nport=".$port."\n";
file_put_contents($cnf, $body);
chmod($cnf, 0600);
echo $name, "\n";
PHP
)"
  dump_status=$?
  set -e
  if [[ "$dump_status" -ne 0 || ! "$database_name" =~ ^[A-Za-z0-9_]+$ ]]; then
    rm -f "$cnf"
    echo "backup=failed"
    prototype_failed=1
  else
    stamp="$(date -u +%Y%m%dT%H%M%SZ)"
    dump="${shared}/backups/${stamp}.sql.gz"
    set +e
    mysqldump --defaults-extra-file="$cnf" --single-transaction --no-tablespaces "$database_name" | gzip > "$dump"
    dump_status=$?
    set -e
    rm -f "$cnf"
    if [[ "$dump_status" -ne 0 ]] || ! gzip -t "$dump"; then
      rm -f "$dump"
      echo "backup=failed"
      prototype_failed=1
    else
      echo "backup=ok"
      set +e
      "$php_bin" "${BASE}/laravel/current/artisan" migrate --force --no-interaction
      migrate_status=$?
      set -e
      if [[ "$migrate_status" -ne 0 ]]; then
        echo "migrated=failed"
        prototype_failed=1
      else
        echo "migrated=yes"
        set +e
        "$php_bin" "${BASE}/laravel/current/artisan" worxevents:platform-admin michael@brandworx.ai --name="Michael"
        admin_status=$?
        "$php_bin" "${BASE}/laravel/current/artisan" worxevents:demo
        demo_status=$?
        set -e
        if [[ "$admin_status" -ne 0 || "$demo_status" -ne 0 ]]; then
          echo "seeded=failed"
          prototype_failed=1
        else
          echo "seeded=yes"
          set +e
          render_log="$(
            cd "${BASE}/laravel/current"
            "$php_bin" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $logPath = null; try { $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class); $logPath = storage_path("logs/laravel.log"); $before = is_file($logPath) ? filesize($logPath) : 0; $request = Illuminate\Http\Request::create("https://app.worxevents.com/", "GET"); $response = $kernel->handle($request); $body = (string) $response->getContent(); echo "home_status=".$response->getStatusCode()."\n"; echo (str_contains($body, "Demo Community Night") ? "demo_event=visible\n" : "demo_event=missing\n"); echo (str_contains($body, "Demo Paid Showcase") ? "paid_event=visible\n" : "paid_event=missing\n"); if ($response->getStatusCode() >= 500) { $chunk = ""; if (is_file($logPath)) { $handle = fopen($logPath, "rb"); if ($handle !== false) { fseek($handle, $before); $chunk = stream_get_contents($handle) ?: ""; fclose($handle); } } $class = "unknown"; if (preg_match("/([A-Za-z0-9_\\\\]+(?:Exception|Error))/", $chunk, $match)) { $class = $match[1]; } echo "render_error=".$class."\n"; } } catch (Throwable $e) { echo "home_status=error\nrender_error=".get_class($e)."\ndemo_event=missing\npaid_event=missing\n"; }'
          )"
          render_status=$?
          set -e
          printf '%s\n' "$render_log"
          if [[ "$render_status" -ne 0 ]] || ! grep -q '^home_status=200$' <<< "$render_log" || ! grep -q '^demo_event=visible$' <<< "$render_log" || ! grep -q '^paid_event=visible$' <<< "$render_log"; then
            echo "prototype=incomplete"
            prototype_failed=1
          fi
        fi
      fi
    fi
  fi
  fi
else
  echo "migrated=skipped"
fi

if grep -q '^db_login=ok$' <<< "$env_log" && [[ -f "${BASE}/laravel/current/artisan" ]]; then
  if ! probe_mail; then
    prototype_failed=1
  fi
fi

cron_line="* * * * * ${php_bin} ${BASE}/laravel/current/artisan schedule:run >> /dev/null 2>&1"
echo "cron_line=${cron_line}"
current_cron="$(crontab -l 2>/dev/null || true)"
if printf '%s\n' "$current_cron" | grep -F 'artisan schedule:run' >/dev/null 2>&1; then
  echo "cron=present"
elif printf '%s\n%s\n' "$current_cron" "$cron_line" | crontab - 2>/dev/null; then
  echo "cron=installed"
else
  echo "manual=cron"
  echo "SiteGround Site Tools → Devs → Cron Jobs, every minute. Command:"
  echo "cron_command=${php_bin} ${BASE}/laravel/current/artisan schedule:run >> /dev/null 2>&1"
fi
echo "https=app"
echo "deployed=${RELEASE}"
if [[ "$db_failed" -eq 1 || "$prototype_failed" -eq 1 ]]; then
  exit 1
fi
