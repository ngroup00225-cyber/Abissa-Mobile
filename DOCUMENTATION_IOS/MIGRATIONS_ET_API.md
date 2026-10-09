# Migration JSON, API et déploiement

## Migration

Il n’y a pas de base SQL dans le projet reçu. La migration 2026-10-04-community-v1 est appelée par l’API sous son verrou de transaction. Elle sauvegarde state/users/submissions/admins dans data/backups/before-2026-10-04-community-v1, convertit les commentaires pending en validated (= publiés), fixe les badges à 95 × 135 mm et crée uniquement les collections inexistantes. Elle inscrit sa version dans migrations.json et ne rejoue pas la conversion après marquage. Les commentaires hidden ne sont pas réaffichés.

Collections conservées : state.COMMENTS, users, admins, submissions (les avis sont des contacts), proof_files et data/proofs pour pièces privées, credential_prints pour suivi. Collections nouvelles : blog_posts, blog_categories, comment_reports, comment_likes, post_likes, feedback_messages, badge_templates, badge_designs, badge_print_jobs, user_blocks, community_notifications, community_limits, push_devices et push_deliveries. Les fichiers sont créés à la première écriture ou par la migration. Les sensibles restent sous data/, protégé par le .htaccess existant. Aucun fichier de production fourni n’a été migré pendant les tests.

## Routes

Toutes les URLs sont sous /appli/api/ ; le rewrite Apache conserve Authorization. Utilisateurs : Authorization: Bearer TOKEN. Administrateurs : cookie de session + X-CSRF-Token pour les mutations. Les noms de permissions sont sensibles à la casse.

| Route | Méthodes | Accès / rôle |
|---|---|---|
| comments?newsId=...&contentType=news/event/blog | GET, POST sur comments | Lecture publique, création connectée active ; parentId pour répondre |
| comments/:id/like | POST, DELETE | Utilisateur actif, opération idempotente |
| comments/:id/report | POST | Utilisateur actif, raison et détails, doublon actif refusé |
| admin/comments | GET, PATCH, DELETE | COMMENTS ; recherche q, ids, status hidden/validated |
| admin/reports | GET, PATCH | REPORTS ; statut reviewed/closed |
| admin/users/:id/block/unblock/suspend | POST | users ; état appliqué aux sessions existantes |
| users/blocked | GET, POST, DELETE | Masquage personnel, body userId |
| users/logout | POST | Révocation du token courant |
| blog/categories, blog | GET | Articles publiés uniquement |
| blog, blog/:id | POST, PUT | Propriétaire actif, brouillon/refusé uniquement |
| blog/:id/submit | POST | Propriétaire, passage en attente |
| my-articles | GET | Articles du propriétaire avec motif de refus |
| blog/:id/like | GET, POST, DELETE | Lecture publique, mutation active |
| admin/blog | GET, PUT, DELETE | BLOG ; corrections ne changent pas le statut |
| admin/blog/:id/approve/reject/archive | POST | BLOG ; reason facultatif pour refus |
| feedback | POST | Avis connecté ou visiteur ; noms/téléphone requis, e-mail facultatif |
| my-feedback | GET | Demandes et échanges du propriétaire connecté |
| feedback/:id/reply | POST | Propriétaire, demande ouverte |
| admin/feedback | GET, PATCH | FEEDBACK ; statuts new/read/in_progress/replied/closed |
| admin/feedback/:id/reply | POST | FEEDBACK ; notification au propriétaire |
| public/proof | POST multipart | Authentifié ; pièce privée, MIME/5 Mo contrôlés |
| feedback/attachment?id=... | GET | Propriétaire ou admin FEEDBACK ; téléchargement privé |
| admin/badge-templates | GET, POST, PUT, DELETE | BADGE_TEMPLATES ; duplication par nouveau POST |
| admin/badge-design?id=... | GET, PUT | BADGES_MANAGE ; positions/style/images/overrides validés |
| admin/badges/print-preview | POST | BADGES_PRINT ; ids/quantités/marges/orientation/layout |
| admin/badge-print-jobs | GET, POST | BADGES_PRINT ; travaux préparés, pas preuve papier |
| admin/slider | GET, PUT | SLIDER ; SETTINGS.homeSlides, image/titre/sous-titre/destination |
| notifications | GET, PATCH | Boîte utilisateur ; notifications officielles en lecture partagée |
| admin/notifications | GET, POST | NOTIFICATIONS ; publication explicite et contenu existant |
| devices | POST, DELETE | Token APNs lié à l’utilisateur actif |
| admin/community-stats | GET | Admin ; seules statistiques de ses permissions |

## Permissions et fonctionnement

Super admin conserve ses droits. Les autres comptes ne reçoivent aucune nouvelle permission automatiquement. Presets de départ : contenu = NEWS/EVENTS/SLIDER/BLOG ; modérateur = COMMENTS/REPORTS/BLOG/users ; badges = BADGES_MANAGE/BADGES_PRINT/BADGE_TEMPLATES ; communication = NEWS/EVENTS/SLIDER/NOTIFICATIONS ; avis = FEEDBACK. Ajuster depuis Administrateurs et appliquer explicitement, sans *. Le journal ACTIVITY_LOGS existant reçoit les mutations sensibles. Les clés des anciens comptes sont conservées.

Le statut utilisateur active autorise les fonctions connectées ; blocked/suspended les refusent même avec un ancien token. La lecture anonyme publique reste disponible. Le Blog impose auteur et statut côté serveur et échappe le texte à l’affichage. La liste SETTINGS.communityBlockedWords permet un filtre serveur des commentaires ; plus de cinq liens est rejeté comme spam. Les limites horaires des commentaires, Blog, avis et signalements s’ajoutent aux protections de l’existant.

Badges : quantité entière de 1 à 100 par badge, 500 maximum par travail ; le serveur recalcule total/colonnes/lignes/pages. A4 portrait et marge 6 mm = 2 × 2 badges. Les PDF intègrent les images à 95 × 135 mm et restent imprimables à 100 %. Un travail « prepared » signifie PDF préparé ; la confirmation d’impression existante reste manuelle. Les modèles personnalisés sont appliqués au rendu et au QR de vérification. Les modèles n’altèrent pas les droits d’accréditation.

## Déploiement réversible

1. Sauvegarder les sources serveur et toutes les données/uploads/config privés ; tester la restauration sur un environnement séparé.
2. Copier les nouveaux fichiers publics de deploy-cpanel dans le répertoire appli du site, en conservant api/config.php, data/ et uploads/. Copier les scripts/API PHP actualisés et conserver les .htaccess. Ne pas déployer qa-tools, fichiers signing ou secrets.
3. Vérifier PHP 8, mbstring, fileinfo, droits d’écriture de data/, et HTTPS. Tester health puis se connecter en admin. La migration s’exécute à la première requête de la nouvelle API.
4. Attribuer explicitement les nouveaux droits ; tester les parcours dans la préproduction et le site public conservé. Le paquet de livraison contient les fichiers API actualisés mais aucune donnée ni configuration privée.
5. Installer le nouvel APK ou distribuer par Play Console. Sur Mac, build/TestFlight puis App Store.
6. Pour retour arrière, conserver les collections nouvelles et les commentaires récents. Restaurer les anciennes sources seulement après vérification de leur compatibilité ; ne pas écraser les données postérieures à la sauvegarde.

Variables nouvelles APNs : APNS_CERTIFICATE, APNS_PRIVATE_KEY, APNS_TOPIC. Config locale iOS : DEVELOPMENT_TEAM, PRODUCT_BUNDLE_IDENTIFIER si nécessaire, teamID dans ExportOptions. Aucun nouveau service externe, framework JS ou package PHP requis par les modules ; Python pour le regroupement, Pillow seulement pour régénérer les icônes, Playwright/php-parser pour les tests locaux.

## Contrôle d’accès 2.1.1

Le guide GUIDE_LAISSEZ_PASSER_211.md à la racine détaille les routes access, les permissions, les profils et la mise en service. Les laissez-passer réutilisent les dossiers submissions. Les fichiers privés supplémentaires sont pass_access_config.json, access_passes.json, pass_signing_secret.json, access_scan_receipts.json et access_scans.jsonl sous data/. Sauvegarder tous ces fichiers avec les données existantes. La clé HMAC est créée côté serveur à la première génération ; aucune clé n’est livrée. L’absence d’une clé existante provoque un refus, sans rotation implicite. Les QR simples des anciens laissez-passer nécessitent une réimpression après configuration des accès.

Les permissions ACCESS_SETTINGS, ACCESS_SCAN et ACCESS_HISTORY s’ajoutent aux droits PASSES_MANAGE et BADGES_PRINT existants. Affecter explicitement les agents aux points de contrôle. Le QR ouvre une page web responsive, requiert une session agent et ne permet aucune admission hors ligne. Les zones interdites sont prioritaires ; une réémission invalide tous les anciens exemplaires.
