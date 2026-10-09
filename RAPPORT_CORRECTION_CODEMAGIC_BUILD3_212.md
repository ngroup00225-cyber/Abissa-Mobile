# Abissa Mobile 2.1.2 — correction de l’échec Codemagic n°3

Correctif local du 9 octobre 2026, préparé pour import manuel sur GitHub `main` à la demande du propriétaire. Version iOS **2.1.2**, Bundle ID **ci.abissa**, Team **XY97926U94**, workflow **ios-212-archive**, intégration **Abissa-Mobile-Apple**. Aucun nouveau transfert Apple n’est confirmé.

## Cause identifiée et preuve

L’ancien script exécutait `app-store-connect get-latest-build-number ...`, puis appliquait une expression régulière numérique à toute la sortie standard. L’erreur transmise démontre que la commande avait un code de retour nul, mais que `stdout.strip()` n’était pas un nombre conforme. Le script confondait donc une sortie de résumé non numérique avec une réponse Apple invalide ; les tests initiaux supposaient à tort que l’absence de builds serait représentée par `0`.

Le code officiel de Codemagic montre qu’en l’absence de build trouvé, l’action retourne **None sans imprimer de numéro**. Ce comportement a été reproduit localement avec le véritable SDK **codemagic-cli-tools 0.70.0** : résultat None et sortie standard vide. L’ancien script rejette nécessairement cette sortie vide. [Implémentation officielle](https://github.com/codemagic-ci-cd/cli-tools/blob/17ceb6890bf3072963d22645ab3a022b2d369419/src/codemagic/tools/app_store_connect/actions/latest_build_number_actions.py).

La sortie brute du build n°3 et les réponses authentifiées de ce compte Apple ne sont pas disponibles dans ce chat. **Le défaut de gestion du cas vide est confirmé ; on ne peut pas affirmer que le compte ne contient aucun build.** Une sortie différente, également non numérique, pourrait déclencher le même message. Le correctif ne transforme donc pas une sortie vide de cette commande en autorisation de choisir un premier build.

Autre fragilité vérifiée : le résumé toutes versions ignore certains builds dont la relation `preReleaseVersion` ou la ressource associée n’est pas présente. Le nouveau calcul ne dépend plus de cette relation.

## Lecture de l’API corrigée

`prepare-app-store-build.py` lit désormais les collections JSON brutes de l’API Apple :

- `/v1/apps`, recherche exacte `filter[bundleId]=ci.abissa` et contrôle d’une seule application.
- `/v1/builds`, filtre sur l’ID de cette application. **Aucun filtre de version, plateforme, état ou expiration** : cela inclut aussi les numéros des builds en traitement et des builds sans relation de version disponible.
- `/v1/apps/{id}/appStoreVersions`, plateforme iOS, lecture de `attributes.versionString` pour signaler l’alignement de la fiche commerciale.

La structure utilisée est `data: [...]`, `links: {...}`, et éventuellement `meta.paging.total`. Chaque ressource doit posséder un type attendu, un ID chaîne et un objet `attributes`. Le numéro de build est la **chaîne `builds.attributes.version`** ; la version commerciale de la fiche est **`appStoreVersions.attributes.versionString`**. [Modèle Build de Codemagic](https://github.com/codemagic-ci-cd/cli-tools/blob/17ceb6890bf3072963d22645ab3a022b2d369419/src/codemagic/apple/resources/build.py), [client et pagination](https://github.com/codemagic-ci-cd/cli-tools/blob/17ceb6890bf3072963d22645ab3a022b2d369419/src/codemagic/apple/app_store_connect/api_client.py).

Le script suit `links.next` jusqu’à la dernière page, conserve les filtres d’origine et vérifie les totaux lorsqu’ils sont fournis. Un changement de total, un doublon de ressource, une boucle, une page manquante ou un lien vers un autre hôte/une autre collection provoque l’arrêt. Un tableau vide avec un lien suivant ne signifie pas « aucun build ».

Les états **PROCESSING, VALID, FAILED et INVALID** sont tous pris en compte. Aucun numéro n’est ignoré parce que son build est expiré ou encore en traitement. Un numéro absent, null, numérique JSON au lieu de chaîne, malformé, ou un état inconnu provoque un diagnostic et l’arrêt.

Les réponses HTTP 401, 403, 404, 429 et autres erreurs signalent leur statut et leur catégorie. Une erreur réseau/TLS, un délai de 45 secondes dépassé ou un JSON invalide arrête également la procédure. Aucun de ces cas n’autorise un numéro initial. Aucun extrait de clé, jeton ou diagnostic privé Apple n’est imprimé.

L’authentification réutilise le SDK installé avec les outils Codemagic et les variables de l’intégration existante. Si `python3` diffère du Python des outils installés via pipx, le script réutilise l’interpréteur déclaré par `app-store-connect`. Aucun paquet n’est installé par le workflow, aucune clé n’est créée, aucun cache de jeton ni fichier privé n’est écrit par ce correctif.

## Version et règle de numérotation

Le projet Xcode utilise `Shared.xcconfig` pour Debug et Release. `Info.plist` conserve :

```text
CFBundleShortVersionString = $(MARKETING_VERSION) = 2.1.2
CFBundleVersion = $(CURRENT_PROJECT_VERSION), minimum local = 25
```

Le nouveau numéro est un entier écrit sous forme de chaîne, calculé après validation complète :

```text
max(minimum explicite du projet,
    25 + BUILD_NUMBER Codemagic,
    première composante du maximum numérique Apple + 1)
```

Le maximum compare numériquement les composantes, avec zéros implicites à droite : `9 < 80 < 96.2.1 < 97`. Choisir la première composante plus un produit un entier strictement supérieur à tout l’historique récupéré de l’application, donc aussi aux builds de la même version commerciale. Le calcul est volontairement conservateur entre versions et plateformes.

**Premier build :** uniquement si la requête authentifiée a réussi, si toutes ses pages sont valides et si la collection complète est vide, le minimum explicite **25** devient applicable. Le compteur Codemagic peut l’augmenter. Avec `BUILD_NUMBER=3` et une vraie collection vide, la règle donne **28**. Ce cas a été testé avec des données synthétiques ; 28 n’est pas présenté comme le prochain numéro réel du compte.

Le script exige un compteur CI valide et un minimum au moins égal à 25. La politique de ce projet produit des entiers de 1 à 9999 ; si aucun entier supérieur ne tient dans cette plage, il arrête la procédure et demande une révision manuelle de la politique. Il ne remet jamais le compteur à zéro.

Le numéro définitif reste inconnu ici : il sera indiqué dans le journal de préparation et dans `ios/ci-build-metadata.json` lors du prochain lancement réel. Ce fichier enregistre aussi le nombre de pages, les états, le maximum Apple, le compteur CI et l’état d’alignement de la fiche.

La fiche **1.0** signalée par le propriétaire n’est pas alignée avec le binaire **2.1.2**. Le script consultera les versions iOS réelles et signalera cet écart sans modifier Apple. L’envoi pour TestFlight reste possible avec 2.1.2 ; avant une future soumission publique, aligner la fiche commerciale sur 2.1.2 selon son état. L’état actuel du compte Apple n’a pas été lu depuis cet ordinateur. [Guide Apple d’envoi et d’association des versions](https://developer.apple.com/help/app-store-connect/manage-builds/upload-builds/).

Lancer une archive à la fois et attendre que le build précédent soit visible/traité par Apple avant le suivant. Une lecture API ne réserve pas atomiquement un numéro ; un envoi simultané par un autre outil ou un téléversement encore invisible à l’API ne peut pas être garanti par ce script.

## Contrôle de l’IPA et configuration préservée

`verify-app-store-ipa.py` compare exactement le Bundle ID, la version commerciale et le numéro de build de l’IPA avec les métadonnées préparées. Il rejette désormais aussi un numéro numérique JSON, un numéro à points, 0, une valeur trop longue et des métadonnées d’une autre équipe/application. Le build sélectionné doit être une chaîne d’entier positif.

Les contrôles Apple Distribution, profil App Store embarqué, absence de débogage, Push de production et Associated Domains sont conservés. Les tests rejettent notamment un plist portant `81.0` lorsque le numéro choisi est `81`, même si leur valeur numérique paraît proche.

**`codemagic.yaml` n’est pas modifié par cette correction.** Sa syntaxe et son schéma officiel sont validés. `ios-212-validation`, la préparation, `xcode-project use-profiles`, la construction IPA et les chemins d’artefacts restent en place. L’intégration `Abissa-Mobile-Apple`, `ios_signing: app_store / ci.abissa`, `auth: integration`, `submit_to_testflight: false` et `submit_to_app_store: false` sont conservés.

La version Android demeure **2.1.2 / code 24**, avec `app/build.gradle` identique à la référence avant intervention. Aucune fonctionnalité de l’application ni aucun certificat/profil n’est modifié.

## Fichiers à importer sur main

1. `ios/scripts/prepare-app-store-build.py` — correction de la lecture et du calcul.
2. `ios/scripts/verify-app-store-ipa.py` — renforcement des valeurs de version/build et des diagnostics d’arrêt.
3. `qa-tools/test-app-store-connect-212.py` — suite portée de 6 à 22 tests.
4. `qa-tools/test-app-store-connect-212-results.json` — résultats de la suite.
5. `qa-tools/test-apple-sdk-build3.py` — reproduction du comportement du SDK et vérification d’authentification synthétique.
6. `qa-tools/test-apple-sdk-build3-results.json` — preuve du contrôle SDK, sans requête Apple réelle.
7. `RAPPORT_CORRECTION_CODEMAGIC_BUILD3_212.md` — présent rapport.

Le ZIP contient ces sept fichiers dans `correctif/`, un guide et le diff des deux scripts. Il ne contient pas les dépendances QA, le YAML existant, des clés, des certificats privés ou une IPA. Le suivi local et le rapport initial signalent également cette correction.

Message de commit conseillé : `fix(ios): validate paginated Apple build history before numbering`.

## Contrôles et limites

**22 tests réussis, zéro échec :** réponses vides, valides, malformées et paginées ; maximum sur dernière page ; première page vide avec suite ; manque/changement de total ; doublons, cycles et liens interdits ; erreurs HTTP et réseau ; numéros absents/null/non numériques ; états de traitement ; absence d’écriture après erreur ; version de fiche 1.0 simulée ; identité et version/build IPA ; maintien du YAML et d’Android.

Le SDK officiel **0.70.0** a été testé avec une clé EC synthétique créée uniquement en mémoire : génération et vérification cryptographique du jeton ES256 réussies, import du SDK réussi, cas « aucun résumé de build » reproduit. **Aucune requête Apple authentifiée réelle n’a été effectuée et aucune clé privée n’a été écrite sur disque.** La syntaxe des scripts Python et le schéma YAML officiel passent.

Les fichiers sont corrigés localement. Le propriétaire fera l’import sur GitHub `main` ; aucun commit distant n’est prétendu. Le dépôt renvoie toujours 404 à la connexion de ce chat. Après import, lancer **ios-212-archive** depuis le nouveau commit et conserver ses journaux. La compilation/signature macOS, l’IPA réelle et le transfert App Store Connect restent à confirmer par Codemagic puis Apple.
