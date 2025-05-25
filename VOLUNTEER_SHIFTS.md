# Volunteer Shifts Feature

## Overview

The Volunteer Shifts feature allows event organizers to create and manage volunteer opportunities for their events. This feature includes:

- **Admin Interface**: Create and manage volunteer shifts from the WordPress admin
- **Database Storage**: Secure storage of volunteer signups with contact information
- **Frontend Display**: Show volunteer opportunities on event pages (to be implemented)
- **User Management**: Support for both logged-in users and guest signups

## Admin Features

### Creating Volunteer Shifts

1. Go to **Events** in your WordPress admin
2. Edit an existing event or create a new one
3. Find the **Volunteer Shifts** meta box below the event details
4. Click **"+ Add Volunteer Shift"** to create a new shift
5. Fill in the shift details:
   - **Shift Title**: e.g., "Event Setup Crew"
   - **Description**: What volunteers will be doing
   - **Shift Date**: When the shift takes place
   - **Start Time**: When the shift begins
   - **End Time**: When the shift ends
   - **Max Volunteers**: Leave empty for unlimited spots

### Managing Shifts

- **Remove Shifts**: Click the "Remove" button on any shift
- **View Signups**: See registered volunteers directly in the admin interface
- **Signup Counts**: View how many people have signed up for each shift

### Admin List View

The events list in the admin now includes a "Volunteer Shifts" column showing:
- Number of shifts available for each event
- Total number of volunteer signups

## Database Structure

The plugin creates a `wp_lcd_volunteer_signups` table with the following fields:

- `id`: Unique signup ID
- `event_id`: Associated event ID
- `shift_index`: Which shift (0, 1, 2, etc.)
- `shift_title`: Title of the shift at time of signup
- `volunteer_name`: Name of the volunteer
- `volunteer_email`: Email address
- `volunteer_phone`: Phone number (optional)
- `volunteer_notes`: Any notes from the volunteer
- `user_id`: WordPress user ID (if logged in)
- `signup_date`: When they signed up
- `status`: 'confirmed', 'cancelled', etc.

## PHP Functions Available

### `lcd_get_event_volunteer_shifts($event_id)`

Returns formatted array of all volunteer shifts for an event with:
- Shift details (title, description, times)
- Signup counts and availability
- Formatted date/time strings
- Full/available status

### `lcd_get_volunteer_signups($event_id)`

Returns all volunteer signups for an event.

### `lcd_get_shift_signup_count($event_id, $shift_index)`

Returns the number of confirmed signups for a specific shift.

## Frontend Implementation (To Do)

The frontend features will include:

1. **Display Section**: Show volunteer opportunities on single event pages
2. **Signup Modal**: Popup form for volunteers to sign up
3. **Contact Forms**: Collect volunteer information
4. **User Dashboard**: Let logged-in users see their volunteer commitments
5. **Guest Signups**: Allow non-registered users to volunteer

## Security Features

- WordPress nonces for form security
- Data sanitization and validation
- User capability checks
- SQL injection prevention with prepared statements

## Activation

The feature is automatically activated when the LCD Events plugin is activated. The database table is created during plugin activation.

## Styling

Admin styles are included in `/css/lcd-events.css` with responsive design for mobile devices. 