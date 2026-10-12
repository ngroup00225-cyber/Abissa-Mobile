# ABISSA Mobile 2.1.2 — corrections et preuves du 12 octobre 2026

Les sources ont été corrigées localement. L'AAB final est compilé et signé avec
la clé d'importation déjà utilisée. Les tests locaux passent. **Le démarrage
WebKit corrigé reste à valider sur Codemagic ; aucune IPA, validation physique,
acceptation Play ou publication n'est annoncée.** L'import GitHub sera effectué
par le propriétaire, conformément à sa dernière demande.

## Diagnostic et correction principale

Le build Codemagic n°3, commit `6558425a012e8b98a060102a63a20a2f02405abf`,
compile sous Xcode 26.6 / SDK simulateur 26.5. Les 16 tests du véritable
gestionnaire Swift passent, mais iPhone SE, iPhone 11 et iPad Pro signalent
`ABISSA_RESOURCE_FAILED -1100`, puis `READY`. Les journaux historiques n'indiquent
pas l'URL en erreur.

L'archive réelle de l'application a été téléchargée depuis Codemagic et inspectée.
Les 56 ressources littérales existent. Une reproduction dynamique du **HTML
exact de ce build**, avec sa base API HTTPS et les ressources locales servies
par un serveur de test, demande `/api/nzema/audio` au serveur local : cette
ressource n'existe pas dans le bundle. Sur l'origine iOS cela correspond à
`abissa-resource://app/api/nzema/audio`. La cause est `learning-v8.js`, qui
utilisait `location.href` comme base aussi pour les API et les enregistrements
distants. La correspondance avec l'unique erreur -1100 est fortement étayée ;
la confirmation finale doit venir des nouvelles traces WebKit.

Les API et enregistrements utilisent maintenant une base serveur distincte.
Les sons embarqués gardent leur base locale. Une tentative audio ayant échoué
hors réseau peut être renouvelée. Les métadonnées audio malformées sont rejetées.
Le gestionnaire Swift journalise chaque chemin demandé/réussi/échoué sans query,
jeton ni contenu personnel. READY exige aussi l'absence de ressource locale en
échec ; une échéance de 30 secondes présente un écran de réessai si nécessaire.
Les contrôles n'ont pas été assouplis pour obtenir un résultat vert.

## Tableau des anomalies

| Problème | Gravité | Correction / traitement | Test réellement effectué | Statut |
|---|---|---|---|---|
| Route audio demandée dans le bundle iOS | P0 | Base HTTPS pour API et enregistrements | Reproduction sur HTML du build n°3, puis zéro requête locale API sur HTML corrigé | Corrigé et testé en navigateur ; WebKit bloqué par l'environnement macOS |
| READY après erreur locale / démarrage sans échéance | P0 | Compteur de ressources et échéance 30 s | Contrôle des sources ; test Swift et simulateurs préparés | Corrigé mais non testé sur iOS |
| Journaux sans URL | P1 | Chemin public sans query à chaque requête | Source Swift revue ; compilation réservée à Codemagic | Corrigé mais non testé sur iOS |
| CSS Leaflet intégré avec mauvaise base | P1/P2 | Conservation du chemin d'origine CSS et trois images officielles manquantes ajoutées | 59 références et 137 fichiers vérifiés, tests CSS imbriqués/casse/URL encodées | Corrigé et testé |
| Erreurs réseau techniques et écran distant sans réessai | P1/P2 | Message français, erreur JSON explicite, bouton Réessayer | Réseau bloqué et réponse HTML invalide, captures Blog/Profil | Corrigé et testé |
| Audios bloqués après premier essai hors réseau | P2 | Promesse libérée et nouvelle tentative possible | Premier échec puis réponse JSON valide simulée | Corrigé et testé |
| Téléversement Apple automatique par le workflow archive | P1 | Section publishing retirée | Schéma Codemagic officiel et tests de contrat | Corrigé et testé |
| Appareil « récent » choisi par ordre de liste | P2 | Sélection numérique de génération | Syntaxe Python et revue ; exécution simctl réservée à macOS | Corrigé mais non testé sur iOS |
| Contrôles imposant d'anciens builds/hashes | P2 | Assertions du comportement courant ; suite rapide maintenue | 12 tests portables, 22 tests Apple, 15 gardes Android | Corrigé et testé |
| assetlinks.json 403 / association Apple 404 | P1/P2 | Générateur corrigé pour ci.abissa ; fichier Apple préparé | HEAD HTTPS de production | Bloqué par le déploiement de l'hébergeur |
| Signature attendue et dernier code Play inconnus | P0 si incompatibles | Clé et code 27 conservés ; procédure au publieur | Certificat local et AAB vérifiés ; aucun accès au compte du publieur | Bloqué par un accès externe |
| Mise à jour historique com.abissa.mobile → ci.abissa | P0 si présentée comme directe | Deux identités documentées ; aucune migration promise | Configuration et signatures historiques examinées | Bloqué pour une mise à jour directe entre packages différents |
| App Store, APNs, WhatsApp et essais physiques | P1 | Procédure et déclarations existantes conservées | Contrôles locaux et tests serveur isolés | Bloqué par les services/appareils et comptes externes |
| Petites tailles / tablette | P2 | Aucune refonte nécessaire ; charte conservée | 80 contrôles sans débordement, huit formats, captures | Non reproduit pour les débordements recherchés |
| Vérification Android native finale interrompue | P1 validation | APK issu du nouvel AAB installé ; manifeste et signature corrects | System UI de l'émulateur bloqué, puis hiérarchie UIAutomator indisponible | Bloqué par l'environnement de test ; parcours natif final non validé |

## Tests et couverture

- 12 tests rapides : fichiers absents, CSS imbriqué, casse exacte, traversée de
  dossier, espaces encodés, templates JS, contrats d'identité et publication manuelle.
- Syntaxe de 49 fichiers PHP et 48 fichiers JavaScript ; plists, privacy manifest,
  entitlements, XML Android, projet Xcode et schéma YAML.
- 22 tests de préparation Apple : pagination complète, erreurs HTTP/JSON/réseau,
  collisions de numéros, identité/signature/profil synthétiques. Aucune requête
  authentifiée Apple réelle et aucune IPA réelle dans ces tests.
- 23 contrôles métier avec PHP réel sur données temporaires : inscription,
  sessions, commentaires, réponses, signalements, masquage, Blog privé/validation,
  droits des autres utilisateurs, avis et réponses privées, suspension,
  permissions/CSRF, badges/quantités/modèles, notifications, déconnexion et
  suppression du compte. Quatre contrôles supplémentaires de suppression
  vérifient les sauvegardes, photos, sessions, documents et conservation d'un autre compte.
- 11 groupes de tests Chromium sur le HTML embarqué corrigé : 32 destinations,
  20 entrées de menu, 80 contrôles de mise en page, panier de deux articles,
  partage/favoris, recherche N'zima et 12 modes, JSON invalide avec réessai,
  retour réseau des audios, session expirée, thèmes et rechargement.
  Aucune ressource locale manquante et aucune exception JS non gérée.
- Android : compilation réelle Java/D8/aapt2, validation bundletool, signature,
  CRC et égalité des 137 ressources ; 15 tests des gardes Gradle réelles.
  La compilation complète par AGP n'a pas été exécutée sur cet hôte.

Le nouvel AAB final conserve le code 27 et contient les dernières corrections
réseau. Son SHA-256 est
`5b00054bd9d21d9a02a744730a9dfffd9d62bd692c4b971d0c390497f1fb38bd`.
L'APK universel produit depuis cet AAB a été vérifié et installé dans l'émulateur
isolé Android 16, mais le parcours interactif final n'a pas abouti : System UI
de l'émulateur a affiché une erreur ANR, puis UIAutomator a échoué. Le succès
d'un ancien AAB ne valide pas ce nouveau fichier. Voir android-final-runtime.json.

Les destinations couvrent accueil, programme, histoire, sites, hôtels/restaurants,
carte, galerie, actualités, partenaires, accréditation, comité, langue, lexique,
direct, guides, palais, site officiel, boutique, publicité, Blog, avis, profil,
événements, panier, notifications, connexion, inscription, mot de passe oublié,
demandes/articles privés et utilisateurs masqués. Le site officiel est une
ouverture externe interceptée dans le test. Les écrans privés exigent une connexion.
Les commandes WhatsApp, envois de messages réels, paiements, notifications APNs
et validations d'accès sur le terrain n'ont pas été déclenchés.

Le test navigateur réécrit le schéma embarqué en HTTP local et simule le pont
natif. Il ne constitue **pas** une exécution iOS/Safari, une validation de caméra,
une vérification complète des vidéos distantes ni un essai sur téléphone physique.

## Audit visuel et étapes capturées

1. Accueil et navigation : logo, carrousel, vingt tuiles, barre de navigation
   présents ; charte conservée. Captures avant/après disponibles, le carrousel
   et le compteur peuvent afficher des instants différents.
2. Modules publics : captures de chaque destination ouverte. Aucun débordement
   horizontal dans les formats testés ; contenus distants indisponibles indiqués.
3. Accès privés : redirection vers connexion selon le compte de test invité.
4. Profil et six services natifs : contrôles accessibles sur huit formats.
5. Panier et détail culturel : quantités, partage et favoris testés sans commande.
6. N'zima : recherche, détail, disponibilité honnête des audios et modes de quiz.
7. Erreurs réseau et réponse JSON invalide : message et réessai visibles.
8. Préférences système clair/sombre et réduction du mouvement : captures.

Formats web : 320×568, 375×667, 390×844, 430×932, 667×375, 768×1024,
1024×768 et 1024×1366. La capture n'atteste pas VoiceOver, clavier iOS, Dynamic
Type ou safe areas de chaque appareil physique ; ces essais restent ouverts.

## API, sécurité, confidentialité et dépendances

Les HEAD publics retournent 200 HTTPS pour health, data, nzema/audio, Blog,
confidentialité et suppression de compte. Les corps de réponses de production
ne sont pas téléchargés : le contrôle d'approbation a refusé cette lecture car
data peut contenir des données sensibles. Les erreurs JSON/401/réseau sont
exercées en local ; aucune donnée de production n'a été modifiée.

Le contrôle d'accès serveur impose utilisateur, permissions administrateur et
CSRF ; les tests tentent aussi des actions intercomptes et des liens exécutables.
Le pont iOS limite les messages au cadre principal embarqué. ATS et HTTPS restent
actifs. La session native Android utilise Android Keystore ; la session de la
partie web utilise encore localStorage, point à renforcer avec un parcours de
migration testé. Les déclarations existantes listent comptes, contenus utilisateur,
photos, appareil et fonctionnalités natives ; leur validation finale dans les
formulaires des stores incombe au responsable du compte.

Les versions QA sont verrouillées dans package.json/package-lock. Leaflet 1.9.4
reste la version stable officielle consultée ; seules ses images manquantes ont
été ajoutées depuis son dépôt officiel. Aucun passage à une version alpha ou
mise à jour massive de bibliothèques n'a été effectué. L'analyse de vulnérabilités
complète des dépendances et un audit d'intrusion de production restent à réaliser ;
aucune certification générale de sécurité n'est revendiquée.

## Fichiers, commits et prochaine validation

Le manifeste `PREUVES/SOURCES-SHA256.json` de la livraison identifie chaque source.
Les modifications principales touchent learning-v8.js, community-mobile.js,
BundleResourceHandler.swift, AbissaViewController.swift, validate_web_bundle.py,
generate-associations.py, MainActivity.java, le bundler, les tests et codemagic.yaml.
Trois images Leaflet sont ajoutées ; les interfaces Android et iOS sont régénérées.
Les configs de version et les clés n'ont pas été changées pendant cet audit.

**Commits distants créés : aucun**, import manuel demandé. La branche est visible
dans Chrome, mais le connecteur et Git distant n'ont pas accès au dépôt privé.
Le dépôt Git parent du profil Windows n'a pas été utilisé pour committer ce projet.

Lire `IMPORTER_GITHUB_STABILISATION_20261012.md` : import des fichiers extraits,
qa-quick → ios-212-validation → archive signée après succès, transfert manuel,
TestFlight sur appareils physiques, puis contrôles Play par le publieur.
Les artefacts des tests sont conservés ; aucun workflow distant n'a été déclenché.

Sources officielles consultées :
[exigences SDK Apple](https://developer.apple.com/news/upcoming-requirements/?id=02032026a),
[publication Codemagic](https://docs.codemagic.io/yaml-publishing/app-store-connect/),
[distribution Leaflet](https://leafletjs.com/download.html).
