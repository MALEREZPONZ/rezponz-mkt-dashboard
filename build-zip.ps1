$src = 'C:\Users\male-\projects\rezponz-mkt-dashboard'
$ver = '2.2.7'
$out = "C:\Users\male-\projects\versions\rezponz-mkt-dashboard-v$ver.zip"
Add-Type -AssemblyName System.IO.Compression.FileSystem
if (Test-Path $out) { Remove-Item $out }
$zip = [System.IO.Compression.ZipFile]::Open($out, 'Create')
Get-ChildItem -Path $src -Recurse -File | Where-Object {
    $rel = $_.FullName.Substring($src.Length + 1)
    ($rel -notmatch [regex]::Escape('.git')) -and
    ($_.Name -notmatch '\.zip$') -and
    ($_.Name -ne 'docker-compose.yml') -and
    ($_.Name -ne 'build-zip.ps1')
} | ForEach-Object {
    $entry = $_.FullName
    $rel2 = $entry.Substring($src.Length + 1)
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $entry, ('rezponz-mkt-dashboard/' + $rel2)) | Out-Null
}
$zip.Dispose()
Write-Host "Done: $out"
