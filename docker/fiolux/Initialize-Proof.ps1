#requires -Version 7.4
$ErrorActionPreference = 'Stop'
$proofRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$environmentPath = Join-Path $proofRoot '.env'
$testingPath = Join-Path $proofRoot '.env.testing'
if ((Test-Path -LiteralPath $environmentPath) -or (Test-Path -LiteralPath $testingPath)) {
    throw 'Já existe configuração local. Nenhum arquivo foi sobrescrito.'
}
$applicationKey = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$databasePassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
$rootPassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
$demoPassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(18))
$environmentLines = @(
    'APP_ENV=production', 'APP_DEBUG=false', "APP_KEY=base64:$applicationKey",
    'APP_URL=http://localhost:3041', 'APP_TIMEZONE=America/Sao_Paulo', 'APP_LOCALE=pt-BR',
    'DB_CONNECTION=mysql', 'DB_HOST=db', 'DB_PORT=3306', 'DB_DATABASE=teste_fiolux_poc',
    'DB_USERNAME=teste_fiolux', "DB_PASSWORD=$databasePassword", "MYSQL_ROOT_PASSWORD=$rootPassword",
    'MAIL_MAILER=array', 'MAIL_DRIVER=array', 'MAIL_FROM_ADDR=teste-fiolux@example.invalid',
    'MAIL_FROM_NAME="TESTE FIOLUX"', 'QUEUE_CONNECTION=sync', 'SESSION_DRIVER=file',
    'CACHE_DRIVER=file', 'ALLOW_BACKUP_DELETE=false', 'FIOLUX_ENABLED=true',
    "FIOLUX_DEMO_PASSWORD=$demoPassword"
)
[IO.File]::WriteAllLines($environmentPath, $environmentLines, [Text.UTF8Encoding]::new($false))
$testingLines = $environmentLines | Where-Object { $_ -notmatch '^MYSQL_ROOT_PASSWORD=|^FIOLUX_DEMO_PASSWORD=' } | ForEach-Object {
    $_ -replace '^APP_ENV=production$', 'APP_ENV=testing' -replace '^DB_DATABASE=teste_fiolux_poc$', 'DB_DATABASE=teste_fiolux_tests' -replace '^SESSION_DRIVER=file$', 'SESSION_DRIVER=array' -replace '^CACHE_DRIVER=file$', 'CACHE_DRIVER=array'
}
[IO.File]::WriteAllLines($testingPath, $testingLines, [Text.UTF8Encoding]::new($false))
Write-Output 'Configurações exclusivas de teste criadas. Valores secretos não foram exibidos.'
