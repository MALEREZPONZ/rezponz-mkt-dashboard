Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead('C:\Users\male-\projects\rezponz-mkt-dashboard\rezponz-mkt-dashboard-v2.0.8.zip')
$entries = $zip.Entries | Where-Object { $_.Name -like '*logo*' -or $_.Name -like '*Logo*' }
$entries | ForEach-Object { Write-Host $_.FullName }
$png = $zip.Entries | Where-Object { $_.Name -like '*.png' }
if ($png) {
    $dest = 'C:\Users\male-\projects\rezponz-mkt-dashboard\assets\Rezponz-logo.png'
    [System.IO.Compression.ZipFileExtensions]::ExtractToFile($png[0], $dest, $true)
    Write-Host "Extracted: $($png[0].FullName) -> $dest"
} else {
    Write-Host "No PNG found"
}
$zip.Dispose()
