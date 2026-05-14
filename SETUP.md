# Setup

One-time setup to take this skeleton from this folder to a working Laravel app on GitHub + Cloudways.

## 0. Prerequisites on your Mac

```bash
php --version           # need 8.2 or newer
composer --version      # need 2.x
git --version
mysql --version         # optional locally; you can also run via Cloudways from day one
```

If you don't have PHP 8.2 + Composer:

```bash
brew install php composer
```

## 1. Create the GitHub repo

In your browser:

1. Go to https://github.com/new
2. Owner: **sepici**
3. Repository name: **rag-tracker**
4. Visibility: **Private**
5. Do **NOT** initialise with README, .gitignore, or license (this folder already has those).
6. Click *Create repository*.

GitHub will show a "…or push an existing repository" snippet. Note the URL — it'll be either:
- HTTPS: `https://github.com/sepici/rag-tracker.git`
- SSH (recommended): `git@github.com:sepici/rag-tracker.git`

## 2. Initialise git and push the skeleton

The project already lives at `~/Development/rag-tracker/`. Open Terminal:

```bash
cd ~/Development/rag-tracker
git init -b main
git config user.name "Sep"
git config user.email "sepici@gmail.com"
git add .
git commit -m "Initial skeleton — README, .gitignore, design doc, setup guide"

# Wire up GitHub
git remote add origin git@github.com:sepici/rag-tracker.git    # or HTTPS URL
git push -u origin main
```

You should now see the README, .gitignore, and docs/design.md on GitHub.

## 3. Install Laravel 11 into this folder

Laravel adds a lot of files — we'll let Composer scaffold them without overwriting our README, .gitignore, or docs.

```bash
cd ~/Development/rag-tracker

# Composer will refuse to install into a non-empty dir, so use a temp dir
composer create-project laravel/laravel:^11 _laravel-temp

# Move Laravel's files in, preserving our existing README/.gitignore/docs.
# Laravel's .gitignore is more permissive than ours; we want to KEEP ours.
mv _laravel-temp/.editorconfig . 2>/dev/null
mv _laravel-temp/.env.example .
# Skip _laravel-temp/.gitignore — ours is better tuned

# Everything else
rsync -av --exclude='.git' --exclude='.gitignore' --exclude='README.md' --exclude='docs' --exclude='SETUP.md' _laravel-temp/ .

rm -rf _laravel-temp

# Generate app key + first migrations
cp .env.example .env
php artisan key:generate
```

## 4. Local database (optional but recommended)

Either run a local MySQL or use Cloudways DB straight away. For local:

```bash
# Update .env DB_* values to point at your local MySQL
# Then:
mysql -uroot -e "CREATE DATABASE rag_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# We'll add migrations in M1; nothing to migrate yet.
```

## 5. Install Laravel Breeze (auth scaffolding)

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade --dark
npm install
npm run build
```

This adds login, register, forgot-password routes and views. We'll customise them in M1 (disable public registration; admin creates users).

## 6. Confirm everything runs

```bash
php artisan serve
# visit http://127.0.0.1:8000
```

You should see Laravel's welcome page.

## 7. First commit with Laravel + Breeze

```bash
git add .
git commit -m "Scaffold Laravel 11 + Breeze auth"
git push
```

## 8. Cloudways deployment (when you're ready)

1. In Cloudways, create a new application: **PHP Stack**, PHP 8.2+, MySQL 8.
2. Application Settings → Deployment via Git → connect GitHub → select `sepici/rag-tracker` → branch `main`.
3. Authentication: you'll set this up later — either Cloudways' deploy SSH key (recommended) or a Personal Access Token.
4. Deploy path: `public_html`.
5. Post-deploy script:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan storage:link
   ```
6. Set environment variables on Cloudways (Application Settings → Environment Variables):
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://your-cloudways-domain`
   - `APP_KEY=` (generate with `php artisan key:generate --show` locally, copy the value)
   - DB credentials are auto-injected by Cloudways.
7. First deploy: trigger manually from Cloudways UI. Subsequent deploys auto-pull on push to main.

## What to do next

Tell me the push to GitHub worked, and we'll start M1 (auth + roles + admin user-management).
