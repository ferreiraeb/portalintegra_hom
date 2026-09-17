#!/bin/bash
set -e

mkdir -p /var/www/html/storage/sessions
chown -R www-data:www-data /var/www/html/storage/sessions 2>/dev/null || true
chmod 0770 /var/www/html/storage/sessions 2>/dev/null || true

touch /var/log/sync_ad.log /var/log/birthday_emails.log
chown www-data:www-data /var/log/sync_ad.log /var/log/birthday_emails.log 2>/dev/null || true

cron

exec apache2-foreground
