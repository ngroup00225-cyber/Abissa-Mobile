# Run locally in Windows PowerShell. Never put the signing password in chat or a repository.
$ErrorActionPreference = 'Stop'
$workspacePath = Split-Path -Parent $PSScriptRoot
$privateDir = Join-Path $workspacePath '.release-signing'
$existingKey = Join-Path $privateDir 'abissa-mobile-upload.jks'
if (-not (Test-Path -LiteralPath $existingKey)) { throw 'La clé existante doit être restaurée. Aucune nouvelle clé ne sera créée.' }
Add-Type -AssemblyName System.Security
$protectedInput = Read-Host 'Mot de passe de la clé existante abissa-upload (saisie masquée)' -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($protectedInput)
try {
    $env:ABISSA_UPLOAD_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    & 'C:/Program Files/Java/jdk-17/bin/keytool.exe' -list -keystore $existingKey -alias abissa-upload -storepass:env ABISSA_UPLOAD_PASSWORD
    if ($LASTEXITCODE -ne 0) { throw 'Mot de passe incorrect ; le fichier protégé existant est conservé.' }
    $bytes = [Text.Encoding]::UTF8.GetBytes($env:ABISSA_UPLOAD_PASSWORD)
    $encrypted = [Security.Cryptography.ProtectedData]::Protect($bytes,$null,[Security.Cryptography.DataProtectionScope]::CurrentUser)
    $passwordFile = Join-Path $privateDir 'upload-password.dpapi'
    if (Test-Path -LiteralPath $passwordFile) { Copy-Item -LiteralPath $passwordFile -Destination ($passwordFile + '.unrecoverable-backup') }
    [IO.File]::WriteAllBytes($passwordFile,$encrypted)
    Write-Output 'Mot de passe validé et protégé pour ce compte Windows. Relancer build-playstore-212.py.'
} finally {
    Remove-Item Env:ABISSA_UPLOAD_PASSWORD -ErrorAction SilentlyContinue
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    $protectedInput.Dispose()
}
