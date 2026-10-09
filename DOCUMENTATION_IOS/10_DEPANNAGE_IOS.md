# 10 — Dépannage

| Symptôme | Contrôle et correction |
|---|---|
| No signing certificate / provisioning profile | Ajouter le compte dans Xcode, choisir la bonne Team, vérifier Bundle ID et capacités, autoriser la création des profils. |
| ci.abissa indisponible | Utiliser l’identifiant déjà enregistré par le propriétaire ou un nouvel identifiant qu’il choisit ; mettre à jour les configs, APNs et AASA ensemble. |
| Web/abissa_mobile.html introuvable | Exécuter le regroupement et vérifier le dossier Web dans Copy Bundle Resources. |
| Écran web vide | Vérifier le log WKWebView, le résultat du build, les ressources locales et le scheme abissa-resource. Ne pas remplacer l’écran par une URL distante pour masquer une erreur. |
| Requêtes API bloquées | Vérifier HTTPS, chaîne TLS, DNS et OPTIONS/CORS. L’utilisateur utilise Bearer, l’admin reste même origine avec CSRF. Aucun Access-Control-Allow-Credentials avec origine * n’est nécessaire. |
| Upload 413 ou 415 | JPEG/PNG/WebP de 3 Mo pour photos ; PDF/JPEG/PNG/WebP de 5 Mo pour justificatifs ; vérifier les limites PHP et le MIME réel. |
| Auth 401 / 403 | Session expirée, compte bloqué/suspendu, permission absente ou CSRF ; se reconnecter, sans ajouter des droits globaux. |
| Badge vide / erreur canvas | Contrôler images HTTPS, CORS des images distantes et taille des données ; un fond invalide n’est pas ignoré silencieusement. |
| PDF trop grand | Réduire le nombre de badges par travail ; maximum 500, avec images à résolution fixe. Imprimer à 100 %, sans ajustement à la page. |
| Push APNs absent | Consentement iPhone, capacité Push, profil, token, environment, certificat serveur, topic et worker ; la boîte dans l’application reste distincte. |
| Universal Link ouvre le navigateur | Team ID/AASA, HTTPS sans redirection du fichier, Associated Domains, app réinstallée après modification et ouverture depuis une autre app. |
| Caméra/Photos/Localisation refusée | Réglages iOS > ABISSA MOBILE, puis réactiver. Vérifier les descriptions Info.plist et les parcours avec refus. |
| App Store rejette un build | Lire le motif, contrôler SDK, captures, confidentialité, complétude et contenus communautaires, corriger et incrémenter le build. |

Les journaux doivent masquer tokens, mots de passe et données personnelles. Ne pas copier config.php ou un certificat privé dans un ticket de support. Consigner les résultats des contrôles sur Mac : le dossier ne prétend pas avoir exécuté ces étapes depuis Windows.
