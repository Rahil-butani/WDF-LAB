
<?php
session_start();
require_once "mysqli-db.php";

if (isset($_SESSION["student_id"])) {
    $id = (int) $_SESSION["student_id"];

    $stmt = $conn->prepare(
        "UPDATE students
         SET remember_token_hash = NULL, remember_expires = NULL
         WHERE student_id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

setcookie("studenthub_remember", "", [
    "expires" => time() - 3600,
    "path" => "/practical10/",
    "httponly" => true,
    "secure" => !empty($_SERVER["HTTPS"]) &&
                $_SERVER["HTTPS"] !== "off",
    "samesite" => "Lax"
]);

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(session_name(), "", [
        "expires" => time() - 3600,
        "path" => $params["path"],
        "domain" => $params["domain"],
        "secure" => $params["secure"],
        "httponly" => $params["httponly"],
        "samesite" => "Lax"
    ]);
}

session_destroy();

header("Location: login.php?logged_out=1");
exit;
?>
