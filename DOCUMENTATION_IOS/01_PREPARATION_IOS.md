# 01 — Préparer les sources iOS

Extraire `SOURCES-ANDROID-IOS.zip` du paquet de livraison dans un même dossier sur un Mac. Conserver les dossiers ios/, app/, deploy-cpanel/ et qa-tools/ côte à côte : le script de regroupement dépend de ces chemins relatifs. Aucun fichier privé de production n’est nécessaire pour compiler le binaire ; l’API déployée sera nécessaire pour les fonctionnalités connectées. Installer Xcode 26 ou version ultérieure compatible avec le macOS du Mac. Apple impose le SDK iOS/iPadOS 26 ou ultérieur pour les envois depuis le 28 avril 2026 : [exigences SDK](https://developer.apple.com/news/upcoming-requirements/?id=04282026a). La cible minimale des appareils de l’application reste iOS 15 ; elle ne doit pas être confondue avec le SDK de compilation.

Ouvrir Xcode une première fois, accepter la licence et installer un simulateur iOS. Installer Python 3 pour le regroupement des ressources ; ce script utilise uniquement la bibliothèque standard. Les icônes préparées sont déjà présentes. Leurs tailles ne nécessitent pas Pillow pour une compilation ordinaire.

Dans Terminal, depuis la racine du projet :

```bash
xcode-select -p
xcodebuild -version
python3 qa-tools/bundle-mobile.py
cd ios
bash scripts/build-ios.sh simulator
open AbissaMobile.xcodeproj
```

Le paquet Web est généré dans ios/AbissaMobile/Web. Ne jamais y placer api/config.php, data/, uploads privés, clés ou mots de passe. Le premier lancement dispose des contenus embarqués ; les fonctionnalités communautaires nécessitent l’API HTTPS et une connexion. Le dernier cache public reste local après synchronisation.

Vérifier l’API https://abissamobile.com/appli/api/health et déployer la nouvelle API avant de tester Blog/Avis/Signalements. Le site doit avoir un certificat TLS valide, une chaîne complète et un DNS accessible en IPv6/NAT64. CORS est géré dans l’API PHP pour les Bearer tokens ; les pages admin doivent rester sur la même origine que leur API. Ne pas contourner un problème TLS avec NSAllowsArbitraryLoads.

Il n’y a aucune commande flutter clean, flutter pub get ou pod install : le projet existant n’est pas Flutter. Nettoyage natif : bash scripts/build-ios.sh clean. Relancer ensuite le regroupement et le build simulateur. Les commandes sont disponibles dans ios/scripts/build-ios.sh.
