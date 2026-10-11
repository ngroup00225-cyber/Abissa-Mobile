# ABISSA Mobile — correction du démarrage iOS — 11 octobre 2026

Le correctif local est prêt pour une compilation macOS. Version commerciale 2.1.2,
minimum de build 30, Bundle ID `ci.abissa`, équipe `XY97926U94` conservés.
Aucune IPA n'a été produite sur ce poste Windows, aucune exécution Codemagic ni
installation TestFlight du correctif n'est confirmée. Aucune publication effectuée.

## Diagnostic et niveau de preuve

L'architecture disponible est une application Swift/UIKit contenant une WKWebView,
un pont natif et une interface HTML embarquée. Ce n'est pas un projet Flutter.
La page initiale est `abissa-resource://app/abissa_mobile.html`, et non la page
HTTPS ouverte par Safari. Le bon fonctionnement du site dans Safari ne teste donc
pas le chemin de chargement initial de l'application native.

Le message rapporté est exactement celui de `didFailProvisionalNavigation` dans
le contrôleur. Le gestionnaire de ressources pouvait produire une erreur avant
le chargement de la page : il normalisait le chemin du fichier candidat avec
`standardizedFileURL`, puis le comparait au chemin brut du dossier `Web`.
Une transformation `/private/var/...` vers `/var/...` suffit à rejeter le fichier
pourtant présent. La comparaison asymétrique est un défaut réel du code ; elle
constitue une cause plausible du problème sur appareil physique.

Apple décrit les transformations du préfixe `/private` dans ses opérations de
[normalisation des chemins](https://developer.apple.com/documentation/foundation/nsstring/standardizingpath).
Le traitement d'un schéma personnalisé passe par les réponses du
[WKURLSchemeTask](https://developer.apple.com/documentation/webkit/wkurlschemetask).

**La cause exacte du Build 29 n'est pas confirmée par un journal d'appareil.**
Nous ne disposons ici ni de son IPA, ni de ses journaux, ni d'un iPhone connecté.
Les fichiers locaux du projet sont la base du correctif ; leur identité avec le
commit exact du Build 29 n'a pas été établie. Le dépôt GitHub fourni avait renvoyé
404 lors des tentatives précédentes ; aucune modification distante n'est annoncée.

Le nouveau test macOS compile le vrai gestionnaire Swift et enregistre notamment
`oldPrivatePrefixBugReproduced`. Ce résultat devra être lu après exécution CI ;
il n'est pas présenté comme obtenu sur Windows.

Autres défauts corrigés : double décodage possible du chemin, absence de
diagnostics permettant de distinguer fichier manquant/erreur de navigation,
bouton Réessayer qui rechargeait simplement l'état courant, et test simulateur
qui pouvait réussir alors que le processus affichait l'alerte de démarrage.

## Fichiers de production modifiés

| Fichier | Changement |
| --- | --- |
| `ios/AbissaMobile/BundleResourceHandler.swift` | Gestionnaire extrait du contrôleur ; même résolution des liens et normalisation pour dossier/fichier ; comparaison par composants ; décodage unique ; refus des sorties de dossier et liens symboliques externes ; MIME/UTF-8 explicites ; diagnostics sans chemins privés. |
| `ios/AbissaMobile/AbissaViewController.swift` | Vérification de la présence du menu avant état prêt ; chargement explicite lors de Réessayer ; erreurs provisoires/commises et annulations distinguées ; récupération automatique limitée à une tentative après arrêt du processus WebKit ; ouverture du site uniquement sur action de l'utilisateur. |
| `ios/AbissaMobile.xcodeproj/project.pbxproj` | Ajout du nouveau fichier Swift à la cible existante. |
| `ios/Config/Shared.xcconfig` | Minimum de build passé de 25 à 30 ; version, identité et équipe inchangées. |
| `ios/scripts/validate_web_bundle.py` | Contrôle de la page, des modules indispensables, des références locales et de l'intégrité des ressources. |
| `ios/scripts/verify-app-store-ipa.py` | Contrôle du dossier Web réellement inclus dans l'IPA et comparaison avec les sources validées, après les contrôles de signature existants. |
| `codemagic.yaml` | Ajout des contrôles de ressources et du test Swift aux deux workflows ; collecte du résultat Swift. Intégration Apple, profils, publication et étapes existantes conservés. |

Les tests `test-ios-simulator-212.py` et `test-app-store-connect-212.py` sont
actualisés. Trois nouveaux scripts QA vérifient les ressources localement,
le vrai gestionnaire Swift sur macOS et l'interface HTML dans Chrome.

Le pont natif, les règles d'ouverture de liens, les téléchargements, le partage,
la localisation, les notifications, les favoris, le scanner, les informations
de confidentialité et les entitlements sont conservés. Aucun effacement du
stockage, changement d'origine applicative, contournement ATS ou certificat créé.
Le gestionnaire continue d'exposer le pont exclusivement à l'origine embarquée.
Android et son AAB Release signé ne sont pas modifiés par cette correction.

## Contrôles réellement exécutés sur Windows

- 11 tests du correctif : intégrité des 134 fichiers/56 références, ressources
  manquantes et traversée de dossier rejetées, plancher 30 et collision 30→31,
  identité/configuration Codemagic préservées, référence Xcode, plists,
  conservation du pont/des fonctions et intégrité de l'AAB Android.
- 22 tests Apple simulés : pagination et erreurs, sélection du numéro de build,
  absence d'écriture sur erreur, contrôles d'identité/profil/entitlements,
  schéma officiel Codemagic mis en cache. Aucun appel authentifié Apple.
- Interface HTML embarquée dans Chrome local, schéma personnalisé réécrit en
  HTTP local uniquement dans le test et toutes requêtes externes bloquées :
  démarrage, 20 tuiles, expression de disponibilité du contrôleur vraie,
  six services natifs présents, aucune erreur JavaScript non gérée.
  Captures accueil/profil inspectées.

Les dépendances QA Windows nécessitent le Python 3.12 fourni par Codex ; Python
3.13 ne charge pas leur extension native rpds. Les tests finaux ont réussi avec
le runtime compatible. Ce problème d'outillage n'est pas une panne iOS.

Résultats : `qa-tools/test-ios-startup-local-20261011-results.json`,
`qa-tools/test-app-store-connect-212-results.json`,
`qa-tools/ios-interface-20261011/results.json`.

## Contrôles à exécuter sur Codemagic et iPhone

1. Importer le contenu de `correctif/` du ZIP à la racine du dépôt, en conservant
   tous les chemins. Ne pas importer seulement le ZIP dans GitHub. Le contenu
   `ios/AbissaMobile/Web`, les sources partagées et le reste du projet doivent
   déjà être présents dans le dépôt. Choisir le nouveau commit pour les builds.
2. Lancer `ios-212-validation`. Le workflow reconstruit les ressources, exécute
   le vrai gestionnaire Swift sur macOS, compile l'app et démarre les trois
   simulateurs. Il exige `ABISSA_STARTUP_READY` après contrôle du menu dans la
   WKWebView ; la survie du processus seule ne suffit plus.
3. Vérifier le résultat Swift, les captures et les fichiers `*-startup.log`.
   En cas d'échec, conserver les lignes `ABISSA_RESOURCE_FAILED` /
   `ABISSA_STARTUP_FAILED`, le domaine et le code, avant toute autre modification.
4. Après validation, lancer **une seule fois** `ios-212-archive` sur le même
   commit avec l'intégration existante `Abissa-Mobile-Apple`. Le script existant
   interroge l'historique Apple complet ; 30 est le minimum. Si 30 est déjà
   réservé ou si le compteur CI l'exige, il choisit un numéro supérieur.
   Le numéro effectif figure dans `ios/ci-build-metadata.json`.
5. La signature est réalisée par Codemagic avec les certificats/profils existants.
   L'IPA est vérifiée (identité, distribution, push, liens universels et ressources)
   avant le transfert configuré vers App Store Connect. Le workflow conserve
   `submit_to_testflight: false` et `submit_to_app_store: false` : aucune soumission
   automatique à une révision bêta externe ou publique. Nous n'avons lancé aucun
   de ces workflows depuis cette session.
6. Après traitement Apple, affecter le build aux testeurs internes selon la
   configuration du compte. Mettre à jour depuis TestFlight sur le même iPhone
   **sans désinstaller**, pour vérifier la conservation de la session et des
   favoris. Tester Wi-Fi, réseau mobile, démarrage sans réseau, scanner et refus
   caméra, notifications, localisation, partage et téléchargement.

La génération d'une IPA et la confirmation de la résolution sur l'iPhone restent
des validations externes nécessaires. Ce ZIP est un correctif de sources, pas une IPA.
