# ABISSA iPhone : guide Codemagic, TestFlight et App Store

Mise à jour : 13 octobre 2026. Branche : `fix/ios-demarrage`.

La photo « L'application n'a pas pu démarrer » confirme un échec de chargement iOS ; elle ne donne pas sa cause exacte. Une compilation verte seule ne prouve pas le bon fonctionnement sur iPhone.

## Corrections et contrôles

L'application est en Swift/UIKit avec une interface embarquée dans WKWebView. Aucun Flutter, React Native, CocoaPods ou paquet Swift externe n'est requis.

Le chargeur résout de la même manière le dossier Web et ses fichiers, notamment pour les chemins iPhone `/var` et `/private/var`. Il refuse les traversées et les liens symboliques sortant du dossier. Ce défaut réel du code antérieur est corrigé ; son implication dans le build installé doit être confirmée par les journaux et un nouvel essai TestFlight.

Le démarrage exige le menu chargé et le module natif JavaScript. Les journaux distinguent erreurs de ressource, de navigation et de processus WebKit. Les liens `abissa://` sont convertis vers l'URL officielle comprise par l'interface. Les ressources sont contrôlées avant compilation et dans l'IPA signée.

Le scénario natif installe l'application sur deux simulateurs iPhone, ouvre Accueil, Programme, Histoire, Sites, Langue et Profil, vérifie les services natifs, deux appels HTTPS, un lien natif et le redémarrage. Le scénario est réservé au simulateur Debug et exclu de l'IPA.

## 1. Lancer la validation iPhone

1. Dans GitHub, sélectionner **fix/ios-demarrage** à la place de **main**. Le bouton Compare & pull request n'est pas nécessaire pour le build.
2. Ouvrir Codemagic > Applications > Abissa-Mobile > **Start new build**.
3. Choisir **Build branch**, puis **fix/ios-demarrage**.
4. Choisir **ABISSA - iOS iPhone validation** (ID `ios-212-validation`).
5. Vérifier le dernier commit de cette branche et lancer un seul build.

Le YAML impose `mac_mini_m2` et Xcode `27.0`. Ce workflow compile directement l'interface iOS versionnée ; il ne construit pas Android, ne demande pas de certificat et n'envoie rien à Apple.

Si l'ancien nom « ABISSA 2.1.2 - iOS simulator validation » apparaît encore, vérifier branche et commit puis actualiser la configuration. Si **Oh no!** / **Loading branches** persiste, ne pas lancer sur main : Codemagic n'arrive pas à charger le dépôt. Réessayer après actualisation et vérifier la connexion GitHub existante. Ne pas supprimer l'application Codemagic ni les certificats.

Le 13 octobre, Codemagic signale aussi des files d'attente M2 allongées. Cela ne prouve pas que cet incident cause l'erreur de chargement des branches.

## 2. Vérifier les résultats avant de signer

Le build doit réussir jusqu'à la dernière étape. Télécharger les artefacts :

- `ios/iphone-validation/results.json` ;
- rapports `*-webview.json`, journaux `*-startup.log` et `*-restart.log` ;
- captures des pages, du lien natif et du redémarrage ;
- `ios/build-validation.log` et résultat du test Swift du chargeur.

Vérifier le commit dans results.json. Les valeurs `nativeStartupVerified`, `nativeNavigationVerified` et `networkVerified` doivent toutes être `true`, avec deux iPhones testés et aucune erreur. Examiner les captures.

La navigation utilise des clics DOM dans la vraie WKWebView ; elle ne certifie pas les gestes physiques ni les permissions sur un appareil réel. En cas d'échec, conserver les artefacts et corriger la première erreur avant d'envoyer une IPA.

## 3. Produire l'IPA signée sans l'envoyer

Après validation, utiliser exactement le même commit et lancer :

**ABISSA 2.1.2 - signed archive (manual upload)**, ID `ios-212-archive`.

Identité conservée : Bundle ID **ci.abissa**, équipe **XY97926U94**, version **2.1.2**, minimum iOS **15.0**, plancher du numéro de build **30**. L'intégration Apple existante est **Abissa-Mobile-Apple**.

Le numéro réel doit dépasser tous les builds chez Apple. Le script lit tout l'historique, y compris les builds en traitement et en échec ; il s'arrête si cet historique ne peut pas être vérifié. Ne pas forcer 30 si ce numéro existe déjà. Ne pas lancer deux archives simultanément.

Dans Codemagic, les profils App Store existants doivent correspondre au certificat Apple Distribution, à ci.abissa, aux notifications Push et aux Associated Domains. Le vérificateur contrôle l'expiration du profil, l'identité, le numéro et les ressources dans l'IPA. Ne pas régénérer ou remplacer les clés existantes pour contourner un échec.

Ce workflow produit l'IPA et `ios/ci-build-metadata.json` sans transfert à Apple. Un IPA téléchargé ne s'installe pas directement via TestFlight.

## 4. Envoyer vers TestFlight quand vous le décidez

Après réussite des étapes précédentes, sélectionner le même commit puis démarrer manuellement :

**ABISSA - iOS TestFlight upload (manual)**, ID `ios-212-testflight`.

**Ce choix envoie l'IPA signée à App Store Connect.** Ne pas le lancer avant validation iPhone. Il reprend les contrôles de l'archive, avec un nouveau numéro supérieur à l'historique Apple. L'intégration existante doit autoriser l'envoi.

Il ne se déclenche pas sur un push. Il ne soumet ni à la révision bêta externe ni à la révision App Store et ne publie pas au public. Lire l'étape Publishing : une compilation ou une signature réussie ne confirme pas l'envoi.

Dans App Store Connect > ABISSA MOBILE > TestFlight, attendre le traitement du nouveau build, compléter les éventuelles informations de conformité, puis l'ajouter à votre groupe interne. Les testeurs externes peuvent nécessiter Beta App Review.

## 5. Vérifier sur votre iPhone

Installer le nouveau build depuis TestFlight par mise à jour pour préserver session et favoris. Vérifier son numéro afin de ne pas retester l'ancien.

Tester : lancement après fermeture complète ; accueil et menu ; navigation ; connexion/déconnexion ; favoris ; mode avion puis reconnexion ; caméra/photos/localisation avec permission acceptée et refusée ; partage ; téléchargements ; formulaires ; notifications. Noter modèle, version iOS, numéro du build et résultat.

Si l'alerte revient, conserver capture, heure, détails TestFlight et artefacts du même build. Ne pas désinstaller comme premier dépannage : cela supprime les données locales.

## 6. Soumettre à l'App Store après l'essai réussi

Dans App Store Connect, aligner la fiche commerciale sur **2.1.2**, sélectionner le build réellement testé et compléter captures iPhone, confidentialité, politique de confidentialité, support, classification d'âge et informations de révision.

Vérifier le parcours de suppression de compte si l'application permet d'en créer un. Si la révision nécessite une connexion, fournir un compte de démonstration fonctionnel dans App Store Connect, jamais dans GitHub.

La soumission finale reste une action du propriétaire. L'acceptation dépend de la révision Apple ; aucun contrôle local ne la garantit.

## Vérifications disponibles

Local : huit contrôles de configuration iOS, syntaxe JavaScript du scénario et 22 tests de numérotation/signature/YAML, dont validation selon le schéma officiel Codemagic. Les tests Apple utilisent des réponses simulées : leurs numéros ne sont pas l'historique réel du compte.

Les tests natifs, la signature réelle et l'envoi ne sont confirmés que par les résultats du build correspondant. Aucun envoi Apple n'a été exécuté pendant la préparation de ce guide.

## Sources

- [Apple : versions Xcode compatibles](https://developer.apple.com/xcode/system-requirements/)
- [Codemagic : Xcode 27.0 et Mac mini M2](https://docs.codemagic.io/specs-macos/xcode-27-0/)
- [Codemagic : envoi App Store Connect](https://docs.codemagic.io/yaml-publishing/app-store-connect/)
- [Apple : TestFlight](https://developer.apple.com/help/app-store-connect/test-a-beta-version/testflight-overview/)
- [État du service Codemagic](https://status.codemagic.io/)
