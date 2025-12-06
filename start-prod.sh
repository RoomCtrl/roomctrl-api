#!/bin/bash

set -e

echo "Starting RoomCtrl API in PRODUCTION mode..."
echo "================================================"

if [ ! -f .env.prod ]; then
    if [ ! -f .env.prod.example ]; then
        echo "Error: .env.prod.example file not found!"
        echo "Please create .env.prod.example file first"
        exit 1
    fi
    echo "Error: .env.prod not found!"
    echo "Please create .env.prod file based on .env.prod.example"
    echo "NEVER use example values in production - set strong passwords and secrets!"
    exit 1
fi

if grep -q "CHANGE-THIS" .env.prod; then
    echo "Error: Please replace all CHANGE-THIS placeholders in .env.prod with strong passwords!"
    exit 1
fi

cp .env.prod .env
echo "Loaded production environment variables"

echo "Building production containers..."
export BUILD_DATE=$(date -u +'%Y-%m-%dT%H:%M:%SZ')
export VCS_REF=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")

docker compose -f docker-compose.prod.yml build --no-cache \
    --build-arg BUILD_DATE="$BUILD_DATE" \
    --build-arg VCS_REF="$VCS_REF"

echo "Starting production containers..."
docker compose -f docker-compose.prod.yml up -d

echo "Waiting for database to be ready..."
sleep 10

echo "Running database migrations..."
docker compose -f docker-compose.prod.yml exec -T roomctrl-php php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "Verifying installation..."
docker compose -f docker-compose.prod.yml ps

echo ""
echo "================================================"
echo "Production environment is ready!"
echo "================================================"
echo ""
echo "Services available at:"
echo "   - API: http://localhost (or https with SSL)"
echo ""
echo "Important production notes:"
echo "   1. Configure SSL certificates in docker/nginx/ssl/"
echo "   2. Set up proper domain in docker/nginx/default.prod.conf"
echo "   3. Configure backup schedule"
echo "   4. Monitor logs regularly"
echo "   5. Keep sensitive data in .env.prod secure"
echo ""
echo "Useful commands:"
echo "   - View logs: docker compose -f docker-compose.prod.yml logs -f"
echo "   - Stop: docker compose -f docker-compose.prod.yml down"
echo "   - Restart: docker compose -f docker-compose.prod.yml restart"
echo "   - Backup DB: docker compose -f docker-compose.prod.yml exec roomctrl-postgres pg_dump -U \$POSTGRES_USER \$POSTGRES_DB > backup.sql"
echo ""
