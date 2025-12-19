<?php

/**
 * The sidebar template for the admin panel
 */
?>
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
                <li class="nav-item"><a class="nav-link" href="add-work.php"><span class="nav-icon"><span class="nav-icon-bullet"></span></span> Add new Work</a></li>
                <li class="nav-item"><a class="nav-link" href="works.php"><span class="nav-icon"><span class="nav-icon-bullet"></span></span> View all Works</a></li>
            </ul>
        </li>
    </ul>
    <div class="sidebar-footer border-top d-none d-md-flex">
        <button class="sidebar-toggler" type="button" data-coreui-toggle="unfoldable"></button>
    </div>
</div>