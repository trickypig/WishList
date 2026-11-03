# WishList - Shared Family Wish Lists

A family wish list app where users create and share gift lists with family members. Features purchase tracking (so gifts aren't duplicated), web scraping to auto-fill product details from URLs, and multi-family sharing controls.

**Stack**: PHP backend (custom router, no framework) | React 19 + Vite + TypeScript frontend | .NET MAUI desktop/mobile app

---

## Prerequisites

- **PHP 8.1+** with `pdo_sqlite` extension
- **Node.js 18+** and npm
- **.NET 10 SDK** (for MAUI app)

---

## Local Development

### 1. Backend (PHP API)

```bash
cd Wish

# Create the data directory for SQLite
mkdir -p data

# Copy and configure environment
cp .env.example .env   # or edit .env directly
# Defaults work for local dev — just ensure JWT_SECRET is set

# Run database migrations
php api/migrations/migrate.php

# Start the dev server on port 8080
php -S localhost:8080 api/router.php
```

The API is now available at `http://localhost:8080/api/`.

**Test it:**
```bash
# Register a user
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123","display_name":"Test User"}'

# Login (returns a JWT token)
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
```

### 2. Frontend (React)

```bash
cd Wish/frontend

# Install dependencies
npm install

# Start dev server on port 5173 (proxies /api to localhost:8080)
npm run dev
```

Open `http://localhost:5173` in your browser.

### 3. MAUI Desktop/Mobile App

```bash
cd Wish/MAUI/WishList

# Restore packages
dotnet restore
```

**Windows (debug):**
```bash
dotnet build -f net10.0-windows10.0.19041.0 -c Debug
dotnet run -f net10.0-windows10.0.19041.0 -c Debug
```

**Android (debug on emulator):**
```bash
dotnet build -f net10.0-android -c Debug
dotnet run -f net10.0-android -c Debug
```

> In Debug mode, the app hits `http://localhost:8080/api`. In Release mode, it uses `https://wish.trickypig.com/api`.

---

## Project Structure

```
Wish/
├── .env                    # Environment config
├── .htaccess               # Apache rewrite rules
├── api/
│   ├── index.php           # Front controller
│   ├── router.php          # PHP dev server router
│   ├── env.php             # .env parser
│   ├── config/             # App + database config
│   ├── helpers/            # Router, Response, Validator
│   ├── middleware/         # Auth (JWT), CORS
│   ├── models/            # User, WishList, Item, Family, Purchase, AllowedDomain
│   ├── routes/            # Route handlers (auth, lists, items, families, purchases, scrape, admin)
│   └── migrations/        # Schema scripts
├── data/                   # SQLite database (gitignored)
├── frontend/
│   ├── src/
│   │   ├── api/client.ts  # API client with all endpoints
│   │   ├── context/       # Auth context
│   │   ├── components/    # Reusable components
│   │   ├── pages/         # Page components
│   │   └── types/         # TypeScript types
│   ├── package.json
│   └── vite.config.ts     # Dev proxy to backend
└── MAUI/WishList/
    ├── Models/             # Data models
    ├── Services/           # API + Auth services
    ├── ViewModels/         # MVVM view models
    ├── Views/              # XAML pages
    ├── Converters/         # Value converters
    └── Helpers/            # Constants, SecureStorage
```

---

## Environment Variables (.env)

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `development` | `development` or `production` |
| `DB_DRIVER` | `sqlite` | `sqlite` or `mysql` |
| `DB_PATH` | `../data/wish.db` | SQLite file path (relative to api/) |
| `DB_HOST` | _(empty)_ | MySQL host |
| `DB_NAME` | _(empty)_ | MySQL database name |
| `DB_USER` | _(empty)_ | MySQL user |
| `DB_PASS` | _(empty)_ | MySQL password |
| `JWT_SECRET` | `local-dev-secret-change-in-production-abc123` | **Change this!** Secret for JWT signing |
| `CORS_ORIGIN` | `http://localhost:5173` | Allowed CORS origin |
| `APP_URL` | value of `CORS_ORIGIN` | Public site address used in emailed links |
| `SMTP_HOST` | _(empty)_ | SMTP server for password reset emails. If empty, development writes emails to `data/mail.log` |
| `SMTP_PORT` | `465` | SMTP port |
| `SMTP_USER` | _(empty)_ | SMTP username |
| `SMTP_PASS` | _(empty)_ | SMTP password |
| `MAIL_FROM` | `noreply@trickypig.com` | Sender address for emails |

---

## Production Deployment

### Backend

1. Deploy the `Wish/` directory (excluding `frontend/`, `MAUI/`, `data/`) to your web server
2. Ensure Apache has `mod_rewrite` enabled (the `.htaccess` handles routing)
3. Create the `data/` directory and ensure it's writable by the web server
4. Update `.env`:
   ```
   APP_ENV=production
   JWT_SECRET=<a-strong-random-secret>
   CORS_ORIGIN=https://wish.trickypig.com
   ```
5. Run migrations:
   ```bash
   php api/migrations/migrate.php
   ```
6. For MySQL instead of SQLite, set `DB_DRIVER=mysql` and the `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` variables

### Frontend

```bash
cd Wish/frontend

# Build for production
npm run build

# The dist/ folder contains the static site
# Deploy to your web server's document root
```

If serving the frontend from the same domain as the API, the `.htaccess` already handles SPA fallback routing.

### MAUI

The production API URL is set in `Helpers/Constants.cs` to `https://wish.trickypig.com/api` (used in Release builds).

**Windows (release):**
```bash
cd Wish/MAUI/WishList
dotnet publish -f net10.0-windows10.0.19041.0 -c Release -p:WindowsPackageType=None -p:SelfContained=true
# Output at: bin/Release/net10.0-windows10.0.19041.0/publish/
```

**Android (release APK):**
```bash
cd Wish/MAUI/WishList
dotnet publish -f net10.0-android -c Release
# Output at: bin/Release/net10.0-android/publish/
```

### Build Configurations Summary

| Platform | Debug (test) | Release (production) |
|----------|-------------|---------------------|
| **Android** | `dotnet run -f net10.0-android -c Debug` — deploys to emulator, hits localhost API | `dotnet publish -f net10.0-android -c Release` — signed APK for distribution |
| **Windows** | `dotnet run -f net10.0-windows10.0.19041.0 -c Debug` — runs locally, hits localhost API | `dotnet publish -f net10.0-windows10.0.19041.0 -c Release` — standalone .exe |

---

## API Endpoints

### Auth

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/auth/register` | No | Register new user |
| POST | `/api/auth/login` | No | Login, returns JWT |
| GET | `/api/auth/me` | Yes | Current user info |

### Lists

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/lists` | Yes | Get user's own lists |
| POST | `/api/lists` | Yes | Create new list |
| GET | `/api/lists/family/{familyId}` | Yes | Get lists visible to a family |
| GET | `/api/lists/{id}` | Yes | Get list with items |
| PUT | `/api/lists/{id}` | Yes | Update list (owner only) |
| DELETE | `/api/lists/{id}` | Yes | Delete list (owner only) |
| PUT | `/api/lists/{id}/sharing` | Yes | Update sharing settings (owner only) |

### Items

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/lists/{listId}/items` | Yes | Create item (owner only) |
| PUT | `/api/items/{id}` | Yes | Update item (owner only) |
| DELETE | `/api/items/{id}` | Yes | Delete item (owner only) |
| PUT | `/api/lists/{listId}/items/reorder` | Yes | Reorder items (owner only) |

### Purchases

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/items/{itemId}/purchase` | Yes | Mark item as purchased |
| DELETE | `/api/items/{itemId}/purchase` | Yes | Unmark purchase |

### Families

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/families` | Yes | Get user's families |
| POST | `/api/families` | Yes | Create family |
| POST | `/api/families/join` | Yes | Join family via invite code |
| GET | `/api/families/{id}` | Yes | Get family with members |
| POST | `/api/families/{id}/invite` | Yes | Generate invite code (admin only) |
| PUT | `/api/families/{id}/members/{userId}/child-status` | Yes | Toggle child status (admin only) |
| DELETE | `/api/families/{id}/members/{userId}` | Yes | Remove member or leave family |
| GET | `/api/families/{id}/domains` | Yes | Get allowed scraping domains |
| POST | `/api/families/{id}/domains` | Yes | Add family domain (admin only) |
| DELETE | `/api/families/{id}/domains/{domain}` | Yes | Remove family domain (admin only) |

### Scraping

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/scrape/url` | Yes | Extract product info from URL |
| POST | `/api/scrape/html` | Yes | Extract product info from a page the client already loaded (`url`, `html`) |

### Admin

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/admin/stats` | Admin | System statistics |
| GET | `/api/admin/scrape-logs` | Admin | Paginated scrape logs |
| GET | `/api/admin/scrape-logs/{id}` | Admin | Single scrape log with raw HTML |
| GET | `/api/admin/domains` | Admin | List global default domains |
| POST | `/api/admin/domains` | Admin | Add global default domain |
| DELETE | `/api/admin/domains/{id}` | Admin | Remove global default domain |
