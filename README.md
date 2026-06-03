# SkillBridge Portal 🚀

A full-stack job matching and talent acquisition platform designed to connect recruiters with potential candidates. This application demonstrates core web programming concepts, relational database architecture, and Role-Based Access Control (RBAC).

## 🌟 Key Features

* **Multi-Role Dashboards:** Distinct interface rendering and logic for Administrators, Recruiters, and Talents[cite: 1].
* **Secure Session Management:** Custom implementation of global state preservation and authentication checks[cite: 1].
* **Binary File Processing:** Secure upload handling pipeline for candidate resumes and documents[cite: 1].
* **Relational Data Mapping:** Structured database design utilizing primary/foreign key relationships[cite: 1].

## 🛠️ Tech Stack

* **Backend:** Native PHP (Procedural / Page Controller Pattern)
* **Database:** MySQL / MariaDB
* **Frontend:** HTML5, CSS3

## 📂 Project Architecture

The application is structured using a Page Controller paradigm, with centralized database and session handlers:
* `index.php` - Application entry point and public routing[cite: 1].
* `db_connect.php` - PDO/MySQLi connection encapsulation[cite: 1].
* `session_check.php` - Authentication state validation[cite: 1].
* `*_dashboard.php` - Context-specific views based on user roles[cite: 1].
* `uploads/resumes/` - Storage directory for processed candidate files[cite: 1].

## ⚙️ Local Installation

1. Clone the repository:
   `git clone https://github.com/AliTudaSuda/SkillBridge-Portal.git`
2. Set up a local server environment (e.g., XAMPP, MAMP, or Laragon).
3. Import the database schema:
   * Open phpMyAdmin (or your preferred SQL client).
   * Create a new database named `skillbridge`.
   * Import the provided `skillbridge.sql` file to generate the tables[cite: 1].
4. Configure database credentials:
   * Update the connection variables in `db_connect.php` to match your local database settings[cite: 1].
5. Launch the application by navigating to the project directory in your browser.

## 🛡️ Upcoming Security Refactoring
*Currently undergoing continuous improvement to meet modern security standards:*
* Migration from raw string queries to **PDO Prepared Statements** to prevent SQL Injection.
* Implementation of `PASSWORD_BCRYPT` for secure credential hashing.
* Enhanced MIME-type validation for arbitrary file upload prevention.