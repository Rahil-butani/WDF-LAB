
const apiUrl = "api/events.php";

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const eventForm = document.getElementById("eventForm");
const eventIdInput = document.getElementById("eventId");
const eventTableBody = document.getElementById("eventTableBody");
const eventCount = document.getElementById("eventCount");
const messageBox = document.getElementById("message");
const formHeading = document.getElementById("formHeading");
const saveButton = document.getElementById("saveButton");
const cancelEditButton = document.getElementById("cancelEdit");
const searchInput = document.getElementById("search");
const statusFilter = document.getElementById("statusFilter");
const categoryFilter = document.getElementById("categoryFilter");

let allEvents = [];
let searchTimer;

function escapeHTML(value) {
    return String(value ?? "").replace(/[&<>"']/g, character => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[character]);
}

function showMessage(text, type = "success") {
    messageBox.textContent = text;
    messageBox.className = `message ${type}`;
    messageBox.hidden = false;
}

function clearMessage() {
    messageBox.hidden = true;
    messageBox.textContent = "";
}

async function apiRequest(url, options = {}) {
    const headers = {
        Accept: "application/json",
        ...(options.headers || {})
    };

    if (options.method && options.method !== "GET") {
        headers["Content-Type"] = "application/json";
        headers["X-CSRF-Token"] = csrfToken;
    }

    const response = await fetch(url, {
        ...options,
        headers,
        credentials: "same-origin"
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
        throw new Error(data.message || "The request could not be completed.");
    }

    return data;
}

async function loadEvents() {
    eventTableBody.innerHTML = `
        <tr>
            <td colspan="8" class="empty-state">Loading events...</td>
        </tr>
    `;

    const params = new URLSearchParams({
        search: searchInput.value.trim(),
        status: statusFilter.value,
        category: categoryFilter.value
    });

    try {
        const data = await apiRequest(`${apiUrl}?${params.toString()}`);
        allEvents = data.events || [];
        eventCount.textContent = data.count ?? allEvents.length;
        renderEvents(allEvents);
    } catch (error) {
        eventCount.textContent = "0";
        eventTableBody.innerHTML = `
            <tr>
                <td colspan="8" class="empty-state">
                    ${escapeHTML(error.message)}
                </td>
            </tr>
        `;
        showMessage(error.message, "error");
    }
}

function renderEvents(events) {
    if (!events.length) {
        eventTableBody.innerHTML = `
            <tr>
                <td colspan="8" class="empty-state">
                    No matching events found.
                </td>
            </tr>
        `;
        return;
    }

    eventTableBody.innerHTML = events.map(event => {
        const status = String(event.status || "Open");
        const statusClass = status.toLowerCase() === "closed"
            ? "status-closed"
            : "status-open";

        const poster = event.poster_path
            ? `<img class="poster-thumb"
                    src="${escapeHTML(event.poster_path)}"
                    alt="Poster for ${escapeHTML(event.title)}"
                    loading="lazy">`
            : `<span class="no-poster">No poster</span>`;

        return `
            <tr>
                <td>${escapeHTML(event.event_id)}</td>
                <td>
                    <div class="event-title">${escapeHTML(event.title)}</div>
                    <div class="event-description">${escapeHTML(event.description || "")}</div>
                </td>
                <td>${escapeHTML(event.event_date)}</td>
                <td>${escapeHTML(event.venue || "Not specified")}</td>
                <td>${escapeHTML(event.category || "Other")}</td>
                <td>
                    <span class="status-tag ${statusClass}">
                        ${escapeHTML(status)}
                    </span>
                </td>
                <td>${poster}</td>
                <td>
                    <div class="row-actions">
                        <button type="button"
                                class="btn secondary small"
                                data-action="edit"
                                data-id="${escapeHTML(event.event_id)}">
                            Edit
                        </button>
                        <button type="button"
                                class="btn danger small"
                                data-action="delete"
                                data-id="${escapeHTML(event.event_id)}">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join("");
}

function resetForm() {
    eventForm.reset();
    eventIdInput.value = "";
    formHeading.textContent = "Add a new event";
    saveButton.textContent = "Save event";
    cancelEditButton.hidden = true;
}

function startEdit(event) {
    eventIdInput.value = event.event_id;
    document.getElementById("title").value = event.title || "";
    document.getElementById("event_date").value = event.event_date || "";
    document.getElementById("venue").value = event.venue || "";
    document.getElementById("category").value = event.category || "";
    document.getElementById("description").value = event.description || "";
    document.getElementById("status").value = event.status || "Open";

    formHeading.textContent = `Edit event #${event.event_id}`;
    saveButton.textContent = "Update event";
    cancelEditButton.hidden = false;

    clearMessage();
    eventForm.scrollIntoView({ behavior: "smooth", block: "start" });
}

eventForm.addEventListener("submit", async event => {
    event.preventDefault();
    clearMessage();

    if (!eventForm.reportValidity()) {
        return;
    }

    const id = eventIdInput.value;
    const payload = {
        title: document.getElementById("title").value.trim(),
        event_date: document.getElementById("event_date").value,
        venue: document.getElementById("venue").value.trim(),
        category: categoryFilter.options.length
            ? document.getElementById("category").value
            : "",
        description: document.getElementById("description").value.trim(),
        status: document.getElementById("status").value
    };

    saveButton.disabled = true;
    saveButton.textContent = id ? "Updating..." : "Saving...";

    try {
        const data = await apiRequest(
            id ? `${apiUrl}?id=${encodeURIComponent(id)}` : apiUrl,
            {
                method: id ? "PUT" : "POST",
                body: JSON.stringify(payload)
            }
        );

        resetForm();
        await loadEvents();
        showMessage(data.message, "success");
    } catch (error) {
        showMessage(error.message, "error");
    } finally {
        saveButton.disabled = false;

        if (!eventIdInput.value) {
            saveButton.textContent = "Save event";
        } else {
            saveButton.textContent = "Update event";
        }
    }
});

eventTableBody.addEventListener("click", async event => {
    const button = event.target.closest("button[data-action]");

    if (!button) {
        return;
    }

    const id = button.dataset.id;
    const action = button.dataset.action;
    const selectedEvent = allEvents.find(item => String(item.event_id) === String(id));

    if (action === "edit") {
        if (selectedEvent) {
            startEdit(selectedEvent);
        }
        return;
    }

    if (action === "delete") {
        const confirmed = window.confirm(
            "Are you sure you want to delete this event? This action cannot be undone."
        );

        if (!confirmed) {
            return;
        }

        button.disabled = true;

        try {
            const data = await apiRequest(
                `${apiUrl}?id=${encodeURIComponent(id)}`,
                { method: "DELETE" }
            );

            if (eventIdInput.value === id) {
                resetForm();
            }

            await loadEvents();
            showMessage(data.message, "success");
        } catch (error) {
            showMessage(error.message, "error");
            button.disabled = false;
        }
    }
});

searchInput.addEventListener("input", () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadEvents, 250);
});

statusFilter.addEventListener("change", loadEvents);
categoryFilter.addEventListener("change", loadEvents);
document.getElementById("refreshButton").addEventListener("click", loadEvents);
cancelEditButton.addEventListener("click", resetForm);

document.getElementById("resetButton").addEventListener("click", () => {
    setTimeout(() => {
        eventIdInput.value = "";
        formHeading.textContent = "Add a new event";
        saveButton.textContent = "Save event";
        cancelEditButton.hidden = true;
    }, 0);
});

loadEvents();
