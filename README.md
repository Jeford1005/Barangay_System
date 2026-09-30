# Barangay Management System

A comprehensive web-based management system for Philippine barangays, built with Laravel 12 and PHP 8.2+.

## Features

- **Resident Management** - Register, track, and manage resident information
- **Household Management** - Manage household records and family structures
- **Purok Management** - Organize residents by purok/zone
- **Officials Management** - Track barangay officials and their terms
- **Document Management** - Handle barangay certificates, permits, and clearances
- **Blotter System** - Record and manage incident reports
- **Welfare & Health Tracking** - Monitor community welfare and health programs
- **User Authentication** - Secure login and role-based access

## System Requirements

- PHP ^8.2 (>= 8.2, < 9.0 — matches `require.php` in composer.json)
- Composer 2
- Node.js ^20.19 or >=22.12 (Vite 7's requirement) and npm
- SQLite (default, recommended) or MySQL

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd Barangay_Management_System
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install
```

### 3. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# On Windows PowerShell (where `cp` does not exist):
#   Copy-Item .env.example .env

# Generate application key (if not already set)
php artisan key:generate
```

### 4. Database Setup

The system uses SQLite by default. On a fresh clone the `.sqlite` file does
not exist yet, so create it **before** migrating (otherwise `migrate` fails
with a "database does not exist" error). `composer setup` does this for you;
for a manual setup run:

```bash
# Create the SQLite file first (fresh clones only; harmless no-op afterwards)
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"

# Run migrations
php artisan migrate

# (Optional) Seed sample data
php artisan db:seed
```

### 5. Build Assets

```bash
# Development
npm run dev

# Production
npm run build
```

### 6. Start Development Server

```bash
php artisan serve
```

Visit `http://localhost:8000` in your browser.

## Configuration

### Database Configuration

By default, the system uses SQLite. To use MySQL or PostgreSQL, update your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=barangay_db
DB_USERNAME=root
DB_PASSWORD=
```

### Session Configuration

Sessions are stored in the database by default. Update `SESSION_DRIVER` in `.env` if needed.

## Default User Access

After running seeders (when implemented), use these credentials:

- **Administrator**: admin@barangay.local / password
- **Staff**: staff@barangay.local / password
- **Official**: official@barangay.local / password

Fresh installations create the sample Staff account with the limited `staff` role. Existing installations are not automatically changed; an administrator must review and assign the role explicitly.

### Access roles

- **Administrator** — full access, including users, roles, settings, backups, audit logs, certificate catalog, archive, and irreversible actions.
- **Official** — reviews and decides: reads every operational module, records blotter cases, and settles welfare, certificate request, and correction decisions. Resident and household records are read-only, certificates are never issued by this role, and no destructive action is available.
- **Staff** — day-to-day resident, household, blotter, welfare intake, certificate issuance, reports, and analytics work. Puroks are read-only, approvals remain restricted to officials and administrators, and destructive actions remain protected.
- **Resident** — self-service access to their own account: certificate requests, correction requests, incident reports, assistance requests, and a read-only directory of the barangay officials.

## Project Structure

```
├── app/
│   ├── Http/Controllers/   # Application controllers
│   ├── Models/             # Eloquent models
│   └── ...
├── database/
│   ├── migrations/         # Database migrations
│   └── seeders/           # Database seeders
├── resources/
│   ├── views/             # Blade templates
│   ├── js/                # JavaScript files
│   └── css/               # Stylesheets
├── routes/
│   ├── web.php            # Web routes
│   └── auth.php           # Authentication routes
└── public/                # Public assets
```

## Key Models

- **Resident** - Individual resident records
- **Household** - Household/family units
- **Purok** - Geographic zones/puroks
- **Official** - Barangay officials
- **Document** - Document templates and records
- **Blotter** - Incident reports

## Security Features

- CSRF protection on all forms
- SQL injection prevention via Eloquent ORM
- XSS protection with input sanitization
- Password hashing using bcrypt
- Database transactions for data integrity
- Soft deletes for data recovery
- Audit trails (created_by, updated_by)

## Development

### Running Tests

```bash
php artisan test     # PHP / feature tests (also: composer test)
npm test             # browser tests (Playwright; also: npm run test:browser)
npm run preview      # preview the production Vite build locally
```

There is intentionally no `npm run lint`: no JS linter is configured for this
project. PHP style is checked with Pint (see Code Style below).

`package.json` declares `engines: node ^20.19 || >=22.12` (Vite 7's
requirement), so Node 18 fails fast at install time instead of breaking mid-build.

### Code Style

```bash
# Check code style
./vendor/bin/pint --test   # also: composer lint

# Fix code style
./vendor/bin/pint          # also: composer format
```

### Database Maintenance

```bash
# Refresh database (WARNING: deletes all data)
php artisan migrate:fresh --seed

# Rollback last migration
php artisan migrate:rollback
```

## Backup & Maintenance

### Database Backup

The simplest path is the **in-app backup**: Admin → Settings → Maintenance →
**Create backup**, or let the 03:00 scheduled job run one. It works on SQLite, MySQL and
MariaDB, writes to `storage/app/private/backups`, and keeps the newest 10.

```bash
# SQLite: a raw copy of a live WAL database can be torn - prefer the in-app backup,
# or stop the app first and remove the sidecar files.
sqlite3 database/database.sqlite ".backup backups/backup-$(date +%Y%m%d).sqlite"

# MySQL: an extra copy held outside the app
mysqldump --single-transaction -u root -p barangay_db > backup-$(date +%Y%m%d).sql
```

Restore steps for both formats: [docs/operations/deploy.md](docs/operations/deploy.md#backups)

### Log Files

Application logs are stored in `storage/logs/laravel.log`

## Troubleshooting

### Permission Issues

```bash
chmod -R 775 storage bootstrap/cache
```

On Windows / XAMPP there is no `chmod` — give `storage/` and
`bootstrap/cache/` write access to the Apache user instead (File Explorer →
right-click → Properties → Security → Edit), or run your terminal as
Administrator if the folders were created by another user.

### Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

After changing `.env` on a server, rebuild in the same order — clear first,
then cache (see [Production Deployment](#production-deployment)):

```bash
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Database Issues

```bash
# Check database connection
php artisan migrate:status

# Reset database
php artisan migrate:fresh
```

## Production Deployment

Full, step-by-step guide: **[docs/operations/deploy.md](docs/operations/deploy.md)**

Quick version — the four things that break first:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # public/build/ is NOT in git — required
php artisan migrate --force
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Plus two processes that must stay running:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

On Windows there is no cron — use Task Scheduler to run the same command
every minute (adjust the path to your install):

```powershell
schtasks /create /tn "BarangayApp schedule:run" `
  /tr "php C:\Xampp\htdocs\Barangay_Management_System\artisan schedule:run" `
  /sc minute /mo 1
```

(GUI alternative: Task Scheduler → Create Task → trigger "Daily", repeat every
1 minute → action starts `php` with argument
`C:\Xampp\htdocs\Barangay_Management_System\artisan schedule:run`.)

…and a **queue worker** (supervisor, or a cron fallback) — without it certificate
notifications and backups never leave `Queued`.

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=60
```

Key details the guide covers: doc root must be `public/`; `APP_DEBUG=false`;
**do not `db:seed` in production** (it creates `admin@barangay.local` /
`password`); and backups are built in for **SQLite, MySQL and MariaDB** — see the
[backup and restore notes](docs/operations/deploy.md#backups).

`public/.htaccess` ships with **no hardcoded `RewriteBase`**, so docroot,
Alias/subdirectory (e.g. XAMPP `/Barangay_Management_System`) and shared-host
`public_html` layouts all work without edits.

**Hosting:** any PHP host (VPS + Forge, shared cPanel, Render/Railway).
**Not** Vercel/Netlify/serverless — this is Laravel, and it needs a PHP runtime,
a database, a writable filesystem for photo uploads, and a queue worker.

## Mail / Gmail SMTP (password-reset emails)

Password reset sends a 6-character code by email. Verify your setup **before** relying on it:

```bash
php artisan mail:check          # config audit
php artisan mail:check --send   # + deliver a test email
```

The same checklist is available in the admin area under **Mail Check** (`/admin/mail-health`).

### Gmail setup

1. Enable **2-Step Verification** on the Google account that will send mail.
2. Create an **App Password**: <https://myaccount.google.com/apppasswords> (16 characters — Gmail rejects normal account passwords).
3. In `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your.address@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_FROM_ADDRESS="your.address@gmail.com"
```

> `MAIL_FROM_ADDRESS` must be the **same** Gmail account as `MAIL_USERNAME`, or Google rejects the message. Restart `php artisan serve` after editing `.env`.

With `MAIL_MAILER=log` (the default), emails are written to `storage/logs/laravel.log` instead of being delivered — useful for local testing of the reset flow.

## Security Considerations

- **Never commit `.env` file** - Contains sensitive credentials
- **Use HTTPS in production** - Configure SSL certificates
- **Regular backups** - Implement automated backup strategy
- **Keep dependencies updated** - Run `composer update` and `npm update` regularly
- **Monitor logs** - Check `storage/logs` for security issues
- **Strong passwords** - Enforce password policies for users

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License.

## Support

For issues and questions:
- Create an issue in the repository
- Contact the development team
- Check the documentation

## Changelog

### Version 1.0.0 (2026-09-17)
- Initial release
- Core resident and household management
- Purok organization system
- Authentication and security improvements
- Database optimization with indexes and soft deletes
- Audit trail implementation

---

**Built with Laravel 12 and PHP 8.2+**
