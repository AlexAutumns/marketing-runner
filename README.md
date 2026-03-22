# Marketing Runner Workflow Kernel (v2.12)

## 1. What This Branch Is
This branch is the **workflow-kernel foundation** for the marketing automation part of the CRM project.

Its purpose is to provide the workflow execution and orchestration layer that can:

- store workflow definitions
- store workflow versions / rule sets
- track which contact is currently inside a workflow
- receive workflow-facing events
- process those events step by step
- record workflow history
- queue workflow-decided actions
- support operational inspection through CLI commands

This branch is **not** the campaign builder.
It is **not** the content builder.
It is **not** the lead-scoring engine.
It is **not** yet the final production-complete CRM workflow system.

Instead, it is the workflow foundation that is being shaped to sit between:

- upstream campaign/content setup systems
- downstream action execution and scoring-related integrations

---

## 2. Current Project Scope
At the current state of the branch, the workflow kernel can:

- define workflows as data
- define workflow rule/config versions as data
- create workflow runtime state for a contact through enrollment
- accept workflow-facing events into a normalized workflow inbox
- process those events using step-aware workflow logic
- record workflow step history separately from current state
- queue workflow-decided actions separately from action execution
- inspect workflow runs, events, step logs, and queued actions through CLI commands

In practical terms, this means the branch now supports a full local workflow-kernel loop:

1. seed a sample workflow
2. enroll a contact
3. inject a workflow-facing event
4. process the event
5. inspect the workflow state, step history, and queued actions

---

## 3. Architecture Overview
The current branch is organized around six core workflow concepts.

### 3.1 `workflow_definition`
This stores the **stable identity** of a workflow.

It answers questions like:

- what is this workflow called?
- what category does it belong to?
- what campaign context does it belong to?

This table should stay lightweight and stable.
It should not become a duplicate of campaign-builder tables.

### 3.2 `workflow_version`
This stores the **rule/config version** of a workflow.

It answers questions like:

- what step graph does this workflow use?
- what event categories and event types are relevant?
- what actions should be queued on step completion?

This is intentionally separate from `workflow_definition` so workflow identity and workflow behavior do not get mixed together.

### 3.3 `workflow_enrollment`
This stores the **current runtime state** of a contact inside a workflow version.

It answers questions like:

- which contact is inside this workflow?
- what workflow version is the contact currently using?
- what is the current workflow step?
- is the workflow run active, completed, waiting, or failed?

This is the live workflow run record.

### 3.4 `workflow_event_inbox`
This stores **workflow-facing intake events**.

This table acts as the workflow input boundary.
It allows the workflow kernel to receive structured workflow events without tightly depending on every raw upstream system.

### 3.5 `workflow_step_log`
This stores **workflow history**.

It answers questions like:

- what step was processed?
- what event was involved?
- what result happened?
- what transition occurred?

This keeps workflow history separate from current workflow state.

### 3.6 `workflow_action_queue`
This stores **workflow-decided action intent**.

It answers questions like:

- what action should happen next?
- which workflow run produced that action?
- what is the current action queue status?

This table intentionally records action intent only.
It does not directly execute external side effects.

---

## 4. Design Philosophy
This branch is being developed around the following design philosophy:

- **Flexible edges**
- **Robust, solid core**
- **Minimize rewrite**
- **Readable for human eyes**
- **Explicit contracts over cleverness**
- **Separate workflow decision from external execution**

### What “flexible edges” means here
The workflow kernel should remain stable even if surrounding systems change.

That means:

- upstream systems may evolve
- event names may expand over time
- campaign/content systems may change shape
- delivery/scoring integrations may change later

The workflow core should still remain understandable and durable.

### What “robust, solid core” means here
The core workflow concepts should stay clear and separate:

- workflow identity
- workflow behavior/version
- workflow runtime state
- workflow event intake
- workflow history
- workflow action intent

### What “explicit contracts over cleverness” means here
This branch avoids relying on hidden behavior or overly magical code.

When flexibility is needed, it should still be controlled through:

- clear event categories
- clear event types
- clear workflow step definitions
- clear queue boundaries

---

## 5. Campaign-Aware Workflow Direction
The workflow definition is now shaped to be **campaign-aware**, but only in a lightweight way.

### Current campaign-aware fields in `workflow_definition`
- `MarketingCampaignID`
- `CampaignTemplateID`
- `ObjectiveCode`
- `PlatformCode`

### Why these fields were added
These fields were added because the workflow kernel should not behave like an isolated abstract workflow anymore.

The workflow is intended to sit downstream of:

- campaign-building systems
- content-building systems

So the workflow definition now keeps lightweight campaign context so the workflow can be understood in business terms.

### Why only lightweight context is stored
The workflow definition should not become a duplicate of campaign-builder or content-builder data.

That means it should **not** be overloaded with fields like:

- campaign budget
- country targeting
- placement-level detail
- asset detail
- large content metadata

Those belong upstream.

The workflow definition should only keep the context it genuinely needs for:

- identity
- routing
- explainability
- future integration readiness

---

## 6. Event Contract
The event model is one of the most important parts of this branch.

### 6.1 Stable event categories
The branch currently uses broad, durable event categories such as:

- `ENGAGEMENT`
- `CAMPAIGN_CONTEXT`
- `WORKFLOW_CONTROL`

These categories are intentionally broad.
They help keep the workflow intake model structured even if exact upstream event names change later.

### 6.2 Known event types
The current branch also uses more specific event types, such as:

- `MANUAL_TEST_EVENT`
- `EMAIL_LINK_CLICKED`
- `BROCHURE_LINK_CLICKED`
- `FORM_SUBMITTED`
- `CAMPAIGN_READY`
- `ASSET_APPROVED`

### 6.3 Why categories are broad but event matching is specific
This is an intentional design choice.

- **Categories** keep the event model stable and easier to evolve.
- **Event types** keep workflow step behavior precise.

At the current stage, the processor matches workflow steps by **accepted event types**, not by category alone.

That prevents the workflow from becoming too vague while still keeping the intake model flexible.

### 6.4 Why category validation is strict but event-type validation is softer
The event command uses a deliberate balance:

- category validation is stricter
- event-type validation is softer

Why:

- categories are meant to stay stable
- exact event-type naming may evolve as upstream systems evolve

This allows the workflow intake layer to remain practical during ongoing integration work.

---

## 7. Workflow Processing Flow
The workflow processor follows a step-aware workflow flow.

### Current high-level processing path
1. a workflow-facing event is stored in `workflow_event_inbox`
2. the processor loads pending events
3. the processor resolves the matching workflow enrollment/run
4. the processor resolves the workflow version and step graph
5. the processor resolves the current workflow step
6. the processor checks whether the current step accepts the event type
7. if accepted, the workflow transitions to the next step
8. the processor writes a step log entry
9. the processor queues configured workflow actions
10. the event is marked as processed, ignored, or failed

### Ignored vs failed vs processed
- **Ignored** means the event was valid enough to record but not usable for the current workflow path.
- **Failed** means workflow processing could not continue safely.
- **Processed** means the event was successfully interpreted in workflow context.

### Why this matters
This flow keeps the workflow behavior explicit and easier to reason about.

---

## 8. Command Reference
The branch currently exposes two main groups of commands.

### 8.1 Core workflow commands

#### `workflow:enroll`
Creates workflow runtime state for a contact.

**Purpose:**
- validate workflow definition and version
- prevent duplicate active-like runs
- create the workflow enrollment
- write the initial step log

**Example:**
```bash
php artisan workflow:enroll CNT_9001 WFL_001 WFLV_001
```

---

#### `workflow:event`
Creates a workflow-facing event in the workflow inbox.

**Purpose:**
- normalize workflow-facing event intake
- support local development and demos
- shape future integration input contracts

**Example:**
```bash
php artisan workflow:event EMAIL_LINK_CLICKED CNT_9001 --workflowId=WFL_001 --workflowVersionId=WFLV_001
```

---

#### `workflow:process`
Processes currently pending workflow events.

**Purpose:**
- resolve workflow run context
- evaluate current step
- apply step transition
- write step logs
- queue workflow actions

**Example:**
```bash
php artisan workflow:process
```

---

### 8.2 Inspection commands

#### `workflow:show-enrollments`
Inspect workflow runtime state.

**Useful options:**
- `--contactId=`
- `--workflowId=`
- `--status=`
- `--limit=`
- `--detail`

**Example:**
```bash
php artisan workflow:show-enrollments --contactId=CNT_9001 --detail
```

---

#### `workflow:show-events`
Inspect workflow inbox events.

**Useful options:**
- `--contactId=`
- `--workflowId=`
- `--eventType=`
- `--category=`
- `--status=`
- `--limit=`
- `--detail`

**Example:**
```bash
php artisan workflow:show-events --category=ENGAGEMENT --detail
```

---

#### `workflow:show-step-logs`
Inspect workflow history.

**Useful options:**
- `--enrollmentId=`
- `--contactId=`
- `--workflowId=`
- `--status=`
- `--limit=`
- `--detail`

**Example:**
```bash
php artisan workflow:show-step-logs --contactId=CNT_9001 --detail
```

---

#### `workflow:show-actions`
Inspect queued workflow action intent.

**Useful options:**
- `--enrollmentId=`
- `--contactId=`
- `--workflowId=`
- `--actionType=`
- `--status=`
- `--limit=`
- `--detail`

**Example:**
```bash
php artisan workflow:show-actions --contactId=CNT_9001 --detail
```

---

## 9. Quick Demo / Verification Commands
This branch supports a simple end-to-end local demo flow that can be used for:

- sanity-check testing
- onboarding
- future demos
- validating that the workflow kernel is still working after changes

### Step 1 — Seed the sample workflow
```bash
php artisan db:seed --class=WorkflowFoundationSeeder
```

**What this command does:**
- loads the reference workflow definition
- loads the reference workflow version
- loads the reference step graph and placeholder action config

**What the result means:**
The system now has a known workflow configuration available for local testing and demonstration.

### Step 2 — Enroll a contact into the workflow
```bash
php artisan workflow:enroll CNT_9001 WFL_001 WFLV_001
```

**What this command does:**
- validates the workflow definition
- validates the workflow version
- checks that the version belongs to the workflow
- prevents duplicate active-like enrollments
- creates the workflow runtime state for the contact
- writes the initial step log row

**What the result means:**
The contact now has a live workflow run inside the workflow kernel.

### Step 3 — Inject a workflow-facing event
```bash
php artisan workflow:event EMAIL_LINK_CLICKED CNT_9001 --workflowId=WFL_001 --workflowVersionId=WFLV_001
```

**What this command does:**
- creates a workflow-facing event in the workflow inbox
- stores it with event type, category, source, and workflow context
- marks it as pending until the processor handles it

**What the result means:**
The workflow kernel now has a signal that it may need to interpret.

### Step 4 — Process the pending workflow event
```bash
php artisan workflow:process
```

**What this command does:**
- reads pending workflow events
- resolves the matching workflow run
- checks the current workflow step
- accepts or ignores the event based on the step graph
- updates workflow state
- writes workflow history
- queues the next workflow action

**What the result means:**
The workflow kernel has interpreted the event and applied the next workflow decision.

### Step 5 — Inspect the resulting workflow state
```bash
php artisan workflow:show-enrollments --contactId=CNT_9001 --detail
php artisan workflow:show-events --contactId=CNT_9001 --detail
php artisan workflow:show-step-logs --contactId=CNT_9001 --detail
php artisan workflow:show-actions --contactId=CNT_9001 --detail
```

**What these commands do:**
- show the current workflow run state
- show the workflow-facing event that entered the inbox
- show the workflow history written during processing
- show the queued action intent produced by the workflow

**What the results mean:**
Together, these commands let the operator verify the full workflow-kernel path from workflow setup to action intent.

### Quick interpretation of a successful demo
If the demo works as expected, it should show that:

- the workflow exists as stored data
- the contact entered workflow runtime state
- the workflow accepted a workflow-facing event
- the workflow advanced through the step graph
- the workflow wrote step history
- the workflow queued a next action

---

## 10. Example Demo / Verification Flow
The quick demo above is the fastest way to verify the workflow kernel locally.

The branch currently supports this full local workflow loop:

1. seed the sample workflow  
2. enroll a contact  
3. inject a workflow event  
4. process the event  
5. inspect the resulting state, history, and action queue  

### Why this flow is useful
This gives a simple, repeatable way to verify that the workflow kernel is still behaving correctly after changes.

### What it proves
It proves that the branch currently supports:

- workflow definition as data
- workflow version as data
- workflow runtime state for a contact
- workflow-facing event intake
- step-aware processing
- workflow history logging
- queued workflow action intent

---

## 11. Relationship to the Wider CRM Project
This branch should be understood as the **workflow orchestration side** of the wider marketing CRM effort.

### Current wider-role understanding
- **Campaign-building system** = creates campaign setup, templates, and campaign context
- **Content-building system** = creates/manages content and content-related workflow context
- **Lead-scoring system** = uses engagement and behavior to calculate scoring outcomes
- **This workflow-kernel branch** = interprets workflow-facing events and coordinates workflow progression/action intent

### Why this matters
This branch should not try to own the entire marketing system.
Its strength is in being the workflow orchestration layer that sits between upstream context and downstream decisions/actions.

---

## 11. Current Limitations
This branch is much stronger than the earlier prototype stages, but it still has clear limits.

### Current limitations include
- no full provider execution path yet
- no final action executor yet
- no full direct integration with the campaign-building system yet
- no full direct integration with the content-building system yet
- no final direct integration with the lead-scoring system yet
- event intake is still semi-controlled/manual in the current development flow
- this branch is not yet production-ready for high-scale campaign execution

### Why this is stated clearly
This README is meant to be realistic.
The current branch is a strong workflow-kernel foundation, not yet a final production-complete workflow platform.

---

## 12. v1.8 Note / Historical Context
The earlier v1.8 runner proved the original marketing-runner concept.

That earlier version was useful for showing:
- a simple send → wait → check → resend pattern
- a basic workflow idea in motion

This current branch moves beyond that earlier runner by building a more durable workflow-kernel foundation with:
- workflow definition
- workflow version
- workflow run state
- workflow-facing event intake
- workflow history
- action queue intent
- inspection commands

For the earlier runner context, keep the older document separately as:
- `READMEv1.8.md`

---

## 13. Likely Next Areas of Development
Likely future areas of development include:

- stronger integration with campaign/context systems
- stronger integration with content/context systems
- richer event sources
- stronger action execution path
- clearer workflow-to-scoring handoff
- further production hardening

This section is intentionally short because the exact next work may still evolve based on wider project changes.

---

## 14. Final Note
This branch should be viewed as a **workflow-kernel foundation that is being stabilized and made integration-ready**.

Its purpose is to create a durable workflow core that can:

- receive structured workflow-facing events
- interpret workflow meaning step by step
- track current workflow state
- record workflow history
- express next-action intent clearly

That is the current value of this branch in the wider CRM project.

