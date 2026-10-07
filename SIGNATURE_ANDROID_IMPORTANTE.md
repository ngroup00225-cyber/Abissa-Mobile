# Signature Android historique — 2.1.2

Package inchangé : `com.abissa.mobile`. APK Release : version 2.1.2, code 24, minimum API 23, cible API 36. Les signatures v1/v2/v3 sont vérifiées et le certificat correspond aux APK historiquement distribués.

| Champ | Valeur |
|---|---|
| Copie privée du keystore | `.release-signing/backups/2026-10-07/historical-android.keystore`, dans le projet original |
| Alias | `androiddebugkey` |
| Certificat | `CN=Android Debug, O=Android, C=US` |
| Clé | RSA 2048 bits, entrée privée récupérable |
| SHA-1 | `DC:CB:D2:2B:50:EF:56:F1:11:01:F8:25:C8:F5:0B:C7:F2:8E:11:CA` |
| SHA-256 | `13:F8:E6:D5:D5:AE:51:62:3D:72:D3:2E:3D:4B:9C:00:B5:07:E6:AE:5E:61:46:56:79:CC:87:40:B6:89:32:93` |
| Certificat public exporté | `store-assets/historical-android-certificate.der` |
| Mot de passe | Protégé localement par Windows DPAPI ; jamais inclus dans cette documentation ni dans le ZIP |

Le chemin complet de la copie privée sur cette machine est `C:\Users\Narcisse Djéni\Documents\ChatGPT\ABISSA MOBILE 2\.release-signing\backups\2026-10-07\historical-android.keystore`. Conserver une sauvegarde hors dépôt, avec le mot de passe dans un gestionnaire sécurisé. La copie DPAPI n’est pas une sauvegarde portable du mot de passe : elle dépend du compte Windows. Le ZIP de sources ne contient aucune clé privée.

`qa-tools/verify-signature-212.py` vérifie l’accès à l’entrée privée avec son mot de passe et exporte uniquement le certificat public. `qa-tools/signature-212-results.json` consigne le type réel du keystore et les résultats. `qa-tools/build-android-212.py` réutilise cette clé pour les mises à jour APK. Ne jamais supprimer ou régénérer le keystore historique, même si son alias contient « debug ».

Pour une prochaine livraison, augmenter `versionCode`, conserver le package et le certificat, comparer les empreintes avec `apksigner verify --verbose --print-certs`, puis installer avec `adb install -r` sur une ancienne version, sans désinstallation ni effacement. Le test 2.1.1 → 2.1.2 compare UID, date de première installation et inode du dossier de données. La conservation d’une session réelle connectée nécessite aussi un essai avec un compte de test sur le serveur.

## Limite de publication Google Play

Cette identité historique est un **certificat de débogage Android**, même si l’APK utilise une compilation Release non débogable. La taille RSA répond au minimum de 2048 bits, mais cela ne prouve pas que Google accepte ce certificat final. [Android documente le refus habituel des certificats debug par les stores](https://developer.android.com/studio/publish/app-signing#debug-mode).

Ne pas remplacer la clé pour contourner ce problème : cela casserait les mises à jour directes des utilisateurs existants. Vérifier l’acceptation de l’import de cette identité dans Play Console et solliciter le support Google si elle est refusée. Aucune acceptation Play ni migration de signature n’est confirmée dans cette livraison.
