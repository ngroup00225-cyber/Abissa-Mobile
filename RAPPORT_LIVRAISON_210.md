# Livraison ABISSA MOBILE 2.1.0 — 4 octobre 2026

## Travaux intégrés

Les modules communautaires, le slider et les badges sont intégrés aux sources actives Android/web/PHP et au back-office existant. Les sources iOS partagent cette interface, avec le bridge natif documenté. Le choix UIKit/WKWebView répond à l’architecture réellement reçue ; aucun projet Flutter n’existait à réparer.

## Bugs corrigés

1. Commentaires enregistrés pending et invisibles avant validation : publication serveur immédiate et affichage après succès.
2. Clic sur slide intercepté par la capture du pointeur sur son parent : capture portée par la cible, testé avec de vrais clics navigateur.
3. Nouveaux liens du profil effacés par le rafraîchissement asynchrone de l’existant : réintégration après mise à jour.
4. Sélection/quantités de badges perdues en filtrant : conservation des IDs et des quantités.
5. PDF/aperçu ne produisaient qu’une copie par ID : images répétées selon quantité, pagination et taille physique contrôlées.
6. Compte suspendu non représenté dans les outils et statut inconnu pouvant devenir actif : statut suspendu et validation stricte côté serveur.
7. Déconnexion uniquement locale : révocation du token sur l’API et suppression des inscriptions push liées à la session.
8. Nouvelles collections ignorées par la suppression du compte : prise en compte des articles, demandes, réactions et appareils.
9. Édition d’un badge non prise en compte dans son suivi imprimé : design ajouté à l’empreinte.
10. API utilisateur sans la limite de tentatives présente chez les admins : limitation également au login utilisateur.

11. Vérification AAB restée au build 20 : contrôle version 2.1.0/build 21 et certificat d’envoi identique à 2.0.9. Le script refuse de remplacer une clé manquante.
12. Certains lecteurs PDF omettaient des copies partageant le même flux JPEG : flux indépendant par exemplaire, avec encodage JPEG mis en cache. Les 17 rendus, leurs images et leurs dimensions sont maintenant vérifiés.
13. Politique de confidentialité encore basée sur les commentaires validés : texte aligné sur les commentaires immédiats, le Blog modéré, les demandes privées et les inscriptions APNs. Le propriétaire doit confirmer le texte avant publication.
14. Les tests copiaient la configuration privée du serveur : ils utilisent maintenant qa-tools/qa-api-config.php, limité aux données fictives, sans identifiants de production. Ce fichier n’est pas dans SITE-A-HEBERGER.
15. Script shell iOS écrit avec fins de ligne Windows : conversion LF ; le regroupement des ressources utilise désormais directement la liste Android 2.1.0.

## Preuves et limites

Les rapports JSON dans qa-tools/test-community-210-*-results.json et test-210-syntax-results.json, test-apk-210-package-results.json, test-apk-offline-210-results.json, test-playstore-210-results.json et test-pdf-210-results.json décrivent les contrôles réellement exécutés. Les essais utilisent des fixtures isolées ; aucune donnée serveur n’a été déployée ou migrée. Les captures et PDF de test sont dans qa-tools/210-captures. Les contrôles de la version 2.0.9 ont été relancés sur les sources nouvelles.

Les tests navigateur couvrent les largeurs 360/390/430 et iPad 768/1024 ; ils ne certifient pas WKWebView ni les appareils. La compilation Xcode, les certificats/profils Apple, l’IPA, TestFlight, les captures natives et la publication App Store restent à effectuer avec un Mac et le compte propriétaire. Les fichiers de configuration ne constituent pas des certificats ni une archive signée.

APNs : inscription et worker préparés, sans identifiants ni livraison réelle validée. Android : boîte de notifications intégrée ; aucun service FCM fourni. Partage et caméra doivent être essayés dans les apps et appareils réellement installés. Les URLs support/confidentialité, contenus légaux et coordonnées Apple doivent être validés par le propriétaire. La checklist ne les coche pas comme terminés.

PDF : vérification de 17 images sur 5 pages A4 avec dimensions 95 × 135 mm ; l’impression physique et l’échelle 100 % restent à mesurer sur papier. Les travaux prepared ne sont pas présentés comme une impression papier réussie.

## Fichiers modifiés ou ajoutés

- website-v2/confidentialite.php
- qa-tools/build-playstore-210.py
- qa-tools/bundle-mobile.py
- qa-tools/test-apk-210-package.py
- qa-tools/test-apk-offline-210.cjs
- qa-tools/test-pdf-210.py
- qa-tools/package-210.py
- qa-tools/qa-api-config.php
- qa-tools/test-community-210.py
- qa-tools/test-retours-209-api.py
- README.md
- CHANGELOG.md
- APP_STORE_METADATA.md
- RAPPORT_LIVRAISON_210.md
- .gitignore
- app/build.gradle
- app/src/main/AndroidManifest.xml
- app/src/main/java/com/abissa/mobile/MainActivity.java
- app/src/main/assets/abissa_mobile.html
- deploy-cpanel/index.html
- deploy-cpanel/mobile.html
- deploy-cpanel/refonte-2026.js
- deploy-cpanel/badge-workshop.js
- deploy-cpanel/community-mobile.js
- deploy-cpanel/community-admin.js
- deploy-cpanel/community.css
- deploy-cpanel/badge-designer.js
- deploy-cpanel/api/index.php
- deploy-cpanel/api/user-features.php
- deploy-cpanel/api/evolution.php
- deploy-cpanel/api/credentials.php
- deploy-cpanel/api/credential-workshop.php
- deploy-cpanel/api/account-deletion-model.php
- deploy-cpanel/api/community.php
- deploy-cpanel/api/badge-design.php
- deploy-cpanel/api/push-worker.php
- DOCUMENTATION_IOS/00_ARCHITECTURE_ET_ETAT.md
- DOCUMENTATION_IOS/01_PREPARATION_IOS.md
- DOCUMENTATION_IOS/02_COMPTE_APPLE_DEVELOPER.md
- DOCUMENTATION_IOS/03_CONFIGURATION_XCODE.md
- DOCUMENTATION_IOS/04_CERTIFICATS_ET_SIGNING.md
- DOCUMENTATION_IOS/05_CONFIGURATION_APP_STORE_CONNECT.md
- DOCUMENTATION_IOS/06_TESTFLIGHT.md
- DOCUMENTATION_IOS/07_PUBLICATION_APP_STORE.md
- DOCUMENTATION_IOS/08_GENERATION_IPA.md
- DOCUMENTATION_IOS/09_MISE_A_JOUR_APPLICATION.md
- DOCUMENTATION_IOS/10_DEPANNAGE_IOS.md
- DOCUMENTATION_IOS/11_CHECKLIST_PUBLICATION.md
- DOCUMENTATION_IOS/12_NOTIFICATIONS_ET_LIENS.md
- DOCUMENTATION_IOS/MIGRATIONS_ET_API.md
- DOCUMENTATION_IOS/README.md
- ios/ExportOptions.plist.example
- ios/AbissaMobile/AbissaMobile.entitlements
- ios/AbissaMobile/AbissaViewController.swift
- ios/AbissaMobile/AppDelegate.swift
- ios/AbissaMobile/Info.plist
- ios/AbissaMobile/LaunchScreen.storyboard
- ios/AbissaMobile/PrivacyInfo.xcprivacy
- ios/AbissaMobile.xcodeproj/project.pbxproj
- ios/Config/Shared.xcconfig
- ios/Config/Signing.local.xcconfig.example
- ios/scripts/build-ios.sh
- ios/scripts/generate-associations.py
- ios/AbissaMobile.xcodeproj/xcshareddata/xcschemes/AbissaMobile.xcscheme
- ios/AbissaMobile/Assets.xcassets/Contents.json
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/Contents.json
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/ios-marketing-1024-1.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/ipad-20-1.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/ipad-20-2.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/ipad-29-1.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/ipad-29-2.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/ipad-40-1.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/ipad-40-2.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/ipad-76-1.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/ipad-76-2.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/ipad-83.5-2.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/iphone-20-2.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/iphone-20-3.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/iphone-29-2.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/iphone-29-3.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/iphone-40-2.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/iphone-40-3.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/iphone-60-2.png
- ios/AbissaMobile/Assets.xcassets/AppIcon.appiconset/iphone-60-3.png
- ios/AbissaMobile/Assets.xcassets/LaunchLogo.imageset/Contents.json
- ios/AbissaMobile/Assets.xcassets/LaunchLogo.imageset/launch-logo.png

Les ressources Web Android/iOS sont générées à partir des modules partagés par qa-tools/bundle-mobile.py. Les scripts de build/tests/documentation sont livrés sous qa-tools ; les clés privées, config.php, données utilisateurs et anciens paquets sont exclus du nouveau paquet de livraison.

## Livrables finaux

APK 2.1.0 signé avec le certificat historique et AAB 2.1.0/build21/API36 signé avec la clé d’envoi Play existante. Source iOS, documentation Apple, backend et back-office sont regroupés dans `00 - ABISSA FINAL/Abissa-Mobile-v2.1.0-a-heberger-2026-10-04/` et son ZIP voisin. `CONTENU-SHA256.txt` et `qa-tools/package-210-results.json` contrôlent les fichiers et l’archive. Les clés et les données privées sont exclues. Aucun IPA ni déploiement n’a été effectué.
