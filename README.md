# Payroll Portal

Payroll, payslips and leave management, built on **Laravel 13 / PHP 8.3** with a
Domain-Driven Design layout.

Three things it does:

1. **Employees sign in and download their payslips.**
2. **HR enters payroll and issues payslips**, with statutory contributions
   calculated rather than typed.
3. **Leave management** — employees apply online, see their balances, and track
   where a request has got to.

---

## Read this before running real payroll

The statutory rates in `config/statutory/malaysia.php` are **development
defaults, not a certified rate set**. They give the engine a working shape; they
are not maintained in step with rate changes, and the percentage fallbacks for
SOCSO and EIS will not match the published banded tables at every wage level.

Before the first live run:

- Have your payroll or tax adviser reconcile every value in
  `config/statutory/malaysia.php` against the current published tables from
  **KWSP** (EPF), **PERKESO** (SOCSO and EIS) and **LHDN**.
- Paste the official SOCSO and EIS contribution tables into the `bands` arrays.
  When those arrays are non-empty the engine uses them in preference to the
  percentages, and the published amounts are what an audit checks against.
- Set `rate_set_label` to the version you reconciled, so a payslip can be traced
  back to a known rate set.
- Re-run that reconciliation whenever rates change.

**PCB (monthly tax) defaults to manual entry.** Malaysia's MTD formula depends on
year-to-date remuneration, accumulated deductions, marital status, dependants and
zakat — none of which can be derived from one month in isolation. Rather than
approximate it and quietly under-withhold, the default engine returns zero and
HR enters the figure from their own LHDN-approved calculator. A
`progressive_estimate` engine exists for sanity-checking a run; it is explicitly
not suitable for determining what gets remitted.

Under-remitting statutory contributions is the employer's liability, not the
employee's.

---

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate

# Runs on SQLite out of the box — no database server needed.
php artisan migrate --seed

php artisan serve
php artisan queue:work --queue=documents,default   # renders payslip PDFs
```

To use MySQL instead, uncomment the `DB_*` block in `.env`, create the database,
then run `php artisan migrate --seed`.

**Requires PHP 8.3 or newer.** If `composer install` reports a conflict, check
that no third-party package in your fork is still pinned to a pre-Laravel-13
constraint — `laravel/tinker` in particular must be `^3.0`, since 2.x caps at
`illuminate/support ^12.0`.

The seeders create a small demo organisation. **Every demo account uses the
password `password`** — change or remove them before this touches a real network.

| Account | Role | Sees |
|---|---|---|
| `nurul.rahman@example.com` | HR admin | Everything |
| `weiming.tan@example.com` | Manager | Own data + approves their team's leave |
| `arvind.kumar@example.com` | Employee | Own payslips and leave |

Public holidays: the seeder adds only the fixed-date national ones. The moving
holidays (Hari Raya, Chinese New Year, Deepavali, Wesak, Thaipusam) and any state
holidays are announced rather than calculated, so HR enters them each year into
`public_holidays`. Leave day counts depend on this table being right.

```bash
php artisan test
php artisan leave:grant-entitlements 2027   # opens a new leave year
```

---

## Attendance

Employees report a monthly attendance sheet — one row per day they want on
record, with the columns Date, Day, Hours, Leave Type and Remarks — and HR
approves or returns it. The workflow is Draft → Submitted → Approved, with a
Returned state that sends the sheet back to the employee with HR's note for
another pass.

The one rule worth calling out is **leave reconciliation on submit**: a row
marked with a leave type is only accepted if the employee already has *approved*
leave of that type on that date. That check is the Attendance context's single
dependency on the Leave context — it reads through the `LeaveVerifier` port,
implemented by `ApprovedLeaveVerifier`, which joins `leave_days` →
`leave_applications` (approved only) → `leave_types`. Nothing else in Attendance
touches Leave's tables, so if Leave's storage changes, that one adapter is the
only thing to update.

"Hours" are stored as whole minutes (an integer), the same discipline used for
money, so a half-day is 240 rather than a float that drifts.

---

## The Vue front end

A Vue 3 + Vite single-page app lives in `frontend/`. It consumes the API above
and nothing else — no shared session, no Blade.

```bash
cd frontend
npm install
cp .env.example .env
npm run dev      # http://localhost:5173, proxied to Laravel on :8000
npm run build    # production bundle in frontend/dist
```

Leave `VITE_API_BASE_URL` empty in development and the Vite proxy handles it. In
production, point it at the Laravel application and add that origin to
`CORS_ALLOWED_ORIGINS` in the Laravel `.env`.

**How it is put together**

- **Vue 3 `<script setup>`**, Vue Router and Pinia. Routes are lazily imported,
  so each screen is its own chunk.
- **One axios instance** attaches the bearer token and treats a 401 as a single
  event — the session ended — so no view handles it individually.
- **`readError()`** turns all three failure registers the API speaks (Laravel
  validation bags, domain rule violations, plain HTTP errors) into one readable
  sentence, and `readFieldErrors()` puts messages next to their input.
- **`useAsync()`** holds the loading / failed / empty states that every screen
  needs, so views stay about their subject.
- **Navigation is built from the signed-in role**, and the router guards each
  route against `meta.roles` — a manager never sees an HR link they would only
  be refused at.
- **Payslip PDFs are fetched as a blob** with the token attached rather than
  linked directly, because they sit on a private disk.

**Screens**

| Route | Who | What |
|---|---|---|
| `/sign-in` | Everyone | Sign in |
| `/` | Everyone | Latest net pay, leave available, anything awaiting you |
| `/payslips` | Employee | Payslip history + year-to-date band |
| `/payslips/:id` | Employee | Full breakdown, PDF download |
| `/leave` | Employee | Balances with taken/pending meter, own requests |
| `/leave/apply` | Employee | Apply, with the leave type's rules shown inline |
| `/attendance` | Employee | Monthly attendance sheet: report days, submit to HR |
| `/hr/attendance` | HR | Review queue; approve or return submitted sheets |
| `/approvals` | Manager, HR | Decision queue; declining requires a reason |
| `/hr/payroll` | HR | Runs list, open a run |
| `/hr/payroll/:id` | HR | The four-step run workflow and the register |
| `/hr/employees` | HR | Directory and registration |

**On the design.** The subject is a payroll record, so the interface borrows
from printed financial statements rather than from dashboards: hairline rules,
letterspaced monospace micro-labels, and dotted leaders carrying the eye from a
label across to its figure. Every amount is set in IBM Plex Mono with tabular
numerals so digits align in a column. The one bold element is the *ledger band*
— a deep-teal block carrying the single most important figure on each screen,
which is the on-screen echo of the net-pay band printed on the payslip PDF, so
the document and the portal read as the same object.

---

## Layout

Each bounded context is a self-contained slice under `src/`, with the same four
layers. Dependencies point inward: the domain layer knows nothing about Laravel,
HTTP or the database.

```
src/
├── Shared/      Money, DateRange, identifiers, domain events, clock
├── Identity/    accounts, roles, sign-in
├── Employee/    staff records, compensation, statutory profile
├── Payroll/     payroll runs, payslips, statutory engine, PDF rendering
└── Leave/       leave types, entitlements, applications, working-day calendar

    <Context>/
    ├── Domain/          entities, value objects, repository + service *interfaces*
    ├── Application/     use cases (commands + handlers), read queries
    ├── Infrastructure/  Eloquent records, mappers, adapters — the framework lives here
    └── Presentation/    controllers, form requests, API resources, policies
```

`app/` holds only framework glue. `app/Providers/DomainServiceProvider.php` is the
single file that decides which adapter satisfies which port.

### Decisions worth knowing about

**Money is never a float.** `Money` holds integer minor units and every rounding
decision is explicit. A payslip that adds up on screen adds up in the ledger.

**Aggregates are plain PHP.** `Employee`, `Payslip`, `PayrollRun`,
`LeaveApplication` and `LeaveEntitlement` have no Eloquent in them. Persistence
goes through a `*Mapper` and an `Eloquent*Repository`. It costs a mapper per
aggregate and buys business rules you can read without knowing Laravel, and unit
tests that run without booting it.

**Payslips freeze an employee snapshot.** A payslip is a historical record. If
someone changes department or bank account next year, last year's payslip still
shows what was true then.

**Issued payslips are immutable.** A correction creates a new payslip that
supersedes the original, so the audit trail always shows what the employee was
first given.

**Leave days are stored one row per day.** That is what lets payroll ask "how
many unpaid days fell in July" and get an exact answer when a leave spell
straddles two pay periods — something a start/end date pair cannot do.

**Leave balances use a reservation.** Applying moves days from *granted* to
*pending* under a row lock; approval moves them to *taken*, rejection hands them
back. Two requests submitted seconds apart cannot both claim the last day.

**Payroll and Leave talk through a port.** Payroll depends on its own
`UnpaidLeaveLedger` interface; `LeaveContextUnpaidLeaveLedger` is the only place
the two vocabularies meet. If leave ever becomes a separate service, that is the
one file that changes.

**Statutory calculation sits behind an interface.** The payroll domain does not
know what EPF is — it asks a `StatutoryContributionCalculator` what to withhold.
Changing country, or updating next year's rates, touches one adapter.

**Payslip PDFs are never public.** They are written to a private disk and served
only through an endpoint that authorises each request, so a leaked URL is useless
on its own.

---

## API

All routes are JSON and prefixed `/api`. Authentication is a Sanctum bearer token
(`POST /api/auth/login`).

### Everyone

| | |
|---|---|
| `POST /auth/login` | Sign in, returns a 12-hour token |
| `POST /auth/logout` | Revoke the current token |
| `GET /auth/me` | Current account and linked employee |

### Employee self-service

| | |
|---|---|
| `GET /me/payslips` | Own payslip history plus year-to-date totals |
| `GET /payslips/{id}` | One payslip in full |
| `GET /payslips/{id}/download` | The PDF |
| `GET /me/leave/balances` | Entitled, taken, pending, available per leave type |
| `GET /me/leave/applications` | Own requests and their status |
| `GET /leave/types` | Leave types and their rules |
| `POST /leave/applications` | Apply (multipart when attaching a medical certificate) |
| `POST /leave/applications/{id}/cancel` | Withdraw or cancel |

### Approvers — `role:manager,hr_admin`

| | |
|---|---|
| `GET /approvals/leave` | Pending requests in scope |
| `POST /approvals/leave/{id}` | `{"decision":"approve"}` or `{"decision":"reject","note":"…"}` |
| `GET /approvals/leave/calendar` | Who is away between two dates |

A manager sees only their direct reports, and approving leave grants no access to
pay data.

### HR — `role:hr_admin`

| | |
|---|---|
| `GET/POST /hr/employees` | Directory and registration |
| `GET /hr/employees/{id}` | One employee |
| `PUT /hr/employees/{id}/compensation` | Change salary |
| `POST /hr/employees/{id}/terminate` | Close an employment record |
| `GET/POST /hr/payroll-runs` | List and open runs |
| `POST /hr/payroll-runs/{id}/populate` | Seed a run with everyone employed that period |
| `POST /hr/payroll-runs/{id}/entries` | Enter or overwrite one employee's pay |
| `GET /hr/payroll-runs/{id}/payslips` | The run's register |
| `DELETE /hr/payroll-runs/{id}/payslips/{payslip}` | Remove someone from a draft run |
| `POST /hr/payroll-runs/{id}/finalise` | Issue every payslip — irreversible |
| `POST /hr/payroll-runs/{id}/mark-paid` | Record the transfer |
| `POST /hr/leave/entitlements/grant` | Open a leave year |
| `POST /hr/leave/entitlements/adjust` | Correct a balance (written to the audit log) |

### A payroll run, start to finish

```bash
# 1. Open the run
POST /api/hr/payroll-runs
{"period": "2026-07", "payment_date": "2026-07-28"}

# 2. Pull in everyone employed during July at their contractual salary
POST /api/hr/payroll-runs/{id}/populate

# 3. Adjust the exceptions — overtime, a bonus, a manually entered PCB figure
POST /api/hr/payroll-runs/{id}/entries
{
  "employee_id": "…",
  "earnings":   [{"type": "overtime", "amount": "450.00"}],
  "deductions": [{"type": "pcb", "amount": "212.50"}]
}

# 4. Check the register, then lock it. This is the point of no return:
#    payslips become visible to employees and PDFs start generating.
GET  /api/hr/payroll-runs/{id}/payslips
POST /api/hr/payroll-runs/{id}/finalise

# 5. After the bank transfer clears
POST /api/hr/payroll-runs/{id}/mark-paid
```

Step 3 is idempotent — sending the same entry again overwrites it rather than
duplicating, which is what HR expects when fixing a typo. Statutory lines are
recalculated on every entry and cannot be typed in by hand; PCB is the deliberate
exception.

---

## Configuration

| Setting | Where | Notes |
|---|---|---|
| `PAYROLL_CURRENCY` | `.env` | Default `MYR` |
| `PAYROLL_WORKING_DAYS_PER_MONTH` | `.env` | Divisor for unpaid-leave deductions. Default 22. Check your contracts |
| `STATUTORY_PROFILE` | `.env` | `malaysia` or `none` |
| `PCB_ENGINE` | `.env` | `manual` (default) or `progressive_estimate` |
| `LEAVE_REST_DAYS` | `.env` | e.g. `saturday,sunday`, or just `sunday` for a six-day week |
| `LEAVE_BACKDATE_GRACE_DAYS` | `.env` | How late an employee may still file sick leave themselves |
| `LEAVE_HOLIDAY_REGION` | `.env` | Scopes the holiday calendar to a state |
| `PAYSLIP_DISK` | `.env` | Must stay a private disk |
| Statutory rates | `config/statutory/malaysia.php` | See the warning above |
| Leave types | `database/seeders/LeaveTypeSeeder.php` | Entitlement days vary by service length and contract |

Balance adjustments are written to `storage/logs/audit.log`, retained for two
years — separately from application logs, because these are the entries you need
when a figure is disputed months later.

---

## What is not here

Worth knowing before you plan around it:

- **No EA form or statutory submission files.** CP8D, Borang E and the bank
  giro/EPF/SOCSO upload formats are not generated.
- **No proration for mid-month joiners and leavers.** Someone who joins on the
  15th is currently paid a full month unless HR overrides the earning lines.
- **No overtime rate calculation.** Overtime is entered as an amount, not derived
  from hours and a multiplier.
- **PCB is manual** by default, as described above.
- **No password reset flow or first-login forced reset.** Registration creates a
  random temporary password; wire up Laravel's password broker before rollout.
