# marketing-runner (Demo Marketing Workflow Runner)

This repository is a **demo-first marketing automation runner** built with **Laravel** and **SQLite**.

It demonstrates the core “HubSpot-like” loop we need for the CRM project:

- **Send** a marketing email (currently **log mode**, so no real email provider required)
- **Wait** a short window (demo uses minutes; production will use **6 hours**)
- **Check compliance** (a contact “complied” = engagement record exists)
- If **not complied**, **resend** up to N attempts
- If **complied**, stop sending and record status

The goal is to have a reliable demo even while:
- Azure SQL is still being finalized
- Mailchimp paid access isn’t available yet
- We must keep the runner **robust and adaptable** (easy to swap providers later)

---

## What’s implemented so far

### Demo data model (SQLite)
We use a lightweight schema that mirrors the official CRM ERD concepts:

- `contacts`
  - Stores demo contacts (email, name, basic lifecycle/status fields)
- `contact_activities`
  - Stores “email sent” activity and **last_messaging_contents / last_messaging_date**
  - Also stores `attempts` (resend count)
- `contact_engagements`
  - Stores engagement/compliance events (e.g., `COMPLIED = YES`)

> Later, when Azure SQL is available, we will map these to the official tables:
> - `CrmDB_Contact`
> - `CrmDB_Contact_Activities_Lead` (or `CrmDB_Contact_Activities`)
> - `CrmDB_Contact_Engagement`

### Commands (Artisan)
- `php artisan marketing:run`
  - Runs the workflow runner:
    - sends/logs first email if no activity
    - checks if a contact is due (based on `--minutes`)
    - resends if due and not complied
- `php artisan marketing:complied <email>`
  - Marks a contact as complied by inserting a `contact_engagements` record
  - Used for demos when click/reply tracking isn’t integrated yet
- `php artisan marketing:reset [--reseed]`
  - **Demo-only** reset helper:
    - clears activities + engagements
    - keeps contacts by default
    - `--reseed` re-creates contacts from the seeder
  - Guarded so it only runs in `local/development/testing`

### Email “sending” (Log mode)
- Current mode: `MAIL_MAILER=log`
- Emails are written to: `storage/logs/laravel.log`
- This avoids external dependencies and keeps the demo stable.

---

## Requirements

- PHP 8.2+
- Composer
- (No Node/NPM required for the current demo)

---

## Setup (Local Demo)

### 1) Install dependencies
```bash
composer install
```

### 2) Configure environment
Copy the example env file:
```bash
copy .env.example .env
```

Then edit `.env` and ensure:

```env
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Keep demo simple
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file

# Log-mode mail
MAIL_MAILER=log
MAIL_DRIVER_MODE=log
```

> Notes:
> - `MAIL_DRIVER_MODE` is a small toggle used by our mailer binding logic.
> - In demo we keep queues as `sync` to avoid queue workers.

### 3) Create the SQLite database file
```bash
# PowerShell
New-Item -ItemType File -Path .\database\database.sqlite -Force
```

### 4) Generate app key + migrate
```bash
php artisan key:generate
php artisan migrate
```

### 5) Seed demo contacts
```bash
php artisan db:seed --class=DemoContactsSeeder
```

---

## Running the demo

### Reset demo state
Keeps the same contacts, clears activity/engagement state:
```bash
php artisan marketing:reset
```

Full reset (recreates demo contacts too):
```bash
php artisan marketing:reset --reseed
```

### Run the workflow runner
Run with a short demo window (e.g., 1 minute) and max attempts:
```bash
php artisan marketing:run --minutes=1 --maxAttempts=3
```

Run again after a minute to trigger resends.

### Mark a contact as complied
Simulate “the user engaged”:
```bash
php artisan marketing:complied test1@example.com
```

Then re-run:
```bash
php artisan marketing:run --minutes=1 --maxAttempts=3
```

### Where to see the “sent emails”
Open:
- `storage/logs/laravel.log`

You’ll see entries like:
- who would be emailed
- subject
- tracking id
- short preview of the HTML content

---

## Switching to SMTP later (planned)

When credentials are available, we will flip from log mode to SMTP with minimal changes.

### 1) Update `.env`
```env
MAIL_DRIVER_MODE=smtp
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME="Marketing Runner"
```

### 2) Test
```bash
php artisan marketing:run --minutes=1
```

The workflow engine logic stays the same; only the mailer implementation changes.

---

## Mailchimp plans (planned)

The company will use Mailchimp (paid), but access/API keys are still pending.

The intended integration is:
- Keep workflow logic in Laravel (timers + compliance checks + resends)
- Use a **Mailchimp adapter** for delivery:
  - send via API
  - store provider message/campaign id in logs
- Continue using the same data model concepts:
  - activities (sent)
  - engagements (complied)

For the MVP/demo we **do not depend** on Mailchimp automation features.

---

## Future roadmap

### Phase 1 (Demo-ready)
- Log-mode sending (done)
- Timer window (minutes for demo)
- Manual compliance injection (done)
- Reset command (done)

### Phase 2 (Real sending)
- SMTP mailer adapter
- Basic bounce/failure handling
- Better tracking id strategy (persist per contact + step)

### Phase 3 (Production integration)
- Switch storage to Azure SQL
- Map to official CRM schema (ERD)
- Add real compliance signals:
  - CTA click tracking (redirect endpoint)
  - reply detection (provider-specific)
- Handle scale: batch processing + throttling for 100k+ contacts

---

## Safety / Notes
- `marketing:reset` is **demo-only** and guarded by environment checks.
- Do not commit real secrets (API keys, passwords) into `.env`.
