<?php
session_start();

if (empty($_SESSION["student_id"])) {
    header("Location: login.php");
    exit;
}

if (
    isset($_SESSION["last_activity"]) &&
    time() - (int) $_SESSION["last_activity"] > 900
) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}

$_SESSION["last_activity"] = time();

require_once "mysqli-db.php";
require_once "audit_logger.php";

$role = $_SESSION["role"] ?? "student";
$name = $_SESSION["full_name"] ?? "Student";

if (!isset($_SESSION["dashboard_logged"])) {
    logAudit($conn, "DASHBOARD_ACCESS", "Opened the dashboard");
    $_SESSION["dashboard_logged"] = true;
}

$studentCount = 0;
$eventCount = 0;
$auditCount = 0;

if ($role === "admin") {
    $result = $conn->query("SELECT COUNT(*) AS total FROM students");
    $studentCount = (int) $result->fetch_assoc()["total"];

    $result = $conn->query("SELECT COUNT(*) AS total FROM events");
    $eventCount = (int) $result->fetch_assoc()["total"];

    $result = $conn->query("SELECT COUNT(*) AS total FROM audit_logs");
    $auditCount = (int) $result->fetch_assoc()["total"];
}

$menu = [
    [
        "label" => "Dashboard",
        "url" => "admin-dashboard.php",
        "roles" => ["admin", "student"]
    ],
    [
        "label" => "Manage Students",
        "url" => "admin.php",
        "roles" => ["admin"]
    ],
    [
        "label" => "Manage Events",
        "url" => "manage-events-ajax.php",
        "roles" => ["admin"]
    ],
    [
        "label" => "Audit Log",
        "url" => "audit-log.php",
        "roles" => ["admin"]
    ],
    [
        "label" => "Logout",
        "url" => "logout.php",
        "roles" => ["admin", "student"]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub Dashboard</title>
    <link rel="stylesheet" href="dashboard.css?v=1">
</head>
<body>

<header class="topbar">
    <div class="brand">StudentHub</div>

    <nav class="navigation">
        <?php foreach ($menu as $item): ?>
            <?php if (in_array($role, $item["roles"], true)): ?>
                <a href="<?= htmlspecialchars($item["url"]) ?>">
                    <?= htmlspecialchars($item["label"]) ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</header>

<main class="container">
    <section class="welcome">
        <p class="eyebrow">STUDENTHUB CONTROL PANEL</p>
        <h1>Welcome, <?= htmlspecialchars($name) ?>!</h1>
        <p>
            You are logged in as
            <span class="role"><?= htmlspecialchars(ucfirst($role)) ?></span>.
        </p>
    </section>

    <?php if ($role === "admin"): ?>
        <section class="stats">
            <article class="stat-card">
                <p>Total Students</p>
                <h2><?= $studentCount ?></h2>
            </article>

            <article class="stat-card">
                <p>Total Events</p>
                <h2><?= $eventCount ?></h2>
            </article>

            <article class="stat-card">
                <p>Audit Records</p>
                <h2><?= $auditCount ?></h2>
            </article>
        </section>

        <section class="panel">
            <h2>Administrator Actions</h2>
            <p>Manage student records, events and system activity.</p>

            <div class="actions">
                <a class="button" href="admin.php">Manage Students</a>
                <a class="button" href="manage-events-ajax.php">Manage Events</a>
                <a class="button" href="audit-log.php">View Audit Log</a>
            </div>
        </section>
    <?php else: ?>
        <section class="panel">
            <h2>Student Dashboard</h2>
            <p>
                Welcome to StudentHub. Your dashboard access is limited
                according to your student role.
            </p>
        </section>
    <?php endif; ?>

    <footer>
        StudentHub | Role-Based Dashboard
    </footer>
</main>

</body>
</html>