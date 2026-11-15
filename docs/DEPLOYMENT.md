# Deploying Biponon to shared cPanel hosting

How the app gets from GitHub to `https://saas-backend.mypersonalwork.com`.

## How it works

```
push to main ──► GitHub Action: tests, composer --no-dev, npm run build
                       │
                       ▼
                 `production` branch (code + vendor/ + public/build, ready to run)
                       │
cPanel Git Version Control: "Update from Remote" → "Deploy HEAD Commit"
                       │
                       ▼
       .cpanel.yml: php artisan migrate --force, php artisan optimize
```

- The server never builds anything: it has PHP but no composer or npm.
- `main` stays clean; only the `production` branch carries `vendor/` and `public/build`.
- Deploys are a manual click, so database migrations only run when you choose.
- Server-only files never touch git: `backend/.env`, `storage/` contents (logs, sessions, backups).

| Piece | File |
|---|---|
| Build + publish | `.github/workflows/production-build.yml` |
| Ignore rules on `production` | `.github/deploy/production.gitignore` |
| Deploy steps on the server | `.cpanel.yml` |
| Production settings template | `backend/.env.production.example` |
| Queue + backup schedule | `backend/routes/console.php` |

**Folders on the server**

```
/home/myperson/biponon/                  ← the git clone (production branch), NOT web-reachable
/home/myperson/biponon/backend/public/   ← the only public folder
/home/myperson/public_html/saas-backend/public
        → symbolic link to /home/myperson/biponon/backend/public  (the subdomain's document root)
```

The app lives outside `public_html` because the main domain serves all of `public_html`; inside it,
`.env` could be downloaded through `mypersonalwork.com/...`.

---

## One-time setup

### 1. GitHub

1. Revoke any personal access token stored in your local git remote URL, and reset the URL:
   `git remote set-url origin https://github.com/akmsadi/biponon-saas.git`
2. Push `main`. Open the repo's **Actions** tab and wait for **Production build** to go green.
   It creates the `production` branch.
   - If it fails at "Publish to the production branch" with a permission error: repo **Settings →
     Actions → General → Workflow permissions → Read and write permissions**, then re-run.

### 2. Check the server (cPanel → Terminal)

```bash
php -v                 # must be 8.3 or newer. PHP 8.4.27 (cli) (built: Oct 24 2026 00:00:00) (NTS)
which php              # note the full path, used for the cron job, /usr/local/bin/php
php -m                 # must list: bcmath ctype fileinfo intl mbstring openssl pdo_mysql tokenizer xml zip. OK all exists
which mysqldump        # needed for backups; if empty, ask the host for its folder → DB_DUMP_PATH. /bin/mysqldump
```

In **MultiPHP Manager**, set `saas-backend.mypersonalwork.com` to the same PHP version (8.4).
Missing extensions can usually be switched on in **Select PHP Version → Extensions**.

### 3. Database

cPanel → **MySQL Databases**:
1. Create a database (cPanel adds your prefix, e.g. `myperson_biponon`).
2. Create a user with a strong password.
3. Add the user to the database with **ALL PRIVILEGES**.

Keep the three full names and the password for step 6.

### 4. Deploy key (so the server can read the private repo)

In the Terminal:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519 -N "" -C "cpanel-biponon"
cat ~/.ssh/id_ed25519.pub
```

Copy the printed line into GitHub → repo **Settings → Deploy keys → Add deploy key**
(title "cPanel", leave **Allow write access** unticked). Then check it:

```bash
ssh -T git@github.com     # type "yes" once; expect: "Hi akmsadi/biponon-saas! You've successfully authenticated"
```

### 5. Clone the production branch

```bash
git clone --branch production git@github.com:akmsadi/biponon-saas.git ~/biponon
```

Then cPanel → **Git™ Version Control → Create**: switch **Clone a Repository** off, set
**Repository Path** to `biponon`, name it `biponon`. cPanel now manages the existing clone.

### 6. Production settings

```bash
cd ~/biponon/backend
cp .env.production.example .env
```

Edit `.env` (cPanel **File Manager** → right-click → Edit, or `nano .env`) and fill every `CHANGE_ME`:
- `APP_URL=https://saas-backend.mypersonalwork.com`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from step 3
- `MAIL_FROM_ADDRESS`, `BACKUP_NOTIFICATION_EMAIL`
- `DB_DUMP_PATH` only if `which mysqldump` found nothing

Then:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan optimize
```

**Never run `php artisan db:seed` here**: the seeders create demo data and a development account.

### 7. Point the subdomain at the app

The document root is `/public_html/saas-backend/public`. Replace that folder with a link:

```bash
ls -la ~/public_html/saas-backend/public     # should be empty (or only cPanel's placeholder files)
rm -rf ~/public_html/saas-backend/public
ln -s ~/biponon/backend/public ~/public_html/saas-backend/public
```

Then cPanel → **Domains**: turn on **Force HTTPS Redirect** for `saas-backend.mypersonalwork.com`
(the SSL certificate comes from AutoSSL; cookies are HTTPS-only).

Open `https://saas-backend.mypersonalwork.com/up`: it should say the application is up.
- **403 Forbidden** there means the host doesn't follow symbolic links. Tell me and we'll switch to
  the fallback (app inside `public_html`, everything but `public/` blocked by `.htaccess`).

### 8. Cron job (queue + backups)

**Why:** shared hosting can't keep background programs running. Instead, cPanel runs one command
every minute, and Laravel decides what is due each time: sending queued invitation emails (every
minute) and the database backup (nightly).

**a. Find PHP's full path.** Cron jobs don't know what plain `php` means the way the Terminal does.
In the Terminal:

```bash
which php        # e.g. /usr/local/bin/php
```

**b. Add the job.** cPanel → **Cron Jobs** → **Add New Cron Job**:
- **Common Settings:** pick **Once Per Minute (\* \* \* \* \*)**. All five time boxes fill with `*`.
- **Command:** paste this, with `/usr/local/bin/php` replaced by what step a printed:

```bash
cd /home/myperson/biponon/backend && /usr/local/bin/php artisan schedule:run >> /home/myperson/cron.log 2>&1
```

What each part does:
- `cd /home/myperson/biponon/backend`: go to the app folder.
- `/usr/local/bin/php artisan schedule:run`: run whatever is due right now.
- `>> /home/myperson/cron.log 2>&1`: write the output to `cron.log` in your home folder so you can
  see that it ran.

Click **Add New Cron Job**.

**c. Check it runs.** Wait two minutes, then in the Terminal:

```bash
cat ~/cron.log
```

- Lines like `Running [...queue:work...]` → working.
- `No scheduled commands are ready to run.` → also working (nothing was due).
- Empty or missing file → the job isn't running; check the command for typos.
- An error mentioning the PHP version → the path from step a is an older PHP; tell me.

**d. Stop the log growing.** Once it works, edit the cron job and change the end of the command
from `>> /home/myperson/cron.log 2>&1` to `>> /dev/null 2>&1` (discard the output), then
`rm ~/cron.log`.

### 9. First login

```bash
cd ~/biponon/backend
php artisan biponon:super-admin you@example.com --create
```

It asks for your name and a password (typed hidden) and creates a verified super-admin account.
Log in, then **Organizations → New organization** with the organization's admin email. You get an
invitation link to copy (no mail provider yet); the admin opens it to set up their account.

---

## Every update

1. Push to `main` (or merge into it).
2. Wait for **Production build** to go green in GitHub → Actions (a red build never reaches `production`).
3. cPanel → **Git™ Version Control → biponon → Manage → Pull or Deploy**:
   **Update from Remote**, then **Deploy HEAD Commit**.

The deploy runs `migrate --force` and `optimize`. Its log is under the same page.

If `.env` changes later, run `php artisan optimize` again (settings are cached).

**Roll back** (code only; migrations stay applied):

```bash
cd ~/biponon && git log --oneline -5
git reset --hard <previous commit>
cd backend && php artisan optimize
```

---

## Backups

- Nightly at 02:00 (Asia/Dhaka): `storage/app/private/biponon/<date>.zip` containing a MySQL dump.
  Old copies are pruned automatically (all for 7 days, then daily for 16 days, then weekly/monthly).
- Failures are emailed to `BACKUP_NOTIFICATION_EMAIL` once real mail is set up; until then they
  appear in `storage/logs`.
- Run one by hand: `php artisan backup:run --only-db`. List them: `php artisan backup:list`.
- The backups sit on the same server. Download one now and then (File Manager) or use cPanel's own
  account backups as a second copy.

**Restore:** unzip the file, then
`mysql -u DB_USERNAME -p DB_DATABASE < db-dumps/mysql-DB_DATABASE.sql`.

## Email

Until a mail provider is set, `MAIL_MAILER=log` writes emails to `storage/logs` and invitations are
shared by copying the link. To send real mail, create `no-reply@mypersonalwork.com` in cPanel →
**Email Accounts**, put its SMTP settings into `.env` (see the comments there), then
`php artisan optimize`.

## When something breaks

- Errors: `storage/logs/laravel-<date>.log` (only errors are logged).
- Blank page or 500 right after a deploy: `php artisan optimize:clear`, then `php artisan optimize`.
- Never set `APP_DEBUG=true` on the live site; it shows secrets on error pages.
