# ABISSA iPhone validation

Select branch `fix/ios-demarrage`, workflow **ABISSA - iOS iPhone validation**
(ID `ios-212-validation`), machine `mac_mini_m2`. The workflow is manual.
It uses Xcode 27.0, the stable release listed by Apple and available on the
Codemagic M2 image on 13 October 2026. Xcode 27.1 is still listed as RC.

The app is Swift/UIKit with a bundled WKWebView, not Flutter or React Native.
Its frameworks ship with the iOS SDK; there is no Podfile or Swift package
dependency to install. The committed `ios/AbissaMobile/Web` interface is validated
and compiled directly. This workflow does not rebuild or test Android.

## Evidence required

1. Configuration and resource tests must pass.
2. Xcode must compile the real simulator app.
3. Two fresh compatible iPhone simulators must install and open that app.
4. The real WKWebView must render Home, Programme, Histoire, Sites, Langue and
   Profil, with native services present and no captured JavaScript runtime error.
5. The existing production public-data bridge and WebKit HTTPS audio fetch must
   return valid public JSON. These are read-only calls, without test accounts.
6. The native `abissa://open?page=programme` link must open Programme.
7. After termination, a second process must reach the native readiness marker.

Download `ios/iphone-validation/results.json`, each `*-webview.json`, startup and
restart logs, screenshots, and `ios/build-validation.log` from build artifacts.
The summary records the exact commit. All three fields `nativeStartupVerified`,
`nativeNavigationVerified` and `networkVerified` must be true. Check screenshots.
Failures or missing evidence stop validation. Compilation alone is insufficient.

Navigation uses DOM button clicks in the real iOS WKWebView; it does not certify
physical taps or replace XCUITest/device testing. The harness is opt-in, compiled
only for Debug simulator execution, and excluded from signed archives. No
authenticated actions, orders, messages or store uploads are performed.

## TestFlight preparation

After successful simulator results, select the same validated commit and run
`ios-212-archive` (**ABISSA 2.1.2 - signed archive (manual upload)**).
The existing `Abissa-Mobile-Apple` integration supplies the existing profiles and
certificates. Do not generate replacement signing keys.

Identity remains `ci.abissa`, team `XY97926U94`, version `2.1.2`, minimum iOS 15
and minimum build 30. The archive script reads all Apple build history to select
a higher build. Failure to authenticate or enumerate history stops the archive.
The IPA verifier checks distribution identity, version/build, profile expiry,
Push and Associated Domains capabilities, and embedded interface integrity.

Download the verified IPA and metadata. No workflow publishes to App Store
Connect or submits to TestFlight/App Store. Upload requires owner authorization.
Existing signing access and actual certificate/profile availability are only
confirmed by a successful signed archive, not by these source checks.

Before distribution, test on a physical iPhone: upgrade over the existing
TestFlight app, retained session/favorites, cold offline launch, reconnection,
camera/photos/location permissions, notifications, downloads, sharing and forms.
Simulator success does not certify these device features.

References:
- https://developer.apple.com/xcode/system-requirements
- https://docs.codemagic.io/specs-macos/xcode-27-0/
