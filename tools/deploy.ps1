# Деплой сайта «Трио» на свой сервер из Windows-терминала (PowerShell или cmd): сборка и заливка dist по SSH.
# Нужны встроенные в Windows ssh, scp и tar (есть в Windows 10/11) и Node.js для сборки.
#
#   npm run deploy                  обновить сайт: код и картинки. НЕ трогает то, что на сервере меняют админка и посетители:
#                                   data/ (тексты и работы из админки), uploads/, admin/_data/ (база, аккаунты), form-config.php, docs/files/
#   npm run deploy -- -Dry          только показать, что будет залито
#   npm run deploy -- -WithData     ещё и залить data/*.json с компьютера (перезапишет правки из админки на сервере!)
#   npm run deploy -- -First        первый запуск: залить всё, включая data/
#
# Настройки: tools/deploy.config (создать из tools/deploy.config.example, в git не попадает).
param([switch]$Dry, [switch]$WithData, [switch]$First)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
if ($First) { $WithData = $true }

$cfgFile = 'tools\deploy.config'
if (-not (Test-Path $cfgFile)) { throw "Нет $cfgFile. Скопируйте tools\deploy.config.example в tools\deploy.config и заполните." }
$cfg = @{}
Get-Content $cfgFile -Encoding UTF8 | ForEach-Object {
    if ($_ -match '^\s*([A-Z_]+)\s*=\s*(.*?)\s*$' -and $_ -notmatch '^\s*#') { $cfg[$Matches[1]] = $Matches[2] }
}
foreach ($k in 'DEPLOY_HOST', 'DEPLOY_USER', 'DEPLOY_PATH') { if (-not $cfg[$k]) { throw "В deploy.config не указан $k" } }
$port = if ($cfg['DEPLOY_PORT']) { $cfg['DEPLOY_PORT'] } else { '22' }
$target = "$($cfg['DEPLOY_USER'])@$($cfg['DEPLOY_HOST'])"
$path = $cfg['DEPLOY_PATH']

function Invoke-Remote([string]$cmd) {
    & ssh -p $port -o ConnectTimeout=10 $target $cmd
    if ($LASTEXITCODE -ne 0) { throw "Команда на сервере завершилась с ошибкой: $cmd" }
}

Write-Host '==> Сборка сайта'
& npm run build | Out-Null
if ($LASTEXITCODE -ne 0 -or -not (Test-Path 'dist\index.html')) { throw 'Сборка не удалась (нет dist\index.html)' }

# что НЕ заливаем (его меняют на сервере админка и посетители)
$skip = @('^admin/_data/', '^uploads/', '^form-config\.php$', '^docs/files/')
if (-not $WithData) { $skip += '^data/' }
if ($First) { $skip = @('^form-config\.php$') }

$root = (Resolve-Path 'dist').Path
$files = Get-ChildItem $root -Recurse -File | ForEach-Object {
    $_.FullName.Substring($root.Length + 1).Replace('\', '/')
} | Where-Object { $rel = $_; -not ($skip | Where-Object { $rel -match $_ }) }
$files = @($files)

if ($Dry) {
    Write-Host "==> Будет залито файлов: $($files.Count) (пробный запуск, ничего не отправлено)"
    $files | Select-Object -First 40 | ForEach-Object { "  $_" }
    if ($files.Count -gt 40) { '  …' }
    return
}

$list = Join-Path $env:TEMP 'oknatrio-files.txt'
$arc = Join-Path $env:TEMP 'oknatrio-deploy.tgz'
[IO.File]::WriteAllLines($list, $files, (New-Object Text.UTF8Encoding($false)))
& tar -czf $arc -C $root -T $list
if ($LASTEXITCODE -ne 0) { throw 'Не удалось создать архив' }

Write-Host "==> Проверка доступа к $target"
Invoke-Remote "mkdir -p '$path' && echo ok" | Out-Null

Write-Host '==> Резервная копия текущей версии на сервере'
$bk = "mkdir -p '$path/../oknatrio-backups' && tar -C '$path' --exclude=./uploads --exclude=./docs -czf `"$path/../oknatrio-backups/site-`$(date +%Y%m%d-%H%M%S).tar.gz`" . ; ls -1t '$path/../oknatrio-backups' | tail -n +11 | while read f; do rm -f `"$path/../oknatrio-backups/`$f`"; done; true"
try { Invoke-Remote $bk } catch { Write-Host '   (копию сделать не удалось, продолжаем)' }

Write-Host '==> Заливка файлов'
& scp -P $port -q $arc "${target}:/tmp/oknatrio-deploy.tgz"
if ($LASTEXITCODE -ne 0) { throw 'Не удалось передать архив (scp)' }
Invoke-Remote "tar -xzf /tmp/oknatrio-deploy.tgz -C '$path' --no-same-owner && rm -f /tmp/oknatrio-deploy.tgz"

Write-Host '==> Права на файлы'
$perm = "cd '$path' && find . \( -path ./admin/_data -o -path ./uploads -o -path ./data \) -prune -o -type d -exec chmod 755 {} + -o -type f -exec chmod 644 {} + ; mkdir -p data uploads docs/files admin/_data; chmod 775 data uploads docs/files admin/_data 2>/dev/null; if [ ! -f form-config.php ]; then echo '   ВНИМАНИЕ: на сервере нет form-config.php (создайте из form-config.example.php, иначе заявки не уйдут)'; fi"
Invoke-Remote $perm

Remove-Item $list, $arc -ErrorAction SilentlyContinue

if ($cfg['SITE_URL']) {
    Write-Host "==> Проверка сайта $($cfg['SITE_URL'])"
    foreach ($u in '/', '/data/pages.json') {
        try { $code = (Invoke-WebRequest "$($cfg['SITE_URL'])$u" -UseBasicParsing -TimeoutSec 15).StatusCode } catch { $code = $_.Exception.Message }
        Write-Host "   ${u}: $code"
    }
}
Write-Host '==> Готово'
