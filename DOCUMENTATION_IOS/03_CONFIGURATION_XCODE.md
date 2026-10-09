# 03 — Configuration Xcode

Ouvrir ios/AbissaMobile.xcodeproj, choisir le scheme partagé AbissaMobile et le target AbissaMobile. Les sources sont AppDelegate.swift et AbissaViewController.swift ; les ressources sont Assets.xcassets, LaunchScreen.storyboard, PrivacyInfo.xcprivacy et le dossier Web. Vérifier que Web est copié comme dossier : l’exécutable attend Web/abissa_mobile.html.

Dans General : nom affiché ABISSA MOBILE, version 2.1.1, build 22, iOS 15 minimum, appareils iPhone et iPad. Dans Signing & Capabilities, choisir l’équipe et activer Automatically manage signing. Confirmer le Bundle ID. Les configurations Debug/Release utilisent Shared.xcconfig ; Debug a aps-environment development, Release production. Ajouter Push Notifications et Associated Domains si Xcode demande leur enregistrement dans le compte.

Les descriptions de permissions sont dans Info.plist : caméra pour photos choisies, bibliothèque pour sélection/sauvegarde et localisation pour les lieux proches. Aucune collecte permanente de localisation n’est demandée. Le micro n’est pas requis par les nouveaux formulaires. Les vidéos intégrées se lisent dans WKWebView après un geste utilisateur. Le partage natif comporte une ancre de popover pour iPad.

L’app utilise le stockage WKWebsiteDataStore persistant et les zones sûres d’écran. La sélection des photos passe par le sélecteur iOS de WKWebView ; l’export utilise le sélecteur Fichiers. Les sites externes s’ouvrent dans Safari intégré sans accès au bridge de l’application. Le bridge est limité à la frame principale des ressources embarquées.

Pour les liens universels, générer le fichier apple-app-site-association avec le véritable Team ID selon 12_NOTIFICATIONS_ET_LIENS.md et le publier à la racine du domaine. Ne pas activer des domaines appartenant à des tiers. Tester chaque lien partagé dans Messages/Notes et Safari sur un vrai appareil.

Sélectionner un simulateur iPhone petit/grand puis iPad portrait/paysage, et lancer Run. Ne pas considérer la seule ouverture du projet comme preuve de compilation : conserver le résultat de xcodebuild et les essais appareils dans la checklist.
