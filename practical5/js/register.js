
const form = document.getElementById("registerForm");

const fullname = document.getElementById("fullname");
const email = document.getElementById("email");
const mobile = document.getElementById("mobile");
const course = document.getElementById("course");
const year = document.getElementById("year");
const password = document.getElementById("password");
const confirm = document.getElementById("confirm");
const terms = document.getElementById("terms");

const strengthBar = document.getElementById("strengthBar");
const passwordStrength = document.getElementById("passwordStrength");
const registerSuccess = document.getElementById("registerSuccess");

function showError(field, errorId, message) {
    document.getElementById(errorId).textContent = message;

    if (field) {
        field.classList.toggle("input-error", message !== "");
        field.classList.toggle("input-success", message === "");
    }
}

function validateName() {
    const pattern = /^[A-Za-z ]{3,}$/;

    if (!pattern.test(fullname.value.trim())) {
        showError(fullname, "fullnameError", "Enter a valid name with at least 3 letters.");
        return false;
    }

    showError(fullname, "fullnameError", "");
    return true;
}

function validateEmail() {
    const pattern = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;

    if (!pattern.test(email.value.trim())) {
        showError(email, "emailError", "Enter a valid email address.");
        return false;
    }

    showError(email, "emailError", "");
    return true;
}

function validateMobile() {
    const pattern = /^[0-9]{10}$/;

    if (!pattern.test(mobile.value.trim())) {
        showError(mobile, "mobileError", "Enter exactly 10 digits.");
        return false;
    }

    showError(mobile, "mobileError", "");
    return true;
}

function validateCourse() {
    if (course.value === "") {
        showError(course, "courseError", "Please select a course.");
        return false;
    }

    showError(course, "courseError", "");
    return true;
}

function validateYear() {
    if (year.value === "") {
        showError(year, "yearError", "Please select your academic year.");
        return false;
    }

    showError(year, "yearError", "");
    return true;
}

function validatePassword() {
    const pattern = /^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$/;

    if (!pattern.test(password.value)) {
        showError(password, "passwordError",
            "Use 8 characters with uppercase, lowercase, number and special character.");
        return false;
    }

    showError(password, "passwordError", "");
    return true;
}

function validateConfirm() {
    if (confirm.value === "" || confirm.value !== password.value) {
        showError(confirm, "confirmError", "Passwords do not match.");
        return false;
    }

    showError(confirm, "confirmError", "");
    return true;
}

function validateGender() {
    const selected = document.querySelector('input[name="gender"]:checked');
    const error = document.getElementById("genderError");

    error.textContent = selected ? "" : "Please select your gender.";

    return selected !== null;
}

function validateTerms() {
    const error = document.getElementById("termsError");

    error.textContent = terms.checked ? "" : "Please accept the terms and conditions.";

    return terms.checked;
}

function checkPasswordStrength() {
    const value = password.value;
    let score = 0;

    if (value.length >= 8) score++;
    if (/[A-Z]/.test(value)) score++;
    if (/[a-z]/.test(value)) score++;
    if (/[0-9]/.test(value)) score++;
    if (/[!@#$%^&*]/.test(value)) score++;

    strengthBar.style.width = (score * 20) + "%";

    if (value === "") {
        passwordStrength.textContent = "Password strength: Not entered";
        passwordStrength.className = "password-strength";
        strengthBar.style.backgroundColor = "#e0e0e0";
    } else if (score <= 2) {
        passwordStrength.textContent = "Password strength: Weak";
        passwordStrength.className = "password-strength weak";
        strengthBar.style.backgroundColor = "#d32f2f";
    } else if (score <= 4) {
        passwordStrength.textContent = "Password strength: Medium";
        passwordStrength.className = "password-strength medium";
        strengthBar.style.backgroundColor = "#e69500";
    } else {
        passwordStrength.textContent = "Password strength: Strong";
        passwordStrength.className = "password-strength strong";
        strengthBar.style.backgroundColor = "#2e7d32";
    }
}

fullname.addEventListener("input", validateName);
email.addEventListener("input", validateEmail);
mobile.addEventListener("input", validateMobile);
course.addEventListener("change", validateCourse);
year.addEventListener("change", validateYear);

password.addEventListener("input", function () {
    validatePassword();
    checkPasswordStrength();

    if (confirm.value !== "") {
        validateConfirm();
    }
});

confirm.addEventListener("input", validateConfirm);

document.querySelectorAll('input[name="gender"]').forEach(function (field) {
    field.addEventListener("change", validateGender);
});

terms.addEventListener("change", validateTerms);

form.addEventListener("submit", function (event) {
    event.preventDefault();

    const validName = validateName();
    const validEmail = validateEmail();
    const validMobile = validateMobile();
    const validCourse = validateCourse();
    const validYear = validateYear();
    const validPassword = validatePassword();
    const validConfirm = validateConfirm();
    const validGender = validateGender();
    const validTerms = validateTerms();

    if (
        !validName ||
        !validEmail ||
        !validMobile ||
        !validCourse ||
        !validYear ||
        !validPassword ||
        !validConfirm ||
        !validGender ||
        !validTerms
    ) {
        registerSuccess.hidden = true;
        return;
    }

    const student = {
        fullname: fullname.value.trim(),
        email: email.value.trim(),
        mobile: mobile.value.trim(),
        course: course.value,
        year: year.value,
        gender: document.querySelector('input[name="gender"]:checked').value
    };

    localStorage.setItem("studentData", JSON.stringify(student));
    localStorage.setItem("userID", student.fullname);

    registerSuccess.textContent = "Registration completed successfully!";
    registerSuccess.hidden = false;
    form.reset();

    document.querySelectorAll(".input-success, .input-error").forEach(function (field) {
        field.classList.remove("input-success", "input-error");
    });

    checkPasswordStrength();
});

form.addEventListener("reset", function () {
    setTimeout(function () {
        document.querySelectorAll(".error-message").forEach(function (message) {
            message.textContent = "";
        });

        document.querySelectorAll(".input-success, .input-error").forEach(function (field) {
            field.classList.remove("input-success", "input-error");
        });

        registerSuccess.hidden = true;
        checkPasswordStrength();
    }, 0);
});
