<?php
session_start();

if (empty($_SESSION["student_id"])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied. Administrator access is required.");
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

logAudit($conn, "VIEW_AUDIT_LOG", "Viewed recent audit records");

$stmt = $conn->prepare(
    "SELECT log_id, user_id, user_name, user_role,
            action, details, ip_address, created_at
     FROM audit_logs
     ORDER BY log_id DESC
     LIMIT 200"
);

$stmt->execute();
$logs = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub Audit Log</title>
    <link rel="stylesheet" href="audit-log.css?v=1">
</head>
<body>

<header class="topbar">
    <h1>StudentHub Audit Log</h1>
    <nav>
        <a href="admin-dashboard.php">Dashboard</a>
        <a href="admin.php">Manage Students</a>
        <a href="manage-events-ajax.php">Manage Events</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">
    <section class="intro">
        <h2>System Activity</h2>
        <p>Showing the latest 200 audit records, newest first.</p>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                    <th>Date and Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs->num_rows > 0): ?>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int) $log["log_id"] ?></td>
                            <td>
                                <?= htmlspecialchars($log["user_name"] ?? "") ?>
                                <small>
                                    <?= htmlspecialchars($log["user_id"] ?? "") ?>
                                </small>
                            </td>
                            <td><?= htmlspecialchars($log["user_role"] ?? "") ?></td>
                            <td>
                                <span class="action-tag">
                                    <?= htmlspecialchars($log["action"]) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($log["details"] ?? "") ?></td>
                            <td><?= htmlspecialchars($log["ip_address"] ?? "") ?></td>
                            <td><?= htmlspecialchars($log["created_at"]) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No audit records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a class="back-link" href="admin-dashboard.php">Back to Dashboard</a>
</main>

</body>
</html>
<?php
$stmt->close();
$conn->close();
?>