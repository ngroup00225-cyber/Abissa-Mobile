# Importer les sources 2.1.2 dans GitHub et Codemagic

Le ZIP `Abissa-Mobile-v2.1.2-SOURCES-GITHUB-CODEMAGIC.zip` contient les sources, les ressources publiques et les scripts nécessaires. La racine de l’archive est directement la racine du dépôt : `app/`, `ios/`, `deploy-cpanel/`, `qa-tools/`, `gradle/` et `codemagic.yaml`.

## GitHub

1. Extraire le ZIP dans un dossier séparé du projet original.
2. Ajouter **le contenu extrait** dans `ngroup00225-cyber/Abissa-Mobile`, en conservant les dossiers. Mettre à jour les fichiers racine existants, notamment `codemagic.yaml`. Utiliser GitHub Desktop ou un clone Git pour conserver toute l’arborescence. Ajouter seulement le ZIP comme fichier au dépôt ne permettra pas à Codemagic de compiler.
3. Vérifier sur GitHub que `ios/AbissaMobile.xcodeproj/project.pbxproj`, `ios/AbissaMobile/NativeCompanion.swift`, `app/libs/zxing-core-3.5.3.jar`, `deploy-cpanel/release-212.js`, `qa-tools/bundle-mobile.py` et `qa-tools/test-ios-simulator-212.py` sont présents.
4. Vérifier le commit final : aucune clé `.jks`/`.keystore`, aucun fichier `.dpapi`, certificat privé Apple, `api/config.php`, donnée de compte ni dossier `uploads/`. Le certificat public `.der` peut rester. Le ZIP a été construit avec une liste de sources autorisées ; ne pas ajouter tout le dossier original qui contient des données privées et des sauvegardes.

La lecture du dépôt public a confirmé qu’il ne contenait initialement que les fichiers racine, sans les sources iOS. La connexion GitHub disponible dans ce chat est en lecture seule. Aucun fichier ni commit du dépôt n’a été modifié par Codex.

## Codemagic

1. Dans l’application reliée au dépôt, actualiser la configuration YAML depuis le commit importé.
2. Lancer manuellement **ios-212-validation**. Ce workflow regroupe les ressources, vérifie les plists, compile avec Xcode et démarre des simulateurs petit iPhone, iPhone récent et iPad. Télécharger `build-validation.log`, les captures et `simulator-qa-212/results.json`.
3. Ces tests vérifient installation/démarrage et survie du processus. Ils ne certifient pas le QR caméra, APNs, les sessions, les formulaires et les fonctions interactives. Examiner les captures et réaliser les essais natifs complets avant publication.
4. Pour produire une archive signée, s’assurer que les certificats/profils **existants** `ci.abissa` sont importés dans Codemagic, avec Push Notifications et Associated Domains autorisés. Lancer **ios-212-archive**. Aucun publishing/TestFlight automatique n’est déclaré dans le YAML.
5. Télécharger l’IPA et les dSYM ; tester l’IPA et effectuer ensuite l’envoi manuel vers App Store Connect/TestFlight. Aucune archive signée ni IPA n’a été produite depuis Windows.

La version reste 2.1.2 ; build Android et iOS : **24**, pour cette correction du Profil. La barre additionnelle « Accueil, Scanner, Favoris, Plus » est retirée. Les fonctions sont accessibles depuis **Profil → Services de l’application**. « À propos » ouvre les informations et liens natifs.

## Liens associés et serveur

Publier le certificat Android historique de `store-assets/well-known-212/assetlinks.json` sur `https://abissamobile.com/.well-known/assetlinks.json`, après vérification. Ce fichier vise l’identité des APK historiques ; ne pas y substituer l’upload key.

Pour iOS, le Team ID Apple propriétaire reste nécessaire. Générer le fichier réel, sans publier de valeur fictive :

```powershell
python ios/scripts/generate-associations.py --team-id VOTRETEAMID --output store-assets/well-known-212
```

Le script exige un Team ID réel de dix caractères ; remplacer `VOTRETEAMID` par celui du compte. Publier `apple-app-site-association` en HTTPS sans redirection. Vérifier le Bundle ID `ci.abissa` et l’identité Team ID dans les profils de signature avant génération.

Les pages de confidentialité et suppression, le backend communautaire et la consultation de badges doivent être mis à jour manuellement en préservant `api/config.php`, `data/` et `uploads/`. Configurer APNs uniquement côté serveur avec les identifiants propriétaire. La livraison ne modifie ni l’hébergement ni les comptes stores.

Référence : [configuration iOS native Codemagic](https://docs.codemagic.io/yaml-quick-start/building-a-native-ios-app/).
