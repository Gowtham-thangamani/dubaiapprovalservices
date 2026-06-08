$files = @(
    'services.html', 'about.html', 'blog.html', 'contact.html',
    'civil-defense-approval.html', 'dewa-approval.html', 'dso-approval.html',
    'dubai-development-authority.html', 'dubai-municipality-approval.html',
    'emaar-approval.html', 'food-control-department-approval.html',
    'jafza-approval.html', 'nakheel-approval.html', 'sand-transfer-permit.html',
    'shisha-cafe-license-dubai.html', 'signage-approval.html', 'smoking-permit.html',
    'spa-approval.html', 'swimming-pool-approval.html', 'tecom-and-dcca-approval.html',
    'third-party-consultants-approval.html', 'trakhees-approval.html',
    'rta-permit-and-approval.html', 'solar-approval.html', 'tent-approval.html',
    'dha-approval.html', 'property-snagging-and-inspection.html',
    'diez-approval.html', 'dubai-south-approval.html', 'dhcc-approval.html',
    'gas-approval.html', 'mezzanine-approval.html', 'concordia-approval.html',
    'blog-details1.html', 'blog-details2.html', 'blog-details3.html'
)

foreach ($file in $files) {
    if (Test-Path $file) {
        $content = Get-Content $file -Raw -Encoding UTF8
        $content = $content.Replace('class="fa fa-youtube"', 'class="fa fa-youtube-play"')
        Set-Content $file -Value $content -Encoding UTF8 -NoNewline
        Write-Host "Updated: $file"
    } else {
        Write-Host "Not found: $file"
    }
}
