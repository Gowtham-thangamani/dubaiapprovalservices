<?php
/**
 * Blog Management API for Dubai Approval Services Admin
 * Handles CRUD operations for blog posts + generates static HTML detail pages
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

define('BLOG_FILE', DATA_DIR . '/blog_posts.json');
define('BLOG_IMAGES_DIR', dirname(dirname(__DIR__)) . '/images/blog/default');

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Initialize blog file with existing posts if it doesn't exist
if (!file_exists(BLOG_FILE)) {
    seed_existing_posts();
}

switch ($action) {
    case 'list':
        $posts = load_posts();
        echo json_encode(['success' => true, 'posts' => $posts]);
        break;

    case 'public_list':
        $posts = load_posts();
        $published = array_values(array_filter($posts, function($p) { return $p['status'] === 'published'; }));
        usort($published, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
        echo json_encode(['success' => true, 'posts' => $published]);
        break;

    case 'get':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $posts = load_posts();
        $post = null;
        foreach ($posts as $p) {
            if ($p['id'] === $id) { $post = $p; break; }
        }
        if ($post) {
            echo json_encode(['success' => true, 'post' => $post]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Post not found']);
        }
        break;

    case 'add':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['title']) || empty($input['content'])) {
            echo json_encode(['success' => false, 'message' => 'Title and content are required']);
            break;
        }
        $posts = load_posts();
        $max_id = 0;
        foreach ($posts as $p) { if ($p['id'] > $max_id) $max_id = $p['id']; }
        $new_id = $max_id + 1;
        $slug = 'blog-details' . $new_id;

        $post = [
            'id' => $new_id,
            'title' => $input['title'],
            'slug' => $slug,
            'excerpt' => isset($input['excerpt']) ? $input['excerpt'] : substr(strip_tags($input['content']), 0, 180) . '...',
            'content' => $input['content'],
            'image' => isset($input['image']) ? $input['image'] : 'images/blog/default/blog-default.jpg',
            'date' => isset($input['date']) ? $input['date'] : date('Y-m-d'),
            'author' => isset($input['author']) ? $input['author'] : 'Admin',
            'meta_title' => isset($input['meta_title']) ? $input['meta_title'] : $input['title'],
            'meta_description' => isset($input['meta_description']) ? $input['meta_description'] : (isset($input['excerpt']) ? $input['excerpt'] : substr(strip_tags($input['content']), 0, 160)),
            'meta_keywords' => isset($input['meta_keywords']) ? $input['meta_keywords'] : '',
            'status' => isset($input['status']) ? $input['status'] : 'draft',
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];

        $posts[] = $post;
        save_posts($posts);

        if ($post['status'] === 'published') {
            generate_blog_detail_html($post, $posts);
        }

        echo json_encode(['success' => true, 'post' => $post, 'message' => 'Post created successfully']);
        break;

    case 'edit':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'Post ID is required']);
            break;
        }
        $posts = load_posts();
        $found = false;
        foreach ($posts as &$p) {
            if ($p['id'] === intval($input['id'])) {
                if (isset($input['title'])) $p['title'] = $input['title'];
                if (isset($input['excerpt'])) $p['excerpt'] = $input['excerpt'];
                if (isset($input['content'])) $p['content'] = $input['content'];
                if (isset($input['image'])) $p['image'] = $input['image'];
                if (isset($input['date'])) $p['date'] = $input['date'];
                if (isset($input['author'])) $p['author'] = $input['author'];
                if (isset($input['meta_title'])) $p['meta_title'] = $input['meta_title'];
                if (isset($input['meta_description'])) $p['meta_description'] = $input['meta_description'];
                if (isset($input['meta_keywords'])) $p['meta_keywords'] = $input['meta_keywords'];
                if (isset($input['status'])) $p['status'] = $input['status'];
                $p['updated_at'] = date('c');
                $found = $p;
                break;
            }
        }
        unset($p);

        if ($found) {
            save_posts($posts);
            if ($found['status'] === 'published') {
                generate_blog_detail_html($found, $posts);
            }
            echo json_encode(['success' => true, 'post' => $found, 'message' => 'Post updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Post not found']);
        }
        break;

    case 'delete':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'Post ID is required']);
            break;
        }
        $posts = load_posts();
        $new_posts = [];
        $deleted = null;
        foreach ($posts as $p) {
            if ($p['id'] === intval($input['id'])) {
                $deleted = $p;
            } else {
                $new_posts[] = $p;
            }
        }
        if ($deleted) {
            save_posts($new_posts);
            // Optionally delete the static HTML file
            $html_file = dirname(dirname(__DIR__)) . '/' . $deleted['slug'] . '.html';
            if (file_exists($html_file) && $deleted['id'] > 3) {
                // Only auto-delete files for posts created via admin (id > 3)
                unlink($html_file);
            }
            echo json_encode(['success' => true, 'message' => 'Post deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Post not found']);
        }
        break;

    case 'upload_image':
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No image uploaded or upload error']);
            break;
        }
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Use JPG, PNG, WebP, or GIF.']);
            break;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB.']);
            break;
        }

        if (!is_dir(BLOG_IMAGES_DIR)) {
            mkdir(BLOG_IMAGES_DIR, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
        $filename = $safe_name . '-' . time() . '.' . $ext;
        $dest = BLOG_IMAGES_DIR . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $path = 'images/blog/default/' . $filename;
            echo json_encode(['success' => true, 'path' => $path, 'message' => 'Image uploaded']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save image']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// ============================================================
// Helper Functions
// ============================================================

function load_posts() {
    if (!file_exists(BLOG_FILE)) return [];
    $data = json_decode(file_get_contents(BLOG_FILE), true);
    return is_array($data) ? $data : [];
}

function save_posts($posts) {
    file_put_contents(BLOG_FILE, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function seed_existing_posts() {
    $posts = [
        [
            'id' => 1,
            'title' => 'The Importance of Engineering Consultants in Securing Dubai Authority Approvals',
            'slug' => 'blog-details1',
            'excerpt' => 'Engineering consultants ensure projects comply with Dubai\'s regulations, streamlining the approval process for smooth execution.',
            'content' => '',
            'image' => 'images/blog/default/the-role-of-engineering-consultants-in-dubai-authority-approvals.png',
            'date' => '2025-01-10',
            'author' => 'Admin',
            'meta_title' => 'Engineering Consultants for Dubai Authority Approvals',
            'meta_description' => 'Learn why engineering consultants are essential for securing Dubai authority approvals. Expert guidance on compliance, documentation, and regulatory requirements.',
            'meta_keywords' => 'engineering consultants dubai, dubai authority approvals, dubai municipality approval, construction approval dubai',
            'status' => 'published',
            'is_legacy' => true,
            'created_at' => '2025-01-10T00:00:00+04:00',
            'updated_at' => '2025-01-10T00:00:00+04:00'
        ],
        [
            'id' => 2,
            'title' => 'A Complete Guide to Renovating Your Villa in Dubai',
            'slug' => 'blog-details2',
            'excerpt' => 'This guide provides essential steps and expert tips for successfully renovating your villa in Dubai, ensuring compliance and quality.',
            'content' => '',
            'image' => 'images/blog/default/A-Guide-to-Villa-Renovation-Process-in-Dubai.png',
            'date' => '2025-01-15',
            'author' => 'Admin',
            'meta_title' => 'A Complete Guide to Villa Renovation Process in Dubai',
            'meta_description' => 'This guide provides essential steps and expert tips for successfully renovating your villa in Dubai.',
            'meta_keywords' => 'villa renovation dubai, dubai villa renovation guide, renovation approval dubai',
            'status' => 'published',
            'is_legacy' => true,
            'created_at' => '2025-01-15T00:00:00+04:00',
            'updated_at' => '2025-01-15T00:00:00+04:00'
        ],
        [
            'id' => 3,
            'title' => 'Common Construction Safety Hazards in Dubai and How to Avoid Them',
            'slug' => 'blog-details3',
            'excerpt' => 'This guide highlights common construction safety hazards in Dubai and offers practical solutions to prevent accidents and ensure a safe working environment.',
            'content' => '',
            'image' => 'images/blog/default/Common-Construction-Safety-Hazards-in-Dubai.png',
            'date' => '2025-01-19',
            'author' => 'Admin',
            'meta_title' => 'Common Construction Safety Hazards in Dubai and How to Avoid Them',
            'meta_description' => 'This guide highlights common construction safety hazards in Dubai and offers practical solutions to prevent accidents.',
            'meta_keywords' => 'construction safety dubai, safety hazards dubai, construction safety guidelines',
            'status' => 'published',
            'is_legacy' => true,
            'created_at' => '2025-01-19T00:00:00+04:00',
            'updated_at' => '2025-01-19T00:00:00+04:00'
        ]
    ];
    save_posts($posts);
}

function format_date_display($date_str) {
    $ts = strtotime($date_str);
    return date('d F', $ts);
}

function format_date_year($date_str) {
    $ts = strtotime($date_str);
    return date('Y', $ts);
}

function format_date_iso($date_str) {
    $ts = strtotime($date_str);
    return date('Y-m-d', $ts);
}

// ============================================================
// Static HTML Generator
// ============================================================

function generate_blog_detail_html($post, $all_posts) {
    $site_url = 'https://www.dubaiapprovalservices.com';
    $slug = $post['slug'];
    $title = htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8');
    $meta_title = htmlspecialchars($post['meta_title'] ?: $post['title'], ENT_QUOTES, 'UTF-8');
    $meta_desc = htmlspecialchars($post['meta_description'], ENT_QUOTES, 'UTF-8');
    $meta_keywords = htmlspecialchars($post['meta_keywords'], ENT_QUOTES, 'UTF-8');
    $image = $post['image'];
    $image_full = $site_url . '/' . $image;
    $canonical = $site_url . '/' . $slug . '.html';
    $date_display = format_date_display($post['date']);
    $date_year = format_date_year($post['date']);
    $date_iso = format_date_iso($post['date']);
    $content = $post['content'];
    $author = htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8');

    // Get recent posts for sidebar (excluding current)
    $recent = [];
    $published = array_filter($all_posts, function($p) use ($post) {
        return $p['status'] === 'published' && $p['id'] !== $post['id'];
    });
    usort($published, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
    $recent = array_slice($published, 0, 3);

    $recent_posts_html = '';
    foreach ($recent as $rp) {
        $rp_title = htmlspecialchars($rp['title'], ENT_QUOTES, 'UTF-8');
        $rp_date = format_date_display($rp['date']);
        $recent_posts_html .= '
                                                    <div class="widget-post clearfix">
                                                        <div class="mt-post-media">
                                                            <img src="' . htmlspecialchars($rp['image'], ENT_QUOTES, 'UTF-8') . '" alt="' . $rp_title . '">
                                                        </div>
                                                        <div class="mt-post-info">
                                                            <div class="mt-post-meta">
                                                                <ul>
                                                                    <li class="post-author">' . $rp_date . '</li>
                                                                    <li class="post-comment">By ' . htmlspecialchars($rp['author'], ENT_QUOTES, 'UTF-8') . '</li>
                                                                </ul>
                                                            </div>
                                                            <div class="mt-post-header">
                                                                <h6 class="post-title"><a href="' . $rp['slug'] . '.html">' . $rp_title . '</a></h6>
                                                            </div>
                                                        </div>
                                                    </div>';
    }

    // Footer recent posts
    $footer_recent = array_slice($published, 0, 2);
    $footer_recent_html = '';
    foreach ($footer_recent as $fp) {
        $fp_title = htmlspecialchars($fp['title'], ENT_QUOTES, 'UTF-8');
        $fp_ts = strtotime($fp['date']);
        $footer_recent_html .= '
                <div class="bdr-light-blue widget-post clearfix bdr-b-1 m-b10 p-b10">
                  <div class="mt-post-date text-center text-uppercase text-white p-tb5"> <strong class="p-date">' . date('d', $fp_ts) . '</strong> <span class="p-month">' . date('M', $fp_ts) . '</span> <span class="p-year">' . date('Y', $fp_ts) . '</span> </div>
                  <div class="mt-post-info">
                    <div class="mt-post-header">
                      <h6 class="post-title"><a href="' . $fp['slug'] . '.html">' . $fp_title . '</a></h6>
                    </div>
                    <div class="mt-post-meta">
                      <ul>
                        <li class="post-author"><i class="fa fa-user"></i>By ' . htmlspecialchars($fp['author'], ENT_QUOTES, 'UTF-8') . '</li>
                      </ul>
                    </div>
                  </div>
                </div>';
    }

    $html = '<!DOCTYPE html>
<html lang="en">

<head>

<!-- META -->
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>' . $meta_title . '</title>
<meta name="description" content="' . $meta_desc . '" />
<meta name="keywords" content="' . $meta_keywords . '" />
<meta name="robots" content="index, follow" />
<link rel="canonical" href="' . $canonical . '" />
<meta property="og:site_name" content="Dubai Approvals">
<meta property="og:title" content="' . $title . '">
<meta property="og:url" content="' . $canonical . '">
<meta property="og:description" content="' . $meta_desc . '">
<meta property="og:type" content="article">
<meta property="og:image" content="' . $image_full . '">

<!-- Twitter Card Meta Tags -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@dubaiapprovals">
<meta name="twitter:title" content="' . $title . '">
<meta name="twitter:description" content="' . $meta_desc . '">
<meta name="twitter:image" content="' . $image_full . '">

<!-- FAVICONS ICON -->
<link rel="icon" href="images/dubai-approvals.png" type="image/png" />
<link rel="shortcut icon" type="image/x-icon" href="images/dubai-approvals.png" />
<link rel="apple-touch-icon" href="images/dubai-approvals.png" />

<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-S1K2YER2RB"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag(\'js\', new Date());
  gtag(\'config\', \'G-S1K2YER2RB\');
</script>

<!-- Schema.org Structured Data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BlogPosting",
  "headline": "' . $title . '",
  "image": "' . $image_full . '",
  "datePublished": "' . $date_iso . '",
  "author": {
    "@type": "Organization",
    "name": "Dubai Approvals"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Dubai Approvals",
    "logo": {
      "@type": "ImageObject",
      "url": "' . $site_url . '/images/dubai-approvals.png"
    }
  },
  "description": "' . $meta_desc . '"
}
</script>

    <!-- MOBILE SPECIFIC -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- BOOTSTRAP STYLE SHEET -->
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
    <!-- FONTAWESOME STYLE SHEET -->
    <link rel="stylesheet" type="text/css" href="css/fontawesome/css/font-awesome.min.css" />
    <!-- OWL CAROUSEL STYLE SHEET -->
    <link rel="stylesheet" type="text/css" href="css/owl.carousel.min.css">
    <!-- MAGNIFIC POPUP STYLE SHEET -->
    <link rel="stylesheet" type="text/css" href="css/magnific-popup.min.css">
    <!-- LOADER STYLE SHEET -->
    <link rel="stylesheet" type="text/css" href="css/loader.min.css">
    <!-- FLATICON STYLE SHEET -->
    <link rel="stylesheet" type="text/css" href="css/flaticon.min.css">
    <!-- MAIN STYLE SHEET -->
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <!-- Color Theme Change Css -->
    <link rel="stylesheet" class="skin" type="text/css" href="css/skin/skin-1.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css?family=Poppins:200,200i,300,300i,400,400i,500,500i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

</head>
<body>

	<div class="page-wraper">

<!-- HEADER START -->
<header class="site-header header-style-1 mobile-sider-drawer-menu">
  <div class="sticky-header main-bar-wraper">
    <div class="main-bar bg-white">
      <div class="container">
        <div class="logo-header">
          <div class="logo-header-inner logo-header-one"> <a href="index.html"> <img src="images/dubai-approvals.png" alt="Dubai Approvals Logo" /> </a> </div>
        </div>
        <button id="mobile-side-drawer" data-target=".header-nav" data-toggle="collapse" type="button" class="navbar-toggler collapsed"> <span class="sr-only">Toggle navigation</span> <span class="icon-bar icon-bar-first"></span> <span class="icon-bar icon-bar-two"></span> <span class="icon-bar icon-bar-three"></span> </button>
        <div class="extra-nav">
          <div class="extra-cell"> <a href="#search"> <i class="fa fa-search"></i> </a> </div>
          <div class="extra-cell"> <a href="#" class="contact-slide-show"><i class="fa fa-angle-left arrow-animation"></i></a> </div>
        </div>
        <div class="contact-slide-hide " style="background-image:url(images/background/bg-5.png)">
          <div class="contact-nav"> <a href="javascript:void(0)" class="contact_close">&times;</a>
            <div class="contact-nav-form p-a30">
              <div class="contact-info m-b30">
                <div class="mt-icon-box-wraper center p-b30">
                  <div class="icon-xs m-b20 scale-in-center"><i class="fa fa-phone"></i></div>
                  <div class="icon-content">
                    <h5 class="m-t0 font-weight-500">Phone number</h5>
                    <p><center><a href="tel:+97142851590">+97142851590</a></center></p>
                  </div>
                </div>
                <div class="mt-icon-box-wraper center p-b30">
                  <div class="icon-xs m-b20 scale-in-center"><i class="fa fa-envelope"></i></div>
                  <div class="icon-content">
                    <h5 class="m-t0 font-weight-500">Email address</h5>
                    <p><center>info@dubaiapprovalservices.com</center></p>
                  </div>
                </div>
                <div class="mt-icon-box-wraper center p-b30">
                  <div class="icon-xs m-b20 scale-in-center"><i class="fa fa-map-marker"></i></div>
                  <div class="icon-content">
                    <h5 class="m-t0 font-weight-500">Address info</h5>
                    <p>Office 3409, 34th Floor,<br>The Citadel Tower,<br>Business Bay,<br>Dubai</p>
                  </div>
                </div>
              </div>
              <div class="full-social-bg">
                <ul>
                  <li><a href="#" class="facebook"><i class="fa fa-facebook"></i></a></li>
                  <li><a href="#" class="google"><i class="fa fa-google"></i></a></li>
                  <li><a href="#" class="instagram"><i class="fa fa-instagram"></i></a></li>
                  <li><a href="#" class="tumblr"><i class="fa fa-tumblr"></i></a></li>
                  <li><a href="#" class="twitter"><i class="fa fa-twitter"></i></a></li>
                  <li><a href="#" class="youtube"><i class="fa fa-youtube-play"></i></a></li>
                </ul>
              </div>
              <div class="text-center">
                <h4 class="font-weight-600">&copy; 2025 DUBAI APPROVAL</h4>
              </div>
            </div>
          </div>
        </div>
        <div id="search"> <span class="close"></span>
          <form role="search" id="searchform" action="#" method="get" onsubmit="return false;" class="radius-xl">
            <div class="input-group">
              <input value="" name="q" type="search" placeholder="Type to search"/>
              <span class="input-group-btn">
              <button type="button" class="search-btn"><i class="fa fa-search arrow-animation"></i></button>
              </span> </div>
          </form>
        </div>
        <div class="header-nav navbar-collapse collapse">
          <ul class=" nav navbar-nav">
            <li> <a href="index.html">Home</a> </li>
            <li> <a href="about.html">About us</a> </li>
            <li> <a href="services.html">Services</a> </li>
            <li class="active"> <a href="blog.html">Blog</a> </li>
            <li> <a href="contact.html">Contact us</a> </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</header>
<!-- HEADER END -->

        <!-- CONTENT START -->
        <div class="page-content">
            <!-- INNER PAGE BANNER -->
            <div class="mt-bnr-inr overlay-wraper bg-parallax bg-top-center" data-stellar-background-ratio="0.5" style="background-image:url(images/banner/3.jpg);">
            	<div class="overlay-main bg-black opacity-07"></div>
                <div class="container">
                    <div class="mt-bnr-inr-entry">
                    	<div class="banner-title-outer">
                        	<div class="banner-title-name">
                        		<h1 class="m-b0">Quality is what we pursue, We know what we do.</h1>
                            </div>
                        </div>
                            <div>
                                <ul class="mt-breadcrumb breadcrumb-style-2">
                                    <li><a href="index.html">Home</a></li>
                                    <li><a href="blog.html" style="color: #FFFFFF;">Blog</a></li>
                                    <li>' . $title . '</li>
                                </ul>
                            </div>
                    </div>
                </div>
            </div>
            <!-- INNER PAGE BANNER END -->

			<!-- SECTION CONTENT START -->
            <div class="section-full p-tb80 inner-page-padding">
                	<div class="container">
                    	<div class="row">
                        	<div class="col-lg-8 col-md-12">
                                <div class="news-listing">
                                     <div class="blog-post blog-lg date-style-3 block-shadow">
                                       <div class="mt-post-media mt-img-effect zoom-slow">
        <img src="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" alt="' . $title . '">
    </div>
    <div class="mt-post-info p-a30 p-m30 bg-white">
        <div class="mt-post-title">
            <h3 class="post-title">' . $title . '</h3>
        </div>
        <div class="mt-post-meta">
            <ul>
                <li class="post-date"> <i class="fa fa-calendar site-text-primary"></i><strong>' . $date_display . '</strong> <span> ' . $date_year . '</span> </li>
                <li class="post-author"><i class="fa fa-user site-text-primary"></i>By <span>' . $author . '</span></li>
                <li class="post-comment"><i class="fa fa-comments site-text-primary"></i> 0 Comments</li>
            </ul>
        </div>
        <div class="mt-post-text">
            ' . $content . '
        </div>
                                            <div class="clearfix">
                                                <div class="widget_social_inks pull-right">
                                                    <ul class="social-icons social-radius social-dark m-b0">
                                                        <li><a href="https://www.facebook.com/" class="fa fa-facebook"></a></li>
                                                        <li><a href="https://twitter.com/" class="fa fa-twitter"></a></li>
                                                        <li><a href="https://rss.com/" class="fa fa-rss"></a></li>
                                                        <li><a href="https://www.youtube.com/" class="fa fa-youtube-play"></a></li>
                                                        <li><a href="https://in.linkedin.com/" class="fa fa-instagram"></a></li>
                                                    </ul>
                                               </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- SIDE BAR START -->
                            <div class="col-lg-4 col-md-12">
                                <aside class="side-bar">
                                        <!-- SEARCH -->
                                        <div class="widget bg-white">
                                            <h4 class="widget-title">Search</h4>
                                            <div class="search-bx">
                                                <form role="search" method="post">
                                                    <div class="input-group">
                                                        <input name="news-letter" type="text" class="form-control bg-gray" placeholder="Write your text">
                                                        <span class="input-group-btn bg-gray">
                                                            <button type="submit" class="btn"><i class="fa fa-search"></i></button>
                                                        </span>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- ABOUT AUTHOR -->
                                        <div class="widget bg-white">
                                            <h4 class="widget-title">About Author</h4>
                                            <div class="widget-post m-b15">
                                                <img src="images/projects/square/pic4.jpg" alt="Dubai Approvals Blog Author" class="img-responsive">
                                            </div>
                                            <p>Engaging with the relevant authorities proactively and maintaining clear communication channels will help expedite the process and mitigate potential delays.</p>
                                        </div>

                                        <!-- RECENT POSTS -->
                                        <div class="widget bg-white recent-posts-entry">
                                            <h4 class="widget-title">Recent Posts</h4>
                                            <div class="section-content">
                                                <div class="widget-post-bx">' . $recent_posts_html . '
                                                </div>
                                            </div>
                                        </div>

                                        <!-- NEWSLETTER -->
                                        <div class="widget widget_newsletter-2 bg-white">
                                            <h4 class="widget-title">Newsletter</h4>
                                            <div class="newsletter-bx p-a30">
                                                <div class="newsletter-icon"><i class="fa fa-envelope-o"></i></div>
                                                <div class="newsletter-content"><p>Subscribe to our mailing list to get the update to your email.</p></div>
                                                <div class="m-t20">
                                                    <form role="search" method="post">
                                                    <div class="input-group">
                                                        <input name="news-letter" class="form-control" placeholder="ENTER YOUR EMAIL" type="text">
                                                        <span class="input-group-btn"><button type="submit" class="site-button"><i class="fa fa-paper-plane-o"></i></button></span>
                                                    </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                </aside>
                            </div>
                            <!-- SIDE BAR END -->
                        </div>
            		</div>
            </div>
            <!-- SECTION CONTENT END -->

        </div>
        <!-- CONTENT END -->

        <!-- FOOTER START -->
  <footer class="site-footer footer-large footer-dark footer-wide">
    <div class="footer-top overlay-wraper">
      <div class="overlay-main"></div>
      <div class="container">
        <div class="row">
          <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="widget widget_about">
              <div class="logo-footer clearfix p-b15"> <a href="index.html"><img src="images/dubai-approvals.png" alt="Dubai Approvals Logo"></a> </div>
              <p class="max-w400" style="color: #FFFFFF;">Dubai Approval Services, your reliable partner in guiding you through the intricacies of obtaining approvals in the dynamic city of Dubai.</p>
              <ul class="social-icons mt-social-links">
                <li><a href="https://www.facebook.com/dubaiapprovals" class="fa fa-facebook" target="_blank"></a></li>
                <li><a href="https://www.instagram.com/dubaiapproval" class="fa fa-instagram" target="_blank"></a></li>
                <li><a href="https://x.com/dubaiapprovalss" class="fa fa-twitter" target="_blank"></a></li>
                <li><a href="https://www.linkedin.com/company/dubai-approval" class="fa fa-linkedin" target="_blank"></a></li>
                <li><a href="https://www.youtube.com/@dubaiapproval" class="fa fa-youtube-play" target="_blank"></a></li>
              </ul>
            </div>
          </div>
          <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="widget widget_address_outer">
              <h4 class="widget-title">Contact Us</h4>
              <ul class="widget_address" style="color: #FFFFFF;">
                <li style="color: #FFFFFF;">Office 3409, 34th Floor, The Citadel Tower, Business Bay,<br>Dubai</li>
                <li><a href="mailto:info@dubaiapprovalservices.com" style="color: #FFFFFF;">info@dubaiapprovalservices.com</a></li>
                <li><a href="tel:+97142851590" style="color:#FFFFFF;">+97142851590</a></li>
                <li><a href="tel:+971542323854" style="color:#FFFFFF;">+971 54 232 3854</a></li>
              </ul>
            </div>
          </div>
          <div class="col-lg-3 col-md-6 col-sm-6 footer-col-3">
            <div class="widget widget_services inline-links">
              <h4 class="widget-title">Useful links</h4>
              <ul>
                <li><a href="about.html" style="color: #FFFFFF;">About</a></li>
                <li><a href="services.html" style="color: #FFFFFF;">Services</a></li>
                <li><a href="blog.html" style="color: #FFFFFF;">Blog</a></li>
                <li><a href="contact.html" style="color: #FFFFFF;">Contact Us</a></li>
              </ul>
            </div>
          </div>
          <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="widget recent-posts-entry-date">
              <h4 class="widget-title">Resent Post</h4>
              <div class="widget-post-bx">' . $footer_recent_html . '
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="footer-bottom overlay-wraper">
      <div class="overlay-main"></div>
      <div class="container">
        <div class="row">
          <div class="mt-footer-bot-center"> <span class="copyrights-text"><a href="https://www.aaaa.ae/" style="color:#FFFFFF"> 2025 DUBAI-APPROVALS. All Rights Reserved.</a></span> </div>
        </div>
      </div>
    </div>
  </footer>
  <!-- FOOTER END -->
		<button class="scroltop"><span class="fa fa-angle-up relative" id="btn-vibrate"></span></button>
    </div>

<!-- JAVASCRIPT FILES -->
<script src="js/jquery-3.6.1.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/popper.min.js"></script>
<script src="js/magnific-popup.min.js"></script>
<script src="js/waypoints.min.js"></script>
<script src="js/counterup.min.js"></script>
<script src="js/waypoints-sticky.min.js"></script>
<script src="js/isotope.pkgd.min.js"></script>
<script src="js/imagesloaded.pkgd.min.js"></script>
<script src="js/owl.carousel.min.js"></script>
<script src="js/jquery.owl-filter.js"></script>
<script src="js/custom.js"></script>
<script src="js/shortcode.js"></script>
<script src="js/jquery.bgscroll.js"></script>

<div class="contact-buttons">
  <a href="tel:+97142851590" class="contact-button call-button"> <img src="images/call now.png" alt="Call Now"> </a>
  <a href="https://wa.me/+971542323854" class="contact-button whatsapp-button" target="_blank"> <img src="images/whatsapp.png" alt="WhatsApp"> </a>
</div>
</body>

</html>';

    $output_file = dirname(dirname(__DIR__)) . '/' . $slug . '.html';
    file_put_contents($output_file, $html);
    return true;
}
?>