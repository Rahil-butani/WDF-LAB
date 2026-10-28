
<?php

header("Content-Type: application/json; charset=UTF-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function respond($statusCode, $data)
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function readInput()
{
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    if (is_array($data)) {
        return $data;
    }

    return $_POST;
}

function validateEvent($data)
{
    $title = trim((string) ($data["title"] ?? ""));
    $description = trim((string) ($data["description"] ?? ""));
    $eventDate = trim((string) ($data["event_date"] ?? ""));
    $venue = trim((string) ($data["venue"] ?? ""));
    $category = trim((string) ($data["category"] ?? ""));
    $status = trim((string) ($data["status"] ?? "Open"));

    if ($title === "") {
        respond(422, [
            "success" => false,
            "message" => "Event title is required."
        ]);
    }

    if (strlen($title) > 150) {
        respond(422, [
            "success" => false,
            "message" => "Event title must not exceed 150 characters."
        ]);
    }

    if (strlen($description) > 5000) {
        respond(422, [
            "success" => false,
            "message" => "Description must not exceed 5000 characters."
        ]);
    }

    if ($eventDate === "") {
        respond(422, [
            "success" => false,
            "message" => "Event date is required."
        ]);
    }

    $date = DateTime::createFromFormat("!Y-m-d", $eventDate);

    if (!$date || $date->format("Y-m-d") !== $eventDate) {
        respond(422, [
            "success" => false,
            "message" => "Enter a valid event date."
        ]);
    }

    if ($venue === "") {
        respond(422, [
            "success" => false,
            "message" => "Event venue is required."
        ]);
    }

    if (strlen($venue) > 200) {
        respond(422, [
            "success" => false,
            "message" => "Venue must not exceed 200 characters."
        ]);
    }

    $allowedCategories = [
        "Technical",
        "Academic",
        "Competition",
        "Cultural",
        "Sports",
        "Other"
    ];

    if (!in_array($category, $allowedCategories, true)) {
        respond(422, [
            "success" => false,
            "message" => "Select a valid event category."
        ]);
    }

    if (!in_array($status, ["Open", "Closed"], true)) {
        respond(422, [
            "success" => false,
            "message" => "Select a valid event status."
        ]);
    }

    return [
        "title" => $title,
        "description" => $description,
        "event_date" => $eventDate,
        "venue" => $venue,
        "category" => $category,
        "status" => $status
    ];
}

function removeEventPoster($posterPath)
{
    if (!$posterPath) {
        return;
    }

    $filename = basename(str_replace("\\", "/", $posterPath));

    if ($filename === "" || $filename === "." || $filename === "..") {
        return;
    }

    $uploadDirectory = dirname(__DIR__) . "/uploads/event_posters";
    $filePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;

    if (is_file($filePath)) {
        unlink($filePath);
    }
}

if (empty($_SESSION["student_id"])) {
    respond(401, [
        "success" => false,
        "message" => "Please log in to continue."
    ]);
}

if (($_SESSION["role"] ?? "") !== "admin") {
    respond(403, [
        "success" => false,
        "message" => "Administrator access is required."
    ]);
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

if (!in_array($method, ["GET", "POST", "PUT", "DELETE"], true)) {
    header("Allow: GET, POST, PUT, DELETE");

    respond(405, [
        "success" => false,
        "message" => "Request method not allowed."
    ]);
}

if ($method !== "GET") {
    $csrfToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
    $sessionToken = $_SESSION["events_api_csrf"] ?? "";

    if (
        $sessionToken === "" ||
        $csrfToken === "" ||
        !hash_equals($sessionToken, $csrfToken)
    ) {
        respond(403, [
            "success" => false,
            "message" => "Invalid or missing security token. Refresh the page and try again."
        ]);
    }
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    require_once dirname(__DIR__) . "/mysqli-db.php";
    require_once dirname(__DIR__) . "/audit_logger.php";

    $conn->set_charset("utf8mb4");

    if ($method === "GET") {
        $search = trim((string) ($_GET["search"] ?? ""));
        $status = trim((string) ($_GET["status"] ?? ""));
        $category = trim((string) ($_GET["category"] ?? ""));

        $sql = "
            SELECT
                event_id,
                title,
                description,
                event_date,
                venue,
                category,
                status,
                poster_path,
                created_at
            FROM events
            WHERE (
                ? = ''
                OR title LIKE CONCAT('%', ?, '%')
                OR description LIKE CONCAT('%', ?, '%')
                OR venue LIKE CONCAT('%', ?, '%')
            )
            AND (? = '' OR status = ?)
            AND (? = '' OR category = ?)
            ORDER BY event_date DESC, event_id DESC
        ";

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
            $row["event_id"] = (int) $row["event_id"];
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
        $data = validateEvent(readInput());

        $stmt = $conn->prepare(
            "INSERT INTO events
            (title, description, event_date, venue, category, status)
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssss",
            $data["title"],
            $data["description"],
            $data["event_date"],
            $data["venue"],
            $data["category"],
            $data["status"]
        );

        $stmt->execute();

        $eventId = (int) $conn->insert_id;

        $stmt->close();

        logAudit(
            $conn,
            "CREATE_EVENT",
            "Created event ID: " . $eventId . ", title: " . $data["title"]
        );

        respond(201, [
            "success" => true,
            "message" => "Event created successfully.",
            "event_id" => $eventId
        ]);
    }

    if ($method === "PUT") {
        $eventId = filter_var(
            $_GET["id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$eventId || $eventId < 1) {
            respond(400, [
                "success" => false,
                "message" => "A valid event ID is required."
            ]);
        }

        $data = validateEvent(readInput());

        $stmt = $conn->prepare(
            "UPDATE events
             SET title = ?,
                 description = ?,
                 event_date = ?,
                 venue = ?,
                 category = ?,
                 status = ?
             WHERE event_id = ?"
        );

        $stmt->bind_param(
            "ssssssi",
            $data["title"],
            $data["description"],
            $data["event_date"],
            $data["venue"],
            $data["category"],
            $data["status"],
            $eventId
        );

        $stmt->execute();

        $affectedRows = $stmt->affected_rows;

        $stmt->close();

        if ($affectedRows === 0) {
            $check = $conn->prepare(
                "SELECT event_id FROM events WHERE event_id = ?"
            );

            $check->bind_param("i", $eventId);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$exists) {
                respond(404, [
                    "success" => false,
                    "message" => "Event not found."
                ]);
            }
        }

        logAudit(
            $conn,
            "UPDATE_EVENT",
            "Updated event ID: " . $eventId
        );

        respond(200, [
            "success" => true,
            "message" => "Event updated successfully.",
            "event_id" => (int) $eventId
        ]);
    }

    if ($method === "DELETE") {
        $eventId = filter_var(
            $_GET["id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$eventId || $eventId < 1) {
            respond(400, [
                "success" => false,
                "message" => "A valid event ID is required."
            ]);
        }

        $find = $conn->prepare(
            "SELECT title, poster_path
             FROM events
             WHERE event_id = ?"
        );

        $find->bind_param("i", $eventId);
        $find->execute();

        $event = $find->get_result()->fetch_assoc();

        $find->close();

        if (!$event) {
            respond(404, [
                "success" => false,
                "message" => "Event not found."
            ]);
        }

        $stmt = $conn->prepare(
            "DELETE FROM events WHERE event_id = ?"
        );

        $stmt->bind_param("i", $eventId);
        $stmt->execute();

        $deleted = $stmt->affected_rows > 0;

        $stmt->close();

        if (!$deleted) {
            respond(500, [
                "success" => false,
                "message" => "The event could not be deleted."
            ]);
        }

        logAudit(
            $conn,
            "DELETE_EVENT",
            "Deleted event ID: " . $eventId . ", title: " . $event["title"]
        );

        removeEventPoster($event["poster_path"] ?? "");

        respond(200, [
            "success" => true,
            "message" => "Event deleted successfully.",
            "event_id" => (int) $eventId
        ]);
    }

} catch (Throwable $e) {
    error_log("Events API error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "A server error occurred while processing the request."
    ]);
}
