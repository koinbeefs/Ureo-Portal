# TAU-UREO Portal Approval Workflow Implementation

## Overview
This document outlines the complete approval workflow implementation for the TAU-UREO Portal, handling different review types (Exempt, Expedited, Full) and their respective processes.

## Workflow Summary

### 1. Exempt Review Workflow
- **Process**: Direct approval → UREC Manager assignment
- **No category forms required**
- **Status Flow**: `CATEGORIZED` → `UREC_REVIEW_REQUIRED`
- **Next Step**: UREC Manager assigns to UREC member or takes the application

### 2. Expedited/Full Review Workflow
- **Process**: Approval → Category Forms → UREC Review
- **Category forms required** based on QF02 responses
- **Status Flow**: `CATEGORIZED` → `CATEGORY_FORMS_REQUIRED` → `CATEGORY_FORMS_COMPLETED` → `UREC_REVIEW_REQUIRED`
- **Categories**: Human, Animal, Plant, Engineering, Food

## File Structure

### Core Approval Files
```
staff/
├── approve-application.php          # Approval interface
├── process-approve-application.php  # Main approval processing
├── category-form-filler.php         # Staff category form interface
├── process-category-form.php        # Category form processing
└── send-category-forms.php          # Send forms to applicants

applicant/
└── category-form.php                # Applicant category form interface

includes/
└── category-form-pdf-generator.php  # PDF generation system
```

### Database Tables
```
applications                          # Main application data
fillable_forms                       # QF01, QF02, and category form data
category_form_tokens                 # Access tokens for applicant forms
system_messages                      # Internal messaging
status_history                       # Application status tracking
```

## Key Features

### 1. Smart Category Detection
The system automatically determines the appropriate category based on QF02 responses:
- **Human**: Criteria 1,2,3,4,5,15,16,17
- **Animal**: Criteria 6,7,8,9
- **Plant**: Criteria 10,11,12
- **Engineering**: Criteria 16,17,18
- **Food**: Criteria 19,20

### 2. PDF Generation with Floating Remarks
- Generates annotated QF02 PDF with staff remarks
- Creates category-specific form PDFs
- Combines QF02 + Category forms into single review document
- Uses FPDI + TCPDF for PDF manipulation

### 3. Secure Token-Based Access
- Generates unique tokens for category form access
- 7-day expiration for security
- Tracks access and completion status

### 4. Email Notifications
- Different templates for each workflow step
- Automatic notifications to applicants
- System messages for internal tracking

## Implementation Details

### Approval Process (`process-approve-application.php`)
```php
// Exempt workflow
if ($review_type === 'exempt') {
    $new_status = 'UREC_REVIEW_REQUIRED';
    handleExemptWorkflow($conn, $application, $queue_number);
} else {
    // Category forms workflow
    $new_status = 'CATEGORY_FORMS_REQUIRED';
    handleCategoryFormsWorkflow($conn, $application, $queue_number, $review_type);
}
```

### Category Determination
```php
function determineCategory($qf02_data) {
    // Logic to determine category based on QF02 responses
    // Checks specific criteria for each category
    // Defaults to 'human' if no specific category found
}
```

### PDF Generation
```php
// Generate annotated QF02 with remarks
$annotated_pdf_path = generateAnnotatedPDF($queue_number, $qf02_data['form_data']);

// Generate category form PDF
$category_pdf_path = generateCategoryFormPDF($queue_number, $category, $form_data);

// Generate combined review PDF
$combined_pdf_path = generateCombinedReviewPDF($queue_number, $category, $form_data);
```

## Database Schema Updates

### Fillable Forms Enhancement
The existing `fillable_forms` table now supports multiple form types:
- `qf01` - QF01 form data
- `qf02` - QF02 form data  
- `category_form` - Category-specific form data and PDF paths
- `category_token` - Secure access tokens for applicant forms

Token data structure in `form_data` JSON:
```json
{
    "token": "unique_token_string",
    "expires_at": "2026-03-30 17:00:00",
    "category": "human",
    "review_type": "expedited",
    "accessed_at": "2026-03-23 10:30:00"
}
```

## Status Flow Chart

```
[STAFF REVIEW]
     ↓
[CATEGORIZED]
     ↓
[APPROVAL SELECTION]
     ├── Exempt → UREC_REVIEW_REQUIRED
     └── Expedited/Full → CATEGORY_FORMS_REQUIRED
                              ↓
                        [FORMS SENT TO APPLICANT]
                              ↓
                        [APPLICANT COMPLETES FORMS]
                              ↓
                        CATEGORY_FORMS_COMPLETED
                              ↓
                        UREC_REVIEW_REQUIRED
```

## Email Templates Required

1. **EXEMPT_APPROVED** - Exempt approval notification
2. **CATEGORY_FORMS_REQUIRED** - Category forms needed
3. **CATEGORY_FORMS_LINK** - Category forms access link
4. **CATEGORY_FORMS_COMPLETED** - Forms completion confirmation

## Security Features

1. **Token-based authentication** for category forms
2. **Role-based access control** for staff operations
3. **Database transactions** for data integrity
4. **Input validation** and sanitization
5. **Activity logging** for audit trails

## Next Steps (UREC System)

When ready to implement the UREC system:

1. **UREC Manager Interface** - Assign applications to UREC members
2. **UREC Member Dashboard** - Review assigned applications
3. **UREC Review Process** - Approve/reject with comments
4. **Certificate Generation** - Generate approval certificates

## Testing Checklist

- [ ] Exempt approval workflow
- [ ] Category determination accuracy
- [ ] PDF generation with remarks
- [ ] Category form completion
- [ ] Email notifications
- [ ] Token security
- [ ] Status transitions
- [ ] Database integrity

## Notes

- All PDFs are stored in `uploads/{queue_number}/` directory
- Category form templates are in `assets/to_send/for_reply_to_categories/`
- System uses existing email template system
- Maintains compatibility with existing QF01/QF02 workflow
- No UREC system dependencies in this phase
