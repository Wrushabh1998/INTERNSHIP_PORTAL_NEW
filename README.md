# InternTrack Pro — Student Internship Tracking & Attendance Management Portal

InternTrack Pro is a professional, production-ready, database-driven Internship Tracking Portal designed for organizations, companies, and colleges to monitor student internships, biometric face (selfie) attendance, project progress, daily work reports, tasks, leave requests, announcements, and performance rankings.

Developed using **pure vanilla technologies** (no external backend or frontend frameworks like Node, React, Laravel, or Firebase), making it highly optimized, responsive, and easy to deploy on any XAMPP environment.

---

## 🛠️ Technology Stack
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla JS), Chart.js
- **Backend:** PHP 8.0+ (OOP & procedural hybrid, secure prepared statements)
- **Database:** MySQL
- **Web Server:** XAMPP (Apache + MariaDB)

---

## 🚀 Key Features

### 👤 Student Portal
- **Dashboard:** Today's Punch Status (In, Lunch Start, Lunch End, Out), working hours tracker, attendance rate index, task progress, active projects, notification center.
- **Biometric Webcam Attendance:** Real-time webcam capture selfie on punch-in, punch-out, and lunch breaks. Saves location parameters, IP details, browser client, and face photo log.
- **Leave Management:** Apply for leave, upload PDF/Image proof documents, select leave categories (Casual, Sick, Emergency, Medical, Other) and track history.
- **My Tasks:** Update task progress slider (0% to 100%), add comments, check priority categories, and deadlines.
- **Daily Work Reports:** Submit daily logs containing module details, hours worked, challenges, solutions, screenshot uploads, GitHub repository link, and live preview URL.
- **My Projects:** View assigned projects, milestones, role assignments, and team stacks.
- **Documents Center:** Store, preview, and download letters (Offer Letter, ID, Certificates, Reports).
- **Performance analytics:** Multi-criteria performance breakdown scorecard (Attendance, Tasks, Reports, Avg Hours).
- **Certificate Portal:** Download authenticated certificates issued by the system once finalized by the admin.

### 👑 Admin Portal
- **Dashboard Analytics:** Core metrics counting total active interns, today's present, absent, on leave, late arrivals, active projects, pending tasks, and average monthly hours. Responsive charts (weekly attendance, task progress breakdown, monthly trends, department ranks).
- **Intern Management:** Add, edit, delete, activate/deactivate student credentials, and reset account passwords.
- **Attendance Registry:** Comprehensive grid tracking daily stamps, geolocation details, IP, client agents, and webcam punch photos. Allows manual time adjustments.
- **Leave Review Desk:** Process pending leave requests, check doctor/medical proofs, and log admin review comments.
- **Project Board:** Create projects, set milestones, track completion rates, and assign team rosters.
- **Task Allocator:** Create tasks, assign priorities (Low to Critical), set deadlines, attach references, and verify completed submissions.
- **Daily Report Desk:** Read, approve, or reject daily work logs, check screenshots/code repositories, and add reviews.
- **Performance Evaluation:** Automated score evaluation computing overall performance indices and giving star ratings.
- **Reports Builder:** Form-driven analytics exporter generating printable sheets or downloadable CSV registers for Attendance, Leaves, Tasks, and Performance.
- **Notice Board:** Dispatch categorized notices (General, Urgent, Meetings, Holidays) with document attachment support.
- **Audit logs:** Detailed tracking log documenting account events, login status, actions, and client IPs.
- **System settings:** Configure office hours, lunch duration, holidays, and departments.

---

## 📥 XAMPP Installation Guide

Follow these simple steps to deploy InternTrack Pro on your local system:

### 1. File Scaffold Placement
1. Download or clone this project.
2. Copy the entire `INTERNSHIP PORTAL` directory.
3. Paste it inside your XAMPP's `htdocs` folder:
   `C:\xampp\htdocs\INTERNSHIP PORTAL\`

### 2. Database Import
1. Start **XAMPP Control Panel** and enable **Apache** and **MySQL**.
2. Open your web browser and navigate to: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
3. Create a new database named: **`internship_portal`** (with charset collation `utf8mb4_unicode_ci`).
4. Click on the **Import** tab.
5. Choose file and select `schema.sql` located inside the project:
   `C:\xampp\htdocs\INTERNSHIP PORTAL\database\schema.sql`
6. Click **Go** / **Import** to configure the 22 normalization tables and load demo data.

### 3. Verify Database Credentials
By default, the database connection is pre-configured to look for standard XAMPP credentials (`root` / no password). If you have a custom MySQL root password:
1. Open `config/config.php` in a text editor.
2. Adjust `DB_PASS` constant to your password:
   ```php
   define('DB_PASS', 'your_password');
   ```

### 4. Check Folder Write Permissions
Verify that the `uploads` directory and its subdirectories inside the project have write permissions:
- `uploads/profile`
- `uploads/attendance`
- `uploads/documents`
- `uploads/reports`
- `uploads/leaves`
- `uploads/announcements`

---

## 🔑 Demo Account Credentials

Use these credentials to log in and explore the dashboards:

### 👑 Administrator Role
- **Email:** `admin@portal.com`
- **Password:** `Admin@123`

### 👤 Student Role
- **Email:** `aarav@student.com`
- **Password:** `Student@123`

*(Alternatively, you can log in as Priya Patel: `priya@student.com` or Sneha Joshi: `sneha@student.com` using the same `Student@123` password).*

---

## 🔒 Security Practices Configured
- **Prepared Statements (PDO):** Complete defense against SQL Injection.
- **Input Sanitization:** Filter wrappers against XSS injection vectors.
- **CSRF Token Protection:** Dynamic session tokens protecting all state-modifying actions.
- **Session Expiry Watchdog:** Client-side count alerts pinger prompting extension or secure logouts.
- **Brute-Force Account Protection:** Standard lockouts after 5 consecutive password mismatches.
