Add-Type -AssemblyName System.Drawing
$images = Get-ChildItem -Path "images" -Recurse -Include "*.png","*.jpg","*.jpeg","*.webp","*.gif" -File
foreach ($img in $images) {
    try {
        $bitmap = [System.Drawing.Image]::FromFile($img.FullName)
        $relativePath = $img.FullName.Replace((Get-Location).Path + "\", "").Replace("\", "/")
        Write-Host "$relativePath|$($bitmap.Width)|$($bitmap.Height)"
        $bitmap.Dispose()
    } catch {
        Write-Host "$($img.Name)|ERROR|$($_.Exception.Message)"
    }
}
