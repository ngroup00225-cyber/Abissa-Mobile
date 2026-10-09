package com.abissa.mobile;

import android.app.*;
import android.app.job.*;
import android.content.*;
import android.net.Uri;
import android.os.Build;
import org.json.*;
import java.io.*;
import java.net.*;
import java.nio.charset.StandardCharsets;
import java.util.*;

/** Native background delivery from the existing HTTPS inbox, subject to Android job scheduling. */
public final class NotificationSyncService extends JobService {
    private volatile boolean stopped;
    @Override public boolean onStartJob(JobParameters parameters){stopped=false;new Thread(()->{try{sync();}catch(Exception ignored){}finally{if(!stopped)jobFinished(parameters,false);}},"abissa-notifications").start();return true;}
    private void sync()throws Exception{
        SharedPreferences prefs=getSharedPreferences("abissa-native-212",0);String token=SecureSession.read(this);
        if(token.isEmpty()||!prefs.getBoolean("notifications",false))return;
        HttpURLConnection c=(HttpURLConnection)new URL("https://abissamobile.com/appli/api/notifications").openConnection();c.setConnectTimeout(10000);c.setReadTimeout(15000);c.setInstanceFollowRedirects(false);c.setRequestProperty("Authorization","Bearer "+token);
        try{
            int code=c.getResponseCode();if(code==401){if(token.equals(SecureSession.read(this)))SecureSession.save(this,"");return;}if(code!=200)return;
            ByteArrayOutputStream bytes=new ByteArrayOutputStream();try(InputStream input=c.getInputStream()){byte[] block=new byte[4096];int n;while((n=input.read(block))!=-1){if(bytes.size()+n>2*1024*1024)return;bytes.write(block,0,n);}}
            JSONArray items=new JSONArray(new String(bytes.toByteArray(),StandardCharsets.UTF_8));Set<String> seen=new LinkedHashSet<>(prefs.getStringSet("notification-seen",Collections.emptySet()));
            NotificationManager manager=(NotificationManager)getSystemService(NOTIFICATION_SERVICE);if(Build.VERSION.SDK_INT>=26)manager.createNotificationChannel(new NotificationChannel("abissa-inbox","Messages de l’organisation",NotificationManager.IMPORTANCE_DEFAULT));
            for(int i=Math.max(0,items.length()-100);i<items.length();i++){
                JSONObject item=items.getJSONObject(i);String id=item.optString("id");
                if(stopped||!token.equals(SecureSession.read(this))||!prefs.getBoolean("notifications",false))return;
                if(id.isEmpty()||seen.contains(id))continue;seen.add(id);
                // First synchronization establishes a baseline without replaying the old inbox.
                if(!prefs.contains("notification-seen")||item.optBoolean("read",false))continue;
                String type=item.optString("type"),target=item.optString("target"),url="https://abissamobile.com/appli/mobile.html?page=community-notifications";
                if(Arrays.asList("news","event","blog").contains(type))url="https://abissamobile.com/appli/mobile.html?content="+type+"&id="+Uri.encode(target);
                Intent open=new Intent(this,MainActivity.class).setData(Uri.parse(url));PendingIntent tap=PendingIntent.getActivity(this,id.hashCode(),open,PendingIntent.FLAG_UPDATE_CURRENT|PendingIntent.FLAG_IMMUTABLE);
                Notification.Builder b=Build.VERSION.SDK_INT>=26?new Notification.Builder(this,"abissa-inbox"):new Notification.Builder(this);
                b.setSmallIcon(MainActivity.notificationIcon(this)).setContentTitle(item.optString("title","ABISSA MOBILE")).setContentText(item.optString("text")).setVisibility(Notification.VISIBILITY_PRIVATE).setAutoCancel(true).setContentIntent(tap);
                try{manager.notify(id.hashCode(),b.build());}catch(SecurityException ignored){}
            }
            while(seen.size()>200)seen.remove(seen.iterator().next());prefs.edit().putStringSet("notification-seen",seen).apply();
        }finally{c.disconnect();}
    }
    @Override public boolean onStopJob(JobParameters p){stopped=true;return true;}
}
