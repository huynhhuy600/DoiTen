<?php
require_once __DIR__ . '/config/settings.php';
echo "BASE_URL is: " . BASE_URL . "\n";
echo "SCRIPT_NAME is: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "HTTPS is: " . (isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'not set') . "\n";
echo "X-Forwarded-Proto is: " . (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : 'not set') . "\n";
echo "HTTP_HOST is: " . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'not set') . "\n";
