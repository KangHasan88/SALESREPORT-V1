package com.sekarbumi.erequestapproval;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.app.DownloadManager;
import android.app.Notification;
import android.app.DatePickerDialog;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.media.AudioAttributes;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.os.Handler;
import android.os.Looper;
import android.provider.MediaStore;
import android.provider.Settings;
import android.text.Editable;
import android.text.InputType;
import android.text.TextWatcher;
import android.view.Gravity;
import android.view.View;
import android.view.animation.DecelerateInterpolator;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;
import android.webkit.WebResourceRequest;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;

import org.json.JSONArray;
import org.json.JSONObject;

import com.google.firebase.messaging.FirebaseMessaging;

import java.io.BufferedReader;
import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.text.NumberFormat;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.Date;
import java.util.HashMap;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import androidx.core.content.FileProvider;

public class MainActivity extends Activity {
    private static final String DEFAULT_BASE_URL = "http://103.172.43.220:8181/api/mobile";
    private static final String PREFS = "erequest_approval";
    private static final String APPROVAL_CHANNEL_ID = "erequest_approval_requests_voice_v2";
    private static final String WORK_ORDER_CHANNEL_ID = "erequest_work_order_voice_v1";
    private static final String UPDATE_CHANNEL_ID = "erequest_app_updates";
    private static final int NAVY = Color.rgb(17, 24, 39);
    private static final int TEAL = Color.rgb(15, 118, 110);
    private static final int BLUE = Color.rgb(37, 99, 235);
    private static final int BG = Color.rgb(243, 246, 250);
    private static final int SURFACE = Color.rgb(248, 250, 252);
    private static final int BORDER = Color.rgb(226, 232, 240);
    private static final int TEXT = Color.rgb(15, 23, 42);
    private static final int MUTED = Color.rgb(100, 116, 139);
    private static final int ORANGE = Color.rgb(217, 119, 6);
    private static final int GREEN = Color.rgb(22, 163, 74);
    private static final int RED = Color.rgb(220, 38, 38);
    private static final int PINK = Color.rgb(219, 39, 119);

    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private final Handler handler = new Handler(Looper.getMainLooper());
    private SharedPreferences prefs;
    private LinearLayout root;
    private int lastNotificationCount = -1;
    private Runnable poller;
    private String currentScreen = "login";
    private int pendingDoneWoId = -1;
    private Uri pendingCameraUri;
    private final ArrayList<Uri> pendingCameraUris = new ArrayList<>();
    private ValueCallback<Uri[]> webFileCallback;
    private Uri pendingWebCameraUri;
    private boolean updateDialogShowing = false;
    private long updateDownloadId = -1L;
    private File pendingUpdateFile;
    private final BroadcastReceiver updateDownloadReceiver = new BroadcastReceiver() {
        @Override
        public void onReceive(Context context, Intent intent) {
            if (!DownloadManager.ACTION_DOWNLOAD_COMPLETE.equals(intent.getAction())) {
                return;
            }
            long id = intent.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1L);
            if (id != updateDownloadId) {
                return;
            }
            handleUpdateDownloadComplete(id);
        }
    };
    private static final int REQ_PICK_PHOTOS = 2401;
    private static final int REQ_CAPTURE_PHOTO = 2402;
    private static final int REQ_WEB_FILE = 2403;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        configureSystemBars();
        prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        createNotificationChannel();
        requestNotificationPermission();
        registerUpdateDownloadReceiver();

        if (token().isEmpty()) {
            showLogin();
            refreshRemoteConfig(true);
        } else {
            registerFcmToken();
            showDashboard();
            handleLaunchIntent(getIntent());
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleLaunchIntent(intent);
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        try {
            unregisterReceiver(updateDownloadReceiver);
        } catch (Exception ignored) {
        }
        executor.shutdownNow();
        super.onDestroy();
    }

    private void showLogin() {
        currentScreen = "login";
        stopPolling();
        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setBackgroundColor(BG);
        root = column(18);
        root.setGravity(Gravity.CENTER_VERTICAL);
        root.setPadding(dp(24), dp(18), dp(24), dp(18));
        scroll.addView(root);

        TextView logo = title(labelValue("label_app_title", "e-Request"), 24, navyColor());
        logo.setGravity(Gravity.CENTER);
        root.addView(logo);
        TextView sub = label(labelValue("label_login_subtitle", "Request, Approval, and Tracking"), 13, MUTED);
        sub.setGravity(Gravity.CENTER);
        root.addView(sub);

        LinearLayout card = card(14);
        card.setPadding(dp(18), dp(20), dp(18), dp(20));
        root.addView(card);

        TextView welcome = title("Selamat Datang", 22, TEXT);
        welcome.setGravity(Gravity.CENTER);
        card.addView(welcome);
        TextView help = label("Masuk untuk mengelola request, approval, dan tracking", 13, MUTED);
        help.setGravity(Gravity.CENTER);
        card.addView(help);

        EditText username = input("Username");
        EditText password = input("Kata sandi");
        password.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD);
        card.addView(username);
        card.addView(password);

        Button login = primaryButton("Masuk");
        login.setLayoutParams(new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(46)));
        card.addView(login);
        login.setOnClickListener(v -> {
            String u = username.getText().toString().trim();
            String p = password.getText().toString();
            if (u.isEmpty() || p.isEmpty()) {
                toast("Username dan kata sandi wajib diisi.");
                return;
            }
            login.setEnabled(false);
            login.setText("Memproses...");
            doLogin(u, p, login);
        });

        setContentView(scroll);
    }

    private void doLogin(String username, String password, Button button) {
        executor.execute(() -> {
            try {
                JSONObject body = new JSONObject();
                body.put("username", username);
                body.put("password", password);
                body.put("device_name", Build.MODEL == null ? "Android" : Build.MODEL);
                ApiResponse response = post("/login", body);
                if (response.ok && response.json.optBoolean("success")) {
                    JSONObject user = response.json.getJSONObject("user");
                    prefs.edit()
                            .putString("token", response.json.getString("token"))
                            .putString("name", user.optString("name"))
                            .putString("username", user.optString("username", username))
                            .putString("role", user.optString("role"))
                            .putString("role_label", user.optString("role_label"))
                            .apply();
                    registerFcmToken();
                    runOnUiThread(this::showDashboard);
                } else {
                    String msg = response.json == null ? "Login gagal." : response.json.optString("message", "Username atau kata sandi salah.");
                    runOnUiThread(() -> {
                        toast(msg);
                        button.setEnabled(true);
                        button.setText("Masuk");
                    });
                }
            } catch (Exception e) {
                runOnUiThread(() -> {
                    toast("Tidak bisa terhubung ke server.");
                    button.setEnabled(true);
                    button.setText("Masuk");
                });
            }
        });
    }

    private void showDashboard() {
        currentScreen = "dashboard";
        refreshRemoteConfig(false);
        if (isEngineeringUser()) {
            showWebViewScreen("Engineering", "Buat dan pantau PB serta WO.", "/web/engineering", "home");
            startPolling();
            checkForAppUpdate();
            return;
        }
        if ("approval".equals(role()) || "approval2".equals(role()) || "section_head".equals(role())) {
            showWebViewScreen("Dashboard", "Ringkasan antrian approval.", "/web/dashboard", "home");
            startPolling();
            checkForAppUpdate();
            return;
        }
        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setBackgroundColor(BG);
        root = column(14);
        root.setPadding(dp(18), topSafePadding(), dp(18), dp(22));
        scroll.addView(root);

        TextView greeting = title(greetingText() + ", " + displayUsername(), 20, TEXT);
        greeting.setIncludeFontPadding(false);
        root.addView(greeting);

        LinearLayout hero = darkPanel(10);
        hero.addView(label(labelValue("label_app_title", "e-Request"), 13, Color.rgb(191, 219, 254)));
        hero.addView(title(roleLabel(), 20, Color.WHITE));
        hero.addView(label(prefs.getString("name", "-"), 13, Color.rgb(203, 213, 225)));
        root.addView(hero);

        LinearLayout summary = card(14);
        root.addView(summary);
        summary.addView(label("Memuat ringkasan...", 14, MUTED));

        LinearLayout budgetContainer = column(12);
        root.addView(budgetContainer);

        root.addView(actionPanel());

        setContentWithNav(scroll, "home");
        loadDashboard(summary, budgetContainer);
        startPolling();
        checkForAppUpdate();
    }

    private void refreshRemoteConfig(boolean redrawCurrentScreen) {
        executor.execute(() -> {
            try {
                ApiResponse response = get("/config");
                if (!response.ok || response.json == null || !response.json.optBoolean("success")) {
                    return;
                }
                JSONObject data = response.json.optJSONObject("data");
                if (data == null) {
                    return;
                }
                JSONObject theme = data.optJSONObject("theme");
                JSONObject features = data.optJSONObject("features");
                JSONObject labels = data.optJSONObject("labels");
                SharedPreferences.Editor editor = prefs.edit();
                if (theme != null) {
                    putConfigString(editor, theme, "primary_color", "theme_primary_color");
                    putConfigString(editor, theme, "accent_color", "theme_accent_color");
                    putConfigString(editor, theme, "navy_color", "theme_navy_color");
                    putConfigString(editor, theme, "background_color", "theme_background_color");
                    putConfigString(editor, theme, "surface_color", "theme_surface_color");
                    putConfigString(editor, theme, "border_color", "theme_border_color");
                }
                if (features != null) {
                    editor.putBoolean("feature_show_bottom_nav", features.optBoolean("show_bottom_nav", true));
                    editor.putBoolean("feature_history_search_on_dashboard", features.optBoolean("history_search_on_dashboard", false));
                    editor.putBoolean("feature_enable_webview_pages", features.optBoolean("enable_webview_pages", false));
                }
                if (labels != null) {
                    putConfigString(editor, labels, "language", "label_language");
                    putConfigString(editor, labels, "app_title", "label_app_title");
                    putConfigString(editor, labels, "login_subtitle", "label_login_subtitle");
                }
                editor.apply();
                if (redrawCurrentScreen) {
                    runOnUiThread(() -> {
                        if ("login".equals(currentScreen)) {
                            showLogin();
                        } else if (!token().isEmpty()) {
                            showDashboard();
                        }
                    });
                }
            } catch (Exception ignored) {
            }
        });
    }

    private void putConfigString(SharedPreferences.Editor editor, JSONObject source, String sourceKey, String prefKey) {
        String value = source.optString(sourceKey, "").trim();
        if (!value.isEmpty()) {
            editor.putString(prefKey, value);
        }
    }

    private void checkForAppUpdate() {
        executor.execute(() -> {
            try {
                ApiResponse response = get("/app-version");
                if (!response.ok || response.json == null || !response.json.optBoolean("success")) {
                    return;
                }
                JSONObject data = response.json.optJSONObject("data");
                if (data == null) {
                    return;
                }
                int latestCode = data.optInt("version_code", 0);
                if (latestCode <= currentVersionCode()) {
                    return;
                }
                runOnUiThread(() -> showUpdateDialog(data));
            } catch (Exception ignored) {
            }
        });
    }

    private void showUpdateDialog(JSONObject data) {
        if (updateDialogShowing) {
            return;
        }
        updateDialogShowing = true;
        boolean force = data.optBoolean("force_update", false);
        String version = data.optString("version_name", "baru");
        String apkUrl = data.optString("apk_url", "");
        String notes = releaseNotesText(data.optJSONArray("release_notes"));
        String message = "Versi " + version + " sudah tersedia.\n\n" + notes + "\nFile update akan diunduh di background. Setelah selesai, Android akan meminta konfirmasi install.";
        AlertDialog.Builder builder = new AlertDialog.Builder(this)
                .setTitle("Update tersedia")
                .setMessage(message.trim())
                .setPositiveButton("Download Update", (d, which) -> {
                    updateDialogShowing = false;
                    downloadUpdateApk(apkUrl, version);
                });
        if (!force) {
            builder.setNegativeButton("Nanti", (d, which) -> updateDialogShowing = false);
        }
        AlertDialog dialog = builder.create();
        dialog.setCancelable(!force);
        dialog.setOnCancelListener(d -> updateDialogShowing = false);
        dialog.show();
    }

    private String releaseNotesText(JSONArray notes) {
        if (notes == null || notes.length() == 0) {
            return "Silakan update untuk mendapatkan perbaikan terbaru.";
        }
        StringBuilder builder = new StringBuilder();
        for (int i = 0; i < notes.length(); i++) {
            String note = notes.optString(i, "").trim();
            if (!note.isEmpty()) {
                builder.append("- ").append(note).append("\n");
            }
        }
        return builder.toString();
    }

    private long currentVersionCode() {
        try {
            if (Build.VERSION.SDK_INT >= 28) {
                return getPackageManager().getPackageInfo(getPackageName(), 0).getLongVersionCode();
            }
            return getPackageManager().getPackageInfo(getPackageName(), 0).versionCode;
        } catch (Exception e) {
            return 0;
        }
    }

    private void registerUpdateDownloadReceiver() {
        IntentFilter filter = new IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE);
        if (Build.VERSION.SDK_INT >= 33) {
            registerReceiver(updateDownloadReceiver, filter, Context.RECEIVER_NOT_EXPORTED);
        } else {
            registerReceiver(updateDownloadReceiver, filter);
        }
    }

    private String resolveUpdateUrl(String apkUrl) {
        if (apkUrl == null || apkUrl.trim().isEmpty()) {
            return "";
        }
        String trimmed = apkUrl.trim();
        return trimmed.startsWith("http") ? trimmed : serverOrigin() + trimmed;
    }

    private void downloadUpdateApk(String apkUrl, String version) {
        String resolved = resolveUpdateUrl(apkUrl);
        if (resolved.isEmpty()) {
            toast("URL update belum tersedia.");
            return;
        }
        DownloadManager manager = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
        if (manager == null) {
            toast("Download manager tidak tersedia.");
            return;
        }
        File dir = getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS);
        if (dir == null) {
            toast("Folder download update tidak tersedia.");
            return;
        }
        if (!dir.exists()) {
            dir.mkdirs();
        }
        String safeVersion = version.replaceAll("[^A-Za-z0-9._-]", "_");
        File apkFile = new File(dir, "e-request-approval-" + safeVersion + ".apk");
        if (apkFile.exists()) {
            apkFile.delete();
        }

        try {
            DownloadManager.Request request = new DownloadManager.Request(Uri.parse(resolved));
            request.setTitle("e-Request " + version);
            request.setDescription("Mengunduh update aplikasi");
            request.setMimeType("application/vnd.android.package-archive");
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            request.setDestinationUri(Uri.fromFile(apkFile));
            pendingUpdateFile = apkFile;
            updateDownloadId = manager.enqueue(request);
            prefs.edit()
                    .putLong("update_download_id", updateDownloadId)
                    .putString("update_apk_path", apkFile.getAbsolutePath())
                    .putString("update_version_name", version)
                    .apply();
            toast("Update sedang diunduh di background.");
        } catch (Exception e) {
            toast("Gagal memulai download update.");
        }
    }

    private void handleUpdateDownloadComplete(long id) {
        DownloadManager manager = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
        if (manager == null) {
            return;
        }
        DownloadManager.Query query = new DownloadManager.Query().setFilterById(id);
        try (Cursor cursor = manager.query(query)) {
            if (cursor == null || !cursor.moveToFirst()) {
                toast("Download update tidak ditemukan.");
                return;
            }
            int status = cursor.getInt(cursor.getColumnIndexOrThrow(DownloadManager.COLUMN_STATUS));
            if (status != DownloadManager.STATUS_SUCCESSFUL) {
                toast("Download update gagal.");
                return;
            }
        } catch (Exception e) {
            toast("Gagal membaca status download update.");
            return;
        }

        File apkFile = pendingUpdateFile;
        if (apkFile == null) {
            String path = prefs.getString("update_apk_path", "");
            apkFile = path.isEmpty() ? null : new File(path);
        }
        if (apkFile == null || !apkFile.exists()) {
            toast("File update belum ditemukan.");
            return;
        }
        showInstallReadyDialog(apkFile);
        showUpdateReadyNotification(apkFile);
    }

    private void showInstallReadyDialog(File apkFile) {
        new AlertDialog.Builder(this)
                .setTitle("Update siap diinstall")
                .setMessage("File update sudah selesai diunduh. Klik Install untuk melanjutkan.")
                .setNegativeButton("Nanti", null)
                .setPositiveButton("Install", (dialog, which) -> installDownloadedApk(apkFile))
                .show();
    }

    private void showUpdateReadyNotification(File apkFile) {
        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager == null) {
            return;
        }
        Intent intent = installIntent(apkFile);
        int flags = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= 23) {
            flags |= PendingIntent.FLAG_IMMUTABLE;
        }
        PendingIntent pendingIntent = PendingIntent.getActivity(this, 1404, intent, flags);
        Notification.Builder builder = Build.VERSION.SDK_INT >= 26
                ? new Notification.Builder(this, UPDATE_CHANNEL_ID)
                : new Notification.Builder(this);
        Notification notification = builder
                .setSmallIcon(android.R.drawable.stat_sys_download_done)
                .setContentTitle("Update e-Request siap")
                .setContentText("Klik untuk install versi terbaru.")
                .setContentIntent(pendingIntent)
                .setAutoCancel(true)
                .build();
        manager.notify(1404, notification);
    }

    private Intent installIntent(File apkFile) {
        Uri uri = FileProvider.getUriForFile(this, getPackageName() + ".fileprovider", apkFile);
        Intent intent = new Intent(Intent.ACTION_VIEW);
        intent.setDataAndType(uri, "application/vnd.android.package-archive");
        intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_ACTIVITY_NEW_TASK);
        return intent;
    }

    private void installDownloadedApk(File apkFile) {
        if (Build.VERSION.SDK_INT >= 26 && !getPackageManager().canRequestPackageInstalls()) {
            Intent settings = new Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:" + getPackageName()));
            startActivity(settings);
            toast("Aktifkan izin install aplikasi, lalu klik install update lagi.");
            return;
        }
        try {
            startActivity(installIntent(apkFile));
        } catch (Exception e) {
            toast("Tidak bisa membuka installer update.");
        }
    }

    private void loadDashboard(LinearLayout summary, LinearLayout budgetContainer) {
        executor.execute(() -> {
            try {
                ApiResponse response = get("/dashboard");
                if (!response.ok) {
                    runOnUiThread(() -> handleAuthError(response));
                    return;
                }
                JSONObject s = response.json.getJSONObject("summary");
                runOnUiThread(() -> {
                    summary.removeAllViews();
                    budgetContainer.removeAllViews();
                    if (isEngineeringUser()) {
                        summary.addView(title("Aktivitas Engineering", 17, TEXT));
                        LinearLayout rowOne = row(8);
                        rowOne.addView(metricCard("Total PB", s.optInt("pb_total"), BLUE), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
                        rowOne.addView(metricCard("PB Pending", s.optInt("pb_pending"), ORANGE), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
                        summary.addView(rowOne);
                        LinearLayout rowTwo = row(8);
                        rowTwo.addView(metricCard("Total WO", s.optInt("wo_total"), TEAL), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
                        rowTwo.addView(metricCard("WO Progress", s.optInt("wo_progress"), GREEN), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
                        summary.addView(rowTwo);
                    } else if ("section_head".equals(role())) {
                        summary.addView(title("Pekerjaan Saya", 17, TEXT));
                        LinearLayout rowOne = row(8);
                        rowOne.addView(metricCard("Verifikasi PB", s.optInt("pb_verification"), ORANGE), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
                        rowOne.addView(metricCard("WO Assigned", s.optInt("assigned_wo"), BLUE), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
                        summary.addView(rowOne);
                        LinearLayout rowTwo = row(8);
                        rowTwo.addView(metricCard("Done Hari Ini", s.optInt("done_today"), GREEN), new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT));
                        summary.addView(rowTwo);
                    } else {
                        summary.addView(title("Antrian Approval", 17, TEXT));
                        LinearLayout rowOne = row(8);
                        rowOne.addView(metricCard("PB Menunggu", s.optInt("pb_pending"), ORANGE), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
                        rowOne.addView(metricCard("WO Menunggu", s.optInt("wo_pending"), BLUE), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
                        summary.addView(rowOne);
                        LinearLayout rowTwo = row(8);
                        rowTwo.addView(metricCard("Approved Hari Ini", s.optInt("approved_today"), GREEN), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
                        rowTwo.addView(metricCard("Rejected Hari Ini", s.optInt("rejected_today"), RED), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
                        summary.addView(rowTwo);
                        if ("approval".equals(role())) {
                            JSONObject budget = response.json.optJSONObject("budget");
                            JSONArray bySectionHead = response.json.optJSONArray("budget_by_section_head");
                            if (budget != null) {
                                budgetContainer.addView(budgetSnapshotCard(budget));
                            }
                            if (bySectionHead != null) {
                                budgetContainer.addView(sectionHeadBudgetCard(bySectionHead));
                            }
                        }
                    }
                });
            } catch (Exception e) {
                runOnUiThread(() -> toast("Gagal memuat dashboard."));
            }
        });
    }

    private LinearLayout actionPanel() {
        LinearLayout panel = card(12);
        panel.addView(title("Aksi Cepat", 16, TEXT));
        if (isEngineeringUser()) {
            LinearLayout actions = row(8);
            Button pb = primaryButton("Buat / Lihat PB");
            Button wo = secondaryButton("Buat / Lihat WO");
            actions.addView(pb, new LinearLayout.LayoutParams(0, dp(46), 1));
            actions.addView(wo, new LinearLayout.LayoutParams(0, dp(46), 1));
            panel.addView(actions);
            pb.setOnClickListener(v -> showWebViewScreen("Permintaan Barang", "Buat PB dan pantau progress approval.", "/web/engineering/pb", "home"));
            wo.setOnClickListener(v -> showWebViewScreen("Work Order", "Buat WO dan pantau progress pekerjaan.", "/web/engineering/wo", "home"));
        } else if ("section_head".equals(role())) {
            LinearLayout actions = row(8);
            Button pbVerify = primaryButton("Verifikasi PB");
            Button assignedWo = secondaryButton("WO Assigned");
            Button stockSparepart = secondaryButton("Stock Sparepart");
            actions.addView(pbVerify, new LinearLayout.LayoutParams(0, dp(46), 1));
            actions.addView(assignedWo, new LinearLayout.LayoutParams(0, dp(46), 1));
            panel.addView(actions);
            LinearLayout.LayoutParams stockParams = new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(46));
            stockParams.topMargin = dp(10);
            panel.addView(stockSparepart, stockParams);
            pbVerify.setOnClickListener(v -> showPbList());
            assignedWo.setOnClickListener(v -> showSectionWoList());
            stockSparepart.setOnClickListener(v -> showWebViewScreen(
                    "Stock Sparepart",
                    "Cek stok dari data ERP. Hasil tampil setelah mengetik minimal 2 huruf.",
                    "/web/stock-sparepart",
                    "home"
            ));
        } else if ("approval2".equals(role())) {
            Button pb = primaryButton("Review PB > 10jt");
            pb.setOnClickListener(v -> showPbList());
            panel.addView(pb, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(46)));
        } else {
            LinearLayout actions = row(8);
            Button pb = primaryButton("Review PB");
            Button wo = secondaryButton("Review WO");
            actions.addView(pb, new LinearLayout.LayoutParams(0, dp(46), 1));
            actions.addView(wo, new LinearLayout.LayoutParams(0, dp(46), 1));
            panel.addView(actions);
            pb.setOnClickListener(v -> showPbList());
            wo.setOnClickListener(v -> showWoList());
        }
        return panel;
    }

    private void showHistory() {
        currentScreen = "history";
        if (isEngineeringUser()) {
            showWebViewScreen("History", "Riwayat PB dan WO Engineering.", "/web/history", "history");
            return;
        }
        showWebViewScreen("History", historySubtitle(), "/web/history", "history");
    }

    private String historySubtitle() {
        if ("section_head".equals(role())) {
            return "Riwayat pekerjaan WO yang sudah selesai.";
        }
        if ("approval2".equals(role())) {
            return "Riwayat approval PB > 10 juta.";
        }
        return "Riwayat approval PB dan WO.";
    }

    private void showWebViewScreen(String heading, String subtitle, String path, String activeTab) {
        currentScreen = path.contains("/web/engineering") ? "engineering" : activeTab;

        LinearLayout page = new LinearLayout(this);
        page.setOrientation(LinearLayout.VERTICAL);
        page.setBackgroundColor(BG);
        page.setPadding(dp(18), topSafePadding(), dp(18), dp(10));

        LinearLayout top = row(8);
        Button back = ghostButton("< Kembali");
        top.addView(back, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, dp(40)));
        back.setOnClickListener(v -> showDashboard());
        TextView titleView = title(heading, 21, TEXT);
        titleView.setGravity(Gravity.RIGHT | Gravity.CENTER_VERTICAL);
        top.addView(titleView, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        page.addView(top);
        TextView subtitleView = label(subtitle, 13, MUTED);
        page.addView(subtitleView);
        top.setVisibility(View.GONE);
        subtitleView.setVisibility(View.GONE);

        WebView webView = new WebView(this);
        webView.setBackgroundColor(BG);
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(false);
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, FileChooserParams fileChooserParams) {
                if (webFileCallback != null) {
                    webFileCallback.onReceiveValue(null);
                }
                webFileCallback = filePathCallback;
                pendingWebCameraUri = null;
                Intent fileIntent = new Intent(Intent.ACTION_GET_CONTENT);
                fileIntent.addCategory(Intent.CATEGORY_OPENABLE);
                fileIntent.setType(resolveWebFileAccept(fileChooserParams));
                fileIntent.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
                fileIntent.putExtra(Intent.EXTRA_MIME_TYPES, resolveWebFileMimeTypes(fileChooserParams));
                fileIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
                Intent chooser = Intent.createChooser(fileIntent, "Pilih lampiran");
                ArrayList<Intent> extraIntents = new ArrayList<>();
                Intent cameraIntent = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
                if (cameraIntent.resolveActivity(getPackageManager()) != null) {
                    try {
                        pendingWebCameraUri = createCameraImageUri();
                        cameraIntent.putExtra(MediaStore.EXTRA_OUTPUT, pendingWebCameraUri);
                        cameraIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_GRANT_WRITE_URI_PERMISSION);
                        extraIntents.add(cameraIntent);
                    } catch (IOException ignored) {
                        pendingWebCameraUri = null;
                    }
                }
                if (!extraIntents.isEmpty()) {
                    chooser.putExtra(Intent.EXTRA_INITIAL_INTENTS, extraIntents.toArray(new Intent[0]));
                }
                try {
                    startActivityForResult(chooser, REQ_WEB_FILE);
                } catch (Exception e) {
                    webFileCallback = null;
                    pendingWebCameraUri = null;
                    toast("Gagal membuka pilihan file.");
                    return false;
                }
                return true;
            }
        });
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                return handleWebNavigation(view, request.getUrl().toString());
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                return handleWebNavigation(view, url);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                top.setVisibility(View.GONE);
                subtitleView.setVisibility(View.GONE);
            }
        });

        LinearLayout.LayoutParams webParams = new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 0, 1);
        page.addView(webView, webParams);
        setContentWithNav(page, activeTab);
        webView.loadUrl(baseUrl() + path, authHeaders());
    }

    private boolean isStockSparepartWebUrl(String url) {
        return url != null && url.contains("/web/stock-sparepart");
    }

    private boolean isDashboardWebPath(String path) {
        return "/web/dashboard".equals(path);
    }

    private boolean isDashboardWebUrl(String url) {
        return url != null && (url.contains("/web/dashboard") || url.contains("/api/mobile/web/dashboard"));
    }

    private boolean isMobileWebUrl(String url) {
        return url != null && (url.startsWith(baseUrl() + "/web/") || url.startsWith(serverOrigin() + "/api/mobile/web/"));
    }

    private boolean isMobileDocumentUrl(String url) {
        return url != null
                && url.startsWith(serverOrigin() + "/api/mobile/wo/")
                && url.contains("/document");
    }

    private String webHeadingForUrl(String url, String fallback) {
        if (url == null) {
            return fallback;
        }
        if (isStockSparepartWebUrl(url)) {
            return "";
        }
        if (url.contains("/web/engineering/pb/create")) {
            return "Buat PB";
        }
        if (url.contains("/web/engineering/pb")) {
            return "Permintaan Barang";
        }
        if (url.contains("/web/engineering/wo/create")) {
            return "Buat WO";
        }
        if (url.contains("/web/engineering/wo")) {
            return "Work Order";
        }
        if (url.contains("/web/engineering")) {
            return "Engineering";
        }
        return fallback;
    }

    private String webSubtitleForUrl(String url, String fallback) {
        if (url == null) {
            return fallback;
        }
        if (isStockSparepartWebUrl(url)) {
            return "";
        }
        if (url.contains("/web/engineering/pb/create")) {
            return "Lengkapi permintaan barang, lalu kirim ke approval.";
        }
        if (url.contains("/web/engineering/pb")) {
            return "Buat PB dan pantau progress approval.";
        }
        if (url.contains("/web/engineering/wo/create")) {
            return "Lengkapi work order, lalu submit ke Approval L1.";
        }
        if (url.contains("/web/engineering/wo")) {
            return "Buat WO dan pantau progress pekerjaan.";
        }
        if (url.contains("/web/engineering")) {
            return "Buat dan pantau PB serta WO.";
        }
        return fallback;
    }

    private boolean handleWebNavigation(WebView view, String url) {
        if (isMobileDocumentUrl(url)) {
            openProtectedDocument(url);
            return true;
        }
        if (isMobileWebUrl(url)) {
            if (isDashboardWebUrl(url)) {
                showDashboard();
                return true;
            }
            view.loadUrl(url, authHeaders());
            return true;
        }
        return false;
    }

    private String resolveWebFileAccept(WebChromeClient.FileChooserParams params) {
        String[] types = params == null ? null : params.getAcceptTypes();
        if (types == null || types.length == 0) {
            return "*/*";
        }
        boolean hasImage = false;
        boolean hasOther = false;
        for (String type : types) {
            if (type == null || type.trim().isEmpty()) {
                continue;
            }
            String clean = type.trim().toLowerCase(Locale.ROOT);
            if (clean.startsWith("image/")) {
                hasImage = true;
            } else {
                hasOther = true;
            }
        }
        if (hasImage && !hasOther) {
            return "image/*";
        }
        return "*/*";
    }

    private String[] resolveWebFileMimeTypes(WebChromeClient.FileChooserParams params) {
        String[] types = params == null ? null : params.getAcceptTypes();
        if (types == null || types.length == 0) {
            return new String[]{"image/*", "application/pdf"};
        }
        ArrayList<String> result = new ArrayList<>();
        for (String type : types) {
            if (type == null || type.trim().isEmpty()) {
                continue;
            }
            String clean = type.trim();
            if (clean.equalsIgnoreCase(".pdf")) {
                clean = "application/pdf";
            }
            if (clean.equalsIgnoreCase(".jpg") || clean.equalsIgnoreCase(".jpeg")) {
                clean = "image/jpeg";
            }
            if (clean.equalsIgnoreCase(".png")) {
                clean = "image/png";
            }
            if (clean.equalsIgnoreCase(".webp")) {
                clean = "image/webp";
            }
            if (!result.contains(clean)) {
                result.add(clean);
            }
        }
        if (result.isEmpty()) {
            result.add("image/*");
            result.add("application/pdf");
        }
        return result.toArray(new String[0]);
    }

    private void openProtectedDocument(String url) {
        toast("Membuka lampiran...");
        executor.execute(() -> {
            HttpURLConnection conn = null;
            try {
                conn = (HttpURLConnection) new URL(url).openConnection();
                conn.setConnectTimeout(15000);
                conn.setReadTimeout(30000);
                conn.setRequestProperty("Accept", "*/*");
                if (!token().isEmpty()) {
                    conn.setRequestProperty("Authorization", "Bearer " + token());
                }
                int code = conn.getResponseCode();
                if (code < 200 || code >= 300) {
                    runOnUiThread(() -> toast("Lampiran belum bisa dibuka."));
                    return;
                }

                String contentType = conn.getContentType();
                String extension = documentExtension(url, contentType);
                File dir = getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS);
                if (dir == null) {
                    runOnUiThread(() -> toast("Folder lampiran tidak tersedia."));
                    return;
                }
                if (!dir.exists()) {
                    dir.mkdirs();
                }
                File file = new File(dir, "lampiran-wo-" + System.currentTimeMillis() + extension);
                try (InputStream input = conn.getInputStream(); FileOutputStream output = new FileOutputStream(file)) {
                    byte[] buffer = new byte[8192];
                    int read;
                    while ((read = input.read(buffer)) != -1) {
                        output.write(buffer, 0, read);
                    }
                }
                runOnUiThread(() -> openDownloadedDocument(file, contentType));
            } catch (Exception e) {
                runOnUiThread(() -> toast("Gagal membuka lampiran."));
            } finally {
                if (conn != null) {
                    conn.disconnect();
                }
            }
        });
    }

    private String documentExtension(String url, String contentType) {
        String lowerType = contentType == null ? "" : contentType.toLowerCase(Locale.ROOT);
        if (lowerType.contains("pdf")) {
            return ".pdf";
        }
        if (lowerType.contains("png")) {
            return ".png";
        }
        if (lowerType.contains("webp")) {
            return ".webp";
        }
        if (lowerType.contains("jpeg") || lowerType.contains("jpg")) {
            return ".jpg";
        }
        String clean = url == null ? "" : url.split("\\?")[0].toLowerCase(Locale.ROOT);
        if (clean.endsWith(".pdf")) return ".pdf";
        if (clean.endsWith(".png")) return ".png";
        if (clean.endsWith(".webp")) return ".webp";
        if (clean.endsWith(".jpeg")) return ".jpg";
        if (clean.endsWith(".jpg")) return ".jpg";
        return ".pdf";
    }

    private void openDownloadedDocument(File file, String contentType) {
        try {
            Uri uri = FileProvider.getUriForFile(this, getPackageName() + ".fileprovider", file);
            String type = contentType == null || contentType.trim().isEmpty() ? "application/pdf" : contentType.split(";")[0].trim();
            Intent intent = new Intent(Intent.ACTION_VIEW);
            intent.setDataAndType(uri, type);
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            startActivity(Intent.createChooser(intent, "Buka lampiran"));
        } catch (Exception e) {
            toast("Tidak ada aplikasi untuk membuka lampiran.");
        }
    }

    private Map<String, String> authHeaders() {
        HashMap<String, String> headers = new HashMap<>();
        if (!token().isEmpty()) {
            headers.put("Authorization", "Bearer " + token());
        }
        return headers;
    }

    private void renderHistory(JSONArray items) {
        baseListScreen("History", historySubtitle());
        currentScreen = "history";
        final String[] selectedType = {"Semua"};
        final String[] fromDate = {""};
        final String[] toDate = {""};
        final String[] keyword = {""};

        LinearLayout filters = card(8);
        if ("approval".equals(role())) {
            Button typeButton = secondaryButton("Tipe: Semua");
            filters.addView(typeButton, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(42)));
            typeButton.setOnClickListener(v -> new AlertDialog.Builder(this)
                    .setTitle("Filter tipe history")
                    .setItems(new String[]{"Semua", "PB", "WO"}, (dialog, which) -> {
                        selectedType[0] = which == 1 ? "PB" : (which == 2 ? "WO" : "Semua");
                        typeButton.setText("Tipe: " + selectedType[0]);
                        LinearLayout list = (LinearLayout) typeButton.getTag();
                        if (list != null) {
                            renderHistoryItems(items, list, keyword[0], selectedType[0], fromDate[0], toDate[0]);
                        }
                    })
                    .show());
        }

        LinearLayout range = row(8);
        Button from = secondaryButton("Dari");
        Button to = secondaryButton("Sampai");
        Button reset = ghostButton("Reset");
        range.addView(from, new LinearLayout.LayoutParams(0, dp(42), 1));
        range.addView(to, new LinearLayout.LayoutParams(0, dp(42), 1));
        range.addView(reset, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, dp(42)));
        filters.addView(range);
        root.addView(filters);

        EditText search = input("Cari nomor, status, atau riwayat");
        root.addView(search);
        LinearLayout list = column(12);
        root.addView(list);
        for (int i = 0; i < filters.getChildCount(); i++) {
            View child = filters.getChildAt(i);
            if (child instanceof Button && ((Button) child).getText().toString().startsWith("Tipe:")) {
                child.setTag(list);
            }
        }
        Runnable refresh = () -> renderHistoryItems(items, list, keyword[0], selectedType[0], fromDate[0], toDate[0]);
        from.setOnClickListener(v -> pickHistoryDate("Dari tanggal", fromDate, value -> {
            from.setText(value);
            refresh.run();
        }));
        to.setOnClickListener(v -> pickHistoryDate("Sampai tanggal", toDate, value -> {
            to.setText(value);
            refresh.run();
        }));
        reset.setOnClickListener(v -> {
            fromDate[0] = "";
            toDate[0] = "";
            from.setText("Dari");
            to.setText("Sampai");
            refresh.run();
        });
        search.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                keyword[0] = s.toString();
                refresh.run();
            }

            @Override
            public void afterTextChanged(Editable s) {
            }
        });
        refresh.run();
    }

    private void renderHistoryItems(JSONArray items, LinearLayout list, String keyword) {
        renderHistoryItems(items, list, keyword, "Semua", "", "");
    }

    private void renderHistoryItems(JSONArray items, LinearLayout list, String keyword, String typeFilter, String fromDate, String toDate) {
        list.removeAllViews();
        String filter = keyword == null ? "" : keyword.trim().toLowerCase(Locale.ROOT);
        if (items.length() == 0) {
            list.addView(empty("Belum ada history."));
            return;
        }

        int shown = 0;
        for (int i = 0; i < items.length(); i++) {
            JSONObject item = items.optJSONObject(i);
            if (item == null) {
                continue;
            }
            if (!filter.isEmpty() && !item.toString().toLowerCase(Locale.ROOT).contains(filter)) {
                continue;
            }
            String type = item.optString("history_type", item.has("nomor_pb") ? "PB" : "WO");
            if (!"Semua".equalsIgnoreCase(typeFilter) && !type.equalsIgnoreCase(typeFilter)) {
                continue;
            }
            String dateKey = historyDateKey(item);
            if (!fromDate.isEmpty() && (dateKey.isEmpty() || dateKey.compareTo(fromDate) < 0)) {
                continue;
            }
            if (!toDate.isEmpty() && (dateKey.isEmpty() || dateKey.compareTo(toDate) > 0)) {
                continue;
            }
            LinearLayout card = card(10);
            card.setPadding(dp(14), dp(14), dp(14), dp(13));
            String status = item.optString("progress_status", item.optString("status", "-"));
            LinearLayout header = row(8);
            TextView typePill = pill(type, "WO".equals(type) ? accentColor() : primaryColor());
            header.addView(typePill, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, dp(30)));
            header.addView(new View(this), new LinearLayout.LayoutParams(0, 1, 1));
            TextView statusPill = pill(statusLabel(status), statusColor(status));
            statusPill.setGravity(Gravity.CENTER);
            LinearLayout.LayoutParams statusParams = new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, dp(30));
            statusParams.gravity = Gravity.RIGHT;
            header.addView(statusPill, statusParams);
            card.addView(header);

            TextView number = title(item.optString("nomor_pb", item.optString("nomor", "-")), 16, TEXT);
            number.setPadding(0, dp(2), 0, 0);
            card.addView(number);
            card.addView(label(item.optString("judul", item.optString("tujuan_nama", "-")), 13, TEXT));
            if (item.has("total_value")) {
                card.addView(historyMetaRow("Total", rupiah(item.optDouble("total_value"))));
            }
            String time = item.optString("closed_at", item.optString("approved_at", item.optString("rejected_at", "")));
            if (!time.isEmpty() && !"null".equals(time)) {
                card.addView(historyMetaRow("Tanggal", time));
            }
            JSONArray details = item.optJSONArray("items");
            if (details != null && details.length() > 0) {
                card.addView(thinDivider());
                card.addView(label("Barang", 12, MUTED));
                for (int j = 0; j < details.length(); j++) {
                    JSONObject detail = details.optJSONObject(j);
                    card.addView(label("- " + detail.optString("nama_barang") + " x " + qty(detail.optDouble("jumlah")) + " " + detail.optString("satuan"), 12, TEXT));
                }
            }
            JSONArray photos = item.optJSONArray("photos");
            if (photos != null && photos.length() > 0) {
                card.addView(historyMetaRow("Foto hasil", photos.length() + " file"));
            }
            TextView hint = label("Ketuk untuk lihat detail", 12, primaryColor());
            hint.setGravity(Gravity.RIGHT);
            hint.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
            card.addView(hint);
            if ("WO".equalsIgnoreCase(type)) {
                card.setOnClickListener(v -> showWoDetail(item, "section_head".equals(role()), this::showHistory));
            } else if ("PB".equalsIgnoreCase(type)) {
                card.setOnClickListener(v -> showPbHistoryDetail(item));
            }
            list.addView(card);
            shown++;
        }

        if (shown == 0) {
            list.addView(empty("Tidak ada history yang cocok."));
        }
    }

    private interface DatePicked {
        void onPicked(String value);
    }

    private void pickHistoryDate(String title, String[] target, DatePicked picked) {
        Calendar calendar = Calendar.getInstance();
        if (target[0] != null && target[0].matches("\\d{4}-\\d{2}-\\d{2}")) {
            String[] parts = target[0].split("-");
            calendar.set(Integer.parseInt(parts[0]), Integer.parseInt(parts[1]) - 1, Integer.parseInt(parts[2]));
        }
        DatePickerDialog dialog = new DatePickerDialog(this, (view, year, month, dayOfMonth) -> {
            String value = String.format(Locale.US, "%04d-%02d-%02d", year, month + 1, dayOfMonth);
            target[0] = value;
            picked.onPicked(value);
        }, calendar.get(Calendar.YEAR), calendar.get(Calendar.MONTH), calendar.get(Calendar.DAY_OF_MONTH));
        dialog.setTitle(title);
        dialog.show();
    }

    private String historyDateKey(JSONObject item) {
        String raw = item.optString("closed_at",
                item.optString("approved_at",
                        item.optString("rejected_at",
                                item.optString("created_at", ""))));
        if (raw == null || raw.isEmpty() || "null".equals(raw)) {
            return "";
        }
        if (raw.matches("\\d{4}-\\d{2}-\\d{2}.*")) {
            return raw.substring(0, 10);
        }
        java.util.regex.Matcher matcher = java.util.regex.Pattern.compile("(\\d{1,2})/(\\d{1,2})/(\\d{4})").matcher(raw);
        if (matcher.find()) {
            return String.format(Locale.US, "%04d-%02d-%02d",
                    Integer.parseInt(matcher.group(3)),
                    Integer.parseInt(matcher.group(2)),
                    Integer.parseInt(matcher.group(1)));
        }
        return "";
    }

    private void showProfile() {
        currentScreen = "profile";
        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setBackgroundColor(BG);
        root = column(14);
        root.setPadding(dp(18), topSafePadding(), dp(18), dp(22));
        scroll.addView(root);

        root.addView(title("Profil", 23, TEXT));
        LinearLayout hero = darkPanel(10);
        hero.addView(title(displayUsername(), 22, Color.WHITE));
        hero.addView(label(roleLabel(), 13, Color.rgb(203, 213, 225)));
        hero.addView(label(prefs.getString("name", "-"), 13, Color.rgb(203, 213, 225)));
        root.addView(hero);

        LinearLayout info = card(10);
        info.addView(title("Akun", 16, TEXT));
        info.addView(label("Username: " + displayUsername(), 13, TEXT));
        info.addView(label("Nama: " + prefs.getString("name", "-"), 13, TEXT));
        info.addView(label("Role: " + roleLabel(), 13, TEXT));
        info.addView(label("Server: " + baseUrl(), 12, MUTED));
        root.addView(info);

        Button logout = dangerButton("Keluar");
        logout.setOnClickListener(v -> confirm("Keluar aplikasi?", "Sesi mobile akan diakhiri dari perangkat ini.", this::doLogout));
        root.addView(logout, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(46)));

        setContentWithNav(scroll, "profile");
    }

    private void showPbList() {
        setLoadingScreen("Review PB", "Memuat permintaan barang...");
        executor.execute(() -> {
            try {
                ApiResponse response = get("/pb");
                if (!response.ok) {
                    runOnUiThread(() -> handleAuthError(response));
                    return;
                }
                JSONArray data = response.json.getJSONArray("data");
                runOnUiThread(() -> renderPbList(data));
            } catch (Exception e) {
                runOnUiThread(() -> toast("Gagal memuat PB."));
            }
        });
    }

    private void renderPbList(JSONArray items) {
        boolean sectionHead = "section_head".equals(role());
        baseListScreen(sectionHead ? "Verifikasi PB" : "Review PB", sectionHead ? "Konfirmasi PB sebelum masuk Approval Level 1." : "Antrian permintaan barang yang perlu keputusan.");
        if (items.length() == 0) {
            root.addView(empty(sectionHead ? "Tidak ada PB menunggu verifikasi." : "Tidak ada PB menunggu approval."));
            return;
        }

        for (int i = 0; i < items.length(); i++) {
            JSONObject item = items.optJSONObject(i);
            LinearLayout card = card(12);
            card.addView(title(item.optString("nomor_pb"), 15, TEXT));
            card.addView(pill(sectionHead ? "Menunggu Verifikasi" : "Approval Level " + item.optInt("approval_current_level"), sectionHead ? ORANGE : BLUE));
            card.addView(label(item.optString("tujuan_nama") + " - " + item.optString("jenis_pekerjaan", "-"), 13, TEXT));
            card.addView(label("Total: " + rupiah(item.optDouble("total_value")), 13, item.optBoolean("is_high_value") ? ORANGE : MUTED));
            JSONArray preview = item.optJSONArray("items");
            if (preview != null && preview.length() > 0) {
                card.addView(label("Barang", 13, MUTED));
                for (int j = 0; j < preview.length(); j++) {
                    JSONObject detail = preview.optJSONObject(j);
                    card.addView(label("- " + detail.optString("nama_barang") + "  x " + qty(detail.optDouble("jumlah")) + " " + detail.optString("satuan"), 13, TEXT));
                }
            }
            LinearLayout actions = row(8);
            Button approve = primaryButton(sectionHead ? "Verifikasi" : "Approve");
            Button reject = dangerButton(sectionHead ? "Tolak" : "Reject");
            actions.addView(approve, new LinearLayout.LayoutParams(0, dp(42), 1));
            actions.addView(reject, new LinearLayout.LayoutParams(0, dp(42), 1));
            card.addView(actions);
            int id = item.optInt("id");
            approve.setOnClickListener(v -> confirm(sectionHead ? "Verifikasi PB?" : "Approve PB?", sectionHead ? "PB akan dikirim ke Approval Level 1." : approvePbMessage(item), () -> approvePb(id)));
            reject.setOnClickListener(v -> promptReject("Alasan reject PB", reason -> rejectPb(id, reason)));
            root.addView(card);
        }
    }

    private void approvePb(int id) {
        postAction("/pb/" + id + "/approve", new JSONObject(), "PB berhasil diproses.", this::showPbList);
    }

    private void rejectPb(int id, String reason) {
        try {
            JSONObject body = new JSONObject();
            body.put("alasan", reason);
            postAction("/pb/" + id + "/reject", body, "PB berhasil ditolak.", this::showPbList);
        } catch (Exception ignored) {
        }
    }

    private void showWoList() {
        if (!"approval".equals(role())) {
            toast("Approval L2 hanya menangani PB > 10 juta.");
            return;
        }
        setLoadingScreen("Review WO", "Memuat work order...");
        executor.execute(() -> {
            try {
                ApiResponse response = get("/wo");
                if (!response.ok) {
                    runOnUiThread(() -> handleAuthError(response));
                    return;
                }
                JSONArray data = response.json.getJSONArray("data");
                runOnUiThread(() -> renderWoList(data));
            } catch (Exception e) {
                runOnUiThread(() -> toast("Gagal memuat WO."));
            }
        });
    }

    private void renderWoList(JSONArray items) {
        baseListScreen("Review WO", "Approve WO dan assign ke pelaksana.");
        if (items.length() == 0) {
            root.addView(empty("Tidak ada WO menunggu approval."));
            return;
        }

        for (int i = 0; i < items.length(); i++) {
            JSONObject item = items.optJSONObject(i);
            LinearLayout card = card(12);
            card.addView(title(item.optString("nomor"), 15, TEXT));
            card.addView(pill("Submitted", Color.rgb(147, 51, 234)));
            card.addView(label(item.optString("judul"), 13, TEXT));
            card.addView(label(item.optString("created_by_name", "-"), 12, MUTED));
            LinearLayout actions = row(8);
            Button approve = primaryButton("Approve + Assign");
            Button reject = dangerButton("Reject");
            actions.addView(approve, new LinearLayout.LayoutParams(0, dp(42), 1));
            actions.addView(reject, new LinearLayout.LayoutParams(0, dp(42), 1));
            card.addView(actions);
            int id = item.optInt("id");
            card.setOnClickListener(v -> showWoDetail(item, false));
            approve.setOnClickListener(v -> pickPelaksana(id));
            reject.setOnClickListener(v -> promptReject("Alasan reject WO", reason -> rejectWo(id, reason)));
            root.addView(card);
        }
    }

    private void showSectionWoList() {
        if (!"section_head".equals(role())) {
            toast("Menu ini hanya untuk Section Head.");
            return;
        }
        setLoadingScreen("WO Assigned", "Daftar pekerjaan yang perlu diselesaikan.");
        executor.execute(() -> {
            try {
                ApiResponse response = get("/section/work-orders");
                if (!response.ok) {
                    runOnUiThread(() -> handleAuthError(response));
                    return;
                }
                JSONArray data = response.json.getJSONArray("data");
                runOnUiThread(() -> renderSectionWoList(data));
            } catch (Exception e) {
                runOnUiThread(() -> toast("Gagal memuat WO assigned."));
            }
        });
    }

    private void renderSectionWoList(JSONArray items) {
        baseListScreen("WO Assigned", "Upload foto hasil pekerjaan untuk menyelesaikan WO.");
        currentScreen = "section";
        final String[] fromDate = {""};
        final String[] toDate = {""};

        LinearLayout filters = card(8);
        filters.addView(label("Filter tanggal assign", 12, MUTED));
        LinearLayout range = row(8);
        Button from = secondaryButton("Dari");
        Button to = secondaryButton("Sampai");
        Button reset = ghostButton("Reset");
        range.addView(from, new LinearLayout.LayoutParams(0, dp(42), 1));
        range.addView(to, new LinearLayout.LayoutParams(0, dp(42), 1));
        range.addView(reset, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, dp(42)));
        filters.addView(range);
        root.addView(filters);

        LinearLayout list = column(12);
        root.addView(list);
        Runnable refresh = () -> renderSectionWoItems(items, list, fromDate[0], toDate[0]);
        from.setOnClickListener(v -> pickHistoryDate("Dari tanggal", fromDate, value -> {
            from.setText(value);
            refresh.run();
        }));
        to.setOnClickListener(v -> pickHistoryDate("Sampai tanggal", toDate, value -> {
            to.setText(value);
            refresh.run();
        }));
        reset.setOnClickListener(v -> {
            fromDate[0] = "";
            toDate[0] = "";
            from.setText("Dari");
            to.setText("Sampai");
            refresh.run();
        });
        refresh.run();
    }

    private void renderSectionWoItems(JSONArray items, LinearLayout list, String fromDate, String toDate) {
        list.removeAllViews();
        if (items.length() == 0) {
            list.addView(empty("Tidak ada WO assigned."));
            return;
        }

        int shown = 0;
        for (int i = 0; i < items.length(); i++) {
            JSONObject item = items.optJSONObject(i);
            if (item == null) {
                continue;
            }
            String dateKey = compactDate(item.optString("assigned_at", item.optString("approved_at", item.optString("created_at", ""))));
            if (!fromDate.isEmpty() && (dateKey.isEmpty() || dateKey.compareTo(fromDate) < 0)) {
                continue;
            }
            if (!toDate.isEmpty() && (dateKey.isEmpty() || dateKey.compareTo(toDate) > 0)) {
                continue;
            }
            LinearLayout card = card(12);
            card.addView(title(item.optString("nomor"), 15, TEXT));
            String progressStatus = item.optString("progress_status", "open");
            card.addView(pill(statusLabel(progressStatus), statusColor(progressStatus)));
            card.addView(label(item.optString("judul"), 13, TEXT));
            String desc = item.optString("deskripsi", "");
            if (!desc.isEmpty() && !"null".equals(desc)) {
                card.addView(label(desc, 12, MUTED));
            }
            String delegationNotes = item.optString("delegation_notes", "");
            if (!delegationNotes.isEmpty() && !"null".equals(delegationNotes)) {
                card.addView(label("Catatan delegasi: " + delegationNotes, 12, MUTED));
            }
            int id = item.optInt("id");
            int photoCount = item.optInt("photo_count", 0);
            if ("progress".equalsIgnoreCase(progressStatus)) {
                card.addView(label("Foto tersimpan: " + photoCount, 12, MUTED));

                LinearLayout actions = new LinearLayout(this);
                actions.setOrientation(LinearLayout.VERTICAL);
                actions.setPadding(0, dp(8), 0, 0);

                Button upload = primaryButton("Upload Foto Hasil");
                actions.addView(upload, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(42)));
                upload.setOnClickListener(v -> pickDonePhotos(id));

                Button done = photoCount > 0 ? primaryButton("Done") : secondaryButton("Done");
                LinearLayout.LayoutParams doneParams = new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(42));
                doneParams.topMargin = dp(8);
                actions.addView(done, doneParams);
                done.setOnClickListener(v -> completeWorkOrder(id, photoCount));

                card.addView(actions);
            } else {
                Button progress = primaryButton("Mulai Progress");
                card.addView(progress, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(42)));
                progress.setOnClickListener(v -> startWoProgress(id));
            }
            card.setOnClickListener(v -> showWoDetail(item, true));
            list.addView(card);
            shown++;
        }

        if (shown == 0) {
            list.addView(empty("Tidak ada WO pada range tanggal ini."));
        }
    }

    private void showWoDetail(JSONObject item, boolean sectionMode) {
        showWoDetail(item, sectionMode, null);
    }

    private void showWoDetail(JSONObject item, boolean sectionMode, Runnable backAction) {
        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setBackgroundColor(BG);
        root = column(12);
        root.setPadding(dp(18), topSafePadding(), dp(18), dp(22));
        scroll.addView(root);

        LinearLayout top = row(8);
        Button back = ghostButton("< Kembali");
        top.addView(back, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, dp(40)));
        TextView heading = title("Detail WO", 21, TEXT);
        heading.setGravity(Gravity.RIGHT | Gravity.CENTER_VERTICAL);
        top.addView(heading, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        root.addView(top);
        back.setOnClickListener(v -> {
            if (backAction != null) {
                backAction.run();
                return;
            }
            if (sectionMode) {
                showSectionWoList();
            } else {
                showWoList();
            }
        });

        LinearLayout detail = card(12);
        detail.addView(title(item.optString("nomor", "-"), 17, TEXT));
        detail.addView(pill(statusLabel(item.optString("progress_status", item.optString("status", "-"))), statusColor(item.optString("progress_status", item.optString("status", "-")))));
        detail.addView(label(item.optString("judul", "-"), 14, TEXT));
        String desc = item.optString("deskripsi", "");
        if (!desc.isEmpty() && !"null".equals(desc)) {
            detail.addView(label(desc, 13, MUTED));
        }
        String creator = item.optString("created_by_name", "");
        if (!creator.isEmpty() && !"null".equals(creator)) {
            detail.addView(label("Dibuat oleh: " + creator, 12, MUTED));
        }
        String assignedRegu = item.optString("assigned_regu", "");
        if (!assignedRegu.isEmpty() && !"null".equals(assignedRegu)) {
            detail.addView(label("Pelaksana: " + assignedRegu, 12, MUTED));
        }
        String assignedAt = item.optString("assigned_at", "");
        if (!assignedAt.isEmpty() && !"null".equals(assignedAt)) {
            detail.addView(label("Assign: " + assignedAt, 12, MUTED));
        }
        String delegationNotes = item.optString("delegation_notes", "");
        if (!delegationNotes.isEmpty() && !"null".equals(delegationNotes)) {
            LinearLayout noteBox = card(8);
            noteBox.addView(label("Catatan Delegasi", 12, MUTED));
            noteBox.addView(label(delegationNotes, 13, TEXT));
            detail.addView(noteBox);
        }
        String progressNotes = item.optString("progress_notes", "");
        if (!progressNotes.isEmpty() && !"null".equals(progressNotes)) {
            LinearLayout doneBox = card(8);
            doneBox.addView(label("Deskripsi Pekerjaan Selesai", 12, MUTED));
            doneBox.addView(label(progressNotes, 13, TEXT));
            detail.addView(doneBox);
        }

        String fileName = item.optString("file_name", "");
        if (!fileName.isEmpty() && !"null".equals(fileName)) {
            LinearLayout fileBox = card(8);
            fileBox.addView(label("Lampiran WO", 12, MUTED));
            fileBox.addView(title(fileName, 13, TEXT));
            Button preview = secondaryButton(isImageFile(fileName) ? "Preview Gambar" : "Lihat File");
            fileBox.addView(preview, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(42)));
            preview.setOnClickListener(v -> previewWorkOrderDocument(item.optInt("id"), fileName));
            detail.addView(fileBox);
        }

        JSONArray photos = item.optJSONArray("photos");
        if (photos != null && photos.length() > 0) {
            LinearLayout photosBox = card(8);
            photosBox.addView(label("Foto hasil pekerjaan", 12, MUTED));
            for (int i = 0; i < photos.length(); i++) {
                JSONObject photo = photos.optJSONObject(i);
                if (photo == null) {
                    continue;
                }
                LinearLayout photoItem = column(6);
                photoItem.setPadding(dp(10), dp(10), dp(10), dp(10));
                android.graphics.drawable.GradientDrawable photoBg = new android.graphics.drawable.GradientDrawable();
                photoBg.setColor(SURFACE);
                photoBg.setCornerRadius(dp(10));
                photoBg.setStroke(1, BORDER);
                photoItem.setBackground(photoBg);
                photoItem.addView(title("Foto " + (i + 1), 13, TEXT));
                String photoFileName = photo.optString("file_name", "");
                if (!photoFileName.isEmpty() && !"null".equals(photoFileName)) {
                    photoItem.addView(label(photoFileName, 11, MUTED));
                }
                String uploadedBy = photo.optString("uploaded_by_name", "");
                String createdAt = photo.optString("created_at", "");
                if (!uploadedBy.isEmpty() || !createdAt.isEmpty()) {
                    photoItem.addView(label((uploadedBy.isEmpty() ? "-" : uploadedBy) + (createdAt.isEmpty() ? "" : " - " + createdAt), 11, MUTED));
                }
                Button viewPhoto = secondaryButton("Lihat Foto");
                String url = photo.optString("url", "");
                viewPhoto.setOnClickListener(v -> previewRemoteImage(url, photo.optString("file_name", "Foto hasil")));
                photoItem.addView(viewPhoto, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(42)));
                photosBox.addView(photoItem);
            }
            detail.addView(photosBox);
        }

        if (sectionMode) {
            String progressStatus = item.optString("progress_status", "open");
            int id = item.optInt("id");
            int photoCount = item.optInt("photo_count", photos == null ? 0 : photos.length());
            if ("progress".equalsIgnoreCase(progressStatus)) {
                Button upload = primaryButton("Upload Foto Hasil");
                detail.addView(upload, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(42)));
                upload.setOnClickListener(v -> pickDonePhotos(id));
                Button done = photoCount > 0 ? primaryButton("Done") : secondaryButton("Done");
                detail.addView(done, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(42)));
                done.setOnClickListener(v -> completeWorkOrder(id, photoCount));
            } else if ("open".equalsIgnoreCase(progressStatus)) {
                Button progress = primaryButton("Mulai Progress");
                detail.addView(progress, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(42)));
                progress.setOnClickListener(v -> startWoProgress(id));
            }
        } else if ("approval".equals(role()) && "submitted".equalsIgnoreCase(item.optString("status", "submitted"))) {
            LinearLayout actions = row(8);
            Button approve = primaryButton("Approve + Assign");
            Button reject = dangerButton("Reject");
            int id = item.optInt("id");
            actions.addView(approve, new LinearLayout.LayoutParams(0, dp(42), 1));
            actions.addView(reject, new LinearLayout.LayoutParams(0, dp(42), 1));
            detail.addView(actions);
            approve.setOnClickListener(v -> pickPelaksana(id));
            reject.setOnClickListener(v -> promptReject("Alasan reject WO", reason -> rejectWo(id, reason)));
        }

        root.addView(detail);
        currentScreen = sectionMode ? "section" : "wo";
        setContentWithNav(scroll, "home");
    }

    private void previewWorkOrderDocument(int id, String fileName) {
        if (!isImageFile(fileName)) {
            toast("Preview mobile baru tersedia untuk file gambar.");
            return;
        }
        showImageDialog(fileName, image -> loadProtectedImage("/wo/" + id + "/document", image));
    }

    private void previewRemoteImage(String url, String title) {
        if (url == null || url.isEmpty()) {
            toast("URL foto tidak tersedia.");
            return;
        }
        showImageDialog(title, image -> loadPublicImage(url, image));
    }

    private interface ImageLoader {
        void load(ImageView image);
    }

    private void showImageDialog(String titleText, ImageLoader loader) {
        LinearLayout box = column(10);
        box.setPadding(dp(4), dp(4), dp(4), dp(4));
        TextView caption = title(titleText, 14, TEXT);
        ImageView image = new ImageView(this);
        image.setAdjustViewBounds(true);
        image.setScaleType(ImageView.ScaleType.FIT_CENTER);
        image.setMinimumHeight(dp(260));
        box.addView(caption);
        box.addView(image, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(360)));
        AlertDialog dialog = new AlertDialog.Builder(this)
                .setView(box)
                .setPositiveButton("Tutup", null)
                .create();
        dialog.setOnShowListener(d -> loader.load(image));
        dialog.show();
    }

    private void loadProtectedImage(String path, ImageView image) {
        loadBitmap(baseUrl() + path, true, image);
    }

    private void loadPublicImage(String url, ImageView image) {
        String resolved = url.startsWith("http") ? url : serverOrigin() + url;
        resolved = resolved.replace("http://10.10.31.40:8002", serverOrigin());
        resolved = resolved.replace("http://10.10.31.40:8181", serverOrigin());
        loadBitmap(resolved, false, image);
    }

    private void showPbHistoryDetail(JSONObject item) {
        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setBackgroundColor(BG);
        root = column(12);
        root.setPadding(dp(18), topSafePadding(), dp(18), dp(22));
        scroll.addView(root);

        LinearLayout top = row(8);
        Button back = ghostButton("< Kembali");
        top.addView(back, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, dp(40)));
        TextView heading = title("Detail PB", 21, TEXT);
        heading.setGravity(Gravity.RIGHT | Gravity.CENTER_VERTICAL);
        top.addView(heading, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        root.addView(top);
        back.setOnClickListener(v -> showHistory());

        LinearLayout detail = card(12);
        detail.addView(title(item.optString("nomor_pb", "-"), 17, TEXT));
        detail.addView(pill(statusLabel(item.optString("status", "-")), statusColor(item.optString("status", "-"))));

        String tujuan = item.optString("tujuan_nama", "");
        String jenis = item.optString("jenis_pekerjaan", "");
        if (!tujuan.isEmpty() || !jenis.isEmpty()) {
            detail.addView(label((tujuan.isEmpty() ? "-" : tujuan) + (jenis.isEmpty() ? "" : " - " + jenis), 14, TEXT));
        }
        if (item.has("total_value")) {
            detail.addView(label("Total: " + rupiah(item.optDouble("total_value")), 13, item.optBoolean("is_high_value") ? ORANGE : MUTED));
        }
        String tanggal = item.optString("tanggal_permintaan", item.optString("created_at", ""));
        if (!tanggal.isEmpty() && !"null".equals(tanggal)) {
            detail.addView(label("Tanggal PB: " + tanggal, 12, MUTED));
        }
        String approvedAt = item.optString("approved_at", "");
        if (!approvedAt.isEmpty() && !"null".equals(approvedAt)) {
            detail.addView(label("Approved: " + approvedAt, 12, GREEN));
        }
        String rejectedAt = item.optString("rejected_at", "");
        if (!rejectedAt.isEmpty() && !"null".equals(rejectedAt)) {
            detail.addView(label("Rejected: " + rejectedAt, 12, RED));
        }
        String reason = item.optString("rejection_reason", "");
        if (!reason.isEmpty() && !"null".equals(reason)) {
            detail.addView(label("Alasan reject: " + reason, 12, RED));
        }

        JSONArray details = item.optJSONArray("items");
        if (details != null && details.length() > 0) {
            LinearLayout itemBox = card(8);
            itemBox.addView(label("Daftar Barang", 12, MUTED));
            for (int i = 0; i < details.length(); i++) {
                JSONObject row = details.optJSONObject(i);
                if (row == null) {
                    continue;
                }
                itemBox.addView(label((i + 1) + ". " + row.optString("nama_barang") + " x " + qty(row.optDouble("jumlah")) + " " + row.optString("satuan"), 13, TEXT));
            }
            detail.addView(itemBox);
        }

        root.addView(detail);
        currentScreen = "history";
        setContentWithNav(scroll, "history");
    }

    private void loadBitmap(String url, boolean bearer, ImageView image) {
        executor.execute(() -> {
            try {
                HttpURLConnection conn = (HttpURLConnection) new URL(url).openConnection();
                conn.setConnectTimeout(12000);
                conn.setReadTimeout(20000);
                if (bearer && !token().isEmpty()) {
                    conn.setRequestProperty("Authorization", "Bearer " + token());
                }
                try (InputStream stream = conn.getInputStream()) {
                    Bitmap bitmap = BitmapFactory.decodeStream(stream);
                    runOnUiThread(() -> {
                        if (bitmap == null) {
                            toast("Gambar belum bisa ditampilkan.");
                        } else {
                            image.setImageBitmap(bitmap);
                        }
                    });
                }
            } catch (Exception e) {
                runOnUiThread(() -> toast("Gagal memuat gambar."));
            }
        });
    }

    private void startWoProgress(int woId) {
        confirm("Mulai Progress?", "Status WO akan berubah dari Open menjadi In Progress.", () -> {
            try {
                postAction("/section/work-orders/" + woId + "/progress", new JSONObject(), "WO masuk In Progress.", this::showSectionWoList);
            } catch (Exception ignored) {
            }
        });
    }

    private void pickDonePhotos(int woId) {
        pendingDoneWoId = woId;
        pendingCameraUri = null;
        pendingCameraUris.clear();
        new AlertDialog.Builder(this)
                .setTitle("Foto Hasil Pekerjaan")
                .setItems(new String[]{"Ambil dari Kamera", "Pilih dari Galeri"}, (dialog, which) -> {
                    if (which == 0) {
                        openCameraForDone();
                    } else {
                        openGalleryForDone();
                    }
                })
                .setNegativeButton("Batal", (dialog, which) -> {
                    pendingDoneWoId = -1;
                    pendingCameraUri = null;
                    pendingCameraUris.clear();
                })
                .show();
    }

    private void openGalleryForDone() {
        if (pendingDoneWoId <= 0) {
            toast("WO belum dipilih.");
            return;
        }
        Intent intent = new Intent(Intent.ACTION_OPEN_DOCUMENT);
        intent.addCategory(Intent.CATEGORY_OPENABLE);
        intent.setType("image/*");
        intent.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
        startActivityForResult(intent, REQ_PICK_PHOTOS);
    }

    private void openCameraForDone() {
        if (pendingDoneWoId <= 0) {
            toast("WO belum dipilih.");
            return;
        }
        Intent intent = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
        if (intent.resolveActivity(getPackageManager()) == null) {
            toast("Aplikasi kamera tidak tersedia.");
            return;
        }
        try {
            pendingCameraUri = createCameraImageUri();
            intent.putExtra(MediaStore.EXTRA_OUTPUT, pendingCameraUri);
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_GRANT_WRITE_URI_PERMISSION);
            startActivityForResult(intent, REQ_CAPTURE_PHOTO);
        } catch (IOException e) {
            toast("Gagal menyiapkan file foto.");
        }
    }

    private Uri createCameraImageUri() throws IOException {
        File dir = new File(getExternalCacheDir(), "camera");
        if (!dir.exists() && !dir.mkdirs()) {
            throw new IOException("Cannot create camera cache directory");
        }
        String stamp = new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.US).format(new Date());
        File file = File.createTempFile("WO_" + stamp + "_", ".jpg", dir);
        return FileProvider.getUriForFile(this, getPackageName() + ".fileprovider", file);
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == REQ_WEB_FILE) {
            handleWebFileResult(resultCode, data);
            return;
        }
        if (pendingDoneWoId <= 0) {
            return;
        }
        if (requestCode == REQ_CAPTURE_PHOTO) {
            handleCameraResult(resultCode);
            return;
        }
        if (requestCode != REQ_PICK_PHOTOS || resultCode != RESULT_OK || data == null) {
            return;
        }

        ArrayList<Uri> uris = new ArrayList<>();
        if (data.getClipData() != null) {
            for (int i = 0; i < data.getClipData().getItemCount(); i++) {
                uris.add(data.getClipData().getItemAt(i).getUri());
            }
        } else if (data.getData() != null) {
            uris.add(data.getData());
        }

        if (uris.isEmpty()) {
            toast("Minimal 1 foto wajib dipilih.");
            return;
        }

        confirmDonePhotos(uris);
    }

    private void handleWebFileResult(int resultCode, Intent data) {
        if (webFileCallback == null) {
            return;
        }

        ArrayList<Uri> selected = new ArrayList<>();
        if (resultCode == RESULT_OK && data != null) {
            if (data.getClipData() != null) {
                for (int i = 0; i < data.getClipData().getItemCount(); i++) {
                    selected.add(data.getClipData().getItemAt(i).getUri());
                }
            } else if (data.getData() != null) {
                selected.add(data.getData());
            }
        }
        if (resultCode == RESULT_OK && selected.isEmpty() && pendingWebCameraUri != null) {
            selected.add(pendingWebCameraUri);
        }

        Uri[] result = selected.isEmpty() ? null : selected.toArray(new Uri[0]);
        webFileCallback.onReceiveValue(result);
        webFileCallback = null;
        pendingWebCameraUri = null;
    }

    private void handleCameraResult(int resultCode) {
        if (resultCode != RESULT_OK || pendingCameraUri == null) {
            if (pendingCameraUris.isEmpty()) {
                pendingDoneWoId = -1;
            }
            pendingCameraUri = null;
            return;
        }
        pendingCameraUris.add(pendingCameraUri);
        pendingCameraUri = null;
        new AlertDialog.Builder(this)
                .setTitle("Foto ditambahkan")
                .setMessage("Sudah ada " + pendingCameraUris.size() + " foto. Mau tambah foto lagi?")
                .setPositiveButton("Tambah Foto", (dialog, which) -> openCameraForDone())
                .setNegativeButton("Upload Foto", (dialog, which) -> {
                    ArrayList<Uri> uris = new ArrayList<>(pendingCameraUris);
                    pendingCameraUris.clear();
                    confirmDonePhotos(uris);
                })
                .setNeutralButton("Batal", (dialog, which) -> {
                    pendingDoneWoId = -1;
                    pendingCameraUris.clear();
                })
                .show();
    }

    private void confirmDonePhotos(ArrayList<Uri> uris) {
        int woId = pendingDoneWoId;
        pendingDoneWoId = -1;
        new AlertDialog.Builder(this)
                .setTitle("Foto siap diupload")
                .setMessage(uris.size() + " foto hasil pekerjaan sudah dipilih.\n\nKlik Upload Foto untuk menyimpan. WO belum ditutup sampai tombol Done diklik.")
                .setPositiveButton("Upload Foto", (dialog, which) -> uploadDonePhotos(woId, uris))
                .setNegativeButton("Batal", null)
                .show();
    }

    private void uploadDonePhotos(int woId, ArrayList<Uri> uris) {
        executor.execute(() -> {
            try {
                ApiResponse response = multipartPost("/section/work-orders/" + woId + "/photos", uris);
                String msg = response.json == null ? "Foto berhasil diupload." : response.json.optString("message", "Foto berhasil diupload.");
                runOnUiThread(() -> {
                    toast(msg);
                    if (response.ok) {
                        showSectionWoList();
                    }
                });
            } catch (Exception e) {
                runOnUiThread(() -> toast("Gagal upload foto hasil pekerjaan."));
            }
        });
    }

    private void completeWorkOrder(int woId, int photoCount) {
        if (photoCount < 1) {
            toast("Upload minimal 1 foto dulu sebelum Done.");
            return;
        }

        promptRequiredText("Deskripsi Pekerjaan", "Jelaskan pekerjaan yang sudah dilakukan", "Done", notes -> confirm("Selesaikan WO?", "Pastikan semua foto hasil pekerjaan sudah diupload. Setelah Done, WO akan masuk status selesai.", () -> {
            try {
                JSONObject body = new JSONObject();
                body.put("notes", notes);
                postAction("/section/work-orders/" + woId + "/done", body, "WO selesai.", this::showSectionWoList);
            } catch (Exception ignored) {
            }
        }));
    }

    private void pickPelaksana(int id) {
        executor.execute(() -> {
            try {
                ApiResponse response = get("/pelaksana");
                JSONArray data = response.json.getJSONArray("data");
                ArrayList<String> names = new ArrayList<>();
                for (int i = 0; i < data.length(); i++) {
                    names.add(data.getJSONObject(i).optString("nama"));
                }
                runOnUiThread(() -> {
                    String[] options = names.toArray(new String[0]);
                    new AlertDialog.Builder(this)
                            .setTitle("Pilih Pelaksana")
                            .setItems(options, (dialog, which) -> promptRequiredText(
                                    "Catatan Delegasi",
                                    "Tulis instruksi untuk " + options[which],
                                    "Approve",
                                    notes -> approveWo(id, options[which], notes)
                            ))
                            .show();
                });
            } catch (Exception e) {
                runOnUiThread(() -> toast("Gagal memuat pelaksana."));
            }
        });
    }

    private void approveWo(int id, String pelaksana, String delegationNotes) {
        try {
            JSONObject body = new JSONObject();
            body.put("pelaksana", pelaksana);
            body.put("delegation_notes", delegationNotes);
            postAction("/wo/" + id + "/approve", body, "WO berhasil diapprove.", this::showWoList);
        } catch (Exception ignored) {
        }
    }

    private void rejectWo(int id, String reason) {
        try {
            JSONObject body = new JSONObject();
            body.put("rejection_notes", reason);
            postAction("/wo/" + id + "/reject", body, "WO berhasil ditolak.", this::showWoList);
        } catch (Exception ignored) {
        }
    }

    private void postAction(String path, JSONObject body, String successMessage, Runnable refresh) {
        executor.execute(() -> {
            try {
                ApiResponse response = post(path, body);
                String msg = response.json == null ? successMessage : response.json.optString("message", successMessage);
                runOnUiThread(() -> {
                    toast(msg);
                    if (response.ok) {
                        refresh.run();
                    }
                });
            } catch (Exception e) {
                runOnUiThread(() -> toast("Aksi gagal diproses."));
            }
        });
    }

    private void startPolling() {
        stopPolling();
        poller = new Runnable() {
            @Override
            public void run() {
                if (!token().isEmpty()) {
                    executor.execute(() -> {
                        try {
                            ApiResponse response = get("/notifications");
                            if (response.ok) {
                                int count = response.json.optInt("count", 0);
                                if (lastNotificationCount >= 0 && count > lastNotificationCount) {
                                    String latestType = "PB";
                                    JSONArray items = response.json.optJSONArray("items");
                                    if (items != null && items.length() > 0) {
                                        JSONObject latest = items.optJSONObject(0);
                                        if (latest != null) {
                                            latestType = latest.optString("type", latestType);
                                        }
                                    }
                                    showLocalNotification(count, latestType);
                                }
                                lastNotificationCount = count;
                            }
                        } catch (Exception ignored) {
                        }
                    });
                    handler.postDelayed(this, 8000);
                }
            }
        };
        handler.post(poller);
    }

    private void registerFcmToken() {
        FirebaseMessaging.getInstance().getToken()
                .addOnSuccessListener(token -> {
                    if (token == null || token.isEmpty() || token().isEmpty()) {
                        return;
                    }
                    executor.execute(() -> {
                        try {
                            JSONObject body = new JSONObject();
                            body.put("fcm_token", token);
                            body.put("platform", "android");
                            post("/device-token", body);
                        } catch (Exception ignored) {
                        }
                    });
                });
    }

    private void stopPolling() {
        if (poller != null) {
            handler.removeCallbacks(poller);
            poller = null;
        }
    }

    private void showLocalNotification(int count, String latestType) {
        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager == null) {
            return;
        }
        if (Build.VERSION.SDK_INT >= 33 && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            return;
        }
        android.app.Notification.Builder builder = Build.VERSION.SDK_INT >= 26
                ? new android.app.Notification.Builder(this, APPROVAL_CHANNEL_ID)
                : new android.app.Notification.Builder(this);

        Intent intent = new Intent(this, MainActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        if ("section_head".equals(role())) {
            if ("PB".equalsIgnoreCase(latestType)) {
                intent.putExtra("target", "pb_verification");
                intent.putExtra("type", "PB");
                intent.putExtra("sound_type", "approval");
            } else {
                intent.putExtra("target", "section_wo");
                intent.putExtra("type", "WO");
                intent.putExtra("sound_type", "work_order");
            }
        } else if ("WO".equalsIgnoreCase(latestType)) {
            intent.putExtra("target", "approval_wo");
            intent.putExtra("type", "WO");
        } else if ("approval".equals(role())) {
            intent.putExtra("target", "approval");
            intent.putExtra("type", "PB");
        } else {
            intent.putExtra("target", "approval");
            intent.putExtra("type", "PB");
        }
        int flags = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= 23) {
            flags |= PendingIntent.FLAG_IMMUTABLE;
        }
        PendingIntent pendingIntent = PendingIntent.getActivity(this, 1001, intent, flags);

        builder.setSmallIcon(R.drawable.ic_notification)
                .setContentTitle("e-Request")
                .setContentText(count + " request menunggu approval")
                .setContentIntent(pendingIntent)
                .setAutoCancel(true);
        manager.notify(1001, builder.build());
    }

    private void doLogout() {
        executor.execute(() -> {
            try {
                post("/logout", new JSONObject());
            } catch (Exception ignored) {
            }
            prefs.edit().clear().apply();
            runOnUiThread(this::showLogin);
        });
    }

    private ApiResponse get(String path) throws Exception {
        return request("GET", path, null);
    }

    private ApiResponse post(String path, JSONObject body) throws Exception {
        return request("POST", path, body);
    }

    private ApiResponse multipartPost(String path, ArrayList<Uri> photos) throws Exception {
        String boundary = "ERequestBoundary" + System.currentTimeMillis();
        URL url = new URL(baseUrl() + path);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("POST");
        conn.setConnectTimeout(20000);
        conn.setReadTimeout(30000);
        conn.setDoOutput(true);
        conn.setRequestProperty("Accept", "application/json");
        conn.setRequestProperty("Content-Type", "multipart/form-data; boundary=" + boundary);
        if (!token().isEmpty()) {
            conn.setRequestProperty("Authorization", "Bearer " + token());
        }

        try (OutputStream out = conn.getOutputStream()) {
            writeFormField(out, boundary, "notes", "Upload foto hasil pekerjaan dari mobile app");
            for (int i = 0; i < photos.size(); i++) {
                Uri uri = photos.get(i);
                byte[] bytes = readBytes(uri);
                String fileName = "wo-photo-" + System.currentTimeMillis() + "-" + (i + 1) + ".jpg";
                writeFileField(out, boundary, "photos[]", fileName, "image/jpeg", bytes);
            }
            out.write(("--" + boundary + "--\r\n").getBytes(StandardCharsets.UTF_8));
        }

        int code = conn.getResponseCode();
        InputStream stream = code >= 200 && code < 400 ? conn.getInputStream() : conn.getErrorStream();
        String raw = readAll(stream);
        JSONObject json = raw.isEmpty() ? new JSONObject() : new JSONObject(raw);
        return new ApiResponse(code >= 200 && code < 300, code, json);
    }

    private void writeFormField(OutputStream out, String boundary, String name, String value) throws Exception {
        out.write(("--" + boundary + "\r\n").getBytes(StandardCharsets.UTF_8));
        out.write(("Content-Disposition: form-data; name=\"" + name + "\"\r\n\r\n").getBytes(StandardCharsets.UTF_8));
        out.write(value.getBytes(StandardCharsets.UTF_8));
        out.write("\r\n".getBytes(StandardCharsets.UTF_8));
    }

    private void writeFileField(OutputStream out, String boundary, String fieldName, String fileName, String contentType, byte[] bytes) throws Exception {
        out.write(("--" + boundary + "\r\n").getBytes(StandardCharsets.UTF_8));
        out.write(("Content-Disposition: form-data; name=\"" + fieldName + "\"; filename=\"" + fileName + "\"\r\n").getBytes(StandardCharsets.UTF_8));
        out.write(("Content-Type: " + contentType + "\r\n\r\n").getBytes(StandardCharsets.UTF_8));
        out.write(bytes);
        out.write("\r\n".getBytes(StandardCharsets.UTF_8));
    }

    private byte[] readBytes(Uri uri) throws Exception {
        try (InputStream input = getContentResolver().openInputStream(uri);
             ByteArrayOutputStream buffer = new ByteArrayOutputStream()) {
            if (input == null) {
                return new byte[0];
            }
            byte[] chunk = new byte[8192];
            int read;
            while ((read = input.read(chunk)) != -1) {
                buffer.write(chunk, 0, read);
            }
            return buffer.toByteArray();
        }
    }

    private ApiResponse request(String method, String path, JSONObject body) throws Exception {
        URL url = new URL(baseUrl() + path);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod(method);
        conn.setConnectTimeout(12000);
        conn.setReadTimeout(12000);
        conn.setRequestProperty("Accept", "application/json");
        conn.setRequestProperty("Content-Type", "application/json");
        if (!token().isEmpty()) {
            conn.setRequestProperty("Authorization", "Bearer " + token());
        }
        if (body != null) {
            conn.setDoOutput(true);
            byte[] bytes = body.toString().getBytes(StandardCharsets.UTF_8);
            try (OutputStream out = conn.getOutputStream()) {
                out.write(bytes);
            }
        }

        int code = conn.getResponseCode();
        InputStream stream = code >= 200 && code < 400 ? conn.getInputStream() : conn.getErrorStream();
        String raw = readAll(stream);
        JSONObject json = raw.isEmpty() ? new JSONObject() : new JSONObject(raw);
        return new ApiResponse(code >= 200 && code < 300, code, json);
    }

    private String readAll(InputStream stream) throws Exception {
        if (stream == null) {
            return "";
        }
        StringBuilder sb = new StringBuilder();
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(stream, StandardCharsets.UTF_8))) {
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line);
            }
        }
        return sb.toString();
    }

    private void handleAuthError(ApiResponse response) {
        if (response.status == 401) {
            prefs.edit().clear().apply();
            showLogin();
        } else {
            toast(response.json == null ? "Request gagal." : response.json.optString("message", "Request gagal."));
        }
    }

    private void handleLaunchIntent(Intent intent) {
        if (intent == null || token().isEmpty()) {
            return;
        }
        String target = intent.getStringExtra("target");
        String type = intent.getStringExtra("type");
        String soundType = intent.getStringExtra("sound_type");
        if ("section_head".equals(role()) && ("pb_verification".equals(target) || "PB".equalsIgnoreCase(type))) {
            handler.postDelayed(this::showPbList, 250);
            intent.removeExtra("target");
            intent.removeExtra("type");
            intent.removeExtra("sound_type");
            return;
        }
        if ("section_wo".equals(target) || "work_order".equals(soundType)) {
            if ("section_head".equals(role())) {
                handler.postDelayed(this::showSectionWoList, 250);
            } else {
                handler.postDelayed(this::showWoList, 250);
            }
            intent.removeExtra("target");
            intent.removeExtra("type");
            intent.removeExtra("sound_type");
            return;
        }
        if ("WO".equalsIgnoreCase(type)) {
            handler.postDelayed(this::showWoList, 250);
            intent.removeExtra("type");
            return;
        }
        if ("PB".equalsIgnoreCase(type) || "approval".equals(target)) {
            handler.postDelayed(this::showPbList, 250);
            intent.removeExtra("target");
            intent.removeExtra("type");
        }
    }

    private void setLoadingScreen(String heading, String message) {
        baseListScreen(heading, message);
        root.addView(label("Loading...", 14, MUTED));
    }

    private void baseListScreen(String heading, String subtitle) {
        String lower = heading.toLowerCase(Locale.ROOT);
        currentScreen = lower.contains("history") ? "history" : (lower.contains("wo") ? "wo" : "pb");
        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setBackgroundColor(BG);
        root = column(12);
        root.setPadding(dp(18), topSafePadding(), dp(18), dp(22));
        scroll.addView(root);
        LinearLayout top = row(8);
        Button back = ghostButton("< Kembali");
        top.addView(back, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, dp(40)));
        back.setOnClickListener(v -> showDashboard());
        TextView title = title(heading, 21, TEXT);
        title.setGravity(Gravity.RIGHT | Gravity.CENTER_VERTICAL);
        top.addView(title, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        root.addView(top);
        root.addView(label(subtitle, 13, MUTED));
        setContentWithNav(scroll, "history".equals(currentScreen) ? "history" : "home");
    }

    private void setContentWithNav(View content, String activeTab) {
        LinearLayout page = new LinearLayout(this);
        page.setOrientation(LinearLayout.VERTICAL);
        page.setBackgroundColor(BG);
        page.addView(content, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 0, 1));
        page.addView(bottomNav(activeTab), new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(64) + navigationSafePadding()));
        setContentView(page);
        animateView(content);
    }

    private LinearLayout bottomNav(String activeTab) {
        LinearLayout nav = row(0);
        nav.setGravity(Gravity.CENTER);
        nav.setPadding(dp(14), dp(7), dp(14), dp(7) + navigationSafePadding());
        nav.setBackgroundColor(Color.WHITE);
        if (Build.VERSION.SDK_INT >= 21) {
            nav.setElevation(dp(8));
        }
        nav.addView(navButton("Home", "home".equals(activeTab), this::showDashboard), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.MATCH_PARENT, 1));
        nav.addView(navButton("History", "history".equals(activeTab), this::showHistory), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.MATCH_PARENT, 1));
        nav.addView(navButton("Profil", "profile".equals(activeTab), this::showProfile), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.MATCH_PARENT, 1));
        return nav;
    }

    private LinearLayout navButton(String text, boolean active, Runnable action) {
        LinearLayout button = column(2);
        button.setGravity(Gravity.CENTER);
        button.setPadding(dp(4), dp(5), dp(4), dp(5));
        ImageView icon = new ImageView(this);
        icon.setImageResource(navIconRes(text));
        icon.setColorFilter(active ? primaryColor() : MUTED);
        button.addView(icon, new LinearLayout.LayoutParams(dp(20), dp(20)));
        TextView caption = label(text, 10, active ? primaryColor() : MUTED);
        caption.setGravity(Gravity.CENTER);
        caption.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
        button.addView(caption);
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(active ? tint(primaryColor(), 18) : Color.WHITE);
        bg.setCornerRadius(dp(16));
        bg.setStroke(1, active ? tint(primaryColor(), 45) : Color.TRANSPARENT);
        button.setBackground(bg);
        button.setOnClickListener(v -> {
            if (!active) {
                action.run();
            }
        });
        return button;
    }

    private int navIconRes(String text) {
        if ("History".equals(text)) {
            return android.R.drawable.ic_menu_recent_history;
        }
        if ("Profil".equals(text)) {
            return android.R.drawable.ic_menu_myplaces;
        }
        return android.R.drawable.ic_menu_view;
    }

    private String navIconSafe(String text) {
        if ("History".equals(text)) {
            return "H";
        }
        if ("Profil".equals(text)) {
            return "P";
        }
        return "A";
    }

    private String navIcon(String text) {
        if ("History".equals(text)) {
            return "◷";
        }
        if ("Profil".equals(text)) {
            return "◯";
        }
        return "⌂";
    }

    private void animateView(View view) {
        view.setAlpha(0f);
        view.setTranslationY(dp(12));
        view.animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(220)
                .setInterpolator(new DecelerateInterpolator())
                .start();
    }

    private LinearLayout column(int gap) {
        LinearLayout layout = new LinearLayout(this);
        layout.setOrientation(LinearLayout.VERTICAL);
        layout.setShowDividers(LinearLayout.SHOW_DIVIDER_MIDDLE);
        layout.setDividerDrawable(new SpaceDrawable(dp(gap)));
        return layout;
    }

    private LinearLayout row(int gap) {
        LinearLayout layout = new LinearLayout(this);
        layout.setOrientation(LinearLayout.HORIZONTAL);
        layout.setGravity(Gravity.CENTER_VERTICAL);
        layout.setShowDividers(LinearLayout.SHOW_DIVIDER_MIDDLE);
        layout.setDividerDrawable(new SpaceDrawable(dp(gap)));
        return layout;
    }

    private LinearLayout card(int gap) {
        LinearLayout layout = column(gap);
        layout.setPadding(dp(16), dp(16), dp(16), dp(16));
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(Color.WHITE);
        bg.setCornerRadius(dp(12));
        bg.setStroke(1, BORDER);
        layout.setBackground(bg);
        if (Build.VERSION.SDK_INT >= 21) {
            layout.setElevation(dp(1));
        }
        return layout;
    }

    private LinearLayout darkPanel(int gap) {
        LinearLayout layout = column(gap);
        layout.setPadding(dp(18), dp(17), dp(18), dp(17));
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable(
                android.graphics.drawable.GradientDrawable.Orientation.LEFT_RIGHT,
                new int[]{navyColor(), accentColor()});
        bg.setCornerRadius(dp(14));
        layout.setBackground(bg);
        if (Build.VERSION.SDK_INT >= 21) {
            layout.setElevation(dp(2));
        }
        return layout;
    }

    private LinearLayout searchBanner(String text) {
        LinearLayout banner = row(10);
        banner.setPadding(dp(16), dp(10), dp(16), dp(10));
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(Color.WHITE);
        bg.setCornerRadius(dp(28));
        bg.setStroke(1, BORDER);
        banner.setBackground(bg);
        if (Build.VERSION.SDK_INT >= 21) {
            banner.setElevation(dp(1));
        }
        TextView icon = title("Search", 12, MUTED);
        banner.addView(icon);
        banner.addView(label(text, 14, MUTED));
        banner.setOnClickListener(v -> showHistory());
        return banner;
    }

    private void configureSystemBars() {
        if (Build.VERSION.SDK_INT >= 21) {
            getWindow().setStatusBarColor(BG);
            getWindow().setNavigationBarColor(Color.WHITE);
        }
        if (Build.VERSION.SDK_INT >= 23) {
            getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR);
        }
    }

    private int topSafePadding() {
        return dp(20) + systemBarHeight("status_bar_height");
    }

    private int navigationSafePadding() {
        return Math.max(dp(10), systemBarHeight("navigation_bar_height"));
    }

    private int systemBarHeight(String name) {
        int id = getResources().getIdentifier(name, "dimen", "android");
        return id > 0 ? getResources().getDimensionPixelSize(id) : 0;
    }

    private String greetingText() {
        int hour = java.util.Calendar.getInstance().get(java.util.Calendar.HOUR_OF_DAY);
        if (hour < 11) {
            return "Selamat pagi";
        }
        if (hour < 15) {
            return "Selamat siang";
        }
        if (hour < 19) {
            return "Selamat sore";
        }
        return "Selamat malam";
    }

    private String displayUsername() {
        String username = prefs.getString("username", "").trim();
        if (!username.isEmpty()) {
            return username;
        }

        String name = prefs.getString("name", "").trim();
        if (!name.isEmpty()) {
            return name;
        }

        return "user";
    }

    private String labelValue(String key, String fallback) {
        String value = prefs == null ? "" : prefs.getString(key, "");
        return value == null || value.trim().isEmpty() ? fallback : value.trim();
    }

    private int primaryColor() {
        return configColor("theme_primary_color", BLUE);
    }

    private int accentColor() {
        return configColor("theme_accent_color", TEAL);
    }

    private int navyColor() {
        return configColor("theme_navy_color", NAVY);
    }

    private int configColor(String key, int fallback) {
        if (prefs == null) {
            return fallback;
        }
        String value = prefs.getString(key, "");
        if (value == null || value.trim().isEmpty()) {
            return fallback;
        }
        try {
            return Color.parseColor(value.trim());
        } catch (Exception ignored) {
            return fallback;
        }
    }

    private int tint(int color, int alpha) {
        return Color.argb(alpha, Color.red(color), Color.green(color), Color.blue(color));
    }

    private String statusLabel(String status) {
        if ("closed".equalsIgnoreCase(status) || "completed".equalsIgnoreCase(status)) {
            return "Done";
        }
        if ("approved".equalsIgnoreCase(status)) {
            return "Approved";
        }
        if ("rejected".equalsIgnoreCase(status)) {
            return "Rejected";
        }
        if ("submitted".equalsIgnoreCase(status)) {
            return "Submitted";
        }
        if ("progress".equalsIgnoreCase(status)) {
            return "In Progress";
        }
        if ("open".equalsIgnoreCase(status)) {
            return "Open";
        }
        return status == null || status.isEmpty() ? "-" : status;
    }

    private int statusColor(String status) {
        if ("closed".equalsIgnoreCase(status) || "completed".equalsIgnoreCase(status) || "approved".equalsIgnoreCase(status)) {
            return GREEN;
        }
        if ("rejected".equalsIgnoreCase(status)) {
            return RED;
        }
        if ("submitted".equalsIgnoreCase(status) || "pending".equalsIgnoreCase(status)) {
            return ORANGE;
        }
        if ("open".equalsIgnoreCase(status)) {
            return MUTED;
        }
        return primaryColor();
    }

    private TextView pill(String text, int color) {
        TextView view = label(text, 12, color);
        view.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
        view.setPadding(dp(10), dp(5), dp(10), dp(5));
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(Color.argb(24, Color.red(color), Color.green(color), Color.blue(color)));
        bg.setCornerRadius(dp(20));
        bg.setStroke(1, Color.argb(55, Color.red(color), Color.green(color), Color.blue(color)));
        view.setBackground(bg);
        return view;
    }

    private TextView title(String text, int sp, int color) {
        TextView view = label(text, sp, color);
        view.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
        return view;
    }

    private TextView label(String text, int sp, int color) {
        TextView view = new TextView(this);
        view.setText(text);
        view.setTextSize(sp);
        view.setTextColor(color);
        view.setIncludeFontPadding(false);
        view.setLineSpacing(2, 1.05f);
        return view;
    }

    private TextView metric(String label, int value, int color) {
        TextView view = title(label + ": " + value, 16, color);
        view.setPadding(0, dp(4), 0, dp(4));
        return view;
    }

    private LinearLayout historyMetaRow(String labelText, String valueText) {
        LinearLayout row = row(6);
        TextView key = label(labelText, 12, MUTED);
        TextView value = label(valueText == null || valueText.isEmpty() ? "-" : valueText, 12, TEXT);
        value.setGravity(Gravity.RIGHT);
        value.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
        row.addView(key, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        row.addView(value, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        return row;
    }

    private View thinDivider() {
        View divider = new View(this);
        divider.setBackgroundColor(BORDER);
        divider.setLayoutParams(new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 1));
        return divider;
    }

    private LinearLayout metricCard(String label, int value, int color) {
        LinearLayout box = column(6);
        box.setPadding(dp(12), dp(12), dp(12), dp(12));
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(SURFACE);
        bg.setCornerRadius(dp(10));
        bg.setStroke(1, BORDER);
        box.setBackground(bg);
        box.addView(label(label, 11, MUTED));
        box.addView(title(String.valueOf(value), 22, color));
        return box;
    }

    private LinearLayout budgetSnapshotCard(JSONObject budget) {
        LinearLayout panel = card(12);
        panel.addView(title("Budget Snapshot", 17, TEXT));
        panel.addView(label("Ringkasan nilai PB untuk awareness approval.", 12, MUTED));
        panel.addView(budgetBlock("Final Approved", rupiah(budget.optDouble("total_used")), "L1 langsung + approved L2", GREEN));
        panel.addView(budgetBlock("Masih Menunggu", rupiah(budget.optDouble("waiting_l2")), "Sudah L1, belum L2", ORANGE));
        panel.addView(budgetBlock("Tidak Disetujui", rupiah(budget.optDouble("rejected")), "Rejected PB", RED));
        return panel;
    }

    private LinearLayout budgetBlock(String titleText, String amount, String caption, int color) {
        LinearLayout box = column(6);
        box.setPadding(dp(14), dp(12), dp(14), dp(12));
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(Color.argb(18, Color.red(color), Color.green(color), Color.blue(color)));
        bg.setCornerRadius(dp(12));
        bg.setStroke(1, Color.argb(45, Color.red(color), Color.green(color), Color.blue(color)));
        box.setBackground(bg);
        TextView heading = label(titleText.toUpperCase(Locale.US), 11, color);
        heading.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
        box.addView(heading);
        box.addView(title(amount, 16, TEXT));
        box.addView(label(caption, 11, color));
        return box;
    }

    private LinearLayout sectionHeadBudgetCard(JSONArray rows) {
        LinearLayout panel = card(12);
        panel.addView(title("Budget per Section Head", 17, TEXT));
        panel.addView(label("Pemakaian budget final approved berdasarkan verifikator PB.", 12, MUTED));

        if (rows.length() == 0) {
            panel.addView(empty("Belum ada budget final approved."));
            return panel;
        }

        for (int i = 0; i < rows.length(); i++) {
            JSONObject item = rows.optJSONObject(i);
            if (item == null) {
                continue;
            }
            if (i > 0) {
                panel.addView(thinDivider());
            }
            panel.addView(sectionHeadBudgetRow(item));
        }

        return panel;
    }

    private LinearLayout sectionHeadBudgetRow(JSONObject item) {
        LinearLayout wrap = column(7);
        String name = item.optString("name", "Section Head");
        int pbCount = item.optInt("pb_count", 0);
        double amount = item.optDouble("amount", 0);
        double percent = item.optDouble("percent", 0);

        LinearLayout top = row(8);
        LinearLayout text = column(3);
        text.addView(title(name, 13, TEXT));
        text.addView(label(pbCount + " PB final approved", 11, MUTED));
        TextView value = title(rupiah(amount), 13, primaryColor());
        value.setGravity(Gravity.RIGHT);
        top.addView(text, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        top.addView(value, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, LinearLayout.LayoutParams.WRAP_CONTENT));
        wrap.addView(top);

        LinearLayout bar = new LinearLayout(this);
        bar.setOrientation(LinearLayout.HORIZONTAL);
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(Color.rgb(241, 245, 249));
        bg.setCornerRadius(dp(12));
        bar.setBackground(bg);
        View fill = new View(this);
        android.graphics.drawable.GradientDrawable fillBg = new android.graphics.drawable.GradientDrawable();
        fillBg.setColor(primaryColor());
        fillBg.setCornerRadius(dp(12));
        fill.setBackground(fillBg);
        int weight = Math.max(1, (int) Math.round(percent));
        bar.addView(fill, new LinearLayout.LayoutParams(0, dp(7), weight));
        View rest = new View(this);
        bar.addView(rest, new LinearLayout.LayoutParams(0, dp(7), Math.max(0, 100 - weight)));
        wrap.addView(bar);

        return wrap;
    }

    private EditText input(String hint) {
        EditText input = new EditText(this);
        input.setHint(hint);
        input.setSingleLine(true);
        input.setTextSize(15);
        input.setTextColor(TEXT);
        input.setHintTextColor(Color.rgb(148, 163, 184));
        input.setPadding(dp(14), 0, dp(14), 0);
        input.setBackground(inputBg());
        input.setLayoutParams(new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(48)));
        return input;
    }

    private Button primaryButton(String text) {
        return button(text, primaryColor(), Color.WHITE, true);
    }

    private Button secondaryButton(String text) {
        return button(text, Color.WHITE, primaryColor(), false);
    }

    private Button ghostButton(String text) {
        return button(text, Color.TRANSPARENT, TEXT, false);
    }

    private Button dangerButton(String text) {
        return button(text, RED, Color.WHITE, true);
    }

    private Button button(String text, int bgColor, int textColor) {
        return button(text, bgColor, textColor, false);
    }

    private Button button(String text, int bgColor, int textColor, boolean raised) {
        Button button = new Button(this);
        button.setText(text);
        button.setTextColor(textColor);
        button.setTextSize(14);
        button.setAllCaps(false);
        button.setTypeface(android.graphics.Typeface.DEFAULT_BOLD);
        button.setGravity(Gravity.CENTER);
        button.setStateListAnimator(null);
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(bgColor);
        bg.setCornerRadius(dp(16));
        int strokeColor = bgColor == Color.WHITE ? Color.rgb(191, 219, 254) : bgColor == Color.TRANSPARENT ? Color.TRANSPARENT : bgColor;
        bg.setStroke(1, strokeColor);
        button.setBackground(bg);
        button.setMinHeight(dp(44));
        button.setPadding(dp(14), 0, dp(14), 0);
        if (Build.VERSION.SDK_INT >= 21) {
            button.setElevation(raised ? dp(4) : dp(1));
        }
        return button;
    }

    private android.graphics.drawable.Drawable inputBg() {
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(Color.WHITE);
        bg.setCornerRadius(dp(10));
        bg.setStroke(1, Color.rgb(203, 213, 225));
        return bg;
    }

    private TextView empty(String text) {
        TextView view = label(text, 15, MUTED);
        view.setGravity(Gravity.CENTER);
        view.setPadding(0, dp(32), 0, dp(32));
        return view;
    }

    private void promptReject(String title, RejectCallback callback) {
        EditText input = input("Alasan");
        input.setMinLines(3);
        input.setSingleLine(false);
        new AlertDialog.Builder(this)
                .setTitle(title)
                .setView(input)
                .setNegativeButton("Batal", null)
                .setPositiveButton("Reject", (dialog, which) -> {
                    String reason = input.getText().toString().trim();
                    if (reason.isEmpty()) {
                        toast("Alasan wajib diisi.");
                        return;
                    }
                    callback.onReject(reason);
                })
                .show();
    }

    private void promptRequiredText(String title, String hint, String actionLabel, TextCallback callback) {
        EditText input = input(hint);
        input.setMinLines(4);
        input.setSingleLine(false);
        AlertDialog dialog = new AlertDialog.Builder(this)
                .setTitle(title)
                .setView(input)
                .setNegativeButton("Batal", null)
                .setPositiveButton(actionLabel, null)
                .create();
        dialog.setOnShowListener(d -> dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> {
            String value = input.getText().toString().trim();
            if (value.isEmpty()) {
                toast(title + " wajib diisi.");
                return;
            }
            dialog.dismiss();
            callback.onText(value);
        }));
        dialog.show();
    }

    private void confirm(String title, String message, Runnable yes) {
        new AlertDialog.Builder(this)
                .setTitle(title)
                .setMessage(message)
                .setNegativeButton("Batal", null)
                .setPositiveButton("Ya", (dialog, which) -> yes.run())
                .show();
    }

    private String approvePbMessage(JSONObject item) {
        StringBuilder builder = new StringBuilder();
        builder.append("Setujui ").append(item.optString("nomor_pb")).append("?\n\n");
        builder.append("Daftar barang:\n");
        JSONArray preview = item.optJSONArray("items");
        if (preview == null || preview.length() == 0) {
            builder.append("- Belum ada detail barang di preview");
            return builder.toString();
        }
        for (int i = 0; i < preview.length(); i++) {
            JSONObject detail = preview.optJSONObject(i);
            builder.append("- ")
                    .append(detail.optString("nama_barang"))
                    .append(" x ")
                    .append(qty(detail.optDouble("jumlah")))
                    .append(" ")
                    .append(detail.optString("satuan"))
                    .append("\n");
        }
        return builder.toString();
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= 26) {
            NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            if (manager != null) {
                ensureNotificationChannel(manager, APPROVAL_CHANNEL_ID, "Approval Request", "Notifikasi antrian approval PB", R.raw.erequest_notification);
                ensureNotificationChannel(manager, WORK_ORDER_CHANNEL_ID, "Work Order Assignment", "Notifikasi work order masuk untuk pelaksana", R.raw.work_order_notification);
                if (manager.getNotificationChannel(UPDATE_CHANNEL_ID) == null) {
                    NotificationChannel updateChannel = new NotificationChannel(UPDATE_CHANNEL_ID, "App Update", NotificationManager.IMPORTANCE_DEFAULT);
                    updateChannel.setDescription("Notifikasi download update aplikasi");
                    manager.createNotificationChannel(updateChannel);
                }
            }
        }
    }

    private void ensureNotificationChannel(NotificationManager manager, String id, String name, String description, int soundResId) {
        if (Build.VERSION.SDK_INT < 26 || manager.getNotificationChannel(id) != null) {
            return;
        }

        NotificationChannel channel = new NotificationChannel(id, name, NotificationManager.IMPORTANCE_HIGH);
        channel.setDescription(description);
        channel.setSound(Uri.parse("android.resource://" + getPackageName() + "/" + soundResId), new AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .build());
        manager.createNotificationChannel(channel);
    }

    private void requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= 33 && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.POST_NOTIFICATIONS}, 10);
        }
    }

    private String baseUrl() {
        return prefs.getString("base_url", DEFAULT_BASE_URL);
    }

    private String serverOrigin() {
        String value = baseUrl();
        int marker = value.indexOf("/api/mobile");
        return marker >= 0 ? value.substring(0, marker) : value;
    }

    private String token() {
        return prefs.getString("token", "");
    }

    private String role() {
        return prefs.getString("role", "");
    }

    private boolean isEngineeringUser() {
        String username = displayUsername().toLowerCase(Locale.ROOT);
        return "Admin Engineering".equals(prefs.getString("role_label", ""))
                || ("user".equals(role()) && "adm-engineering".equals(username))
                || ("user".equals(role()) && username.matches("eng\\d+\\.bpu"));
    }

    private String roleLabel() {
        if (isEngineeringUser()) {
            return "Admin Engineering";
        }
        if ("approval2".equals(role())) {
            return "Approval Level 2";
        }
        if ("section_head".equals(role())) {
            return "Section Head";
        }
        return "Approval Level 1";
    }

    private String rupiah(double value) {
        NumberFormat format = NumberFormat.getCurrencyInstance(new Locale("id", "ID"));
        format.setMaximumFractionDigits(0);
        return format.format(value);
    }

    private String qty(double value) {
        if (value == Math.rint(value)) {
            return String.valueOf((int) value);
        }
        return String.valueOf(value);
    }

    private String compactDate(String value) {
        if (value == null || value.isEmpty() || "null".equals(value)) {
            return "";
        }
        if (value.length() >= 10) {
            return value.substring(0, 10);
        }
        return value;
    }

    private boolean isImageFile(String fileName) {
        String lower = fileName == null ? "" : fileName.toLowerCase(Locale.ROOT);
        return lower.endsWith(".jpg")
                || lower.endsWith(".jpeg")
                || lower.endsWith(".png")
                || lower.endsWith(".webp");
    }

    @Override
    public void onBackPressed() {
        if ("pb".equals(currentScreen) || "wo".equals(currentScreen) || "section".equals(currentScreen) || "engineering".equals(currentScreen) || "history".equals(currentScreen) || "profile".equals(currentScreen)) {
            showDashboard();
            return;
        }
        super.onBackPressed();
    }

    private void toast(String text) {
        Toast.makeText(this, text, Toast.LENGTH_LONG).show();
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    private interface RejectCallback {
        void onReject(String reason);
    }

    private interface TextCallback {
        void onText(String value);
    }

    private static class ApiResponse {
        final boolean ok;
        final int status;
        final JSONObject json;

        ApiResponse(boolean ok, int status, JSONObject json) {
            this.ok = ok;
            this.status = status;
            this.json = json;
        }
    }

    private static class SpaceDrawable extends android.graphics.drawable.ColorDrawable {
        private final int size;

        SpaceDrawable(int size) {
            super(Color.TRANSPARENT);
            this.size = size;
        }

        @Override
        public int getIntrinsicHeight() {
            return size;
        }

        @Override
        public int getIntrinsicWidth() {
            return size;
        }
    }
}
