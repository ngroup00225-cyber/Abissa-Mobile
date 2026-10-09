# 08 — Archive Release et IPA

Depuis la racine sur Mac :

```bash
python3 qa-tools/bundle-mobile.py
cd ios
cp Config/Signing.local.xcconfig.example Config/Signing.local.xcconfig
# Éditer DEVELOPMENT_TEAM, puis confirmer le Bundle ID.
cp ExportOptions.plist.example ExportOptions.local.plist
# Éditer teamID : utiliser le même Team ID.
bash scripts/build-ios.sh archive
bash scripts/build-ios.sh export
```

L’archive attendue est ios/build/AbissaMobile.xcarchive. L’export produit le .ipa sous ios/build/export ; son nom dépend du produit Xcode. Conserver aussi les dSYM et les logs de compilation pour analyser les crashes. Les fichiers ne sont pas produits sur Windows et ne figurent pas dans cette livraison.

La commande archive utilise Release, generic/platform=iOS et -allowProvisioningUpdates. Elle doit s’exécuter depuis un compte Apple Developer autorisé avec un profil App Store valide. L’exemple ExportOptions utilise method=app-store-connect et signingStyle=automatic, destination=export. Le fichier d’exemple contient un Team ID à compléter ; ne jamais tenter l’envoi en laissant YOUR_APPLE_TEAM_ID.

Alternative graphique : Xcode > Product > Archive, puis Organizer > Distribute App > App Store Connect pour envoyer directement ou exporter. Pour TestFlight et App Store, le même mode de distribution App Store Connect est utilisé. Un export ad-hoc destiné à des appareils enregistrés est un autre mode et ne sert pas à publier dans App Store Connect.

Avant l’envoi, vérifier le numéro de build, le Bundle ID, les entitlements, la signature et l’absence de fichiers privés dans l’app. Ne jamais fournir un IPA non signé comme une version installable. Référence : [envoi de builds](https://developer.apple.com/help/app-store-connect/manage-builds/upload-builds/).
