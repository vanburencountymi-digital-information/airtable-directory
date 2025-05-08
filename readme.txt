=== Airtable Directory ===
Contributors: yourname
Tags: airtable, directory, staff, shortcode, custom directory
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A custom WordPress plugin to display staff directories using data from Airtable. Supports filtering by department and customizable field visibility.

== Description ==

Airtable Directory is a lightweight WordPress plugin that pulls employee data from an Airtable base and displays it as a directory using shortcodes.

**Features:**
- Fetches employee and department data from Airtable
- Displays staff members in a responsive card layout
- Supports filtering by department using department IDs
- Allows custom selection of visible fields (name, title, department, email, phone, photo)
- Includes department details display with separate shortcode
- Caching system for improved performance
- Admin interface for clearing the cache

== Installation ==

1. Upload the `airtable-directory` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Edit your `wp-config.php` file to securely store your Airtable API credentials:
   ```php
   define('AIRTABLE_API_KEY', 'your_airtable_api_key');
   define('AIRTABLE_BASE_ID', 'your_airtable_base_id');
   ```
4. Use the shortcodes `[staff_directory]` and `[department_details]` to display the content.
5. You can clear the cache through Settings > Airtable Directory in the admin dashboard.

== Shortcodes ==

**Staff Directory**

Basic Usage:
```
[staff_directory]
```
Displays the full staff directory.

Filter by Department (using Department ID):
```
[staff_directory department="recXXXXXXXXXXXX"]
```
Only shows employees in the specified department. Note: Use the record ID from Airtable, not the department name.

Control Displayed Fields:
```
[staff_directory department="recXXXXXXXXXXXX" show="name,title,email"]
```
Only displays Name, Title, and Email for employees in the specified department.

**Department Details**

Display department information:
```
[department_details department="recXXXXXXXXXXXX"]
```
Shows details for the specified department.

Control Displayed Fields:
```
[department_details department="recXXXXXXXXXXXX" show="name,address,phone"]
```
Only displays Name, Address, and Phone for the specified department.

**Searchable Staff Directory**

Create a searchable staff directory with pagination:
```
[searchable_staff_directory]
```

Options:
```
[searchable_staff_directory per_page="10" show="name,title,department,email,phone,photo" default_view="table"]
```
- `per_page`: Number of staff members to show per page (default: 20)
- `show`: Fields to display (default: name,title,department,email,phone,photo)
- `default_view`: Initial view to show (card or table, default: card)

== Frequently Asked Questions ==

= How do I find my department IDs? =

You can find the record IDs in your Airtable interface. Open your base, navigate to the Departments table, and the ID is visible in the URL when you select a record, or you can use the Airtable API documentation to see all record IDs.

= How do I add employee photos? =

Ensure your Airtable Staff table has a Photo field that stores attachments. The plugin will automatically pull the image URL.

= What happens if a field is missing? =

If an employee is missing data for a field, the plugin will gracefully handle it and not display an empty field.

= Can I style the output? =

Yes! The plugin outputs with semantic class names that can be styled using CSS:**Searchable Staff Directory**

Create a searchable staff directory with pagination:
```
[searchable_staff_directory]
```

Options:
```
[searchable_staff_directory per_page="10" show="name,title,department,email,phone,photo"]
```
- `per_page`: Number of staff members to show per page (default: 20)
- `show`: Fields to display (default: name,title,department,email,phone,photo)
- `.staff-directory` - Container for the directory
- `.staff-card` - Individual staff member card
- `.staff-photo-container` - Photo wrapper
- `.staff-info` - Text information
- `.department-details` - Department information container

== Changelog ==

= 2.1 =
* Refactored plugin into a modular, class-based structure
* Fixed constant redefinition issues when using constants in wp-config.php
* Improved photo display with support for various Airtable image formats
* Added admin interface for cache management
* Fixed various bugs and improved error handling

= 2.0 =
* Added support for separate Departments and Staff tables
* Implemented new department_details shortcode
* Added caching system for API requests
* Improved error handling and logging

= 1.0 =
* Initial release
* Fetches employee data from Airtable
* Supports department filtering and dynamic field visibility

== Upgrade Notice ==

= 2.1 =
Important update with improved architecture and bug fixes. Compatible with existing shortcodes.