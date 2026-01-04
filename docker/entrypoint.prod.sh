#!/bin/sh
set -e

# Fix permissions for mounted volumes
chown -R www-data:www-data /var/www/html/public/uploads
chmod -R 775 /var/www/html/public/uploads
chown -R www-data:www-data /var/www/html/var
chmod -R 777 /var/www/html/var

# Execute the main command
exec "$@"
