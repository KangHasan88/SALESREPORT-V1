package com.sekarbumi.erequestapproval;

import android.Manifest;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Intent;
import android.content.SharedPreferences;
import android.media.AudioAttributes;
import android.net.Uri;
import android.content.pm.PackageManager;
import android.os.Build;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

import org.json.JSONObject;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public class ERequestFirebaseMessagingService extends FirebaseMessagingService {
    private static final String APPROVAL_CHANNEL_ID = "erequest_approval_requests_voice_v2";
    private static final String WORK_ORDER_CHANNEL_ID = "erequest_work_order_voice_v1";
    private static final String PREFS = "erequest_approval";
    private static final String DEFAULT_BASE_URL = "http://103.172.43.220:8181/api/mobile";

    @Override
    public void onMessageReceived(RemoteMessage message) {
        String title = "e-Request";
        String body = "Ada request baru yang perlu ditindaklanjuti.";
        String channelId = APPROVAL_CHANNEL_ID;

        if (message.getNotification() != null) {
            if (message.getNotification().getTitle() != null) {
                title = message.getNotification().getTitle();
            }
            if (message.getNotification().getBody() != null) {
                body = message.getNotification().getBody();
            }
        } else if (!message.getData().isEmpty()) {
            title = message.getData().containsKey("title") ? message.getData().get("title") : title;
            body = message.getData().containsKey("body") ? message.getData().get("body") : body;
        }

        if ("work_order".equals(message.getData().get("sound_type"))) {
            channelId = WORK_ORDER_CHANNEL_ID;
        }

        showNotification(
                title,
                body,
                channelId,
                message.getData().get("type"),
                message.getData().get("target"),
                message.getData().get("sound_type")
        );
    }

    @Override
    public void onNewToken(String token) {
        super.onNewToken(token);
        registerToken(token);
    }

    private void showNotification(String title, String body, String channelId, String type, String target, String soundType) {
        NotificationManager manager = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
        if (manager == null) {
            return;
        }

        if (Build.VERSION.SDK_INT >= 33 && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            return;
        }

        if (Build.VERSION.SDK_INT >= 26) {
            ensureChannel(manager, APPROVAL_CHANNEL_ID, "Approval Request", "Notifikasi antrian approval PB", R.raw.erequest_notification);
            ensureChannel(manager, WORK_ORDER_CHANNEL_ID, "Work Order Assignment", "Notifikasi work order masuk untuk pelaksana", R.raw.work_order_notification);
        }

        android.app.Notification.Builder builder = Build.VERSION.SDK_INT >= 26
                ? new android.app.Notification.Builder(this, channelId)
                : new android.app.Notification.Builder(this);

        Intent intent = new Intent(this, MainActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        intent.putExtra("type", type == null ? "" : type);
        intent.putExtra("sound_type", soundType == null ? "" : soundType);
        String resolvedTarget = target == null || target.isEmpty()
                ? ("work_order".equals(soundType) ? "section_wo" : "approval")
                : target;
        intent.putExtra("target", resolvedTarget);
        int flags = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= 23) {
            flags |= PendingIntent.FLAG_IMMUTABLE;
        }
        PendingIntent pendingIntent = PendingIntent.getActivity(this, 1002, intent, flags);

        builder.setSmallIcon(R.drawable.ic_notification)
                .setContentTitle(title)
                .setContentText(body)
                .setContentIntent(pendingIntent)
                .setAutoCancel(true);

        manager.notify((int) System.currentTimeMillis(), builder.build());
    }

    private void ensureChannel(NotificationManager manager, String id, String name, String description, int soundResId) {
        if (Build.VERSION.SDK_INT < 26 || manager.getNotificationChannel(id) != null) {
            return;
        }

        NotificationChannel channel = new NotificationChannel(id, name, NotificationManager.IMPORTANCE_HIGH);
        channel.setDescription(description);
        channel.setSound(notificationSoundUri(soundResId), new AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .build());
        manager.createNotificationChannel(channel);
    }

    private Uri notificationSoundUri(int soundResId) {
        return Uri.parse("android.resource://" + getPackageName() + "/" + soundResId);
    }

    private void registerToken(String fcmToken) {
        if (fcmToken == null || fcmToken.isEmpty()) {
            return;
        }

        SharedPreferences prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        String authToken = prefs.getString("token", "");
        if (authToken.isEmpty()) {
            return;
        }

        String baseUrl = prefs.getString("base_url", DEFAULT_BASE_URL);
        new Thread(() -> {
            HttpURLConnection conn = null;
            try {
                URL url = new URL(baseUrl + "/device-token");
                conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setConnectTimeout(12000);
                conn.setReadTimeout(12000);
                conn.setDoOutput(true);
                conn.setRequestProperty("Accept", "application/json");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setRequestProperty("Authorization", "Bearer " + authToken);

                JSONObject body = new JSONObject();
                body.put("fcm_token", fcmToken);
                body.put("platform", "android");

                byte[] bytes = body.toString().getBytes(StandardCharsets.UTF_8);
                try (OutputStream out = conn.getOutputStream()) {
                    out.write(bytes);
                }
                conn.getResponseCode();
            } catch (Exception ignored) {
            } finally {
                if (conn != null) {
                    conn.disconnect();
                }
            }
        }).start();
    }
}
