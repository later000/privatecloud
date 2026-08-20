<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'pan_vxva');
define('DB_USER', 'pan_user');
define('DB_PASS', '此处填写数据库密码');

define('DATA_DIR', dirname(__DIR__) . '/storage');
define('LOCK_FILE', dirname(__DIR__) . '/config/.installed.lock');

define('SITE_URL', 'https://pan.vxva.cn');
define('SITE_NAME', '后来的网盘');
define('AUTHOR_AVATAR', 'https://q1.qlogo.cn/g?b=qq&nk=3785320778&s=640');
define('SHARE_FEEDBACK_DEFAULT', '无法下载或遇到问题，请联系作者：QQ 3785320778 / later0@88.com');
define('MAX_UPLOAD_SIZE', 5368709120);
define('TOKEN_TTL', 600);
define('PAGE_SIZE', 20);

define('RATE_LIMIT_ENABLED', true);
define('RATE_WINDOW', 60);
define('RATE_LIMIT_GET', 300);
define('RATE_LIMIT_POST', 60);
define('LOGIN_FAIL_MAX', 5);
define('LOGIN_LOCK_MINUTES', 15);
