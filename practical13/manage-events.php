<?php
require_once "session_check.php";

if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Unauthorized access. Admins only.");
}

require_once "mysqli-db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

$uploadDir = __DIR__ . "/uploads/event_posters/";
$uploadPath = "uploads/event_posters/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (empty($_SESSION["events_csrf"])) {
    $_SESSION["events_csrf"] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function removePoster($path)
{
    if (!$path || strpos($path, "uploads/event_posters/") !== 0) {
        return;
    }

    $file = __DIR__ . "/" . $path;

    if (is_file($file)) {
        unlink($file);
    }
}

$message = $_SESSION["events_flash"] ?? "";
unset($_SESSION["events_flash"]);

$editing = null;

if (isset($_GET["edit"]) && ctype_digit($_GET["edit"])) {
    $editId = (int)$_GET["edit"];

    $stmt = $conn->prepare(
        "SELECT * FROM events WHERE event_id = ?"
    );
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();

    if (!$editing) {
        $message = "Event not found.";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newPoster = null;
    $oldPoster = null;

    try {
        $token = $_POST["csrf_token"] ?? "";

        if (!hash_equals($_SESSION["events_csrf"], $token)) {
            throw new RuntimeException(
                "Security token invalid. Refresh and try again."
            );
        }

        $action = $_POST["action"] ?? "";

        if ($action === "delete") {
            $id = filter_var(
                $_POST["event_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException("Invalid event ID.");
            }

            $stmt = $conn->prepare(
                "SELECT poster_path FROM events WHERE event_id = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $event = $stmt->get_result()->fetch_assoc();

            if (!$event) {
                throw new RuntimeException("Event not found.");
            }

            $stmt = $conn->prepare(
                "DELETE FROM events WHERE event_id = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();

            removePoster($event["poster_path"]);

            $_SESSION["events_flash"] =
                "Event deleted successfully.";

            header("Location: manage-events.php");
            exit;
        }

        if ($action !== "save") {
            throw new RuntimeException("Invalid request.");
        }

        $id = (int)($_POST["event_id"] ?? 0);
        $title = trim($_POST["title"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $date = trim($_POST["event_date"] ?? "");
        $venue = trim($_POST["venue"] ?? "");
        $category = trim($_POST["category"] ?? "");
        $status = $_POST["status"] ?? "Open";

        if ($title === "" || strlen($title) > 150) {
            throw new RuntimeException(
                "Event title is required and must be under 150 characters."
            );
        }

        $dateObject = DateTime::createFromFormat("!Y-m-d", $date);

        if (!$dateObject || $dateObject->format("Y-m-d") !== $date) {
            throw new RuntimeException("Enter a valid event date.");
        }

        if (strlen($description) > 5000) {
            throw new RuntimeException(
                "Description must be under 5000 characters."
            );
        }

        if (strlen($venue) > 150 || strlen($category) > 100) {
            throw new RuntimeException(
                "Venue or category is too long."
            );
        }

        if (!in_array($status, ["Open", "Closed"], true)) {
            throw new RuntimeException("Invalid event status.");
        }

        if ($id > 0) {
            $stmt = $conn->prepare(
                "SELECT poster_path FROM events WHERE event_id = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();

            if (!$existing) {
                throw new RuntimeException("Event not found.");
            }

            $oldPoster = $existing["poster_path"];
            $poster = $oldPoster;
        } else {
            $poster = null;
        }

        if (
            isset($_FILES["poster"]) &&
            $_FILES["poster"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES["poster"];

            if ($file["error"] !== UPLOAD_ERR_OK) {
                throw new RuntimeException(
                    "Poster upload failed. Please try again."
                );
            }

            if ($file["size"] > 2 * 1024 * 1024) {
                throw new RuntimeException(
                    "Poster must be 2 MB or smaller."
                );
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file["tmp_name"]);

            $allowed = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            if (!isset($allowed[$mime])) {
                throw new RuntimeException(
                    "Only JPG, PNG and WebP images are allowed."
                );
            }

            if (getimagesize($file["tmp_name"]) === false) {
                throw new RuntimeException(
                    "The uploaded file is not a valid image."
                );
            }

            $filename = bin2hex(random_bytes(16))
                . "." . $allowed[$mime];

            $destination = $uploadDir . $filename;

            if (!move_uploaded_file($file["tmp_name"], $destination)) {
                throw new RuntimeException(
                    "Could not save the uploaded poster."
                );
            }

            $newPoster = $uploadPath . $filename;
            $poster = $newPoster;
        }

        if ($id > 0) {
            $stmt = $conn->prepare(
                "UPDATE events
                 SET title = ?, description = ?, event_date = ?,
                     venue = ?, category = ?, status = ?, poster_path = ?
                 WHERE event_id = ?"
            );

            $stmt->bind_param(
                "sssssssi",
                $title,
                $description,
                $date,
                $venue,
                $category,
                $status,
                $poster,
                $id
            );

            $stmt->execute();
            $successMessage = "Event updated successfully.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO events
                 (title, description, event_date, venue,
                  category, status, poster_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "sssssss",
                $title,
                $description,
                $date,
                $venue,
                $category,
                $status,
                $poster
            );

            $stmt->execute();
            $successMessage = "Event added successfully.";
        }

        if ($newPoster && $oldPoster) {
            removePoster($oldPoster);
        }

        $_SESSION["events_flash"] = $successMessage;
        header("Location: manage-events.php");
        exit;

    } catch (Throwable $error) {
        if ($newPoster) {
            removePoster($newPoster);
        }

        $message = $error instanceof RuntimeException
            ? $error->getMessage()
            : "The operation failed. Check your database and try again.";
    }
}

$search = trim($_GET["search"] ?? "");
$statusFilter = $_GET["status"] ?? "";
$categoryFilter = trim($_GET["category"] ?? "");

if (!in_array($statusFilter, ["", "Open", "Closed"], true)) {
    $statusFilter = "";
}

$like = "%" . $search . "%";

$stmt = $conn->prepare(
    "SELECT * FROM events
     WHERE (title LIKE ? OR category LIKE ? OR venue LIKE ?)
       AND (? = '' OR status = ?)
       AND (? = '' OR category = ?)
     ORDER BY event_date DESC, event_id DESC"
);

$stmt->bind_param(
    "sssssss",
    $like,
    $like,
    $like,
    $statusFilter,
    $statusFilter,
    $categoryFilter,
    $categoryFilter
);

$stmt->execute();
$events = $stmt->get_result();

$categories = $conn->query(
    "SELECT DISTINCT category FROM events
     WHERE category IS NOT NULL AND category <> ''
     ORDER BY category"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub | Event Management</title>
    <link rel="stylesheet" href="events.css">
</head>
<body>

<header class="topbar">
    <div>
        <h1>StudentHub</h1>
        <p>Event Management Console</p>
    </div>

    <nav>
        <a href="admin.php">Admin Dashboard</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">

    <section class="intro">
        <div>
            <p class="eyebrow">ADMINISTRATION</p>
            <h2>Manage Events</h2>
            <p>Create events, update details and manage event posters.</p>
        </div>
        <div class="count-card">
            <span>Events displayed</span>
            <strong><?= $events->num_rows ?></strong>
        </div>
    </section>

    <?php if ($message !== ""): ?>
        <div class="notice" role="status">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <div class="section-heading">
            <div>
                <h2><?= $editing ? "Edit Event" : "Add New Event" ?></h2>
                <p>Fields marked with * are required.</p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="event-form">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION["events_csrf"]) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="event_id"
                   value="<?= e($editing["event_id"] ?? 0) ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="title">Event Title *</label>
                    <input id="title" name="title" maxlength="150"
                           required value="<?= e($editing["title"] ?? "") ?>">
                </div>

                <div class="field">
                    <label for="event_date">Event Date *</label>
                    <input type="date" id="event_date" name="event_date"
                           required value="<?= e($editing["event_date"] ?? "") ?>">
                </div>

                <div class="field">
                    <label for="venue">Venue</label>
                    <input id="venue" name="venue" maxlength="150"
                           value="<?= e($editing["venue"] ?? "") ?>">
                </div>

                <div class="field">
                    <label for="category">Category</label>
                    <input id="category" name="category" maxlength="100"
                           placeholder="Technical, Cultural, Workshop"
                           value="<?= e($editing["category"] ?? "") ?>">
                </div>

                <div class="field">
                    <label for="status">Event Status</label>
                    <select id="status" name="status">
                        <option value="Open"
                            <?= ($editing["status"] ?? "Open") === "Open" ? "selected" : "" ?>>
                            Open
                        </option>
                        <option value="Closed"
                            <?= ($editing["status"] ?? "") === "Closed" ? "selected" : "" ?>>
                            Closed
                        </option>
                    </select>
                </div>

                <div class="field">
                    <label for="poster">Event Poster</label>
                    <input type="file" id="poster" name="poster"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <small>JPG, PNG or WebP. Maximum size: 2 MB.</small>
                </div>
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description"
                          rows="4" maxlength="5000"><?= e($editing["description"] ?? "") ?></textarea>
            </div>

            <?php if (!empty($editing["poster_path"])): ?>
                <div class="current-poster">
                    <span>Current poster</span>
                    <img src="<?= e($editing["poster_path"]) ?>"
                         alt="Current event poster">
                    <small>Choose a new image above to replace it.</small>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn primary">
                    <?= $editing ? "Save Changes" : "Add Event" ?>
                </button>

                <?php if ($editing): ?>
                    <a href="manage-events.php" class="btn secondary">Cancel Edit</a>
                <?php else: ?>
                    <button type="reset" class="btn secondary">Reset</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="section-heading">
            <div>
                <h2>Event Records</h2>
                <p>Search, filter, edit or remove an event.</p>
            </div>
        </div>

        <form method="GET" class="filters">
            <div class="field">
                <label for="search">Search events</label>
                <input type="search" id="search" name="search"
                       placeholder="Title, category or venue"
                       value="<?= e($search) ?>">
            </div>

            <div class="field">
                <label for="filterStatus">Status</label>
                <select id="filterStatus" name="status">
                    <option value="">All statuses</option>
                    <option value="Open" <?= $statusFilter === "Open" ? "selected" : "" ?>>Open</option>
                    <option value="Closed" <?= $statusFilter === "Closed" ? "selected" : "" ?>>Closed</option>
                </select>
            </div>

            <div class="field">
                <label for="filterCategory">Category</label>
                <select id="filterCategory" name="category">
                    <option value="">All categories</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= e($cat["category"]) ?>"
                            <?= $categoryFilter === $cat["category"] ? "selected" : "" ?>>
                            <?= e($cat["category"]) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-buttons">
                <button class="btn primary" type="submit">Apply Filters</button>
                <a class="btn secondary" href="manage-events.php">Clear</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Poster</th>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($events->num_rows === 0): ?>
                    <tr>
                        <td colspan="7" class="empty">
                            No events found. Try clearing the filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while ($event = $events->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if (!empty($event["poster_path"])): ?>
                                    <img class="poster-thumb"
                                         src="<?= e($event["poster_path"]) ?>"
                                         alt="Event poster">
                                <?php else: ?>
                                    <span class="no-poster">No poster</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($event["title"]) ?></strong>
                                <small><?= e($event["description"]) ?></small>
                            </td>
                            <td><?= e($event["event_date"]) ?></td>
                            <td><?= e($event["venue"]) ?></td>
                            <td><?= e($event["category"]) ?></td>
                            <td>
                                <span class="status <?= strtolower(e($event["status"])) ?>">
                                    <?= e($event["status"]) ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn small secondary"
                                       href="manage-events.php?edit=<?= (int)$event["event_id"] ?>">
                                        Edit
                                    </a>

                                    <form method="POST"
                                          onsubmit="return confirm('Delete this event permanently?');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION["events_csrf"]) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="event_id"
                                               value="<?= (int)$event["event_id"] ?>">
                                        <button class="btn small danger" type="submit">
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
    </section>

</main>

<footer>
    <p>StudentHub Event Management</p>
</footer>

</body>
</html>