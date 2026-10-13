#!/usr/bin/env bash
# Orchestrates a production -> staging content refresh.
# PRODUCTION IS READ-ONLY: only `mysqldump` and an rsync PULL ever touch it.
# Every step is overridable via an env var so it can be tested offline.
set -euo pipefail

STAGING_SSH=${STAGING_SSH:-staging-host}
STAGING_DB_SSH=${STAGING_DB_SSH:-staging-db-host}
PROD_SSH=${PROD_SSH:-prod-host}
DUMP=${DUMP:-/tmp/flarum-prod.sql.gz}
FLARUM=/var/www/flarum

STOP_WORKER_CMD=${STOP_WORKER_CMD:-"ssh $STAGING_SSH 'sudo systemctl stop flarum-queue || true'"}
ENV_CHECK_CMD=${ENV_CHECK_CMD:-"ssh $STAGING_SSH 'bash $FLARUM/scripts/staging-env-check.sh $FLARUM/config.php'"}
DUMP_CMD=${DUMP_CMD:-"ssh $PROD_SSH 'mysqldump flarum | gzip' > $DUMP"}
IMPORT_CMD=${IMPORT_CMD:-"gunzip -c $DUMP | ssh $STAGING_DB_SSH 'mysql flarum'"}
SCRUB_CMD=${SCRUB_CMD:-"ssh $STAGING_SSH 'cd $FLARUM && sudo -u www-data php scripts/staging-scrub.php $FLARUM/config.php'"}
RSYNC_CMD=${RSYNC_CMD:-"rsync -az $PROD_SSH:$FLARUM/public/assets/files/ $STAGING_SSH:$FLARUM/public/assets/files/"}
MIGRATE_CMD=${MIGRATE_CMD:-"ssh $STAGING_SSH 'cd $FLARUM && sudo -u www-data php flarum migrate && sudo -u www-data php flarum cache:clear'"}
BACKUP_CMD=${BACKUP_CMD:-"ssh $STAGING_DB_SSH 'mkdir -p /root/staging-db-backups && mysqldump flarum | gzip > /root/staging-db-backups/flarum-\$(date +%Y%m%d-%H%M%S).sql.gz'"}
START_WORKER_CMD=${START_WORKER_CMD:-"ssh $STAGING_SSH 'sudo systemctl start flarum-queue || true'"}

step() { echo "== $1"; shift; eval "$1"; }

step stop-worker  "$STOP_WORKER_CMD"    # no prod job may dispatch mid-refresh
step env-check    "$ENV_CHECK_CMD"      # staging config must be prod-shaped first
step dump-prod    "$DUMP_CMD"           # READ-ONLY
step backup       "$BACKUP_CMD"         # staging's own DB, pre-import
step import       "$IMPORT_CMD"
step scrub        "$SCRUB_CMD"          # truncates jobs BEFORE the worker resumes
step assets       "$RSYNC_CMD"          # READ-ONLY pull from prod
step migrate      "$MIGRATE_CMD"
step start-worker "$START_WORKER_CMD"
echo "REFRESH OK"
