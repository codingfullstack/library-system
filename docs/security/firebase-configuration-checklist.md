# Firebase Configuration Security Checklist

`google-services.json` in the Android app is client configuration. Its API key identifies the Firebase project to client SDKs; by itself it is not a server secret. Do not delete it only because it contains a client API key.

## Repository Audit

Checked in this repository:

- Android client config: `LibraryApp/app/google-services.json` is tracked.
- Android package name in client config: `com.example.libraryapp`.
- No tracked Firebase service account JSON was found in root git or Android git.
- No tracked FCM legacy server key, `.pem`, `.p12`, or backend Firebase credential was found in git.
- A local untracked file exists at `storage/app/private/firebase-service-account.json`; keep it out of git and provide production credentials through secure secret storage.

## Client Config vs Server Secrets

Client config:
- `google-services.json`
- Firebase project number, app id, package name, client API key
- Safe to ship in Android builds when Firebase Console restrictions and rules are correct

Server secrets:
- Service account JSON
- FCM legacy server key
- OAuth private keys
- Backend credentials for Firebase Admin APIs
- Must never be committed to Android code or public repository history

## Manual Firebase Console Checks

The Firebase Console state was not accessible from this workspace. Verify manually:

- Android app restriction is configured for package `com.example.libraryapp`.
- SHA-1 and SHA-256 fingerprints match every release signing certificate.
- Debug fingerprints are separated from release fingerprints.
- API key is restricted to required Android apps and allowed APIs.
- Only required APIs are enabled.
- App Check is enabled or rollout plan is documented.
- Firestore rules deny unauthenticated and cross-user access unless explicitly required.
- Storage rules deny unauthenticated and cross-user access unless explicitly required.
- Realtime Database rules deny unauthenticated and cross-user access unless explicitly required.
- FCM credentials are stored only in backend secret storage.
- Dev, test, and production projects are separated, or shared-project risk is formally accepted.
- Crash reporting breadcrumbs do not include access tokens, emails, phone numbers, reservation payloads, loan payloads, or notification metadata.

## Environment Separation Risk

Current Android config points at project `bibliotekos-sistema`. The repo does not show separate `debug` and `release` `google-services.json` files. If debug and production builds use the same Firebase project, test devices, debug FCM tokens, and production users may share notification infrastructure.

Recommended follow-up:

1. Add explicit Firebase projects for dev/test/prod, or document why one project is acceptable.
2. Put variant-specific `google-services.json` under `app/src/debug` and `app/src/release` when environments differ.
3. Keep backend service account credentials outside the repo and inject them via environment-specific secret management.

## Remaining Risk

This audit cannot verify Firebase Console restrictions, App Check state, or database/storage rules from local files. Treat those checks as mandatory before production release.
