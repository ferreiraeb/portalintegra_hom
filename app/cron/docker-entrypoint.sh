#!/bin/bash
set -e

# CSV de composição, sessões e uploads Massey precisam ser graváveis por www-data.
mkdir -p /var/www/html/storage/sessions \
         /var/www/html/storage/rh \
         /var/www/html/storage/massey
chown -R www-data:www-data /var/www/html/storage 2>/dev/null || true
chmod -R 0775 /var/www/html/storage 2>/dev/null || true

touch /var/log/sync_ad.log /var/log/birthday_emails.log
chown www-data:www-data /var/log/sync_ad.log /var/log/birthday_emails.log 2>/dev/null || true

cron

exec apache2-foreground
