#!/bin/bash
set -e

touch /var/log/sync_ad.log
chown www-data:www-data /var/log/sync_ad.log 2>/dev/null || true

cron

exec apache2-foreground
