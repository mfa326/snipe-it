#requires -Version 7.4
param([string]$Filter = '')
$ErrorActionPreference = 'Stop'
$proofRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$proofMutex = [Threading.Mutex]::new($false, 'Local\FioluxSnipeItTests')
$hasMutex = $false
try {
    $hasMutex = $proofMutex.WaitOne(0)
    if (-not $hasMutex) { throw 'Já existe uma execução dos testes Fiolux. Aguarde sua conclusão.' }
    Push-Location $proofRoot
    try {
        docker compose -f compose.fiolux.yml exec -T db sh -c 'export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"; mariadb -uroot -e "CREATE DATABASE IF NOT EXISTS teste_fiolux_tests; GRANT ALL ON teste_fiolux_tests.* TO \"teste_fiolux\"@\"%\";"'
        if ($LASTEXITCODE -ne 0) { throw 'Não foi possível preparar o banco exclusivo de testes.' }
        $testArguments = @('compose', '-f', 'compose.fiolux.yml', 'exec', '-T', '--user', 'docker', '-e', 'DB_DATABASE=teste_fiolux_tests', '-e', 'APP_ENV=testing', '-e', 'FIOLUX_ENABLED=true', 'app', 'php', 'vendor/bin/phpunit', 'tests/Feature/Fiolux', '--cache-directory', 'storage/framework/testing/phpunit-cache', '--testdox', '--log-junit', 'storage/logs/fiolux-tests.xml')
        if ($Filter) { $testArguments += @('--filter', $Filter) }
        & docker @testArguments
        if ($LASTEXITCODE -ne 0) { throw 'Os testes Fiolux falharam. Consulte o resultado acima.' }
    } finally {
        Pop-Location
    }
} finally {
    if ($hasMutex) { $proofMutex.ReleaseMutex() }
    $proofMutex.Dispose()
}
