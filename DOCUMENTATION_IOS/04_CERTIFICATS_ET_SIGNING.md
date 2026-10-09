# 04 — Certificats et signature

La signature ne peut pas être préparée avec de vrais certificats sans accès au compte propriétaire et à un Mac. Aucun certificat Apple n’est inventé ou inclus dans cette livraison. Le projet fournit les entitlements et la configuration de signature automatique.

Dans Xcode > Settings > Accounts, ajouter son propre Apple Account. Dans le target, choisir la Team. Xcode peut créer les certificats Apple Development et les profils de développement adaptés au Bundle ID et aux appareils enregistrés. Pour une archive App Store, laisser Xcode gérer Apple Distribution et le profil App Store correspondant. Les capacités Push/Associated Domains doivent correspondre aux entitlements de l’app.

Pour un mode manuel : créer une CSR dans Trousseaux d’accès, créer le certificat sur le portail, installer le .cer sur le même Mac qui conserve la clé privée, créer un profil App Store pour l’App ID explicite et le certificat de distribution, télécharger et installer le profil. Choisir les bons profils dans Release et les inclure dans ExportOptions.local.plist si signingStyle=manual. Le fichier .cer seul ne suffit pas sans sa clé privée.

Conserver une sauvegarde chiffrée du .p12 avec sa clé et de son mot de passe, hors du dépôt et hors du site public. Restreindre les accès et suivre les dates d’expiration. Ne pas révoquer un certificat partagé avec d’autres apps sans examiner l’impact. Pour les certificats APNs serveur, consulter 12_NOTIFICATIONS_ET_LIENS.md ; ils sont distincts de la signature du binaire.

Avant l’archive, vérifier que la Team, le Bundle ID, le profil et les entitlements concordent. Un simulateur peut compiler CODE_SIGNING_ALLOWED=NO ; une archive distribuable ne peut pas s’en passer. La signature automatique requiert souvent une connexion réseau et les droits Certificates, Identifiers & Profiles.
