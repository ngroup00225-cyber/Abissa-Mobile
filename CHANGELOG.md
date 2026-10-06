# CHANGELOG

## 2.1.1 — 2026-10-04

- Accueil : flèches gauche/droite symétriques à l’intérieur du slider ; compteur redondant retiré.
- Menu : vingt icônes Microsoft Fluent Color, y compris Blog et Votre avis compte ; fond côtier atténué, libellés centrés, petites flèches de droite retirées selon la dernière correction.
- Laissez-passer officiels A4 portrait/paysage : titre et couleur dynamiques, identité, numéro, période, filigrane et QR système ; QR exemple et textes fixes du modèle remplacés.
- Administration : profils couleur/niveau, zones autorisées/interdites, horaires/journées/types d’accès, affectation des agents aux points, création/modification/suspension/annulation/suppression logique/réémission et historique.
- Contrôle : QR unique signé HMAC et état vérifiés côté serveur, refus hors zone/période/type, usage unique facultatif, suspicion de scans rapprochés à différents points, réessai idempotent et révocation des anciens QR.
- Permissions dédiées, cookies/CSRF, clé serveur privée, stockage sous verrou et journalisation ; aucune admission sans réponse serveur. Les anciens QR simples de laissez-passer sont refusés.
- Android 2.1.1/code22/API36 : APK historique et AAB clé Play existante. Sources iOS 2.1.1/build22 mises à jour ; compilation Xcode et tests appareils restent à effectuer.
- Guide opérateur, routes/API et rapport de livraison. Aucun déploiement ni publication de store.

## 2.1.0 — 2026-10-04

- Version iOS native réutilisant le moteur existant : projet Xcode, icônes, lancement, permissions, stockage web, partage/copie, Fichiers, localisation, liens et code APNs. Build signé et tests appareils restent à effectuer sur Mac.
- Commentaires immédiats et réponses, Likes idempotents/retrait, signalements, masquage et suppression groupée, recherche et statut actif/bloqué/suspendu.
- Blog : brouillons, aperçu, images, soumission, approbation/refus motivé/archivage, espace Mes articles, interactions sur articles publiés.
- Votre avis compte : 8 catégories, préremplissage connecté, visiteurs, pièces privées, suivi, réponses et Mes demandes.
- Slider : image/titre/sous-titre, liens internes et HTTPS, gauche/droite, swipe, points et autoplay conservé. Correction de capture tactile.
- Badges : quantités, aperçu recalculé serveur, modèles persistants, édition graphique, photo/logo/fond, police/couleur/alignement, QR/Code39, PDF à 95 × 135 mm avec flux d’image indépendant pour chaque copie et encodage mis en cache. Laissez-passer A4 conservés.
- Back-office : nouveaux droits explicitement attribués, presets de rôles, statistiques accessibles selon permission, journal des actions sensibles.
- Sécurité : déconnexion côté serveur, limites d’envoi, filtre commentaires configurable, confidentialité des échanges et uploads, statut Blog/auteur imposés côté serveur, suppression du compte étendue aux nouveaux modules.
- Migration JSON 2026-10-04-community-v1 sauvegardée, idempotente, sans tables SQL dupliquées. Les demandes réutilisent submissions/contact et les commentaires state.COMMENTS.
- Sources Android 2.1.0/code21, partage/copie et liens natifs ; identité et certificat historique conservés.
- Correction finale du contrôle AAB build21, préservation de la clé Play, vérification visuelle de toutes les copies PDF et politique de confidentialité actualisée.
- Documentation iOS, Apple, TestFlight/IPA, routes, variables, migration, déploiement et validations à compléter.

Les routes et dépendances sont détaillées dans DOCUMENTATION_IOS/MIGRATIONS_ET_API.md. La liste des fichiers modifiés et les limites figurent dans RAPPORT_LIVRAISON_210.md. Les anciennes versions ne sont pas supprimées.
