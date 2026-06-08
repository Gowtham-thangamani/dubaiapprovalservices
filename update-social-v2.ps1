$files = @(
    'about.html', 'blog.html', 'contact.html',
    'blog-details1.html', 'blog-details2.html', 'blog-details3.html'
)

$newSocialBlock = @'
<li><a href="https://www.facebook.com/dubaiapprovals" class="fa fa-facebook" target="_blank"></a></li>
                <li><a href="https://www.instagram.com/dubaiapproval" class="fa fa-instagram" target="_blank"></a></li>
                <li><a href="https://x.com/dubaiapprovalss" class="fa fa-twitter" target="_blank"></a></li>
                <li><a href="https://www.linkedin.com/company/dubai-approval" class="fa fa-linkedin" target="_blank"></a></li>
                <li><a href="http://www.youtube.com/@dubaiapproval" class="fa fa-youtube-play" target="_blank"></a></li>
'@

foreach ($file in $files) {
    if (Test-Path $file) {
        $content = Get-Content $file -Raw -Encoding UTF8

        # Pattern to match the social media block in footer
        $pattern = '(?s)(<ul class="social-icons\s+mt-social-links">)\s*<li><a href="https://www\.google\.com/".*?</ul>'

        if ($content -match $pattern) {
            $content = $content -replace $pattern, ('$1' + "`n                " + $newSocialBlock + "`n              </ul>")
            Set-Content $file -Value $content -Encoding UTF8 -NoNewline
            Write-Host "Updated: $file"
        } else {
            Write-Host "Pattern not found or already updated: $file"
        }
    } else {
        Write-Host "Not found: $file"
    }
}
