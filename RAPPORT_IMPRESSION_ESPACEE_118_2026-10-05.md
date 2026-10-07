# Impression espacée — référence 1.1.8

L’archive de référence Abissa-Mobile-v1.1.8-a-heberger-2026-09-15.zip a été inspectée. Son fichier workflows.js utilise un espace de 8 mm entre les badges sur A4. Ses badges mesuraient alors 90 × 130 mm avec des marges de 10 mm.

La correction actuelle reprend cet espacement de 8 mm, dans les deux directions, tout en conservant les dimensions demandées de 95 × 135 mm pour les badges membres et les accréditations. Les marges latérales sont adaptées à 6 mm : 95 + 8 + 95 + 6 + 6 = 210 mm. Deux rangées tiennent sur A4 : 135 + 8 + 135 + 6 + 6 = 290 mm, pour une feuille de 297 mm de haut.

L’aperçu, le calcul serveur et le PDF utilisent la même disposition. Les images des badges ne sont pas réduites dans le PDF. Seul l’aperçu à l’écran s’adapte à sa largeur disponible pour montrer la feuille entière, y compris sur mobile. La case de quantité est conservée et le nombre d’exemplaires identiques reste respecté.

L’habillage et les données actuels des badges sont conservés ; la référence 1.1.8 est utilisée pour sa règle de disposition et de découpe. Le reste de l’application garde sa version actuelle.

Fichiers modifiés : deploy-cpanel/print-workshop.js, deploy-cpanel/badge-designer.js, deploy-cpanel/api/badge-design.php, deploy-cpanel/index.html. Les références de cache des scripts modifiés sont renouvelées.

Les tests vérifient les espaces entre colonnes et rangées dans le navigateur puis dans les matrices du PDF : pas horizontal de 103 mm (95 + 8), pas vertical de 143 mm (135 + 8). Les tailles des badges, le nombre de pages, les copies identiques et l’absence de débordement sont également contrôlés. Le scénario comprend cinq badges membres sur deux pages A4 et quatre accréditations sur une page A4. Aucun essai sur imprimante physique n’a été effectué.

Le paquet complet remplace la correction A4 précédente. Copier le dossier appli dans le dossier appli existant en conservant api/config.php, data et uploads. Imprimer sur A4, à taille réelle / 100 %. Les anciens dossiers de laissez-passer ne sont pas retirés à nouveau si la migration précédente figure déjà dans data/migrations.json ; aucune nouvelle migration de nettoyage n’est ajoutée ici. Le site en ligne n’a pas été modifié depuis cette machine.
