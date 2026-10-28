
<?php
$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST["fullname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $mobile = trim($_POST["mobile"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $year = trim($_POST["year"] ?? "");
    $gender = trim($_POST["gender"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm"] ?? "";
    $terms = $_POST["terms"] ?? "";

    if (!preg_match("/^[A-Za-z][A-Za-z .'-]{1,59}$/", $fullname)) {
        $errors[] = "Please enter a valid full name.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (!preg_match("/^[0-9]{10}$/", $mobile)) {
        $errors[] = "Mobile number must contain exactly 10 digits.";
    }

    if (!in_array($course, ["IT", "CE"], true)) {
        $errors[] = "Please select a valid course.";
    }

    if (!in_array($year, ["1", "2", "3", "4"], true)) {
        $errors[] = "Please select a valid year.";
    }

    if (!in_array($gender, ["Male", "Female", "Other"], true)) {
        $errors[] = "Please select a valid gender.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    }

    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    if ($terms !== "yes") {
        $errors[] = "You must accept the terms and conditions.";
    }

    if (empty($errors)) {
        $dataDirectory = __DIR__ . "/data";
        $file = $dataDirectory . "/students.csv";

        if (!is_dir($dataDirectory) || !is_writable($dataDirectory)) {
            $errors[] = "The data directory is not writable.";
        } else {
            $handle = fopen($file, "c+");

            if ($handle === false) {
                $errors[] = "Unable to open the student data file.";
            } elseif (!flock($handle, LOCK_EX)) {
                fclose($handle);
                $errors[] = "Unable to lock the student data file.";
            } else {
                rewind($handle);
                $firstRow = fgetcsv($handle);

                if ($firstRow === false) {
                    fputcsv($handle, [
                        "Full Name",
                        "Email",
                        "Mobile",
                        "Course",
                        "Year",
                        "Gender",
                        "Password Hash",
                        "Registered At"
                    ]);
                }

                rewind($handle);
                fgetcsv($handle);

                $duplicate = false;

                while (($row = fgetcsv($handle)) !== false) {
                    if (
                        isset($row[1], $row[2]) &&
                        (
                            strtolower($row[1]) === strtolower($email) ||
                            $row[2] === $mobile
                        )
                    ) {
                        $duplicate = true;
                        break;
                    }
                }

                if ($duplicate) {
                    $errors[] = "This email address or mobile number is already registered.";
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    fseek($handle, 0, SEEK_END);

                    $saved = fputcsv($handle, [
                        $fullname,
                        $email,
                        $mobile,
                        $course,
                        $year,
                        $gender,
                        $passwordHash,
                        date("Y-m-d H:i:s")
                    ]);

                    if ($saved !== false && fflush($handle)) {
                        $success = "Registration completed successfully.";
                    } else {
                        $errors[] = "Unable to save your registration. Please try again.";
                    }
                }

                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }
} else {
    $errors[] = "Please submit the registration form first.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Result | StudentHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>StudentHub</h1>
        <nav>
            <a href="index.html">Home</a>
            <a href="dashboard.html">Dashboard</a>
            <a href="events.html">Events</a>
            <a href="about.html">FAQs</a>
            <a href="register.html">Registration</a>
        </nav>
    </header>

    <main class="container">
        <section class="json-section">
            <h2>Registration Result</h2>

            <?php if ($success !== ""): ?>
                <div class="form-message success-message">
                    <p><?= htmlspecialchars($success, ENT_QUOTES, "UTF-8") ?></p>
                    <p>Your registration details have been saved.</p>
                </div>
            <?php else: ?>
                <div class="form-message error-message">
                    <p>Registration could not be completed.</p>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <p><a href="register.html">Back to Registration</a></p>
            <p><a href="index.html">Return to Home</a></p>
        </section>
    </main>

    <footer>
        <p>StudentHub Student Portal</p>
    </footer>
</body>
</html>
