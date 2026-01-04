#!/bin/sh

echo "Starting RoomCtrl API in PROD mode..."

echo "Stopping existing containers..."
docker compose -f docker-compose.prod.yml down

echo "Building Docker images..."
docker compose -f docker-compose.prod.yml build --no-cache

echo "Starting Docker containers..."
docker compose -f docker-compose.prod.yml up -d

echo "Waiting for containers to be ready..."
sleep 10

if [ ! -f "config/jwt/private.pem" ] || [ ! -f "config/jwt/public.pem" ]; then
    echo "JWT keys not found. Generating..."
    docker compose -f docker-compose.prod.yml exec php php bin/console lexik:jwt:generate-keypair
    echo "JWT keys generated successfully!"
else
    echo "JWT keys already exist"
fi

echo "Setting up database..."
docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:database:drop --force
docker compose -f docker-compose.prod.yml exec php rm -f migrations/*.php
docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:database:create
docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:diff
docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate --no-interaction

echo "Production environment is ready!"