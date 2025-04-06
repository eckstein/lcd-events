# LCD Events

A custom WordPress plugin for managing events for the Lewis County Democrats website.

## Description

LCD Events is a lightweight event management plugin that provides a custom post type for creating and managing events. It includes custom fields for event details such as date, time, location, registration information, and more.

## Features

- Custom "Events" post type
- Event meta fields: date, time, location, address, registration info, and more
- Custom admin columns for easy event management
- Custom templates for displaying events on the front-end
- Sorting events by date in admin and front-end
- Separate display for upcoming and past events
- Responsive design for event listings and single event pages
- Shortcode for embedding events on any page

## Installation

1. Upload the `lcd-events` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Start creating events using the new "Events" menu item in the WordPress admin
4. Visit the Events page at `/events/` to see your events

## Usage

### Creating a New Event

1. Navigate to "Events" > "Add New" in the WordPress admin
2. Enter the event title and description in the main editor
3. Fill in the event details in the "Event Details" meta box:
   - Event Date
   - Start Time
   - End Time
   - Location Name
   - Address
   - Map Link
   - Registration URL
   - Registration Button Text
   - Ticketing Notes
   - Capacity
   - Event Cost
   - Event Poster
4. Set a featured image for the event (optional)
5. Publish the event

### Templates

The plugin includes custom templates for displaying events:

- `archive-event.php` - Displays a listing of events, with upcoming events shown in a grid and past events in a simple list
- `single-event.php` - Displays a single event with all details

These templates will automatically be used when viewing event pages.

### Shortcode

You can use the `[lcd_events]` shortcode to display a list of upcoming events on any page or post.

#### Shortcode Options

- `limit` - Number of events to display (default: 3)
- `show_past` - Whether to show past events instead of upcoming events (default: false)
- `show_title` - Whether to show the title above the events list (default: true)
- `title` - The title to display above the events list (default: "Upcoming Events")

#### Examples

Display 3 upcoming events:
```
[lcd_events]
```

Display 5 upcoming events:
```
[lcd_events limit="5"]
```

Display 3 past events:
```
[lcd_events show_past="true" title="Past Events"]
```

Display events with no title:
```
[lcd_events show_title="false"]
```

## Customization

To override the default templates:

1. Create a folder called `lcd-events` in your theme directory
2. Create a `templates` folder inside the `lcd-events` folder
3. Copy the template files from the plugin's `templates` directory into your theme's `lcd-events/templates` directory
4. Modify the templates as needed

## Troubleshooting

If you experience 404 errors when trying to access the events archive page (/events/), try these steps:

1. Go to Settings > Permalinks in your WordPress admin
2. Click "Save Changes" without making any changes (this flushes rewrite rules)
3. Try accessing the events page again

## Credits

Developed for the Lewis County Democrats website by LCD Web Team.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher

## License

GPL v2 or later 