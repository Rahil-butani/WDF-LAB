
const registerForm = document.getElementById("registerForm");
const passwordInput = document.getElementById("password");
const confirmInput = document.getElementById("confirm");

function validatePasswords() {
    if (passwordInput.value !== confirmInput.value) {
        confirmInput.setCustomValidity("Passwords do not match.");
    } else {
        confirmInput.setCustomValidity("");
    }
}

passwordInput.addEventListener("input", validatePasswords);
confirmInput.addEventListener("input", validatePasswords);

registerForm.addEventListener("submit", function(event) {
    validatePasswords();

    if (!registerForm.checkValidity()) {
        event.preventDefault();
        registerForm.reportValidity();
    }
});
