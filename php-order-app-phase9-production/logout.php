<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
session_unset();
session_destroy();
header('Location: /login.php?store=demo');
exit;
