# marketing-runner

Demo-first marketing workflow runner built with **Laravel** and **SQLite**.

This repository is a small, focused workflow engine for the wider CRM initiative. It is designed to be:

- **Adaptable** — swap email providers without rewriting workflow logic
- **Robust** — keep a stable demo path even when external access is not ready
- **Easy to edit** — separate engine logic from storage and mail delivery details
- **Safe for demos** — use log mode or SMTP sandbox tools without touching production systems

---

## What this project currently does

At a high level, the runner executes a simple marketing workflow loop:

1. **Read eligible contacts**
2. **Send** an email (log mode or SMTP)
3. **Record activity** in the database
4. **Wait** based on a time window
5. **Check compliance**
6. **Resend** if needed
7. **Stop** if complied or max attempts reached

Right now, compliance is still **manual** for demo/testing:
- a contact is marked complied through:
  - `php artisan marketing:complied test1@example.com`

This means the project **does not yet have automatic click / reply / form-submit compliance tracking**.

---

## Current status summary

### Already implemented
- Workflow execution engine
- Storage adapter pattern
- Mail adapter pattern
- Log mail mode
- SMTP mail mode
- Demo reset command
- Manual compliance command
- Tracking IDs stored on send activities
- Compliance check prefers `tracking_id`

### Not implemented yet
- Automatic compliance tracking from:
  - reply
  - click
  - form submit
- Azure SQL storage adapter
- Mailchimp provider adapter
- UI builder for IF / THEN workflow definitions

---

## Core design philosophy

This project was intentionally built **engine-first**.

### Why?
Because the wider CRM project still has moving parts:
- Azure SQL access is still pending/finalizing
- Mailchimp access is still pending/finalizing
- final event ingestion design is still not confirmed
- final target table mapping is still not confirmed

So instead of blocking on those dependencies, this runner proves the most important thing first:

> the workflow logic itself works, and the code structure allows later integration without a rewrite.

---

## Project architecture

There are **three core layers**:

### 1) Workflow Engine
**File:** `app/Services/Workflow/MarketingWorkflowEngine.php`

This is the core logic layer.

#### Purpose
To run the send → wait → check → resend loop.

#### What it does
- reads contacts from storage
- checks latest activity
- checks whether the contact complied
- decides whether to send, resend, skip, or stop
- records new activity state

#### Why it matters
This is the most important part of the project and should remain stable even if:
- the DB changes
- the email provider changes
- the CRM schema changes

---

### 2) Storage Adapter
**Files:**
- `app/Contracts/MarketingStorage.php`
- `app/Services/Storage/DemoSqliteMarketingStorage.php`

#### Purpose
To isolate database reads and writes from the workflow engine.

#### What it does
The engine does not directly talk to table names. Instead, it calls storage methods like:
- list contacts
- get latest activity
- insert activity
- insert engagement
- update contact fields

#### Why it matters
Right now, the implementation uses **SQLite demo tables**.

Later, this can be replaced with an **Azure SQL CRM storage adapter** that maps the same engine operations into the official CRM schema.

That means:
- **engine logic stays the same**
- only the storage implementation changes

---

### 3) Mail Adapter
**Files:**
- `app/Contracts/MarketingMailer.php`
- `app/Services/Mail/LogMarketingMailer.php`
- `app/Services/Mail/LaravelSmtpMarketingMailer.php`
- `app/Providers/AppServiceProvider.php`

#### Purpose
To isolate email sending logic from the workflow engine.

#### What it does
The engine calls the mail adapter, and the provider binding decides how the message is sent:
- `log` mode → writes to `storage/logs/laravel.log`
- `smtp` mode → sends through configured SMTP credentials

#### Why it matters
This lets you:
- demo safely in log mode
- test real SMTP without rewriting engine logic
- add Mailchimp later as another adapter

---

## Current file structure (important files only)

### Commands
- `app/Console/Commands/RunMarketingWorkflow.php`
- `app/Console/Commands/MarkComplied.php`
- `app/Console/Commands/ResetMarketingDemo.php`

### Engine
- `app/Services/Workflow/MarketingWorkflowEngine.php`

### Storage
- `app/Contracts/MarketingStorage.php`
- `app/Services/Storage/DemoSqliteMarketingStorage.php`

### Mail
- `app/Contracts/MarketingMailer.php`
- `app/Services/Mail/LogMarketingMailer.php`
- `app/Services/Mail/LaravelSmtpMarketingMailer.php`

### Provider binding
- `app/Providers/AppServiceProvider.php`

### Email template
- `resources/views/emails/welcome.blade.php`

### Database / seeders
- `database/migrations/...`
- `database/seeders/DemoContactsSeeder.php`

---

## Database model used right now

### `contacts`
Stores:
- contact_id
- personal_email
- first_name
- lifecycle_stage
- lead_status
- cilos_substage_id

### `contact_activities`
Stores:
- activity_id
- contact_id
- tracking_id
- activity_type
- activity_channel
- last_messaging_contents
- last_messaging_date
- attempts

### `contact_engagements`
Stores:
- engagement_id
- contact_id
- engagement_type
- engagement_status
- engagement_channel
- tracking_id
- occurred_at

---

## How the current workflow logic works

### Step 1 — Find contacts
The engine reads all contacts from the storage adapter.

### Step 2 — Get latest activity
For each contact, it checks the latest send activity.

### Step 3 — Check compliance
If the latest activity has a `tracking_id`, the engine checks whether there is a matching engagement row with:
- `engagement_status = YES`
- same `tracking_id`

If no `tracking_id` exists, the fallback logic uses time-based comparison.

### Step 4 — Decide action
The engine then does one of these:

- **Send first email**
  - if no activity exists yet

- **Resend**
  - if the contact is due and has not complied

- **Skip as complied**
  - if compliance exists

- **Skip as not due**
  - if the wait window has not passed yet

- **Skip as max attempts reached**
  - if attempts already reached the configured limit

### Step 5 — Record send activity
When an email is sent, the engine stores:
- a new `tracking_id`
- attempt number
- step key
- last send time

This is what gives the workflow state.

---

## Compliance tracking: current reality

### Important current limitation
**There is no automatic compliance tracking yet.**

That means:
- Mailtrap or SMTP sending only proves the **send path**
- it does **not** automatically mark contacts as complied

### So what should you do right now?
Yes — **you still need to use the compliance command** in demos/tests:

```bash
php artisan marketing:complied test1@example.com
```

### Why?
Because reply / click / form tracking has not been implemented yet.

### What this command currently does
- finds the contact by email
- finds the latest activity
- copies that activity's `tracking_id`
- inserts a `COMPLIED` engagement row
- updates the contact's `lead_status` and `lifecycle_stage`

---

## Requirements

- PHP 8.2+
- Composer
- SQLite (file-based, no server required)
- Optional: SMTP sandbox provider (Mailtrap main, Ethereal fallback)

---

## Local setup

### 1) Install dependencies
```bash
composer install
```

### 2) Configure environment
Copy example file:
```bash
copy .env.example .env
```

### 3) Minimum `.env` for local log-mode
```env
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file

MAIL_DRIVER_MODE=log
MAIL_MAILER=log
MAIL_FROM_ADDRESS="demo@marketing-runner.local"
MAIL_FROM_NAME="Marketing Runner"

COMPLIED_LEAD_STATUS=Engaged
COMPLIED_LIFECYCLE_STAGE=Interest
```

### 4) Create SQLite DB file
PowerShell:
```bash
New-Item -ItemType File -Path .\database\database.sqlite -Force
```

### 5) Generate app key + migrate
```bash
php artisan key:generate
php artisan migrate
```

### 6) Seed demo contacts
```bash
php artisan db:seed --class=DemoContactsSeeder
```

---

## Demo guide — local log mode (recommended stable path)

This is the **most stable demo** and should always remain your fallback path.

### Step 1 — Make sure `.env` uses log mode
```env
MAIL_DRIVER_MODE=log
MAIL_MAILER=log
```

### Step 2 — Clear caches
```bash
php artisan optimize:clear
```

### Step 3 — Reset and reseed
```bash
php artisan marketing:reset --reseed --force
```

### Step 4 — Run the workflow
```bash
php artisan marketing:run --minutes=1 --maxAttempts=3
```

### What should happen
- contacts are processed
- send attempts are recorded in `contact_activities`
- console groups results into:
  - Sent
  - Resent
  - Skipped (already complied)
  - Skipped (not due)
  - Skipped (max attempts reached)

### Step 5 — Inspect generated email output
Open:
- `storage/logs/laravel.log`

### What you should see
- email subject
- tracking id
- body preview

### Step 6 — Simulate compliance
```bash
php artisan marketing:complied test1@example.com
```

### Step 7 — Run again
```bash
php artisan marketing:run --minutes=1 --maxAttempts=3
```

### Expected result
- `test1@example.com` should now appear under:
  - `Skipped (already complied)`

---

## Demo guide — Mailtrap SMTP sandbox testing

This is your **main visible SMTP proof path**.

### What Mailtrap proves
Mailtrap proves:
- your Laravel SMTP mailer works
- your workflow engine can send through SMTP
- your rendered email appears in a sandbox inbox

### What Mailtrap does NOT prove
Mailtrap does **not** automatically track compliance in your current project.

So even in Mailtrap mode:
- you still need to use `marketing:complied` manually

---

### Step 1 — Create / open your Mailtrap Email Sandbox inbox
In Mailtrap:
- create a sandbox inbox/project
- copy the SMTP credentials

### Step 2 — Update `.env` for SMTP mode
```env
MAIL_DRIVER_MODE=smtp
MAIL_MAILER=smtp
MAIL_HOST=your_mailtrap_host
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="demo@marketing-runner.local"
MAIL_FROM_NAME="Marketing Runner"
```

### Step 3 — Clear caches
```bash
php artisan optimize:clear
```

### Step 4 — Reset and reseed
```bash
php artisan marketing:reset --reseed --force
```

### Step 5 — Run the workflow
```bash
php artisan marketing:run --minutes=1 --maxAttempts=1
```

### Why use `--maxAttempts=1` for Mailtrap?
Because Mailtrap free sandbox plans can have strict message rate limits.

You already hit this error:
```txt
550 5.7.0 Too many emails per second. Please upgrade your plan
```

So the safest Mailtrap demo approach is:
- keep sends very small
- avoid blasting all 10 contacts at once
- use `--maxAttempts=1`

### Important current limitation with your current seeder
Your `DemoContactsSeeder` currently creates **10 contacts**.

That means the engine tries to send to all 10 on first run, which can exceed Mailtrap free limits.

---

## Recommended Mailtrap testing strategy right now

### Best current workaround
Use **only one contact** or a very small number of contacts when testing Mailtrap SMTP.

You have a few options:

### Option A — temporarily reduce seeded contacts
Edit `database/seeders/DemoContactsSeeder.php` and reduce the loop count from 10 to 1 or 2 while testing Mailtrap.

Then restore it after testing if needed.

### Option B — create a dedicated SMTP sandbox seeder (recommended)
Create a separate seeder specifically for SMTP sandbox testing, for example:
- `SmtpSandboxContactsSeeder`

This would seed only:
- 1 or 2 contacts

### Why this is recommended
It keeps:
- your normal workflow demo seeder separate
- your SMTP sandbox testing controlled
- Mailtrap free-tier limits easier to handle

---

## Current answer to your question about compliance
### Question
“Right now we have nothing to track compliance, right?”

### Answer
Correct — **there is no automatic compliance tracking yet**.

### Question
“So that means I still have to do the compliance command when I try?”

### Answer
Yes — **you still need to run**:

```bash
php artisan marketing:complied test1@example.com
```

until a real event source is implemented.

---

## Troubleshooting

### Mailtrap error: too many emails per second
You saw:
```txt
550 5.7.0 Too many emails per second. Please upgrade your plan
```

### What this means
Your workflow run tried to send more messages too quickly for the free sandbox limit.

### Safe fixes
1. Use **fewer seeded contacts**
2. Use `--maxAttempts=1`
3. Only test one run at a time
4. Create a dedicated SMTP sandbox seeder with 1–2 contacts
5. Keep log mode as the fallback if Mailtrap free rate limits get in the way

---

## Recommended next steps

### Immediate next steps
1. Keep **log mode** as your stable demo fallback
2. Use **Mailtrap** as the main visible SMTP proof
3. Create a **small SMTP sandbox seeder** for 1–2 contacts
4. Keep using `marketing:complied` manually for compliance proof

### After that
1. Implement automatic compliance source later:
   - click tracking
   - reply tracking
   - form submit
2. Add Azure SQL storage adapter
3. Add Mailchimp adapter when access is ready

---

## Why this structure is good for the wider CRM project

This runner is already aligned with the wider CRM goals because it separates:

- **engine logic**
- **storage implementation**
- **email provider implementation**

So when the rest of the CRM becomes available:
- the engine stays
- storage mapping changes
- provider adapter changes
- workflow logic stays intact

That is why the current code structure is useful and not wasted work.

---

## Safety notes

- `marketing:reset` is demo-only and guarded by environment checks
- Do not commit real SMTP credentials
- Keep provider credentials in local `.env` only
- Use Mailtrap/Ethereal for safe testing before trying real inbox delivery

---

## Suggested command cheat sheet

### Local log demo
```bash
php artisan optimize:clear
php artisan marketing:reset --reseed --force
php artisan marketing:run --minutes=1 --maxAttempts=3
php artisan marketing:complied test1@example.com
php artisan marketing:run --minutes=1 --maxAttempts=3
```

### Mailtrap sandbox test
```bash
php artisan optimize:clear
php artisan marketing:reset --reseed --force
php artisan marketing:run --minutes=1 --maxAttempts=1
# check Mailtrap inbox
php artisan marketing:complied test1@example.com
php artisan marketing:run --minutes=1 --maxAttempts=1
```

---

## Final note

This project currently proves three important things:

1. The **workflow engine logic works**
2. The code is already **adaptable** across providers and storage implementations
3. The runner can be shown in:
   - **safe demo mode** (log)
   - **safe SMTP sandbox mode** (Mailtrap)

That makes it a valid and useful stepping stone toward the full CRM workflow automation system.
