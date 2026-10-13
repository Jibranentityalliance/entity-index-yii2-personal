#!/usr/bin/env bash
# Deploys the app to a server over SFTP (key authentication) using lftp.
#
# Required environment:
#   SFTP_HOST, SFTP_USERNAME, SFTP_KEY_FILE (private key path), SFTP_KNOWN_HOSTS_FILE (known_hosts path),
#   SFTP_REMOTE_PATH (absolute path of the project root on the server; its web/ dir is the Apache DocumentRoot)
# Optional:
#   SFTP_PORT (default 22), DEPLOY_DRY_RUN=1 (list what would change, upload nothing)
#
# Never uploaded, never deleted on the server (the server owns them):
#   .env*, config/db.php, runtime/, web/assets/
# Not uploaded at all: .git*, .github, tests/, docs/, scripts/, data/sample/, README, local tooling.
# vendor/ is mirrored only when composer.lock changed since the last deploy (marker: .deploy-vendor-hash).
# SFTP cannot run commands: run `php yii migrate` / `php yii cache/flush-all` on the server when needed.
set -euo pipefail

: "${SFTP_HOST:?}" "${SFTP_USERNAME:?}" "${SFTP_KEY_FILE:?}" "${SFTP_KNOWN_HOSTS_FILE:?}" "${SFTP_REMOTE_PATH:?}"
PORT="${SFTP_PORT:-22}"
DRY="${DEPLOY_DRY_RUN:-}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ ! "$SFTP_REMOTE_PATH" =~ ^/[^/].* ]]; then
  echo "SFTP_REMOTE_PATH must be an absolute path other than / (got '$SFTP_REMOTE_PATH')" >&2; exit 2
fi
[[ -f "$ROOT/vendor/autoload.php" ]] || { echo "vendor/ is missing: run 'composer install --no-dev --optimize-autoloader' first" >&2; exit 2; }
[[ -f "$ROOT/web/index.php" ]] || { echo "web/index.php not found under $ROOT" >&2; exit 2; }
chmod 600 "$SFTP_KEY_FILE"

STAGE="$(mktemp -d)"
STAGE_VENDOR="$(mktemp -d)"
trap 'rm -rf "$STAGE" "$STAGE_VENDOR"' EXIT

# 1. Stage the application files (everything except vendor/ and the exclusions above).
rsync -a --delete \
  --exclude='/.git*' --exclude='/.github/' --exclude='/tests/' --exclude='/docs/' --exclude='/scripts/' \
  --exclude='/data/sample/' --exclude='/vendor/' --exclude='/.env*' --exclude='/config/db.php' \
  --exclude='/runtime/' --exclude='/web/assets/' --exclude='/.editorconfig' --exclude='/README.md' \
  --exclude='/phpcs.xml.dist' --exclude='/phpstan.neon' --exclude='/codeception.yml' --exclude='/.bowerrc' \
  --exclude='/LICENSE.md' --exclude='/yii.bat' --exclude='/web/index-test.php' \
  "$ROOT"/ "$STAGE"/

SSH_CMD="ssh -a -x -i $SFTP_KEY_FILE -o IdentitiesOnly=yes -o BatchMode=yes -o PasswordAuthentication=no \
-o StrictHostKeyChecking=yes -o UserKnownHostsFile=$SFTP_KNOWN_HOSTS_FILE -p $PORT"

# Paths owned by the server: excluded from the mirror (so --delete never touches them).
KEEP=(-x '^\.env' -x '^config/db\.php$' -x '^runtime/' -x '^web/assets/' -x '^vendor/' -x '^\.deploy-vendor-hash$')
MIRROR_OPTS=(--reverse --delete --no-perms --parallel=4 --verbose=1)
[[ -n "$DRY" ]] && MIRROR_OPTS+=(--dry-run)

lftp_run() {
  lftp -e "set cmd:fail-exit yes; set net:max-retries 3; set net:timeout 30; set sftp:auto-confirm no; \
set sftp:connect-program \"$SSH_CMD\"; $1; bye" "sftp://${SFTP_USERNAME}:@${SFTP_HOST}"
}

# 2. Does the remote project dir exist? (never create it: a typo'd path should fail loudly)
lftp_run "cd '$SFTP_REMOTE_PATH'" >/dev/null

echo "== Application files =="
lftp_run "cd '$SFTP_REMOTE_PATH'; mirror ${MIRROR_OPTS[*]} ${KEEP[*]} '$STAGE' ."
if [[ -z "$DRY" ]]; then
  # Directories the server writes to; harmless if they already exist.
  lftp_run "cd '$SFTP_REMOTE_PATH'; set cmd:fail-exit no; mkdir -p runtime; mkdir -p web/assets" >/dev/null 2>&1 || true
fi

# 3. vendor/: only when composer.lock changed.
LOCK_HASH="$(sha256sum "$ROOT/composer.lock" | cut -d' ' -f1)"
REMOTE_HASH=""
if lftp_run "cd '$SFTP_REMOTE_PATH'; get -O '$STAGE' .deploy-vendor-hash -o remote-vendor-hash" >/dev/null 2>&1; then
  REMOTE_HASH="$(tr -d '[:space:]' < "$STAGE/remote-vendor-hash")"
fi
if [[ "$REMOTE_HASH" == "$LOCK_HASH" ]]; then
  echo "== vendor/ unchanged (composer.lock hash matches), skipped =="
else
  echo "== vendor/ (composer.lock changed) =="
  # Strip VCS metadata that composer leaves behind when it installs from source.
  rsync -a --exclude='.git/' --exclude='.git' "$ROOT/vendor/" "$STAGE_VENDOR/"
  lftp_run "cd '$SFTP_REMOTE_PATH'; mirror ${MIRROR_OPTS[*]} '$STAGE_VENDOR' vendor"
  if [[ -z "$DRY" ]]; then
    echo "$LOCK_HASH" > "$STAGE/.deploy-vendor-hash"
    lftp_run "cd '$SFTP_REMOTE_PATH'; put -O . '$STAGE/.deploy-vendor-hash'"
  fi
fi

echo "== Deploy finished${DRY:+ (dry run)} =="
echo "Reminder: run 'php yii migrate --interactive=0' and 'php yii cache/flush-all' on the server if this release has new migrations."
