# mTrack API And Web App

Laravel, Inertia, Vue, TypeScript, Tailwind, Horizon, Reverb, and Sanctum foundation for mTrack.

## Local Setup

```bash
composer install --ignore-platform-req=ext-pcntl
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run dev
```

The local Composer ignore is only needed when the PHP CLI lacks `ext-pcntl`; deployment adds `php84Extensions.pcntl` in `nixpacks.toml` for Horizon.
