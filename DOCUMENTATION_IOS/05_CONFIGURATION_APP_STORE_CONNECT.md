# 05 — App Store Connect

Dans My Apps > + > New App, choisir iOS, le nom ABISSA MOBILE, le français comme langue principale, le Bundle ID réservé et un SKU interne unique, par exemple ABISSA-MOBILE-IOS. Le SKU est interne et n’est pas le Bundle ID. Pour une fiche existante, utiliser cette fiche au lieu d’en créer une seconde.

Renseigner les propositions dans APP_STORE_METADATA.md après validation par le propriétaire : sous-titre, description, mots-clés, catégorie, copyright et coordonnées de support. Les URLs proposées de support/confidentialité doivent être déployées, publiques et accessibles sans connexion. Les écrans de connexion ne peuvent pas les remplacer.

Remplir le questionnaire d’âge selon les fonctions réellement offertes et la modération. Déclarer les contenus créés par les utilisateurs. Dans App Privacy, déclarer les noms, numéros de téléphone, e-mails facultatifs, identifiants de compte, photos, contenus et tokens d’appareil réellement transmis. Les messages privés sont liés à l’utilisateur connecté ; ne pas annoncer « aucune donnée collectée ». Le manifeste PrivacyInfo accompagne le binaire ; il ne remplace pas ce questionnaire. Réexaminer les SDK si d’autres bibliothèques sont ajoutées.

Renseigner le contact de validation, un compte de test fonctionnel et les instructions pour consulter les modules. Le mot de passe de test doit être transmis dans la zone protégée App Review Information, jamais dans le dépôt. Choisir la distribution et les territoires avec le propriétaire. Compléter les exigences commerciales/régionales affichées par Apple pour ce compte.

Importer de vraies captures de l’application iOS testée, aux tailles indiquées dans les métadonnées. Les captures Playwright du dossier QA sont des contrôles de mise en page ; elles ne constituent pas les captures App Store définitives.
