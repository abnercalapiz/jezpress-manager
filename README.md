# JezPress Manager

Central dashboard for managing all JezPress WordPress plugins, licenses, and support.

## Description

JezPress Manager provides a unified admin dashboard for all JezPress plugins. Instead of navigating to different settings pages scattered throughout the WordPress admin, access all your JezPress plugins from one convenient location.

### Features

- **Centralized Dashboard** - View all installed JezPress plugins in one place
- **License Management** - Monitor license status for all your plugins at a glance
- **Quick Support Access** - Easy access to documentation, support, and your account
- **Clean Interface** - Modern, responsive design that integrates with WordPress admin

## Requirements

- WordPress 6.0 or higher
- PHP 8.2 or higher

## Installation

1. Download the plugin zip file or clone this repository
2. Upload to `/wp-content/plugins/jezpress-manager/`
3. Activate the plugin through the WordPress Plugins menu
4. Access JezPress Manager from the admin sidebar

## Plugin Integration

Other JezPress plugins can register themselves with the manager using a simple API.

### Basic Registration

Add this code to your JezPress plugin:

```php
add_action( 'plugins_loaded', 'my_plugin_register_with_manager', 15 );

function my_plugin_register_with_manager() {
    // Only register if JezPress Manager is active
    if ( ! class_exists( 'JezPress_Manager' ) ) {
        return;
    }

    JezPress_Manager::register( array(
        'slug'       => 'my-plugin-settings',
        'name'       => 'My Plugin Name',
        'version'    => '1.0.0',
        'menu_title' => 'My Plugin',
        'capability' => 'manage_options',
        'callback'   => array( 'My_Plugin_Admin', 'render_settings_page' ),
    ) );
}
```

### Registration with License Status

```php
add_action( 'plugins_loaded', 'my_plugin_register_with_manager', 15 );

function my_plugin_register_with_manager() {
    if ( ! class_exists( 'JezPress_Manager' ) ) {
        return;
    }

    // Get license status from your license handler
    $license_status = '';
    $license_expiry = '';

    if ( class_exists( 'My_Plugin_License' ) ) {
        $license = My_Plugin_License::instance();
        $license_status = $license->is_valid() ? 'active' : 'invalid';
        $license_expiry = $license->get_expiry_date(); // e.g., '2025-12-31'
    }

    JezPress_Manager::register( array(
        'slug'           => 'my-plugin-settings',
        'name'           => 'My Plugin Name',
        'version'        => MY_PLUGIN_VERSION,
        'menu_title'     => 'My Plugin',
        'capability'     => 'manage_options',
        'callback'       => array( 'My_Plugin_Admin', 'render_settings_page' ),
        'license_status' => $license_status,
        'license_expiry' => $license_expiry,
        'description'    => 'Short description of what the plugin does.',
    ) );
}
```

### Registration Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `slug` | string | Yes | Unique identifier for the plugin (used in menu URL) |
| `name` | string | Yes | Full plugin name displayed in dashboard |
| `version` | string | Yes | Plugin version number |
| `menu_title` | string | Yes | Short title for the submenu |
| `capability` | string | No | Required capability (default: `manage_options`) |
| `callback` | callable | No | Settings page render callback |
| `license_status` | string | No | `active`, `expired`, `invalid`, or empty |
| `license_expiry` | string | No | Expiry date string |
| `description` | string | No | Short plugin description |
| `icon` | string | No | Dashicon class (default: `dashicons-admin-plugins`) |
| `position` | int | No | Menu order - lower number appears first (default: `10`) |

### License Status Values

| Value | Display | Description |
|-------|---------|-------------|
| `active` | Green badge | License is valid and active |
| `expired` | Yellow badge + Renew link | License has expired |
| `invalid` | Red badge | License key is invalid |
| (empty) | Gray "Not Set" | No license configured |

## Menu Structure

Once activated, you'll see:

```
JezPress Manager
├── Dashboard       ← Shows all plugins + licenses + support info
├── Plugin A        ← First registered plugin's settings
├── Plugin B        ← Second registered plugin's settings
└── ...
```

## Helper Functions

### Check if Manager is Active

```php
// Method 1: Using constant (fastest)
if ( defined( 'JEZPRESS_MANAGER_ACTIVE' ) && JEZPRESS_MANAGER_ACTIVE ) {
    // Manager is available
}

// Method 2: Using class check
if ( class_exists( 'JezPress_Manager' ) ) {
    // Manager is available
}
```

### Get All Registered Plugins

```php
$plugins = JezPress_Manager::get_plugins();
```

### Deregister a Plugin

```php
JezPress_Manager::deregister( 'my-plugin-slug' );
```

## Hooks

### Actions

None currently.

### Filters

None currently.

## Changelog

### 1.1.0
- Added JezPress updater for automatic updates from updates.jezpress.com
- Plugin now receives automatic updates through the JezPress platform

### 1.0.0
- Initial release
- Central dashboard for JezPress plugins
- License status display
- Support and quick links section
- Plugin registration API

## License

GPL-2.0+ - See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

## Support

- **Email:** jez@jezpress.com
