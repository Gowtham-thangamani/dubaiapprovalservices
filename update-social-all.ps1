$files = @(
    'about.html', 'blog.html', 'contact.html',
    'blog-details1.html', 'blog-details2.html', 'blog-details3.html'
)

$oldSocialBlock = @'
<li><a href="https://www.google.com/" class="fa fa-google"></a></li>
                <li><a href="https://rss.com/" class="fa fa-rss"></a></li>
                <li><a href="https://www.facebook.com/" class="fa fa-facebook"></a></li>
                <li><a href="https://twitter.com/" class="fa fa-twitter"></a></li>
                <li><a href="https://in.linkedin.com/" class="fa fa-linkedin"></a></li>
'@

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
        if ($content -match 'fa-google') {
            $content = $content.Replace($oldSocialBlock, $newSocialBlock)
            Set-Content $file -Value $content -Encoding UTF8 -NoNewline
            Write-Host "Updated: $file"
        } else {
            Write-Host "Already updated: $file"
        }
    } else {
        Write-Host "Not found: $file"
    }
}
