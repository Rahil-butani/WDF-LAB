
<?php
function redirectByRole($role)
{
    if ($role === "admin") {
        header("Location: admin.php");
        exit;
    }

    header("Location: dashboard.php");
    exit;
}
?>
