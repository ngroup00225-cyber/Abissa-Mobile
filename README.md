# Stabilisation du 12 octobre 2026

Sources corrigees pour import manuel sur fix/ios-demarrage. Lire RAPPORT_STABILISATION_20261012.md et IMPORTER_GITHUB_STABILISATION_20261012.md. Commencer par qa-quick puis ios-212-validation ; aucun transfert automatique vers les stores. Android conserve ci.abissa/build27 et la cle existante. Validation WebKit et appareils physiques encore requise.

# ABISSA MOBILE 2.1.2 — Play Store ci.abissa / build27

Livraison actuelle : `00 - ABISSA FINAL/Android-2.1.2-ci.abissa-Play-2026-10-12/Abissa-Mobile-ci.abissa-v2.1.2-build27-release.aab`. Package d'installation **ci.abissa**, namespace Java com.abissa.mobile, SDK minimal24, cible36. Les activités et services utilisent leurs noms complets et À propos lit la version réelle. Le build27 remplace les builds25 et26 refusés. Lire `NOTICE_EDITEUR_PLAYSTORE_20261012.md` et `RAPPORT_FINAL_PLAY_CI_ABISSA_20261012.md` ; transmettre le dossier final à la personne qui publie depuis son compte Play.

Compilation : `python qa-tools/build-play-release-20261011.py` ; contrôles : `python qa-tools/test-play-release-20261011.py` puis `python qa-tools/test-play-ci-abissa-20261012.py` sur l'émulateur QA Android16 ; livraison : `python qa-tools/package-play-release-20261011.py`. La clé d'importation est conservée. L'historique du compte et le certificat attendu restent à vérifier par le responsable Play. Le changement de package ne migre pas les données locales des anciens APK com.abissa.mobile.

## Historique — correction SDK code26

Correction du 12 octobre 2026 : utiliser `00 - ABISSA FINAL/Android-2.1.2-Release-Play-2026-10-12/Abissa-Mobile-v2.1.2-build26-release.aab`. Le build25 a été refusé car son SDK minimal était 23 ; il est remplacé par le build26 (Android 7.0 minimum / API 24, cible API 36). La même clé de publication est réutilisée. Le script de compilation lit désormais les versions dans `app/build.gradle`, rejette un SDK minimal inférieur à 24 et contrôle le manifeste du bundle signé. Lire `RAPPORT_SDK_PLAY_20261012.md`.

Compilation Play locale : `python qa-tools/build-play-release-20261011.py` ; vérification : `python qa-tools/test-play-release-20261011.py` ; livraison : `python qa-tools/package-play-release-20261011.py`. L'ancien point d'entrée `qa-tools/build-playstore-212.py` appelle ce même constructeur actuel. Dans un environnement Gradle complet : `qa-tools/build-gradle-play-release.ps1`. Les anciens scripts APK et les livraisons datées ci-dessous sont historiques.

## Historique — correction de signature code25

Correction Android du 11 octobre 2026 : AAB Release signé avec un certificat de publication, vérifié par jarsigner et bundletool. Utiliser `qa-tools/build-play-release-20261011.py` sur la machine locale ou `qa-tools/build-gradle-play-release.ps1` avec un environnement Gradle complet. La clé privée reste hors dépôt ; le certificat public est `store-assets/abissa-play-upload-20261011-certificate.pem`. Lire `RAPPORT_SIGNATURE_PLAY_20261011.md` : l’enregistrement de la clé d’importation et le code25 doivent être confirmés dans Play Console. Les mises à jour des APK historiques ne sont pas garanties par ce nouveau certificat d’envoi. L’AAB `signe-historique` a été refusé et ne doit plus être importé. Aucune modification iOS ni publication automatique pour ce correctif.

## Préparation précédente — build 24

La barre native supplémentaire « ACCUEIL, SCANNER, FAVORIS, PLUS » est supprimée. Scanner, favoris, notifications, téléchargements, préférences et À propos sont regroupés dans **Profil → Services de l’application**. La navigation principale existante reste disponible.

APK : `Abissa-Mobile-v2.1.2-release.apk`, signé avec le certificat historique pour les mises à jour directes. Sources : `app/`, `ios/`, `deploy-cpanel/` et `qa-tools/`. Lire **RAPPORT_PUBLICATION_212.md** pour les preuves et les validations encore ouvertes. La publication complète reste en cours : AAB signé bloqué par le mot de passe de la clé d’envoi existante ; compilation/signature iOS à effectuer dans Codemagic. Le certificat historique porte l’identité Android Debug : l’acceptation Google Play doit être résolue avant publication.

Importer les fichiers **extraits** du ZIP à la racine de GitHub selon `GITHUB_CODEMAGIC_IMPORT_212.md`. Ne pas déposer seulement le ZIP dans le dépôt. Aucun déploiement automatique ou publication de store n’est activé.

Compilation locale Android : `python qa-tools/build-android-212.py` puis `python qa-tools/build-playstore-212.py`, avec SDK/JDK et clés privées conservés séparément. Regroupement partagé : `python qa-tools/bundle-mobile.py`. Sur Mac, ouvrir `ios/AbissaMobile.xcodeproj` ou utiliser les workflows `codemagic.yaml`. Tests actuels : `qa-tools/validate-212.py`, `qa-tools/test-native-212-ui.cjs`, `qa-tools/test-apk-212-package.py` et `qa-tools/test-android-runtime-212.py`.

Les sections suivantes décrivent les livraisons précédentes et ne certifient pas la publication 2.1.2.

# Historique — ABISSA MOBILE 2.1.1

Sources Android : Java/WebView, com.abissa.mobile, code 22, API 36. Sources iOS : UIKit/WKWebView, Bundle ID proposé ci.abissa, version 2.1.1/build22, iOS 15 minimum. Interface HTML/JS et backend PHP JSON partagés. Le projet reçu n’est pas Flutter.

Accueil : commandes de slider symétriques, 20 icônes Microsoft Fluent locales, libellés centrés et petites flèches de menu retirées. Blog et Votre avis compte sont harmonisés. Laissez-passer : profils de couleur, zones, points affectés aux agents, QR signés, refus serveur, historique et réémission. Lire GUIDE_LAISSEZ_PASSER_211.md avant mise en service.

Les commentaires sont publiés immédiatement ; les articles Blog utilisateurs restent privés avant approbation. Modération, signalements, blocage/suspension, avis et réponses, slider configurable, modèles habituels de badges 95 × 135 mm, quantités et PDF sont intégrés au code existant.

Lire [la documentation iOS](DOCUMENTATION_IOS/README.md), [l’architecture](DOCUMENTATION_IOS/00_ARCHITECTURE_ET_ETAT.md), [les migrations/API/déploiement](DOCUMENTATION_IOS/MIGRATIONS_ET_API.md), [les métadonnées App Store](APP_STORE_METADATA.md) et [le rapport de livraison](RAPPORT_LIVRAISON_211.md).

## Réaménagements du back-office — 5 octobre 2026

Menu en trois catégories, laissez-passer personnalisable avec aperçu immédiat et QR signé. Impression rétablie au format fixe 9,5 × 13,5 cm, sans Éditer ni boutons +/− : saisir la quantité dans la case. Disposition A4 portrait systématique : quatre badges de 9,5 × 13,5 cm par page (2 × 2), espacés de 8 mm pour la découpe, avec copies identiques dans le PDF. La correction retire une seule fois les anciens laissez-passer à son installation et révoque leurs QR, après sauvegarde. Les prochains dossiers restent conservés. Sources : deploy-cpanel/. Rapports : RAPPORT_REAMENAGEMENTS_2026-10-05.md (première livraison) et RAPPORT_CORRECTIONS_IMPRESSION_2026-10-05.md (correction actuelle). Hébergement manuel.

## Compilation et vérification

Sous Windows avec le SDK local déjà présent : python qa-tools/build-android-211.py, puis python qa-tools/build-playstore-211.py. Le premier produit l’APK signé avec le certificat historique ; le second produit l’AAB avec la clé d’envoi Play existante. Android Studio ouvre le projet Gradle à la racine ; le mode Release Gradle doit disposer de sa propre configuration de signature avant publication.

Sur Mac : python3 qa-tools/bundle-mobile.py puis cd ios et bash scripts/build-ios.sh simulator, archive ou export. Renseigner Team ID et ExportOptions.local.plist pour les archives signées. Aucun Flutter/Pods requis. Les sources Swift n’ont pas été compilées sur cette machine Windows ; aucun IPA ou upload TestFlight n’a été réalisé.

Tests locaux : python qa-tools/test-community-regression-211.py (PHP réel sur données isolées + Playwright), python qa-tools/validate-211.py. Les contrôles QA ne remplacent pas les essais Android/iPhone/iPad, le serveur de production ni l’impression papier. La checklist de publication garde ces validations ouvertes.

Déploiement : copier les fichiers publics de deploy-cpanel dans appli/ en préservant config.php, data/ et uploads/. La migration JSON sauvegardée s’applique une seule fois sous verrou. Ne pas attribuer automatiquement les nouvelles permissions aux anciens administrateurs. Variables APNs : APNS_CERTIFICATE, APNS_PRIVATE_KEY, APNS_TOPIC, côté serveur seulement. La boîte de notifications est disponible ; APNs nécessite les certificats et essais réels, FCM Android n’est pas configuré.

## Paquet de livraison 2.1.1

Dossier : `00 - ABISSA FINAL/Abissa-Mobile-v2.1.1-a-heberger-2026-10-04/`. Le ZIP voisin comprend `SITE-A-HEBERGER/`, l’APK, `PLAY-STORE/`, `SOURCES-ANDROID-IOS.zip`, la documentation et les vérifications. Dans l’archive des sources, les dossiers app/, ios/, deploy-cpanel/ et qa-tools/ conservent leurs chemins pour les scripts de regroupement. Aucune clé privée, donnée de production ni configuration serveur privée n’est fournie. La signature APK conserve le certificat historique (debug.keystore existant) ; l’AAB utilise la clé d’envoi Play dédiée. Sauvegarder ces clés séparément pour les futures mises à jour.

## Historique conservé

> Nouvelle livraison : **2.0.9 du 3 octobre 2026 / code 20 / API 36**. Retours interface et back-office, Espace Publicité et direct intégré. Voir `qa-tools/TRAVAIL-TERMINE-209.md`. Paquet dans `00 - ABISSA FINAL/Abissa-Mobile-v2.0.9-a-heberger-2026-10-03/`. Installation et hébergement manuels ; essai Android réel et diffusions à confirmer.

> Version actuelle livrée : **2.0.8 du 3 octobre 2026 / code 19 / API 36**. QR remonté pour préserver « ABISSA 2026 ». APK historique et AAB première publication signés, site et dossier Play Store complets dans `Abissa-Mobile-livraison-2.0.8-2026-10-03/` et le ZIP correspondant. 88 contrôles locaux réussis. Voir `qa-tools/TRAVAIL-TERMINE-208.md`, `qa-tools/package-208-results.json` et `PLAY-STORE/GUIDE-PUBLICATION.md` dans la livraison. Publication manuelle ; tests Android réels, production serveur, papier et examen Google restent à effectuer.

> Livraison historique : **2.0.7 du 1er octobre 2026 / code 18**. APK signé `Abissa-Mobile-v2.0.7.apk`, ZIP vérifié `Abissa-Mobile-v2.0.7-a-heberger-2026-10-01.zip` et dossier de livraison prêts. Voir `qa-tools/TRAVAIL-TERMINE-207.md` et `qa-tools/package-207-results.json`. Badges transparents sans fond imposé, contour couleur commission, textes agrandis, noms sur deux lignes et QR à 24 %. 70 contrôles locaux réussis. Mise en ligne manuelle ; lancement sur téléphone et impression papier restent à confirmer.

> Livraison historique : **2.0.6 du 1er octobre 2026 / code 17**. APK signé `Abissa-Mobile-v2.0.6.apk` et ZIP vérifié `Abissa-Mobile-v2.0.6-a-heberger-2026-10-01.zip` conservés à la racine. Voir `qa-tools/TRAVAIL-TERMINE-206.md` et `qa-tools/package-206-results.json`. Le fond culturel fourni dans cette version est corrigé par la demande 2.0.7 ; ses rapports ne valident pas le nouveau rendu.

# Abissa Mobile — Android, site PHP et back-office

Cette application Android embarque fidèlement le prototype `abissa_mobile_1.html` dans une WebView locale.

## Compiler l’APK de test

Sous Windows :

```powershell
.\gradlew.bat assembleDebug
```

L’APK généré se trouve dans `app/build/outputs/apk/debug/app-debug.apk`.

La version compilée et signée actuelle est fournie à la racine : `Abissa-Mobile-v2.1.1.apk`. Le paquet Google Play est `Abissa-Mobile-v2.1.1-playstore.aab`.

Le dossier racine peut être ouvert directement dans Android Studio. L’identifiant de l’application est `com.abissa.mobile`, la version des sources est `2.1.1` (`versionCode 22`, cible API 36) et le logo officiel est utilisé comme icône.

## Fonctionnalités Android ajoutées

- navigation retour Android intégrée à la navigation interne ;
- sélection de photos et import de configuration ;
- export JSON via le sélecteur de fichiers Android ;
- ouverture des liens web, téléphone et e-mail dans les applications du système ;
- stockage local persistant et accès réseau pour le backend facultatif.

## Site et administration

- `website-v2/` : site PHP multipage public ;
- `deploy-cpanel/` : back-office, application web, API PHP et stockage JSON ;
- `app/` : projet Android Studio complet.

Le site public et l’application lisent les mêmes contenus gérés depuis le back-office.

## Codes WhatsApp

Les parcours d’inscription et de mot de passe oublié sont prêts. Pour activer les envois réels, renseigner dans `deploy-cpanel/api/config.php` les valeurs `WHATSAPP_PHONE_NUMBER_ID` et `WHATSAPP_ACCESS_TOKEN` fournies par Meta WhatsApp Business Cloud. Le jeton doit rester exclusivement côté serveur.

Compilation alternative avec le SDK local : `py -3 qa-tools/build-android.py`.

Depuis la version 1.1.3, l'application récupère les contenus à l’ouverture, chaque minute et au retour réseau ou au premier plan. Les derniers contenus restent disponibles hors ligne. Installer la version actuelle une fois sur les téléphones existants pour bénéficier de sa nouvelle interface.

## Mise à jour du 7 septembre 2026

APK : `Abissa-Mobile-v1.1.4.apk`. Signature identique à la 1.1.2, installation par-dessus l’existant. Une installation de cette version est nécessaire pour les nouveaux parcours Android. Les contenus sont récupérés au lancement, toutes les 60 secondes, au retour réseau et au premier plan, puis conservés hors connexion.

Archive prête : `Abissa-2026-mise-a-jour.zip`. Mise en ligne manuelle par l’utilisateur, conformément à sa dernière consigne. Extraire le contenu dans public_html/abissamobile en conservant appli/data, appli/uploads et appli/api/config.php. Consulter LIRE-AVANT-INSTALLATION.txt dans le dossier de livraison.
