package com.sdk;

import android.app.Activity;
import android.os.Bundle;
import android.widget.TextView;
import android.widget.Toast;

public class SdkDemoActivity extends Activity {

    private static final String VERIFY_URL = "https://your-domain.com/web/sdk_verify.php";
    private static final String SDK_KEY = "SDK-XXXXXXXXXXXXXXXX";

    private TextView statusText;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        statusText = new TextView(this);
        statusText.setPadding(48, 48, 48, 48);
        statusText.setTextSize(16);
        setContentView(statusText);

        statusText.setText("Initializing SDK Manager...");

        SdkManager sdkManager = SdkManager.init(this, VERIFY_URL);
        sdkManager.loadNativeKey();

        if (sdkManager.hasValidStoredToken()) {
            statusText.setText("Already authorized.\nToken: " + sdkManager.getStoredToken()
                    + "\nExpiry: " + sdkManager.getStoredExpiry());
            return;
        }

        statusText.setText("Verifying SDK key...");
        sdkManager.verify(SDK_KEY, new SdkManager.AuthCallback() {
            @Override
            public void onResult(boolean authorized, String token, String expiry, String reason) {
                runOnUiThread(() -> {
                    if (authorized) {
                        statusText.setText("AUTHORIZED\nToken: " + token + "\nExpiry: " + expiry);
                    } else {
                        statusText.setText("DENIED: " + reason);
                        Toast.makeText(SdkDemoActivity.this, reason, Toast.LENGTH_LONG).show();
                    }
                });
            }
        });
    }
}
