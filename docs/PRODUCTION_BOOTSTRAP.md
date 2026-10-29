# Create the first owner account

After dependencies, database migrations and the portal document root are configured, run this from the backend project folder:

```bash
php artisan db:seed --class=ProductionSeeder --force
```

The command prompts for the owner name, login email, password and password confirmation. Password input is hidden. Use at least 12 characters with uppercase/lowercase letters, a number and a symbol. The account receives the `ipl_admin` role required for the owner portal at `/admin/login`.

This seeder creates only the named owner. It does not create demo logins, sample paid bookings, catalog records, inventory balances or financial entries. Rerunning it with an existing owner email leaves the original password and profile unchanged. An existing guest/staff email is rejected rather than silently promoted; choose a separate owner email.

In `APP_ENV=production`, the default `php artisan db:seed --force` also routes to this bootstrap instead of development sample data. Use the explicit `ProductionSeeder` command when installing to ensure the intended behavior regardless of environment configuration. Review and populate the real rooms, services, stock and prices through the owner/operations portals before opening sales.
