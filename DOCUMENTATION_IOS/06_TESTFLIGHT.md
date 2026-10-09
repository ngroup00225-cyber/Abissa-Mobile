# 06 — TestFlight

Après une archive Release réussie et signée, ouvrir Window > Organizer > Archives, sélectionner AbissaMobile puis Distribute App > App Store Connect > Upload. Valider le binaire avant son envoi. L’alternative est l’IPA exportée puis envoyée avec Transporter. L’upload et la publication sont des actions distinctes ; aucun envoi n’a été réalisé depuis Windows.

Attendre le traitement dans App Store Connect > TestFlight. Résoudre les erreurs de signature, d’icônes ou de conformité au chiffrement. ITSAppUsesNonExemptEncryption=false correspond à l’usage standard d’HTTPS sans chiffrement spécifique ajouté ; vérifier cette déclaration après toute nouvelle bibliothèque.

Créer d’abord un groupe de testeurs internes et ajouter le build 22. Renseigner les informations de test : commentaires immédiats, Blog soumis à validation, réponses aux avis et formats de badges. Les groupes externes peuvent nécessiter une validation Beta App Review. Ne pas envoyer d’invitation tant que les autorisations du propriétaire et les coordonnées ne sont pas confirmées.

Sur les appareils : installation propre et mise à jour, inscription/connexion/déconnexion, ouverture hors ligne, reconnexion, caméra/galerie, permissions refusées puis réactivées, clavier, partage natif vers les apps disponibles, liens universels, localisation, notifications sandbox/production et iPad. Tester les sessions bloquées, la confidentialité des demandes, le signalement et le refus d’un Blog. Contrôler les journaux de crash et les retours des testeurs.

Consigner appareil, version OS, numéro de build et résultat dans 11_CHECKLIST_PUBLICATION.md. Corriger les problèmes, augmenter CURRENT_PROJECT_VERSION et envoyer un nouveau build. Référence : [TestFlight](https://developer.apple.com/help/app-store-connect/test-a-beta-version/testflight-overview/).
