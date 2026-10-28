
<?php
session_start();
require_once "mysqli-db.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$errors = [];
$success = "";
$fullname = "";
$enrollment_no = "";
$email = "";
$mobile = "";
$course = "";
$year = "";
$gender = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["csrf_token"], $token)) {
        $errors[] = "Invalid form request. Please refresh the page and try again.";
    }

    $fullname = trim($_POST["fullname"] ?? "");
    $enrollment_no = trim($_POST["enrollment_no"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $mobile = trim($_POST["mobile"] ?? "");
    $course = $_POST["course"] ?? "";
    $year = $_POST["year"] ?? "";
    $gender = $_POST["gender"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $terms = $_POST["terms"] ?? "";

    if ($fullname === "" || strlen($fullname) > 100) {
        $errors[] = "Enter a valid full name.";
    }

    if (!preg_match('/^[A-Za-z0-9-]{3,20}$/', $enrollment_no)) {
        $errors[] = "Student ID must be 3 to 20 letters, numbers or hyphens.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors[] = "Enter a valid email address.";
    }

    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors[] = "Mobile number must contain exactly 10 digits.";
    }

    if (!in_array($course, ["IT", "CE"], true)) {
        $errors[] = "Select a valid course.";
    }

    if (!in_array($year, ["1", "2", "3", "4"], true)) {
        $errors[] = "Select a valid year.";
    }

    if (!in_array($gender, ["Male", "Female", "Other"], true)) {
        $errors[] = "Select a valid gender.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if ($terms !== "accepted") {
        $errors[] = "You must accept the terms and conditions.";
    }

    if (empty($errors)) {
        $check = $conn->prepare(
            "SELECT student_id FROM students
             WHERE enrollment_no = ? OR email = ? LIMIT 1"
        );

        $check->bind_param("ss", $enrollment_no, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors[] = "Student ID or email is already registered.";
        }

        $check->close();
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $year_number = (int) $year;

        $stmt = $conn->prepare(
            "INSERT INTO students
            (enrollment_no, full_name, email, mobile, course,
             year_of_study, gender, password_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "sssssiss",
            $enrollment_no,
            $fullname,
            $email,
            $mobile,
            $course,
            $year_number,
            $gender,
            $password_hash
        );

        if ($stmt->execute()) {
            $success = "Registration successful! Your Student ID is " .
                htmlspecialchars($enrollment_no, ENT_QUOTES, "UTF-8") . ".";

            $fullname = "";
            $enrollment_no = "";
            $email = "";
            $mobile = "";
            $course = "";
            $year = "";
            $gender = "";

            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        } else {
            if ($stmt->errno === 1062) {
                $errors[] = "Student ID or email is already registered.";
            } else {
                error_log($stmt->error);
                $errors[] = "Registration failed. Please try again.";
            }
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub Registration</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>StudentHub</h1>
        <nav>
            <a href="index.html">Home</a>
            <a href="register.php">Registration</a>
            <a href="db-test.php">Database Test</a>
        </nav>
    </header>

    <main>
        <h2>Student Registration</h2>
        <p>Create your StudentHub account.</p>

        <?php if (!empty($errors)): ?>
            <div class="form-message error-message" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="form-message success-message" role="status">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <form id="registrationForm" action="register.php" method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">

            <div class="form-group">
                <label for="fullname">Full Name</label>
                <input type="text" id="fullname" name="fullname"
                       maxlength="100" required
                       value="<?= htmlspecialchars($fullname, ENT_QUOTES, "UTF-8") ?>">
            </div>

            <div class="form-group">
                <label for="enrollment_no">Student ID</label>
                <input type="text" id="enrollment_no" name="enrollment_no"
                       minlength="3" maxlength="20"
                       pattern="[A-Za-z0-9-]{3,20}" required
                       value="<?= htmlspecialchars($enrollment_no, ENT_QUOTES, "UTF-8") ?>">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       maxlength="100" required
                       value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>">
            </div>

            <div class="form-group">
                <label for="mobile">Mobile Number</label>
                <input type="tel" id="mobile" name="mobile"
                       pattern="[0-9]{10}" maxlength="10"
                       inputmode="numeric" required
                       value="<?= htmlspecialchars($mobile, ENT_QUOTES, "UTF-8") ?>">
            </div>

            <div class="form-group">
                <label for="course">Course</label>
                <select id="course" name="course" required>
                    <option value="">Select Course</option>
                    <option value="IT" <?= $course === "IT" ? "selected" : "" ?>>Information Technology</option>
                    <option value="CE" <?= $course === "CE" ? "selected" : "" ?>>Computer Engineering</option>
                </select>
            </div>

            <div class="form-group">
                <label for="year">Year of Study</label>
                <select id="year" name="year" required>
                    <option value="">Select Year</option>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <option value="<?= $i ?>" <?= $year === (string) $i ? "selected" : "" ?>>
                            Year <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?= $gender === "Male" ? "selected" : "" ?>>Male</option>
                    <option value="Female" <?= $gender === "Female" ? "selected" : "" ?>>Female</option>
                    <option value="Other" <?= $gender === "Other" ? "selected" : "" ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       minlength="8" required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       minlength="8" required autocomplete="new-password">
                <small id="passwordMessage"></small>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="terms" value="accepted" required>
                    I accept the terms and conditions.
                </label>
            </div>

            <button type="submit">Register</button>
        </form>
    </main>

    <script src="js/register.js"></script>
</body>
</html>
