# Configure Resend Email

Use this workflow when enabling production email delivery for access codes, ownership invitations, password-reset links, and payment receipts.

## Purpose

GradeManager provisions accounts before users complete signup. The platform sends one-time access codes when possible, while keeping those codes visible to the responsible school administrator, faculty head, or HOD in case they need to communicate one directly. Payment receipts are generated and stored before their email is queued, so an email failure never prevents dashboard download.

## Resend Account

1. Create a Resend team for GradeManager.
2. Create a sending-only API key for the production application.
3. Store the key only in deployment secrets.
4. Review the account's email quota and request rate before importing large staff or student lists.

## Sending Domain

Use a dedicated transactional subdomain such as `mail.example.com` rather than the root application domain.

1. Add the sending subdomain in the Resend dashboard.
2. Add the SPF and DKIM records supplied by Resend to DNS.
3. Add a DMARC record appropriate for the platform's domain policy.
4. Wait until Resend reports the domain as verified.
5. Use an address on that verified domain, such as `notifications@mail.example.com`.

GradeManager uses one verified platform sender. Tenant school names belong in subjects and message content; do not use an unverified school domain as the envelope sender.

## Environment Variables

Set these values in production:

```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=notifications@mail.example.com
MAIL_FROM_NAME="GradeManager"

RESEND_API_KEY=re_xxxxxxxxx
RESEND_WEBHOOK_SECRET=whsec_xxxxxxxxx
RESEND_DOMAIN=
RESEND_PATH=resend
RESEND_WEBHOOK_TOLERANCE=300
```

Local development should keep `MAIL_MAILER=log` and leave `RESEND_API_KEY` empty. The application records the email as skipped, while access codes remain available in the dashboard.

## Webhook

Register this public HTTPS endpoint in the Resend dashboard:

```text
https://your-production-domain.example/resend/webhook
```

Subscribe only to these events:

- `email.sent`
- `email.delivered`
- `email.delivery_delayed`
- `email.failed`
- `email.bounced`
- `email.suppressed`
- `email.complained`

Copy the webhook signing secret into `RESEND_WEBHOOK_SECRET`. The official Resend Laravel middleware verifies every webhook signature before GradeManager updates a delivery record. Open and click tracking are not needed for these transactional messages.

## Queue Worker

Email is sent after the surrounding database transaction commits. Run a worker that includes the `emails` queue:

```shell
php artisan queue:work --queue=emails,default
```

The application limits Resend work to four requests per second to remain below the provider's default team limit. Automatic retries reuse the same Resend idempotency key. A deliberate user resend creates a new send cycle and a new key.

## Payment Receipts

Receipt generation and email delivery are separate jobs:

1. Paystack confirms the exact amount paid.
2. GradeManager generates and stores the PDF receipt on the private receipt disk.
3. The email job attaches that stored PDF through Laravel's storage attachment API.
4. The email also contains a signed seven-day fallback download link.
5. The receipt remains downloadable from GradeManager after the email link expires.

Do not make the PDF public to simplify email delivery. Resend receives the attachment bytes from the queue worker, while the permanent stored copy remains private.

## Production Cutover

1. Configure and verify Resend before deploying the provider change.
2. Pause new account imports briefly.
3. Allow queued jobs from the previous email provider to finish.
4. Deploy the Resend release and restart queue workers.
5. Confirm `php artisan route:list --path=resend` shows the webhook route.
6. Resume imports after a test delivery succeeds.

Historical `email_deliveries` rows retain their original mailer value. New deliveries use `resend`.

## Verification

1. Provision one test staff account and confirm the access-code email arrives.
2. Request a password reset and confirm the one-time link opens on the correct school domain.
3. Send and accept a school-admin or faculty-head ownership invitation.
4. Complete one test due payment and confirm the PDF receipt is attached.
5. Confirm the Resend webhook changes the delivery from sent to delivered.
6. Test a rejected address and confirm the dashboard does not continuously retry a bounced or suppressed message.
7. Confirm receipt download still works when email delivery fails.

## Security

- Never commit the API key or webhook secret.
- Use a sending-only API key and rotate it after any suspected exposure.
- Verify webhook signatures and keep the tolerance narrow.
- Do not log access codes, password-reset links, receipt bytes, or API credentials.
- Keep receipt storage private.
- Monitor bounce and complaint rates in Resend and correct invalid addresses instead of repeatedly retrying them.
