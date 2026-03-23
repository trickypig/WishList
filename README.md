# WishList

## Install Locally
# - Initialize databases
php api/migrations/migrate.php
# - Install Node
cd frontend
npm install

## Running Locally
# - Start the PHP API engine
cd api && php -S localhost:8080 -t .
# - Start front end
cd frontend
npm run dev
# - Run locally
http://localhost:5173

## MAUI app -- Production
dotnet publish -f net10.0-windows10.0.19041.0 -c Release
