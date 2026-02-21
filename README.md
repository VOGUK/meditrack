# MediTrack

**MediTrack** is a secure, privacy-focused web application designed for households to manage medication inventory, track expiration dates, and simplify the reordering process.

Unlike complex medical software, MediTrack is built specifically for home use—ensuring family members stay synced, medicines stay in date, and you never run out of essentials.

It is fully mobile responsive and can be installed on any web host including shared web hosting. 

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/php-%3E%3D%208.0-777bb4.svg)
![SQLite](https://img.shields.io/badge/database-SQLite-003b57.svg)

---

## ✨ Features

* **👥 Household Sharing**: Link multiple users to one household via a secure **10-character Share Code**. Sync data instantly between partners, parents, or caregivers.
* **📦 Inventory Tracking**: Manage stock levels, strengths, and specific storage locations (e.g., "Kitchen Cupboard" or "Fridge").
* **⏳ Expiration Dashboard**: An automated "Needs Attention" view highlights medications that are expired or expiring within 30 days.
* **📋 Reorder System**: Track active prescriptions. When stock hits zero, the app prompts a reorder and logs the order date.
* **📄 PDF Reports**: Generate one-click reports for current stock, reorder lists, or items ready for disposal.
* **🌓 Dark Mode**: Full support for light and dark themes with adjustable text scaling for accessibility.

## 🔒 Security

MediTrack is built with modern security best practices:
* **CSRF Protection**: Every request is validated with a unique cryptographic token to prevent cross-site attacks.
* **SQL Injection Prevention**: All database interactions use strictly prepared statements.
* **XSS Filtering**: User input is escaped before rendering to prevent malicious script injection.
* **Password Hashing**: User credentials are encrypted using Bcrypt (`password_hash`).

---

## 🛠 Technical Stack

* **Backend:** PHP 8.x
* **Database:** SQLite (Zero-configuration, file-based)
* **Frontend:** Vanilla JavaScript, CSS3
* **Dependencies:** [jsPDF](https://github.com/parallax/jsPDF) (CDN)

---

## 🚀 Installation

1.  **Clone the repository:**
    ```bash
    git clone [https://github.com/YOUR_USERNAME/meditrack.git](https://github.com/YOUR_USERNAME/meditrack.git)
    ```
2.  **Upload to your server:**
    Ensure your web server (Apache/Nginx) has the **PHP SQLite3 extension** enabled.
3.  **Set Permissions:**
    The server needs write access to the folder so it can create the `meditrack.db` file on the first run.
4.  **Launch:**
    Navigate to the URL. The app will automatically run migrations and create a default admin account.
    * **Default Username:** `admin`
    * **Default Password:** `admin`
    * *Change your password immediately after logging in!*

---

## ⚠️ Important Note

This application stores your data in a local file named `meditrack.db` which is auto created when you install the app on your web server.
