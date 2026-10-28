
<?php
require_once "session_check.php";
require_once "role.php";

if ($_SESSION["role"] === "admin") {
    redirectByRole("admin");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style.css?v=2">
</head>
<body>
    <header>
        <h1>StudentHub</h1>
        <nav>
            <a href="index.html">Home</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <main>
        <h2>Student Dashboard</h2>

        <p>Welcome,
            <?= htmlspecialchars($_SESSION["full_name"], ENT_QUOTES, "UTF-8") ?>!
        </p>

        <p>Student ID:
            <?= htmlspecialchars($_SESSION["enrollment_no"], ENT_QUOTES, "UTF-8") ?>
        </p>

        <p>Role: Student</p>
        <p>You are logged in successfully.</p>

        <a href="logout.php">Logout</a>
    </main>
</body>
</html>
