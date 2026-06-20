#!/bin/bash
set -e

echo "=== Turtle Update Script ==="
echo "Pulling latest changes..."
git pull origin main

echo "Building and restarting containers..."
docker compose down
docker compose up -d --build

echo "Installing dependencies..."
docker compose exec -T app composer install --no-interaction --prefer-dist

echo "Running migrations..."
docker compose exec -T app php artisan migrate --force

echo "Clearing cache..."
docker compose exec -T app php artisan optimize:clear

echo "Building frontend assets..."
docker compose exec -T app npm install --silent
docker compose exec -T app npm run build

echo "Update complete!"
