import Foundation
import WebKit
import UniformTypeIdentifiers
import os

/// Only read files inside the bundled Web directory, including on physical iPhones
/// where /var and /private/var can name the same container.
final class BundleResourceHandler: NSObject, WKURLSchemeHandler {
    private let root: URL?
    private let logger = Logger(subsystem: "ci.abissa", category: "Startup")

    init(root: URL? = Bundle.main.resourceURL?.appendingPathComponent("Web", isDirectory: true)) {
        self.root = root
        super.init()
    }

    static func resourceURL(for url: URL, root rawRoot: URL) throws -> URL {
        guard url.scheme == "abissa-resource", url.host == "app",
              url.user == nil, url.password == nil, url.port == nil,
              let encodedPath = URLComponents(url: url, resolvingAgainstBaseURL: false)?.percentEncodedPath,
              let path = encodedPath.removingPercentEncoding, !path.contains("\0") else {
            throw URLError(.badURL)
        }
        // Decode once. Reject traversal before resolving symlinks; apply the SAME
        // canonicalization to the root and candidate before comparing components.
        let parts = path.split(separator: "/", omittingEmptySubsequences: true).map(String.init)
        guard !parts.isEmpty, !parts.contains(".."), !parts.contains(".") else {
            throw URLError(.noPermissionsToReadFile)
        }
        let root = rawRoot.resolvingSymlinksInPath().standardizedFileURL
        let file = root.appendingPathComponent(parts.joined(separator: "/"))
            .resolvingSymlinksInPath().standardizedFileURL
        let base = root.pathComponents
        guard file.pathComponents.count > base.count,
              Array(file.pathComponents.prefix(base.count)) == base else {
            throw URLError(.noPermissionsToReadFile)
        }
        guard (try? file.resourceValues(forKeys: [.isRegularFileKey]).isRegularFile) == true else {
            throw URLError(.fileDoesNotExist)
        }
        return file
    }

    func webView(_ webView: WKWebView, start task: WKURLSchemeTask) {
        do {
            guard let url = task.request.url, let root = root else { throw URLError(.fileDoesNotExist) }
            let file = try Self.resourceURL(for: url, root: root)
            let data = try Data(contentsOf: file, options: .mappedIfSafe)
            let mime: String
            switch file.pathExtension.lowercased() {
            case "html": mime = "text/html; charset=utf-8"
            case "js": mime = "application/javascript; charset=utf-8"
            case "css": mime = "text/css; charset=utf-8"
            default: mime = UTType(filenameExtension: file.pathExtension)?.preferredMIMEType ?? "application/octet-stream"
            }
            guard let response = HTTPURLResponse(url: url, statusCode: 200, httpVersion: "HTTP/1.1", headerFields: [
                "Content-Type": mime, "Content-Length": String(data.count),
                "Access-Control-Allow-Origin": "*", "Cache-Control": "no-cache"
            ]) else { throw URLError(.badServerResponse) }
            task.didReceive(response)
            task.didReceive(data)
            task.didFinish()
        } catch {
            let failure = error as NSError
            logger.error("ABISSA_RESOURCE_FAILED domain=\(failure.domain, privacy: .public) code=\(failure.code)")
            task.didFailWithError(error)
        }
    }

    // Reads and callbacks above are synchronous; no work remains after stop.
    func webView(_ webView: WKWebView, stop task: WKURLSchemeTask) {}
}
