package com.sdk;

import android.content.Context;
import android.content.SharedPreferences;
import android.os.AsyncTask;
import android.provider.Settings;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.Executor;
import java.util.concurrent.Executors;

public class SdkManager {

    private static volatile SdkManager instance;
    private final Context context;
    private final String verifyUrl;
    private final SharedPreferences prefs;
    private final Executor executor = Executors.newSingleThreadExecutor();

    private static final String PREFS_NAME = "sdk_auth";
    private static final String KEY_AUTH_TOKEN = "auth_token";
    private static final String KEY_EXPIRY = "expiry";
    private static final String KEY_SDK_KEY = "sdk_key";

    public interface AuthCallback {
        void onResult(boolean authorized, String token, String expiry, String reason);
    }

    private SdkManager(Context context, String verifyUrl) {
        this.context = context.getApplicationContext();
        this.verifyUrl = verifyUrl;
        this.prefs = this.context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
    }

    public static SdkManager init(Context context, String verifyUrl) {
        if (instance == null) {
            synchronized (SdkManager.class) {
                if (instance == null) {
                    instance = new SdkManager(context, verifyUrl);
                }
            }
        }
        return instance;
    }

    public static SdkManager getInstance() {
        if (instance == null) {
            throw new IllegalStateException("SdkManager not initialized. Call init() first.");
        }
        return instance;
    }

    public String getDeviceId() {
        return Settings.Secure.getString(context.getContentResolver(), Settings.Secure.ANDROID_ID);
    }

    public String getStoredToken() {
        return prefs.getString(KEY_AUTH_TOKEN, null);
    }

    public String getStoredExpiry() {
        return prefs.getString(KEY_EXPIRY, null);
    }

    public boolean hasValidStoredToken() {
        String token = getStoredToken();
        String expiry = getStoredExpiry();
        if (token == null || expiry == null) return false;
        if ("lifetime".equals(expiry)) return true;
        try {
            java.text.SimpleDateFormat sdf = new java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss", java.util.Locale.US);
            java.util.Date expiryDate = sdf.parse(expiry);
            return expiryDate != null && expiryDate.after(new java.util.Date());
        } catch (Exception e) {
            return false;
        }
    }

    public void verify(String sdkKey, AuthCallback callback) {
        executor.execute(() -> {
            try {
                JSONObject body = new JSONObject();
                body.put("device_id", getDeviceId());
                body.put("sdk_key", sdkKey);

                HttpURLConnection conn = (HttpURLConnection) new URL(verifyUrl).openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setConnectTimeout(15000);
                conn.setReadTimeout(15000);
                conn.setDoOutput(true);

                byte[] payload = body.toString().getBytes(StandardCharsets.UTF_8);
                OutputStream os = conn.getOutputStream();
                os.write(payload);
                os.flush();
                os.close();

                int responseCode = conn.getResponseCode();
                BufferedReader reader;
                if (responseCode >= 200 && responseCode < 300) {
                    reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                } else {
                    reader = new BufferedReader(new InputStreamReader(conn.getErrorStream()));
                }

                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = reader.readLine()) != null) {
                    sb.append(line);
                }
                reader.close();
                conn.disconnect();

                JSONObject response = new JSONObject(sb.toString());
                boolean status = response.optBoolean("status", false);

                if (status) {
                    String token = response.optString("token", "");
                    String expiry = response.optString("expiry", "lifetime");

                    prefs.edit()
                            .putString(KEY_AUTH_TOKEN, token)
                            .putString(KEY_EXPIRY, expiry)
                            .putString(KEY_SDK_KEY, sdkKey)
                            .apply();

                    callback.onResult(true, token, expiry, null);
                } else {
                    String reason = response.optString("reason", "Verification failed");
                    prefs.edit().clear().apply();
                    callback.onResult(false, null, null, reason);
                }

            } catch (Exception e) {
                callback.onResult(false, null, null, "Network error: " + e.getMessage());
            }
        });
    }

    public void logout() {
        prefs.edit().clear().apply();
    }

    public void loadNativeKey() {
        System.loadLibrary("sdk_auth");
    }

    public native String getNativeSdkKey();
}
