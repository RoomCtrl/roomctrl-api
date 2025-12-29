#!/bin/sh
set -e

# Fix uploads directory permissions
if [ -d "/var/www/html/public/uploads/rooms" ]; then
    chown -R www-data:www-data /var/www/html/public/uploads/rooms
    chmod -R 775 /var/www/html/public/uploads/rooms
fi

# Execute the main command
exec "$@"
