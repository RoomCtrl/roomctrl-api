#!/bin/bash

set -e

echo "Starting RoomCtrl API..."
echo "================================================"

if [ ! -f .env ]; then
    if [ ! -f .env.example ]; then
        echo "Error: .env.example file not found!"
        echo "Please create .env.example file first"
        exit 1
    fi
    echo "Creating .env from .env.example..."
    cp .env.example .env
    echo "Created .env - please review and update the configuration if needed"
fi

echo "Using environment variables from .env"

echo "Stopping existing containers..."
docker compose down

echo "Building containers (using cache)..."
export DOCKER_BUILDKIT=1
export COMPOSE_DOCKER_CLI_BUILD=1
docker compose build

echo "Starting containers..."
docker compose up -d

echo "Waiting for database to be ready..."
sleep 5

echo "Installing Composer dependencies..."
docker compose exec -T roomctrl-php composer install

if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT keys..."
    docker compose exec -T roomctrl-php php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

echo "Running database migrations..."
docker compose exec -T roomctrl-php php bin/console doctrine:migrations:migrate --no-interaction

read -p "Load database fixtures? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Loading fixtures..."
    docker compose exec -T roomctrl-php php bin/console doctrine:fixtures:load --no-interaction
fi

echo "Clearing cache..."
docker compose exec -T roomctrl-php php bin/console cache:clear

echo ""
echo "================================================"
echo "Environment is ready!"
echo "================================================"
echo ""
echo "Services available at:"
echo "   - API: http://localhost:8080"
echo "   - MailHog: http://localhost:8025"
echo "   - pgAdmin: http://localhost:5050"
echo "   - PostgreSQL: localhost:5432"
echo ""
echo "Useful commands:"
echo "   - View logs: docker compose logs -f"
echo "   - Stop: docker compose down"
echo "   - Restart: docker compose restart"
echo "   - Shell: docker compose exec roomctrl-php bash"
echo ""
