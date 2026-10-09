# Abissa Mobile 2.1.2 — préparation App Store Connect

État au 9 octobre 2026 : correctif appliqué aux sources locales, six tests réussis. Le dépôt `ngroup00225-cyber/Abissa-Mobile` renvoie encore 404 à la connexion GitHub et au clone Git. Le propriétaire demande finalement un ZIP à importer lui-même dans GitHub. Aucun commit sur `main`, lancement Codemagic, IPA réelle ou transfert Apple n’est confirmé.

## Fichiers modifiés

| Fichier | Modification |
| --- | --- |
| `codemagic.yaml` | Ajout de l’intégration et du téléversement App Store Connect dans `ios-212-archive`, avec les deux soumissions désactivées ; sélection du build et contrôle de l’IPA avant envoi. |
| `ios/Config/Shared.xcconfig` | Build minimum 24 → 25 et Team ID `XY97926U94`. Version commerciale `2.1.2` conservée. |
| `ios/scripts/prepare-app-store-build.py` | Nouveau : recherche de l’application par Bundle ID et choix du prochain build à partir d’Apple et du compteur Codemagic. |
| `ios/scripts/verify-app-store-ipa.py` | Nouveau : contrôle de l’IPA réelle, de la signature Distribution, du profil, des versions et des capacités avant téléversement. |
| `.gitignore` | Exclusion des clés `.p8`, des métadonnées CI et des dossiers locaux de dépendances, de références QA et de clone. Les exclusions `.p12` et des profils privés sont conservées. |
| `qa-tools/test-app-store-connect-212.py` | Nouveau : six tests de configuration et de logique ; schéma Codemagic officiel et références de conservation du workflow/Android. |
| `qa-tools/test-app-store-connect-212-results.json` | Nouveau : résultats locaux et limites explicites. |
| `qa-tools/validate-212.py` | Actualisation du contrôle de build iOS à 25 ; contrôle Android à 24 conservé. |
| `GITHUB_CODEMAGIC_IMPORT_212.md` | Guide actualisé pour l’envoi automatique et la confirmation manuelle du résultat Apple. |
| `RAPPORT_APP_STORE_CONNECT_212.md` | Présent rapport. |

Le suivi local `qa-tools/EN-COURS.md` est également actualisé. Le ZIP du correctif contient uniquement les dix fichiers ci-dessus, un guide d’import et un diff des deux configurations principales ; aucune dépendance QA ni source privée.

## Configuration conservée

`ios-212-validation` reste identique à la configuration locale avant intervention. Le workflow d’archive conserve `mac_mini_m2`, sa durée maximale, `xcode: latest`, le regroupement des ressources, `xcode-project use-profiles`, ainsi que la commande :

```sh
xcode-project build-ipa --project ios/AbissaMobile.xcodeproj --scheme AbissaMobile
```

Les chemins d’artefacts IPA, journaux Xcode et dSYM sont conservés. Le fichier de métadonnées du build est ajouté aux artefacts. `ios_signing` garde `distribution_type: app_store` et `bundle_identifier: ci.abissa`. Aucun certificat ni profil Codemagic n’est remplacé ou créé. Les sources des capacités Push Notifications et Associated Domains sont conservées.

La configuration ajoutée au niveau du workflow est :

```yaml
integrations:
  app_store_connect: Abissa-Mobile-Apple
publishing:
  app_store_connect:
    auth: integration
    submit_to_testflight: false
    submit_to_app_store: false
```

Ces options configurent l’authentification de l’envoi et désactivent les demandes automatiques de révision bêta externe et App Store. Aucun groupe de testeurs, déclencheur automatique ou règle de publication publique n’est ajouté. [Documentation Codemagic](https://docs.codemagic.io/yaml-publishing/app-store-connect/).

## Version iOS et numéro de build

`Info.plist` utilise `CFBundleShortVersionString = $(MARKETING_VERSION)` et `CFBundleVersion = $(CURRENT_PROJECT_VERSION)`. Les configurations Debug/Release du projet Xcode utilisent `ios/Config/Shared.xcconfig` : avant modification, **2.1.2 / 24** ; après modification, **2.1.2 / minimum 25**. Le Bundle ID reste `ci.abissa` et le Team ID devient explicitement `XY97926U94`.

Le build réel sera choisi dans Codemagic, avant compilation, suivant :

```text
max(build minimum du projet,
    partie entière principale du dernier build Apple + 1,
    25 + BUILD_NUMBER Codemagic)
```

La requête Apple utilise `get-latest-build-number --platform IOS --all-versions`, après recherche stricte de l’application `ci.abissa`. Le numéro réel sera supérieur aux builds connus d’Apple, y compris sur les anciennes versions commerciales. En cas d’erreur d’authentification, de réponse inattendue ou de compteur manquant, la préparation s’arrête avant compilation. Aucun numéro supposé n’est utilisé en remplacement. [Commande officielle Codemagic](https://github.com/codemagic-ci-cd/cli-tools/blob/master/docs/app-store-connect/get-latest-build-number.md).

Le numéro final ne peut pas être annoncé aujourd’hui : l’historique Apple et le compteur Codemagic ne sont pas accessibles ici. **25 est le minimum retenu, pas un build Apple déjà envoyé.** Le numéro 81 visible dans certains tests provient d’une réponse Apple simulée et ne représente pas le compte réel.

Lancer un seul `ios-212-archive` à la fois et attendre le traitement Apple avant le suivant. La lecture de l’historique puis l’envoi ne constituent pas une réservation atomique ; des compilations parallèles ou un autre outil d’envoi simultané pourraient choisir un numéro identique.

La fiche commerciale **1.0**, indiquée par le propriétaire, n’est pas alignée sur la version du binaire **2.1.2**. L’envoi pour TestFlight peut porter la version 2.1.2 ; avant une future soumission publique, aligner manuellement la fiche App Store sur 2.1.2 selon son état. Ce correctif ne modifie pas les métadonnées commerciales Apple. Apple associe les builds par Bundle ID et version, puis doit les traiter avant qu’ils deviennent disponibles. [Guide Apple sur l’envoi](https://developer.apple.com/help/app-store-connect/manage-builds/upload-builds/), [choix d’un build pour une version](https://developer.apple.com/help/app-store-connect/manage-builds/choose-a-build-to-submit/).

Android conserve **versionName 2.1.2 / versionCode 24**. Le fichier `app/build.gradle` est identique octet pour octet à la référence locale avant intervention. Aucun artefact Android n’a été recompilé pour ce correctif.

## Vérifications effectuées

Six tests locaux ont réussi, sans erreur ni échec :

1. Analyse YAML, validation avec le schéma officiel `https://codemagic.io/codemagic-schema.json`, conservation du workflow de validation, de la signature, des commandes et artefacts, et désactivation des soumissions.
2. Versions iOS/Android, références du plist, Bundle ID/Team ID et présence des capacités existantes.
3. Recherche stricte de l’application Apple, rejet des applications absentes, incorrectes ou ambiguës.
4. Numérotation supérieure à l’historique, anciennes versions avec builds à points et progression du compteur ; rejet des valeurs invalides.
5. Simulation d’échec Apple sans écriture de configuration ni métadonnées, puis simulation d’un choix valide.
6. Contrôle des métadonnées IPA simulées et rejet de mauvaises versions, identités, Push de développement, domaines absents et profils de débogage/ad hoc.

Les tests de logique utilisent des données synthétiques. La génération Xcode, les commandes de signature macOS et les requêtes Apple réelles restent à exécuter dans Codemagic. Le script de contrôle est intégré après `build-ipa` : il vérifie l’intégrité ZIP, `codesign`, le profil App Store embarqué, l’identité Apple Distribution, la version/build, Push en production et `applinks:abissamobile.com`. Son exécution macOS réelle n’est pas encore confirmée.

Pour reproduire les tests de ce correctif : installer `PyYAML` et `jsonschema` dans un environnement Python, puis lancer `python qa-tools/test-app-store-connect-212.py`. Le schéma officiel est téléchargé dans le cache QA si absent. Les dépendances ne sont nécessaires qu’à ces contrôles locaux ; les scripts du workflow utilisent la bibliothèque standard Python.

## Commit et lancement

Message de commit préparé : `ci(ios): upload 2.1.2 archives to App Store Connect without review`.

Cible : `ngroup00225-cyber/Abissa-Mobile`, branche **main**. Il faut d’abord pouvoir lire le commit courant et comparer les fichiers distants pour préserver toute configuration fonctionnelle ajoutée depuis la copie locale. Le dossier de travail n’a pas de dépôt Git propre ; le dépôt Git trouvé dans un dossier parent n’est pas utilisé pour fabriquer un commit sans parent GitHub vérifié.

Une fois le correctif présent sur `main`, actualiser le YAML dans Codemagic et lancer **ios-212-archive**. L’intégration existante **Abissa-Mobile-Apple** doit avoir accès à l’application `ci.abissa`, aux requêtes de builds et au téléversement. Les certificats/profils existants doivent autoriser Team `XY97926U94`, Push et Associated Domains.

## Points bloquants et preuve de transfert

- **GitHub : accès 404.** Aucun commit distant ni comparaison de la version actuelle de `main` n’est confirmé.
- **Codemagic : non lancé.** Aucun résultat de compilation/signature n’est disponible pour ce correctif.
- **App Store Connect : historique et transfert non confirmés.** Le build final sera connu après la préparation CI. L’état de la fiche 1.0 provient de l’information du propriétaire.
- **macOS : absent ici.** L’IPA ne peut pas être compilée et sa signature réellement contrôlée sous Windows.

Pour déclarer l’envoi réussi, conserver le résultat de téléversement Codemagic et vérifier dans App Store Connect le build **2.1.2**, son numéro exact et la fin de son traitement Apple. `ios/ci-build-metadata.json` décrit l’IPA contrôlée ; son champ `uploadConfirmed` reste `false` car ce script s’exécute avant le téléversement. Ce fichier seul ne prouve pas l’acceptation Apple.

Aucun fichier `.p8`/`.p12`, mot de passe, clé privée, configuration serveur privée ou donnée utilisateur n’est ajouté au correctif. Aucun transfert Apple ni publication publique n’est annoncé comme réussi.
