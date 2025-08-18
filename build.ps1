# OptimizadorPro Build Script for Windows PowerShell
# Genera un ZIP listo para distribución e instalación

param(
    [switch]$Clean = $false
)

# Configuration
$PluginName = "optimizador-pro"
$Version = (Get-Content "optimizador-pro.php" | Select-String "Version:" | ForEach-Object { $_.Line -replace ".*Version:\s*", "" -replace "\s.*", "" })
$BuildDir = "build"
$DistDir = "dist"
$ZipName = "$PluginName-v$Version.zip"

Write-Host "🚀 OptimizadorPro Build Script" -ForegroundColor Blue
Write-Host "================================" -ForegroundColor Blue
Write-Host "Plugin: " -NoNewline; Write-Host $PluginName -ForegroundColor Green
Write-Host "Version: " -NoNewline; Write-Host $Version -ForegroundColor Green
Write-Host "Output: " -NoNewline; Write-Host $ZipName -ForegroundColor Green
Write-Host ""

# Clean previous builds
Write-Host "🧹 Cleaning previous builds..." -ForegroundColor Yellow
if (Test-Path $BuildDir) { Remove-Item -Recurse -Force $BuildDir }
if (Test-Path $DistDir) { Remove-Item -Recurse -Force $DistDir }
New-Item -ItemType Directory -Path $BuildDir -Force | Out-Null
New-Item -ItemType Directory -Path $DistDir -Force | Out-Null

# Copy plugin files to build directory
Write-Host "📁 Copying plugin files..." -ForegroundColor Yellow
$DestPath = Join-Path $BuildDir $PluginName
Copy-Item -Path "." -Destination $DestPath -Recurse -Force

# Enter build directory
Push-Location (Join-Path $BuildDir $PluginName)

# Install Composer dependencies for production
Write-Host "📦 Installing Composer dependencies (production)..." -ForegroundColor Yellow
if (Test-Path "composer.json") {
    try {
        & composer install --no-dev --optimize-autoloader --no-interaction
        Write-Host "✅ Composer dependencies installed" -ForegroundColor Green
    }
    catch {
        Write-Host "❌ Error installing Composer dependencies" -ForegroundColor Red
        Pop-Location
        exit 1
    }
} else {
    Write-Host "❌ composer.json not found" -ForegroundColor Red
    Pop-Location
    exit 1
}

# Remove development files and directories
Write-Host "🗑️  Removing development files..." -ForegroundColor Yellow

$DevFiles = @(
    "build.sh",
    "build.ps1",
    "build",
    "dist",
    ".git",
    ".gitignore",
    ".gitattributes",
    "composer.lock",
    "phpunit.xml",
    "phpcs.xml",
    ".phpcs.xml.dist",
    "tests",
    "node_modules",
    "package.json",
    "package-lock.json",
    "webpack.config.js",
    "gulpfile.js",
    ".editorconfig",
    ".vscode",
    ".idea",
    "wp-rocket",
    "plan.md"
)

foreach ($file in $DevFiles) {
    if (Test-Path $file) {
        Remove-Item -Recurse -Force $file
        Write-Host "  ✓ Removed: $file" -ForegroundColor Green
    }
}

# Remove log files
Get-ChildItem -Filter "*.log" -Recurse | Remove-Item -Force

# Go back to original directory
Pop-Location

# Create ZIP file
Write-Host "📦 Creating ZIP file..." -ForegroundColor Yellow
$SourcePath = Join-Path $BuildDir $PluginName
$ZipPath = Join-Path $DistDir $ZipName

try {
    Compress-Archive -Path $SourcePath -DestinationPath $ZipPath -Force
    Write-Host "✅ ZIP file created successfully" -ForegroundColor Green
}
catch {
    Write-Host "❌ Error creating ZIP file" -ForegroundColor Red
    exit 1
}

# Get file size
$FileSize = [math]::Round((Get-Item $ZipPath).Length / 1MB, 2)

# Verify ZIP contents
Write-Host "🔍 Verifying ZIP contents..." -ForegroundColor Yellow
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
$zip.Entries | Select-Object -First 20 | ForEach-Object { Write-Host "  $($_.FullName)" }
$zip.Dispose()

Write-Host ""
Write-Host "🎉 Build completed successfully!" -ForegroundColor Green
Write-Host "================================" -ForegroundColor Green
Write-Host "File: " -NoNewline; Write-Host $ZipPath -ForegroundColor Blue
Write-Host "Size: " -NoNewline; Write-Host "$FileSize MB" -ForegroundColor Blue
Write-Host "Ready for installation in WordPress!"
Write-Host ""

# Show installation instructions
Write-Host "📋 Installation Instructions:" -ForegroundColor Blue
Write-Host "1. Go to WordPress Admin → Plugins → Add New"
Write-Host "2. Click 'Upload Plugin'"
Write-Host "3. Choose: " -NoNewline; Write-Host $ZipPath -ForegroundColor Green
Write-Host "4. Click 'Install Now'"
Write-Host "5. Activate the plugin"
Write-Host "6. Go to Settings → OptimizadorPro to configure"
Write-Host ""

# Cleanup build directory
Write-Host "🧹 Cleaning up build directory..." -ForegroundColor Yellow
Remove-Item -Recurse -Force $BuildDir

Write-Host "✨ All done!" -ForegroundColor Green
