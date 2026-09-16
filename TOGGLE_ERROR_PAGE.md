# Developer Guide: How to Turn the Error / Lockout Page On and Off

This document explains how to activate or deactivate the basic server error page for the system.

---

## 🚀 Quick Instructions

To turn the error page **ON** or **OFF**, you only need to edit **one single line** in one file.

### 📄 Target File:
`app/config/server_status.php`

---

## 🔒 To Turn the Error Page ON (Lock Access)

1. Open `app/config/server_status.php`.
2. Locate **Line 10**:
   ```php
   $ENABLE_SERVER_ERROR = false;
   ```
3. Change `false` to `true`:
   ```php
   $ENABLE_SERVER_ERROR = true;
   ```
4. Save the file and upload/deploy it to your server.

> **Result:** Anyone visiting the system will immediately see a basic WordPress-style "Server Error - Can't communicate with server" page showing the $48 fee breakdown (June $10, July $10, August $10, September $10, Taxes $8).

---

## 🔓 To Turn the Error Page OFF (Restore Normal Access)

1. Open `app/config/server_status.php`.
2. Locate **Line 10**:
   ```php
   $ENABLE_SERVER_ERROR = true;
   ```
3. Change `true` to `false`:
   ```php
   $ENABLE_SERVER_ERROR = false;
   ```
4. Save the file and upload/deploy it to your server.

> **Result:** The system will function normally for all users and login screens/dashboards will work standard.

---

## ⚙️ Summary Table

| Desired State | Line 10 Setting in `app/config/server_status.php` | System Behavior |
| :--- | :--- | :--- |
| **Normal System** | `$ENABLE_SERVER_ERROR = false;` | Full access to application |
| **Error Page Active** | `$ENABLE_SERVER_ERROR = true;` | Blocked with Basic Server Error Page ($48 Total) |

