package com.abissa.mobile;

import android.app.*;
import android.app.job.*;
import android.content.Intent;
import android.os.Build;
import android.net.Uri;

/** One opt-in reminder; no background account data or invented remote push token. */
public final class ReminderService extends JobService {
    @Override public boolean onStartJob(JobParameters p){
        if(getSharedPreferences("abissa-native-212",0).getBoolean("notifications",false)){
            NotificationManager manager=(NotificationManager)getSystemService(NOTIFICATION_SERVICE);
            if(Build.VERSION.SDK_INT>=26)manager.createNotificationChannel(new NotificationChannel("abissa-reminders","Rappels ABISSA",NotificationManager.IMPORTANCE_DEFAULT));
            Intent intent=new Intent(this,MainActivity.class).setData(Uri.parse("https://abissamobile.com/appli/mobile.html?page=programme"));
            PendingIntent tap=PendingIntent.getActivity(this,212,intent,PendingIntent.FLAG_UPDATE_CURRENT|PendingIntent.FLAG_IMMUTABLE);
            Notification.Builder b=Build.VERSION.SDK_INT>=26?new Notification.Builder(this,"abissa-reminders"):new Notification.Builder(this);
            b.setSmallIcon(com.abissa.mobile.MainActivity.notificationIcon(this)).setContentTitle("ABISSA MOBILE").setContentText("Retrouvez le programme et les actualités de l’Abissa.").setAutoCancel(true).setContentIntent(tap);
            try{manager.notify(212,b.build());}catch(SecurityException ignored){}
        }
        return false;
    }
    @Override public boolean onStopJob(JobParameters p){return false;}
}
