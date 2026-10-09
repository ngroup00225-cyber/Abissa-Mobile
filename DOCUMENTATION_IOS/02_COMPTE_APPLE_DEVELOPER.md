# 02 — Compte Apple Developer

Le propriétaire doit posséder un Apple Account avec authentification à deux facteurs et une inscription Apple Developer Program active. Choisir une inscription correspondant au propriétaire juridique réel de l’application. Pour une organisation, suivre les pièces justificatives demandées par Apple. Ne pas inscrire une identité fictive ni utiliser un compte personnel d’un prestataire pour contourner la propriété du projet.

L’Account Holder accepte les accords Apple actuels, puis ajoute les personnes autorisées dans Users and Access d’App Store Connect. Attribuer Developer pour compiler et envoyer les builds, App Manager pour la fiche et la soumission si nécessaire. Éviter le partage de mots de passe ; chaque personne utilise son compte.

Relever le Team ID dans Membership Details. Ce Team ID est une donnée de configuration, distincte de l’adresse e-mail et de l’identifiant de l’app. Créer ios/Config/Signing.local.xcconfig à partir de l’exemple et y renseigner DEVELOPMENT_TEAM. Ce fichier doit rester local ; ne pas y ajouter un mot de passe ou une clé privée.

Dans Certificates, Identifiers & Profiles, réserver l’App ID explicite ci.abissa si disponible. Si une fiche existante utilise déjà un autre Bundle ID, conserver cet identifiant dans Shared.xcconfig ou Signing.local.xcconfig. Ne pas changer le Bundle ID d’une application déjà publiée. Activer Push Notifications et Associated Domains. L’inscription et la disponibilité de ci.abissa ne peuvent pas être garanties avant cette opération dans le compte propriétaire.

Références : [programme développeur](https://developer.apple.com/programs/), [gestion des accès](https://developer.apple.com/help/app-store-connect/manage-your-team/overview-of-accounts-and-roles/).
