import UIKit
import WebKit
import AVFoundation
import Network
import UserNotifications
import SafariServices
import QuickLook

/** A native companion for the bundled WKWebView. No camera frame is uploaded. */
final class NativeCompanion {
    private weak var owner: UIViewController?
    private weak var web: WKWebView?
    private let preferences = UserDefaults.standard
    private let monitor = NWPathMonitor()
    private var banner: UILabel?
    private var online = true
    var networkChanged: ((Bool) -> Void)?
    init(owner: UIViewController, web: WKWebView) { self.owner = owner; self.web = web }
    func startNetwork(in view: UIView) {
        let label = UILabel(); label.text = "Hors connexion · contenus enregistrés disponibles"; label.numberOfLines = 2
        label.font = .preferredFont(forTextStyle: .caption1); label.textColor = .white; label.backgroundColor = .systemBrown
        label.textAlignment = .center; label.isHidden = true; label.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(label); banner = label
        NSLayoutConstraint.activate([label.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor), label.leadingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.leadingAnchor), label.trailingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.trailingAnchor), label.heightAnchor.constraint(equalToConstant: 40)])
        monitor.pathUpdateHandler = { [weak self] path in DispatchQueue.main.async { self?.online = path.status == .satisfied; self?.banner?.isHidden = path.status == .satisfied; self?.networkChanged?(path.status == .satisfied); self?.emit("abissa-native-network", path.status == .satisfied) } }
        monitor.start(queue: DispatchQueue(label: "ci.abissa.network"))
    }
    func ready() {
        emit("abissa-native-state", ["platform": "ios", "version": "2.1.2", "favorites": favorites, "lastPage": preferences.string(forKey: "abissa.lastPage") ?? "accueil", "language": preferences.string(forKey: "abissa.language") ?? "fr"] as [String: Any])
        emit("abissa-native-network", online)
    }
    private var favorites: [[String: String]] { preferences.array(forKey: "abissa.favorites") as? [[String: String]] ?? [] }
    private func emit(_ name: String, _ value: Any) { web?.evaluateJavaScript("window.dispatchEvent(new CustomEvent('\(name)',{detail:\(AbissaViewController.jsonLiteral(value))}));") }
    private func page(_ id: String) { web?.evaluateJavaScript("window.goTo?.(\(AbissaViewController.jsonLiteral(id)));") }
    static func official(_ url: URL) -> Bool { url.scheme == "https" && url.host == "abissamobile.com" && url.user == nil && url.password == nil && url.port == nil }
    func perform(_ body: [String: Any]) {
        guard let action = body["action"] as? String else { return }
        switch action {
        case "home": page("accueil")
        case "back": web?.evaluateJavaScript("window.AbissaNative212?.back();")
        case "scan": scanner()
        case "favorites": listFavorites()
        case "settings": more()
        case "downloads": downloads()
        case "notifications": notifications()
        case "about": about()
        case "favorite":
            guard let value = body["url"] as? String, value.count < 2048, let url = URL(string: value), Self.official(url), let title = body["title"] as? String, title.count <= 200 else { return }
            var next = favorites
            if next.contains(where: { $0["url"] == value }) { next.removeAll { $0["url"] == value } }
            else if next.count < 200 { next.append(["url": value, "title": title]) }
            preferences.set(next, forKey: "abissa.favorites"); ready()
        case "preferences":
            if let language = body["language"] as? String, ["fr", "en", "nz"].contains(language) { preferences.set(language, forKey: "abissa.language") }
            if let page = body["lastPage"] as? String, page.range(of: "^[a-z0-9-]{1,60}$", options: .regularExpression) != nil { preferences.set(page, forKey: "abissa.lastPage") }
        case "directions":
            guard let value = body["url"] as? String, let url = URL(string: value), url.scheme == "https", ["www.google.com", "maps.google.com", "www.openstreetmap.org"].contains(url.host ?? "") else { return }
            let destination = URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems?.first(where: { $0.name == "destination" || $0.name == "q" })?.value
            var components = URLComponents(string: "https://maps.apple.com/")!; components.queryItems = [URLQueryItem(name: "daddr", value: destination)]
            if destination != nil, let map = components.url { open(map, fallback: url) } else { open(url) }
        case "publicData": if let id = body["id"] as? String, id.count < 40 { publicData(id: id) }
        case "download": if let value = body["url"] as? String, let url = URL(string: value), Self.official(url) { download(url) }
        default: break
        }
    }
    private func alert(_ title: String, _ message: String) { let a = UIAlertController(title: title, message: message, preferredStyle: .alert); a.addAction(UIAlertAction(title: "Fermer", style: .cancel)); owner?.present(a, animated: true) }
    private func sheet(_ title: String, actions: [(String, () -> Void)]) {
        let a = UIAlertController(title: title, message: nil, preferredStyle: .actionSheet)
        for (label, action) in actions { a.addAction(UIAlertAction(title: label, style: .default) { _ in action() }) }
        a.addAction(UIAlertAction(title: "Fermer", style: .cancel)); a.popoverPresentationController?.sourceView = owner?.view
        a.popoverPresentationController?.sourceRect = CGRect(x: owner?.view.bounds.midX ?? 0, y: owner?.view.bounds.maxY ?? 0, width: 1, height: 1)
        owner?.present(a, animated: true)
    }
    private func listFavorites() {
        guard !favorites.isEmpty else { alert("Mes favoris", "Ouvrez un contenu et touchez Favori pour le retrouver ici après redémarrage."); return }
        sheet("Mes favoris", actions: favorites.map { item in (item["title"] ?? "Contenu", { [weak self] in if let value = item["url"] { self?.emit("abissa-deeplink", value) } }) })
    }
    private func more() { sheet("ABISSA MOBILE", actions: [
        ("Notifications", { [weak self] in self?.notifications() }),
        ("Langue", { [weak self] in self?.language() }),
        ("Téléchargements", { [weak self] in self?.downloads() }),
        ("Dernière page", { [weak self] in guard let self = self else { return }; self.page(self.preferences.string(forKey: "abissa.lastPage") ?? "accueil") }),
        ("À propos", { [weak self] in self?.about() })]) }
    private func language() { sheet("Langue préférée des contenus", actions: [("Français", "fr"), ("English", "en"), ("N’zima", "nz")].map { pair in (pair.0, { [weak self] in self?.preferences.set(pair.1, forKey: "abissa.language"); self?.ready() }) }) }
    private func about() {
        let info = Bundle.main.infoDictionary ?? [:]
        let title = "ABISSA MOBILE \(info["CFBundleShortVersionString"] ?? "2.1.2") (\(info["CFBundleVersion"] ?? "24"))"
        sheet(title, actions: [
            ("Créé par Narcisse Djéni N’DIAMOA", { [weak self] in self?.alert("ABISSA MOBILE", "Royauté N’zima Kotoko · Grand-Bassam\nCréé par Narcisse Djéni N’DIAMOA\nContact développeur : rubrique Support et site officiel.") }),
            ("Site officiel et développeur", { [weak self] in self?.open(URL(string: "https://abissamobile.com/")!) }),
            ("Confidentialité", { [weak self] in self?.open(URL(string: "https://abissamobile.com/confidentialite.php")!) }),
            ("Conditions d’utilisation", { [weak self] in self?.web?.evaluateJavaScript("window.AbissaRefonte?.actions.contract();") }),
            ("Support", { [weak self] in self?.page("feedback") })])
    }
    private func open(_ url: URL, fallback: URL? = nil) { UIApplication.shared.open(url) { [weak self] success in if !success { DispatchQueue.main.async { if let alternate = fallback { self?.owner?.present(SFSafariViewController(url: alternate), animated: true) } else { self?.alert("Lien indisponible", "Aucune application ne peut ouvrir ce lien.") } } } } }
    private func notifications() {
        sheet("Notifications et rappels", actions: [
            ("Consulter mes notifications", { [weak self] in self?.page("community-notifications") }),
            ("Activer les notifications", { [weak self] in
                UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound]) { granted, _ in DispatchQueue.main.async {
                    if granted { UIApplication.shared.registerForRemoteNotifications(); self?.alert("Notifications autorisées", "Les messages à distance dépendent du service APNs de l’organisation.") }
                    else { self?.alert("Notifications refusées", "Les notifications restent consultables dans l’application.") }
                } }
            }),
            ("Rappel du programme demain", { [weak self] in self?.reminder() }),
            ("Retirer mes rappels", { UNUserNotificationCenter.current().removePendingNotificationRequests(withIdentifiers: ["abissa-programme-212"]) })])
    }
    private func reminder() { UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound]) { [weak self] granted, _ in
        guard granted else { DispatchQueue.main.async { self?.alert("Rappel désactivé", "Autorisez les notifications dans Réglages pour recevoir un rappel.") }; return }
        let content = UNMutableNotificationContent(); content.title = "ABISSA MOBILE"; content.body = "Retrouvez le programme et les actualités de l’Abissa."; content.sound = .default; content.userInfo = ["url": "https://abissamobile.com/appli/mobile.html?page=programme"]
        UNUserNotificationCenter.current().add(UNNotificationRequest(identifier: "abissa-programme-212", content: content, trigger: UNTimeIntervalNotificationTrigger(timeInterval: 86400, repeats: false))) { error in DispatchQueue.main.async { self?.alert("Rappel", error == nil ? "Rappel programmé pour demain." : "Le rappel n’a pas pu être enregistré.") } }
    } }
    private func scanner() {
        AVCaptureDevice.requestAccess(for: .video) { [weak self] granted in DispatchQueue.main.async {
            guard granted else { self?.alert("Caméra non autorisée", "Vous pouvez autoriser la caméra dans Réglages. Le reste de l’application reste disponible."); return }
            let scanner = NativeQRController(); scanner.result = { [weak self] text in self?.qr(text) }; self?.owner?.present(scanner, animated: true)
        } }
    }
    private func qr(_ text: String) {
        if let url = URL(string: text), Self.official(url), url.path == "/appli/credential.html" {
            let confirmation = UIAlertController(title: "Badge ABISSA", message: "Consulter ce badge sur le serveur officiel. La lecture du QR ne valide pas un accès ; un agent autorisé doit effectuer le contrôle en ligne.", preferredStyle: .alert)
            confirmation.addAction(UIAlertAction(title: "Consulter", style: .default) { [weak self] _ in self?.open(url) })
            confirmation.addAction(UIAlertAction(title: "Annuler", style: .cancel)); owner?.present(confirmation, animated: true)
        }
        else if let url = URL(string: text), Self.official(url), url.path == "/appli/mobile.html" { emit("abissa-deeplink", text) }
        else { alert("QR code lu", String(text.prefix(4096)) + "\n\nLa validation d’accès d’un badge nécessite un agent autorisé connecté.") }
    }
    private func publicData(id: String) {
        let request = URLRequest(url: URL(string: "https://abissamobile.com/appli/api/data")!, cachePolicy: .reloadIgnoringLocalCacheData, timeoutInterval: 15)
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            let folder = FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask)[0]
            let file = folder.appendingPathComponent("abissa-public-data-212.json")
            var content: Data?
            if error == nil, (response as? HTTPURLResponse)?.statusCode == 200, let data = data, data.count <= 8 * 1024 * 1024, (try? JSONSerialization.jsonObject(with: data)) is [String: Any] {
                try? FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
                try? data.write(to: file, options: [.atomic, .completeFileProtection]); content = data
            } else { content = try? Data(contentsOf: file) }
            let text = content.flatMap { String(data: $0, encoding: .utf8) }
            DispatchQueue.main.async { self?.web?.evaluateJavaScript("window.__abissaPublicDataResult?.(\(AbissaViewController.jsonLiteral(id)),\(text.map { AbissaViewController.jsonLiteral($0) } ?? "null"),\(text == nil ? AbissaViewController.jsonLiteral("Connexion indisponible. Les contenus embarqués restent accessibles.") : "null"));") }
        }.resume()
    }
    private func downloads() {
        let folder = FileManager.default.urls(for: .documentDirectory, in: .userDomainMask)[0].appendingPathComponent("Downloads")
        let files = (try? FileManager.default.contentsOfDirectory(at: folder, includingPropertiesForKeys: nil)) ?? []
        if files.isEmpty { alert("Téléchargements", "Aucun fichier téléchargé pour le moment."); return }
        sheet("Téléchargements", actions: files.map { file in (String(file.lastPathComponent.dropFirst(37)), { [weak self] in self?.openFile(file) }) })
    }
    func openFile(_ file: URL) { let preview = NativeFilePreview(file: file); owner?.present(preview, animated: true) }
    private func download(_ url: URL) {
        web?.evaluateJavaScript("window.showToast?.('Téléchargement démarré.');")
        let config = URLSessionConfiguration.ephemeral; config.httpCookieStorage = nil; config.timeoutIntervalForRequest = 30
        let session = URLSession(configuration: config)
        session.downloadTask(with: url) { [weak self] temporary, response, error in
            defer { session.finishTasksAndInvalidate() }
            guard error == nil, let temporary = temporary, let response = response as? HTTPURLResponse, response.statusCode == 200, let final = response.url, Self.official(final) else { DispatchQueue.main.async { self?.alert("Téléchargement impossible", "Vérifiez votre connexion puis réessayez.") }; return }
            let folder = FileManager.default.urls(for: .documentDirectory, in: .userDomainMask)[0].appendingPathComponent("Downloads", isDirectory: true)
            let name = URL(fileURLWithPath: url.lastPathComponent).lastPathComponent
            let file = folder.appendingPathComponent(UUID().uuidString + "-" + (name.isEmpty ? "abissa-document" : name))
            do { try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true); try FileManager.default.moveItem(at: temporary, to: file); DispatchQueue.main.async { self?.openFile(file) } }
            catch { DispatchQueue.main.async { self?.alert("Fichier non enregistré", "Le téléchargement n’a pas pu être conservé.") } }
        }.resume()
    }
    deinit { monitor.cancel() }
}

final class NativeQRController: UIViewController, AVCaptureMetadataOutputObjectsDelegate {
    var result: ((String) -> Void)?
    private let session = AVCaptureSession()
    private let queue = DispatchQueue(label: "ci.abissa.qr")
    private var preview: AVCaptureVideoPreviewLayer?
    private var finished = false
    override func viewDidLoad() {
        super.viewDidLoad(); view.backgroundColor = .black
        let close = UIButton(type: .system); close.setTitle("Retour à Abissa", for: .normal); close.tintColor = .white; close.backgroundColor = .systemBlue; close.translatesAutoresizingMaskIntoConstraints = false
        close.addAction(UIAction { [weak self] _ in self?.dismiss(animated: true) }, for: .touchUpInside); view.addSubview(close)
        NSLayoutConstraint.activate([close.leadingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.leadingAnchor), close.trailingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.trailingAnchor), close.bottomAnchor.constraint(equalTo: view.safeAreaLayoutGuide.bottomAnchor), close.heightAnchor.constraint(equalToConstant: 50)])
        guard let camera = AVCaptureDevice.default(for: .video), let input = try? AVCaptureDeviceInput(device: camera), session.canAddInput(input) else { close.setTitle("Caméra indisponible · Retour", for: .normal); return }
        session.addInput(input); let output = AVCaptureMetadataOutput()
        guard session.canAddOutput(output) else { return }; session.addOutput(output); output.setMetadataObjectsDelegate(self, queue: .main); output.metadataObjectTypes = [.qr]
        let layer = AVCaptureVideoPreviewLayer(session: session); layer.videoGravity = .resizeAspectFill; preview = layer; view.layer.insertSublayer(layer, at: 0)
        queue.async { [weak self] in self?.session.startRunning() }
    }
    override func viewDidLayoutSubviews() { super.viewDidLayoutSubviews(); preview?.frame = view.bounds.inset(by: UIEdgeInsets(top: 0, left: 0, bottom: 60, right: 0)) }
    override func viewDidDisappear(_ animated: Bool) { super.viewDidDisappear(animated); queue.async { [weak self] in self?.session.stopRunning() } }
    func metadataOutput(_ output: AVCaptureMetadataOutput, didOutput metadataObjects: [AVMetadataObject], from connection: AVCaptureConnection) {
        guard !finished, let text = (metadataObjects.first as? AVMetadataMachineReadableCodeObject)?.stringValue else { return }; finished = true
        let callback = result; dismiss(animated: true) { callback?(text) }
    }
}

final class NativeFilePreview: QLPreviewController, QLPreviewControllerDataSource {
    private let file: URL
    init(file: URL) { self.file = file; super.init(nibName: nil, bundle: nil); dataSource = self }
    required init?(coder: NSCoder) { fatalError("NativeFilePreview is created with a downloaded file") }
    func numberOfPreviewItems(in controller: QLPreviewController) -> Int { 1 }
    func previewController(_ controller: QLPreviewController, previewItemAt index: Int) -> QLPreviewItem { file as NSURL }
}
