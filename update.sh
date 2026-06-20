#!/bin/bash
set -e

echo "=== Turtle Update Script ==="
echo "Pulling latest changes..."
git pull origin main

echo "Building and restarting containers..."
docker compose down
docker compose up -d --build

echo "Running migrations..."
docker compose exec -T laravel.test php artisan migrate --force

echo "Clearing cache..."
docker compose exec -T laravel.test php artisan optimize:clear

echo "Update complete!"
