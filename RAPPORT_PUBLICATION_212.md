# ABISSA MOBILE 2.1.2 / build 24 — état du 7 octobre 2026

**Préparation locale avancée ; publication complète encore ouverte.** L’APK signé et les sources importables sont disponibles. L’AAB signé final et l’archive iOS restent à produire. Aucune publication, aucun envoi TestFlight et aucun déploiement serveur n’ont été effectués.

## Correction demandée du menu

La barre native supplémentaire « ACCUEIL, SCANNER, FAVORIS, PLUS » est retirée sur Android et iOS. La navigation principale du site est conservée. Dans **Profil → Services de l’application**, six boutons donnent accès au scanner QR, aux favoris natifs, aux notifications, aux téléchargements, aux préférences et à À propos. Ces services restent accessibles aux visiteurs ; les fonctions du compte conservent leur authentification.

À propos présente la version/build, le développeur, les contacts et les liens de confidentialité. Les fonctions utiles disposent de leur propre entrée dans Profil.

## Projets et fonctions

Android : Java/WebView, package `com.abissa.mobile`, version 2.1.2/code 24, minSdk 23, cible API 36. iOS : UIKit/WKWebView, Bundle ID `ci.abissa`, version 2.1.2/build 24, iOS 15 minimum. Identifiants conservés.

L’interface et ses ressources de premier lancement sont embarquées. Les ajouts natifs comprennent le scanner ZXing/AVFoundation, le partage système, les téléchargements, les favoris et préférences persistants, l’état hors connexion, les liens associés et la navigation retour. Les liens Maps, téléphone, email et WhatsApp utilisent les applications système. Les permissions sont demandées lors de l’usage ; une activation déjà enregistrée des notifications peut reprendre au démarrage.

Android possède un rappel natif et une consultation périodique de la boîte de notifications. Cela ne constitue pas une réception FCM temps réel. iOS possède les hooks APNs, dont les certificats et essais serveur restent nécessaires. Le scanner d’un laissez-passer ouvre la consultation officielle après confirmation ; il ne remplace pas la validation serveur ni les permissions de l’agent.

## Livrables

Dossier : `00 - ABISSA FINAL/Abissa-Mobile-v2.1.2-preparation-2026-10-07/`.

- `Abissa-Mobile-v2.1.2-release.apk` : APK signé historique, 37 270 037 octets ; SHA-256 `af32a2388f80f5d082c1e6144f1fb8c662e37f96e4b8b5dabf1bb7c69bcd5a31`.
- `Abissa-Mobile-v2.1.2-SOURCES-GITHUB-CODEMAGIC.zip` : projets Android/iOS, dépendance scanner, interface, backend et configuration Codemagic. Extraire puis importer les dossiers à la racine du dépôt.
- `Abissa-Mobile-v2.1.2-SITE-A-HEBERGER.zip` : correctifs publics à héberger manuellement, en préservant `api/config.php`, `data/` et `uploads/`.
- `MANIFESTE-212.json` : tailles, SHA-256 et résultats de vérification des archives.
- Documents de signature, configuration Google Play, confidentialité et import GitHub/Codemagic.

Les ZIP publics excluent les clés privées, mots de passe DPAPI, configurations serveur privées, données de comptes et uploads. La sauvegarde du keystore historique reste dans `.release-signing/backups/2026-10-07/` du projet original, hors dépôt. Les certificats DER fournis sont publics.

## Vérifications terminées

Les rapports JSON de `qa-tools/` distinguent les contrôles réels et les simulations.

- APK : huit groupes réussis, dont certificat historique, signatures v1/v2/v3, manifeste, DEX, CRC et correspondance exacte des 58 ressources embarquées.
- Interface : sept groupes Playwright réussis, dont les six boutons du Profil et absence de débordement horizontal à 360/390/768 px. Le pont natif est simulé dans ce test navigateur.
- Syntaxe/structure : 47 fichiers PHP, 45 fichiers JavaScript, plists, privacy manifest, schéma et références iOS, versions et permissions Android vérifiés. Cela ne remplace pas une compilation Swift.
- Suppression de compte : quatre groupes avec serveur PHP et comptes synthétiques isolés ; authentification, effacement des éléments privés/sessions/QR/sauvegardes gérées et conservation des autres comptes vérifiés.
- Régression communauté : rapport `test-community-regression-211-results.json`, exécuté sur le backend repris dans cette préparation ; il ne certifie pas l’hébergement de production.
- AAB **non signé** : structure validée par bundletool dans `manual-build-v24/`. Il reste local, n’est pas livré comme AAB final et ne peut pas être soumis en cet état.

Android natif : huit groupes réussis sur l’émulateur Android 16/API 36, consignés dans `qa-tools/android-runtime-212/results.json`. Démarrage sans barre supplémentaire, À propos, activité caméra, vrai rappel et ouverture du Programme, lien hôtel et menu de partage système, favori conservé après redémarrage et interface embarquée hors connexion vérifiés. Les captures sont dans le même dossier. La mise à jour depuis 2.1.1/code 22, sans désinstallation, conserve UID, date d’installation et inode du dossier de données. Cela ne prouve pas encore la conservation d’une session serveur connectée.

## Blocages et validations restant nécessaires

1. **AAB signé** : le mot de passe DPAPI de la clé d’envoi Play existante ne se déchiffre plus. Exécuter `qa-tools/recover-upload-password.ps1` avec le mot de passe propriétaire saisi de manière masquée, puis `qa-tools/build-playstore-212.py`. Ne pas régénérer de clé pour contourner le blocage.
2. **Identité Google Play** : le certificat historique est `CN=Android Debug, O=Android, C=US`, RSA 2048. Il permet les mises à jour APK existantes, mais l’acceptation comme clé finale Google Play reste à résoudre. Les stores refusent habituellement les certificats debug selon la [documentation Android](https://developer.android.com/studio/publish/app-signing#debug-mode). La clé d’envoi RSA 4096 est distincte ; voir `GOOGLE_PLAY_SIGNATURE_SETUP.md`.
3. **iOS** : importer les sources dans GitHub puis lancer `ios-212-validation` et `ios-212-archive` dans Codemagic avec les profils/certificats existants. Windows ne dispose pas de Xcode. Aucun IPA ni essai petit iPhone/iPhone récent/iPad n’est confirmé à ce stade.
4. **Serveur et associations** : héberger manuellement les pages confidentialité/suppression et correctifs API ; vérifier les URLs publiques, configurer APNs, fournir le Team ID réel pour l’AASA et vérifier les liens associés sur appareils. Pas de valeur Apple fictive publiée.
5. **Matrice appareils** : Android 8/10/12/13/14/15, téléphones physiques, vrai décodage QR, comptes connectés avant/après mise à jour, téléchargements et communications, APNs et parcours iOS interactifs restent à vérifier. Aucun résultat sur ces plateformes n’est inventé.
6. **Examen stores** : remplir les déclarations d’après les flux réellement déployés. Les fonctions natives améliorent l’application ; elles ne garantissent pas l’acceptation App Store, qui dépend notamment des [règles 4.2 et de modération](https://developer.apple.com/app-store/review/guidelines/).

Le dépôt public a été consulté sans modification distante. L’import manuel demandé par le propriétaire est documenté dans `GITHUB_CODEMAGIC_IMPORT_212.md`. La tâche de publication reste en cours tant que les éléments ci-dessus ne sont pas réglés.
