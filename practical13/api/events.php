
<?php
header("Content-Type: application/json; charset=UTF-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function respond($status, $data)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION["student_id"])) {
    respond(401, ["success" => false, "message" => "Please log in first."]);
}

if (($_SESSION["role"] ?? "") !== "admin") {
    respond(403, ["success" => false, "message" => "Administrator access required."]);
}

require_once dirname(__DIR__) . "/mysqli-db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn->set_charset("utf8mb4");

$method = $_SERVER["REQUEST_METHOD"];

if ($method !== "GET") {
    $csrf = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
    $sessionToken = $_SESSION["events_api_csrf"] ?? "";

    if (
        !is_string($csrf) ||
        !is_string($sessionToken) ||
        $sessionToken === "" ||
        !hash_equals($sessionToken, $csrf)
    ) {
        respond(403, ["success" => false, "message" => "Invalid security token. Refresh the page and try again."]);
    }
}

function readInput()
{
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        respond(400, ["success" => false, "message" => "Invalid JSON request."]);
    }

    return $data;
}

function validateEvent($data)
{
    $title = trim($data["title"] ?? "");
    $description = trim($data["description"] ?? "");
    $date = trim($data["event_date"] ?? "");
    $venue = trim($data["venue"] ?? "");
    $category = trim($data["category"] ?? "");
    $status = trim($data["status"] ?? "Open");

    if ($title === "" || strlen($title) > 150) {
        respond(422, ["success" => false, "message" => "Title is required and must not exceed 150 characters."]);
    }

    $dateObject = DateTime::createFromFormat("!Y-m-d", $date);

    if (!$dateObject || $dateObject->format("Y-m-d") !== $date) {
        respond(422, ["success" => false, "message" => "Enter a valid event date."]);
    }

    if (strlen($description) > 5000) {
        respond(422, ["success" => false, "message" => "Description must not exceed 5000 characters."]);
    }

    if (strlen($venue) > 150 || strlen($category) > 100) {
        respond(422, ["success" => false, "message" => "Venue or category is too long."]);
    }

    if (!in_array($status, ["Open", "Closed"], true)) {
        respond(422, ["success" => false, "message" => "Choose a valid event status."]);
    }

    return [
        $title,
        $description,
        $date,
        $venue,
        $category,
        $status
    ];
}

function removeEventPoster($path)
{
    if (
        !$path ||
        strpos($path, "uploads/event_posters/") !== 0
    ) {
        return;
    }

    $file = dirname(__DIR__) . "/" . $path;

    if (is_file($file)) {
        unlink($file);
    }
}

try {
    if ($method === "GET") {
        $search = trim($_GET["search"] ?? "");
        $status = trim($_GET["status"] ?? "");
        $category = trim($_GET["category"] ?? "");

        if (!in_array($status, ["", "Open", "Closed"], true)) {
            respond(400, ["success" => false, "message" => "Invalid status filter."]);
        }

        $sql = "SELECT event_id, title, description, event_date,
                       venue, category, status, poster_path, created_at
                FROM events
                WHERE (? = ''
                       OR title LIKE CONCAT('%', ?, '%')
                       OR venue LIKE CONCAT('%', ?, '%')
                       OR category LIKE CONCAT('%', ?, '%'))
                  AND (? = '' OR status = ?)
                  AND (? = '' OR category = ?)
                ORDER BY event_date DESC, event_id DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssss",
            $search,
            $search,
            $search,
            $search,
            $status,
            $status,
            $category,
            $category
        );

        $stmt->execute();
        $result = $stmt->get_result();
        $events = [];

        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }

        $stmt->close();

        respond(200, [
            "success" => true,
            "message" => "Events retrieved successfully.",
            "count" => count($events),
            "events" => $events
        ]);
    }

    if ($method === "POST") {
        $data = readInput();
        [$title, $description, $date, $venue, $category, $status] = validateEvent($data);

        $stmt = $conn->prepare(
            "INSERT INTO events
             (title, description, event_date, venue, category, status)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssss",
            $title,
            $description,
            $date,
            $venue,
            $category,
            $status
        );

        $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();

        respond(201, [
            "success" => true,
            "message" => "Event created successfully.",
            "event_id" => $id
        ]);
    }

    if ($method === "PUT") {
        $id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);

        if (!$id || $id < 1) {
            respond(400, ["success" => false, "message" => "A valid event ID is required."]);
        }

        $data = readInput();
        [$title, $description, $date, $venue, $category, $status] = validateEvent($data);

        $stmt = $conn->prepare("SELECT event_id FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exists) {
            respond(404, ["success" => false, "message" => "Event not found."]);
        }

        $stmt = $conn->prepare(
            "UPDATE events
             SET title = ?, description = ?, event_date = ?,
                 venue = ?, category = ?, status = ?
             WHERE event_id = ?"
        );

        $stmt->bind_param(
            "ssssssi",
            $title,
            $description,
            $date,
            $venue,
            $category,
            $status,
            $id
        );

        $stmt->execute();
        $stmt->close();

        respond(200, [
            "success" => true,
            "message" => "Event updated successfully."
        ]);
    }

    if ($method === "DELETE") {
        $id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);

        if (!$id || $id < 1) {
            respond(400, ["success" => false, "message" => "A valid event ID is required."]);
        }

        $stmt = $conn->prepare("SELECT poster_path FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$event) {
            respond(404, ["success" => false, "message" => "Event not found."]);
        }

        $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        removeEventPoster($event["poster_path"] ?? null);

        respond(200, [
            "success" => true,
            "message" => "Event deleted successfully."
        ]);
    }

    header("Allow: GET, POST, PUT, DELETE");
    respond(405, ["success" => false, "message" => "Method not allowed."]);
} catch (Throwable $error) {
    error_log("Events API error: " . $error->getMessage());

    respond(500, [
        "success" => false,
        "message" => "A server error occurred. Check the PHP error log if the problem continues."
    ]);
}
?>
