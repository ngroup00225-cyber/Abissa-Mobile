# 12 — Notifications et liens

Les notifications sont conservées dans community_notifications.json : réponses aux commentaires et demandes, acceptation/refus Blog et publications officielles. La boîte de notifications fonctionne sans service push. Android reçoit cette boîte dans l’application ; aucun projet FCM ou service push Android n’a été fourni ou créé.

iOS : dans Profil > Notifications, toucher Activer les notifications iPhone. L’app demande le consentement, s’inscrit auprès d’APNs et envoie le token à POST /devices avec l’utilisateur connecté. Debug utilise development ; Release/TestFlight production. La distribution reste dépendante des capacités, du profil et du certificat réel.

Créer un certificat APNs pour l’App ID propriétaire, avec sa clé privée. Exporter depuis Trousseaux d’accès et préparer les fichiers PEM selon l’environnement serveur. Stocker les secrets hors public_html avec des droits restrictifs. Variables du worker : APNS_CERTIFICATE (chemin PEM), APNS_PRIVATE_KEY (chemin PEM), APNS_TOPIC (Bundle ID, ci.abissa par défaut). Ne jamais publier les certificats/clé avec le site. Le certificat doit autoriser l’environnement utilisé.

Le worker CLI deploy-cpanel/api/push-worker.php lit les notifications de moins de 24 heures, ignore les comptes non actifs, suit les livraisons, retire les tokens 410 et réessaie ultérieurement les erreurs temporaires. Il n’est pas accessible comme route web. Configurer une tâche côté hébergement uniquement après validation des secrets et essais ; exemple de commande serveur : php /chemin/prive/appli/api/push-worker.php. Vérifier cURL HTTP/2 et mbstring. Une livraison réussie en APNs n’atteste pas la réception sur un appareil en mode silencieux ou déconnecté.

Les liens partagés sont HTTPS /appli/mobile.html?content=news|event|blog&id=... . iOS accepte aussi le scheme abissa. Générer les associations avec :

```bash
python3 ios/scripts/generate-associations.py --team-id VOTRE_TEAM_ID --android-sha256 EMPREINTE_CERTIFICAT_PLAY --output website-v2/.well-known
```

L’empreinte Android doit être celle du certificat de signature des applications Google Play pour les installations Play ; l’upload certificate seul ne suffit pas. Un APK historique installé directement peut avoir une autre empreinte. Après vérification, publier apple-app-site-association sans extension et assetlinks.json dans /.well-known/ sur abissamobile.com en HTTPS avec application/json, sans authentification ni redirection. Ne pas publier les valeurs d’exemple.

Références : [Associated Domains](https://developer.apple.com/documentation/xcode/supporting-associated-domains), [APNs](https://developer.apple.com/documentation/usernotifications/setting-up-a-remote-notification-server).
