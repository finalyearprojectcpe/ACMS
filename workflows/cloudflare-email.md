# Configure Cloudflare Email

Use this workflow when enabling production email delivery for access codes, ownership invites, password-reset links, and payment receipts.

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

The platform creates an `email_deliveries` record when an email needs to be sent, then queues a delivery job after the surrounding database transaction commits. Each request carries a stable idempotency key, so a provider retry does not create a second message.

Run a queue worker in production:

```shell
php artisan queue:work --tries=5
```

The email may contain an access code, an ownership invite, a one-time password-reset link, or a payment receipt. Password-reset links expire after the interval in `config/auth.php` and are scoped to the school address that issued them.

Payment receipt delivery starts only after the PDF has been generated and stored. The Cloudflare endpoint receives attachments in this JSON shape:

```json
{
    "attachments": [
        {
            "filename": "receipt-GM-20260711-000001.pdf",
            "content_type": "application/pdf",
            "content_base64": "JVBERi0x..."
        }
    ]
}
```

The email worker must decode `content_base64` and attach the bytes using the supplied filename and content type. Receipt emails also contain a seven-day signed download link. A failed email does not make the receipt unavailable in GradeManager; users can download it from the dashboard and retry email delivery separately.

If delivery is not configured or fails, the person responsible for onboarding can still copy the access code from the dashboard and share it directly. Password resets remain private: the user should request another link rather than receive a reset token from staff.

## Verification

After configuring production secrets:

1. Provision or import one test staff account.
2. Confirm an `email_deliveries` row is created for the user.
3. Confirm the queue worker processes the delivery and the recipient receives the email.
4. Confirm the access code shown in the dashboard matches the email.
5. Confirm a failed delivery still leaves the access code visible in the UI.
6. Complete one test due payment and confirm the generated PDF arrives as an attachment.
7. Confirm the receipt fallback link downloads the PDF and expires after seven days.

Use read-only inspection when checking delivery records:

```shell
php artisan tinker --execute 'App\Models\EmailDelivery::query()->latest()->first()?->only(["recipient_email", "subject", "status"]);'
```

## Security Notes

- Do not commit Cloudflare tokens.
- Use environment-level secrets in production.
- Scope tokens narrowly.
- Rotate the token if it is ever exposed.
- Keep the email endpoint and token in deployment secrets only.
- Do not hide access codes in the dashboard; they are one-time codes and operational staff may need to communicate them orally.
