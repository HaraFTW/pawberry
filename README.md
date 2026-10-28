# PawBerry

A personal, mobile-first pet care journal. Private, single-user, cozy.

Phase 1 provides the project foundation: configuration, routing with base-path support, authentication, migrations, application shell, and PWA scaffolding.

## Technology

- Native PHP 8.2+ (no Laravel / Composer required)
- MySQL / MariaDB via PDO
- HTML5, CSS3, vanilla JavaScript (ES modules)
- Local Lucide-style icons (no Node build step)
- Designed for Apache + cPanel shared hosting

## Project structure

```
pawberry/
├── app/                 # Application code (not web-accessible ideally)
│   ├── Controllers/
│   ├── Core/            # Router, Request, Response, View, CSRF, Session, …
│   ├── Middleware/
│   ├── Services/        # Auth, rate limiting, settings
│   └── Views/
├── bin/                 # CLI tools (migrate, create-user)
├── config/              # Local config (gitignored) + *.example files
├── database/migrations/
├── public/              # Web document root
│   ├── index.php
│   ├── sw.js
│   └── assets/
├── routes/web.php
├── storage/             # Logs, cache, future uploads
├── bootstrap.php
└── README.md
```

## Local installation (XAMPP on Windows)

### 1. Prerequisites

- XAMPP with PHP 8.2+ and MySQL/MariaDB
- Apache `mod_rewrite` enabled

### 2. Database

Create the database (tables are created by migrations):

```sql
CREATE DATABASE pawberry CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Default local credentials (empty password):

| Setting  | Value       |
|----------|-------------|
| Host     | `127.0.0.1` |
| Database | `pawberry`  |
| Username | `root`      |
| Password | *(empty)*   |
| Charset  | `utf8mb4`   |

### 3. Configuration

```bash
copy config\app.php.example config\app.php
copy config\database.php.example config\database.php
```

Edit `config/app.php`:

- `base_url` → `http://pawberry.local`
- `base_path` → `/` (root install)
- `env` → `development`

Edit `config/database.php` if your MySQL credentials differ.

### 4. Virtual host (local)

Local development uses DocumentRoot at the **project root** (do not change this for the current setup):

```apache
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot "D:/Proj/pawberry"
    ServerName pawberry.local

    <Directory "D:/Proj/pawberry">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add to `C:\Windows\System32\drivers\etc\hosts` (if missing):

```
127.0.0.1 pawberry.local
```

Because DocumentRoot is the project root, copy the environment-specific rewrite rules:

```bash
copy .htaccess.example .htaccess
```

The root `.htaccess` is **gitignored**. It rewrites requests into `public/` and blocks HTTP access to private paths (`app`, `bin`, `config`, `database`, `storage`, `routes`, `bootstrap.php`, `.git`, logs, etc.).

Production should expose only `public/` under `/pawberry` and does not need this root file. Ordinary production updates remain `git pull` (+ migrations when needed).

### 5. Migrations

```bash
php bin/migrate.php
```

### 6. Create the initial user

There is no public registration and no default password in the repository.

```bash
php bin/create-user.php "Your Name"
```

You will be prompted for a passphrase (minimum 8 characters). Passphrases are never printed or logged.

Re-running the command **updates** the existing single user (it does not create a second account). Changing the passphrase revokes all remember-me tokens and invalidates existing sessions.

### 7. Open the app

Visit [http://pawberry.local/](http://pawberry.local/)

## Configuration files

| File | Committed? | Purpose |
|------|------------|---------|
| `config/app.php.example` | Yes | Template for app settings |
| `config/database.php.example` | Yes | Template for DB settings |
| `config/app.php` | No (gitignored) | Live app config |
| `config/database.php` | No (gitignored) | Live DB credentials |

### Base path

Never hardcode `/pawberry` in templates or JavaScript. Use `Url::to()` / `Url::asset()` in PHP and `window.PawBerry.basePath` in JS.

| Environment | `base_url` | `base_path` |
|-------------|------------|-------------|
| Local       | `http://pawberry.local` | `/` |
| Production  | `https://bfgd.ro` | `/pawberry` |

## Migration commands

```bash
php bin/migrate.php
```

- Migrations run **only** via this CLI command (or an equally controlled process).
- They do **not** run on normal page requests.
- Applied versions are stored in the `migrations` table.
- Phase 1 migrations are additive only.

## Production deployment

Ordinary updates: `git pull` (plus `php bin/migrate.php` when new migrations exist).

### Recommended layout (shared hosting under `/pawberry`)

Do **not** change the existing site’s document root. Deploy private code outside the public web tree when possible:

```
/home/USER/
  pawberry/                 # private (app, config, database, storage, bootstrap)
    app/
    bin/
    config/
    database/
    storage/
    bootstrap.php
    routes/
  public_html/
    … existing site …
    pawberry/               # public front only
      index.php             # bootstraps ../../pawberry/bootstrap.php (adjust path)
      .htaccess
      assets/
      sw.js
```

`public/index.php` currently assumes the project lives one level above `public/`. For a split deploy, adjust the `require` path in the public `index.php` copy to point at the private `bootstrap.php`.

Set production `config/app.php`:

```php
'env' => 'production',
'base_url' => 'https://bfgd.ro',
'base_path' => '/pawberry',
```

Ensure HTTPS-aware cookies (`secure` auto-detects HTTPS by default).

### Security checklist

- Private directories (`app`, `config`, `storage`, `database`, `bin`, `routes`) must not be web-accessible.
- Prefer a public-only document root; do not rely solely on `.htaccess`.
- Keep `config/*.php` out of Git.
- Run migrations deliberately after deploy when needed.

## Authentication notes

- Single-user passphrase login (`password_hash` / `password_verify`)
- CSRF tokens on state-changing requests
- Login rate limiting
- Session regeneration after login
- Remember-me: 365-day default, selector + validator cookie, only SHA-256 hash stored, rotation on use, HttpOnly / Secure (HTTPS) / SameSite / path-scoped cookie

## PWA foundation

- Dynamic `manifest.webmanifest` (respects base path)
- Service worker scoped to the app base path
- Only static `/assets/` responses are eligible for caching
- Authenticated HTML and private data are not indiscriminately cached

## Development vs production errors

- `development`: richer diagnostics on the 500 page / JSON errors
- `production`: generic user-facing messages; details go to `storage/logs/`

## Phases

**Phase 1:** foundation, auth, shell, placeholders, PWA scaffold.

**Phase 2:** The Gang — member listing, profiles, add/edit, archive/restore, authenticated photo serving, Cropper.js square crop workflow.

**Not yet:** event types, events, recurrence, reminders, calendar logic, sounds.

Member photographs live under `storage/uploads/members/` (not web-accessible) and are served via `/gang/{id}/photo/{cropped|original}` after authentication.
