$file = "index.html"
$content = Get-Content $file -Raw -Encoding UTF8

# Fix header logo
$content = $content -replace '<img src="images/dubai-approvals.png" alt="" />', '<img src="images/dubai-approvals.png" alt="Dubai Approvals Logo" />'

# Fix slider images
$content = $content -replace '<img src="images/main-slider/slider1/slide1.png" alt=""', '<img src="images/main-slider/slider1/slide1.png" alt="Dubai Approval Services - Engineering Solutions"'
$content = $content -replace '<img src="images/main-slider/slider1/slide2.png" alt=""', '<img src="images/main-slider/slider1/slide2.png" alt="Dubai Municipality Approval Services"'
$content = $content -replace '<img src="images/main-slider/slider1/slide3.png" alt=""', '<img src="images/main-slider/slider1/slide3.png" alt="Civil Defense Approval in Dubai"'
$content = $content -replace '<img src="images/main-slider/slider1/slide4.png" alt=""', '<img src="images/main-slider/slider1/slide4.png" alt="DEWA and RTA Approval Services"'

# Fix gallery images
$content = $content -replace '<img src="images/gallery/pic1.png" alt="">', '<img src="images/gallery/pic1.png" alt="Dubai Approval Projects">'
$content = $content -replace '<img src="images/gallery/pic2.png" alt="">', '<img src="images/gallery/pic2.png" alt="Engineering Consultation Services">'
$content = $content -replace '<img src="images/gallery/pic3.png" alt="">', '<img src="images/gallery/pic3.png" alt="Construction Approval Projects">'
$content = $content -replace '<img src="images/gallery/pic4.png" alt="">', '<img src="images/gallery/pic4.png" alt="Dubai Development Projects">'
$content = $content -replace '<img src="images/gallery/pic5.png" alt="">', '<img src="images/gallery/pic5.png" alt="Building Approval Services">'

# Fix testimonial images
$content = $content -replace '<img src="images/testimonials/pic1.jpg" width="100" height="100" alt="">', '<img src="images/testimonials/pic1.jpg" width="100" height="100" alt="Rasha J. Client Testimonial">'
$content = $content -replace '<img src="images/testimonials/pic2.jpg" width="100" height="100" alt="">', '<img src="images/testimonials/pic2.jpg" width="100" height="100" alt="Maha S. Client Testimonial">'
$content = $content -replace '<img src="images/testimonials/pic3.jpg" width="100" height="100" alt="">', '<img src="images/testimonials/pic3.jpg" width="100" height="100" alt="Laila F. Client Testimonial">'
$content = $content -replace '<img src="images/testimonials/pic4.jpg" width="100" height="100" alt="">', '<img src="images/testimonials/pic4.jpg" width="100" height="100" alt="Ali M. Client Testimonial">'
$content = $content -replace '<img src="images/testimonials/pic5.jpg" width="100" height="100" alt="">', '<img src="images/testimonials/pic5.jpg" width="100" height="100" alt="Omar B. Client Testimonial">'

# Fix footer logo
$content = $content -replace 'logo-footer clearfix p-b15"> <a href="index.html"><img src="images/dubai-approvals.png" alt="">', 'logo-footer clearfix p-b15"> <a href="index.html"><img src="images/dubai-approvals.png" alt="Dubai Approvals Footer Logo">'

Set-Content $file -Value $content -Encoding UTF8 -NoNewline
Write-Host "Updated ALT tags in: $file"
