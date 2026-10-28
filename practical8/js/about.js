const faqList = document.getElementById("faqList");
const faqStatus = document.getElementById("faqStatus");
const faqSearch = document.getElementById("faqSearch");
const faqCategory = document.getElementById("faqCategory");

let faqs = [];

async function loadFAQs() {
    faqStatus.textContent = "Loading FAQs...";

    try {
        const response = await fetch("data/faqs.json");

        if (!response.ok) {
            throw new Error("Unable to load FAQs.");
        }

        faqs = await response.json();
        renderFAQs();
    } catch (error) {
        faqStatus.textContent =
            "Unable to load FAQs. Please run the page using Live Server.";
        console.error(error);
    }
}

function renderFAQs() {
    const searchText = faqSearch.value.trim().toLowerCase();
    const selectedCategory = faqCategory.value;

    const filteredFAQs = faqs.filter(function (faq) {
        const matchesSearch =
            faq.question.toLowerCase().includes(searchText) ||
            faq.answer.toLowerCase().includes(searchText);

        const matchesCategory =
            selectedCategory === "all" ||
            faq.category === selectedCategory;

        return matchesSearch && matchesCategory;
    });

    faqList.replaceChildren();

    if (filteredFAQs.length === 0) {
        faqStatus.textContent = "No matching FAQs found.";
        return;
    }

    faqStatus.textContent =
        "Showing " + filteredFAQs.length + " FAQ(s).";

    filteredFAQs.forEach(function (faq) {
        const card = document.createElement("article");
        card.className = "json-card";

        const question = document.createElement("h3");
        question.textContent = faq.question;

        const answer = document.createElement("p");
        answer.textContent = faq.answer;

        const category = document.createElement("p");
        category.textContent = "Category: " + faq.category;

        card.append(question, answer, category);
        faqList.appendChild(card);
    });
}

faqSearch.addEventListener("input", renderFAQs);
faqCategory.addEventListener("change", renderFAQs);

loadFAQs();