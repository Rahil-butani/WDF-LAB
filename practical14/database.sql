
CREATE DATABASE IF NOT EXISTS studenthub_db;

USE studenthub_db;

CREATE TABLE IF NOT EXISTS students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mobile VARCHAR(10) NOT NULL UNIQUE,
    course VARCHAR(10) NOT NULL,
    year_of_study TINYINT NOT NULL,
    gender VARCHAR(10) NOT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    venue VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    status ENUM('Open', 'Closed') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Registered', 'Cancelled') DEFAULT 'Registered',
    UNIQUE (student_id, event_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(event_id)
        ON DELETE CASCADE
);

INSERT IGNORE INTO students
(full_name, email, mobile, course, year_of_study, gender)
VALUES
('Aarav Patel', 'aarav@example.com', '9876543210', 'IT', 2, 'Male'),
('Diya Shah', 'diya@example.com', '9876543211', 'CE', 3, 'Female'),
('Krish Mehta', 'krish@example.com', '9876543212', 'IT', 1, 'Male');

INSERT INTO events
(title, description, event_date, venue, category, status)
VALUES
('Web Development Workshop', 'Learn the basics of web development.', '2027-01-15', 'Computer Lab', 'Workshop', 'Open'),
('Coding Competition', 'A programming competition for students.', '2027-01-20', 'Main Auditorium', 'Competition', 'Open'),
('Cybersecurity Seminar', 'Introduction to cybersecurity concepts.', '2027-01-25', 'Seminar Hall', 'Seminar', 'Open');

INSERT IGNORE INTO registrations (student_id, event_id)
VALUES
(1, 1),
(2, 2),
(3, 3);
