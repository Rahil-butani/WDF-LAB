<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function logAudit($conn, $action, $details = "")
{
    $userId = (string) ($_SESSION["student_id"] ?? "");
    $userName = (string) ($_SESSION["full_name"] ?? "Unknown");
    $userRole = (string) ($_SESSION["role"] ?? "unknown");
    $ipAddress = (string) ($_SERVER["REMOTE_ADDR"] ?? "Unknown");

    $stmt = $conn->prepare(
        "INSERT INTO audit_logs
        (user_id, user_name, user_role, action, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "ssssss",
        $userId,
        $userName,
        $userRole,
        $action,
        $details,
        $ipAddress
    );

    $stmt->execute();
    $stmt->close();
}