
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/mysqli-db.php";

function clearRememberCookie()
{
    setcookie("studenthub_remember", "", [
        "expires" => time() - 3600,
        "path" => "/practical10/",
        "httponly" => true,
        "secure" => !empty($_SERVER["HTTPS"]) &&
                    $_SERVER["HTTPS"] !== "off",
        "samesite" => "Lax"
    ]);
}

function destroyStudentSession()
{
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
}

if (isset($_SESSION["student_id"])) {
    if (isset($_SESSION["last_activity"]) &&
        time() - $_SESSION["last_activity"] > 900) {

        $id = (int) $_SESSION["student_id"];

        $stmt = $conn->prepare(
            "UPDATE students
             SET remember_token_hash = NULL, remember_expires = NULL
             WHERE student_id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        clearRememberCookie();
        destroyStudentSession();

        header("Location: login.php?timeout=1");
        exit;
    }

    $_SESSION["last_activity"] = time();
} else {
    $rememberToken = $_COOKIE["studenthub_remember"] ?? "";

    if (is_string($rememberToken) &&
        preg_match('/^[a-f0-9]{64}$/', $rememberToken)) {

        $tokenHash = hash("sha256", $rememberToken);

        $stmt = $conn->prepare(
            "SELECT student_id, enrollment_no, full_name, role
             FROM students
             WHERE remember_token_hash = ?
             AND remember_expires > NOW()
             LIMIT 1"
        );

        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();

        if ($student) {
            session_regenerate_id(true);

            $_SESSION["student_id"] = (int) $student["student_id"];
            $_SESSION["enrollment_no"] = $student["enrollment_no"];
            $_SESSION["full_name"] = $student["full_name"];
            $_SESSION["role"] = $student["role"];
            $_SESSION["last_activity"] = time();
        } else {
            clearRememberCookie();
        }
    }
}

if (!isset($_SESSION["student_id"])) {
    header("Location: login.php");
    exit;
}
?>
