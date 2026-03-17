# IAS-LOGS: Audit Document System

A complete Audit Office Document Log and Record-Keeping System built with PHP, Bootstrap, and MySQL.

## System Overview

This system is designed for the Audit Office to log all incoming and outgoing documents. All received letters and documents must be recorded upon arrival and logged out once returned to their respective offices.

## Features

- **User Authentication**: Secure login system with username and password
- **Document Logbook**: Track all incoming and outgoing documents
- **Purchase Request Module**: Separate module for managing purchase requests
- **Time In/Time Out Tracking**: Log when documents arrive and when they're released
- **Search & Filter**: Filter by date, office, and document type
- **Dashboard**: Overview with statistics and recent documents
- **Modern UI**: Green and gold theme matching Audit Office branding
- **Fully Offline**: Works completely offline using XAMPP

## Requirements

- XAMPP (Apache + MySQL + PHP)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser (Chrome, Firefox, Edge, etc.)

## Installation

### Step 1: Setup Database

1. Open XAMPP Control Panel
2. Start Apache and MySQL services
3. Open phpMyAdmin (http://localhost/phpmyadmin)
4. Click on "Import" tab
5. Select the file: `database/audit_log_system.sql`
6. Click "Go" to import the database

### Step 2: Configure Database Connection

1. Open `includes/db.php`
2. Update database credentials if needed (default XAMPP settings):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'audit_log_system');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Default XAMPP password is empty
   ```

### Step 3: Create Admin User

1. Navigate to: `http://localhost/IAS-LOGS/setup_admin.php`
2. This will create the default admin account:
   - **Username:** admin
   - **Password:** admin123
3. **IMPORTANT:** Delete `setup_admin.php` after use for security!

### Step 4: Access the System

1. Place the project folder in `C:\xampp\htdocs\IAS-LOGS`
2. Open your web browser
3. Navigate to: `http://localhost/IAS-LOGS/`
4. You will be redirected to the login page
5. Login with the admin credentials created in Step 3

## Project Structure

```
IAS-LOGS/
│
├── database/
│   └── audit_log_system.sql      # Database schema
│
├── includes/
│   └── db.php                     # Database connection
│
├── documents/
│   ├── index.php                  # Document logbook listing
│   ├── add.php                    # Add new document (Time In)
│   ├── edit.php                   # Edit document
│   └── timeout.php                # Update Time Out
│
├── purchase_requests/
│   ├── index.php                  # Purchase requests listing
│   └── add.php                    # Add new purchase request
│
├── assets/
│   ├── css/                       # Bootstrap CSS files
│   └── js/                        # Bootstrap JS files
│
└── index.php                      # Main dashboard
```

## Usage Guide

### Login

1. Navigate to the system URL: `http://localhost/IAS-LOGS/`
2. Enter your username and password
3. Click "Login" button
4. You'll be redirected to the dashboard

### Adding a Document (Time In)

1. Navigate to **Document Logbook** → **Add New Document**
2. Fill in the required fields:
   - Date Received
   - Office
   - Document Type (Purchase Order or Purchase Request)
   - Particulars
   - Time In
   - Remarks (optional)
3. Click **Add Document**

### Updating Time Out

1. Go to **Document Logbook**
2. Find the document that needs to be logged out
3. Click **Time Out** button
4. Enter the time when the document was released
5. Click **Update Time Out**

### Adding Purchase Request

1. Navigate to **Purchase Requests** → **Add New Purchase Request**
2. Fill in:
   - Date
   - Office
   - Particulars
   - Amount
   - Remarks (optional)
3. Click **Add Purchase Request**

### Searching and Filtering

- Use the filter section on the Document Logbook or Purchase Requests page
- Filter by:
  - Date
  - Office
  - Document Type (Document Logbook only)
- Use the search box to search in particulars, office, or remarks

## Database Tables

### users
Stores user accounts for login authentication.

### document_logs
Stores all incoming and outgoing documents with time tracking.

### purchase_requests
Stores purchase request documents with amount information.

## Security Notes

- This is a basic system suitable for local/internal use
- For production use, consider adding:
  - User authentication
  - Input sanitization enhancements
  - CSRF protection
  - SQL injection prevention (already using prepared statements)

## Troubleshooting

### Database Connection Error
- Ensure MySQL is running in XAMPP
- Check database credentials in `includes/db.php`
- Verify database `audit_log_system` exists

### Page Not Found
- Ensure Apache is running
- Check file paths are correct
- Verify project is in `htdocs` folder

### Bootstrap Not Loading
- Check that `assets` folder contains Bootstrap files
- Verify file paths in HTML (should be relative paths)

## Support

For issues or questions, check:
1. XAMPP error logs
2. PHP error logs
3. Browser console for JavaScript errors

## License

This system is created for educational and internal office use.

