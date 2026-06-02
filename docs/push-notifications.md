# Push Notifications

This project uses Firebase Cloud Messaging HTTP v1 for Android push notifications.

## Backend Commands

```bash
composer require google/auth
php artisan migrate
php artisan queue:work
php artisan config:clear
```

Useful test commands:

```bash
php artisan test tests\Feature\Api\DeviceTokenApiTest.php
php artisan test tests\Feature\FcmServiceTest.php
php artisan test tests\Feature\FcmNotificationChannelTest.php
php artisan test tests\Feature\Api\NotificationApiTest.php
```

## Android Commands

```bash
cd LibraryApp
.\gradlew.bat testDebugUnitTest
.\gradlew.bat assembleDebug
```

## Laravel Files

Device tokens:

- `database/migrations/2026_05_31_220000_create_device_tokens_table.php`
- `app/Models/DeviceToken.php`
- `app/Http/Requests/StoreDeviceTokenRequest.php`
- `app/Http/Requests/DeleteDeviceTokenRequest.php`
- `app/Http/Controllers/Api/DeviceTokenController.php`
- `tests/Feature/Api/DeviceTokenApiTest.php`

FCM:

- `app/Services/FcmService.php`
- `app/Notifications/Channels/FcmChannel.php`
- `tests/Feature/FcmServiceTest.php`
- `tests/Feature/FcmNotificationChannelTest.php`

Notifications API:

- `app/Http/Controllers/Api/NotificationController.php`
- `routes/api.php`
- `tests/Feature/Api/NotificationApiTest.php`

Updated existing files:

- `app/Models/User.php`
- `app/Notifications/Concerns/BuildsLibraryNotificationPayload.php`
- `config/services.php`
- `.env.example`
- `composer.json`
- `composer.lock`

## Laravel Migration

`device_tokens`:

- `id`
- `user_id`
- `token`
- `device_name`
- `created_at`
- `updated_at`

The `token` column is unique. One user can have many device tokens.

## Laravel API

Device token endpoints:

```http
POST /api/auth/device-token
DELETE /api/auth/device-token
```

Request:

```json
{
  "token": "fcm-token",
  "device_name": "Google Pixel 8"
}
```

Notifications endpoints:

```http
GET  /api/auth/notifications
GET  /api/auth/notifications/unread-count
POST /api/auth/notifications/{notification}/read
POST /api/auth/notifications/read-all
```

Legacy compatibility endpoints:

```http
PATCH /api/auth/notifications/{notification}/read
POST  /api/auth/notifications/mark-all-read
```

## Firebase Configuration

`.env`:

```env
FIREBASE_PROJECT_ID=bibliotekos-sistema
FIREBASE_CREDENTIALS=storage/app/private/firebase-service-account.json
```

Store the Firebase service account JSON at:

```text
storage/app/private/firebase-service-account.json
```

The service account needs permission to send Firebase Cloud Messaging messages.

## FCM Payload

The backend sends both notification and data payloads.

Required data fields:

```json
{
  "title": "Title",
  "body": "Body",
  "type": "reservation_ready",
  "notification_id": "uuid",
  "related_type": "App\\Models\\Reservation",
  "related_id": "123",
  "deep_link": "libraryapp://notification/uuid"
}
```

Deep link navigation is not implemented yet. The payload and Android intent are ready for it.

## Laravel Notification Channels

Existing channels are preserved:

- `database`
- `broadcast`

Added channel:

- `App\Notifications\Channels\FcmChannel`

The affected notifications are:

- `ReservationReadyNotification`
- `BookDueSoonNotification`
- `BookOverdueNotification`
- `LibraryNotification`

## Android Files

FCM:

- `LibraryApp/app/src/main/java/com/example/libraryapp/notifications/LibraryFirebaseMessagingService.kt`
- `LibraryApp/app/src/main/java/com/example/libraryapp/notifications/LibraryNotificationHelper.kt`
- `LibraryApp/app/src/main/java/com/example/libraryapp/notifications/FcmTokenRegistrar.kt`

Data/API:

- `LibraryApp/app/src/main/java/com/example/libraryapp/data/remote/dto/DeviceTokenRequest.kt`
- `LibraryApp/app/src/main/java/com/example/libraryapp/data/repository/DeviceTokenRepository.kt`
- `LibraryApp/app/src/main/java/com/example/libraryapp/data/remote/ApiService.kt`
- `LibraryApp/app/src/main/java/com/example/libraryapp/data/local/TokenManager.kt`

UI:

- `LibraryApp/app/src/main/java/com/example/libraryapp/ui/notifications/NotificationsViewModel.kt`
- `LibraryApp/app/src/main/java/com/example/libraryapp/ui/notifications/NotificationsScreen.kt`

App wiring:

- `LibraryApp/app/src/main/java/com/example/libraryapp/MainActivity.kt`
- `LibraryApp/app/src/main/AndroidManifest.xml`
- `LibraryApp/app/build.gradle.kts`
- `LibraryApp/gradle/libs.versions.toml`

## Retrofit Endpoints

```kotlin
@POST("auth/device-token")
suspend fun storeDeviceToken(
    @Body request: DeviceTokenRequest
): MessageResponse

@HTTP(method = "DELETE", path = "auth/device-token", hasBody = true)
suspend fun deleteDeviceToken(
    @Body request: DeviceTokenRequest
): MessageResponse

@GET("auth/notifications")
suspend fun getNotifications(): NotificationsResponseDto

@GET("auth/notifications/unread-count")
suspend fun getUnreadNotificationCount(): UnreadCountResponse

@POST("auth/notifications/read-all")
suspend fun markAllNotificationsRead(): MessageResponse

@POST("auth/notifications/{id}/read")
suspend fun markNotificationRead(
    @Path("id") id: String
): MessageResponse
```

The current implementation uses `getNotifications`, `markAllNotificationsRead`, and `markNotificationRead`.

## Android Manifest

Permissions:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
```

FCM service:

```xml
<service
    android:name=".notifications.LibraryFirebaseMessagingService"
    android:exported="false">
    <intent-filter>
        <action android:name="com.google.firebase.MESSAGING_EVENT" />
    </intent-filter>
</service>
```

Default notification channel:

```xml
<meta-data
    android:name="com.google.firebase.messaging.default_notification_channel_id"
    android:value="library_notifications" />
```

Deep link:

```xml
<intent-filter>
    <action android:name="android.intent.action.VIEW" />
    <category android:name="android.intent.category.DEFAULT" />
    <category android:name="android.intent.category.BROWSABLE" />
    <data
        android:host="notification"
        android:scheme="libraryapp" />
</intent-filter>
```

## Firebase Android App

The Android app must be registered in Firebase with:

```text
com.example.libraryapp
```

The project was checked against:

- namespace: `com.example.libraryapp`
- applicationId: `com.example.libraryapp`
- `google-services.json` package name: `com.example.libraryapp`

## Manual Testing

1. Run migrations.

```bash
php artisan migrate
```

2. Configure Firebase service account.

3. Start the queue worker.

```bash
php artisan queue:work
```

4. Install the debug Android app.

5. Log in on Android.

6. Check the backend table.

```sql
select * from device_tokens;
```

7. Send a test push.

```bash
php artisan tinker
```

```php
$user = App\Models\User::first();

app(App\Services\FcmService::class)->sendToUser($user, 'Testas', 'FCM veikia', [
    'type' => 'test',
    'notification_id' => 'test-id',
    'related_type' => '',
    'related_id' => '',
    'deep_link' => 'libraryapp://notification/test-id',
]);
```

8. Verify:

- foreground app shows an Android system notification;
- background app shows an Android system notification;
- closed app receives Firebase notification;
- notification list refreshes with pull-to-refresh;
- unread badge is visible when unread items exist;
- tapping an unread notification row marks it as read;
- read-all marks all notifications as read.
