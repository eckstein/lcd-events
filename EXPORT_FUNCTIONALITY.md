# Export Functionality for Volunteer Lists

## Overview

The LCD Events plugin now includes export functionality for volunteer lists, allowing administrators to export volunteer information in both CSV and PDF formats.

## Features

### Export Formats
- **CSV Export**: Exports volunteer data in comma-separated values format, perfect for spreadsheet applications
- **PDF Export**: Exports volunteer data in a formatted PDF document, ideal for printing or sharing

### Available Export Locations
1. **Single Event Edit Page**: Export buttons appear in the volunteer shifts meta box when volunteers are registered
2. **Volunteer Shifts Overview Page**: Export buttons appear for each event with registered volunteers

## Data Included in Exports

### CSV Export Includes:
- Volunteer Name
- Email Address
- Phone Number
- Shift Title
- Shift Date
- Shift Start Time
- Shift End Time
- Notes/Comments
- Signup Date
- Status

### PDF Export Includes:
- Event information (title, date, time, location)
- Volunteers organized by shift
- Shift details (date, time, description)
- Volunteer contact information
- Notes for each volunteer
- Total volunteer count
- Generation timestamp

## Usage

### From Single Event Edit Page
1. Navigate to Events → Edit Event
2. Scroll to the "Volunteer Shifts" meta box
3. If volunteers are registered, you'll see an export section at the top
4. Click "Export to CSV" or "Export to PDF" to download the file

### From Volunteer Shifts Overview Page
1. Navigate to Events → Volunteer Shifts
2. For each event with volunteers, export buttons appear in the event header
3. Click "Export CSV" or "Export PDF" to download the file

## File Naming Convention

Exported files are automatically named using the following pattern:
- `{Event Title}_volunteers_{Date}.csv`
- `{Event Title}_volunteers_{Date}.pdf`

Example: `Annual_Fundraiser_volunteers_2024-01-15.csv`

## Technical Implementation

### CSV Export
- Uses PHP's built-in `fputcsv()` function
- Includes UTF-8 BOM for proper character encoding
- Automatically sanitizes file names
- Fetches latest volunteer information from linked person profiles

### PDF Export
- Generates HTML content and serves it as PDF
- Attempts to use `wkhtmltopdf` if available on the server
- Falls back to HTML with PDF headers for browser-based PDF generation
- Includes professional formatting with tables and styling

## Permissions

- Export functionality requires `edit_posts` capability
- Only administrators and editors can access export features
- Export actions are protected with WordPress nonces for security

## Browser Compatibility

The export functionality works with all modern browsers:
- Chrome/Chromium
- Firefox
- Safari
- Edge

## Troubleshooting

### PDF Export Issues
If PDF export doesn't work properly:
1. Check if `wkhtmltopdf` is installed on your server
2. Verify file permissions in the temporary directory
3. The system will fall back to HTML format if PDF generation fails

### CSV Export Issues
If CSV files appear corrupted:
1. Ensure your spreadsheet application supports UTF-8 encoding
2. Try opening the file in a text editor first to verify content
3. Check that special characters are properly encoded

## Security Considerations

- All export requests are validated for proper user permissions
- Event IDs are sanitized and validated
- File names are sanitized to prevent directory traversal
- Nonces are used to prevent CSRF attacks 