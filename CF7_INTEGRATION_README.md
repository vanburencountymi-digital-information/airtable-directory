# Contact Forms 7 Integration for Airtable Directory

This integration adds Contact Forms 7 support to the Airtable Directory plugin, allowing visitors to contact employees and departments through forms instead of exposing email addresses directly.

## Features

- **Context-aware forms**: Forms automatically route to the correct employee or department based on the page context
- **No email exposure**: Email addresses are never exposed in HTML, only resolved server-side
- **Rate limiting**: Built-in rate limiting to prevent spam
- **Flamingo support**: Includes audit headers for Flamingo plugin integration
- **Security**: Uses WordPress nonces for form validation

## Setup

### 1. Create a Contact Form 7 Form

1. Go to **Contact > Contact Forms** in your WordPress admin
2. Create a new form with fields like:
   - Name: `[text* your-name]`
   - Email: `[email* your-email]`
   - Subject: `[text* your-subject]`
   - Message: `[textarea* your-message]`
3. Note the form ID (shown in the form list) - this will be an alphanumeric ID like `a46061f`

### 2. Configure the Integration

1. Go to **Airtable Directory > Display Settings**
2. Scroll to the **Contact Forms 7 Integration** section
3. Enter your CF7 form ID
4. Configure rate limiting (optional):
   - **Max submissions**: Number of submissions allowed per IP
   - **Time window**: Time period in seconds for rate limiting

### 3. Ensure Department Email Fields

Make sure your Airtable Departments table has an `Email` field for department contact forms to work properly.

## How It Works

### Employee Pages
When a visitor views an employee page, they'll see a contact form that automatically routes to that employee's email address.

### Department Pages
When a visitor views a department page, they'll see a contact form that automatically routes to the department's email address.

### Form Processing
1. Form submission includes context (employee/department ID) via hidden fields
2. Server-side validation using WordPress nonces
3. Rate limiting check by IP address
4. Email address resolution from Airtable data
5. Email routing with context-aware subject lines
6. Reply-To header set to sender's email

## Security Features

- **Nonce validation**: Prevents CSRF attacks
- **Rate limiting**: Prevents spam submissions
- **Server-side email resolution**: No email addresses in HTML
- **Input sanitization**: All form data is sanitized
- **Context validation**: Ensures forms are submitted from valid pages

## Customization

### Styling
The integration includes CSS classes for styling:
- `.employee-contact-form` / `.department-contact-form`
- `.vbc-contacting-badge`
- Standard CF7 classes (`.wpcf7-form`, etc.)

### Email Headers
The integration adds these headers to outgoing emails:
- `X-Airtable-Context-Type`: 'employee' or 'department'
- `X-Airtable-Context-ID`: The entity ID
- `X-Page-ID`: WordPress page ID

### Subject Line Format
Emails are sent with subjects like:
- `Website contact → John Doe: [Original Subject]`
- `Website contact → Department Name: [Original Subject]`

## Troubleshooting

### Form Not Appearing
- Check that CF7 form ID is configured in settings (use the alphanumeric ID like `a46061f`)
- Ensure Contact Forms 7 plugin is active
- Verify the form ID exists and is published

### Emails Not Sending
- Check that employee/department has an email address in Airtable
- Verify CF7 mail settings are configured
- Check server logs for any errors

### Rate Limiting Issues
- Adjust rate limit settings in admin
- Clear transients if needed: `wp transient delete airdir_rl_*`

## API Reference

### CF7 Integration Class
```php
// Render contact form for employee
echo $cf7->render_contact_block('employee', $employee_id, $employee_name);

// Render contact form for department
echo $cf7->render_contact_block('department', $department_name, $department_name);
```

### Settings Options
- `airdir_cf7_form_id`: CF7 form ID
- `airdir_cf7_rate_limit_max`: Maximum submissions per window
- `airdir_cf7_rate_limit_seconds`: Time window in seconds 