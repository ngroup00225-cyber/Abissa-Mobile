# Importer les sources 2.1.2 dans GitHub et Codemagic

Le ZIP `Abissa-Mobile-v2.1.2-SOURCES-GITHUB-CODEMAGIC.zip` contient les sources, les ressources publiques et les scripts nécessaires. La racine de l’archive est directement la racine du dépôt : `app/`, `ios/`, `deploy-cpanel/`, `qa-tools/`, `gradle/` et `codemagic.yaml`.

## GitHub

1. Extraire le ZIP dans un dossier séparé du projet original.
2. Ajouter **le contenu extrait** dans `ngroup00225-cyber/Abissa-Mobile`, en conservant les dossiers. Mettre à jour les fichiers racine existants, notamment `codemagic.yaml`. Utiliser GitHub Desktop ou un clone Git pour conserver toute l’arborescence. Ajouter seulement le ZIP comme fichier au dépôt ne permettra pas à Codemagic de compiler.
3. Vérifier sur GitHub que `ios/AbissaMobile.xcodeproj/project.pbxproj`, `ios/AbissaMobile/NativeCompanion.swift`, `app/libs/zxing-core-3.5.3.jar`, `deploy-cpanel/release-212.js`, `qa-tools/bundle-mobile.py` et `qa-tools/test-ios-simulator-212.py` sont présents.
4. Vérifier le commit final : aucune clé `.jks`/`.keystore`, aucun fichier `.dpapi`, certificat privé Apple, `api/config.php`, donnée de compte ni dossier `uploads/`. Le certificat public `.der` peut rester. Le ZIP a été construit avec une liste de sources autorisées ; ne pas ajouter tout le dossier original qui contient des données privées et des sauvegardes.

La lecture du dépôt public le 7 octobre avait confirmé qu’il ne contenait initialement que les fichiers racine, sans les sources iOS. Le 9 octobre, le dépôt renvoie une erreur 404 à la connexion GitHub et au clone Git ; le propriétaire vérifie son accès. Aucun commit distant du correctif App Store Connect n’est confirmé. Voir RAPPORT_APP_STORE_CONNECT_212.md pour l’état actuel.

## Codemagic

1. Dans l’application reliée au dépôt, actualiser la configuration YAML depuis le commit importé.
2. Lancer manuellement **ios-212-validation**. Ce workflow regroupe les ressources, vérifie les plists, compile avec Xcode et démarre des simulateurs petit iPhone, iPhone récent et iPad. Télécharger `build-validation.log`, les captures et `simulator-qa-212/results.json`.
3. Ces tests vérifient installation/démarrage et survie du processus. Ils ne certifient pas le QR caméra, APNs, les sessions, les formulaires et les fonctions interactives. Examiner les captures et réaliser les essais natifs complets avant publication.
4. Pour produire une archive signée, conserver les certificats/profils **existants** `ci.abissa`, avec Push Notifications et Associated Domains autorisés. Vérifier que l’intégration **Abissa-Mobile-Apple** peut lire et téléverser les builds de cette application, puis lancer **ios-212-archive** sur **main**, un seul lancement à la fois.
5. Le workflow sélectionne un build iOS croissant, produit l’IPA, vérifie son identité et l’envoie à App Store Connect avec l’intégration existante. `submit_to_testflight: false` et `submit_to_app_store: false` laissent les demandes de révision bêta externe et publique manuelles. Télécharger l’IPA, les dSYM et `ios/ci-build-metadata.json`. Confirmer l’envoi dans les journaux Codemagic et la présence du build traité dans App Store Connect. Aucune archive signée ni IPA n’a été produite depuis Windows.

La version reste 2.1.2 ; Android conserve le build **24**. iOS passe au minimum **25**, augmenté lors du lancement selon l’historique Apple et le compteur Codemagic. La fiche App Store Connect **1.0** devra être alignée sur **2.1.2** avant une future soumission publique. La barre additionnelle « Accueil, Scanner, Favoris, Plus » est retirée. Les fonctions sont accessibles depuis **Profil → Services de l’application**. « À propos » ouvre les informations et liens natifs.

## Liens associés et serveur

Publier le certificat Android historique de `store-assets/well-known-212/assetlinks.json` sur `https://abissamobile.com/.well-known/assetlinks.json`, après vérification. Ce fichier vise l’identité des APK historiques ; ne pas y substituer l’upload key.

Pour iOS, le Team ID communiqué par le propriétaire est **XY97926U94**. Après vérification de ce Team ID dans le profil de signature, générer le fichier réel :

```powershell
python ios/scripts/generate-associations.py --team-id XY97926U94 --output store-assets/well-known-212
```

Le script exige un Team ID réel de dix caractères. Publier `apple-app-site-association` en HTTPS sans redirection. Cette préparation App Store Connect ne modifie pas l’hébergement.

Les pages de confidentialité et suppression, le backend communautaire et la consultation de badges doivent être mis à jour manuellement en préservant `api/config.php`, `data/` et `uploads/`. Configurer APNs uniquement côté serveur avec les identifiants propriétaire. La livraison ne modifie ni l’hébergement ni les comptes stores.

Référence : [configuration iOS native Codemagic](https://docs.codemagic.io/yaml-quick-start/building-a-native-ios-app/).
