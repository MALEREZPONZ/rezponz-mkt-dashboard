$viewsDir = 'C:\Users\male-\projects\rezponz-mkt-dashboard\admin\views'
Get-ChildItem -Path $viewsDir -Filter '*.php' | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    $updated = $content -replace 'assets/logo\.svg', 'assets/Rezponz-logo.png'
    if ($updated -ne $content) {
        Set-Content -Path $_.FullName -Value $updated -NoNewline
        Write-Host "Updated: $($_.Name)"
    }
}
Write-Host "Done"
