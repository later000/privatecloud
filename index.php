<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (!is_installed()) {
    redirect('install.php');
}

if (is_logged_in()) {
    redirect('dashboard.php');
}
redirect('login.php');
