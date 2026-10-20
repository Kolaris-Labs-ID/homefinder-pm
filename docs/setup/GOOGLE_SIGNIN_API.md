# Google Sign-In API for HRIS Mobile App

## Overview

The Google Sign-In endpoint allows HRIS mobile app users to authenticate using their Google account. This endpoint verifies the Google ID token, finds the matching employee account, and issues a Sanctum token for API access.

## Endpoint

```
POST /api/auth/google
```

## Request

### Headers

```
Content-Type: application/json
```

### Body Parameters

| Parameter     | Type   | Required | Description                                            |
| ------------- | ------ | -------- | ------------------------------------------------------ |
| `id_token`    | string | Yes      | Google ID token (JWT) obtained from Google Sign-In SDK |
| `device_name` | string | No       | Device identifier (default: "mobile-app")              |

### Example Request

```json
{
    "id_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6IjE4MmU0M...",
    "device_name": "iPhone 15 Pro"
}
```

## Responses

### Success (200 OK)

User authenticated successfully with employee record found.

```json
{
    "message": "Login successful.",
    "token": "1|abc123def456...",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john.doe@example.com",
        "division": "Engineering",
        "is_division_head": false,
        "avatar_url": "https://lh3.googleusercontent.com/..."
    },
    "employee": {
        "id": 1,
        "nik": "EMP001",
        "hire_date": "2024-01-15",
        "employment_status": "permanent",
        "is_active": true
    }
}
```

### No Employee Account (404 Not Found)

User exists but has no employee record, or Google account not found in system.

```json
{
    "message": "No employee account found for this Google account."
}
```

### Invalid Token (401 Unauthorized)

Google ID token is invalid or expired.

```json
{
    "message": "Invalid or expired Google token."
}
```

### Validation Error (422 Unprocessable Entity)

Required fields missing or invalid.

```json
{
    "message": "The id_token field is required.",
    "errors": {
        "id_token": ["The id_token field is required."]
    }
}
```

## How It Works

### 1. Token Verification

The endpoint verifies the Google ID token by calling Google's tokeninfo endpoint:

```
GET https://oauth2.googleapis.com/tokeninfo?id_token={token}
```

This validates:

-   Token signature
-   Token expiration
-   Email verification status

### 2. User Lookup

The endpoint searches for a user by:

1. Google ID (`google_id` field)
2. Email address (if Google ID not found)

### 3. Employee Validation

The endpoint checks if the user has an associated employee record. This is **required** for HRIS mobile app access.

### 4. Google ID Linking

If a user is found by email but doesn't have a `google_id`, the endpoint automatically links the Google account:

-   Sets `google_id` field
-   Updates `avatar_url` with Google profile picture

### 5. Token Generation

A Sanctum token is generated for API authentication with the specified device name.

## Mobile App Integration

### iOS (Swift)

```swift
import GoogleSignIn

// Configure Google Sign-In
GIDSignIn.sharedInstance.configuration = GIDConfiguration(clientID: "YOUR_CLIENT_ID")

// Sign in
GIDSignIn.sharedInstance.signIn(withPresenting: self) { result, error in
    guard let user = result?.user,
          let idToken = user.idToken?.tokenString else {
        return
    }

    // Send to backend
    let parameters = [
        "id_token": idToken,
        "device_name": UIDevice.current.name
    ]

    // Make API call to /api/auth/google
    // ...
}
```

### Android (Kotlin)

```kotlin
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.auth.api.signin.GoogleSignInOptions

// Configure Google Sign-In
val gso = GoogleSignInOptions.Builder(GoogleSignInOptions.DEFAULT_SIGN_IN)
    .requestIdToken("YOUR_CLIENT_ID")
    .requestEmail()
    .build()

val googleSignInClient = GoogleSignIn.getClient(this, gso)

// Sign in
val signInIntent = googleSignInClient.signInIntent
startActivityForResult(signInIntent, RC_SIGN_IN)

// Handle result
override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
    if (requestCode == RC_SIGN_IN) {
        val task = GoogleSignIn.getSignedInAccountFromIntent(data)
        val account = task.getResult(ApiException::class.java)
        val idToken = account.idToken

        // Send to backend
        val params = mapOf(
            "id_token" to idToken,
            "device_name" to Build.MODEL
        )

        // Make API call to /api/auth/google
        // ...
    }
}
```

### React Native

```javascript
import { GoogleSignin } from "@react-native-google-signin/google-signin";

// Configure
GoogleSignin.configure({
    webClientId: "YOUR_CLIENT_ID",
});

// Sign in
const signIn = async () => {
    try {
        await GoogleSignin.hasPlayServices();
        const userInfo = await GoogleSignin.signIn();
        const idToken = userInfo.idToken;

        // Send to backend
        const response = await fetch(
            "https://your-homefinder-domain.example/api/auth/google",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    id_token: idToken,
                    device_name: "React Native App",
                }),
            }
        );

        const data = await response.json();
        // Store token and user data
    } catch (error) {
        console.error(error);
    }
};
```

## Testing

### Manual Testing with cURL

You can test the endpoint using a real Google ID token:

```bash
# 1. Get a Google ID token from Google OAuth Playground
# Visit: https://developers.google.com/oauthplayground/

# 2. Test the endpoint
curl -X POST https://your-homefinder-domain.example/api/auth/google \
  -H "Content-Type: application/json" \
  -d '{
    "id_token": "YOUR_GOOGLE_ID_TOKEN",
    "device_name": "Test Device"
  }'
```

### Automated Tests

Run the test suite:

```bash
php artisan test --filter="Google Sign-In endpoint"
```

Tests cover:

-   ✅ Successful login with employee record
-   ✅ 404 when user has no employee record
-   ✅ 404 when Google account not found
-   ✅ 401 when token is invalid
-   ✅ Google ID linking for existing users
-   ✅ Validation errors
-   ✅ Custom device names

## Security Considerations

### Token Verification

-   ID tokens are verified with Google's official tokeninfo endpoint
-   Expired tokens are rejected
-   Unverified emails are rejected

### Employee Requirement

-   Only users with employee records can access the HRIS mobile app
-   This prevents unauthorized access from non-employee Google accounts

### Token Management

-   Each device gets its own Sanctum token
-   Tokens can be revoked individually via `/api/auth/logout`
-   Old tokens remain valid until explicitly revoked

### HTTPS Required

-   All API calls must use HTTPS in production
-   ID tokens should never be logged or stored

## Troubleshooting

### "Invalid or expired Google token"

**Causes:**

-   Token has expired (tokens expire after 1 hour)
-   Token is malformed
-   Network issues with Google's verification endpoint

**Solution:**

-   Request a new ID token from Google Sign-In SDK
-   Ensure the token is sent immediately after obtaining it

### "No employee account found for this Google account"

**Causes:**

-   User exists but has no employee record
-   Google account email doesn't match any user in the system

**Solution:**

-   Contact HR to create an employee record
-   Verify the email address matches the one in the system
-   Run: `php create-missing-employee-records.php` on the server

### Token verification fails silently

**Causes:**

-   Google's tokeninfo endpoint is unreachable
-   Network firewall blocking outbound HTTPS

**Solution:**

-   Check server logs: `tail -f storage/logs/laravel.log`
-   Verify outbound HTTPS is allowed
-   Test connectivity: `curl https://oauth2.googleapis.com/tokeninfo`

## Configuration

### Google Client ID (Optional)

For stricter security, configure your Google Client ID in `.env`:

```env
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
```

This enables audience verification to ensure tokens are for your app.

### Token Expiration

Sanctum tokens don't expire by default. To configure expiration:

```php
// config/sanctum.php
'expiration' => 60 * 24 * 30, // 30 days
```

## API Flow Diagram

```
Mobile App                    Backend API                    Google
    |                             |                             |
    |-- 1. Google Sign-In -------->|                             |
    |<-- Google ID Token ----------|                             |
    |                             |                             |
    |-- 2. POST /api/auth/google ->|                             |
    |    (id_token)                |                             |
    |                             |-- 3. Verify Token --------->|
    |                             |<-- Token Valid -------------|
    |                             |                             |
    |                             |-- 4. Find User/Employee     |
    |                             |                             |
    |                             |-- 5. Generate Sanctum Token |
    |                             |                             |
    |<-- 6. Return Token + Data --|                             |
    |                             |                             |
    |-- 7. Use Token for API ---->|                             |
    |    (Authorization: Bearer)  |                             |
    |<-- 8. API Response ---------|                             |
```

## Related Endpoints

-   `POST /api/auth/login` - Email/password login
-   `POST /api/auth/logout` - Revoke current token
-   `GET /api/auth/me` - Get authenticated user info
-   `GET /api/hris/profile` - Get employee profile

## Support

For issues or questions:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Run diagnostic: `php diagnose-attendance-api.php`
3. Verify employee records exist
4. Test with Google OAuth Playground
