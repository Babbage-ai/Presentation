<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function get_db(): mysqli
{
    static $db = null;

    if ($db instanceof mysqli) {
        return $db;
    }

    $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
    $port = (int) (defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: 3306));
    $database = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'cloud_signage_present');
    $username = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
    $password = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');

    $db = new mysqli($host, $username, $password, $database, $port);
    $db->set_charset('utf8mb4');

    return $db;
}
