# Volunteer Opportunities Page

This document describes the new volunteer opportunities page template that displays upcoming volunteer shifts from all events in a unified view.

## Overview

The volunteer opportunities page provides a centralized location where visitors can:
- View all upcoming volunteer opportunities across all events
- See shift details including date, time, description, and capacity
- Sign up for available volunteer shifts (functionality to be implemented)
- Navigate to event details for more information

## Setup

### Option 1: Create a Page with Slug "volunteer-opportunities"

1. Go to **Pages > Add New** in your WordPress admin
2. Set the page title to "Volunteer Opportunities" (or any title you prefer)
3. Set the page slug to `volunteer-opportunities`
4. Publish the page
5. The template will automatically be applied

### Option 2: Assign Template to Any Page

1. Create or edit any page
2. In the Page Attributes meta box, select "Volunteer Opportunities" from the Template dropdown
3. Save/publish the page

## Features

### Automatic Content Generation

The page automatically:
- Queries all upcoming events that have volunteer shifts
- Filters out past shifts (based on date and time)
- Sorts shifts chronologically
- Displays volunteer capacity and availability status

### Visual Indicators

- **Available shifts**: Green left border and "Available" badge
- **Full shifts**: Red left border and "Full" badge
- **Statistics**: Shows total opportunities and currently available spots

### Responsive Design

The page is fully responsive and includes:
- Mobile-optimized layout
- Touch-friendly buttons
- Accessible keyboard navigation
- Smooth hover effects

## Template Structure

### Header Section
- Eye-catching gradient background
- Page title and description
- Volunteer icon

### Statistics Section
- Total opportunities count
- Available opportunities count
- Clean grid layout

### Shifts Container
- Individual cards for each volunteer shift
- Shift details (event, date, time, description, capacity)
- Action buttons (Sign Up, Event Details)

### Empty State
- Friendly message when no opportunities are available
- Link to events archive

## Styling

The page uses the existing LCD Events CSS framework with new classes:

### Main Classes
- `.volunteer-opportunities` - Main page container
- `.volunteer-shift-card` - Individual shift cards
- `.shift-available` / `.shift-full` - Availability states
- `.volunteer-signup-btn` - Sign-up buttons

### Responsive Breakpoints
- Desktop: Full grid layout with hover effects
- Tablet: Adjusted spacing and button sizes
- Mobile: Stacked layout with full-width buttons

## JavaScript Functionality

### Current Features
- Button click handling with loading states
- Hover effects and accessibility features
- Smooth scrolling for internal links
- Keyboard navigation support

### Placeholder for Future Features
- AJAX volunteer sign-up
- Real-time capacity updates
- Form validation
- Success/error messaging

## Data Structure

The page uses the existing `lcd_get_event_volunteer_shifts()` function to retrieve:

```php
Array(
    'index' => 0,
    'title' => 'Setup Crew',
    'description' => 'Help set up tables and chairs',
    'date' => '2024-01-15',
    'start_time' => '09:00:00',
    'end_time' => '11:00:00',
    'max_volunteers' => 5,
    'signup_count' => 2,
    'spots_remaining' => 3,
    'is_full' => false,
    'event_id' => 123,
    'event_title' => 'Community Meeting',
    'event_permalink' => 'https://example.com/events/community-meeting'
)
```

## Customization

### Modifying the Template

The template file is located at:
```
/plugins/lcd-events/templates/page-volunteer-opportunities.php
```

### Adding Custom Styles

Add custom CSS to your theme or use the WordPress Customizer:

```css
.volunteer-opportunities .custom-section {
    /* Your custom styles */
}
```

### Filtering Content

Use WordPress hooks to modify the content:

```php
// Example: Filter volunteer shifts before display
add_filter('lcd_volunteer_opportunities_shifts', function($shifts) {
    // Modify $shifts array
    return $shifts;
});
```

## Future Enhancements

### Planned Features
1. **AJAX Sign-up Form**: Modal or inline form for volunteer registration
2. **User Authentication**: Integration with WordPress user system
3. **Email Notifications**: Automatic confirmations and reminders
4. **Calendar Integration**: Export shifts to personal calendars
5. **Filtering Options**: Filter by date, event type, or location
6. **Search Functionality**: Search shifts by keywords
7. **Volunteer Profiles**: User dashboard for managing sign-ups

### Technical Improvements
1. **Caching**: Implement caching for better performance
2. **Real-time Updates**: WebSocket or polling for live capacity updates
3. **Analytics**: Track sign-up conversion rates
4. **A/B Testing**: Test different layouts and messaging

## Troubleshooting

### Template Not Loading
1. Check that the page slug is exactly `volunteer-opportunities`
2. Verify the template file exists in the correct location
3. Clear any caching plugins
4. Check for PHP errors in the error log

### No Volunteer Opportunities Showing
1. Ensure events have volunteer shifts configured
2. Check that event dates are in the future
3. Verify volunteer shifts have valid dates and times

### Styling Issues
1. Check for theme CSS conflicts
2. Ensure the LCD Events CSS is loading
3. Clear browser cache
4. Check for JavaScript errors in browser console

## Support

For technical support or feature requests related to the volunteer opportunities page, please refer to the main LCD Events plugin documentation or contact the development team. 