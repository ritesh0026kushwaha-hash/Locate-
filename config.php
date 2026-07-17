<?php
/**
 * config.php
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'YOUR_PASSWORD');
define('DB_NAME', 'web_tracker');

// डेटाबेस कनेक्शन
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    die(json_encode(['status' => 'error', 'message' => 'DB कनेक्शन फेल']));
}
$conn->set_charset("utf8mb4");
?>
