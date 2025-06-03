=== Airtable Directory ===
Contributors: Drake Olejniczak
Tags: airtable, directory, staff, shortcode, custom directory, department pages, employee profiles
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.2
License: MIT

A comprehensive WordPress plugin to display staff directories using data from Airtable. Features department pages, employee profiles, hierarchy support, customizable field visibility, and department photo displays.

== Description ==

Airtable Directory is a powerful WordPress plugin that pulls employee and department data from an Airtable base and displays it both as shortcodes and dedicated directory pages with clean URLs.

**Key Features:**
- Fetches employee and department data from Airtable
- **NEW: Directory Pages** - Automatic `/directory/` pages with department and employee profiles
- **NEW: URL-friendly slugs** - Clean URLs like `/directory/human-resources/` and `/directory/john-smith/`
- **NEW: Department hierarchy support** - Parent/child department relationships with nested staff displays
- **NEW: Department photo support** - Display department photos alongside contact information
- **NEW: Multiple department support** - Display multiple departments in a single shortcode
- **NEW: Staff visibility control** - Public field allows department heads to control who appears in public directories
- Displays staff members in responsive card and table layouts
- Supports filtering by department using department IDs
- Allows custom selection of visible fields (name, title, department, email, phone, photo)
- Includes searchable staff directory with pagination
- Caching system for improved performance
- Admin interface for cache management and URL control

**Directory Structure:**
- **Main Directory:** `/directory/` - Browse all departments
- **Department Pages:** `/directory/{department-name}/` - View department info, staff, and sub-departments  
- **Employee Pages:** `/directory/{employee-name}/` - Individual employee profiles
- **Hierarchy Display:** Parent departments automatically show child department sections

== Installation ==

1. Upload the `airtable-directory` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Edit your `wp-config.php` file to securely store your Airtable API credentials:
   ```php
   define('AIRTABLE_API_KEY', 'your_airtable_api_key');
   define('AIRTABLE_BASE_ID', 'your_airtable_base_id');
   ```
4. **Important:** After activation, go to Settings > Airtable Directory and click "Refresh URL Rules" to enable directory pages.
5. Use the shortcodes or visit `/directory/` to access the new directory pages.

== Directory Pages ==

**Main Directory Page**
Visit `/directory/` to see all departments organized by hierarchy. Each department card shows:
- Department name (linked to department page)
- Staff count
- Child department count
- List of sub-departments

**Department Pages**
Each department gets its own page at `/directory/{department-slug}/` showing:
- Department contact information and details
- Staff members in that department
- Child departments with their own staff tables
- Breadcrumb navigation

**Employee Pages**
Each employee gets a profile page at `/directory/{employee-slug}/` featuring:
- Large profile photo
- Complete contact information
- Job title and department
- Professional details

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

Display Multiple Departments:
```
[department_details department="recXXXXXXXXXXXX,recYYYYYYYYYYYY"]
```
Shows details for multiple departments by providing comma-separated department IDs.

Control Displayed Fields:
```
[department_details department="recXXXXXXXXXXXX" show="name,photo,address,phone"]
```
Controls which fields are displayed. Available fields include:
- `name` - Department name
- `photo` - Department photo/building image
- `address` - Physical and mailing addresses
- `phone` - Phone number
- `fax` - Fax number
- `hours` - Business hours

Control Map Links:
```
[department_details department="recXXXXXXXXXXXX" show_map_link="no"]
```
Use `show_map_link="no"` to hide the "View on Map" links for addresses. Default is "yes".

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

== Department Hierarchy ==

The plugin automatically handles complex department structures:

- **Parent Departments:** Show overview, staff, and list of child departments
- **Child Departments:** Each gets its own section with department details and staff table
- **Multi-level Support:** Handles multiple levels of department nesting
- **Automatic Organization:** Department cards on main directory page group by parent/child relationships

Example hierarchy display on a parent department page:
1. Parent department details and contact info
2. Staff members directly in parent department
3. Child Department 1 details and staff table
4. Child Department 2 details and staff table
5. (Additional child departments as needed)

== Admin Management ==

**Settings Page: Settings > Airtable Directory**

- **Cache Management:** Clear data caches for specific tables or all data
- **Directory Cache:** Clear URL slug mappings and hierarchy caches  
- **URL Management:** Refresh rewrite rules if directory URLs aren't working
- **Usage Information:** View shortcode examples and directory URL structure

== Frequently Asked Questions ==

= How do I find my department IDs? =

You can find the record IDs in your Airtable interface. Open your base, navigate to the Departments table, and the ID is visible in the URL when you select a record, or you can use the Airtable API documentation to see all record IDs.

= How do department hierarchies work? =

In your Airtable Departments table, ensure you have a "Parent Department" field that links to other department records. The plugin automatically detects these relationships and displays child departments as separate sections on parent department pages.

= What if directory URLs aren't working? =

Go to Settings > Airtable Directory and click "Refresh URL Rules." This flushes WordPress rewrite rules and should resolve URL issues. If problems persist, deactivate and reactivate the plugin.

= How do I add department photos? =

Ensure your Airtable Departments table has a Photo field that stores attachments. The plugin will automatically pull the image URL and display photos in department cards, detail pages, and shortcodes when the 'photo' field is included in the 'show' parameter.

= Can I display multiple departments in one shortcode? =

Yes! You can display multiple departments using the department_details shortcode by providing comma-separated department IDs:
```
[department_details department="recDEPT1,recDEPT2,recDEPT3"]
```

= How do I control which department fields are shown? =

Use the 'show' parameter to specify which fields to display:
```
[department_details department="recXXXXXXXXXXXX" show="name,photo,address,phone"]
```
Available fields: name, photo, address, phone, fax, hours

= How do I add employee photos? =

Ensure your Airtable Staff table has a Photo field that stores attachments. The plugin will automatically pull the image URL and display photos in cards, tables, and employee profile pages.

= Can I customize the directory page styling? =

Yes! The plugin outputs semantic CSS classes that can be styled. Key classes include:
- `.airtable-directory-page` - Main container for directory pages
- `.directory-index` - Main directory page
- `.directory-department-page` - Department pages  
- `.directory-employee-page` - Employee profile pages
- `.department-card` - Department cards on index page
- `.employee-profile` - Employee profile container

= What happens if a field is missing? =

If an employee or department is missing data for a field, the plugin will gracefully handle it and not display empty fields.

= How do URL slugs get generated? =

The plugin automatically converts department and employee names into URL-friendly slugs by:
- Converting to lowercase
- Replacing spaces and special characters with hyphens
- Removing duplicate hyphens
- Handling duplicate names by appending numbers

= How do I control which staff members are visible on the website? =

Add a "Public" field (checkbox type) to your Airtable Staff table. Only staff members with this field checked will appear in:
- Staff directory shortcodes
- Department pages showing staff
- Individual employee profile pages
- Searchable staff directories

Staff members without the Public field checked (or with it unchecked) will be completely hidden from all public-facing directory displays. Additionally, non-public staff members will not have individual profile page URLs generated, ensuring they cannot be accessed even if someone tries to guess the URL pattern. This gives department heads full control over their staff's public visibility and ensures complete privacy for non-public staff.

= What happens if I don't have a Public field? =

If your Staff table doesn't have a Public field, all staff members will be treated as non-public and will not appear in any directory displays. You must add the Public field and check it for staff members you want to be publicly visible.

== Changelog ==

= 2.2 =
* **Major Feature:** Added complete directory page system with clean URLs
* **NEW:** Department pages showing staff and child departments with hierarchy support
* **NEW:** Individual employee profile pages with detailed information
* **NEW:** Main directory index page organizing all departments
* **NEW:** Automatic URL slug generation for departments and employees
* **NEW:** Breadcrumb navigation on directory pages
* **NEW:** Department photo support in shortcodes and directory pages
* **NEW:** Multiple department support in department_details shortcode (comma-separated IDs)
* **NEW:** Staff visibility control via Public field - only public staff appear in directories
* **NEW:** Enhanced admin interface with directory cache management and URL controls
* Enhanced department_details shortcode with photo field support
* Added support for displaying multiple departments in single shortcode
* Added Public field support for controlling staff visibility across all directory features
* Extended API class with department hierarchy and staff lookup methods
* Added comprehensive CSS styling for directory pages
* Improved cache management for directory-specific data
* Updated documentation with new features

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

= 2.2 =
Major update with new directory page system! After upgrading, go to Settings > Airtable Directory and click "Refresh URL Rules" to enable the new `/directory/` pages. All existing shortcodes remain fully compatible.

= 2.1 =
Important update with improved architecture and bug fixes. Compatible with existing shortcodes.

---
