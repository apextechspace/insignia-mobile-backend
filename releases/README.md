# Namecheap backend dashboard assets

`insignia-portal-assets.zip` contains the verified Vite output for the backend, including the custom Filament dashboard stylesheet and build manifest. It does not contain PHP dependencies, environment files, uploaded media or application credentials. Filament's own published assets are installed separately by Composer's upgrade script.

From the Laravel project root on Namecheap:

```bash
git pull --ff-only origin fix/remove-checkout-fees
sha256sum -c releases/insignia-portal-assets.sha256
unzip -o releases/insignia-portal-assets.zip -d public
php artisan config:cache
php artisan view:cache
```

The archive contains a `build` directory, so extraction places the manifest at `public/build/manifest.json`. Keep the repository and archive outside the portal domain's public document root; that domain must serve Laravel's `public` directory. These assets complement the PHP application and migrated database; they are separate from the customer storefront ZIP in the frontend repository.
