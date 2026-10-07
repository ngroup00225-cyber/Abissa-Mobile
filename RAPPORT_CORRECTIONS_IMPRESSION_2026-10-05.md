# Correction de l’impression — 5 octobre 2026

Cette livraison remplace le paquet Réaménagements précédent et reprend tous ses changements avec les corrections demandées ensuite.

## Changements

- Le badge reprend le modèle habituel : portrait circulaire, nom en majuscules, fonction, commission et QR complet. Les mises en page individuelles existantes sont retirées, en conservant les noms, fonctions et photos enregistrés dans leurs champs personnalisés.
- L’impression garde son format fixe de 9,5 × 13,5 cm. Éditer, +, − et les champs de largeur/hauteur, orientation, marges et feuille sont retirés de cet écran. Les badges membres et accréditations utilisent systématiquement une page A4 portrait avec quatre emplacements de 95 × 135 mm (2 × 2), marges de 6 mm. L’ancien choix 1 document est retiré.
- Chaque ligne affiche la case à cocher, la photo, le nom puis une simple case de quantité. Saisir 3 produit trois copies identiques, dans l’aperçu comme dans le PDF et dans l’action Imprimer. Les quantités acceptées restent 1 à 100 par badge et 500 par travail.
- Les autres fonctions de personnalisation des nouveaux laissez-passer restent disponibles.

## Retrait des anciens laissez-passer

La migration `2026-10-05-print-restoration-v1` s’exécute une seule fois lors d’un appel API concerné après installation. Elle sauvegarde submissions.json, access_passes.json, badge_designs.json et state.json dans `appli/data/backups/before-2026-10-05-print-restoration-v1/` avant modification.

Elle retire tous les anciens dossiers de type pass, quels que soient leurs statuts, et marque leurs entrées du registre comme supprimées afin de refuser leurs codes. Elle conserve les membres, les accréditations, les journaux de contrôle et les secrets QR. Les nouveaux dossiers créés ensuite restent disponibles après rechargement. Ne pas supprimer cette trace dans migrations.json : elle empêche une nouvelle suppression.

Elle rétablit le format 95 × 135 mm et retire les coordonnées et éléments des mises en page individuelles. Les champs de contenu non vides sont conservés avec un indicateur standard afin de garder les portraits et textes personnalisés dans le modèle habituel.

Les fichiers de données du projet local ne contiennent aucun laissez-passer. Le site en ligne n’a pas été modifié depuis cette machine ; le nettoyage de ses dossiers prend effet lorsque le paquet est installé.

## Vérifications

Les tests utilisent un serveur PHP local, Chrome et des données temporaires : retrait de quatre anciens dossiers dans plusieurs statuts, sauvegardes, refus d’un ancien code court, conservation des nouveaux dossiers, retour au modèle standard avec photo conservée, permissions, quantité seule, ligne horizontale et absence des anciens boutons. Vérifications sur ordinateur et mobile, sans exception JavaScript.

Les PDF réellement téléchargés sont inspectés : trois exemplaires identiques du premier badge et deux du second, deux pages A4 ; quatre accréditations sur une seule page A4. Mesures exactes de 95 × 135 mm et aucun débordement. Les anciennes préférences de feuille, orientation et marges sont neutralisées par le serveur. Les PDF des nouveaux laissez-passer restent vérifiés en portrait/paysage avec QR décodé après rendu. Les régressions de communauté, membres et permissions sont également contrôlées.

Résultats détaillés fournis dans VERIFICATIONS. Les sources de test restent dans qa-tools/test-rearrangements.py, qa-tools/test-rearrangements-ui.cjs et qa-tools/test-rearrangements-pdf.py. Aucun test n’écrit dans les données du site en production.

## Installation

Sauvegarder le site et ses dossiers privés. Copier les fichiers du dossier appli de l’archive dans le dossier appli existant. Conserver api/config.php, data et uploads : ils ne sont pas inclus dans l’archive. Recharger le back-office, puis ouvrir Impression ou Laissez-passer pour appliquer la migration. L’ancienne mise à jour peut déjà être installée : celle-ci utilise sa propre trace de migration.

Les références de cache des fichiers modifiés sont renouvelées. L’archive complète inclut aussi credential.html pour que le téléchargement public utilise le format fixe. L’impression sur une imprimante physique n’a pas été réalisée ; les mesures des PDF sont vérifiées.
