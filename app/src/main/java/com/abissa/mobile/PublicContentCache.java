package com.abissa.mobile;

import android.content.Context;
import android.net.Uri;
import android.webkit.WebResourceResponse;
import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Collections;
import org.json.JSONObject;

/** Only anonymous public data is cached. Authenticated account responses never enter this cache. */
final class PublicContentCache {
    static WebResourceResponse get(Context context,Uri uri){
        File cache=new File(context.getFilesDir(),"public-content-212.json");
        try{
            HttpURLConnection connection=(HttpURLConnection)new URL(uri.toString()).openConnection();
            connection.setConnectTimeout(6000);connection.setReadTimeout(10000);connection.setInstanceFollowRedirects(false);
            try{
                if(connection.getResponseCode()!=200)throw new IOException("Public service unavailable");
                byte[] data=read(connection.getInputStream());new JSONObject(new String(data,StandardCharsets.UTF_8));
                android.util.AtomicFile atomic=new android.util.AtomicFile(cache);FileOutputStream stream=null;
                try{stream=atomic.startWrite();stream.write(data);atomic.finishWrite(stream);}catch(IOException e){if(stream!=null)atomic.failWrite(stream);}
                return response(data,false);
            }finally{connection.disconnect();}
        }catch(Exception e){
            try{if(cache.isFile())return response(read(new FileInputStream(cache)),true);}catch(Exception ignored){}
            return new WebResourceResponse("application/json","UTF-8",503,"Offline",Collections.emptyMap(),new ByteArrayInputStream("{\"error\":\"Connexion indisponible. Les contenus embarqués restent accessibles.\"}".getBytes(StandardCharsets.UTF_8)));
        }
    }
    private static byte[] read(InputStream input)throws IOException{try(InputStream stream=input;ByteArrayOutputStream out=new ByteArrayOutputStream()){byte[] buffer=new byte[8192];int n;while((n=stream.read(buffer))!=-1){if(out.size()+n>8*1024*1024)throw new IOException("Content too large");out.write(buffer,0,n);}return out.toByteArray();}}
    private static WebResourceResponse response(byte[] data,boolean cached){return new WebResourceResponse("application/json","UTF-8",200,"OK",Collections.singletonMap("X-Abissa-Offline",cached?"true":"false"),new ByteArrayInputStream(data));}
}
