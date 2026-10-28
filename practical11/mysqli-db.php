
<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "studenthub_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log($conn->connect_error);
    exit("Database connection failed. Please try again later.");
}

$conn->set_charset("utf8mb4");
?>
