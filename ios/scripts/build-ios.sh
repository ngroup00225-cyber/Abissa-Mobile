#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ "$(uname -s)" != Darwin ]]; then echo 'Un Mac avec Xcode est requis.' >&2; exit 1; fi
ACTION="${1:-simulator}"
if [[ "$ACTION" == clean ]]; then xcodebuild -project AbissaMobile.xcodeproj -scheme AbissaMobile clean; exit; fi
python3 ../qa-tools/bundle-mobile.py
case "$ACTION" in
 simulator) xcodebuild -project AbissaMobile.xcodeproj -scheme AbissaMobile -configuration Debug -sdk iphonesimulator -destination 'generic/platform=iOS Simulator' -derivedDataPath build CODE_SIGNING_ALLOWED=NO build ;;
 archive) xcodebuild -project AbissaMobile.xcodeproj -scheme AbissaMobile -configuration Release -destination 'generic/platform=iOS' -archivePath build/AbissaMobile.xcarchive -allowProvisioningUpdates archive ;;
 export) test -f ExportOptions.local.plist || { echo 'Copiez ExportOptions.plist.example vers ExportOptions.local.plist et renseignez teamID.' >&2; exit 1; }; xcodebuild -exportArchive -archivePath build/AbissaMobile.xcarchive -exportPath build/export -exportOptionsPlist ExportOptions.local.plist -allowProvisioningUpdates ;;
 *) echo 'Usage: bash scripts/build-ios.sh clean|simulator|archive|export' >&2; exit 1 ;;
esac
