
<?php
require_once "session_check.php";

if ($_SESSION["role"] !== "admin") {
    http_response_code(403);
    exit("403 Forbidden: Only administrators can manage student records.");
}

if (empty($_SESSION["students_csrf"])) {
    $_SESSION["students_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["students_csrf"], $token)) {
        $error = "Invalid request. Refresh the page and try again.";
    } else {
        $action = $_POST["action"] ?? "";

        try {
            if ($action === "create" || $action === "update") {
                $enrollment = trim($_POST["enrollment_no"] ?? "");
                $name = trim($_POST["full_name"] ?? "");
                $email = trim($_POST["email"] ?? "");
                $mobile = trim($_POST["mobile"] ?? "");
                $course = $_POST["course"] ?? "";
                $year = filter_var(
                    $_POST["year_of_study"] ?? "",
                    FILTER_VALIDATE_INT
                );
                $gender = $_POST["gender"] ?? "";
                $id = filter_var(
                    $_POST["student_id"] ?? "",
                    FILTER_VALIDATE_INT
                );

                if (
                    $enrollment === "" ||
                    $name === "" ||
                    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                    !preg_match('/^[0-9]{10}$/', $mobile) ||
                    !in_array($course, ["IT", "CE"], true) ||
                    $year === false ||
                    $year < 1 ||
                    $year > 4 ||
                    !in_array($gender, ["Male", "Female", "Other"], true)
                ) {
                    throw new InvalidArgumentException(
                        "Enter valid details in all fields."
                    );
                }

                if ($action === "create") {
                    $stmt = $conn->prepare(
                        "INSERT INTO students
                        (enrollment_no, full_name, email, mobile, course,
                         year_of_study, gender)
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "sssssis",
                        $enrollment,
                        $name,
                        $email,
                        $mobile,
                        $course,
                        $year,
                        $gender
                    );

                    $stmt->execute();
                    $stmt->close();

                    header("Location: students.php?message=created");
                    exit;
                }

                if (!$id || $id < 1) {
                    throw new InvalidArgumentException(
                        "Invalid student record."
                    );
                }

                $stmt = $conn->prepare(
                    "UPDATE students
                     SET enrollment_no = ?, full_name = ?, email = ?,
                         mobile = ?, course = ?, year_of_study = ?, gender = ?
                     WHERE student_id = ?"
                );

                $stmt->bind_param(
                    "sssssisi",
                    $enrollment,
                    $name,
                    $email,
                    $mobile,
                    $course,
                    $year,
                    $gender,
                    $id
                );

                $stmt->execute();
                $stmt->close();

                header("Location: students.php?message=updated");
                exit;
            }

            if ($action === "delete") {
                $id = filter_var(
                    $_POST["student_id"] ?? "",
                    FILTER_VALIDATE_INT
                );

                if (!$id || $id < 1) {
                    throw new InvalidArgumentException(
                        "Invalid student record."
                    );
                }

                if ($id === (int) $_SESSION["student_id"]) {
                    throw new InvalidArgumentException(
                        "You cannot delete your own logged-in account."
                    );
                }

                $stmt = $conn->prepare(
                    "DELETE FROM students WHERE student_id = ?"
                );
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $deleted = $stmt->affected_rows;
                $stmt->close();

                if ($deleted > 0) {
                    header("Location: students.php?message=deleted");
                    exit;
                }

                throw new InvalidArgumentException(
                    "Student record was not found."
                );
            }

            throw new InvalidArgumentException("Invalid operation.");
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === 1451) {
                $error = "This student has related records and cannot be deleted.";
            } elseif ((int) $e->getCode() === 1062) {
                $error = "Student ID, email, or mobile number already exists.";
            } else {
                $error = "The database operation failed. Check the entered details.";
            }
        }
    }
}

$messages = [
    "created" => "Student added successfully.",
    "updated" => "Student details updated successfully.",
    "deleted" => "Student deleted successfully."
];

$notice = $messages[$_GET["message"] ?? ""] ?? "";

$search = trim($_GET["search"] ?? "");
$courseFilter = $_GET["course"] ?? "";

if (!in_array($courseFilter, ["", "IT", "CE"], true)) {
    $courseFilter = "";
}

$page = filter_var($_GET["page"] ?? 1, FILTER_VALIDATE_INT);
$page = $page && $page > 0 ? $page : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM students
     WHERE (? = '' OR full_name LIKE CONCAT('%', ?, '%')
        OR email LIKE CONCAT('%', ?, '%')
        OR course LIKE CONCAT('%', ?, '%'))
       AND (? = '' OR course = ?)"
);

$countStmt->bind_param(
    "ssssss",
    $search,
    $search,
    $search,
    $search,
    $courseFilter,
    $courseFilter
);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()["total"];
$countStmt->close();

$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listStmt = $conn->prepare(
    "SELECT student_id, enrollment_no, full_name, email, mobile,
            course, year_of_study, gender
     FROM students
     WHERE (? = '' OR full_name LIKE CONCAT('%', ?, '%')
        OR email LIKE CONCAT('%', ?, '%')
        OR course LIKE CONCAT('%', ?, '%'))
       AND (? = '' OR course = ?)
     ORDER BY student_id DESC
     LIMIT ? OFFSET ?"
);

$listStmt->bind_param(
    "ssssssii",
    $search,
    $search,
    $search,
    $search,
    $courseFilter,
    $courseFilter,
    $perPage,
    $offset
);
$listStmt->execute();
$students = $listStmt->get_result();

$editStudent = null;
$editId = filter_var($_GET["edit"] ?? "", FILTER_VALIDATE_INT);

if ($editId && $editId > 0) {
    $editStmt = $conn->prepare(
        "SELECT student_id, enrollment_no, full_name, email, mobile,
                course, year_of_study, gender
         FROM students
         WHERE student_id = ?"
    );
    $editStmt->bind_param("i", $editId);
    $editStmt->execute();
    $editStudent = $editStmt->get_result()->fetch_assoc();
    $editStmt->close();

    if (!$editStudent) {
        $error = "Student record was not found.";
    }
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function pageUrl($pageNumber)
{
    return "students.php?" . http_build_query([
        "search" => $_GET["search"] ?? "",
        "course" => $_GET["course"] ?? "",
        "page" => $pageNumber
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management | StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .crud-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }
        .crud-card {
            background: #ffffff;
            color: #192429;
            border: 1px solid #dddddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .crud-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .crud-form-grid input,
        .crud-form-grid select,
        .search-controls input,
        .search-controls select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            box-sizing: border-box;
        }
        .crud-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .crud-actions a,
        .crud-actions button,
        .crud-button {
            padding: 9px 12px;
            border: 0;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .crud-button {
            background: #192429;
            color: #ffffff;
        }
        .crud-secondary {
            background: #eeeeee;
            color: #192429;
        }
        .crud-delete {
            background: #ff3131;
            color: #ffffff;
        }
        .crud-table-wrap {
            overflow-x: auto;
        }
        .crud-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }
        .crud-table th,
        .crud-table td {
            padding: 11px;
            border-bottom: 1px solid #dddddd;
            text-align: left;
        }
        .crud-table th {
            background: #192429;
            color: #ffffff;
        }
        .search-controls {
            display: grid;
            grid-template-columns: 2fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }
        .crud-message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .crud-success {
            background: #e3f5e8;
            color: #14532d;
        }
        .crud-error {
            background: #ffe5e5;
            color: #8b1111;
        }
        .crud-pagination {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 18px;
        }
        @media (max-width: 650px) {
            .search-controls {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>StudentHub Admin</h1>
        <nav>
            <a href="index.html">Home</a>
            <a href="admin.php">Admin Dashboard</a>
            <a href="students.php">Student Management</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <main class="crud-container">
        <h2>Student Management</h2>
        <p>Manage student records using the options below.</p>

        <?php if ($notice !== ""): ?>
            <div class="crud-message crud-success" role="status">
                <?= h($notice) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="crud-message crud-error" role="alert">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <section class="crud-card">
            <h3><?= $editStudent ? "Edit Student" : "Add New Student" ?></h3>

            <form action="students.php" method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?= h($_SESSION["students_csrf"]) ?>">
                <input type="hidden" name="action"
                       value="<?= $editStudent ? "update" : "create" ?>">

                <?php if ($editStudent): ?>
                    <input type="hidden" name="student_id"
                           value="<?= h($editStudent["student_id"]) ?>">
                <?php endif; ?>

                <div class="crud-form-grid">
                    <div>
                        <label for="enrollment_no">Student ID</label>
                        <input id="enrollment_no" name="enrollment_no"
                               value="<?= h($editStudent["enrollment_no"] ?? "") ?>"
                               maxlength="20" required>
                    </div>

                    <div>
                        <label for="full_name">Full Name</label>
                        <input id="full_name" name="full_name"
                               value="<?= h($editStudent["full_name"] ?? "") ?>"
                               maxlength="100" required>
                    </div>

                    <div>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email"
                               value="<?= h($editStudent["email"] ?? "") ?>"
                               maxlength="150" required>
                    </div>

                    <div>
                        <label for="mobile">Mobile Number</label>
                        <input type="tel" id="mobile" name="mobile"
                               pattern="[0-9]{10}" maxlength="10"
                               value="<?= h($editStudent["mobile"] ?? "") ?>"
                               required>
                    </div>

                    <div>
                        <label for="course">Course</label>
                        <select id="course" name="course" required>
                            <option value="">Select Course</option>
                            <option value="IT" <?= ($editStudent["course"] ?? "") === "IT" ? "selected" : "" ?>>IT</option>
                            <option value="CE" <?= ($editStudent["course"] ?? "") === "CE" ? "selected" : "" ?>>CE</option>
                        </select>
                    </div>

                    <div>
                        <label for="year_of_study">Year of Study</label>
                        <select id="year_of_study" name="year_of_study" required>
                            <option value="">Select Year</option>
                            <?php for ($y = 1; $y <= 4; $y++): ?>
                                <option value="<?= $y ?>"
                                    <?= (int) ($editStudent["year_of_study"] ?? 0) === $y ? "selected" : "" ?>>
                                    Year <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div>
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <?php foreach (["Male", "Female", "Other"] as $g): ?>
                                <option value="<?= h($g) ?>"
                                    <?= ($editStudent["gender"] ?? "") === $g ? "selected" : "" ?>>
                                    <?= h($g) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="crud-actions" style="margin-top: 18px;">
                    <button class="crud-button" type="submit">
                        <?= $editStudent ? "Update Student" : "Add Student" ?>
                    </button>

                    <?php if ($editStudent): ?>
                        <a class="crud-actions crud-secondary"
                           href="students.php">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="crud-card">
            <h3>Search and Filter Students</h3>

            <form action="students.php" method="GET" class="search-controls">
                <div>
                    <label for="search">Search Name, Email, or Course</label>
                    <input type="search" id="search" name="search"
                           placeholder="Enter name, email, or course"
                           value="<?= h($search) ?>">
                </div>

                <div>
                    <label for="course_filter">Filter by Course</label>
                    <select id="course_filter" name="course">
                        <option value="">All Courses</option>
                        <option value="IT" <?= $courseFilter === "IT" ? "selected" : "" ?>>IT</option>
                        <option value="CE" <?= $courseFilter === "CE" ? "selected" : "" ?>>CE</option>
                    </select>
                </div>

                <button class="crud-button" type="submit">Search</button>
                <a class="crud-button crud-secondary" href="students.php">Reset</a>
            </form>
        </section>

        <section class="crud-card">
            <h3>Student Records</h3>
            <p>Total matching records: <?= $total ?></p>

            <div class="crud-table-wrap">
                <table class="crud-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Gender</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students->num_rows === 0): ?>
                            <tr>
                                <td colspan="8">No student records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($student = $students->fetch_assoc()): ?>
                                <tr>
                                    <td><?= h($student["enrollment_no"]) ?></td>
                                    <td><?= h($student["full_name"]) ?></td>
                                    <td><?= h($student["email"]) ?></td>
                                    <td><?= h($student["mobile"]) ?></td>
                                    <td><?= h($student["course"]) ?></td>
                                    <td><?= h($student["year_of_study"]) ?></td>
                                    <td><?= h($student["gender"]) ?></td>
                                    <td>
                                        <div class="crud-actions">
                                            <a class="crud-button crud-secondary"
                                               href="students.php?edit=<?= (int) $student["student_id"] ?>">
                                                Edit
                                            </a>

                                            <form action="students.php" method="POST"
                                                  onsubmit="return confirm('Are you sure you want to delete this student record?');">
                                                <input type="hidden" name="csrf_token"
                                                       value="<?= h($_SESSION["students_csrf"]) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="student_id"
                                                       value="<?= (int) $student["student_id"] ?>">
                                                <button class="crud-delete" type="submit">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="crud-pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= h(pageUrl($page - 1)) ?>">Previous</a>
                <?php endif; ?>

                <span>Page <?= $page ?> of <?= $totalPages ?></span>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= h(pageUrl($page + 1)) ?>">Next</a>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
