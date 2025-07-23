=== Airtable Directory ===
Contributors: Drake Olejniczak
Tags: airtable, directory, staff, shortcode, custom directory, department pages, employee profiles
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.5
License: MIT

A comprehensive WordPress plugin to display staff directories using data from Airtable. Features department pages, employee profiles, hierarchy support, customizable field visibility, and department photo displays.

== Description ==

Airtable Directory is a powerful WordPress plugin that pulls employee and department data from an Airtable base and displays it both as shortcodes and dedicated directory pages with clean URLs.

**Key Features:**
- Fetches employee and department data from Airtable
- **NEW: Boards & Commissions Support** - Display boards, committees, and board members from Airtable
- **NEW: Directory Pages** - Automatic `/directory/` pages with department and employee profiles
- **NEW: URL-friendly slugs** - Clean URLs like `/directory/human-resources/` and `/directory/john-smith/`
- **NEW: Department hierarchy support** - Parent/child department relationships with nested staff displays
- **NEW: Department photo support** - Display department photos alongside contact information
- **NEW: Multiple department support** - Display multiple departments in a single shortcode
- **NEW: Staff visibility control** - Public field allows department heads to control who appears in public directories
- **NEW: Featured staff** - Staff with the 'Featured' field checked are highlighted as cards in department details
- **NEW: Table or card view for staff directory** - Choose between a minimal table or card layout for staff directories
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
Displays the full staff directory as a table (default).

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

**Choose Table or Card View:**
```
[staff_directory department="recXXXXXXXXXXXX" view="card"]
[staff_directory department="recXXXXXXXXXXXX" view="table"]
```
- `view`: Set to `table` (default) for a minimal table, or `card` for card-style layout.

**Department Details**

Display department information:
```
[department_details department="recXXXXXXXXXXXX"]
```
Shows details for the specified department, including staff members.

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

Set view:
- `card` - Card style view
- `table` - No extra class on department-details

Control Map Links:
```
[department_details department="recXXXXXXXXXXXX" show_map_link="no"]
```
Use `show_map_link="no"` to hide the "View on Map" links for addresses. Default is "yes".

**Show or Hide Staff in Department Details:**
```
[department_details department="recXXXXXXXXXXXX" show_staff="false"]
```
- `show_staff`: Set to `false` to hide staff members. Default is `true` (shows staff).
- When shown, staff are split into two groups:
  - **Featured staff** (with the 'Featured' field checked in Airtable) are displayed as cards with photo, name, and title.
  - **Regular staff** are displayed as simple blocks with name and title.

**Featured Staff**
- Add a `Featured` checkbox field to your Staff table in Airtable.
- Staff with this checked will appear as cards in department details; others will appear as simple blocks.

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

**Department Footer**

Display department information in a clean footer layout:
```
[department_footer]
```

This shortcode automatically detects the current page's department_id and displays department information in a footer-style layout. If no department is found, it defaults to the administration building.

**Multiple Department Support:**
The shortcode supports multiple departments (comma-separated IDs) and displays them in a responsive column layout:
```
[department_footer department="5,8,12"]
```

Advanced Usage:
```
[department_footer department="5" show="name,address,phone" show_map_link="yes" show_staff="false"]
```

Options:
- `department`: Specific department ID(s) to display (if empty, uses page's department_id or default)
- `show`: Comma-separated list of fields to display (default: name,photo,address,phone,fax,hours)
- `show_map_link`: Whether to show Google Maps links for addresses (default: yes)
- `show_staff`: Whether to show staff members (default: true)
- `default_department`: Department ID to use as fallback (default: 1 for Administration Building)

Examples:
```
[department_footer show="name,phone,fax,hours"]
[department_footer show_staff="false"]
[department_footer department="3" show="name,address,phone"]
[department_footer show_map_link="no"]
[department_footer department="5,8,12" show="name,phone,hours"]
```

**Fallback Behavior:**
1. If a `department` parameter is provided, it uses that department ID(s)
2. If no department parameter is provided, it looks for a `department_id` custom field on the current page
3. If no department_id is found on the page, it uses the `default_department` (defaults to "1" for Administration Building)
4. If the department is not found in Airtable, it skips that department and continues with others

**Multiple Department Layout:**
When multiple departments are specified, they are displayed in a responsive grid layout:
- Desktop: Departments appear in columns (auto-fit grid)
- Mobile: Departments stack vertically
- Each department gets its own card with contact information
- Visual separators between departments for clarity

Perfect for use in:
- Page footers
- Sidebar widgets  
- Contact information sections
- Department landing pages

== Boards & Commissions Shortcodes ==

**Boards Directory**

Display all boards and committees:
```
[boards_directory]
```

Filter by specific board:
```
[boards_directory board="recXXXXXXXXXXXX"]
```

Control displayed fields:
```
[boards_directory show="name,logo,contact_info,meeting_location,meeting_time,member_count"]
```

Choose view type:
```
[boards_directory view="table"]
[boards_directory view="card"]
```

Available fields:
- `name` - Board name
- `logo` - Board logo/image
- `contact_info` - Contact information
- `meeting_location` - Meeting location
- `meeting_time` - Meeting time
- `member_count` - Number of board members

**Board Members**

Display board members for a specific board (using ID number or record ID):
```
[board_members board="1"]
[board_members board="recXXXXXXXXXXXX"]
```

Control displayed fields:
```
[board_members board="1" show="name,role,representative_type,notes"]
```

Choose view type:
```
[board_members board="1" view="table"]
[board_members board="1" view="card"]
```

Available fields:
- `name` - Member name
- `role` - Role on board
- `representative_type` - Representative type
- `notes` - Additional notes

**Display Order:**
Board members are automatically sorted by their "Display Order" field (lower numbers appear first). Members without a display order value appear last, sorted alphabetically by name.

**Board Details**

Display detailed information about a specific board (using ID number or record ID):
```
[board_details board="1"]
[board_details board="recXXXXXXXXXXXX"]
```

Control displayed fields:
```
[board_details board="1" show="name,logo,contact_info,meeting_location,meeting_time,members"]
```

Choose view type:
```
[board_details board="1" view="card"]
[board_details board="1" view="table"]
```

Control member display:
```
[board_details board="1" show_members="false"]
```

Available fields:
- `name` - Board name
- `logo` - Board logo/image
- `contact_info` - Contact information
- `meeting_location` - Meeting location
- `meeting_time` - Meeting time
- `members` - Board members list

**Board Member Display Options:**
- When `show_members="true"` (default), board members are displayed as cards with photos, names, roles, and contact information
- When `view="table"`, board members are listed in a simple table format
- Board member photos are displayed as circular thumbnails
- Contact information (email/phone) is automatically linked for easy access

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

= How do I use the department footer shortcode? =

The `[department_footer]` shortcode is perfect for displaying department contact information in page footers or sidebars. It automatically detects the current page's department_id and provides a clean footer layout.

Basic usage:
```
[department_footer]
```

The shortcode will:
1. Look for a `department_id` in the current page's custom fields
2. If no department_id is found, use the default department (ID: 1 - Administration Building)
3. Display the department information in a footer-style layout

You can customize what's displayed:
```
[department_footer show="name,phone,fax,hours" show_staff="false"]
```

This is ideal for:
- Page footers showing contact information
- Sidebar widgets with department details
- Contact sections on department pages
- Any location where you want consistent department information display

= How do I find my board IDs? =

You can use either the simple ID number or the Airtable record ID:

**Simple ID (Recommended):**
- Open your Airtable base and navigate to the Boards & Committees table
- Look at the "ID" column (auto-incrementing number)
- Use this number in your shortcodes: `[board_details board="1"]`

**Record ID:**
- The record ID is visible in the URL when you select a record in Airtable
- Or you can use the Airtable API documentation to see all record IDs
- Use the full record ID: `[board_details board="recXXXXXXXXXXXX"]`

The plugin automatically detects whether you're using a simple ID number or a record ID, so both formats work seamlessly.

= How do I add board logos? =

Ensure your Airtable Boards & Committees table has a Logo field that stores attachments. The plugin will automatically pull the image URL and display logos in board cards, detail pages, and shortcodes when the 'logo' field is included in the 'show' parameter.

= How do I add board member photos? =

Ensure your Airtable Board Members table has a Photo field that stores attachments. The plugin will automatically pull the image URL and display photos in cards, tables, and board member displays.

= Can I display multiple boards in one shortcode? =

Currently, the boards directory shortcode displays all boards when no specific board is specified. For more granular control, you can use individual board_details shortcodes for specific boards.

= How do I control which board fields are shown? =

Use the 'show' parameter to specify which fields to display:
```
[boards_directory show="name,logo,contact_info,meeting_location"]
```
Available fields: name, logo, contact_info, meeting_location, meeting_time, member_count

= What happens if a board field is missing? =

If a board or board member is missing data for a field, the plugin will gracefully handle it and not display empty fields.

= How do I control the order of board members? =

Add a "Display Order" field (number type) to your Board Members table in Airtable. Set lower numbers for members you want to appear first:
- Chair/Chairperson: 1
- Vice-Chair: 2  
- Secretary: 3
- Treasurer: 4
- Regular members: 10, 20, 30, etc.
- Members without a display order value will appear last, sorted alphabetically

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

= 2.3 =
* **NEW:** Staff directory shortcode supports a 'view' attribute for table (default) or card layouts
* **NEW:** Department details shortcode supports a 'show_staff' attribute (default true) to show/hide staff
* **NEW:** Featured staff support in department details (displayed as cards)
* Enhanced regular staff display in department details for clarity and style

= 2.4 =
* **NEW:** Department footer shortcode for displaying department information in footer layouts
* **NEW:** Automatic department detection from page custom fields with fallback to administration building
* **NEW:** Clean footer styling with responsive design for mobile devices
* Enhanced shortcode documentation with comprehensive examples and usage patterns

= 2.5 =
* **NEW:** Boards & Commissions support with three new shortcodes
* **NEW:** `[boards_directory]` shortcode for displaying all boards and committees
* **NEW:** `[board_members]` shortcode for displaying board members with table/card views
* **NEW:** `[board_details]` shortcode for detailed board information with member lists
* **NEW:** Support for board logos, contact information, meeting details, and member photos
* **NEW:** Responsive CSS styling for all board components
* **NEW:** Table and card view options for all board shortcodes
* Enhanced API class with board-specific methods for data retrieval
* Updated documentation with comprehensive boards & commissions examples

== Upgrade Notice ==

= 2.2 =
Major update with new directory page system! After upgrading, go to Settings > Airtable Directory and click "Refresh URL Rules" to enable the new `/directory/` pages. All existing shortcodes remain fully compatible.

= 2.1 =
Important update with improved architecture and bug fixes. Compatible with existing shortcodes.

---
