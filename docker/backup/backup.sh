#!/bin/bash

set -e

BACKUP_DIR="/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/backup_$TIMESTAMP.sql.gz"

echo "Starting database backup..."

# Create backup
pg_dump -h roomctrl-postgres -U "$POSTGRES_USER" "$POSTGRES_DB" | gzip > "$BACKUP_FILE"

echo "Backup created: $BACKUP_FILE"

# Clean old backups
find "$BACKUP_DIR" -name "backup_*.sql.gz" -mtime +${BACKUP_KEEP_DAYS:-7} -delete

echo "Old backups cleaned up"
echo "Backup completed successfully!"
