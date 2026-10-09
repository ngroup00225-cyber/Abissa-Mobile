package com.abissa.mobile;

import android.app.Activity;
import android.Manifest;
import android.content.pm.PackageManager;
import android.webkit.GeolocationPermissions;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.graphics.Color;
import android.graphics.BitmapFactory;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.Settings;
import android.view.View;
import android.view.Gravity;
import android.webkit.JavascriptInterface;
import android.webkit.MimeTypeMap;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebResourceError;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ImageView;
import android.widget.TextView;
import android.widget.Button;

import java.io.OutputStream;
import java.nio.charset.StandardCharsets;

public class MainActivity extends Activity {
    private static final int FILE_CHOOSER_REQUEST = 1001;
    private static final int EXPORT_REQUEST = 1002;
    private static final int LOCATION_REQUEST = 1003;
    private static final String APP_URL = "https://abissamobile.com/appli/";
    private final Handler startupHandler = new Handler(Looper.getMainLooper());
    private LinearLayout startupView;
    private TextView startupStatus;
    private Button retryButton;
    private int startupAttempt;
    private WebView webView;
    private NativeFeatures nativeFeatures;
    public static int notificationIcon(android.content.Context c) { return c.getResources().getIdentifier("ic_launcher", "mipmap", c.getPackageName()); }
    private ValueCallback<Uri[]> fileCallback;
    private String pendingExport;
    private byte[] pendingExportBytes;
    private String pendingExportMime;
    private GeolocationPermissions.Callback locationCallback;
    private String locationOrigin;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().setStatusBarColor(Color.rgb(0, 45, 89));
        getWindow().setNavigationBarColor(Color.rgb(0, 59, 112));

        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.rgb(0, 45, 89));
        if (android.os.Build.VERSION.SDK_INT >= 35) {
            root.setOnApplyWindowInsetsListener((view, insets) -> {
                android.graphics.Insets bars = insets.getInsets(
                        android.view.WindowInsets.Type.systemBars()
                        | android.view.WindowInsets.Type.displayCutout()
                        | android.view.WindowInsets.Type.ime());
                view.setPadding(bars.left, bars.top, bars.right, bars.bottom);
                return insets;
            });
        }
        setContentView(root);
        if (android.os.Build.VERSION.SDK_INT >= 33) {
            getOnBackInvokedDispatcher().registerOnBackInvokedCallback(
                    android.window.OnBackInvokedDispatcher.PRIORITY_DEFAULT, this::handleBack);
        }
        createStartupView(root);
        try {
            webView = new WebView(this);
        } catch (RuntimeException error) {
            startupStatus.setText("Le composant Android System WebView est indisponible. Mettez-le à jour, puis relancez Abissa Mobile.");
            return;
        }
        webView.setBackgroundColor(Color.rgb(251, 245, 230));
        root.addView(webView, 0, new FrameLayout.LayoutParams(-1, -1));

        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setMediaPlaybackRequiresUserGesture(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setTextZoom(100);
        settings.setUseWideViewPort(false);
        settings.setLoadWithOverviewMode(false);

        webView.addJavascriptInterface(new AndroidBridge(), "Android");
        webView.setWebViewClient(new AbissaWebViewClient());
        webView.setWebChromeClient(new AbissaChromeClient());
        nativeFeatures = new NativeFeatures(this, webView);
        nativeFeatures.attach(root);

        // LocalStorage remains on the same HTTPS origin. Never restore the old
        // multi-megabyte data-URL history after upgrading from 2.0.1.
        loadBundledPage();
    }

    private void createStartupView(FrameLayout root) {
        float density = getResources().getDisplayMetrics().density;
        startupView = new LinearLayout(this);
        startupView.setOrientation(LinearLayout.VERTICAL);
        startupView.setGravity(Gravity.CENTER);
        startupView.setPadding((int)(28*density), 0, (int)(28*density), 0);
        startupView.setBackgroundColor(Color.rgb(0, 45, 89));
        ImageView logo = new ImageView(this);
        int resource = getResources().getIdentifier("abissa_logo", "drawable", getPackageName());
        BitmapFactory.Options options = new BitmapFactory.Options();
        options.inJustDecodeBounds = true;
        BitmapFactory.decodeResource(getResources(), resource, options);
        options.inSampleSize = 1;
        while (options.outWidth/options.inSampleSize > 384 || options.outHeight/options.inSampleSize > 384) options.inSampleSize *= 2;
        options.inJustDecodeBounds = false;
        logo.setImageBitmap(BitmapFactory.decodeResource(getResources(), resource, options));
        logo.setContentDescription("Abissa Mobile");
        startupView.addView(logo, new LinearLayout.LayoutParams((int)(140*density), (int)(140*density)));
        startupStatus = new TextView(this);
        startupStatus.setTextColor(Color.WHITE);
        startupStatus.setTextSize(17);
        startupStatus.setGravity(Gravity.CENTER);
        startupStatus.setPadding(0, (int)(24*density), 0, (int)(16*density));
        startupView.addView(startupStatus);
        retryButton = new Button(this);
        retryButton.setText("Réessayer");
        retryButton.setVisibility(View.GONE);
        retryButton.setOnClickListener(v -> loadBundledPage());
        startupView.addView(retryButton);
        root.addView(startupView, new FrameLayout.LayoutParams(-1, -1));
    }

    private void loadBundledPage() {
        if (webView == null) return;
        int attempt = ++startupAttempt;
        startupHandler.removeCallbacksAndMessages(null);
        startupView.setVisibility(View.VISIBLE);
        startupStatus.setText("Ouverture d’Abissa Mobile…");
        retryButton.setVisibility(View.GONE);
        webView.stopLoading();
        webView.loadUrl(APP_URL);
        checkStartup(attempt, 0);
    }

    private void showStartupError() {
        startupStatus.setText("Le chargement n’a pas abouti. Vos données sont conservées.");
        retryButton.setVisibility(View.VISIBLE);
    }

    private void checkStartup(int attempt, int count) {
        startupHandler.postDelayed(() -> {
            if (attempt != startupAttempt || webView == null || startupView.getVisibility() != View.VISIBLE) return;
            webView.evaluateJavascript("Boolean(window.AbissaExperience201 && document.querySelector('.r-splash'))", result -> {
                if (attempt != startupAttempt) return;
                if ("true".equals(result)) {
                    startupView.setVisibility(View.GONE);
                } else if (count < 59) {
                    if (count == 19) showStartupError();
                    checkStartup(attempt, count + 1);
                } else {
                    showStartupError();
                }
            });
        }, 500);
    }

    private boolean isBundledPage(Uri uri) {
        return "https".equals(uri.getScheme()) && "abissamobile.com".equals(uri.getHost())
                && "/appli/".equals(uri.getPath()) && uri.getQuery() == null;
    }

    private WebResourceResponse bundledResponse(String path, String mime) {
        try {
            WebResourceResponse response = new WebResourceResponse(mime,
                    mime.equals("text/html") ? "UTF-8" : null, getAssets().open(path));
            java.util.Map<String, String> headers = new java.util.HashMap<>();
            headers.put("Cache-Control", "no-store");
            response.setResponseHeaders(headers);
            return response;
        } catch (java.io.IOException error) {
            return new WebResourceResponse("text/plain", "UTF-8", 404, "Not Found",
                    java.util.Collections.emptyMap(), new java.io.ByteArrayInputStream(new byte[0]));
        }
    }

    private class AbissaWebViewClient extends WebViewClient {
        @Override
        public WebResourceResponse shouldInterceptRequest(WebView view, WebResourceRequest request) {
            Uri uri = request.getUrl();
            if (NativeFeatures.official(uri) && "/appli/api/data".equals(uri.getPath())
                    && uri.getQuery() == null && "GET".equals(request.getMethod())
                    && !request.getRequestHeaders().containsKey("Authorization")) {
                return PublicContentCache.get(MainActivity.this, uri);
            }
            if (request.isForMainFrame() && isBundledPage(uri)) {
                return bundledResponse("abissa_mobile.html", "text/html");
            }
            if ("https".equals(uri.getScheme()) && "abissamobile.com".equals(uri.getHost())
                    && uri.getPath() != null && uri.getPath().matches("/appli/bundled-media/[a-f0-9]{64}\\.(png|webp|svg|wav|woff2)")) {
                String name = uri.getLastPathSegment();
                String ext = name.substring(name.lastIndexOf('.')+1);
                String mime = ext.equals("svg") ? "image/svg+xml" : ext.equals("wav") ? "audio/wav"
                        : ext.equals("woff2") ? "font/woff2" : "image/"+ext;
                return bundledResponse("bundled-media/"+name, mime);
            }
            if ("https".equals(uri.getScheme()) && "abissamobile.com".equals(uri.getHost())
                    && uri.getPath() != null && uri.getPath().matches("/appli/bundled-refonte/[a-z0-9-]+\\.png")) {
                return bundledResponse("refonte-2026/"+uri.getLastPathSegment(), "image/png");
            }
            return super.shouldInterceptRequest(view, request);
        }
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            if (!request.isForMainFrame()) return false;
            Uri uri = request.getUrl();
            if (isBundledPage(uri)) return false;
            String scheme = uri.getScheme();
            if (scheme == null || scheme.equals("file") || scheme.equals("data") || scheme.equals("blob")) return true;
            if (scheme.equals("http") || scheme.equals("https") || scheme.equals("tel") || scheme.equals("mailto")) {
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, uri));
                } catch (ActivityNotFoundException e) {
                    Toast.makeText(MainActivity.this, "Aucune application disponible", Toast.LENGTH_SHORT).show();
                }
                return true;
            }
            return true;
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            if (request.isForMainFrame()) runOnUiThread(() -> {
                startupView.setVisibility(View.VISIBLE);
                showStartupError();
            });
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            super.onPageFinished(view, url);
            String bridge = "(function(){" +
                    "if(window.__abissaAndroidReady)return;window.__abissaAndroidReady=true;" +
                    "window.__abissaNav=['accueil'];" +
                    "if(typeof window.goTo==='function'){window.__abissaOriginalGoTo=window.goTo;" +
                    "window.goTo=function(id){var cur=(document.querySelector('.page.active')||{}).id||'';cur=cur.replace('page-','');" +
                    "if(cur&&cur!==id)window.__abissaNav.push(id);return window.__abissaOriginalGoTo(id);};}" +
                    "if(typeof window.exportConfig==='function'){window.exportConfig=function(){" +
                    "Android.saveText(JSON.stringify(collectFullState(),null,2),'abissa-config.json');" +
                    "if(typeof showToast==='function')showToast('Choisissez où enregistrer le fichier');};}" +
                    "})();";
            view.evaluateJavascript(bridge, value -> { nativeFeatures.ready(); deliverDeepLink(); });
        }
    }

    private class AbissaChromeClient extends WebChromeClient {
        @Override
        public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
            if (!"https://abissamobile.com".equals(origin) && !"https://abissamobile.com/".equals(origin)) {
                callback.invoke(origin, false, false); return;
            }
            if (checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED) {
                callback.invoke(origin, true, false);
            } else {
                if (locationCallback != null) locationCallback.invoke(locationOrigin, false, false);
                locationOrigin = origin; locationCallback = callback;
                requestPermissions(new String[]{Manifest.permission.ACCESS_COARSE_LOCATION}, LOCATION_REQUEST);
            }
        }

        @Override
        public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> callback, FileChooserParams params) {
            if (fileCallback != null) fileCallback.onReceiveValue(null);
            fileCallback = callback;
            Intent intent;
            try {
                intent = params.createIntent();
                intent.addCategory(Intent.CATEGORY_OPENABLE);
                startActivityForResult(intent, FILE_CHOOSER_REQUEST);
                return true;
            } catch (ActivityNotFoundException e) {
                fileCallback = null;
                Toast.makeText(MainActivity.this, "Sélecteur de fichiers indisponible", Toast.LENGTH_SHORT).show();
                return false;
            }
        }
    }

    private void showWebsite(String url) {
        Uri uri = Uri.parse(url);
        if (!("https".equals(uri.getScheme()) || "http".equals(uri.getScheme())) || uri.getHost() == null || uri.getUserInfo() != null) return;
        android.app.Dialog dialog = new android.app.Dialog(MainActivity.this, android.R.style.Theme_Material_Light_NoActionBar);
        LinearLayout layout = new LinearLayout(MainActivity.this);
        layout.setOrientation(LinearLayout.VERTICAL);
        Button back = new Button(MainActivity.this);
        back.setText("‹ Retour à Abissa Mobile");
        layout.addView(back, new LinearLayout.LayoutParams(-1, -2));
        WebView browser = new WebView(MainActivity.this);
        browser.getSettings().setJavaScriptEnabled(true);
        browser.getSettings().setDomStorageEnabled(true);
        browser.getSettings().setAllowFileAccess(false);
        browser.getSettings().setAllowContentAccess(false);
        browser.getSettings().setMediaPlaybackRequiresUserGesture(true);
        browser.getSettings().setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        // External pages have no Android JavaScript bridge or access to bundled assets.
        browser.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri target = request.getUrl();
                String scheme = target.getScheme();
                if ("https".equals(scheme) || "http".equals(scheme)) return false;
                if ("tel".equals(scheme) || "mailto".equals(scheme)) {
                    try { startActivity(new Intent(Intent.ACTION_VIEW, target)); } catch (ActivityNotFoundException ignored) {}
                }
                return true;
            }
            @Override public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) Toast.makeText(MainActivity.this, "Ce site est momentanément indisponible", Toast.LENGTH_SHORT).show();
            }
        });
        browser.setWebChromeClient(new WebChromeClient());
        layout.addView(browser, new LinearLayout.LayoutParams(-1, 0, 1));
        dialog.setContentView(layout);
        back.setOnClickListener(v -> dialog.dismiss());
        dialog.setOnKeyListener((d, keyCode, event) -> {
            if (keyCode == android.view.KeyEvent.KEYCODE_BACK && event.getAction() == android.view.KeyEvent.ACTION_UP && browser.canGoBack()) { browser.goBack(); return true; }
            return false;
        });
        dialog.setOnDismissListener(d -> { browser.stopLoading(); browser.destroy(); });
        dialog.show();
        dialog.getWindow().setLayout(-1, -1);
        browser.loadUrl(url);
    }

    private void deliverDeepLink() {
        Uri uri = getIntent().getData();
        if (uri == null || !"https".equals(uri.getScheme()) || !"abissamobile.com".equals(uri.getHost()) || !"/appli/mobile.html".equals(uri.getPath())) return;
        getIntent().setData(null);
        webView.evaluateJavascript("window.dispatchEvent(new CustomEvent('abissa-deeplink',{detail:" + org.json.JSONObject.quote(uri.toString()) + "}));", null);
    }
    @Override protected void onNewIntent(Intent intent) { super.onNewIntent(intent); setIntent(intent); deliverDeepLink(); }

    public class AndroidBridge {
        @JavascriptInterface public void nativeAction(String json) { nativeFeatures.action(json); }
        @JavascriptInterface public void saveFile(String base64, String mime, String filename) {
            if (base64 == null || base64.length() > 35000000 || !java.util.Arrays.asList("application/pdf","image/png","image/jpeg","image/webp").contains(mime)) return;
            try {
                byte[] bytes = android.util.Base64.decode(base64, android.util.Base64.DEFAULT);
                if ("application/pdf".equals(mime) && (bytes.length < 5 || !new String(bytes,0,5,StandardCharsets.US_ASCII).equals("%PDF-"))) return;
                runOnUiThread(() -> startExport(bytes, filename, mime));
            } catch (IllegalArgumentException ignored) {}
        }
        @JavascriptInterface public void copy(String url) {
            Uri uri = Uri.parse(url);
            if (!"https".equals(uri.getScheme()) || !"abissamobile.com".equals(uri.getHost())) return;
            runOnUiThread(() -> {
                android.content.ClipboardManager clipboard = (android.content.ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
                if (clipboard != null) clipboard.setPrimaryClip(android.content.ClipData.newPlainText("ABISSA MOBILE", url));
                Toast.makeText(MainActivity.this, "Lien copié", Toast.LENGTH_SHORT).show();
            });
        }
        @JavascriptInterface public void share(String title, String text, String url) {
            Uri uri = Uri.parse(url);
            if (!"https".equals(uri.getScheme()) || !"abissamobile.com".equals(uri.getHost())) return;
            runOnUiThread(() -> {
                Intent share = new Intent(Intent.ACTION_SEND); share.setType("text/plain");
                share.putExtra(Intent.EXTRA_SUBJECT, title); share.putExtra(Intent.EXTRA_TEXT, title + "\n" + url);
                try { startActivity(Intent.createChooser(share, "Partager avec")); } catch (ActivityNotFoundException ignored) {}
            });
        }

        @JavascriptInterface
        public void openWebsite(String url) {
            runOnUiThread(() -> showWebsite(url));
        }

        @JavascriptInterface
        public void saveText(String content, String filename) {
            exportDocument(content, filename, "application/json");
        }
        @JavascriptInterface
        public void saveCalendar(String content, String filename) {
            exportDocument(content, filename, "text/calendar");
        }
        private void exportDocument(String content, String filename, String mime) {
            if (content == null || content.length() > 10000000) return;
            runOnUiThread(() -> startExport(content.getBytes(StandardCharsets.UTF_8), filename, mime));
        }
    }
    private void startExport(byte[] content, String filename, String mime) {
        if (pendingExportBytes != null) { Toast.makeText(this,"Terminez l’enregistrement en cours.",Toast.LENGTH_SHORT).show(); return; }
        pendingExportBytes = content; pendingExportMime = mime;
        Intent intent = new Intent(Intent.ACTION_CREATE_DOCUMENT); intent.addCategory(Intent.CATEGORY_OPENABLE); intent.setType(mime);
        intent.putExtra(Intent.EXTRA_TITLE, filename == null ? "abissa-document" : new java.io.File(filename).getName());
        try { startActivityForResult(intent, EXPORT_REQUEST); }
        catch (ActivityNotFoundException e) { pendingExportBytes=null; Toast.makeText(this,"Enregistrement indisponible",Toast.LENGTH_SHORT).show(); }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == NativeFeatures.SCAN && resultCode == RESULT_OK && data != null) {
            nativeFeatures.qrResult(data.getStringExtra("qr"));
        } else if (requestCode == FILE_CHOOSER_REQUEST) {
            if (fileCallback != null) {
                fileCallback.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(resultCode, data));
                fileCallback = null;
            }
        } else if (requestCode == EXPORT_REQUEST && resultCode == RESULT_OK && data != null && pendingExportBytes != null) {
            try (OutputStream stream = getContentResolver().openOutputStream(data.getData())) {
                if (stream == null) throw new java.io.IOException("No output stream");
                stream.write(pendingExportBytes);
                Toast.makeText(this, "Fichier enregistré", Toast.LENGTH_SHORT).show();
                if (pendingExportMime.equals("application/pdf") || pendingExportMime.startsWith("image/")) {
                    try { startActivity(new Intent(Intent.ACTION_VIEW).setDataAndType(data.getData(),pendingExportMime).addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)); }
                    catch (ActivityNotFoundException ignored) { Toast.makeText(this,"Le fichier reste accessible dans Fichiers.",Toast.LENGTH_LONG).show(); }
                }
            } catch (Exception e) {
                Toast.makeText(this, "Échec de l’export", Toast.LENGTH_SHORT).show();
            }
            pendingExport = null;
            pendingExportBytes = null; pendingExportMime = null;
        } else if (requestCode == EXPORT_REQUEST) {
            pendingExportBytes = null; pendingExportMime = null;
        }
    }

    @Override
    protected void onDestroy() {
        startupAttempt++;
        startupHandler.removeCallbacksAndMessages(null);
        if (nativeFeatures != null) nativeFeatures.close();
        if (webView != null) webView.destroy();
        super.onDestroy();
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == NativeFeatures.NOTIFICATIONS) {
            if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) nativeFeatures.enableReminder();
            else Toast.makeText(this, "Notifications non autorisées. Le reste de l’application reste disponible.", Toast.LENGTH_LONG).show();
        }
        if (requestCode == LOCATION_REQUEST && locationCallback != null) {
            locationCallback.invoke(locationOrigin, grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED, false);
            locationCallback = null; locationOrigin = null;
        }
    }

    @Override
    public void onBackPressed() {
        handleBack();
    }

    private void handleBack() {
        if (webView == null || startupView.getVisibility() == View.VISIBLE) {
            finish();
            return;
        }
        String script = "(function(){var dialog=document.querySelector('dialog[open]');if(dialog){dialog.close();return 'handled';}if(window.__abissaNav&&window.__abissaNav.length>1){" +
                "window.__abissaNav.pop();var id=window.__abissaNav[window.__abissaNav.length-1];" +
                "window.__abissaOriginalGoTo(id);return 'handled';}return 'exit';})()";
        webView.evaluateJavascript(script, value -> {
            if (!"\"handled\"".equals(value)) finish();
        });
    }
}
