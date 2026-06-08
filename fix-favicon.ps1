$files = Get-ChildItem -Filter "*.html"

foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw -Encoding UTF8
    $modified = $false

    # Fix various favicon patterns to use dubai-approvals.png

    # Pattern 1: favicon.ico references
    if ($content -match 'href="images/favicon\.ico"') {
        $content = $content -replace 'href="images/favicon\.ico"', 'href="images/dubai-approvals.png"'
        $modified = $true
    }

    # Pattern 2: favicon-32x32.png references
    if ($content -match 'href="images/favicon-32x32\.png"') {
        $content = $content -replace 'href="images/favicon-32x32\.png"', 'href="images/dubai-approvals.png"'
        $modified = $true
    }

    # Pattern 3: favicon-16x16.png references
    if ($content -match 'href="images/favicon-16x16\.png"') {
        $content = $content -replace 'href="images/favicon-16x16\.png"', 'href="images/dubai-approvals.png"'
        $modified = $true
    }

    # Pattern 4: Fix incorrect MIME type (type="image/dubai-approvals.png")
    if ($content -match 'type="image/dubai-approvals\.png"') {
        $content = $content -replace 'type="image/dubai-approvals\.png"', 'type="image/png"'
        $modified = $true
    }

    # Pattern 5: Fix type="image/x-icon" to type="image/png" for png files
    if ($content -match 'href="images/dubai-approvals\.png"[^>]*type="image/x-icon"') {
        $content = $content -replace '(href="images/dubai-approvals\.png"[^>]*)type="image/x-icon"', '$1type="image/png"'
        $modified = $true
    }

    if ($modified) {
        Set-Content $file.FullName -Value $content -Encoding UTF8 -NoNewline
        Write-Host "Updated: $($file.Name)"
    } else {
        Write-Host "No changes: $($file.Name)"
    }
}
