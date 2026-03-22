# Marketing Runner — README (v2.5)

## Purpose of this repository
This repository currently contains **two important development tracks**:

- **v1.8** — the original marketing runner that proves the email send → wait → check compliance → resend loop
- **v2.5** — the newer **workflow kernel foundation** that begins turning the project into a definition-driven, event-aware workflow engine

This README is written for the **current v2.5 state of the branch**, but it also includes a full guide for the older **v1.8 path** because that is still useful for demos, comparison, fallback, and historical context.

The design philosophy for this project is:

- **adaptable**
- **resilient**
- **anti-rewrite**
- **sturdy core, flexible edges**

That means the project tries to keep the workflow core stable while allowing the surrounding integrations, providers, signal sources, and transport methods to evolve later.

---

# Table of contents

1. [Current state of the project](#current-state-of-the-project)
2. [High-level architecture](#high-level-architecture)
3. [Version overview: v1.8 vs v2.5](#version-overview-v18-vs-v25)
4. [v2.5 workflow kernel overview](#v25-workflow-kernel-overview)
5. [How v2.5 fits the wider CRM direction](#how-v25-fits-the-wider-crm-direction)
6. [Requirements](#requirements)
7. [Local setup for v2.5](#local-setup-for-v25)
8. [v2.5 database and branch notes](#v25-database-and-branch-notes)
9. [v2.5 files and folders to know](#v25-files-and-folders-to-know)
10. [v2.5 workflow flow explained](#v25-workflow-flow-explained)
11. [v2.5 demo guide](#v25-demo-guide)
12. [v2.5 terminal commands reference](#v25-terminal-commands-reference)
13. [What v2.5 proves right now](#what-v25-proves-right-now)
14. [Current limitations of v2.5](#current-limitations-of-v25)
15. [v1.8 overview](#v18-overview)
16. [v1.8 local setup guide](#v18-local-setup-guide)
17. [v1.8 demo guide](#v18-demo-guide)
18. [v1.8 SMTP / Mailtrap notes](#v18-smtp--mailtrap-notes)
19. [Suggested demo script for showing progress from v1.8 to v2.5](#suggested-demo-script-for-showing-progress-from-v18-to-v25)
20. [Troubleshooting](#troubleshooting)
21. [Next recommended development steps after v2.5](#next-recommended-development-steps-after-v25)

---

# Current state of the project

## What exists now
This branch contains both:

### v1.8 foundation
The original Laravel marketing runner can:
- load contacts from SQLite
- send emails in **log mode** or **SMTP mode**
- record contact activity
- wait based on time
- check manual compliance
- resend when due
- stop when complied or max attempts are reached

### v2.5 workflow kernel foundation
The newer workflow kernel can:
- store workflow definitions as data
- store workflow versions as data
- enroll a contact into a workflow version
- store normalized workflow events in an event inbox
- process workflow events against the current workflow step
- write workflow step history
- queue a placeholder action decision into a workflow action queue

So the project is no longer only a simple runner. It is now beginning to become a **workflow engine foundation**.

---

# High-level architecture

The code now has two architectural layers in practice.

## Layer 1 — Original marketing runner (v1.8)
This is the earlier, more concrete workflow loop:
- send
- wait
- check compliance
- resend
- stop

It is useful because it proves the basic marketing automation concept and is still easy to demo.

## Layer 2 — Workflow kernel foundation (v2.5)
This is the newer, more general workflow architecture:
- workflow definition
- workflow version
- workflow enrollment
- workflow event inbox
- workflow step log
- workflow action queue
- workflow event processor

This is useful because it is much closer to the long-term CRM architecture.

---

# Version overview: v1.8 vs v2.5

## v1.8
### Main purpose
Prove the original end-to-end marketing loop quickly in a local environment.

### Strong points
- easy to understand
- easy to demo
- supports log mode and SMTP sandbox testing
- records activity and engagement
- good for proving the original campaign follow-up concept

### Main limitation
It is still more **flow-specific** and less **definition-driven**.

## v2.5
### Main purpose
Build the first reusable workflow kernel that can stay with the long-term program.

### Strong points
- workflows now exist as data
- event intake exists as a stable workflow boundary
- state, history, events, and actions are separated more clearly
- processing is now step-aware
- a placeholder action queue exists

### Main limitation
It is still an early kernel slice, not a final complete workflow engine.

---

# v2.5 workflow kernel overview

## Main idea
v2.5 is the first build slice of a **definition-driven, event-aware workflow kernel**.

The current sample workflow proves that:
- a workflow can exist as data
- a version can define step flow
- a contact can be enrolled into that workflow
- a workflow event can enter through a normalized event boundary
- the processor can interpret the event using the current step and step graph
- the processor can update state, write step history, and queue an action decision

## Philosophy
v2.5 follows this philosophy:

- the **core** should stay stable
- the **edges** are allowed to change

That means the workflow core should not be tightly hardcoded to:
- one provider
- one webhook shape
- one signal source
- one compliance method
- one score source
- one prediction source

The event model and action queue exist specifically to keep the core stable while the edges evolve later.

---

# How v2.5 fits the wider CRM direction

The v2.5 workflow kernel is meant to align with the broader CRM direction already discussed in related documents.

## What it is trying to become
It is trying to become the **workflow orchestration layer**, not the full owner of every other subsystem.

That means:
- it should consume workflow-relevant occurrences
- interpret them in workflow context
- change workflow state
- decide actions
- leave provider execution and some source-specific details flexible

## What it is not trying to be yet
It is not yet:
- the final Mailchimp integration layer
- the final Azure SQL integration layer
- the final lead-scoring engine
- the final prediction engine
- the final workflow builder UI

---

# Requirements

## Required
- PHP 8.2+
- Composer
- SQLite
- Laravel-compatible CLI environment

## Optional but useful
- Mailtrap or another SMTP sandbox for v1.8 SMTP testing
- Git
- VS Code or another editor

---

# Local setup for v2.5

This setup assumes you are working on the **workflow kernel v2.x branch**.

## 1. Open the repository root
The repository root is the folder containing:
- `artisan`
- `app/`
- `database/`
- `composer.json`

## 2. Install dependencies
Run this in the repository root:

```bash
composer install
```

### Why
Laravel needs the `vendor/` directory before artisan commands can run.

---

## 3. Copy the environment file
If you do not already have a local `.env`, create one from the example file.

### Windows PowerShell
```bash
copy .env.example .env
```

### Why
Laravel reads DB, app, cache, queue, and mail settings from `.env`.

---

## 4. Generate the app key
Run this in the repository root:

```bash
php artisan key:generate
```

### Why
Laravel expects an `APP_KEY` in `.env`.

---

## 5. Configure the database for v2.5
For the v2.5 branch, the recommended local DB is a **new SQLite file** separate from the older v1.8 demo DB.

### Example `.env` values
```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite
DB_DATABASE=database/workflow_engine.sqlite

SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file

MAIL_DRIVER_MODE=log
MAIL_MAILER=log
MAIL_FROM_ADDRESS="demo@marketing-runner.local"
MAIL_FROM_NAME="Marketing Runner"
```

### Why
This keeps the older v1.8 demo DB separate from the newer workflow-kernel DB.

---

## 6. Create the SQLite file for v2.5
### Windows PowerShell
Run this in the repository root:

```bash
New-Item -ItemType File -Path .\database\workflow_engine.sqlite -Force
```

### Why
Laravel needs the file to exist when using SQLite.

---

## 7. Clear cached config
Run this in the repository root:

```bash
php artisan optimize:clear
```

### Why
This makes sure Laravel picks up the latest `.env` settings.

---

## 8. Run migrations
Run this in the repository root:

```bash
php artisan migrate
```

### Important note
Because Laravel runs all migrations found in `database/migrations`, your new `workflow_engine.sqlite` may include:
- old base/demo tables from v1.8
- plus the new workflow-kernel tables

This is acceptable in the current local development setup.

---

## 9. Seed the sample workflow foundation
Run this in the repository root:

```bash
php artisan db:seed --class=WorkflowFoundationSeeder
```

### Why
This creates the sample workflow definition and version used by the v2.5 demo flow.

---

# v2.5 database and branch notes

## Recommended branch idea
The v2.5 work should live in a separate Git branch from v1.8.

Example branch name:
- `feature/workflow-kernel-v2`

## Why the separate branch matters
This keeps the original runner stable while the newer workflow kernel evolves.

## Recommended DB separation
- `database/database.sqlite` → older v1.8 demo DB
- `database/workflow_engine.sqlite` → newer v2.5 workflow kernel DB

This is not a strict requirement, but it is strongly recommended for clarity.

---

# v2.5 files and folders to know

## Main workflow-kernel files

### Models
- `app/Models/WorkflowDefinition.php`
- `app/Models/WorkflowVersion.php`
- `app/Models/WorkflowEnrollment.php`
- `app/Models/WorkflowStepLog.php`
- `app/Models/WorkflowEventInbox.php`
- `app/Models/WorkflowActionQueue.php`

### Commands
- `app/Console/Commands/EnrollWorkflowContact.php`
- `app/Console/Commands/InjectWorkflowEvent.php`
- `app/Console/Commands/ProcessWorkflowEvents.php`

### Processor service
- `app/Services/Workflow/WorkflowEventProcessor.php`

### Seeder
- `database/seeders/WorkflowFoundationSeeder.php`

### Workflow-kernel migrations
- `database/migrations/*create_crmdb_workflow_definition_table.php`
- `database/migrations/*create_crmdb_workflow_version_table.php`
- `database/migrations/*create_crmdb_workflow_enrollment_table.php`
- `database/migrations/*create_crmdb_workflow_step_log_table.php`
- `database/migrations/*create_crmdb_workflow_event_inbox_table.php`
- `database/migrations/*create_crmdb_workflow_action_queue_table.php`

---

# v2.5 workflow flow explained

The current sample workflow is intentionally simple.

## Sample workflow
- Workflow key: `MKT_WELCOME_FLOW`
- Version: `1`
- Initial step: `AWAIT_SIGNAL`
- Accepted events at that step:
  - `MANUAL_TEST_EVENT`
  - `EMAIL_CLICK`
- Next step: `COMPLETE`
- `COMPLETE` is terminal

## What that means
The current workflow kernel is proving this sequence:

1. workflow definition exists
2. workflow version exists
3. contact is enrolled into the workflow version
4. event enters normalized workflow inbox
5. processor reads the current step from enrollment
6. processor reads the step graph from the workflow version
7. processor checks whether the current step accepts that event
8. processor transitions the enrollment to the next step
9. if the next step is terminal, enrollment is completed
10. a step log row is written
11. a placeholder action decision is queued in `CrmDB_Workflow_Action_Queue`

This is the current workflow-kernel foundation slice.

---

# v2.5 demo guide

This is the recommended demo path for the newer workflow kernel.

## Demo goal
Show that the project has moved beyond a hardcoded email runner and has started becoming a real workflow kernel.

## Demo sequence

### 1. Seed the sample workflow
Run in project root:

```bash
php artisan db:seed --class=WorkflowFoundationSeeder
```

### What to explain
This inserts:
- one workflow definition
- one workflow version
- one step graph
- one placeholder action config

---

### 2. Enroll a contact into the workflow
Run in project root:

```bash
php artisan workflow:enroll CNT_3001 WFL_001 WFLV_001
```

### What to explain
This command now:
- validates the workflow definition exists
- validates the workflow version exists
- validates that the version belongs to the workflow
- checks whether an active enrollment already exists
- creates the enrollment if valid
- writes the first step log row

### What this proves
This proves the workflow runtime state now exists as data.

---

### 3. Inject a workflow event
Run in project root:

```bash
php artisan workflow:event MANUAL_TEST_EVENT CNT_3001 --workflowId=WFL_001 --workflowVersionId=WFLV_001
```

### What to explain
This inserts a normalized workflow-facing event into `CrmDB_Workflow_Event_Inbox`.

### What this proves
This proves the workflow boundary now accepts normalized workflow events instead of relying only on hardcoded loops.

---

### 4. Process workflow events
Run in project root:

```bash
php artisan workflow:process
```

### What to explain
This command now:
- reads pending workflow events
- resolves workflow context
- reads the current step from the enrollment
- reads the step graph from the workflow version
- evaluates whether the current step accepts the event
- advances the workflow to the next step
- completes the run if the next step is terminal
- writes the step log
- queues a placeholder action decision in the action queue

### What this proves
This is the main proof of the workflow kernel.

---

## What to say during the demo
A good summary line is:

> “We are now moving from the original demo runner into a definition-driven, event-aware workflow kernel. The workflow now exists as versioned data, a contact can be enrolled into it, the engine can process normalized events against the current workflow step, and the system records both step history and action decisions separately.”

---

# v2.5 terminal commands reference

## Enroll a contact
```bash
php artisan workflow:enroll CNT_3001 WFL_001 WFLV_001
```

### Purpose
Creates a workflow enrollment and initial step log.

---

## Inject an event
```bash
php artisan workflow:event MANUAL_TEST_EVENT CNT_3001 --workflowId=WFL_001 --workflowVersionId=WFLV_001
```

### Purpose
Creates a normalized workflow event in the inbox.

---

## Process events
```bash
php artisan workflow:process
```

### Purpose
Processes all pending workflow events.

---

# What v2.5 proves right now

v2.5 already proves these important things:

- workflows can exist as data
- workflow versions can exist as data
- runtime workflow state can exist as data
- workflow-relevant occurrences can enter through a normalized boundary
- events can be interpreted in step context
- workflow state and workflow history are separated
- action intent can be queued separately from event intake and state progression

This is meaningful progress toward the larger CRM workflow architecture.

---

# Current limitations of v2.5

These are important to state honestly.

## What is not complete yet
- no visual workflow builder UI yet
- no full action executor yet
- no `Workflow_Action_Result` table yet
- no real provider integration yet
- no final Mailchimp integration yet
- no final Azure SQL integration yet
- no final click redirect flow yet
- no final form submission integration yet
- no final reply parsing yet
- no BP/TS/YD production integration yet
- event taxonomy may still expand later

## What that means
v2.5 is a **real workflow foundation slice**, but it is still an early kernel, not a final finished workflow platform.

---

# v1.8 overview

The older v1.8 runner is still useful and should be kept.

## What v1.8 does
The original runner proves the concrete campaign loop:
- send first email
- wait
- check compliance
- resend if needed
- stop if complied or max attempts reached

## Why it still matters
It is still useful for:
- historical comparison
- showing the original business proof-of-concept
- fallback demos
- SMTP testing
- Mailtrap/log-mode demonstrations

---

# v1.8 local setup guide

This section mirrors the earlier v1.8 README idea.

## 1. Configure `.env` for v1.8 database
If you want to run the older v1.8 flow, point SQLite back to the original DB.

### Example
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### Why
The original runner used the earlier database file and earlier contact/activity/engagement flow.

---

## 2. Create the v1.8 DB file if needed
Run in project root:

```bash
New-Item -ItemType File -Path .\database\database.sqlite -Force
```

---

## 3. Clear config cache
```bash
php artisan optimize:clear
```

---

## 4. Run migrations
```bash
php artisan migrate
```

### Why
This creates the v1.8 demo tables such as:
- contacts
- contact_activities
- contact_engagements

---

## 5. Seed demo contacts
Run in project root:

```bash
php artisan db:seed --class=DemoContactsSeeder
```

### Why
This inserts local demo contacts for the v1.8 send/wait/check flow.

---

# v1.8 demo guide

## Demo goal
Show the original campaign automation proof-of-concept.

## Path A — safe log-mode demo

### 1. Set mail mode to log
In `.env`:

```env
MAIL_DRIVER_MODE=log
MAIL_MAILER=log
MAIL_FROM_ADDRESS="demo@marketing-runner.local"
MAIL_FROM_NAME="Marketing Runner"
```

### 2. Run the workflow
```bash
php artisan marketing:run --minutes=1 --maxAttempts=3
```

### What it proves
This proves:
- contacts are loaded
- activity is created
- attempts are recorded
- the workflow loop works

### Where to inspect output
Check:
- `storage/logs/laravel.log`
- database tables such as `contact_activities`

---

## Path B — SMTP / Mailtrap sandbox demo

### 1. Set SMTP values in `.env`
Example structure:

```env
MAIL_DRIVER_MODE=smtp
MAIL_MAILER=smtp
MAIL_HOST=your.smtp.host
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="demo@example.com"
MAIL_FROM_NAME="Marketing Runner"
```

### 2. Clear config cache
```bash
php artisan optimize:clear
```

### 3. Run the workflow
```bash
php artisan marketing:run --minutes=1 --maxAttempts=3
```

### What it proves
This proves the send path can work through a real SMTP sandbox.

### Important note
SMTP sending still does **not** automatically mark compliance.

---

## Manual compliance step for v1.8
If you want to mark a contact as complied, run:

```bash
php artisan marketing:complied test1@example.com
```

### What this does
It:
- finds the contact by email
- finds the latest activity
- copies the `tracking_id`
- inserts a compliance engagement row
- updates contact status fields

### Why this still matters
Because automatic compliance capture is not yet complete.

---

## Reset the v1.8 demo state
Run:

```bash
php artisan marketing:reset --reseed --force
```

### What this does
It resets the demo workflow state and reseeds demo contacts so the demo can be reused.

---

# v1.8 SMTP / Mailtrap notes

## What log mode is good for
- safe demos
- no real send rate limits
- no mailbox dependency
- no risk of sending to real people

## What SMTP sandbox mode is good for
- showing that the send path is real
- giving non-technical viewers more confidence
- comparing provider behavior later

## Limitation
SMTP sandbox mode still does not equal full workflow compliance capture.

---

# Suggested demo script for showing progress from v1.8 to v2.5

A strong progress explanation is:

## Step 1 — remind them what v1.8 proved
- local send/wait/check/resend loop
- activity + engagement tracking
- log mode / SMTP proof

## Step 2 — explain why v2.5 was started
- we need something more reusable
- we need something aligned with long-term CRM workflow architecture
- we need definition-driven and event-aware workflow logic

## Step 3 — show what v2.5 proves now
- workflow exists as data
- version exists as data
- contact can be enrolled
- event enters normalized inbox
- processor reads step graph
- state changes
- history is logged
- action decision is queued

This creates a very strong “v1.8 to v2.5” progress story.

---

# Troubleshooting

## 1. `php artisan migrate` ran old migrations too
This is normal if all migrations are still inside `database/migrations` and the new DB is empty.

### What it means
Laravel runs all unapplied migrations for the active DB.

### Is that okay?
Yes, for this current local dev setup it is acceptable.

---

## 2. `workflow:enroll` says workflow or version not found
Make sure you seeded the workflow foundation first:

```bash
php artisan db:seed --class=WorkflowFoundationSeeder
```

---

## 3. `workflow:event` creates the event but `workflow:process` ignores it
Check whether:
- the contact is actually enrolled
- the enrollment is still `ACTIVE`
- the event type matches what the current step accepts

---

## 4. `workflow:process` finds no pending events
Check whether the event row was created with:
- `ProcessingStatusCode = PENDING`

---

## 5. Antivirus flags the new PHP workflow files
Some antivirus tools may flag new local PHP command or processor files using generic heuristic names such as `IDP.Generic`.

This is commonly triggered by things like:
- CLI automation
- random ID generation
- DB write loops
- newly created local scripts with low reputation

If the files are your own local source files and match your repository history, this is usually more likely to be a heuristic false positive than actual malware.

Still, always follow your team or company security policy.

---

# Next recommended development steps after v2.5

The most natural next upgrades are:

## 1. Add `Workflow_Action_Result`
So action execution history can be recorded separately.

## 2. Add a placeholder action executor
So queued actions can be visibly “handled” in local development.

## 3. Add a tracked-link prototype
So click-based workflow events can enter through a more realistic edge.

## 4. Add one more non-terminal sample step
So the workflow can show a transition that does not immediately end.

## 5. Add richer event states
Examples:
- deferred
- unmapped
- duplicate

## 6. Later map v2.5 to the wider CRM architecture
This includes future alignment with:
- BP provider signals
- TS score/lifecycle changes
- YD prediction signals
- Azure SQL target storage

---

## Final note
This README is intentionally written for the **current v2.5 state** of the branch while still preserving the older v1.8 guide.

The key idea is:

- **v1.8** proved the original marketing automation loop
- **v2.5** proves the first real workflow kernel foundation

That is the current progress story of this repository.
