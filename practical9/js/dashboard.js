const studentList = document.getElementById("studentList");
const studentStatus = document.getElementById("studentStatus");
const studentSearch = document.getElementById("studentSearch");
const studentCourseFilter = document.getElementById("studentCourseFilter");
const studentSort = document.getElementById("studentSort");
const studentPrev = document.getElementById("studentPrev");
const studentNext = document.getElementById("studentNext");
const studentPageInfo = document.getElementById("studentPageInfo");

let students = [];
let currentStudentPage = 1;
const studentsPerPage = 6;

async function fetchStudents() {
    const response = await fetch("data/students.json");

    if (!response.ok) {
        throw new Error("Unable to load student data.");
    }

    return response.json();
}

function showProfile(student) {
    const fields = {
        studentName: student ? student.name : "Student not found",
        studentID: student ? student.studentID : "-",
        studentCourse: student ? student.course : "-",
        studentSemester: student ? student.semester : "-",
        studentEmail: student ? student.email : "-",
        studentMobile: student ? student.mobile : "-",
        studentUniversity: student ? student.university : "-"
    };

    Object.keys(fields).forEach(function (id) {
        document.getElementById(id).textContent = fields[id];
    });

    document.getElementById("profileStatus").textContent = student
        ? "Student profile loaded successfully."
        : "No matching student profile was found.";
}

function renderStudents() {
    const searchText = studentSearch.value.trim().toLowerCase();
    const selectedCourse = studentCourseFilter.value;
    const sortValue = studentSort.value;

    let filteredStudents = students.filter(function (student) {
        const matchesSearch =
            student.name.toLowerCase().includes(searchText) ||
            student.studentID.toLowerCase().includes(searchText);

        const matchesCourse =
            selectedCourse === "all" ||
            student.course === selectedCourse;

        return matchesSearch && matchesCourse;
    });

    filteredStudents.sort(function (a, b) {
        if (sortValue === "name-asc") {
            return a.name.localeCompare(b.name);
        }

        if (sortValue === "name-desc") {
            return b.name.localeCompare(a.name);
        }

        if (sortValue === "id-asc") {
            return a.studentID.localeCompare(b.studentID);
        }

        return b.studentID.localeCompare(a.studentID);
    });

    const totalPages = Math.max(
        1,
        Math.ceil(filteredStudents.length / studentsPerPage)
    );

    if (currentStudentPage > totalPages) {
        currentStudentPage = totalPages;
    }

    const startIndex = (currentStudentPage - 1) * studentsPerPage;
    const pageStudents = filteredStudents.slice(
        startIndex,
        startIndex + studentsPerPage
    );

    studentList.replaceChildren();

    if (pageStudents.length === 0) {
        studentStatus.textContent = "No students found.";
    } else {
        studentStatus.textContent =
            "Showing " + filteredStudents.length + " matching student(s).";

        pageStudents.forEach(function (student) {
            const card = document.createElement("article");
            card.className = "json-card";

            const name = document.createElement("h3");
            name.textContent = student.name;

            const id = document.createElement("p");
            id.textContent = "Student ID: " + student.studentID;

            const course = document.createElement("p");
            course.textContent = "Course: " + student.course;

            const semester = document.createElement("p");
            semester.textContent = "Semester: " + student.semester;

            const email = document.createElement("p");
            email.textContent = "Email: " + student.email;

            const viewButton = document.createElement("button");
            viewButton.type = "button";
            viewButton.textContent = "View Profile";

            viewButton.addEventListener("click", function () {
                showProfile(student);
                document.getElementById("studentProfile").scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });
            });

            card.append(name, id, course, semester, email, viewButton);
            studentList.appendChild(card);
        });
    }

    studentPageInfo.textContent =
        "Page " + currentStudentPage + " of " + totalPages;

    studentPrev.disabled = currentStudentPage === 1;
    studentNext.disabled = currentStudentPage === totalPages ||
        filteredStudents.length === 0;
}

async function loadStudents() {
    studentStatus.textContent = "Loading students...";

    try {
        students = await fetchStudents();

        const savedUser = localStorage.getItem("studentData");
        let profile = null;

        if (savedUser) {
            try {
                const registeredStudent = JSON.parse(savedUser);

                profile = students.find(function (student) {
                    return student.name.toLowerCase() ===
                        registeredStudent.fullname.toLowerCase();
                });
            } catch (error) {
                console.error("Unable to read saved student data.", error);
            }
        }

        if (!profile) {
            const userID = localStorage.getItem("userID");

            profile = students.find(function (student) {
                return student.studentID === userID ||
                    student.name.toLowerCase() ===
                    (userID || "").toLowerCase();
            });
        }

        showProfile(profile);
        renderStudents();
    } catch (error) {
        studentStatus.textContent =
            "Unable to load students. Please run the page using Live Server.";
        document.getElementById("profileStatus").textContent =
            "Student profile could not be loaded.";
        console.error(error);
    }
}

studentSearch.addEventListener("input", function () {
    currentStudentPage = 1;
    renderStudents();
});

studentCourseFilter.addEventListener("change", function () {
    currentStudentPage = 1;
    renderStudents();
});

studentSort.addEventListener("change", renderStudents);

studentPrev.addEventListener("click", function () {
    if (currentStudentPage > 1) {
        currentStudentPage--;
        renderStudents();
    }
});

studentNext.addEventListener("click", function () {
    currentStudentPage++;
    renderStudents();
});

loadStudents();