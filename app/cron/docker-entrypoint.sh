#!/bin/bash
set -e

# Start cron daemon in the background
service cron start

# Hand off to Apache in the foreground (keeps the container alive)
exec apache2-foreground
