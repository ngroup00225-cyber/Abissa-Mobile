# Laissez-passer et contrôle d’accès ABISSA 2026

Version 2.1.1 / build 22. Mise à jour et hébergement manuels.

## Personnalisation ajoutée le 5 octobre 2026

Titre, texte central, couleur (palette/HEX/RGB), lignes avec icônes et ordre, image supérieure fondue et image/texte du bas sont modifiables avec aperçu immédiat. Aucune immatriculation n’est imposée. La catégorie conserve ses règles de droits indépendamment de la couleur graphique. Le QR reste signé par le serveur. Le code court sous le QR est accepté par le contrôle agent et change lors de la réémission. Les admissions répétées au même point peuvent aussi déclencher une suspicion : seuil et fenêtre réglables, 10 admissions en 60 secondes par défaut.

Pour l’impression des badges, ouvrir Site web → Impression. Chaque sélection a une quantité de 1 à 100, au maximum 500 exemplaires par travail. Saisir le nombre directement dans la case, sans boutons +/− ni Éditer. Le format reste fixé à 9,5 × 13,5 cm ; les membres et accréditations sont automatiquement disposés par quatre sur A4 portrait (2 × 2), avec 8 mm entre les badges pour la découpe. Le PDF répète chaque badge selon sa quantité ; imprimer à 100 %.

La correction du 5 octobre retire les anciens dossiers de laissez-passer une seule fois et invalide leur accès, après sauvegarde dans data/backups/before-2026-10-05-print-restoration-v1/. Les nouveaux dossiers créés après cette mise à jour ne sont pas retirés lors des rechargements. Elle rétablit aussi la mise en page habituelle des badges tout en conservant les noms, fonctions et photos personnalisés. Ne pas supprimer sa trace dans data/migrations.json.

## Mise en service

1. Sauvegarder le site, `appli/api/config.php`, `appli/data/` et `appli/uploads/`. Copier les fichiers publics de la livraison en conservant ces dossiers privés.
2. Dans l’administration, ouvrir Site web → Laissez-passer → Catégories, zones et points de contrôle. Définir les zones, les journées/événements, les profils de couleur et les points de contrôle. Un point appartient à une zone et possède un type piéton ou véhicule.
3. Attribuer explicitement les permissions aux comptes concernés : `PASSES_MANAGE` pour les pass, `ACCESS_SETTINGS` pour les règles, `ACCESS_SCAN` pour les agents, `ACCESS_HISTORY` pour le journal, `BADGES_PRINT` ou `BADGES_MANAGE` pour les aperçus. Un compte secondaire ne reçoit aucun droit automatiquement.
4. Affecter chaque agent à ses points de contrôle. Vérifier les règles avec des pass de test avant l’ouverture des accès au public.
5. Créer un pass ou traiter une demande reçue. Renseigner le titulaire, la couleur, les dates, le type d’accès et le format. Choisir une catégorie active et la date de début ; la date de fin est facultative. La présentation graphique et les zones autorisées se configurent dans le formulaire.
6. Télécharger le PNG ou enregistrer le PDF A4 portrait/paysage. Le titre imprimé dépend du profil choisi, y compris un texte personnalisé VIP, STAFF, PRESSE ou ORGANISATION. Vérifier un exemplaire papier à échelle 100 %.

## Règles de couleur

Profils initiaux entièrement modifiables : rouge (toutes les zones), bleu (organisation/backstage/logistique), vert (stands/logistique), jaune (espace public), violet (presse/public), noir (VIP/protocole) et orange (logistique). Ce sont des points de départ, à valider avec l’organisation.

Chaque profil possède un titre imprimé, un niveau, une couleur, les zones autorisées/interdites, les types piéton/véhicule et des restrictions facultatives de dates, jours de semaine, horaires et événements. Les interdictions ont priorité. Les règles modifiées s’appliquent au prochain contrôle, y compris pour les documents déjà imprimés. Les horaires sont interprétés dans Africa/Abidjan et restent dans une même journée.

Un pass peut remplacer les zones autorisées du profil et ajouter des interdictions. Les autres contraintes du profil restent applicables.

## Contrôle

L’agent utilise l’action Scanner / vérifier du module Laissez-passer (avec la permission de gestion) ou `appli/access-control.html`, se connecte, choisit son point et scanne le QR avec la caméra. Le lien peut aussi être collé dans le champ. Une réponse serveur est obligatoire ; une panne réseau ne donne aucune autorisation.

Le résultat affiche autorisation/refus, profil, niveau, numéro, identité, validité, zones et compteurs. L’accès n’est acquis que lorsque la réponse affiche **ACCÈS AUTORISÉ**. Un simple visiteur qui ouvre le QR ne voit pas l’identité et ne peut pas autoriser un passage.

La règle « Une seule admission » refuse une deuxième admission. Un nouvel identifiant de contrôle est créé pour chaque nouveau scan. Après une erreur réseau, le même contrôle est réessayé sans compter deux fois le passage.

## Suspension, réémission et suspicion

Suspendre bloque immédiatement le pass et permet sa réactivation. Annuler ou supprimer bloque également le pass. La suppression est logique et conserve l’historique. La réémission produit un nouveau QR et invalide tous les anciens exemplaires ; le numéro du dossier reste stable. Un pass annulé ou suspect doit être réémis pour redevenir actif.

Deux admissions rapprochées à des points différents peuvent marquer le pass comme **LAISSEZ-PASSER SUSPECT — COPIE / DUPLICATION DÉTECTÉE**. Le délai est paramétrable de 0 à 600 secondes, 30 par défaut ; 0 désactive cette détection. Un transfert légitime entre points peut aussi déclencher cette règle : choisir un délai adapté au terrain et prévoir un responsable pour examiner les alertes.

Un QR peut être photographié. Le système vérifie sa signature HMAC, son état, ses restrictions et l’historique ; il ne peut pas distinguer visuellement l’original d’une copie. Le filigrane et le numéro aident le contrôle humain, sans constituer une preuve d’authenticité à eux seuls.

## Sauvegarde et API

Le système réutilise `submissions` pour les dossiers. Les nouveaux fichiers privés sous `data/` sont `pass_access_config.json`, `access_passes.json`, `pass_signing_secret.json`, `access_scan_receipts.json` et `access_scans.jsonl`. Sauvegarder l’ensemble de `data/`, notamment la clé de signature. Sans cette clé, les QR existants ne peuvent plus être validés ; le serveur refuse de remplacer silencieusement une clé absente lorsqu’il existe des pass.

| Route sous /appli/api/ | Méthodes | Permission |
|---|---|---|
| admin/access-config | GET, PUT | ACCESS_SETTINGS |
| admin/access-profiles | GET | PASSES_MANAGE, BADGES_MANAGE ou BADGES_PRINT |
| admin/access-passes | GET, POST, PUT, PATCH | PASSES_MANAGE |
| admin/access-checkpoints | GET | ACCESS_SCAN, points affectés |
| admin/access-scan | POST | ACCESS_SCAN, cookie + CSRF |
| admin/access-history | GET | ACCESS_HISTORY |
| access/inspect | GET | QR signé, lecture publique limitée, aucun passage |

Les journaux retournent les 500 derniers contrôles correspondant à la recherche. Ils indiquent agent, date, point, zone, pass et résultat ; ils ne révèlent pas le jeton du QR. Les mutations passent par le verrou de transaction de l’API et les écritures atomiques existantes. Les QR simples des anciennes impressions de laissez-passer sont refusés : configurer les dossiers et imprimer de nouveaux documents.

## Validations réalisées et restantes

API PHP réelle sur données fictives isolées : permissions, CSRF, QR altérés, zones, dates, usage unique, suspension, réémission, suspicion et journal vérifiés. Parcours navigateur, PDF A4 et QR décodés depuis les pages rendues contrôlés localement.

Restent les essais avec caméra de téléphone, les tests de charge et de concurrence sur l’hébergement réel, la mesure papier, l’entraînement des agents et la validation des règles par l’organisation. Les applications Android/iOS utilisent les mêmes règles serveur ; la page de contrôle est responsive. La compilation/signature iOS doit être effectuée sur Mac. Aucun déploiement ni publication de store n’a été réalisé.
