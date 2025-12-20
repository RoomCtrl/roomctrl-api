#!/bin/bash

set -e

echo "Starting RoomCtrl API in DEVELOPMENT mode..."
echo "================================================"

if [ ! -f .env.dev ]; then
    if [ ! -f .env.dev.example ]; then
        echo "Error: .env.dev.example file not found!"
        echo "Please create .env.dev.example file first"
        exit 1
    fi
    echo "Creating .env.dev from .env.dev.example..."
    cp .env.dev.example .env.dev
    echo "Created .env.dev - please review and update the configuration if needed"
fi

cp .env.dev .env
echo "Loaded development environment variables"

echo "Stopping existing containers..."
docker compose -f docker-compose.dev.yml down

echo "Building containers..."
docker compose -f docker-compose.dev.yml build --no-cache

echo "Starting containers..."
docker compose -f docker-compose.dev.yml up -d

echo "Waiting for database to be ready..."
sleep 5

echo "Installing Composer dependencies..."
docker compose -f docker-compose.dev.yml exec -T roomctrl-php composer install

if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT keys..."
    docker compose -f docker-compose.dev.yml exec -T roomctrl-php php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

echo "Running database migrations..."
docker compose -f docker-compose.dev.yml exec -T roomctrl-php php bin/console doctrine:migrations:migrate --no-interaction

read -p "Load database fixtures? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Loading fixtures..."
    docker compose -f docker-compose.dev.yml exec -T roomctrl-php php bin/console doctrine:fixtures:load --no-interaction
fi

echo "Clearing cache..."
docker compose -f docker-compose.dev.yml exec -T roomctrl-php php bin/console cache:clear

echo ""
echo "================================================"
echo "Development environment is ready!"
echo "================================================"
echo ""
echo "Services available at:"
echo "   - API: http://localhost:8080"
echo "   - MailHog: http://localhost:8025"
echo "   - pgAdmin: http://localhost:5050"
echo "   - PostgreSQL: localhost:5432"
echo ""
echo "Useful commands:"
echo "   - View logs: docker compose -f docker-compose.dev.yml logs -f"
echo "   - Stop: docker compose -f docker-compose.dev.yml down"
echo "   - Restart: docker compose -f docker-compose.dev.yml restart"
echo "   - Shell: docker compose -f docker-compose.dev.yml exec roomctrl-php bash"
echo ""
