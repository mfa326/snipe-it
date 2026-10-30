#requires -Version 7.4
$ErrorActionPreference = 'Stop'
$proofRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
Push-Location $proofRoot
try {
    $workerPath = 'tests/Fixtures/Fiolux/concurrency-worker.php'
    $beforeText = & docker compose -f compose.fiolux.yml exec -T app php $workerPath summary
    if ($LASTEXITCODE -ne 0) { throw 'Falha na pré-condição do teste concorrente.' }
    $before = $beforeText | ConvertFrom-Json
    if (-not $before.ready -or $null -ne $before.assigned_to) { throw 'Conclua o ciclo atual, a avaliação técnica e os documentos antes deste teste.' }
    $operationKey = [Guid]::NewGuid().ToString()
    $workers = foreach ($index in 1..2) {
        $startInfo = [Diagnostics.ProcessStartInfo]::new('docker')
        $startInfo.WorkingDirectory = $proofRoot
        $startInfo.UseShellExecute = $false
        $startInfo.CreateNoWindow = $true
        $startInfo.RedirectStandardOutput = $true
        $startInfo.RedirectStandardError = $true
        foreach ($argument in @('compose', '-f', 'compose.fiolux.yml', 'exec', '-T', 'app', 'php', $workerPath, 'checkout', $operationKey)) { $startInfo.ArgumentList.Add($argument) }
        $process = [Diagnostics.Process]::new()
        $process.StartInfo = $startInfo
        [void]$process.Start()
        [PSCustomObject]@{ Process = $process; Output = $process.StandardOutput.ReadToEndAsync(); Error = $process.StandardError.ReadToEndAsync() }
    }
    $results = foreach ($worker in $workers) {
        if (-not $worker.Process.WaitForExit(45000)) {
            $worker.Process.Kill($true)
            throw 'Worker excedeu 45 segundos. Verifique o estado do ativo antes de repetir.'
        }
        if ($worker.Process.ExitCode -ne 0) { throw ('Worker falhou: ' + $worker.Error.GetAwaiter().GetResult()) }
        $worker.Output.GetAwaiter().GetResult() | ConvertFrom-Json
        $worker.Process.Dispose()
    }
    $afterText = & docker compose -f compose.fiolux.yml exec -T app php $workerPath summary
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao verificar o resultado.' }
    $after = $afterText | ConvertFrom-Json
    if ($results[0].inspection_id -ne $results[1].inspection_id -or $results[0].cycle_id -ne $results[1].cycle_id -or $after.cycles -ne ($before.cycles + 1) -or $after.inspections -ne ($before.inspections + 1) -or $after.checkouts -ne ($before.checkouts + 1)) {
        throw 'Concorrência reprovada: houve divergência de IDs ou contagens.'
    }
    Write-Output ('APROVADO: duas requisições simultâneas produziram uma entrega, ciclo ' + $results[0].cycle_id + ', conferência ' + $results[0].inspection_id + '.')
    Write-Output 'O ciclo fictício fica aberto para validar a devolução pela interface; documentos permanecem pendentes.'
} finally {
    Pop-Location
}
