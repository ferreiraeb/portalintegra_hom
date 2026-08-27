#!/bin/bash
set -e

touch /var/log/sync_ad.log /var/log/birthday_emails.log
chown www-data:www-data /var/log/sync_ad.log /var/log/birthday_emails.log 2>/dev/null || true

cron

exec apache2-foreground
