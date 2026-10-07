# Google Play : préserver l’identité historique

État au 7 octobre 2026 : aucune première diffusion ni configuration de signature Google effectuée par Codex. La clé finale souhaitée est la clé historique décrite dans `SIGNATURE_ANDROID_IMPORTANTE.md`. La clé d’envoi existante reste distincte.

| Rôle | Alias / certificat SHA-256 |
|---|---|
| App Signing Key souhaitée, APK déjà installés | `androiddebugkey` · `13:F8:E6:D5:D5:AE:51:62:3D:72:D3:2E:3D:4B:9C:00:B5:07:E6:AE:5E:61:46:56:79:CC:87:40:B6:89:32:93` |
| Upload Key existante, AAB envoyé à Google | `abissa-upload`, RSA 4096 · `8A:61:50:37:1C:D1:A6:A1:CC:33:86:B5:56:4C:9A:E7:3B:AB:C5:AD:C8:8E:A4:64:55:DE:A3:93:91:53:8E:67` |

La clé d’upload est dans `.release-signing/abissa-mobile-upload.jks`, uniquement sur la machine originale. Son certificat public est `store-assets/abissa-upload-certificate.der`. Signer l’AAB avec cette clé ne définit pas le certificat des APK livrés par Google.

## Étapes manuelles avant toute diffusion

1. Ouvrir l’application `com.abissa.mobile` dans Play Console. Aller dans **Protected with Play → Play Store distribution → Play App Signing**.
2. Avant toute diffusion en open testing ou production, utiliser **Change the app signing key → Provide a copy of your app signing key**, si cette option reste disponible. [Google décrit cette possibilité et la distinction des clés](https://support.google.com/googleplay/android-developer/answer/9842756).
3. Fournir la **clé historique**, jamais la clé `abissa-upload` à la place. Télécharger PEPK et la clé publique de chiffrement directement depuis cette page Console.
4. Exécuter localement la commande PEPK affichée par Google en remplaçant seulement le chemin du keystore et l’alias. Schéma à adapter aux arguments exacts proposés par Console :

   ```powershell
   java -jar pepk.jar --keystore=".release-signing/backups/2026-10-07/historical-android.keystore" --alias=androiddebugkey --output=".release-signing/historical-for-google.zip" --include-cert --rsa-aes-encryption --encryption-key-path="encryption_public_key.pem"
   ```

   Utiliser les demandes interactives de mot de passe, sans `--keystore-password` ni `--key-password` dans la ligne de commande. L’export privé chiffré reste hors dépôt et ne sera transmis qu’à l’écran officiel prévu par Google, par le propriétaire.
5. Confirmer que le SHA-256 affiché dans la rubrique **App signing key certificate** est exactement `13:F8:…:32:93`. Le SHA-256 de la rubrique Upload key est normalement différent. Ne pas lancer de diffusion si la clé finale ne correspond pas.
6. Si Google refuse le certificat historique « Android Debug », arrêter cette configuration et contacter le support. Une clé RSA 2048 ne garantit pas l’acceptation d’un certificat debug. Ne pas créer une autre clé finale en prétendant conserver la compatibilité.
7. Après acceptation, déposer l’AAB **signé** en test interne. Installer sur un appareil de test un APK historique, puis l’APK réellement délivré par Play, et vérifier l’acceptation de la mise à jour, les données et la session avant tout lancement public.

## Blocage du mot de passe d’envoi

Windows retourne actuellement `CryptUnprotectData : clé non valide dans l’état spécifié` pour `upload-password.dpapi`. La clé existante est conservée. Depuis le projet original, exécuter :

```powershell
powershell -ExecutionPolicy Bypass -File .\qa-tools\recover-upload-password.ps1
python .\qa-tools\build-playstore-212.py
```

Le premier script demande le mot de passe existant en saisie masquée, le vérifie avec `keytool` puis le protège pour le compte Windows courant. Le second vérifie l’empreinte de la clé d’envoi avant signature. Aucun mot de passe ne doit être collé dans le chat ou GitHub.

`manual-build-v24/Abissa-Mobile-v2.1.2-unsigned.aab` est un build provisoire validé par bundletool. **Il n’est pas le livrable final signé et ne doit pas être envoyé à Google Play.** `Abissa-Mobile-v2.1.2.aab` sera créé seulement après récupération du mot de passe.

ProGuard/R8 n’est pas activé (`minifyEnabled false`) : aucun mapping de minification n’est attendu. La clé d’upload n’est ni renouvelée ni remplacée dans cette livraison.
