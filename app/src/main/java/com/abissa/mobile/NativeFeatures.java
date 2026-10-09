package com.abissa.mobile;

import android.app.*;
import android.content.*;
import android.content.pm.PackageManager;
import android.net.*;
import android.os.*;
import android.provider.Settings;
import android.webkit.WebView;
import android.widget.*;
import org.json.*;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.util.*;

/** Features exposed only to the bundled, trusted application. */
final class NativeFeatures {
    static final int SCAN=2120, NOTIFICATIONS=2121;
    private final Activity activity;
    private final WebView web;
    private final android.content.SharedPreferences prefs;
    private final ConnectivityManager connectivity;
    private ConnectivityManager.NetworkCallback networkCallback;
    private BroadcastReceiver downloadReceiver;
    private TextView networkStatus;
    private final Handler handler=new Handler(Looper.getMainLooper());
    NativeFeatures(Activity a,WebView w){activity=a;web=w;prefs=a.getSharedPreferences("abissa-native-212",0);connectivity=(ConnectivityManager)a.getSystemService(Context.CONNECTIVITY_SERVICE);}
    void attach(FrameLayout root){
        networkStatus=new TextView(activity);networkStatus.setText("Hors connexion · contenus enregistrés disponibles");networkStatus.setTextColor(0xffffffff);networkStatus.setBackgroundColor(0xff9a5200);networkStatus.setPadding(12,8,12,8);networkStatus.setVisibility(android.view.View.GONE);
        root.addView(networkStatus,new FrameLayout.LayoutParams(-1,-2,android.view.Gravity.TOP));
        networkCallback=new ConnectivityManager.NetworkCallback(){@Override public void onAvailable(Network n){updateNetwork();}@Override public void onLost(Network n){updateNetwork();}@Override public void onCapabilitiesChanged(Network n,NetworkCapabilities c){updateNetwork();}};
        if(Build.VERSION.SDK_INT>=24)connectivity.registerDefaultNetworkCallback(networkCallback);
        else connectivity.registerNetworkCallback(new NetworkRequest.Builder().addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET).build(),networkCallback);
        updateNetwork();
        downloadReceiver=new BroadcastReceiver(){@Override public void onReceive(Context c,Intent i){long id=i.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID,-1);if(prefs.contains("download-"+id))completedDownload(id);}};
        if(Build.VERSION.SDK_INT>=33)activity.registerReceiver(downloadReceiver,new IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE),Context.RECEIVER_EXPORTED);
        else activity.registerReceiver(downloadReceiver,new IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE));
        web.setDownloadListener((url,agent,disposition,mime,length)->download(url,mime));
    }
    private boolean online(){Network n=connectivity.getActiveNetwork();NetworkCapabilities c=n==null?null:connectivity.getNetworkCapabilities(n);return c!=null&&c.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED);}
    private void updateNetwork(){activity.runOnUiThread(()->{boolean active=online();networkStatus.setVisibility(active?android.view.View.GONE:android.view.View.VISIBLE);networkStatus.post(()->{FrameLayout.LayoutParams p=(FrameLayout.LayoutParams)web.getLayoutParams();p.topMargin=active?0:networkStatus.getMeasuredHeight();web.setLayoutParams(p);});emit("abissa-native-network",Boolean.valueOf(active));});}
    void ready(){emit("abissa-native-state",state());updateNetwork();}
    private JSONObject state(){JSONObject s=new JSONObject();try{s.put("favorites",new JSONArray(prefs.getString("favorites","[]")));s.put("language",prefs.getString("language","fr"));s.put("lastPage",prefs.getString("lastPage","accueil"));s.put("notifications",prefs.getBoolean("notifications",false));s.put("platform","android");s.put("version","2.1.2");}catch(Exception ignored){}return s;}
    private void emit(String name,Object value){String literal=value instanceof String?JSONObject.quote((String)value):String.valueOf(value);web.evaluateJavascript("window.dispatchEvent(new CustomEvent('"+name+"',{detail:"+literal+"}));",null);}
    private void page(String id){web.evaluateJavascript("window.goTo?.("+JSONObject.quote(id)+");",null);}
    private void toast(String m){Toast.makeText(activity,m,Toast.LENGTH_LONG).show();}
    void scanner(){try{activity.startActivityForResult(new Intent(activity,QrScannerActivity.class),SCAN);}catch(Exception e){toast("Scanner indisponible sur cet appareil.");}}
    void qrResult(String text){
        if(text==null||text.length()>4096){toast("QR code invalide.");return;}
        Uri u=Uri.parse(text);
        if(official(u)&&"/appli/credential.html".equals(u.getPath())){
            new AlertDialog.Builder(activity).setTitle("Badge ABISSA")
                .setMessage("Consulter ce badge sur le serveur officiel. La lecture du QR ne valide pas un accès ; un agent autorisé doit effectuer le contrôle en ligne.")
                .setPositiveButton("Consulter",(d,n)->openExternal(text)).setNegativeButton("Annuler",null).show();return;
        }
        if(official(u)&&"/appli/mobile.html".equals(u.getPath())){emit("abissa-deeplink",text);return;}
        new AlertDialog.Builder(activity).setTitle("QR code lu").setMessage(text+"\n\nPour un badge ou laissez-passer, la validation d’accès est réservée aux agents autorisés.").setPositiveButton("Fermer",null).show();
    }
    static boolean official(Uri u){return "https".equals(u.getScheme())&&"abissamobile.com".equals(u.getHost())&&u.getUserInfo()==null&&u.getPort()==-1;}
    void action(String json){if(json==null||json.length()>65536)return;activity.runOnUiThread(()->{try{
        JSONObject b=new JSONObject(json);String action=b.optString("action");
        if(action.equals("scan"))scanner();
        else if(action.equals("about"))about();
        else if(action.equals("favorites"))favorites();
        else if(action.equals("downloads"))downloads();
        else if(action.equals("settings"))menu();
        else if(action.equals("favorite"))toggleFavorite(b);
        else if(action.equals("preferences")){
            String language=b.optString("language",prefs.getString("language","fr")),last=b.optString("lastPage",prefs.getString("lastPage","accueil"));
            if(Arrays.asList("fr","en","nz").contains(language))prefs.edit().putString("language",language).apply();
            if(last.matches("[a-z0-9-]{1,60}"))prefs.edit().putString("lastPage",last).apply();
        }
        else if(action.equals("notifications"))notifications();
        else if(action.equals("session"))SecureSession.save(activity,b.optString("token"));
        else if(action.equals("download"))download(b.optString("url"),b.optString("mime","application/octet-stream"));
        else if(action.equals("directions"))directions(b.optString("url"));
    }catch(Exception e){toast("Cette action n’a pas pu être effectuée.");}});}
    private void toggleFavorite(JSONObject item)throws JSONException {
        String url=item.optString("url"),title=item.optString("title");if(!official(Uri.parse(url))||url.length()>2048||title.length()>200)return;
        JSONArray old=new JSONArray(prefs.getString("favorites","[]")),next=new JSONArray();boolean removed=false;
        for(int i=0;i<old.length();i++){JSONObject f=old.getJSONObject(i);if(url.equals(f.optString("url")))removed=true;else next.put(f);}
        if(!removed){if(next.length()>=200){toast("200 favoris maximum.");return;}JSONObject f=new JSONObject();f.put("url",url);f.put("title",title);next.put(f);}
        prefs.edit().putString("favorites",next.toString()).apply();ready();toast(removed?"Favori retiré.":"Contenu ajouté aux favoris.");
    }
    private void favorites(){try{
        JSONArray items=new JSONArray(prefs.getString("favorites","[]"));if(items.length()==0){new AlertDialog.Builder(activity).setTitle("Favoris").setMessage("Ouvrez un contenu puis touchez Favori pour le retrouver ici, même après redémarrage.").setPositiveButton("Fermer",null).show();return;}
        String[] labels=new String[items.length()];for(int i=0;i<labels.length;i++)labels[i]=items.getJSONObject(i).optString("title");
        new AlertDialog.Builder(activity).setTitle("Mes favoris").setItems(labels,(d,n)->{try{emit("abissa-deeplink",items.getJSONObject(n).getString("url"));}catch(Exception ignored){}}).setNegativeButton("Fermer",null).show();
    }catch(Exception ignored){}}
    private void menu(){String[] entries={"Notifications","Langue","Dernière page","Téléchargements","À propos"};new AlertDialog.Builder(activity).setTitle("ABISSA MOBILE").setItems(entries,(d,n)->{switch(n){case 0:notifications();break;case 1:language();break;case 2:page(prefs.getString("lastPage","accueil"));break;case 3:downloads();break;case 4:about();}}).show();}
    private void language(){String[] codes={"fr","en","nz"};new AlertDialog.Builder(activity).setTitle("Langue").setItems(new String[]{"Français","English","N’zima"},(d,n)->{prefs.edit().putString("language",codes[n]).apply();ready();}).show();}
    private void about(){String[] entries={"Site officiel","Confidentialité","Conditions d’utilisation","Support"};String[] urls={"https://abissamobile.com/","https://abissamobile.com/confidentialite.php"};new AlertDialog.Builder(activity).setTitle("ABISSA MOBILE 2.1.2 (24)").setMessage("Royauté N’zima Kotoko · Grand-Bassam\nCréé par Narcisse Djéni N’DIAMOA\nScanner, favoris et fichiers disponibles dans Profil.").setPositiveButton("Liens utiles",(d,n)->new AlertDialog.Builder(activity).setItems(entries,(a,i)->{if(i<2)openExternal(urls[i]);else if(i==2)web.evaluateJavascript("window.AbissaRefonte?.actions.contract();",null);else page("feedback");}).show()).setNegativeButton("Fermer",null).show();}
    private void openExternal(String url){try{activity.startActivity(new Intent(Intent.ACTION_VIEW,Uri.parse(url)));}catch(ActivityNotFoundException e){toast("Aucune application disponible pour ce lien.");}}
    private void directions(String url){Uri u=Uri.parse(url);if(!"https".equals(u.getScheme())||!("www.google.com".equals(u.getHost())||"maps.google.com".equals(u.getHost())||"www.openstreetmap.org".equals(u.getHost())))return;String destination=u.getQueryParameter("destination");if(destination==null)destination=u.getQueryParameter("q");if(destination!=null){try{activity.startActivity(new Intent(Intent.ACTION_VIEW,Uri.parse("geo:0,0?q="+Uri.encode(destination))));return;}catch(ActivityNotFoundException ignored){}}openExternal(url);}
    private void notifications(){
        new AlertDialog.Builder(activity).setTitle("Notifications ABISSA").setMessage("Activez les messages de l’organisation et un rappel du programme pour demain. Connectez-vous pour recevoir vos messages. Android contrôle la synchronisation en arrière-plan (environ toutes les 15 minutes, sans garantie de délai).").setPositiveButton("Activer",(d,n)->{
            if(Build.VERSION.SDK_INT>=33&&activity.checkSelfPermission(android.Manifest.permission.POST_NOTIFICATIONS)!=PackageManager.PERMISSION_GRANTED)activity.requestPermissions(new String[]{android.Manifest.permission.POST_NOTIFICATIONS},NOTIFICATIONS);
            else enableReminder();
        }).setNeutralButton("Consulter",(d,n)->page("community-notifications")).setNegativeButton("Désactiver",(d,n)->{prefs.edit().putBoolean("notifications",false).apply();android.app.job.JobScheduler scheduler=(android.app.job.JobScheduler)activity.getSystemService(Context.JOB_SCHEDULER_SERVICE);scheduler.cancel(212);scheduler.cancel(213);scheduler.cancel(214);ready();}).show();
    }
    void enableReminder(){prefs.edit().putBoolean("notifications",true).apply();android.app.job.JobInfo job=new android.app.job.JobInfo.Builder(212,new ComponentName(activity,ReminderService.class)).setMinimumLatency(24*60*60*1000L).setOverrideDeadline(26*60*60*1000L).setPersisted(true).build();android.app.job.JobScheduler scheduler=(android.app.job.JobScheduler)activity.getSystemService(Context.JOB_SCHEDULER_SERVICE);int result=scheduler.schedule(job);scheduler.schedule(new android.app.job.JobInfo.Builder(213,new ComponentName(activity,NotificationSyncService.class)).setPeriodic(15*60*1000L).setRequiredNetworkType(android.app.job.JobInfo.NETWORK_TYPE_ANY).setPersisted(true).build());scheduler.schedule(new android.app.job.JobInfo.Builder(214,new ComponentName(activity,NotificationSyncService.class)).setMinimumLatency(1000).setRequiredNetworkType(android.app.job.JobInfo.NETWORK_TYPE_ANY).build());toast(result==android.app.job.JobScheduler.RESULT_SUCCESS?"Notifications activées et rappel programmé.":"Le rappel n’a pas pu être programmé.");ready();}
    private void download(String url,String mime){Uri u=Uri.parse(url);if(!official(u)){toast("Téléchargement refusé : lien non officiel.");return;}try{
        String name=u.getLastPathSegment();if(name==null||!name.matches("[a-zA-Z0-9._-]{1,120}"))name="abissa-document.pdf";
        DownloadManager.Request r=new DownloadManager.Request(u).setTitle(name).setMimeType(mime).setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED).setDestinationInExternalFilesDir(activity,Environment.DIRECTORY_DOWNLOADS,System.currentTimeMillis()+"-"+name);
        // Public downloads never carry account credentials across a server redirect.
        long id=((DownloadManager)activity.getSystemService(Context.DOWNLOAD_SERVICE)).enqueue(r);prefs.edit().putString("download-"+id,name).apply();toast("Téléchargement démarré.");
    }catch(Exception e){toast("Téléchargement impossible. Réessayez avec une connexion active.");}}
    private void downloads(){List<Long> ids=new ArrayList<>();List<String> names=new ArrayList<>();for(Map.Entry<String,?> p:prefs.getAll().entrySet()){if(p.getKey().startsWith("download-")){ids.add(Long.parseLong(p.getKey().substring(9)));names.add(String.valueOf(p.getValue()));}}if(ids.isEmpty()){toast("Aucun téléchargement enregistré.");return;}new AlertDialog.Builder(activity).setTitle("Téléchargements").setItems(names.toArray(new String[0]),(d,n)->completedDownload(ids.get(n))).show();}
    private void completedDownload(long id){DownloadManager dm=(DownloadManager)activity.getSystemService(Context.DOWNLOAD_SERVICE);try(android.database.Cursor c=dm.query(new DownloadManager.Query().setFilterById(id))){if(c==null||!c.moveToFirst())return;int status=c.getInt(c.getColumnIndexOrThrow(DownloadManager.COLUMN_STATUS));if(status!=DownloadManager.STATUS_SUCCESSFUL){toast(status==DownloadManager.STATUS_FAILED?"Téléchargement échoué.":"Téléchargement en cours.");return;}Uri uri=dm.getUriForDownloadedFile(id);String mime=dm.getMimeTypeForDownloadedFile(id);Intent open=new Intent(Intent.ACTION_VIEW).setDataAndType(uri,mime==null?"application/octet-stream":mime).addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);try{activity.startActivity(open);}catch(ActivityNotFoundException e){toast("Fichier enregistré. Installez une application pour l’ouvrir.");}}catch(Exception e){toast("Fichier indisponible.");}}
    void close(){handler.removeCallbacksAndMessages(null);if(networkCallback!=null)connectivity.unregisterNetworkCallback(networkCallback);if(downloadReceiver!=null)activity.unregisterReceiver(downloadReceiver);}
}
