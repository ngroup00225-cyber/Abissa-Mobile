// Execute the actual Gradle signing validator with real keystores, without claiming an AGP build.
import org.codehaus.groovy.control.CompilationUnit
import org.codehaus.groovy.control.Phases

class GradleException extends RuntimeException {
    GradleException(String message) { super(message) }
}
class TestTask {
    String name
    String group
    String description
    Closure action
    List dependencies = []
    void doLast(Closure closure) { action = closure }
    void dependsOn(Object task) { dependencies.add(task) }
}
class TestTasks {
    Map items = [:]
    TestTask register(String name, Closure configure) {
        def task = new TestTask(name: name)
        configure.delegate = task
        configure.resolveStrategy = Closure.DELEGATE_FIRST
        configure.call()
        items[name] = task
        return task
    }
    void configureEach(Closure configure) {
        items.values().each { task ->
            configure.delegate = task
            configure.resolveStrategy = Closure.DELEGATE_FIRST
            configure.call(task)
        }
    }
}

def root = new File('.').canonicalFile
def source = new File(root, 'app/build.gradle').getText('UTF-8')
def syntax = new CompilationUnit()
syntax.addSource('app/build.gradle', source)
syntax.compile(Phases.CONVERSION)
def validator = source.substring(source.indexOf('def checkPlaySigning = tasks.register'))
def expected = '704da9712d6a5a9d992997f653ef9ce0896ce5ea252827335ea8c083134f3021'
def releaseNames = ['validateSigningRelease', 'packageRelease', 'signReleaseBundle', 'bundleRelease', 'assembleRelease']
def results = []
def execute = { String name, Map overrides, boolean shouldPass ->
    def tasks = new TestTasks()
    (releaseNames + ['assembleDebug', 'packageDebug']).each { tasks.items[it] = new TestTask(name: it) }
    def values = [tasks: tasks,
                  playKeystore: new File(root, '.release-signing/abissa-mobile-play-upload-20261011.jks'),
                  playAlias: 'abissa-play-upload-20261011',
                  playStorePassword: System.getenv('ABISSA_UPLOAD_STORE_PASSWORD'),
                  playKeyPassword: System.getenv('ABISSA_UPLOAD_STORE_PASSWORD'),
                  playExpectedSha256: expected,
                  logger: [lifecycle: { String ignored -> }]]
    values.putAll(overrides)
    boolean passed = false
    try {
        def shell = new GroovyShell(this.class.classLoader, new Binding(values))
        def task = shell.evaluate(validator + '\nreturn checkPlaySigning\n')
        task.action.call()
        assert releaseNames.every { tasks.items[it].dependencies.contains(task) }
        assert ['assembleDebug', 'packageDebug'].every { tasks.items[it].dependencies.isEmpty() }
        passed = true
    } catch (Exception failure) {
        if (shouldPass) throw failure
    }
    assert passed == shouldPass: name
    results << [test: name, passed: true]
}

execute('Valid pinned RSA4096 publication key accepted; all Release hooks attached', [:], true)
execute('Missing passwords rejected', [playStorePassword: null, playKeyPassword: null], false)
execute('Missing keystore rejected', [playKeystore: new File(root, '.release-signing/nonexistent-test-key.jks')], false)
execute('Wrong certificate fingerprint rejected', [playExpectedSha256: '0' * 64], false)
execute('Invalid fingerprint syntax rejected', [playExpectedSha256: 'invalid'], false)
execute('Missing private-key alias rejected', [playAlias: 'nonexistent-test-alias'], false)
execute('Wrong private-key password rejected', [playKeyPassword: 'invalid-test-input'], false)
execute('Historical Android Debug certificate rejected even with matching pin', [
    playKeystore: new File(root, '.release-signing/backups/2026-10-07/historical-android.keystore'),
    playAlias: 'androiddebugkey', playStorePassword: System.getenv('ABISSA_HISTORICAL_PASSWORD'),
    playKeyPassword: System.getenv('ABISSA_HISTORICAL_PASSWORD'),
    playExpectedSha256: '13f8e6d5d5ae51623d72d32e3d4b9c00b507e6ae5e61465679cc8740b6893293'], false)
results << [test: 'Complete Gradle source parses with Groovy conversion phase', passed: true]
def sdkValidator = source.substring(source.indexOf('// Play automatic protection'), source.indexOf('dependencies {'))
[23, 24].each { int minimum ->
    boolean accepted = false
    try {
        new GroovyShell(this.class.classLoader, new Binding([
            android: [defaultConfig: [minSdkVersion: [apiLevel: minimum], applicationId: 'ci.abissa', targetSdkVersion: [apiLevel: 36]]]
        ])).evaluate(sdkValidator)
        accepted = true
    } catch (GradleException failure) {
        assert minimum == 23
        assert failure.message.contains('minSdk 24')
    }
    assert accepted == (minimum >= 24)
    results << [test: 'Actual Gradle SDK guard: minSdk ' + minimum + (accepted ? ' accepted' : ' rejected'), passed: true]
}
[['ci.abissa',36,true], ['com.abissa.mobile',36,false], ['ci.abissa',35,false], ['ci.abissa',37,true]].each { config ->
    boolean accepted = false
    try {
        new GroovyShell(this.class.classLoader, new Binding([
            android: [defaultConfig: [minSdkVersion: [apiLevel: 24], applicationId: config[0], targetSdkVersion: [apiLevel: config[1]]]]
        ])).evaluate(sdkValidator)
        accepted = true
    } catch (GradleException failure) {
        assert !config[2]
    }
    assert accepted == config[2]
    results << [test: 'Actual Gradle identity/target guard: ' + config[0] + ' API' + config[1] + (accepted ? ' accepted' : ' rejected'), passed: true]
}
results.each { println('PASS: ' + it.test) }
println('ABISSA_GRADLE_GUARD_TESTS=' + results.size())
