# Configure Cloudflare R2

Use this workflow when enabling production image storage. Local development stays on the local public disk.

## Purpose

Profile photos are stored as files. The database stores only the relative path in `users.profile_photo_path`.

Local development uses:

```env
PROFILE_PHOTO_DISK=public
```

Production should use:

```env
PROFILE_PHOTO_DISK=r2
```

## Cloudflare Setup

1. Create an R2 bucket for public profile photos, for example `grademanager-production`.
2. Attach a public bucket domain or a custom domain for serving profile photos.
3. Create an R2 API token scoped to the bucket with object read/write access.
4. Copy the Access Key ID, Secret Access Key, bucket name, endpoint, and public URL into production secrets.

## Environment Variables

Set these variables in production:

```env
PROFILE_PHOTO_DISK=r2
CLOUDFLARE_R2_ACCESS_KEY_ID=
CLOUDFLARE_R2_SECRET_ACCESS_KEY=
CLOUDFLARE_R2_BUCKET=
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

## Local Development

Use the default local setting:

```env
PROFILE_PHOTO_DISK=public
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

Run the profile-photo tests locally:

```shell
php artisan test --compact tests/Feature/FacultyHeadSettingsTest.php
```

## Security Notes

- Do not commit R2 credentials.
- Use a token scoped only to the profile-photo bucket.
- Keep profile photos in a public bucket only because they are displayed in dashboards.
- Use a separate private bucket/disk later for reports, transcripts, or exported records.
- Do not store image blobs in the database.
