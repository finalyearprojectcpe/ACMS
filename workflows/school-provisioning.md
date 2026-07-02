# Provision a School

Use the reusable school provisioning command when a new school joins the platform. The command creates or updates the school record, creates or updates the first School Admin account, and can seed local-development academic sessions.

## Interactive Setup

For real setup, prefer the interactive command so the admin password is not stored in shell history.

```shell
php artisan school:provision
```

## Non-Interactive Setup

Use this form for local development, repeatable setup, or scripted deployment.

```shell
php artisan school:provision \
    --name="Federal University of Technology Owerri" \
    --abbreviation=FUTO \
    --admin-name="Okoro Chinyere" \
    --admin-email=schooladmin@futo.edu \
    --admin-password=password \
    --seed-sessions
```

The school slug defaults to the full school name with `Str::slug()`. For example, `Federal University of Technology Owerri` becomes `federal-university-of-technology-owerri`.

Pass `--slug=...` only when the school needs a custom domain slug.

```shell
php artisan school:provision \
    --name="Federal University of Technology Owerri" \
    --abbreviation=FUTO \
    --slug=futo \
    --admin-name="Okoro Chinyere" \
    --admin-email=schooladmin@futo.edu \
    --admin-password=password
```

## Idempotency

The workflow is idempotent. Running the same command again updates the same school and School Admin instead of creating duplicates.

The demo `SchoolAdminSeeder` uses the same provisioning action, so local seed data follows the production workflow.

## Setup Chain

After the School Admin exists, platform setup flows downward:

1. School Admin creates faculties, departments, academic sessions, and faculty-head accounts.
2. Faculty Heads create or import HOD accounts for departments in their faculty.
3. HODs create or import staff accounts for their departments.
4. Course Advisors create or import student registration numbers and access codes for their assigned set.

## Verification

Confirm the school and admin were created:

```shell
php artisan tinker --execute 'App\Models\School::query()->where("abbreviation", "FUTO")->first()?->schoolAdmins()->count();'
```

Confirm the admin can sign in through the normal login page.
