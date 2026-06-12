# TAU-UREO Portal

## University Research Ethics Office Portal
Tarlac Agricultural University

### Version 1.0 - Initial Implementation

## Overview
The TAU-UREO Portal is a web-based application management system that streamlines the research ethics review process using Human-In-The-Loop (HITL) automation.

## Features

### For Applicants/Researchers
- Submit letter of intent online
- Receive automatic queue number (UREO-0000 format)
- OTP-based secure login
- Track application progress publicly
- Upload required documents
- Real-time status updates
- Direct messaging with staff
- Download certificates upon approval

### For REO Staff
- Traditional username/password authentication
- Review applications flagged by automation
- Validate additional/conditional requirements
- Send feedback and communications
- Activity logging for accountability
- Assigned application queue management

### For Administrators
- Manage staff accounts
- View comprehensive activity logs
- Monitor system performance
- Oversee application processing metrics

### Automation Features (HITL)
- Auto-send requirements upon intent submission
- Automated document validation against checklist
- Cross-matching of submitted vs. required documents
- Detection of conditional requirements
- Smart staff assignment for complex cases
- Automated email notifications
- Status progression automation

## Technology Stack
- **Backend:** PHP (Native)
- **Frontend:** HTML5, CSS3, Bootstrap 5
- **JavaScript:** AJAX for async operations
- **Database:** MySQL
- **Server:** XAMPP

## Installation

### Prerequisites
- XAMPP (PHP 7.4+ and MySQL 5.7+)
- Web browser (Chrome, Firefox, Edge)

### Setup Steps

1. **Place files in XAMPP directory:**
   ```
   C:\xampp\htdocs\ureo-portal\
   ```

2. **Create database:**
   - Start XAMPP (Apache & MySQL)
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the database schema: `database/schema.sql`
   - Or run SQL manually to create tables

3. **Configure database connection:**
   - Edit `config/database.php` if needed
   - Default credentials: root / no password

4. **Set up file upload directory:**
   - Ensure `uploads/` folder exists and is writable
   - Permissions: 755 or as needed

5. **Configure email settings:**
   - Edit `config/config.php`
   - Set SMTP details for email functionality

6. **Access the portal:**
   ```
   http://localhost/ureo-portal/
   ```

### Default Admin Account
- **Username:** admin
- **Password:** admin123
- **Important:** Change this password immediately in production!

## Directory Structure
```
ureo-portal/
├── applicant/              # Applicant portal
│   ├── automation/         # Automation scripts
│   ├── dashboard.php       # Applicant dashboard
│   ├── login.php          # OTP login
│   ├── logout.php         # Logout handler
│   ├── upload-document.php # Document upload
│   └── send-message.php   # Messaging
├── staff/                  # Staff portal
│   ├── dashboard.php       # Staff dashboard
│   ├── login.php          # Staff login
│   └── view-application.php # Application view
├── admin/                  # Admin panel
├── assets/                 # Static assets
│   ├── css/
│   └── js/
├── config/                 # Configuration files
│   ├── config.php         # Main config
│   └── database.php       # DB config
├── database/              # Database schema
│   └── schema.sql         # SQL schema
├── includes/              # Shared PHP includes
│   └── functions.php      # Common functions
├── uploads/               # File uploads (created automatically)
├── index.php              # Public homepage
└── submit-intent.php      # Application submission
```

## Workflow

### Application Process
1. Applicant submits letter of intent
2. System generates queue number (UREO-0000)
3. Automated email with requirements sent
4. Applicant uploads documents via dashboard
5. System validates documents automatically
6. If simple case: Auto-register and forward to UREC
7. If complex case: Flag for staff review
8. Staff validates additional requirements
9. Application proceeds to UREC review
10. Final approval and certificate issuance

### Automation Triggers
- **Fully Automated:** Intent submission, requirement sending
- **Semi-Automated:** Document validation with conditional checks
- **Human Review:** Additional requirements, edge cases, final approval

## Security Features
- OTP-based applicant authentication
- Password hashing (bcrypt) for staff accounts
- SQL injection protection (prepared statements)
- File upload validation
- Session management with timeouts
- Activity logging for audit trails
- Role-based access control

## Email Notifications
- Queue number confirmation
- Requirements list
- OTP codes
- Status updates
- Incomplete submission alerts
- Staff assignments
- Final approvals

## Database Tables
- `users` - Staff and admin accounts
- `applications` - Research applications
- `documents` - Uploaded files
- `required_documents` - Document checklist
- `status_history` - Status change log
- `staff_logs` - Activity audit trail
- `otp_sessions` - OTP verification
- `messages` - Applicant-staff communication
- `email_logs` - Email tracking
- `system_settings` - Configuration

## Support & Contact
For technical support or inquiries:
- Email: ureo@tau.edu.ph
- Phone: (045) 123-4567

## License
© 2026 Tarlac Agricultural University. All rights reserved.

## Version History
- **v1.0** (February 2026) - Initial release with core HITL automation
