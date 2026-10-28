
<?php
require_once "session_check.php";

if ($_SESSION["role"] !== "admin") {
    http_response_code(403);
    exit("403 Forbidden: You are not authorized to access the admin dashboard.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>StudentHub Admin</h1>
        <nav>
            <a href="manage-events-ajax.php">Practical 13: AJAX CRUD</a>
            <a href="index.html">Home</a>
            <a href="admin.php">Admin Dashboard</a>
            <a href="logout.php">Logout</a>
            <a href="manage-events.php">Manage Events</a>
        </nav>
    </header>

    <main>
        <h2>Administrator Dashboard</h2>

        <p>Welcome,
            <?= htmlspecialchars($_SESSION["full_name"], ENT_QUOTES, "UTF-8") ?>!
        </p>

        <p>Role: Administrator</p>
        <p>You have successfully accessed the protected admin dashboard.</p>

        <a href="logout.php">Logout</a>
    </main>
</body>
</html>
