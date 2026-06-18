# TAU-UREO Portal

## University Research Ethics Office Portal
Tarlac Agricultural University

### Version 1.0 - Initial Implementation

## Overview
The TAU-UREO Portal is a web-based application management system that streamlines the research ethics review process using Human-In-The-Loop (HITL) automation with AI-powered classification and automated document validation.

---

# COMPLETE APPLICATION FLOW: FROM APPLICANT TO UREC

This section documents the end-to-end journey of a research ethics application from initial submission to final UREC approval.

## PHASE 1: APPLICATION SUBMISSION

### Step 1.1: Letter of Intent Submission
**File:** `submit-intent.php`

**Process:**
- Applicant submits letter of intent via public form
- Required fields:
  - Full Name
  - Email Address
  - Applicant Type (Student/Faculty/Researcher)
  - Research Title
- System generates unique queue number (UREO-XXXX format)
- Status transitions: `INTENT_RECEIVED` → `REQUIREMENTS_SENT` → `REQUIREMENTS_PENDING`

**Automated Actions:**
- Queue number generated from system counter
- System message created with acknowledgment
- Three system documents provided to applicant:
  1. General Guidelines (PDF)
  2. TAU-REO-QF-01 Application Form (DOCX template)
  3. TAU-REO-QF-02 Research Ethics Review Category (DOCX template)
- Email notification sent with queue number and document access instructions

**Database Tables:**
- `applications` - New record created
- `system_messages` - Acknowledgment message stored
- `system_documents` - Template documents linked

---

## PHASE 2: APPLICANT PORTAL ACCESS

### Step 2.1: OTP-Based Authentication
**File:** `applicant/login.php`

**Process:**
- Applicant logs in using queue number
- System generates 6-digit OTP code
- OTP sent to applicant's email
- OTP expires after 10 minutes
- Maximum 3 attempts allowed
- Session timeout: 30 minutes

**Security Features:**
- OTP stored in `otp_sessions` table
- IP address tracking
- Attempt counter
- Automatic session cleanup

### Step 2.2: Applicant Dashboard
**File:** `applicant/dashboard.php`

**Features:**
- View current application status
- Track progress percentage
- View document upload statistics
- Access UREC review comments (when available)
- View recent activity (messages + status changes)
- Download certificates upon approval

**Progress Calculation:**
- INTENT_RECEIVED: 15%
- REQUIREMENTS_PENDING/INCOMPLETE: 30%
- UNDER_STAFF_REVIEW: 50%
- CATEGORIZED: 70%
- CATEGORY_FORMS_REQUIRED: 75%
- CHECKLIST_SUBMITTED: 80%
- FORWARDED_TO_UREC: 85%
- UNDER_ETHICAL_REVIEW: 90%
- APPROVED/CERTIFICATE_ISSUED: 100%

---

## PHASE 3: DOCUMENT SUBMISSION & VALIDATION

### Step 3.1: Document Upload
**File:** `applicant/upload-document.php`

**Process:**
- Applicant uploads required documents
- Supported formats: PDF, DOC, DOCX, XLSX, XLS, JPG, JPEG, PNG
- Maximum file size: 10MB
- Files stored in `uploads/{queue_number}/` directory
- Each document validated for type and size

**Required Documents:**
- Letter of Intent
- Research Proposal
- Research Ethics Checklist
- Informed Consent Form
- Curriculum Vitae
- Data Collection Instruments

### Step 3.2: Automated Document Validation
**File:** `applicant/automation/validate-documents.php`

**Process:**
- System checks uploaded documents against required checklist
- Validates mandatory documents are present
- Checks for conditional requirements

**Status Transitions:**

**Path A - Incomplete Documents:**
- Status: `REQUIREMENTS_INCOMPLETE`
- Email notification sent to applicant listing missing documents
- Completion attempt counter incremented
- Applicant must upload missing documents

**Path B - Additional Requirements Detected:**
- Status: `UNDER_AUTO_REVIEW` → `STAFF_REVIEW_REQUIRED`
- Flag `has_additional_requirements` set to 1
- Application auto-assigned to available staff member (least busy)
- Staff notification email sent
- Requires human review

**Path C - All Documents Complete (No Additional Requirements):**
- Status: `UNDER_AUTO_REVIEW` → `REGISTERED` → `UNDER_STAFF_REVIEW`
- Auto-registered and forwarded for review
- Success email sent to applicant
- Staff can proceed with review

---

## PHASE 4: STAFF REVIEW & CLASSIFICATION

### Step 4.1: Staff Dashboard
**File:** `staff/dashboard.php`

**Features:**
- View assigned applications
- Auto-claim unassigned applications
- Track statistics (total assigned, pending review, completed, revisions needed, overdue)
- View urgent items (overdue, new messages, revisions required)
- Access application queue

### Step 4.2: Application Review
**File:** `staff/view-application.php`

**Process:**
- Staff views application details
- Auto-claims unassigned applications on first view
- Reviews uploaded documents
- Reviews QF-01 and QF-02 forms
- Can send messages to applicant
- Can download documents
- Activity logged in `staff_logs` table

**Key Features:**
- View system messages (acknowledgments, requirements)
- View system documents (templates, guidelines)
- Review fillable forms status (QF-01, QF-02)
- Check AI classification results
- View document validation status
- Direct messaging with applicant

### Step 4.3: AI-Powered Classification
**File:** `applicant/automation/ResearchCategoryClassifier.php`

**Process:**
- AI analyzes research title and QF-02 Section C text
- Classifies into one of 7 categories:
  1. Human Use
  2. Animal Welfare
  3. Plant Use
  4. Microbiological/Biotechnological Use
  5. Engineering
  6. Information Technology Use
  7. Food Technology Use

**AI Training Data Sources:**
- Reference data (`reference.json`) - category definitions and keywords
- Historical corrections (`history.jsonl`) - staff feedback from past classifications
- Training CSV (`training_data.csv`) - additional labeled data

**Staff Review of AI Classification:**
- Staff can accept AI prediction
- Staff can correct AI prediction
- Corrections logged for model learning
- Model auto-retrains after 10 corrections
- Final category stored in `ai_classification.json`

**File:** `staff/handle-ai-feedback.php`

### Step 4.4: Staff Approval Decision
**File:** `staff/process-approve-application.php`

**Process:**
- Staff selects review type based on classification and risk assessment:
  1. **Exempt Review** - Minimal risk, no category forms needed
  2. **Expedited Review** - Moderate risk, category forms required
  3. **Full Review** - High risk, category forms required

**Status Transitions:**

**Path A - Exempt Review:**
- Status: `CATEGORIZED` → `UREC_REVIEW_REQUIRED`
- No category forms required
- System message created for UREC Manager
- Email notification sent to applicant
- Application ready for UREC Manager assignment

**Path B - Expedited/Full Review:**
- Status: `CATEGORIZED` → `CATEGORY_FORMS_REQUIRED`
- Category determination based on AI/staff classification
- Annotated QF-02 PDF generated with staff remarks
- Category-specific guidelines attached
- Secure token generated for applicant access (7-day expiry)
- Email sent with category forms and annotated QF-02
- System message created

**PDF Generation:**
- Uses FPDI + TCPDF libraries
- Annotates QF-02 with floating remarks
- Bakes staff remarks into PDF at calibrated positions
- Generates category-specific review documents

---

## PHASE 5: CATEGORY FORMS (EXPEDITED/FULL REVIEW ONLY)

### Step 5.1: Category Form Access
**File:** `applicant/category-form.php`

**Process:**
- Applicant receives email with secure token link
- Token validates access (7-day expiry)
- System loads appropriate category checklist based on classification
- Available checklists:
  - `fill-Human-checklist.php`
  - `fill-Animal-checklist.php`
  - `fill-Plant-checklist.php`
  - `fill-Engineering-checklist.php`
  - `fill-Food-checklist.php`

### Step 5.2: Category Checklist Completion
**File:** `applicant/fill-category-form.php`

**Process:**
- Applicant completes category-specific ethics checklist
- Form includes:
  - Category-specific criteria questions
  - Risk assessment items
  - Compliance declarations
  - Additional remarks
- Data saved to `fillable_forms` table (form_type: 'category_checklist')
- Status: `CATEGORY_FORMS_REQUIRED` → `CHECKLIST_SUBMITTED`
- AJAX submission with real-time validation
- PostMessage communication for iframe embedding

**Features:**
- Pre-filled with applicant name and research title
- Loads previously submitted data for editing
- Guideline documents displayed for reference
- Responsive design with modern UI
- Autosave functionality

---

## PHASE 6: STAFF FORWARDING TO UREC

### Step 6.1: Staff Review of Checklist
**File:** `staff/view-application.php`

**Process:**
- Staff reviews submitted category checklist
- Verifies completeness and accuracy
- Can request revisions if needed
- Approves checklist for UREC forwarding

### Step 6.2: Forward to UREC
**File:** `staff/process-forward-urec.php`

**Process:**
- Staff initiates forwarding after checklist approval
- System reads AI classification from `ai_classification.json`
- Maps classification to appropriate UREC committee:
  - Human Use → HUMAN_USE committee
  - Animal Welfare → ANIMAL_WELFARE committee
  - Plant Use → PLANT_USE committee
  - Microbiological/Biotechnological → MICRO_BIO committee
  - Engineering → ENGINEERING committee
  - Information Technology → IT_USE committee
  - Food Technology → FOOD_TECH committee

**Status Transition:**
- Status: `CHECKLIST_SUBMITTED` → `ASSIGNING_UREC_EVALUATOR` → `FORWARDED_TO_UREC`

**Database Updates:**
- `urec_committee_id` set
- `forwarded_to_urec_at` timestamp set
- `forwarded_by_staff` recorded
- Status history entry created

**Notifications:**
- Email sent to all UREC members in assigned committee
- System message created for UREC committee
- System message created for applicant
- Staff activity logged

**Transaction Safety:**
- Uses database transaction with minimal lock scope
- Lock timeout set to 10 seconds to prevent deadlocks
- Non-blocking notifications sent after commit

---

## PHASE 7: UREC COMMITTEE ASSIGNMENT

### Step 7.1: UREC Dashboard
**File:** `urec/dashboard.php`

**Features:**
- View assigned applications
- Track review statistics
- View committee-wide pending assignments (chairperson only)
- Access review queue
- View recent reviews

**Statistics Tracked:**
- Assigned to me
- Pending review
- Completed reviews
- Pending assignment (chairperson only)

### Step 7.2: Committee Assignment
**File:** `urec/committee-assignment.php`

**Process:**
- Chairperson assigns application to specific committee
- Selects one or more evaluators from committee members
- Can add assignment notes
- Multiple evaluators can be assigned

**Database Updates:**
- `urec_committee_id` set
- `urec_reviewed_by` set (primary evaluator)
- Additional evaluator IDs stored in `urec_review_notes` as JSON
- Status history entry created
- Staff activity logged

**Security:**
- Only chairpersons can access assignment interface
- Committee designation checked (contains "Chair" or "Head")
- Evaluators must be active members of the committee

**Status Transition:**
- Status: `UREC_REVIEW_REQUIRED` → `FORWARDED_TO_UREC`

---

## PHASE 8: UREC ETHICAL REVIEW

### Step 8.1: UREC Application View
**File:** `urec/view-application.php`

**Process:**
- Assigned evaluators access application
- View all application details
- Review documents and forms
- Review category checklist
- View staff activity history
- View UREC activity log
- Can add annotations to research proposal PDF

**Security Checks:**
- User must be assigned evaluator
- Or user must belong to assigned committee
- Chairpersons can view all committee applications
- Access denied if not authorized

**Features:**
- View assigned evaluators list
- View committee information
- View form data summary
- Document viewer with annotation support
- Review queue navigation

### Step 8.2: Ethical Review Process
**File:** `urec/review-proposal.php`

**Process:**
- Evaluators conduct ethical review
- Review research methodology
- Assess risk to participants/environment
- Check compliance with ethical guidelines
- Add annotations to research proposal
- Provide review comments

**Annotation System:**
- PDF annotation viewer
- Floating comments on research proposal
- Annotation storage in `document_annotations` table
- Applicants can view annotations via `applicant/view-annotations.php`

### Step 8.3: UREC Decision
**File:** `urec/process-approve-application.php`

**Process:**
- Evaluators make final decision:
  1. **Approve** - Application ethically sound
  2. **Reject** - Application does not meet ethical standards
  3. **Request Revision** - Requires modifications before approval

**Status Transitions:**

**Path A - Approval:**
- Status: `UNDER_ETHICAL_REVIEW` → `APPROVED` → `CERTIFICATE_ISSUED`
- Decision recorded in `urec_decision` field
- Decision date recorded
- Email notification sent to applicant
- System message created
- Certificate generation triggered

**Path B - Rejection:**
- Status: `UNDER_ETHICAL_REVIEW` → `REJECTED`
- Decision recorded with remarks
- Email notification sent to applicant with explanation
- System message created
- Applicant may submit new application

**Path C - Revision Required:**
- Status: `UNDER_ETHICAL_REVIEW` → `REVISIONS_REQUIRED`
- Revision deadline set (default: 2 weeks)
- Specific revision requirements provided
- Email notification sent to applicant
- System message created
- Application returns to applicant for revisions

**Database Updates:**
- `current_status` updated
- `urec_review_notes` stored
- `urec_decision` recorded
- `urec_decision_date` timestamped
- `urec_revision_deadline` set (if applicable)
- Status history entry created
- UREC activity logged

**Transaction Safety:**
- Uses database transaction
- Rollback on error
- Activity logging outside transaction

---

## PHASE 9: FINAL OUTCOMES

### Outcome A: Approval & Certificate Issuance
**Status:** `CERTIFICATE_ISSUED`

**Process:**
- Ethics approval certificate generated
- Certificate stored in system
- Applicant can download from dashboard
- Research can commence
- Application archived

### Outcome B: Rejection
**Status:** `REJECTED`

**Process:**
- Applicant receives rejection notification
- Reasons for rejection provided
- Applicant may submit new application
- Application archived

### Outcome C: Revision Loop
**Status:** `REVISIONS_REQUIRED` → (Applicant revises) → `UNDER_ETHICAL_REVIEW`

**Process:**
- Applicant makes requested revisions
- Re-uploads modified documents
- Staff reviews revisions
- Application returns to UREC for re-review
- Loop continues until approval or rejection

---

## STATUS FLOW DIAGRAM

```
[INTENT_RECEIVED]
    ↓
[REQUIREMENTS_SENT]
    ↓
[REQUIREMENTS_PENDING]
    ↓ (Applicant uploads documents)
[UNDER_AUTO_REVIEW]
    ↓
    ├─→ [REQUIREMENTS_INCOMPLETE] → (Applicant uploads missing docs) → [UNDER_AUTO_REVIEW]
    │
    ├─→ [STAFF_REVIEW_REQUIRED] → (Staff reviews additional requirements) → [UNDER_STAFF_REVIEW]
    │
    └─→ [REGISTERED] → [UNDER_STAFF_REVIEW]
           ↓
       [CATEGORIZED] (AI classification)
           ↓
       [APPROVAL DECISION]
           ├─→ Exempt → [UREC_REVIEW_REQUIRED]
           │               ↓
           │           [COMMITTEE ASSIGNMENT]
           │               ↓
           │           [FORWARDED_TO_UREC]
           │               ↓
           │           [UNDER_ETHICAL_REVIEW]
           │               ↓
           │           [APPROVED/REJECTED/REVISIONS_REQUIRED]
           │
           └─→ Expedited/Full → [CATEGORY_FORMS_REQUIRED]
                                 ↓
                             [APPLICANT COMPLETES CHECKLIST]
                                 ↓
                             [CHECKLIST_SUBMITTED]
                                 ↓
                             [STAFF REVIEWS & FORWARDS]
                                 ↓
                             [ASSIGNING_UREC_EVALUATOR]
                                 ↓
                             [FORWARDED_TO_UREC]
                                 ↓
                             [COMMITTEE ASSIGNMENT]
                                 ↓
                             [UNDER_ETHICAL_REVIEW]
                                 ↓
                             [APPROVED/REJECTED/REVISIONS_REQUIRED]
```

---

## KEY AUTOMATION POINTS

1. **Queue Number Generation** - Automatic sequential numbering
2. **OTP Authentication** - Secure time-based codes
3. **Document Validation** - Automated checklist verification
4. **Staff Assignment** - Auto-assign to least busy staff
5. **AI Classification** - Machine learning category prediction
6. **PDF Generation** - Annotated documents with remarks
7. **Token-Based Access** - Secure category form links
8. **Email Notifications** - Automated status updates
9. **Committee Mapping** - Auto-route to appropriate UREC committee
10. **Activity Logging** - Comprehensive audit trail

---

## DATABASE TABLES INVOLVED

- `users` - Staff, admin, and UREC member accounts
- `applications` - Main application records
- `documents` - Uploaded applicant documents
- `required_documents` - Document checklist definitions
- `status_history` - Complete status change log
- `staff_logs` - Staff activity audit trail
- `otp_sessions` - OTP authentication records
- `messages` - Applicant-staff communication
- `email_logs` - Email notification tracking
- `system_settings` - Configuration parameters
- `system_messages` - Internal system messages
- `system_documents` - Template and guideline documents
- `fillable_forms` - QF-01, QF-02, and category form data
- `ai_classifications` - AI classification results and feedback
- `urec_committees` - UREC committee definitions
- `document_annotations` - UREC review annotations
- `urec_activity_log` - UREC activity tracking

---

## SECURITY FEATURES

1. **OTP-Based Authentication** - Time-limited, single-use codes
2. **Role-Based Access Control** - Staff, Admin, UREC roles
3. **Session Management** - 30-minute timeout
4. **SQL Injection Protection** - Prepared statements throughout
5. **File Upload Validation** - Type and size restrictions
6. **Token-Based Form Access** - Secure category form links
7. **Activity Logging** - Complete audit trail
8. **Transaction Safety** - Database rollbacks on errors
9. **IP Address Tracking** - Security monitoring
10. **Committee Authorization** - UREC access restricted to assigned committees

---

## EMAIL NOTIFICATIONS

1. **Queue Number Confirmation** - Upon intent submission
2. **Requirements List** - With template documents
3. **OTP Codes** - For applicant authentication
4. **Incomplete Documents** - Missing document alerts
5. **Staff Assignment** - New review notifications
6. **Exempt Approval** - Direct UREC routing
7. **Category Forms Required** - With annotated QF-02
8. **UREC Forwarding** - Committee assignment notification
9. **UREC Decision** - Approval/rejection/revision
10. **Certificate Issuance** - Final approval notification

---

## Technology Stack

- **Backend:** PHP 8.1+ (Native/Procedural)
- **Frontend:** HTML5, CSS3, Bootstrap 5
- **JavaScript:** AJAX for async operations
- **Database:** MySQL/MariaDB
- **Email:** PHPMailer with Gmail SMTP
- **PDF Generation:** FPDI + TCPDF
- **AI/ML:** PHP-ML (NaiveBayes classifier)
- **Server:** XAMPP (Apache + MySQL + PHP)

---

## Installation

### Prerequisites
- XAMPP (PHP 8.1+ and MySQL 5.7+)
- Web browser (Chrome, Firefox, Edge)
- Composer (for PHP dependencies)

### Setup Steps

1. **Place files in XAMPP directory:**
   ```
   C:\xampp\htdocs\ureo-portal\
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Create database:**
   - Start XAMPP (Apache & MySQL)
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the database schema: `database/schema.sql`
   - Run migration files from `database/migrations/`

4. **Configure database connection:**
   - Edit `config/database.php` if needed
   - Default credentials: root / no password

5. **Set up file upload directory:**
   - Ensure `uploads/` folder exists and is writable
   - Permissions: 755 or as needed

6. **Configure email settings:**
   - Edit `config/config.php`
   - Set SMTP details for email functionality

7. **Train AI classifier (optional but recommended):**
   ```bash
   php scripts/train-ai-classifier.php --retrain
   ```

8. **Access the portal:**
   ```
   http://localhost/ureo-portal/
   ```

### Default Admin Account
- **Username:** admin
- **Password:** admin123
- **Important:** Change this password immediately in production!

---

## Directory Structure
```
ureo-portal/
├── applicant/              # Applicant portal
│   ├── automation/         # Automation scripts
│   │   ├── ResearchCategoryClassifier.php  # AI classifier
│   │   ├── TrainableEthicsClassifier.php   # Legacy classifier
│   │   ├── reference.json                   # Category definitions
│   │   ├── history.jsonl                    # Staff feedback history
│   │   ├── validate-documents.php           # Document validation
│   │   └── models/                           # Trained AI models
│   ├── dashboard.php       # Applicant dashboard
│   ├── login.php          # OTP login
│   ├── logout.php         # Logout handler
│   ├── upload-document.php # Document upload
│   ├── send-message.php   # Messaging
│   ├── category-form.php   # Category form access
│   ├── fill-category-form.php # Category checklist filler
│   ├── fill-Human-checklist.php
│   ├── fill-Animal-checklist.php
│   ├── fill-Plant-checklist.php
│   ├── fill-Engineering-checklist.php
│   ├── fill-Food-checklist.php
│   ├── fill-qf01-form.php  # QF-01 form
│   ├── fill-qf02-form.php  # QF-02 form
│   ├── documents.php       # Document management
│   ├── view-annotations.php # View UREC annotations
│   └── history.php         # Application history
├── staff/                  # Staff portal
│   ├── dashboard.php       # Staff dashboard
│   ├── login.php          # Staff login
│   ├── view-application.php # Application view
│   ├── process-approve-application.php # Approval processing
│   ├── process-forward-urec.php # Forward to UREC
│   ├── handle-ai-feedback.php # AI classification feedback
│   ├── ai-classification.php # AI classification interface
│   ├── edit-qf02-remarks.php # Edit QF-02 remarks
│   ├── request-revision.php # Request revisions
│   ├── applications.php     # Application queue
│   ├── review-queue.php     # Review queue
│   └── reports.php         # Reports
├── urec/                   # UREC portal
│   ├── dashboard.php       # UREC dashboard
│   ├── login.php          # UREC login
│   ├── view-application.php # Application view
│   ├── review-proposal.php # Ethical review
│   ├── process-approve-application.php # UREC decision
│   ├── committee-assignment.php # Committee assignment
│   ├── applications.php     # Application queue
│   ├── review-queue.php     # Review queue
│   ├── reports.php         # Reports
│   ├── urec_admin/         # UREC admin functions
│   │   └── assign-application.php # Assign evaluators
│   └── get-evaluators.php  # Get committee evaluators
├── admin/                  # Admin panel
│   ├── dashboard.php       # Admin dashboard
│   ├── manage-users.php     # User management
│   ├── activity-logs.php   # Activity logs
│   ├── email-logs.php      # Email logs
│   └── system-settings.php # System settings
├── api/                    # API endpoints
│   └── predict-category.php # AI classification API
├── assets/                 # Static assets
│   ├── css/                # Stylesheets
│   ├── js/                 # JavaScript
│   └── to_send/            # Documents to send to applicants
│       └── for_reply_to_categories/ # Category-specific guidelines
├── config/                 # Configuration files
│   ├── config.php         # Main config
│   └── database.php       # DB config
├── database/              # Database schema
│   ├── schema.sql         # Main schema
│   └── migrations/        # Database migrations
├── includes/              # Shared PHP includes
│   ├── functions.php      # Common functions
│   ├── email-template-functions.php # Email templates
│   ├── header.php         # Public header
│   ├── footer.php         # Public footer
│   ├── auth_header.php    # Authenticated header
│   └── auth_footer.php    # Authenticated footer
├── includes_urec/         # UREC-specific includes
│   ├── header.php         # UREC header
│   └── footer.php         # UREC footer
├── scripts/               # Utility scripts
│   └── train-ai-classifier.php # AI model training
├── uploads/               # File uploads (created automatically)
├── index.php              # Public homepage
├── submit-intent.php      # Application submission
├── track-application.php  # Public tracking
├── composer.json          # PHP dependencies
└── readme.md             # This file
```

---

## Features

### For Applicants/Researchers
- Submit letter of intent online
- Receive automatic queue number (UREO-0000 format)
- OTP-based secure login
- Track application progress publicly
- Upload required documents
- Real-time status updates
- Direct messaging with staff
- Complete category-specific checklists
- View UREC review annotations
- Download certificates upon approval

### For REO Staff
- Traditional username/password authentication
- Review applications flagged by automation
- Validate additional/conditional requirements
- Send feedback and communications
- Activity logging for accountability
- Assigned application queue management
- AI classification review and correction
- Approve applications (Exempt/Expedited/Full)
- Generate annotated PDFs with remarks
- Forward applications to UREC

### For UREC Members
- Committee-based access control
- View assigned applications
- Conduct ethical reviews
- Add annotations to research proposals
- Approve/reject/request revisions
- View committee-wide assignments (chairperson)
- Assign evaluators to applications (chairperson)
- Track review statistics

### For Administrators
- Manage staff accounts
- Manage UREC member accounts
- View comprehensive activity logs
- Monitor email notifications
- Configure system settings
- Oversee application processing metrics

### Automation Features (HITL)
- Auto-send requirements upon intent submission
- Automated document validation against checklist
- Cross-matching of submitted vs. required documents
- Detection of conditional requirements
- Smart staff assignment for complex cases
- AI-powered research classification
- Automated email notifications
- Status progression automation
- PDF generation with annotations
- Committee mapping based on classification

---

## Workflow Summary

### Application Process
1. Applicant submits letter of intent
2. System generates queue number (UREO-0000)
3. Automated email with requirements sent
4. Applicant logs in with OTP
5. Applicant uploads documents via dashboard
6. System validates documents automatically
7. AI classifies research into category
8. Staff reviews classification and documents
9. Staff approves with review type (Exempt/Expedited/Full)
10. If Expedited/Full: Applicant completes category checklist
11. Staff forwards to appropriate UREC committee
12. Chairperson assigns evaluators
13. UREC conducts ethical review
14. Final approval/rejection/revision decision
15. Certificate issuance (if approved)

### Automation Triggers
- **Fully Automated:** Intent submission, requirement sending, queue number generation
- **Semi-Automated:** Document validation with conditional checks, AI classification
- **Human Review:** Additional requirements, AI classification verification, final approval

### Review Types
- **Exempt:** Minimal risk, direct to UREC, no category forms
- **Expedited:** Moderate risk, category forms required, single reviewer
- **Full:** High risk, category forms required, full committee review

---

## Database Tables

- `users` - Staff, admin, and UREC member accounts
- `applications` - Research applications
- `documents` - Uploaded files
- `required_documents` - Document checklist
- `status_history` - Status change log
- `staff_logs` - Activity audit trail
- `otp_sessions` - OTP verification
- `messages` - Applicant-staff communication
- `email_logs` - Email tracking
- `system_settings` - Configuration
- `system_messages` - Internal messaging
- `system_documents` - Template documents
- `fillable_forms` - QF-01, QF-02, category forms
- `ai_classifications` - AI classification results
- `urec_committees` - Committee definitions
- `document_annotations` - UREC annotations
- `urec_activity_log` - UREC activity
- `category_forms` - Category form metadata
- `email_templates` - Email template definitions

---

## Support & Contact
For technical support or inquiries:
- Email: ureo@tau.edu.ph
- Phone: (045) 123-4567

---

## License
© 2026 Tarlac Agricultural University. All rights reserved.

---

## Version History
- **v1.0** (February 2026) - Initial release with core HITL automation, AI classification, and UREC integration
