#!/bin/bash
set -e

echo "=== Turtle Update ==="
git pull
docker compose up -d --build
echo "Done!"
