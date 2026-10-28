
const menuBtn = document.getElementById("menuBtn");
const navbar = document.getElementById("navbar");

menuBtn.addEventListener("click", function () {
    navbar.classList.toggle("active");

    const isOpen = navbar.classList.contains("active");
    menuBtn.setAttribute("aria-expanded", isOpen);
});

const notification = document.getElementById("notification");
const closeNotification = document.getElementById("closeNotification");

closeNotification.addEventListener("click", function () {
    notification.style.display = "none";
});

const themeBtn = document.getElementById("themeBtn");

let savedTheme = localStorage.getItem("theme");

if (savedTheme === "dark") {
    document.body.classList.add("dark-theme");
    themeBtn.textContent = "Light Mode";
}

themeBtn.addEventListener("click", function () {
    document.body.classList.toggle("dark-theme");

    if (document.body.classList.contains("dark-theme")) {
        themeBtn.textContent = "Light Mode";
        localStorage.setItem("theme", "dark");
    } else {
        themeBtn.textContent = "Dark Mode";
        localStorage.setItem("theme", "light");
    }
});

const modal = document.getElementById("modal");
const openModal = document.getElementById("openModal");
const closeModal = document.getElementById("closeModal");

openModal.addEventListener("click", function () {
    modal.hidden = false;
    closeModal.focus();
});

closeModal.addEventListener("click", function () {
    modal.hidden = true;
    openModal.focus();
});

modal.addEventListener("click", function (event) {
    if (event.target === modal) {
        modal.hidden = true;
        openModal.focus();
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !modal.hidden) {
        modal.hidden = true;
        openModal.focus();
    }
});

const slides = [
    {
        title: "Java Examination",
        text: "The Java programming examination will be conducted on Monday."
    },
    {
        title: "Network Assignment",
        text: "Submit your Computer Network assignment before Friday."
    },
    {
        title: "WDF Practical",
        text: "The Web Development Framework practical submission is next week."
    },
    {
        title: "Semester Results",
        text: "Check the StudentHub portal for semester result updates."
    }
];

let currentSlide = 0;

const slideTitle = document.getElementById("slideTitle");
const slideText = document.getElementById("slideText");

function showSlide() {
    slideTitle.textContent = slides[currentSlide].title;
    slideText.textContent = slides[currentSlide].text;
}

document.getElementById("nextBtn").addEventListener("click", function () {
    currentSlide++;

    if (currentSlide >= slides.length) {
        currentSlide = 0;
    }

    showSlide();
});

document.getElementById("prevBtn").addEventListener("click", function () {
    currentSlide--;

    if (currentSlide < 0) {
        currentSlide = slides.length - 1;
    }

    showSlide();
});

const faqQuestions = document.querySelectorAll(".faq-question");

faqQuestions.forEach(function (question) {
    question.addEventListener("click", function () {
        const answer = question.nextElementSibling;
        const isOpen = question.getAttribute("aria-expanded") === "true";

        question.setAttribute("aria-expanded", !isOpen);
        answer.classList.toggle("show");

        const symbol = question.querySelector("span");

        if (isOpen) {
            symbol.textContent = "+";
        } else {
            symbol.textContent = "-";
        }
    });
});
