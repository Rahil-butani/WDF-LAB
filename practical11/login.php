
<?php
session_start();
require_once "mysqli-db.php";
require_once "role.php";

if (isset($_SESSION["student_id"])) {
    redirectByRole($_SESSION["role"]);
}

if (empty($_SESSION["login_csrf"])) {
    $_SESSION["login_csrf"] = bin2hex(random_bytes(32));
}

$error = "";
$enrollment_no = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["login_csrf"], $token)) {
        $error = "Invalid request. Refresh the page and try again.";
    } else {
        $enrollment_no = trim($_POST["enrollment_no"] ?? "");
        $password = $_POST["password"] ?? "";
        $remember = isset($_POST["remember"]);

        $stmt = $conn->prepare(
            "SELECT student_id, enrollment_no, full_name, password_hash, role
             FROM students
             WHERE enrollment_no = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $enrollment_no);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();

        if ($student && !empty($student["password_hash"]) &&
            password_verify($password, $student["password_hash"])) {

            session_regenerate_id(true);

            $_SESSION["student_id"] = (int) $student["student_id"];
            $_SESSION["enrollment_no"] = $student["enrollment_no"];
            $_SESSION["full_name"] = $student["full_name"];
            $_SESSION["role"] = $student["role"];
            $_SESSION["last_activity"] = time();

            $_SESSION["login_csrf"] = bin2hex(random_bytes(32));

            if ($remember) {
                $rememberToken = bin2hex(random_bytes(32));
                $tokenHash = hash("sha256", $rememberToken);
                $expiry = date("Y-m-d H:i:s", time() + 2592000);

                $update = $conn->prepare(
                    "UPDATE students
                     SET remember_token_hash = ?, remember_expires = ?
                     WHERE student_id = ?"
                );

                $update->bind_param(
                    "ssi",
                    $tokenHash,
                    $expiry,
                    $student["student_id"]
                );
                $update->execute();
                $update->close();

                setcookie("studenthub_remember", $rememberToken, [
                    "expires" => time() + 2592000,
                    "path" => "/practical10/",
                    "httponly" => true,
                    "secure" => !empty($_SERVER["HTTPS"]) &&
                                $_SERVER["HTTPS"] !== "off",
                    "samesite" => "Lax"
                ]);
            }

            $updateLogin = $conn->prepare(
                "UPDATE students SET last_login = NOW() WHERE student_id = ?"
            );

            $updateLogin->bind_param("i", $student["student_id"]);
            $updateLogin->execute();
            $updateLogin->close();

            redirectByRole($student["role"]);
        } else {
            $error = "Invalid Student ID or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>StudentHub</h1>
        <nav>
            <a href="index.html">Home</a>
            <a href="register.php">Register</a>
            <a href="login.php">Login</a>
        </nav>
    </header>

    <main>
        <h2>Login</h2>
<?php if (isset($_GET["timeout"])): ?>
    <p class="form-message error-message">
        Your session expired due to inactivity. Please log in again.
    </p>
<?php endif; ?>

<?php if (isset($_GET["logged_out"])): ?>
    <p class="form-message success-message">
        You have been logged out successfully.
    </p>
<?php endif; ?>
        <?php if ($error !== ""): ?>
            <div class="form-message error-message" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION["login_csrf"], ENT_QUOTES, "UTF-8") ?>">

            <div class="form-group">
                <label for="enrollment_no">Student ID</label>
                <input type="text" id="enrollment_no" name="enrollment_no"
                       value="<?= htmlspecialchars($enrollment_no, ENT_QUOTES, "UTF-8") ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="remember" value="1">
                    Remember Me for 30 days
                </label>
            </div>

            <button type="submit">Login</button>
        </form>

        <p>New student? <a href="register.php">Register here</a>.</p>
    </main>
</body>
</html>
