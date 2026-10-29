# Initial rooms and local media

The room media ZIP contains only the 22 image/video files referenced by `RoomSeeder`, with exact case-sensitive relative filenames preserved. It excludes unrelated uploads, customer records and environment files.

From the Laravel project root on Namecheap:

```bash
git pull --ff-only origin fix/remove-checkout-fees
sha256sum -c releases/insignia-room-media.sha256
unzip -n releases/insignia-room-media.zip -d storage/app/public
php artisan db:seed --class=RoomSeeder --force
```

The archive includes its `rooms` folder, placing the files under `storage/app/public/rooms`. The existing `public/storage` symlink serves them at `/storage/rooms/...`. `unzip -n` preserves media files already present on the host.

This initializes Atala, Junonia, Lyria, Evania, Morpho and Papilio using the current room seed definitions. Existing same-name rooms and their edited prices, descriptions, images and readiness remain unchanged. It creates no demo users, reservations, payments, tasks or ledger entries. Review imported rates and media in the owner portal before opening bookings. Other catalogs (services, boutique stock and dining menu) are separate from this room setup.
