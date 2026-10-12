import UIKit
import WebKit
import SafariServices
import UserNotifications
import UniformTypeIdentifiers
import CoreLocation
import os

// Only the bundled application has a native bridge. External sites use SFSafariViewController.
final class AbissaViewController: UIViewController, WKNavigationDelegate, WKUIDelegate, WKScriptMessageHandler, WKDownloadDelegate, CLLocationManagerDelegate {
    private var webView: WKWebView!
    private var pendingURL: URL?
    private var ready = false
    private let resourceHandler = BundleResourceHandler()
    private var startupDeadline: DispatchWorkItem?
    private let startupLogger = Logger(subsystem: "ci.abissa", category: "Startup")
    private let entryURL = URL(string: "abissa-resource://app/abissa_mobile.html")!
    private var startupAlertVisible = false
    private var processRecoveryAttempted = false
    private var downloadURLs: [ObjectIdentifier: URL] = [:]
    private var nativeCompanion: NativeCompanion!
    private var pendingToken: (String, String)?
    private let locationManager = CLLocationManager()
    private var locationRequests: [String] = []
    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = UIColor(red: 0, green: 0.18, blue: 0.35, alpha: 1)
        let config = WKWebViewConfiguration()
        config.websiteDataStore = .default()
        config.allowsInlineMediaPlayback = true
        config.mediaTypesRequiringUserActionForPlayback = [.audio, .video]
        config.setURLSchemeHandler(resourceHandler, forURLScheme: "abissa-resource")
        config.userContentController.add(self, name: "abissa")
        let bridge = """
        window.Android = {
          openWebsite:function(url){window.webkit.messageHandlers.abissa.postMessage({action:'openWebsite',url:url});},
          saveText:function(text,name){window.webkit.messageHandlers.abissa.postMessage({action:'saveText',text:text,name:name});},
          saveCalendar:function(text,name){window.webkit.messageHandlers.abissa.postMessage({action:'saveCalendar',text:text,name:name});},
          share:function(title,text,url){window.webkit.messageHandlers.abissa.postMessage({action:'share',title:title,text:text,url:url});}
        };
        window.__abissaLocationCallbacks = {};
        window.__abissaLocationResult = function(id,coords,error){var p=window.__abissaLocationCallbacks[id];delete window.__abissaLocationCallbacks[id];if(!p)return;if(coords)p.ok({coords:coords,timestamp:Date.now()});else if(p.error)p.error({code:1,message:error||'Localisation indisponible'});};
        if(navigator.geolocation)navigator.geolocation.getCurrentPosition=function(ok,error){var id=String(Date.now())+Math.random();window.__abissaLocationCallbacks[id]={ok:ok,error:error};window.webkit.messageHandlers.abissa.postMessage({action:'location',id:id});};
        """
        config.userContentController.addUserScript(WKUserScript(source: bridge, injectionTime: .atDocumentStart, forMainFrameOnly: true))
        webView = WKWebView(frame: .zero, configuration: config)
        webView.navigationDelegate = self
        webView.uiDelegate = self
        webView.isOpaque = false
        webView.backgroundColor = .systemBackground
        webView.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(webView)
        nativeCompanion = NativeCompanion(owner: self, web: webView)
        let contentTop = webView.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor)
        nativeCompanion.networkChanged = { [weak self] active in contentTop.constant = active ? 0 : 40; self?.view.layoutIfNeeded() }
        NSLayoutConstraint.activate([
            contentTop,
            webView.bottomAnchor.constraint(equalTo: view.safeAreaLayoutGuide.bottomAnchor),
            webView.leadingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.leadingAnchor),
            webView.trailingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.trailingAnchor)
        ])
        nativeCompanion.startNetwork(in: view)
        loadBundledApplication()
        locationManager.delegate = self
    }
    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        // A living native process and didFinish alone do not prove that the UI booted.
        webView.evaluateJavaScript("document.readyState === 'complete' && document.querySelectorAll('#menuGrid .tile').length > 0 && typeof goTo === 'function' && !!window.AbissaNative212") { [weak self] result, error in
            guard let self = self else { return }
            guard error == nil, result as? Bool == true, self.resourceHandler.failedResourceCount == 0 else {
                self.showStartupFailure(error ?? URLError(.cannotParseResponse), stage: "interface")
                return
            }
            self.ready = true
            self.startupDeadline?.cancel()
            self.startupLogger.notice("ABISSA_STARTUP_READY")
            self.nativeCompanion.ready()
            if let url = self.pendingURL { self.pendingURL = nil; self.openContent(url) }
            if let pair = self.pendingToken { self.pendingToken = nil; self.deliverDeviceToken(pair.0, environment: pair.1) }
        }
    }
    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        showStartupFailure(error, stage: "provisional")
    }
    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        showStartupFailure(error, stage: "navigation")
    }
    func webViewWebContentProcessDidTerminate(_ webView: WKWebView) {
        ready = false
        startupLogger.error("ABISSA_WEB_PROCESS_TERMINATED")
        if !processRecoveryAttempted {
            processRecoveryAttempted = true
            loadBundledApplication()
        } else { showStartupFailure(URLError(.cannotLoadFromNetwork), stage: "web-process") }
    }
    private func loadBundledApplication() {
        ready = false
        startupDeadline?.cancel()
        resourceHandler.resetDiagnostics()
        startupLogger.notice("ABISSA_STARTUP_LOADING")
        webView.load(URLRequest(url: entryURL, cachePolicy: .reloadIgnoringLocalCacheData))
        let deadline = DispatchWorkItem { [weak self] in
            guard let self = self, !self.ready else { return }
            self.showStartupFailure(URLError(.timedOut), stage: "deadline")
        }
        startupDeadline = deadline
        DispatchQueue.main.asyncAfter(deadline: .now() + 30, execute: deadline)
    }
    private func showStartupFailure(_ error: Error, stage: String) {
        let failure = error as NSError
        if failure.domain == NSURLErrorDomain && failure.code == NSURLErrorCancelled { return }
        ready = false
        startupDeadline?.cancel()
        startupLogger.error("ABISSA_STARTUP_FAILED stage=\(stage, privacy: .public) domain=\(failure.domain, privacy: .public) code=\(failure.code)")
        guard !startupAlertVisible else { return }
        startupAlertVisible = true
        let alert = UIAlertController(title: "ABISSA MOBILE", message: "L’application n’a pas pu démarrer. Réessayez.", preferredStyle: .alert)
        alert.addAction(UIAlertAction(title: "Réessayer", style: .default) { [weak self] _ in
            self?.startupAlertVisible = false
            self?.processRecoveryAttempted = false
            self?.loadBundledApplication()
        })
        alert.addAction(UIAlertAction(title: "Ouvrir le site", style: .default) { [weak self] _ in
            self?.startupAlertVisible = false
            self?.present(SFSafariViewController(url: URL(string: "https://abissamobile.com/appli/mobile.html")!), animated: true)
        })
        present(alert, animated: true)
    }
    func openContent(_ url: URL) {
        guard (url.scheme == "https" && url.host == "abissamobile.com" && url.path.hasPrefix("/appli/")) || url.scheme == "abissa" else { return }
        guard ready else { pendingURL = url; return }
        let literal = Self.jsonLiteral(url.absoluteString)
        webView.evaluateJavaScript("window.dispatchEvent(new CustomEvent('abissa-deeplink',{detail:\(literal)}));")
    }
    func deliverDeviceToken(_ token: String, environment: String) {
        guard ready else { pendingToken = (token, environment); return }
        let body = Self.jsonLiteral(["token": token, "environment": environment, "platform": "ios"])
        webView.evaluateJavaScript("window.AbissaCommunity?.api('devices','POST',\(body)).catch(function(e){showToast(e.message);});")
    }
    static func jsonLiteral(_ value: Any) -> String {
        guard let data = try? JSONSerialization.data(withJSONObject: value, options: [.fragmentsAllowed]), let text = String(data: data, encoding: .utf8) else { return "null" }
        return text
    }
    func showMessage(_ message: String) {
        DispatchQueue.main.async { [weak self] in self?.webView?.evaluateJavaScript("window.showToast?.(\(Self.jsonLiteral(message)));") }
    }
    func userContentController(_ userContentController: WKUserContentController, didReceive message: WKScriptMessage) {
        guard message.frameInfo.isMainFrame, message.frameInfo.request.url?.scheme == "abissa-resource", let b = message.body as? [String: Any], let action = b["action"] as? String else { return }
        switch action {
        case "copy":
            if let value = b["url"] as? String, let url = URL(string: value), url.scheme == "https", url.host == "abissamobile.com" { UIPasteboard.general.url = url; showMessage("Lien copié.") }
        case "location":
            if let id = b["id"] as? String, id.count < 100 {
                locationRequests.append(id)
                if locationManager.authorizationStatus == .notDetermined { locationManager.requestWhenInUseAuthorization() }
                else if locationManager.authorizationStatus == .denied || locationManager.authorizationStatus == .restricted { completeLocations(nil, error: "Autorisez la localisation dans Réglages.") }
                else { locationManager.requestLocation() }
            }
        case "share":
            guard let value = b["url"] as? String, let url = URL(string: value), url.scheme == "https", url.host == "abissamobile.com" else { return }
            let vc = UIActivityViewController(activityItems: [b["title"] as? String ?? "ABISSA MOBILE", url], applicationActivities: nil)
            vc.popoverPresentationController?.sourceView = view
            vc.popoverPresentationController?.sourceRect = CGRect(x: view.bounds.midX, y: view.bounds.midY, width: 1, height: 1)
            present(vc, animated: true)
        case "openWebsite":
            if let value = b["url"] as? String, let url = URL(string: value), url.scheme == "https" {
                if ["wa.me", "api.whatsapp.com"].contains(url.host ?? "") {
                    UIApplication.shared.open(url) { [weak self] success in if !success { DispatchQueue.main.async { self?.present(SFSafariViewController(url: url), animated: true) } } }
                } else { present(SFSafariViewController(url: url), animated: true) }
            }
        case "saveText", "saveCalendar":
            if let text = b["text"] as? String, text.utf8.count <= 10_000_000 { export(Data(text.utf8), name: b["name"] as? String ?? "abissa.json") }
        case "savePDF":
            if let text = b["base64"] as? String, text.count <= 70_000_000, let data = Data(base64Encoded: text), data.starts(with: Data("%PDF-".utf8)) { export(data, name: "ABISSA-badges.pdf") }
        case "saveFile":
            if let text = b["base64"] as? String, text.count <= 35_000_000, let mime = b["mime"] as? String, ["application/pdf", "image/png", "image/jpeg", "image/webp"].contains(mime), let data = Data(base64Encoded: text) { export(data, name: b["name"] as? String ?? "abissa-document") }
        case "notifications":
            UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound]) { granted, _ in
                DispatchQueue.main.async { if granted { UIApplication.shared.registerForRemoteNotifications() } else { self.showMessage("Les notifications restent consultables dans l’application.") } }
            }
        case "session":
            if b["loggedIn"] as? Bool == true { UNUserNotificationCenter.current().getNotificationSettings { settings in if settings.authorizationStatus == .authorized { DispatchQueue.main.async { UIApplication.shared.registerForRemoteNotifications() } } } }
        default: nativeCompanion.perform(b)
        }
    }
    func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        guard !locationRequests.isEmpty else { return }
        if manager.authorizationStatus == .authorizedWhenInUse || manager.authorizationStatus == .authorizedAlways { manager.requestLocation() }
        else if manager.authorizationStatus == .denied || manager.authorizationStatus == .restricted { completeLocations(nil, error: "Localisation refusée.") }
    }
    func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        if let location = locations.last { completeLocations(["latitude": location.coordinate.latitude, "longitude": location.coordinate.longitude, "accuracy": location.horizontalAccuracy], error: nil) }
        else { completeLocations(nil, error: "Localisation indisponible.") }
    }
    func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) { completeLocations(nil, error: "Localisation indisponible.") }
    private func completeLocations(_ coords: [String: Double]?, error: String?) {
        let requests = locationRequests; locationRequests = []
        let coordsJSON = coords.map { Self.jsonLiteral($0) } ?? "null"
        let errorJSON = error.map { Self.jsonLiteral($0) } ?? "null"
        for id in requests { webView.evaluateJavaScript("window.__abissaLocationResult(\(Self.jsonLiteral(id)),\(coordsJSON),\(errorJSON));") }
    }
    private func export(_ data: Data, name: String) {
        let safeName = URL(fileURLWithPath: name).lastPathComponent
        let folder = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString)
        do {
            try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
            let url = folder.appendingPathComponent(safeName)
            try data.write(to: url, options: [.atomic, .completeFileProtection])
            present(UIDocumentPickerViewController(forExporting: [url], asCopy: true), animated: true)
        } catch { showMessage("Enregistrement du fichier impossible.") }
    }
    func webView(_ webView: WKWebView, decidePolicyFor navigationAction: WKNavigationAction, decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        guard let url = navigationAction.request.url else { decisionHandler(.cancel); return }
        if navigationAction.shouldPerformDownload { decisionHandler(.download); return }
        if navigationAction.targetFrame?.isMainFrame == false { decisionHandler(.allow); return }
        if url.scheme == "abissa-resource" { decisionHandler(.allow); return }
        if url.scheme == "https" { decisionHandler(.cancel); present(SFSafariViewController(url: url), animated: true); return }
        if ["tel", "mailto", "abissa", "whatsapp"].contains(url.scheme ?? "") { decisionHandler(.cancel); if url.scheme == "abissa" { openContent(url) } else { UIApplication.shared.open(url) { success in if !success { DispatchQueue.main.async { self.showMessage("Aucune application disponible pour ce lien. Utilisez les coordonnées affichées.") } } } }; return }
        decisionHandler(.cancel)
    }
    func webView(_ webView: WKWebView, navigationAction: WKNavigationAction, didBecome download: WKDownload) { download.delegate = self }
    func download(_ download: WKDownload, decideDestinationUsing response: URLResponse, suggestedFilename: String, completionHandler: @escaping (URL?) -> Void) {
        let folder = FileManager.default.urls(for: .documentDirectory, in: .userDomainMask)[0].appendingPathComponent("Downloads", isDirectory: true)
        do { try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true) }
        catch { completionHandler(nil); showMessage("Enregistrement impossible."); return }
        let url = folder.appendingPathComponent(UUID().uuidString + "-" + URL(fileURLWithPath: suggestedFilename).lastPathComponent)
        downloadURLs[ObjectIdentifier(download)] = url; completionHandler(url)
    }
    func downloadDidFinish(_ download: WKDownload) { if let url = downloadURLs.removeValue(forKey: ObjectIdentifier(download)) { nativeCompanion.openFile(url) } }
    func download(_ download: WKDownload, didFailWithError error: Error, resumeData: Data?) { if let file = downloadURLs.removeValue(forKey: ObjectIdentifier(download)) { try? FileManager.default.removeItem(at: file) }; showMessage("Téléchargement impossible.") }
    func webView(_ webView: WKWebView, createWebViewWith configuration: WKWebViewConfiguration, for navigationAction: WKNavigationAction, windowFeatures: WKWindowFeatures) -> WKWebView? {
        if let url = navigationAction.request.url, url.scheme == "https" { present(SFSafariViewController(url: url), animated: true) }
        return nil
    }
    func webView(_ webView: WKWebView, runJavaScriptAlertPanelWithMessage message: String, initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping () -> Void) {
        let vc = UIAlertController(title: "ABISSA MOBILE", message: message, preferredStyle: .alert)
        vc.addAction(UIAlertAction(title: "OK", style: .default) { _ in completionHandler() }); present(vc, animated: true)
    }
    func webView(_ webView: WKWebView, runJavaScriptConfirmPanelWithMessage message: String, initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping (Bool) -> Void) {
        let vc = UIAlertController(title: "ABISSA MOBILE", message: message, preferredStyle: .alert)
        vc.addAction(UIAlertAction(title: "Annuler", style: .cancel) { _ in completionHandler(false) })
        vc.addAction(UIAlertAction(title: "Confirmer", style: .default) { _ in completionHandler(true) }); present(vc, animated: true)
    }
}

