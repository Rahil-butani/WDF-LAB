const eventList = document.getElementById("eventList");
const eventStatus = document.getElementById("eventStatus");
const eventSearch = document.getElementById("eventSearch");
const eventCategory = document.getElementById("eventCategory");
const eventSort = document.getElementById("eventSort");
const eventPrev = document.getElementById("eventPrev");
const eventNext = document.getElementById("eventNext");
const eventPageInfo = document.getElementById("eventPageInfo");

let events = [];
let currentPage = 1;
const itemsPerPage = 6;

async function fetchJSON(url) {
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error("Unable to load event data.");
    }

    return response.json();
}

function renderEvents() {
    const searchText = eventSearch.value.trim().toLowerCase();
    const category = eventCategory.value;
    const sortValue = eventSort.value;

    let filteredEvents = events.filter(function (event) {
        const matchesSearch =
            event.title.toLowerCase().includes(searchText) ||
            event.description.toLowerCase().includes(searchText) ||
            event.venue.toLowerCase().includes(searchText);

        const matchesCategory =
            category === "all" || event.category === category;

        return matchesSearch && matchesCategory;
    });

    filteredEvents.sort(function (a, b) {
        if (sortValue === "date-asc") {
            return a.date.localeCompare(b.date);
        }

        if (sortValue === "date-desc") {
            return b.date.localeCompare(a.date);
        }

        if (sortValue === "title-asc") {
            return a.title.localeCompare(b.title);
        }

        return b.title.localeCompare(a.title);
    });

    const totalPages = Math.max(
        1,
        Math.ceil(filteredEvents.length / itemsPerPage)
    );

    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    const startIndex = (currentPage - 1) * itemsPerPage;
    const pageEvents = filteredEvents.slice(
        startIndex,
        startIndex + itemsPerPage
    );

    eventList.replaceChildren();

    if (pageEvents.length === 0) {
        eventStatus.textContent = "No events found.";
    } else {
        eventStatus.textContent =
            "Showing " + filteredEvents.length + " matching event(s).";

        pageEvents.forEach(function (event) {
            const card = document.createElement("article");
            card.className = "json-card";

            const title = document.createElement("h3");
            title.textContent = event.title;

            const categoryText = document.createElement("p");
            categoryText.textContent = "Category: " + event.category;

            const dateText = document.createElement("p");
            dateText.textContent = "Date: " + event.date;

            const venueText = document.createElement("p");
            venueText.textContent = "Venue: " + event.venue;

            const description = document.createElement("p");
            description.textContent = event.description;

            card.append(
                title,
                categoryText,
                dateText,
                venueText,
                description
            );

            eventList.appendChild(card);
        });
    }

    eventPageInfo.textContent =
        "Page " + currentPage + " of " + totalPages;

    eventPrev.disabled = currentPage === 1;
    eventNext.disabled = currentPage === totalPages ||
        filteredEvents.length === 0;
}

async function loadEvents() {
    eventStatus.textContent = "Loading events...";
    eventList.replaceChildren();

    try {
        events = await fetchJSON("data/events.json");
        renderEvents();
    } catch (error) {
        eventStatus.textContent =
            "Unable to load events. Please run the page using Live Server.";
        console.error(error);
    }
}

eventSearch.addEventListener("input", function () {
    currentPage = 1;
    renderEvents();
});

eventCategory.addEventListener("change", function () {
    currentPage = 1;
    renderEvents();
});

eventSort.addEventListener("change", function () {
    renderEvents();
});

eventPrev.addEventListener("click", function () {
    if (currentPage > 1) {
        currentPage--;
        renderEvents();
    }
});

eventNext.addEventListener("click", function () {
    currentPage++;
    renderEvents();
});

loadEvents();