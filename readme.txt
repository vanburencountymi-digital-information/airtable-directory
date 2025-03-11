=== Airtable Directory ===
Contributors: yourname
Tags: airtable, directory, staff, shortcode, custom directory
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A custom WordPress plugin to display staff directories using data from Airtable. Supports filtering by department and customizable field visibility.

== Description ==

Airtable Directory is a lightweight WordPress plugin that pulls employee data from an Airtable base and displays it as a directory using a shortcode.

**Features:**
- Fetches employee data from Airtable.
- Displays staff members in a list format.
- Supports filtering by department.
- Allows custom selection of visible fields (name, title, department, email, phone, photo).
- Uses a shortcode for easy integration into pages and posts.

== Installation ==

1. Upload the `airtable-directory` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Edit your `wp-config.php` file to securely store your Airtable API credentials:
   ```php
   define('AIRTABLE_API_KEY', 'your_airtable_api_key');
   define('AIRTABLE_BASE_ID', 'your_airtable_base_id');
   define('AIRTABLE_TABLE_NAME', 'Staff'); // Update with your actual table name
   ```
4. Use the shortcode [staff_directory] to display the directory.
5. Optionally, use shortcode attributes to customize the output.

== Shortcodes ==

**Basic Usage**

[staff_directory]
```
Displays the full staff directory.

Filter by Department
csharp
Copy
Edit
[staff_directory department="Finance"]
Only shows employees in the Finance department.

Control Displayed Fields
csharp
Copy
Edit
[staff_directory department="HR" show="name,title,email"]
Only displays Name, Title, and Email for HR employees.

== Frequently Asked Questions ==

= How do I add employee photos? = Ensure your Airtable table has a Photo field that stores image URLs. The plugin will automatically pull the first image.

= What happens if a field is missing? = If an employee is missing an email or phone number, the plugin will gracefully handle it and not display an empty field.

= Can I style the output? = Yes! The plugin outputs a div.staff-directory and ul.staff-list that can be styled using CSS.

== Changelog ==

= 1.0 =

Initial release.
Fetches employee data from Airtable.
Supports department filtering and dynamic field visibility.
== Upgrade Notice ==

= 1.0 = First release - safe to install.