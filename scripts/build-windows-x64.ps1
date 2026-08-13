$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$SpcVersion = '2.8.5'
$SpcSha256 = '425b54ab857e21409c1fd9b818899ffebabd1e2817ef0a0ed5ae8a3d9f5b463b'
$Build = Join-Path $Root 'windows-build'
$Spc = Join-Path $Build 'spc.exe'
$Package = Join-Path $Root 'dist\aml-windows-x64'
$Composer = Join-Path $Build 'composer.phar'
$Version = (Get-Content (Join-Path $Root 'phpaml.json') -Raw | ConvertFrom-Json).version

New-Item -ItemType Directory -Force -Path $Build, "$Package\bin", "$Package\runtime\php", "$Package\runtime\composer", "$Package\runtime\bin" | Out-Null

if (-not (Test-Path $Spc)) {
    Invoke-WebRequest "https://github.com/crazywhalecc/static-php-cli/releases/download/$SpcVersion/spc-windows-x64.exe" -OutFile $Spc
}
if ((Get-FileHash $Spc -Algorithm SHA256).Hash.ToLowerInvariant() -ne $SpcSha256) {
    throw 'Le checksum SHA-256 de Static PHP CLI est invalide.'
}

Copy-Item (Join-Path $Root 'craft-windows.yml') (Join-Path $Build 'craft.yml') -Force
Push-Location $Build
try {
    & $Spc craft
    if ($LASTEXITCODE -ne 0) { throw "La compilation du runtime PHP Windows a échoué." }
} finally {
    Pop-Location
}

Invoke-WebRequest 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile $Composer
$ExpectedComposerHash = (Invoke-WebRequest 'https://getcomposer.org/download/latest-stable/composer.phar.sha256').Content.Trim().ToLowerInvariant()
if ((Get-FileHash $Composer -Algorithm SHA256).Hash.ToLowerInvariant() -ne $ExpectedComposerHash) {
    throw 'Le checksum SHA-256 de Composer est invalide.'
}

$env:GOOS = 'windows'
$env:GOARCH = 'amd64'
$env:CGO_ENABLED = '0'
Push-Location $Root
try {
    & go build -trimpath -ldflags '-s -w' -o "$Package\bin\aml.exe" ./cmd/aml
    if ($LASTEXITCODE -ne 0) { throw 'La compilation de aml.exe a échoué.' }
} finally {
    Pop-Location
}

Copy-Item "$Build\buildroot\bin\php.exe" "$Package\runtime\php\php.exe" -Force
Copy-Item $Composer "$Package\runtime\composer\composer.phar" -Force
Copy-Item "$Root\cli\aml.php" "$Package\runtime\bin\aml.php" -Force
Copy-Item "$Root\cli\ai-debug.php" "$Package\runtime\bin\ai-debug.php" -Force
Copy-Item "$Root\cli\deploy.php" "$Package\runtime\bin\deploy.php" -Force
Copy-Item "$Root\phpaml.json" "$Package\phpaml.json" -Force

& "$Package\bin\aml.exe" version
if ($LASTEXITCODE -ne 0) { throw 'Le test du paquet AML Windows a échoué.' }

$Zip = Join-Path $Root 'dist\aml-windows-x64.zip'
Compress-Archive -Path "$Package\*" -DestinationPath $Zip -CompressionLevel Optimal -Force

$Iscc = Join-Path ${env:ProgramFiles(x86)} 'Inno Setup 6\ISCC.exe'
if (-not (Test-Path $Iscc)) { throw 'Inno Setup 6 est introuvable.' }
& $Iscc "/DMyAppVersion=$Version" (Join-Path $Root 'installer\windows\phpaml.iss')
if ($LASTEXITCODE -ne 0) { throw "La création de l'installateur Windows a échoué." }

$Installer = Join-Path $Root "dist\phpaml-$Version-windows-x64.exe"
foreach ($Artifact in @($Zip, $Installer)) {
    $Hash = (Get-FileHash $Artifact -Algorithm SHA256).Hash.ToLowerInvariant()
    "$Hash  $([System.IO.Path]::GetFileName($Artifact))" | Set-Content "$Artifact.sha256" -Encoding ascii
}

Write-Host "Installateur créé : $Installer"
