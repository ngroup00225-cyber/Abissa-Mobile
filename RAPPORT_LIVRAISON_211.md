# ABISSA MOBILE 2.1.1 — livraison locale du 4 octobre 2026

Android : 2.1.1/code22, com.abissa.mobile, API 36. iOS : sources 2.1.1/build22, ci.abissa, iOS 15 minimum. Le projet conserve Java/WebView, UIKit/WKWebView et PHP/JSON ; aucun remplacement du moteur existant.

## Fonctionnalités finalisées

Le slider possède deux commandes symétriques dans son cadre. Les menus utilisent vingt icônes colorées locales (dix-neuf Microsoft Fluent Color et un mégaphone ABISSA), avec un fond côtier atténué. La dernière correction utilisateur est appliquée à tous les boutons : texte centré, petites flèches à droite retirées, Blog et Votre avis compte dans la même famille d’icônes. Espace publicité utilise un nouveau mégaphone orange/doré à poignée bleue. La licence et la source des icônes sont dans assets/fluent-color/.

Les laissez-passer reprennent le visuel officiel fourni. Le titre de couleur, le niveau, le titulaire, le service, le véhicule, le numéro et la validité sont dynamiques. Les QR et textes d’exemple du visuel sont remplacés. Les PDF A4 portrait/paysage contiennent un QR unique vérifiable côté serveur et un filigrane avec le numéro.

L’administration gère les couleurs/niveaux, zones, restrictions, journées, horaires, types piéton/véhicule et points attribués aux agents. Elle permet création, modification, suspension, réactivation, annulation, suppression logique, réémission et historique. Les demandes existantes restent accessibles.

Le scan exige un agent autorisé, un point affecté et une réponse serveur. Le backend vérifie le jeton HMAC, le dossier, le statut, les dates, les règles et l’usage unique éventuel. Deux admissions rapprochées à des points différents peuvent déclencher une suspicion. Une réémission invalide les anciens QR. Les contrôles utilisent des identifiants de requête pour réessayer sans double admission ; un ancien résultat autorisé ne contourne pas une suspension, une expiration ou une réémission ultérieure.

## Vérifications

Les rapports dans VERIFICATIONS et qa-tools décrivent les tests réellement réalisés : API PHP sur données fictives isolées, fonctions communautaires existantes, syntaxe PHP/JavaScript, paquet APK et signatures, AAB, parcours navigateur, PDF et QR décodés depuis les pages rendues. Le total est enregistré dans package-211-results.json après l’assemblage.

Les PDF ont une page aux dimensions A4 exactes dans chaque orientation. Le QR est décodable depuis le rendu Poppler à 150 dpi dans les deux formats et correspond au même pass de test. La mesure papier et la caméra physique restent à vérifier.

Les références et les captures finales ont été comparées ensemble. Les résidus d’icône du modèle, le filigrane coupé et le fond de menu trop présent ont été corrigés. Les données du slider et les profils photographiques diffèrent dans les fixtures ; les barres Android de la référence ne sont pas ajoutées à la page web. Voir VERIFICATIONS/design-qa-211.md.

## Livraison et installation

Le dossier et le ZIP sont sous `00 - ABISSA FINAL/Abissa-Mobile-v2.1.1-a-heberger-2026-10-04`. Ils comprennent SITE-A-HEBERGER, l’APK, PLAY-STORE, SOURCES-ANDROID-IOS.zip, DOCUMENTATION_IOS, le guide opérateur et les vérifications. Les empreintes et le contenu des archives sont contrôlés. Les clés privées, les comptes/données de production, config.php et les profils Apple locaux sont exclus.

Le serveur existant reste la destination manuelle. Sauvegarder api/config.php, data/ et uploads/ avant copie. Sauvegarder aussi les nouveaux registres de pass et leur clé HMAC dès mise en service. Lire GUIDE_LAISSEZ_PASSER_211.md pour l’attribution des permissions, les points de contrôle et les nouvelles impressions. Les anciens QR simples de laissez-passer sont refusés.

L’APK conserve le certificat historique. L’AAB utilise la clé d’envoi Play existante ; son APK universel de test est fourni séparément. L’interface Android/iOS est regroupée depuis les mêmes sources et les vingt icônes sont embarquées pour le premier lancement.

## Limites à vérifier sur le terrain

Aucun déploiement, envoi Play Console ni upload TestFlight effectué. Aucun appareil/émulateur ADB disponible. Les essais navigateur du HTML embarqué avec API indisponible ne constituent pas un démarrage Android réel. Les sources Swift ne sont pas compilées sur Windows ; Xcode, signature, IPA, iPhone/iPad, APNs et TestFlight nécessitent un Mac et les comptes propriétaire.

Le scan caméra, le HTTPS, le stockage/journal serveur, la concurrence/charge et les règles d’organisation doivent être validés sur l’hébergement et les appareils réels. Une photo d’un QR reste possible : la détection concerne les usages anormaux, pas la reconnaissance visuelle d’une photocopie. Le délai de suspicion doit tenir compte des déplacements légitimes entre points. Le filigrane aide l’agent sans garantir l’originalité du papier.

Les fonctions existantes de badges/accréditations et les modules communautaires sont conservés. Le contrôle d’accès nouveau concerne les laissez-passer ; les badges existants gardent leur circuit d’impression et leur propre validation.
