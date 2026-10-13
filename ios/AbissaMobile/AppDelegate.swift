import UIKit
import UserNotifications

@main
final class AppDelegate: UIResponder, UIApplicationDelegate, UNUserNotificationCenterDelegate {
    weak var controller: AbissaViewController?
    private var pendingURL: URL?
    private var pendingToken: (String, String)?

    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?) -> Bool {
        UNUserNotificationCenter.current().delegate = self
        if let url = launchOptions?[.url] as? URL { pendingURL = url }
        return true
    }
    func attach(_ view: AbissaViewController) {
        controller = view
        if let url = pendingURL { pendingURL = nil; view.openContent(url) }
        if let pair = pendingToken { pendingToken = nil; view.deliverDeviceToken(pair.0, environment: pair.1) }
    }
    private func openContent(_ url: URL) {
        if let view = controller { view.openContent(url) } else { pendingURL = url }
    }
    func application(_ app: UIApplication, open url: URL, options: [UIApplication.OpenURLOptionsKey: Any] = [:]) -> Bool {
        openContent(url); return true
    }
    func application(_ application: UIApplication, continue userActivity: NSUserActivity, restorationHandler: @escaping ([UIUserActivityRestoring]?) -> Void) -> Bool {
        guard userActivity.activityType == NSUserActivityTypeBrowsingWeb, let url = userActivity.webpageURL else { return false }
        openContent(url); return true
    }
    func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
        let token = deviceToken.map { String(format: "%02x", $0) }.joined()
        #if DEBUG
        let environment = "development"
        #else
        let environment = "production"
        #endif
        if let view = controller { view.deliverDeviceToken(token, environment: environment) }
        else { pendingToken = (token, environment) }
    }
    func application(_ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error) {
        controller?.showMessage("Les notifications push ne sont pas disponibles. Consultez votre boîte de notifications dans l’application.")
    }
    func userNotificationCenter(_ center: UNUserNotificationCenter, willPresent notification: UNNotification, withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void) {
        completionHandler([.banner, .sound, .list])
    }
    func userNotificationCenter(_ center: UNUserNotificationCenter, didReceive response: UNNotificationResponse, withCompletionHandler completionHandler: @escaping () -> Void) {
        if let value = response.notification.request.content.userInfo["url"] as? String, let url = URL(string: value) { openContent(url) }
        completionHandler()
    }
}

// Required by the iOS 27 SDK. The window belongs to its scene, not UIApplicationDelegate.
final class AbissaSceneDelegate: UIResponder, UIWindowSceneDelegate {
    var window: UIWindow?

    func scene(_ scene: UIScene, willConnectTo session: UISceneSession, options connectionOptions: UIScene.ConnectionOptions) {
        guard let windowScene = scene as? UIWindowScene else { return }
        let view = AbissaViewController()
        let sceneWindow = UIWindow(windowScene: windowScene)
        sceneWindow.rootViewController = view
        window = sceneWindow
        (UIApplication.shared.delegate as? AppDelegate)?.attach(view)
        sceneWindow.makeKeyAndVisible()
        if let url = connectionOptions.urlContexts.first?.url { view.openContent(url) }
        for activity in connectionOptions.userActivities where activity.activityType == NSUserActivityTypeBrowsingWeb {
            if let url = activity.webpageURL { view.openContent(url) }
        }
        if let value = connectionOptions.notificationResponse?.notification.request.content.userInfo["url"] as? String,
           let url = URL(string: value) { view.openContent(url) }
    }
    func scene(_ scene: UIScene, openURLContexts URLContexts: Set<UIOpenURLContext>) {
        if let url = URLContexts.first?.url { (window?.rootViewController as? AbissaViewController)?.openContent(url) }
    }
    func scene(_ scene: UIScene, continue userActivity: NSUserActivity) {
        guard userActivity.activityType == NSUserActivityTypeBrowsingWeb, let url = userActivity.webpageURL else { return }
        (window?.rootViewController as? AbissaViewController)?.openContent(url)
    }
}
