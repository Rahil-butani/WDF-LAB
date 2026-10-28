
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once "mysqli-db.php";

if (!isset($_SESSION["student_id"])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("403 Forbidden: Administrator access required.");
}

if (empty($_SESSION["events_api_csrf"])) {
    $_SESSION["events_api_csrf"] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$csrfToken = $_SESSION["events_api_csrf"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title>StudentHub | AJAX Event Management</title>
    <link rel="stylesheet" href="events-ajax.css?v=1">
    <script src="events-ajax.js?v=1" defer></script>
</head>
<body>
    <header class="topbar">
        <div>
            <h1>StudentHub</h1>
            <p>Event Management System</p>
        </div>
        <nav>
            <a href="admin.php">Dashboard</a>
            <a href="manage-events.php">Practical 12</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <main class="container">
        <section class="page-heading">
            <div>
                <p class="eyebrow">PRACTICAL 13</p>
                <h2>AJAX Event Management</h2>
                <p>Create, update, search, and delete events without reloading the page.</p>
            </div>
            <div class="counter-card">
                <span>Total events</span>
                <strong id="eventCount">0</strong>
            </div>
        </section>

        <div id="message" class="message" role="status" aria-live="polite" hidden></div>

        <section class="panel">
            <div class="section-heading">
                <div>
                    <h2 id="formHeading">Add a new event</h2>
                    <p>Enter the event details below.</p>
                </div>
            </div>

            <form id="eventForm">
                <input type="hidden" id="eventId" name="event_id">

                <div class="form-grid">
                    <div class="field">
                        <label for="title">Event title *</label>
                        <input type="text" id="title" name="title" maxlength="150" required>
                    </div>

                    <div class="field">
                        <label for="event_date">Event date *</label>
                        <input type="date" id="event_date" name="event_date" required>
                    </div>

                    <div class="field">
                        <label for="venue">Venue</label>
                        <input type="text" id="venue" name="venue" maxlength="150">
                    </div>

                    <div class="field">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">Select category</option>
                            <option value="Technical">Technical</option>
                            <option value="Academic">Academic</option>
                            <option value="Competition">Competition</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Sports">Sports</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="field full-width">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" maxlength="5000"></textarea>
                    </div>

                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="Open">Open</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn primary" id="saveButton">Save event</button>
                    <button type="button" class="btn secondary" id="cancelEdit" hidden>Cancel edit</button>
                    <button type="reset" class="btn secondary" id="resetButton">Clear form</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="section-heading">
                <div>
                    <h2>Event records</h2>
                    <p>Search and filter records instantly using the API.</p>
                </div>
                <button type="button" class="btn secondary" id="refreshButton">Refresh</button>
            </div>

            <div class="filters">
                <div class="field">
                    <label for="search">Search</label>
                    <input type="search" id="search" placeholder="Search title, venue, or category">
                </div>

                <div class="field">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="">All statuses</option>
                        <option value="Open">Open</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>

                <div class="field">
                    <label for="categoryFilter">Category</label>
                    <select id="categoryFilter">
                        <option value="">All categories</option>
                        <option value="Technical">Technical</option>
                        <option value="Academic">Academic</option>
                        <option value="Competition">Competition</option>
                        <option value="Cultural">Cultural</option>
                        <option value="Sports">Sports</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Poster</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eventTableBody">
                        <tr>
                            <td colspan="8" class="empty-state">Loading events...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="table-note">Changes are sent to the PHP JSON API using JavaScript Fetch.</p>
        </section>
    </main>

    <footer class="footer">
        StudentHub | Practical 13 | AJAX and Fetch API
    </footer>
</body>
</html>
