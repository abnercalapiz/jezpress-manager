<?php
/**
 * Uninstall script for JezPress Manager
 *
 * Fired when the plugin is uninstalled.
 *
 * @package JezPress_Manager
 * @since   1.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Delete plugin version option.
delete_option( 'jezpress_manager_version' );

// Delete activation redirect transient (in case it exists during uninstall).
delete_transient( 'jezpress_manager_activation_redirect' );

// Note: Registered plugins are stored in static memory only, no cleanup needed.
