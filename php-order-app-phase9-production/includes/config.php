<?php
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'dbname');
define('DB_USER', 'dbuser');
define('DB_PASS', 'dbpass');
define('APP_NAME', 'Phase 9 Commerce SaaS');
?>
