# Local Windows launcher: inject DPAPI-protected passwords into Gradle's process only.
$ErrorActionPreference = 'Stop'
$projectPath = Split-Path -Parent $PSScriptRoot
$signingPath = Join-Path $projectPath '.release-signing'
$keyFile = Join-Path $signingPath 'abissa-mobile-play-upload-20261011.jks'
$passwordFile = Join-Path $signingPath 'play-upload-20261011-password.dpapi'
if (-not (Test-Path -LiteralPath $keyFile) -or -not (Test-Path -LiteralPath $passwordFile)) {
    throw 'Restore the authorized Play upload key and password locally. Do not generate another key.'
}
Add-Type -AssemblyName System.Security
$privateBytes = [Security.Cryptography.ProtectedData]::Unprotect([IO.File]::ReadAllBytes($passwordFile),$null,[Security.Cryptography.DataProtectionScope]::CurrentUser)
$signingVariables = @('ABISSA_UPLOAD_KEYSTORE','ABISSA_UPLOAD_KEY_ALIAS','ABISSA_UPLOAD_STORE_PASSWORD','ABISSA_UPLOAD_KEY_PASSWORD','ABISSA_UPLOAD_CERT_SHA256')
$previousValues = @{}
foreach ($variable in $signingVariables) { $previousValues[$variable] = [Environment]::GetEnvironmentVariable($variable,'Process') }
$previousDirectory = Get-Location
try {
    $env:ABISSA_UPLOAD_KEYSTORE = $keyFile
    $env:ABISSA_UPLOAD_KEY_ALIAS = 'abissa-play-upload-20261011'
    $env:ABISSA_UPLOAD_STORE_PASSWORD = [Text.Encoding]::UTF8.GetString($privateBytes)
    $env:ABISSA_UPLOAD_KEY_PASSWORD = $env:ABISSA_UPLOAD_STORE_PASSWORD
    $env:ABISSA_UPLOAD_CERT_SHA256 = '704da9712d6a5a9d992997f653ef9ce0896ce5ea252827335ea8c083134f3021'
    Set-Location -LiteralPath $projectPath
    & (Join-Path $projectPath 'gradlew.bat') ':app:bundleRelease'
    if ($LASTEXITCODE -ne 0) { throw 'Gradle Release failed. No unsigned/debug artifact should be published.' }
    $releaseBundle = Join-Path $projectPath 'app\build\outputs\bundle\release\app-release.aab'
    if (-not (Test-Path -LiteralPath $releaseBundle)) { throw 'Signed Release AAB is missing.' }
    Write-Output ('Gradle Release AAB: ' + $releaseBundle)
    Write-Output 'Verify the certificate with jarsigner and compare the public upload fingerprint with Play Console before import.'
} finally {
    [Array]::Clear($privateBytes,0,$privateBytes.Length)
    foreach ($variable in $signingVariables) { [Environment]::SetEnvironmentVariable($variable,$previousValues[$variable],'Process') }
    Set-Location -LiteralPath $previousDirectory
}
