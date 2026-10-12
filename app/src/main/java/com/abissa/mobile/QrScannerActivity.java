package com.abissa.mobile;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.hardware.Camera;
import android.os.Bundle;
import android.view.SurfaceHolder;
import android.view.SurfaceView;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;
import com.google.zxing.BinaryBitmap;
import com.google.zxing.MultiFormatReader;
import com.google.zxing.PlanarYUVLuminanceSource;
import com.google.zxing.common.HybridBinarizer;
import java.util.concurrent.atomic.AtomicBoolean;

/** Camera frames stay on the device. Reading a QR never grants an accreditation. */
@SuppressWarnings("deprecation")
public final class QrScannerActivity extends Activity implements SurfaceHolder.Callback, Camera.PreviewCallback {
    private Camera camera;
    private SurfaceView preview;
    private final AtomicBoolean reading = new AtomicBoolean(false);
    private boolean surfaceReady, finished;
    private TextView status;
    @Override public void onCreate(Bundle saved) {
        super.onCreate(saved);
        LinearLayout body = new LinearLayout(this); body.setOrientation(LinearLayout.VERTICAL);
        body.setBackgroundColor(android.graphics.Color.WHITE);
        if (android.os.Build.VERSION.SDK_INT >= 35) {
            body.setOnApplyWindowInsetsListener((view, insets) -> {
                android.graphics.Insets bars = insets.getInsets(
                        android.view.WindowInsets.Type.systemBars()
                        | android.view.WindowInsets.Type.displayCutout());
                view.setPadding(bars.left, bars.top, bars.right, bars.bottom);
                android.view.WindowInsetsController controller = view.getWindowInsetsController();
                if (controller != null) {
                    int lightBars = android.view.WindowInsetsController.APPEARANCE_LIGHT_STATUS_BARS
                            | android.view.WindowInsetsController.APPEARANCE_LIGHT_NAVIGATION_BARS;
                    controller.setSystemBarsAppearance(lightBars, lightBars);
                }
                return insets;
            });
        }
        status = new TextView(this); status.setText("Cadrez un QR code. La vérification des accès nécessite un agent connecté.");
        status.setPadding(20,20,20,20); body.addView(status);
        preview = new SurfaceView(this); body.addView(preview,new LinearLayout.LayoutParams(-1,0,1));
        Button close = new Button(this); close.setText("Retour à Abissa"); close.setOnClickListener(v -> finish()); body.addView(close);
        setContentView(body); preview.getHolder().addCallback(this);
        body.requestApplyInsets();
        if(checkSelfPermission(Manifest.permission.CAMERA)!=PackageManager.PERMISSION_GRANTED)
            requestPermissions(new String[]{Manifest.permission.CAMERA},212);
    }
    private void startCamera() {
        if(!surfaceReady || camera!=null || finished || checkSelfPermission(Manifest.permission.CAMERA)!=PackageManager.PERMISSION_GRANTED)return;
        try {
            camera=Camera.open(); Camera.Parameters p=camera.getParameters();
            if(p.getSupportedFocusModes().contains(Camera.Parameters.FOCUS_MODE_CONTINUOUS_PICTURE))p.setFocusMode(Camera.Parameters.FOCUS_MODE_CONTINUOUS_PICTURE);
            camera.setParameters(p); camera.setDisplayOrientation(90); camera.setPreviewDisplay(preview.getHolder());
            camera.setPreviewCallback(this); camera.startPreview();
        }catch(Exception e){stopCamera();status.setText("Caméra indisponible. Vérifiez son autorisation dans les réglages Android.");}
    }
    private void stopCamera(){if(camera!=null){camera.setPreviewCallback(null);camera.stopPreview();camera.release();camera=null;}}
    @Override public void onPreviewFrame(byte[] bytes,Camera source){
        if(finished || !reading.compareAndSet(false,true))return;
        Camera.Size size=source.getParameters().getPreviewSize();
        new Thread(() -> {
            try {
                PlanarYUVLuminanceSource luminance=new PlanarYUVLuminanceSource(bytes,size.width,size.height,0,0,size.width,size.height,false);
                String result=new MultiFormatReader().decode(new BinaryBitmap(new HybridBinarizer(luminance))).getText();
                runOnUiThread(() -> {if(finished)return;finished=true;stopCamera();Intent data=new Intent();data.putExtra("qr",result);setResult(RESULT_OK,data);finish();});
            }catch(Exception ignored){}finally{reading.set(false);}
        },"abissa-qr").start();
    }
    @Override public void surfaceCreated(SurfaceHolder h){surfaceReady=true;startCamera();}
    @Override public void surfaceChanged(SurfaceHolder h,int f,int w,int height){}
    @Override public void surfaceDestroyed(SurfaceHolder h){surfaceReady=false;stopCamera();}
    @Override protected void onResume(){super.onResume();startCamera();}
    @Override protected void onPause(){stopCamera();super.onPause();}
    @Override public void onRequestPermissionsResult(int r,String[] p,int[] g){super.onRequestPermissionsResult(r,p,g);if(r==212){if(g.length>0&&g[0]==PackageManager.PERMISSION_GRANTED)startCamera();else status.setText("Caméra non autorisée. Vous pouvez continuer à utiliser Abissa sans scanner.");}}
}
