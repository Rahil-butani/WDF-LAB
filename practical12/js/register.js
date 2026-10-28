
const form = document.getElementById("registrationForm");

if (form) {
    const password = document.getElementById("password");
    const confirmPassword = document.getElementById("confirm_password");
    const passwordMessage = document.getElementById("passwordMessage");

    function checkPasswords() {
        if (confirmPassword.value === "") {
            passwordMessage.textContent = "";
            confirmPassword.setCustomValidity("");
            return;
        }

        if (password.value !== confirmPassword.value) {
            passwordMessage.textContent = "Passwords do not match.";
            confirmPassword.setCustomValidity("Passwords do not match.");
        } else {
            passwordMessage.textContent = "Passwords match.";
            confirmPassword.setCustomValidity("");
        }
    }

    password.addEventListener("input", checkPasswords);
    confirmPassword.addEventListener("input", checkPasswords);

    form.addEventListener("submit", function (event) {
        checkPasswords();

        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });
}
