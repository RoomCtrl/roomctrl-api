#!/bin/sh

echo "Starting RoomCtrl API in DEV mode..."

echo "Stopping existing containers..."
docker compose down

echo "Building Docker images..."
docker compose build --no-cache

echo "Starting Docker containers..."
docker compose up -d

echo "Waiting for containers to be ready..."
sleep 10

echo "Installing Composer dependencies..."
docker compose exec php composer install --no-interaction --prefer-dist

if [ ! -f "config/jwt/private.pem" ] || [ ! -f "config/jwt/public.pem" ]; then
    echo "JWT keys not found. Generating..."
    docker compose exec php php bin/console lexik:jwt:generate-keypair
    echo "JWT keys generated successfully!"
else
    echo "JWT keys already exist"
fi

echo "Setting up database..."
docker compose exec php php bin/console doctrine:database:drop --force
docker compose exec php rm -f migrations/*.php
docker compose exec php php bin/console doctrine:database:create
docker compose exec php php bin/console doctrine:migrations:diff
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

echo "Development environment is ready!"
echo "API Documentation: http://localhost:8080/api/doc"
