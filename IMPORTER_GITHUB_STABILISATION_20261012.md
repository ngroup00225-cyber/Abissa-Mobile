# Import manuel du correctif ABISSA 2.1.2

1. Extraire `ABISSA-CORRECTIF-GITHUB-2026-10-12.zip` pour mettre à jour le dépôt
   existant, ou `ABISSA-SOURCES-CORRIGEES-2026-10-12.zip` pour les sources complètes.
2. Dans la branche **fix/ios-demarrage**, copier **le contenu** de
   `SOURCES-A-IMPORTER/` à la racine du dépôt. Les dossiers `ios/`, `app/`,
   `deploy-cpanel/`, `qa-tools/` et le fichier `codemagic.yaml` doivent conserver
   leurs chemins. Ne pas importer seulement le ZIP ni le dossier extérieur.
3. Conserver les fichiers privés de votre installation séparément. Le ZIP ne
   contient ni clé privée ni configuration serveur réelle ni données utilisateurs.
   Ne pas effacer les données du serveur pour installer ces sources.
4. Créer un commit, par exemple :
   `fix: resolve bundled iOS audio URLs and strengthen release validation`.
   Aucun commit n'a été envoyé depuis cet ordinateur : import manuel demandé.
5. Lancer **qa-quick** sur ce nouveau commit. Il regroupe l'interface et vérifie
   les ressources, les parcours et les mises en page avant de consommer du temps
   Xcode. Télécharger ses rapports et captures en cas d'échec.
6. Si vert, lancer **ios-212-validation** sur **le même commit**. Ce workflow
   utilise Xcode 26.6, déjà présent lors du build n°3. Il compile le vrai Swift,
   compare les ressources de l'app compilée, démarre trois simulateurs et vérifie
   le redémarrage après fermeture forcée. Aucun identifiant Apple n'est nécessaire.
7. Exiger un résultat `nativeStartupVerified: true`, aucune ligne
   `ABISSA_RESOURCE_FAILED`/`ABISSA_STARTUP_FAILED`, et inspecter les captures.
   Les nouveaux journaux nomment chaque chemin demandé. Ne pas relancer une
   archive si cette validation échoue.
8. Après validation, lancer **ios-212-archive**, avec l'intégration existante
   `Abissa-Mobile-Apple`. Le numéro n'est sélectionné qu'à cette étape après
   lecture complète de l'historique Apple. Télécharger l'IPA vérifiée.
   **Le YAML corrigé ne téléverse aucun binaire vers un store.** Le responsable
   de publication effectue ensuite le transfert manuel avec son accès existant.

Le minimum iOS reste 15, le Bundle ID reste `ci.abissa`, la version 2.1.2 et le
minimum de build iOS 30 restent inchangés. Android conserve la configuration
Play déjà corrigée `ci.abissa`, code 27, minSdk 24 et cible 36. Les APK historiques
`com.abissa.mobile` constituent une autre identité : ne pas leur promettre une
mise à jour directe par un binaire `ci.abissa`.

## TestFlight sur appareil physique

Après traitement de l'IPA par Apple, le responsable affecte le build aux testeurs
internes. Installer d'abord par-dessus la version TestFlight existante sans la
désinstaller ; vérifier session, favoris et progression. Tester premier lancement,
fermeture forcée/redémarrage, démarrage hors réseau, retour du réseau, rotation,
clavier, tailles de texte, partage, téléchargement, caméra accordée/refusée,
notifications et localisation. Répéter sur iPhone compact, standard et grand,
et iPad. Les simulations de navigateur ne remplacent pas ces essais.

## Google Play sur l'autre ordinateur

Le dossier `ANDROID/` de la livraison contient le nouvel AAB signé. Le responsable
doit comparer le certificat de clé d'importation de son compte et le plus grand
versionCode de toutes les pistes avant import. Le code 27 est conservé ; s'il a
déjà été utilisé, choisir un code supérieur confirmé puis reconstruire avec
`ABISSA_VERSION_CODE`. Ne pas renommer le fichier pour modifier son identité,
ne pas créer une nouvelle clé, ne pas changer le package pour contourner un refus.
La clé d'importation n'est pas nécessairement la clé de signature d'application
gérée par Play. La continuité d'une mise à jour exige le même package et une
signature compatible sur l'appareil.

## Hébergement des liens d'application

Les contrôles HEAD constatent `assetlinks.json` en 403 et
`apple-app-site-association` en 404. Le fichier Apple préparé dans
`HEBERGEMENT/.well-known/` peut être transmis à l'hébergeur pour validation puis
installation en HTTPS sans redirection, avec type JSON. Pour Android, générer
`assetlinks.json` seulement avec **l'empreinte réelle de signature d'application
Play** fournie par le responsable ; ne pas substituer celle de la clé d'importation.
Le générateur `ios/scripts/generate-associations.py` accepte explicitement le
package Android et conserve `ci.abissa` par défaut. Aucun fichier n'a été envoyé
au serveur de production pendant cette intervention.
