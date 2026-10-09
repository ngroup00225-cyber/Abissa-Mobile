# 09 — Mettre à jour

Conserver le Bundle ID, l’équipe propriétaire et la fiche App Store existants. Incrémenter MARKETING_VERSION pour une nouvelle version commerciale et CURRENT_PROJECT_VERSION pour chaque build envoyé. Android utilise son propre applicationId com.abissa.mobile et son versionCode ; ne pas appliquer ci.abissa à Android.

Déployer les migrations et API compatibles avant les nouveaux clients. Sauvegarder data/, uploads/, config.php et les anciennes sources. Les anciens commentaires restent dans state.COMMENTS. L’ancien client 2.0.9 attendait « validation » après son envoi : le serveur accepte désormais ses commentaires immédiatement, mais seule la version 2.1.1 met à jour tout de suite son écran. La mise à jour de l’APK est donc nécessaire pour cette UX.

Après modification HTML/JS/CSS, relancer python3 qa-tools/bundle-mobile.py. Les ressources embarquées dans Android/iOS ne changent pas à la suite d’un simple déploiement web. Compiler, tester, archiver, vérifier l’IPA, envoyer TestFlight, puis soumettre la nouvelle version.

Les modifications de contenu ordinaires restent gérées par le back-office et la synchronisation publique existante. Ne pas remplacer data/state.json par un fichier de démonstration. Ne pas changer les clés de signature Android déjà utilisées. Prévoir un plan de retour arrière qui conserve les nouvelles données ; restaurer aveuglément une ancienne sauvegarde perdrait les demandes récentes.
