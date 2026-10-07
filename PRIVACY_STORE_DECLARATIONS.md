# Déclarations stores — ABISSA MOBILE 2.1.2

Inventaire technique établi à partir du code Android/iOS/PHP et des tests locaux. À confirmer par l’organisation pour l’hébergement, les liens tiers et les usages réels avant de remplir les formulaires. Aucune déclaration n’a été envoyée aux stores.

| Données | Usage et caractère | Conservation / partage |
|---|---|---|
| Nom, identifiant, téléphone, e-mail facultatif | Compte, contact et traitement des demandes ; rattachés au compte | Serveur de l’organisation et hébergeur ; certains noms validés deviennent publics |
| Mot de passe haché et sessions | Authentification et sécurité | Serveur ; token Android des notifications chiffré avec Android Keystore |
| Photo choisie, justificatifs et dossiers | Profil, badge, adhésion, presse, pass, réservation | Accès gestionnaires ; photo de membre validé et badge peuvent être publics ; justificatifs privés |
| Commentaires, Blog, réactions, signalements | Interaction et modération | Commentaires publics ; Blog après approbation ; signalements pour gestionnaires |
| Avis, messages et pièces jointes | Support et traitement des demandes | Privés à l’auteur et aux gestionnaires autorisés |
| Progression et résultats N’zima | Apprentissage et reprise | Stockage local et serveur lorsqu’enregistrés pour un compte |
| Token APNs et lecture des notifications | Notifications iOS facultatives | Organisation et Apple pour la livraison ; Android synchronise la boîte existante sans FCM |
| Favoris, langue, dernière page, cache et fichiers téléchargés | Préférences et disponibilité hors connexion | Stockage local natif ; pas de nouvel envoi de ces préférences au serveur |
| Position approximative sur demande | Carte et repérage de l’utilisateur | Pas d’historique de localisation transmis au serveur ABISSA dans le code contrôlé ; Maps et liens externes ont leur propre politique |
| Images du scanner QR | Lecture locale du QR | Aucune image caméra envoyée par le scanner natif ; la consultation du badge utilise le serveur officiel |
| Données de commande/réservation | Achat de produits physiques et services | L’utilisateur vérifie puis envoie le message WhatsApp/e-mail ; aucune carte bancaire traitée dans les sources contrôlées |
| IP et journaux techniques d’hébergement | Réseau et sécurité | Politique de l’hébergeur à confirmer ; accès tiers selon navigation |

## Google Play Data Safety

Prévoir les catégories Informations personnelles, Photos, Autres contenus utilisateurs, Messages dans l’application et Identifiants selon les fonctions réellement ouvertes. Indiquer leur rattachement au compte et la finalité Fonctionnement de l’application. La position et la caméra sont utilisées sur l’appareil ; ne pas les déclarer automatiquement comme collectées par ABISSA sans vérifier ce qui quitte réellement l’appareil et les services tiers. Compte nécessaire pour interactions/dossiers ; consultation publique sans compte. Échanges authentifiés par HTTPS. Suppression de compte disponible depuis Profil et sur le site.

**Tracking publicitaire : aucun dans les sources contrôlées. Analytics SDK : aucun intégré.** L’Espace publicité affiche des annonces gérées par l’organisation sans SDK de suivi ajouté. L’hébergement peut conserver des journaux ; les pages/flux tiers peuvent avoir leurs propres mesures. Ne pas déclarer « aucune donnée collectée » puisque comptes, contenus et demandes sont envoyés au serveur. [Guide officiel Data Safety](https://support.google.com/googleplay/android-developer/answer/10787469).

## App Store App Privacy

Préparer les catégories Nom, Téléphone, E-mail, Photos/vidéos choisies, Autres contenus utilisateurs, Identifiant utilisateur et identifiant appareil APNs lorsque le push est activé. Finalité Fonctionnement de l’application ; données de compte liées à l’utilisateur ; aucun suivi publicitaire déclaré par le manifeste livré. `PrivacyInfo.xcprivacy` renseigne UserDefaults avec la raison `CA92.1`, les catégories du compte et `Tracking=false`. Le manifeste ne remplace pas les réponses App Store Connect. [Guide officiel App Privacy](https://developer.apple.com/app-store/app-privacy-details/).

## Suppression, conservation et modération

Profil → Supprimer mon compte exige mot de passe et confirmation `SUPPRIMER`. Le serveur efface compte, sessions, dossiers rattachés, photos privées, justificatifs, commentaires, Blog, avis/échanges, réactions, progression et appareils APNs dans son stockage actif et ses sauvegardes gérées. Des fichiers éditoriaux partagés avec un autre compte, copies papier/téléchargées et journaux indépendants de l’hébergeur peuvent subsister. Les tests isolés contrôlent aussi qu’un utilisateur ne peut pas sélectionner le compte d’un autre.

Politique : `https://abissamobile.com/confidentialite.php`. Suppression web : `https://abissamobile.com/suppression-compte.php`. Déployer et tester ces pages en HTTPS avant déclaration : leur disponibilité actuelle n’a pas pu être confirmée. [Exigences Google sur la suppression](https://support.google.com/googleplay/android-developer/answer/13327111).

Les commentaires et articles peuvent être signalés ; l’utilisateur masque les auteurs ; les gestionnaires suppriment/masquent et bloquent/suspendent des comptes. Le serveur applique authentification, limites anti-spam et permissions. Le Blog impose une validation avant publication, les commentaires sont immédiats. L’organisation doit confirmer les contacts publics, les règles d’usage, la surveillance et le traitement rapide des signalements ; les tests locaux ne certifient pas la modération opérationnelle.
