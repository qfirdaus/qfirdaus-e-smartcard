param()

# Expected authorized absolute path
$expected = '/var/www/app/e-smartcard-uat'

# Try to get the git repository root if available
$gitRoot = $null
try {
    $gitRootRaw = & git rev-parse --show-toplevel 2>$null
    if ($LASTEXITCODE -eq 0 -and $gitRootRaw) { $gitRoot = $gitRootRaw.Trim() }
} catch {
}

if ($gitRoot) { $pathToCheck = $gitRoot } else { $pathToCheck = (Get-Location).Path }

# Normalize paths
$normalize = { param($p) [IO.Path]::GetFullPath($p).TrimEnd('\','/') }
$actual = & $normalize $pathToCheck
$expectedNorm = & $normalize $expected

if ($actual -ieq $expectedNorm) {
    exit 0
} else {
    Write-Error "Unauthorized project location detected. Expected '$expectedNorm' but found '$actual'."
    exit 1
}
