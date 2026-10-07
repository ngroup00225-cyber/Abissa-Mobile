# Réaménagements du back-office — 5 octobre 2026

Les changements sont implémentés dans les sources actives `deploy-cpanel/`. Le paquet contient uniquement les fichiers à remplacer ou ajouter sur la version 2.1.1 existante. Les vérifications utilisent des comptes et données fictifs isolés. La mise en ligne est manuelle.

## 1. Fichiers modifiés et ajoutés

| Périmètre | Fichiers sous deploy-cpanel/ | Rôle |
|---|---|---|
| Navigation | index.html, rearrangements.js, rearrangements.css, settings-201.js | Trois catégories, sous-groupes, catégories repliables et accès aux réglages existants |
| Laissez-passer | access-admin.js, pass-editor.js, pass-renderer.js, access-ui.js | Gestion, personnalisation, aperçu, documents exportés et contrôle |
| Impression | print-workshop.js, badge-workshop.js, community-admin.js, badge-designer.js, workflows.js, credential.js | Quantités intégrées, format physique, pages, PDF et anciens parcours compatibles |
| API et stockage | api/index.php, api/pass-customization.php, api/access-model.php, api/access-control.php, api/badge-design.php, api/credentials.php, api/evolution.php, api/community.php | Validation, migration, sécurité QR, paramètres et conservation des données |

Cela représente 22 fichiers de code. Les nouveaux fichiers sont `rearrangements.js`, `rearrangements.css`, `pass-editor.js`, `print-workshop.js` et `api/pass-customization.php`. Les références de cache des scripts concernés sont mises à jour dans index.html.

Documentation actualisée : README.md et GUIDE_LAISSEZ_PASSER_211.md. Tests ajoutés : qa-tools/test-rearrangements.py, qa-tools/test-rearrangements-ui.cjs et qa-tools/test-rearrangements-pdf.py. Les trois fichiers JSON de résultats et les captures sont conservés dans qa-tools/.

## 2. Nouvelles fonctions

Le menu respecte les catégories SITE WEB, APPLICATION et PARAMÈTRES GÉNÉRAUX. Laissez-passer apparaît une seule fois, sous SITE WEB. Les anciens liens Modèles de badges, Slider accueil et le groupe Contrôle d’accès sont retirés du menu. Les outils de catégories/zones, de scan et d’historique restent des actions du module Laissez-passer avec leurs permissions propres. Les données et fonctions correspondantes sont conservées.

Les pages Abissa en direct, Lexique et les onglets des paramètres sont accessibles depuis les emplacements demandés. L’éditeur Modules & navigation continue de sauvegarder les noms et l’ordre des modules à l’intérieur de leur catégorie ; un module ne peut pas être remis dans une mauvaise catégorie par son ancienne configuration.

Nouvelles routes : GET admin/access-zones ; GET/PUT admin/badge-print-settings. Cette dernière accepte les permissions BADGES_PRINT ou BADGES_MANAGE et protège les écritures par CSRF.

## 3. Modifications de stockage et migration

Le projet utilise des fichiers JSON privés, pas une base SQL. Aucune table SQL n’est créée.

La migration `2026-10-05-pass-print-v1` s’applique une seule fois sous le verrou transactionnel existant. Elle sauvegarde access_passes.json, state.json et submissions.json dans `data/backups/before-2026-10-05-pass-print-v1/` avant toute modification. Sa trace se trouve dans migrations.json. Elle ajoute les présentations manquantes et les paramètres d’impression par défaut, en conservant identifiants, données, dates, nonces et signatures existantes.

Pour chaque laissez-passer, access_passes.json conserve désormais `appearance` : titre, couleur HEX, texte central, image supérieure, image inférieure, texte inférieur et liste ordonnée de champs avec icône/libellé/valeur. Les auteurs createdBy et updatedBy sont conservés avec createdAt et updatedAt. Les identifiants, statuts, catégories, droits, dates, génération et nonce QR restent dans le registre existant. Les images importées restent dans uploads/.

BADGE_SETTINGS dans state.json conserve largeur, hauteur, marges, orientation et feuille. Chaque travail préparé dans badge_print_jobs.json conserve aussi les dimensions et quantités utilisées. Les quantités de la sélection sont mémorisées localement par compte ; leur valeur utilisée dans une impression est enregistrée dans le travail serveur.

## 4. Fonctionnement du laissez-passer

Ajouter un laissez-passer ouvre le formulaire à gauche et l’aperçu à droite. Sur mobile, l’aperçu passe en dessous. Une création enregistre directement la présentation et les accès dans la même opération. Les actions du dossier sont verrouillées pendant l’enregistrement afin d’éviter des écrans remplacés par une réponse tardive. Le nom du titulaire sert à gérer le dossier ; aucun champ véhicule, plaque ni téléphone n’est imposé.

Titre et texte central sont libres. Palette, HEX et RGB sont synchronisés. La couleur pilote les deux côtés, le bandeau, le trait sous le texte central, la barre inférieure et le motif de sécurité. Le sceau officiel et les photographies décoratives conservent leur identité visuelle. Les textes s’adaptent à l’espace disponible.

Jusqu’à huit lignes personnalisées peuvent être ajoutées, retirées et déplacées. Chaque ligne conserve son icône. L’image supérieure peut être importée, remplacée, supprimée ou restaurée ; le rendu utilise une fusion progressive. L’image et le texte du bas sont facultatifs. L’aperçu et l’export utilisent le même moteur de dessin. Un brouillon sans enregistrement affiche un emplacement QR explicitement non valide.

La catégorie définit les règles d’accès ; la couleur graphique ne donne pas de droits supplémentaires. Des zones spécifiques et interdictions peuvent être attribuées au pass. La date de début est obligatoire, la date de fin est facultative. Les restrictions du profil sont vérifiées au contrôle.

## 5. Fonctionnement du QR et de l’anti-duplication

Le QR contient l’identifiant aléatoire du dossier et un nonce de 32 octets signé par HMAC SHA-256 avec une clé privée serveur. Il ne contient pas les renseignements personnels. Le serveur contrôle signature, existence, statut, génération, dates, catégorie, type d’accès et zones. Le résultat agent affiche aussi la couleur personnalisée, l’identité et les accès effectifs.

Le libellé imprimé est SCANNER POUR VÉRIFICATION. Un guilloché de la couleur choisie entoure le QR, avec sa zone blanche de lisibilité préservée, et un filigrane discret ABISSA / ORIGINAL est ajouté. Le code court permet un contrôle manuel authentifié ; il suit les mêmes règles et journalisation qu’un QR.

Deux admissions rapprochées à des points différents déclenchent une suspicion selon la fenêtre existante, 30 secondes par défaut. Des admissions répétées au même point peuvent également déclencher la suspicion : dix admissions dans une fenêtre de 60 secondes par défaut. Les seuils sont modifiables depuis les outils du module et peuvent être désactivés avec 0. La réponse est LAISSEZ-PASSER SUSPECT — COPIE / DUPLICATION DÉTECTÉE. Les contrôles sont journalisés ; un réessai réseau du même contrôle ne compte pas deux admissions.

Suspension et révocation sont immédiatement appliquées. Une réémission invalide l’ancien QR et l’ancien code court. La clé existante n’est pas remplacée par la migration. Une connexion au serveur et une permission de contrôle agent sont nécessaires pour autoriser un passage.

## 6. Quantités de badges

Chaque personne sélectionnée affiche son propre contrôle moins/champ numérique/plus dans sa ligne. La valeur initiale est 1, minimum 1 et maximum 100 par personne ; un travail contient au maximum 500 exemplaires. Une désélection retire les copies et masque le contrôle.

L’aperçu se met à jour sans rechargement et distingue nombre de personnes, nombre de badges et nombre de pages. La sélection et les quantités sont conservées pendant les filtres. Les exemplaires utilisent le même QR de badge existant ; le fonctionnement de vérification des badges n’est pas changé.

## 7. Formats d’impression

Largeur et hauteur se règlent en centimètres, avec 9,5 × 13,5 cm comme valeur normale. Marges, orientation et A4/feuille au format du badge sont également configurables. La mention « sans téléphone ni matricule » est retirée.

L’aperçu utilise des dimensions CSS en millimètres et des pages calculées explicitement. Le nombre de colonnes et lignes dépend de l’espace imprimable après marges. Un format trop grand est refusé avec un message clair. Les badges restent entiers sur chaque page, avec break-inside: avoid.

Le PDF utilise ces mêmes dimensions, converties en points physiques, pour sa MediaBox et le positionnement de chaque badge. Le bouton Imprimer ouvre sa fenêtre dès le clic, puis y charge le même PDF. Exemple vérifié : 3 copies + 2 copies au format 100 × 140 mm, A4 portrait et marges de 6 mm = cinq badges sur trois pages, deux par feuille. Le format standard 95 × 135 mm permet quatre badges par A4 avec ces marges.

## 8. Tests effectués

64 groupes de contrôles réussis, plus vérification syntaxique des 12 scripts JavaScript et huit fichiers PHP concernés :

- 23 groupes de régression communauté, comptes, modération, notifications, modèles et anciennes impressions ;
- 15 groupes de régression QR, permissions, CSRF, altération, dates, zones, usage unique, réémission, suspicion et historique ;
- neuf groupes API supplémentaires sur données temporaires, dont migration sans changement de nonce, création complète sans fin de validité, validations, suspension, duplication, code court, formats et scans répétés ;
- treize groupes navigateur Chrome à 1440 × 1000 et 390 × 844, dont vert, changement immédiat de texte, import d’image, suppression/restauration, RGB, ajout/réorganisation des lignes, 3 + 2 copies, formats, ouverture d’impression et compte BADGES_PRINT ;
- quatre contrôles des PDF réellement téléchargés depuis l’administration : nombre de pages et copies, MediaBox, dimensions de chaque badge et absence de débordement. Les QR sont décodés après rendu des PDF paysage et portrait.

Les neuf scénarios minimum de la demande sont couverts. Aucune exception JavaScript n’a été relevée. Les rendus ont été inspectés ; la barre décorative ne traverse pas le QR en portrait.

## 9. Limites et mise en service

Les essais mobile sont réalisés dans le navigateur avec une largeur de 390 px. La caméra d’un téléphone réel, le papier à échelle 100 %, les marges matérielles de l’imprimante et la concurrence sur l’hébergement doivent encore être vérifiés. Une imprimante qui impose des marges ne peut pas produire un A4 sans bordure ; choisir un matériel adapté ou un réglage de marges adéquat. Lors de l’impression des PDF, utiliser Taille réelle / 100 %.

Un QR visible peut toujours être photographié. Le guilloché aide le contrôle visuel ; la détection en ligne repose sur des comportements suspects et peut avoir des faux positifs lors de passages légitimes très rapprochés. Elle ne prouve pas à elle seule qu’une photocopie a été faite.

Le paquet s’applique sur les fichiers de la version 2.1.1. Sauvegarder le site et les dossiers privés, puis copier le contenu de `appli/` dans le dossier appli/ existant. Conserver config.php, data/ et uploads/. Le paquet ne les contient pas. La migration s’exécute au premier appel API après remplacement des fichiers. Conserver également la clé pass_signing_secret.json dans les sauvegardes.

Pour les imports jusqu’à 5 Mo, configurer PHP avec upload_max_filesize au moins 5M et post_max_size au moins 8M. La limite effective de l’hébergeur reste applicable. Les profils, points et agents doivent être configurés par l’organisation avant usage opérationnel. Les agents disposant uniquement d’ACCESS_SCAN utilisent la page autonome `appli/access-control.html`.
