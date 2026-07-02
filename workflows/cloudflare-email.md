# Configure Cloudflare Email

Use this workflow when enabling production email delivery for access codes, ownership invites, and account setup messages.

## Purpose

GradeManager provisions accounts before users complete signup. The platform generates access codes and sends them by email when possible. Access codes remain visible to the responsible admin, faculty head, or HOD so they can still communicate a code manually if email delivery fails.

## Cloudflare Setup

1. Create or select the Cloudflare account that will send platform email.
2. Create the email-sending API token or worker endpoint used by the platform.
3. Restrict the token to only the resources needed for sending GradeManager emails.
4. Store the token only in production secrets or the deployment environment.

## Environment Variables

Set these variables in production:

```env
CLOUDFLARE_EMAIL_ENDPOINT=
CLOUDFLARE_EMAIL_TOKEN=
```

Local development can leave both values empty. Email delivery records can still be created, and access codes remain visible in the UI.

## Application Flow

The platform creates an `email_deliveries` record when an email needs to be sent. The Cloudflare email service then sends the access code or invite details.

If sending fails, the person responsible for onboarding can still copy the access code from the dashboard and share it directly.

## Verification

After configuring production secrets:

1. Provision or import one test staff account.
2. Confirm an `email_deliveries` row is created for the user.
3. Confirm the recipient receives the email.
4. Confirm the access code shown in the dashboard matches the email.
5. Confirm a failed delivery still leaves the access code visible in the UI.

Use read-only inspection when checking delivery records:

```shell
php artisan tinker --execute 'App\Models\EmailDelivery::query()->latest()->first()?->only(["recipient_email", "subject", "status"]);'
```

## Security Notes

- Do not commit Cloudflare tokens.
- Use environment-level secrets in production.
- Scope tokens narrowly.
- Rotate the token if it is ever exposed.
- Do not hide access codes in the dashboard; they are one-time codes and operational staff may need to communicate them orally.
