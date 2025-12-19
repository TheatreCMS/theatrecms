<?php

/**
 * CoreUI Free Bootstrap Admin Template
 * @version v5.3.0
 * @link https://coreui.io/product/free-bootstrap-admin-template/
 * Copyright (c) 2025 creativeLabs Łukasz Holeczek
 */

require '../../vendor/autoload.php';

use Clubdeuce\TheatreCMS\Models\Season;
use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

try {
    /** @var Psr\Container\ContainerInterface  $container */
    $container = require '../../app/bootstrap.php';

    /** @var SeasonRepository $seasonRepository */
    $seasonRepository = $container->get(SeasonRepository::class);

    $id = filter_input(INPUT_GET,  'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new InvalidArgumentException('Missing required item ID.');
    }

    $season = $seasonRepository->findById($id);

    if(!$season instanceof Season) {
        header('Location: seasons.php');
        exit;
    }
} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
    throw new RuntimeException('Unable to update season: ' . $e->getMessage());
}
?>
<!DOCTYPE html><!--
* CoreUI - Free Bootstrap Admin Template
* @version v5.3.0
* @link https://coreui.io/product/free-bootstrap-admin-template/
* Copyright (c) 2025 creativeLabs Łukasz Holeczek
* Licensed under MIT (https://github.com/coreui/coreui-free-bootstrap-admin-template/blob/main/LICENSE)
-->
<html lang="en">
<head>
    <base href="./">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Theatre CMS Dashboard</title>
    <link rel="apple-touch-icon" sizes="57x57" href="assets/favicon/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="assets/favicon/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="assets/favicon/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="assets/favicon/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="assets/favicon/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="assets/favicon/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="assets/favicon/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="assets/favicon/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="assets/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="assets/favicon/manifest.json">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="assets/favicon/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
    <!-- Vendors styles-->
    <link rel="stylesheet" href="vendors/simplebar/css/simplebar.css">
    <link rel="stylesheet" href="css/vendors/simplebar.css">
    <!-- Main styles for this application-->
    <link href="css/style.css" rel="stylesheet">
    <!-- We use those styles to show code examples, you should remove them in your application.-->
    <link href="css/examples.css" rel="stylesheet">
    <script src="js/config.js"></script>
    <script src="js/color-modes.js"></script>
</head>
<body>
<div class="sidebar sidebar-dark sidebar-fixed border-end" id="sidebar">
    <div class="sidebar-header border-bottom">
        <div class="sidebar-brand">
            <svg class="sidebar-brand-full" width="88" height="32" alt="CoreUI Logo">
                <use xlink:href="assets/brand/coreui.svg#full"></use>
            </svg>
            <svg class="sidebar-brand-narrow" width="32" height="32" alt="CoreUI Logo">
                <use xlink:href="assets/brand/coreui.svg#signet"></use>
            </svg>
        </div>
        <button class="btn-close d-lg-none" type="button" data-coreui-theme="dark" aria-label="Close" onclick="coreui.Sidebar.getInstance(document.querySelector(&quot;#sidebar&quot;)).toggle()"></button>
    </div>
    <ul class="sidebar-nav" data-coreui="navigation" data-simplebar="">
        <li class="nav-item"><a class="nav-link" href="index.html">
                <svg class="nav-icon">
                    <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-speedometer"></use>
                </svg> Dashboard<span class="badge badge-sm bg-info ms-auto">NEW</span></a></li>
        <li class="nav-title">Content</li>
        <li class="nav-group"><a class="nav-link nav-group-toggle" href="#">
                <svg class="nav-icon">
                    <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-calendar"></use>
                </svg> Seasons</a>
            <ul class="nav-group-items compact">
                <li class="nav-item"><a class="nav-link" href="add-season.php"><span class="nav-icon"><span class="nav-icon-bullet"></span></span> Add new Season</a></li>
                <li class="nav-item"><a class="nav-link" href="seasons.php"><span class="nav-icon"><span class="nav-icon-bullet"></span></span> View all Seasons</a></li>
            </ul>
        </li>
        <li class="nav-group">
            <a class="nav-link nav-group-toggle" href="#">
                <svg class="nav-icon">
                    <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-pencil"></use>
                </svg> Works</a>
            <ul class="nav-group-items compact">
                <li class="nav-item"><a class="nav-link" href="add-season.php"><span class="nav-icon"><span class="nav-icon-bullet"></span></span> Add new Season</a></li>
                <li class="nav-item"><a class="nav-link" href="seasons.php"><span class="nav-icon"><span class="nav-icon-bullet"></span></span> View all Seasons</a></li>
            </ul>
        </li>
    </ul>
    <div class="sidebar-footer border-top d-none d-md-flex">
        <button class="sidebar-toggler" type="button" data-coreui-toggle="unfoldable"></button>
    </div>
</div>
<div class="wrapper d-flex flex-column min-vh-100">
    <header class="header header-sticky p-0 mb-4">
        <div class="container-fluid border-bottom px-4">
            <button class="header-toggler" type="button" onclick="coreui.Sidebar.getInstance(document.querySelector('#sidebar')).toggle()" style="margin-inline-start: -14px;">
                <svg class="icon icon-lg">
                    <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-menu"></use>
                </svg>
            </button>
            <ul class="header-nav d-none d-lg-flex">
                <li class="nav-item"><a class="nav-link" href="#">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Users</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Settings</a></li>
            </ul>
            <ul class="header-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="#">
                        <svg class="icon icon-lg">
                            <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-bell"></use>
                        </svg></a></li>
                <li class="nav-item"><a class="nav-link" href="#">
                        <svg class="icon icon-lg">
                            <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-list-rich"></use>
                        </svg></a></li>
                <li class="nav-item"><a class="nav-link" href="#">
                        <svg class="icon icon-lg">
                            <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-envelope-open"></use>
                        </svg></a></li>
            </ul>
            <ul class="header-nav">
                <li class="nav-item py-1">
                    <div class="vr h-100 mx-2 text-body text-opacity-75"></div>
                </li>
                <li class="nav-item dropdown">
                    <button class="btn btn-link nav-link py-2 px-2 d-flex align-items-center" type="button" aria-expanded="false" data-coreui-toggle="dropdown">
                        <svg class="icon icon-lg theme-icon-active">
                            <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-contrast"></use>
                        </svg>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="--cui-dropdown-min-width: 8rem;">
                        <li>
                            <button class="dropdown-item d-flex align-items-center" type="button" data-coreui-theme-value="light">
                                <svg class="icon icon-lg me-3">
                                    <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-sun"></use>
                                </svg>Light
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item d-flex align-items-center" type="button" data-coreui-theme-value="dark">
                                <svg class="icon icon-lg me-3">
                                    <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-moon"></use>
                                </svg>Dark
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item d-flex align-items-center active" type="button" data-coreui-theme-value="auto">
                                <svg class="icon icon-lg me-3">
                                    <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-contrast"></use>
                                </svg>Auto
                            </button>
                        </li>
                    </ul>
                </li>
                <li class="nav-item py-1">
                    <div class="vr h-100 mx-2 text-body text-opacity-75"></div>
                </li>
                <li class="nav-item dropdown"><a class="nav-link py-0 pe-0" data-coreui-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                        <div class="avatar avatar-md"><img class="avatar-img" src="assets/img/avatars/8.jpg" alt="user@email.com"></div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end pt-0">
                        <div class="dropdown-header bg-body-tertiary text-body-secondary fw-semibold rounded-top mb-2">Account</div><a class="dropdown-item" href="#">
                            <svg class="icon me-2">
                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-bell"></use>
                            </svg> Updates<span class="badge badge-sm bg-info ms-2">42</span></a><a class="dropdown-item" href="#">
                            <svg class="icon me-2">
                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-envelope-open"></use>
                            </svg> Messages<span class="badge badge-sm bg-success ms-2">42</span></a><a class="dropdown-item" href="#">
                            <svg class="icon me-2">
                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-task"></use>
                            </svg> Tasks<span class="badge badge-sm bg-danger ms-2">42</span></a><a class="dropdown-item" href="#">
                            <svg class="icon me-2">
                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-comment-square"></use>
                            </svg> Comments<span class="badge badge-sm bg-warning ms-2">42</span></a>
                        <div class="dropdown-header bg-body-tertiary text-body-secondary fw-semibold my-2">
                            <div class="fw-semibold">Settings</div>
                        </div><a class="dropdown-item" href="#">
                            <svg class="icon me-2">
                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-user"></use>
                            </svg> Profile</a><a class="dropdown-item" href="#">
                            <svg class="icon me-2">
                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-settings"></use>
                            </svg> Settings</a><a class="dropdown-item" href="#">
                            <svg class="icon me-2">
                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-credit-card"></use>
                            </svg> Payments<span class="badge badge-sm bg-secondary ms-2">42</span></a><a class="dropdown-item" href="#">
                            <svg class="icon me-2">
                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-file"></use>
                            </svg> Projects<span class="badge badge-sm bg-primary ms-2">42</span></a>
                        <div class="dropdown-divider"></div><a class="dropdown-item" href="#">
                            <svg class="icon me-2">
                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-lock-locked"></use>
                            </svg> Lock Account</a><a class="dropdown-item" href="#">
                            <svg class="icon me-2">
                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-account-logout"></use>
                            </svg> Logout</a>
                    </div>
                </li>
            </ul>
        </div>
        <div class="container-fluid px-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb my-0">
                    <li class="breadcrumb-item active"><span>Home</span>
                    </li>
                </ol>
            </nav>
        </div>
    </header>
    <div class="body flex-grow-1">
        <div class="container-lg px-4">
            <h1 class="h3 mb-4">Edit Season</h1>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label" for="seasonName">Season Name</label>
                    <input
                            class="form-control"
                            name="seasonName"
                            id="seasonName"
                            type="text"
                            placeholder="e.g., 2025/2026"
                            value="<?php echo htmlspecialchars($season->getLabel()); ?>"
                    >
                </div>
                <div class="mb-3">
                    <label class="form-label" for="startDate">Start Date</label>
                    <input class="form-control" name="startDate" id="startDate" type="date" value="<?php echo $season->getStartDate()->format('Y-m-d'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="excerpt">Overview</label>
                    <textarea class="form-control" name="excerpt" id="excerpt" rows="3">
                        <?php echo htmlspecialchars($season->getOverview()); ?>
                    </textarea>
                </div>
                <button class="btn btn-primary" type="submit">Save Season</button>
            </form>
        </div>
    </div>
    <footer class="footer px-4">
        <div><a href="https://coreui.io">CoreUI </a><a href="https://coreui.io/product/free-bootstrap-admin-template/">Bootstrap Admin Template</a> © 2025 creativeLabs.</div>
        <div class="ms-auto">Powered by&nbsp;<a href="https://coreui.io/bootstrap/docs/">CoreUI UI Components</a></div>
    </footer>
</div>
<!-- CoreUI and necessary plugins-->
<script src="vendors/@coreui/coreui/js/coreui.bundle.min.js"></script>
<script src="vendors/simplebar/js/simplebar.min.js"></script>
<script>
    const header = document.querySelector('header.header');

    document.addEventListener('scroll', () => {
        if (header) {
            header.classList.toggle('shadow-sm', document.documentElement.scrollTop > 0);
        }
    });
</script>
<script>
</script>

</body>
</html>