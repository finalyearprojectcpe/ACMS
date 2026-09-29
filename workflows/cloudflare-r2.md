# Configure Cloudflare R2

Use this workflow when enabling production profile-photo and private payment-receipt storage. Local development stays on local disks.

## Purpose

Profile photos are stored as files. The database stores only the relative path in `users.profile_photo_path`.

Local development uses:

```env
PROFILE_PHOTO_DISK=public
```

Production should use:

```env
PROFILE_PHOTO_DISK=r2
DUE_RECEIPT_DISK=r2-private
```

## Cloudflare Setup

1. Create an R2 bucket for public profile photos, for example `grademanager-production`.
2. Create a separate private R2 bucket for payment receipts, for example `grademanager-private`.
3. Attach a public bucket domain or a custom domain for serving profile photos.
4. Create an R2 API token scoped only to the two required buckets with object read/write access.
5. Copy the Access Key ID, Secret Access Key, bucket names, endpoint, and public URL into production secrets.

## Environment Variables

Set these variables in production:

```env
PROFILE_PHOTO_DISK=r2
DUE_RECEIPT_DISK=r2-private
CLOUDFLARE_R2_ACCESS_KEY_ID=
CLOUDFLARE_R2_SECRET_ACCESS_KEY=
CLOUDFLARE_R2_BUCKET=
CLOUDFLARE_R2_PRIVATE_BUCKET=
CLOUDFLARE_R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
CLOUDFLARE_R2_URL=https://<public-assets-domain>
CLOUDFLARE_R2_REGION=auto
CLOUDFLARE_R2_USE_PATH_STYLE_ENDPOINT=true
```

`CLOUDFLARE_R2_URL` should be the public bucket URL or custom domain used by browsers to load profile photos.

## Application Behavior

Profile photo upload uses the configured profile-photo disk:

```php
User::profilePhotoDisk()
```

The path is stored in:

```txt
users.profile_photo_path
```

The public URL is generated from the configured disk:

```php
$user->profilePhotoUrl()
```

Payment receipt PDFs are stored on `DUE_RECEIPT_DISK`. They are private: authorized users download them through GradeManager routes instead of receiving public R2 object URLs.

Receipts use paths in the form `receipts/{adviser-profile-id}/{year}/{receipt-number}.pdf`. A permanent signed verification URL is encoded in each QR code, while email fallback downloads use expiring signed links.

## Local Development

Use the default local setting:

```env
PROFILE_PHOTO_DISK=public
DUE_RECEIPT_DISK=local
```

If the local `/storage` URL does not serve images, create the public storage symlink:

```shell
php artisan storage:link
```

## Verification

After setting production secrets:

1. Sign in as a user with access to Settings.
2. Upload a profile photo.
3. Confirm `users.profile_photo_path` contains a `profile-photos/...` path.
4. Confirm the browser loads the image from the R2 public URL.
5. Replace the photo and confirm the previous object is deleted.
6. Complete a test due payment, run the queue worker, and confirm the PDF is stored in the private bucket.
7. Confirm the dashboard can download the receipt while the underlying R2 object remains private.

Run the profile-photo tests locally:

```shell
php artisan test --compact tests/Feature/DueReceiptTest.php
```

## Security Notes

- Do not commit R2 credentials.
- Scope the token to only the public profile-photo bucket and private receipt bucket.
- Keep profile photos in a public bucket only because they are displayed in dashboards.
- Keep receipts in the private bucket; do not add a public URL for it.
- Do not store image blobs in the database.
