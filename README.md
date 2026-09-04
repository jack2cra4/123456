# SDK License & Permission Management System

A complete self-hosted SDK key licensing system with web admin panel, PHP verification API, and Android client integration.

## Structure

```
Afroz/
├── web/
│   ├── index.php          # Admin dashboard (dark theme UI)
│   ├── api.php            # Key management REST API
│   ├── sdk_verify.php     # SDK verification endpoint
│   ├── database.json      # Keys database (JSON)
│   └── style.css          # Dashboard styles
├── android/
│   └── src/main/
│       ├── java/com/sdk/
│       │   ├── SdkManager.java       # Main SDK helper class
│       │   └── SdkDemoActivity.java  # Demo integration activity
│       ├── cpp/
│       │   ├── native-auth.cpp       # JNI stub with XOR obfuscation
│       │   ├── native-auth.h         # JNI header
│       │   └── CMakeLists.txt        # Native build config
│       └── AndroidManifest.xml
├── README.md
└── .gitignore
```

## Web Panel

Open `web/index.php` in a browser to access the admin dashboard. Generate keys with custom durations (7 days, 30 days, lifetime) and manage device limits.

### API

- `POST api.php?action=create` - Generate new SDK key
- `GET api.php?action=list` - List all keys
- `POST api.php?action=revoke` - Revoke a key
- `POST api.php?action=delete` - Permanently delete a key

### Verification

```bash
curl -X POST https://your-domain/web/sdk_verify.php \
  -H "Content-Type: application/json" \
  -d '{"device_id":"abc123","sdk_key":"SDK-XXXXXXXXXXXXXXXX"}'
```

Success: `{"status":true,"expiry":"2026-12-31","token":"<hex>"}`
Failure: `{"status":false,"reason":"Invalid or Expired SDK Key"}`

## Android Client

### Usage

```java
// Initialize
SdkManager sdk = SdkManager.init(context, "https://your-domain/web/sdk_verify.php");

// Verify a key
sdk.verify("SDK-XXXXXXXXXXXXXXXX", (authorized, token, expiry, reason) -> {
    if (authorized) {
        // Token stored automatically in SharedPreferences
    }
});

// Check stored auth
if (sdk.hasValidStoredToken()) {
    String token = sdk.getStoredToken();
}
```

### Native Key Obfuscation

The JNI layer stores the SDK key using XOR encryption in memory. Call `loadNativeKey()` and `getNativeSdkKey()` to retrieve at runtime.
