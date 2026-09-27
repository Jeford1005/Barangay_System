# Barangay Management System

A comprehensive web-based management system for Philippine barangays, built with Laravel 12 and PHP 8.2.

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

- PHP >= 8.2
- Composer
- Node.js >= 18.x and NPM
- SQLite (default) or MySQL/PostgreSQL

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd Barangay_Management_System1
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

# Generate application key (if not already set)
php artisan key:generate
```

### 4. Database Setup

The system uses SQLite by default. The database file will be created automatically.

```bash
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

Fresh installations create the sample Staff account with the limited `staff` role. Existing installations are not automatically changed; an administrator must review and assign the role explicitly.

### Access roles

- **Administrator** — full access, including users, roles, settings, backups, audit logs, certificate catalog, archive, and irreversible actions.
- **Staff** — day-to-day resident, household, blotter, welfare intake, certificate issuance, reports, and analytics work. Puroks are read-only, welfare approvals/releases and fee overrides are administrator-only, and destructive actions remain protected.
- **Resident** — self-service access to their own account, requests, certificates, and correction requests.

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
php artisan test
```

### Code Style

```bash
# Check code style
./vendor/bin/pint --test

# Fix code style
./vendor/bin/pint
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

For SQLite:
```bash
cp database/database.sqlite database/backups/backup-$(date +%Y%m%d).sqlite
```

For MySQL:
```bash
mysqldump -u root -p barangay_db > backup-$(date +%Y%m%d).sql
```

### Log Files

Application logs are stored in `storage/logs/laravel.log`

## Troubleshooting

### Permission Issues

```bash
chmod -R 775 storage bootstrap/cache
```

### Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

### Database Issues

```bash
# Check database connection
php artisan migrate:status

# Reset database
php artisan migrate:fresh
```

## Production Deployment

### 1. Environment Configuration

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

### 2. Optimize for Production

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

### 3. Build Assets

```bash
npm run build
```

### 4. Set Proper Permissions

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 5. Set Up Queue Workers (if using queues)

```bash
php artisan queue:work --daemon
```

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

**Built with Laravel 12 and PHP 8.2**
