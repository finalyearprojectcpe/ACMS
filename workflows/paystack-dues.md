# Configure Paystack Dues

Use this workflow when enabling online dues payment for staff advisers.

## Purpose

Course advisers create dues for the student sets they manage. Each adviser connects the bank account that should receive those dues from the dashboard. GradeManager keeps one central Paystack integration and creates Paystack subaccounts for adviser payout accounts, so staff do not manage API keys.

## Paystack Setup

1. Create or select the Paystack business account used by the school platform.
2. Copy the secret key and public key from the Paystack dashboard.
3. Confirm split payments and subaccounts are available for the Paystack account.
4. Add the webhook URL in Paystack:

```txt
https://<school-domain>/paystack/webhook
```

5. Keep the Paystack dashboard available to staff advisers through the Paystack Settings page.

## Environment Variables

Set these variables in production:

```env
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_DASHBOARD_URL=https://dashboard.paystack.com
PAYSTACK_SECRET_KEY=
PAYSTACK_PUBLIC_KEY=
PAYSTACK_PAYMENT_CHANNELS=card,bank,ussd,bank_transfer
PAYSTACK_SPLIT_FEE_BEARER=subaccount
```

`PAYSTACK_SPLIT_FEE_BEARER=subaccount` means Paystack fees are deducted from the adviser payout side instead of the central platform account. Use `account` only when the school deliberately wants the central Paystack account to carry payment fees.

## Adviser Workflow

1. The adviser opens `Paystack Settings`.
2. The adviser enters the bank, account number, and a clear label.
3. GradeManager loads supported Nigerian banks from Paystack and caches the list.
4. GradeManager resolves the bank account with Paystack.
5. GradeManager creates one Paystack subaccount for the adviser.
6. If the adviser changes bank details later, GradeManager updates that same Paystack subaccount and current local payout record.
7. Every unpaid and future due managed by the adviser uses that current payout account.

If a due is still a draft, it can exist without a payout account. Publishing requires an active Paystack payout account so students never pay into an unclear settlement destination.

Changing the payout account replaces open checkout attempts so the next payment uses the new details. Completed payments keep a payout snapshot in their payment record, so receipts and historical accounting stay unchanged.

Do not hardcode bank names or bank codes in product code. The Paystack bank list is provider-owned data and should come from Paystack's List Banks endpoint.

## Payment Flow

1. Student clicks `Pay now`.
2. GradeManager resolves the adviser's current payout account and creates a `due_payments` record with a payout snapshot.
3. GradeManager initializes a Paystack transaction with:
    - student email
    - amount in kobo
    - unique reference
    - Paystack subaccount code
    - payment metadata for audit
4. Paystack redirects the student to checkout.
5. Paystack calls the webhook after payment, refund, or dispute events.
6. GradeManager verifies the signature and transaction reference before it marks an assignment as paid.
7. A valid refund or dispute reverses the payment and restores the due to pending when no earlier successful payment remains.
8. GradeManager queues a PDF receipt from the stored payment record and emails it after generation.

The PDF records the exact amount Paystack confirmed and uses Paystack's transaction ID as its verification identity. Its QR code opens a permanent signed verification page. Receipt generation and email delivery have separate retry states, so an email failure never blocks dashboard download.

Published due amounts remain editable. Changing an amount updates pending and waived student assignments and closes their open checkout links. Completed payments and receipts keep their original verified amounts. If an older checkout completes after an amount change, GradeManager records the amount attached to that verified Paystack transaction instead of rewriting payment history.

Course advisers can waive an individual student due with a reason, restore a waived due when needed, or archive a published due. Archiving stops new checkouts without deleting the payment history.

## Local Development

Leave Paystack keys empty unless you are testing against Paystack test mode. Demo seed data creates disabled payout placeholders and paid records so the dashboard can be inspected without sending fake subaccount codes to Paystack.

When testing Paystack locally, use Paystack test keys and expose the local webhook through a secure tunnel.

## Verification

After configuring production secrets:

1. Sign in as a course adviser.
2. Connect the adviser's payout account.
3. Confirm the account shows as active in Paystack Settings.
4. Create and publish a due for an assigned set.
5. Sign in as a student from the set and start payment.
6. Confirm Paystack checkout opens.
7. Complete a test payment.
8. Confirm the due is marked paid and the receipt can be downloaded.
9. Confirm Paystack shows the subaccount on the transaction settlement details.
10. Test a refund or dispute in the Paystack test environment and confirm the payment and assignment are restored correctly.

Run the focused dues tests locally:

```shell
php artisan test --compact --filter=Paystack
php artisan test --compact --filter=due
```

## Security Notes

- Do not ask staff advisers for Paystack API keys.
- Do not commit Paystack keys.
- Store only the adviser bank account number encrypted in the database.
- Show the connected bank details only to the adviser who owns the payout account.
- Verify bank account details with Paystack before creating the subaccount.
- Verify webhook signatures before processing webhook payloads.
- Store only the sanitized webhook fields required for audit and idempotency; do not retain card, customer, or authorization details that GradeManager does not need.
