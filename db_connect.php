<?php

define('DB_HOST',   'localhost');
define('DB_USER',   'root');
define('DB_PASS',   '');
define('DB_NAME',   'skillbridge');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {

    die(
        "<div style='font-family:sans-serif; padding:20px; color:red;'>" .
        "<strong>Database Connection Failed.</strong><br>" .
        "Please check your MySQL credentials in db_connect.php.<br>" .
        "<small>Error: " . htmlspecialchars(mysqli_connect_error()) . "</small>" .
        "</div>"
    );
}

mysqli_set_charset($conn, 'utf8mb4');

?>
