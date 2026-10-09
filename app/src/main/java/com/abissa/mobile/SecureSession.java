package com.abissa.mobile;

import android.content.Context;
import android.security.keystore.*;
import android.util.Base64;
import javax.crypto.*;
import javax.crypto.spec.GCMParameterSpec;
import java.security.KeyStore;
import java.nio.charset.StandardCharsets;

/** Native notification session, encrypted by an app-owned Android Keystore key. */
final class SecureSession {
    private static SecretKey key()throws Exception{
        KeyStore ks=KeyStore.getInstance("AndroidKeyStore");ks.load(null);
        if(ks.containsAlias("abissa-notifications-212"))return (SecretKey)ks.getKey("abissa-notifications-212",null);
        KeyGenerator gen=KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES,"AndroidKeyStore");
        gen.init(new KeyGenParameterSpec.Builder("abissa-notifications-212",KeyProperties.PURPOSE_ENCRYPT|KeyProperties.PURPOSE_DECRYPT).setBlockModes(KeyProperties.BLOCK_MODE_GCM).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE).build());return gen.generateKey();
    }
    static void save(Context context,String token)throws Exception{
        android.content.SharedPreferences prefs=context.getSharedPreferences("abissa-secure-212",0);
        if(token.isEmpty()){prefs.edit().clear().apply();return;}
        if(token.length()>4096)throw new IllegalArgumentException("Invalid session");
        if(token.equals(read(context)))return;
        Cipher c=Cipher.getInstance("AES/GCM/NoPadding");c.init(Cipher.ENCRYPT_MODE,key());
        prefs.edit().putString("iv",Base64.encodeToString(c.getIV(),Base64.NO_WRAP)).putString("token",Base64.encodeToString(c.doFinal(token.getBytes(StandardCharsets.UTF_8)),Base64.NO_WRAP)).apply();
        context.getSharedPreferences("abissa-native-212",0).edit().remove("notification-seen").apply();
    }
    static String read(Context context){try{
        android.content.SharedPreferences p=context.getSharedPreferences("abissa-secure-212",0);if(!p.contains("token"))return "";
        Cipher c=Cipher.getInstance("AES/GCM/NoPadding");c.init(Cipher.DECRYPT_MODE,key(),new GCMParameterSpec(128,Base64.decode(p.getString("iv",""),Base64.NO_WRAP)));
        return new String(c.doFinal(Base64.decode(p.getString("token",""),Base64.NO_WRAP)),StandardCharsets.UTF_8);
    }catch(Exception e){return "";}}
}
