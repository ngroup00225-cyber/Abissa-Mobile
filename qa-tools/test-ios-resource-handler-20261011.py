"""macOS regression tests compile and execute the actual iOS resource handler."""
from pathlib import Path
import json
import platform
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
SWIFT = r'''
import Foundation

@main struct ResourceRegression {
    static func main() throws {
        let fm = FileManager.default
        let temp = fm.temporaryDirectory.appendingPathComponent(UUID().uuidString)
        try fm.createDirectory(at: temp, withIntermediateDirectories: true)
        defer { try? fm.removeItem(at: temp) }
        let root = temp.appendingPathComponent("Web", isDirectory: true)
        try fm.createDirectory(at: root, withIntermediateDirectories: true)
        try Data("<html>ABISSA</html>".utf8).write(to: root.appendingPathComponent("abissa_mobile.html"))
        try Data("accent".utf8).write(to: root.appendingPathComponent("fête 2026.txt"))
        try Data("literal percent".utf8).write(to: root.appendingPathComponent("%2e%2e.txt"))
        let alias = temp.appendingPathComponent("WebAlias")
        try fm.createSymbolicLink(at: alias, withDestinationURL: root)
        let outside = temp.appendingPathComponent("Web-evil", isDirectory: true)
        try fm.createDirectory(at: outside, withIntermediateDirectories: true)
        try Data("private".utf8).write(to: outside.appendingPathComponent("secret.txt"))
        try fm.createSymbolicLink(at: root.appendingPathComponent("escape"), withDestinationURL: outside)
        var count = 0
        func accepted(_ value: String, _ base: URL? = nil, expected: String) throws {
            let file = try BundleResourceHandler.resourceURL(for: URL(string: value)!, root: base ?? root)
            guard String(data: try Data(contentsOf: file), encoding: .utf8) == expected else {
                throw NSError(domain: "ResourceRegression", code: 1)
            }
            count += 1
        }
        func rejected(_ value: String) throws {
            do {
                _ = try BundleResourceHandler.resourceURL(for: URL(string: value)!, root: root)
            } catch { count += 1; return }
            throw NSError(domain: "ResourceRegression", code: 2)
        }
        try accepted("abissa-resource://app/abissa_mobile.html", expected: "<html>ABISSA</html>")
        try accepted("abissa-resource://app/abissa_mobile.html?v=30#home", expected: "<html>ABISSA</html>")
        try accepted("abissa-resource://app/abissa_mobile.html", alias, expected: "<html>ABISSA</html>")
        try accepted("abissa-resource://app/f%C3%AAte%202026.txt", expected: "accent")
        try accepted("abissa-resource://app/%252e%252e.txt", expected: "literal percent")
        try rejected("https://app/abissa_mobile.html")
        try rejected("abissa-resource://other/abissa_mobile.html")
        try rejected("abissa-resource://user@app/abissa_mobile.html")
        try rejected("abissa-resource://app:123/abissa_mobile.html")
        try rejected("abissa-resource://app/missing.html")
        try rejected("abissa-resource://app/")
        try rejected("abissa-resource://app/%2e%2e/Web-evil/secret.txt")
        try rejected("abissa-resource://app/escape/secret.txt")
        try rejected("abissa-resource://app/%00.txt")
        // Exercise the /private alias on Darwin using the actual Foundation APIs.
        let canonical = root.resolvingSymlinksInPath().standardizedFileURL
        let aliasPath = canonical.path.hasPrefix("/private/") ? String(canonical.path.dropFirst(8)) : "/private" + canonical.path
        let privateRoot = URL(fileURLWithPath: aliasPath)
        guard fm.fileExists(atPath: privateRoot.path) else {
            throw NSError(domain: "DarwinPrivateAliasUnavailable", code: 3)
        }
        let oldCandidate = privateRoot.appendingPathComponent("abissa_mobile.html").standardizedFileURL
        let oldWouldReject = !oldCandidate.path.hasPrefix(privateRoot.path + "/")
        try accepted("abissa-resource://app/abissa_mobile.html", privateRoot, expected: "<html>ABISSA</html>")
        guard let response = HTTPURLResponse(url: URL(string: "abissa-resource://app/abissa_mobile.html")!, statusCode: 200, httpVersion: "HTTP/1.1", headerFields: ["Content-Type":"text/html; charset=utf-8"]) else {
            throw NSError(domain: "CustomSchemeResponse", code: 4)
        }
        guard response.mimeType == "text/html", response.textEncodingName == "utf-8" else {
            throw NSError(domain: "CustomSchemeMIME", code: 5)
        }
        count += 1
        let result: [String: Any] = ["tests":count, "passed":true, "actualSwiftHandlerExecuted":true, "oldPrivatePrefixBugReproduced":oldWouldReject]
        print(String(data: try JSONSerialization.data(withJSONObject: result, options: [.sortedKeys]), encoding: .utf8)!)
    }
}
'''


def main():
    if sys.platform != 'darwin':
        raise RuntimeError('Run this Swift/WebKit regression test on the Codemagic macOS builder.')
    subprocess.run(['xcrun', 'swiftc', '-frontend', '-parse',
                    *map(str, sorted((ROOT/'ios/AbissaMobile').glob('*.swift')))],
                   check=True, timeout=60)
    with tempfile.TemporaryDirectory(prefix='abissa-swift-regression-') as temporary:
        folder = Path(temporary)
        harness = folder / 'ResourceRegression.swift'
        harness.write_text(SWIFT, encoding='utf-8')
        executable = folder / 'resource-regression'
        subprocess.run(['xcrun', 'swiftc', '-target', platform.machine()+'-apple-macosx11.0', '-parse-as-library',
                        str(ROOT/'ios/AbissaMobile/BundleResourceHandler.swift'), str(harness),
                        '-o', str(executable)], check=True, timeout=120)
        output = subprocess.check_output([str(executable)], text=True, timeout=30)
    result = json.loads(output)
    (ROOT/'qa-tools/test-ios-resource-handler-20261011-results.json').write_text(
        json.dumps(result, indent=2)+'\n', encoding='utf-8')
    print(json.dumps(result, sort_keys=True))


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, subprocess.SubprocessError, OSError, ValueError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
