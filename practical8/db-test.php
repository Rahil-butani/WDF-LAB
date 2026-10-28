
<?php
require_once "db.php";

$studentCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$eventCount = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$registrationCount = $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();

$course = "IT";

$stmt = $pdo->prepare(
    "SELECT student_id, full_name, email, course
     FROM students
     WHERE course = :course"
);

$stmt->execute(["course" => $course]);
$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub Database Test</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>StudentHub</h1>
        <nav>
            <a href="index.html">Home</a>
            <a href="register.html">Registration</a>
            <a href="db-test.php">Database Test</a>
        </nav>
    </header>

    <main>
        <h2>Database Connection Test</h2>

        <p>Database connected successfully.</p>

        <h3>Database Records</h3>
        <p>Total Students: <?= (int) $studentCount ?></p>
        <p>Total Events: <?= (int) $eventCount ?></p>
        <p>Total Registrations: <?= (int) $registrationCount ?></p>

        <h3>Students in Information Technology</h3>

        <?php if (count($students) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Course</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= (int) $student["student_id"] ?></td>
                            <td><?= htmlspecialchars($student["full_name"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($student["email"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($student["course"], ENT_QUOTES, "UTF-8") ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No students found for this course.</p>
        <?php endif; ?>
    </main>
</body>
</html>
