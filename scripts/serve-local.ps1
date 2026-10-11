param([ValidateRange(1, 65535)][int]$Port = 8080)

$projectRoot = Split-Path -Parent $PSScriptRoot
$reportTempPath = Join-Path $projectRoot 'writable/php-temp'
New-Item -ItemType Directory -Path $reportTempPath -Force | Out-Null
$phpBinary = (Get-Command php -ErrorAction Stop).Source
$documentRoot = Join-Path $projectRoot 'public'
$router = Join-Path $projectRoot 'vendor/codeigniter4/framework/system/rewrite.php'
# PHP needs a writable temporary folder before application code starts executing.
& $phpBinary -d "sys_temp_dir=$reportTempPath" -d "upload_tmp_dir=$reportTempPath" -S "127.0.0.1:$Port" -t $documentRoot $router
exit $LASTEXITCODE
