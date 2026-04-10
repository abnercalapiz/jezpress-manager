<?php
/**
 * JezPress Manager main class
 *
 * Provides a central dashboard for all JezPress plugins with
 * plugin listing, license status, and support information.
 *
 * @package JezPress_Manager
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main JezPress Manager class
 */
class JezPress_Manager {

	/**
	 * Menu slug constant
	 *
	 * @var string
	 */
	const MENU_SLUG = 'jezpress-manager';

	/**
	 * Single instance of the class
	 *
	 * @var JezPress_Manager|null
	 */
	private static $instance = null;

	/**
	 * Registered plugins array
	 *
	 * @var array
	 */
	private static $plugins = array();

	/**
	 * Get the singleton instance
	 *
	 * @return JezPress_Manager
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_dashboard' ) );
	}

	/**
	 * Register a plugin with JezPress Manager
	 *
	 * Called by other JezPress plugins to register themselves.
	 *
	 * @param array $args {
	 *     Plugin registration arguments.
	 *
	 *     @type string   $slug           Unique plugin slug.
	 *     @type string   $name           Plugin display name.
	 *     @type string   $version        Plugin version.
	 *     @type string   $menu_title     Submenu title.
	 *     @type string   $capability     Required capability. Default 'manage_options'.
	 *     @type callable $callback       Settings page callback function.
	 *     @type string   $license_status License status: 'active', 'expired', 'invalid', or empty.
	 *     @type string   $license_expiry License expiry date (optional).
	 *     @type string   $description    Short plugin description (optional).
	 * }
	 */
	public static function register( $args ) {
		$defaults = array(
			'slug'           => '',
			'name'           => '',
			'version'        => '',
			'menu_title'     => '',
			'capability'     => 'manage_options',
			'callback'       => null,
			'license_status' => '',
			'license_expiry' => '',
			'description'    => '',
			'icon'           => 'dashicons-admin-plugins', // Plugin icon for dashboard
			'position'       => 10, // Menu position (lower = higher in list)
		);

		$plugin = wp_parse_args( $args, $defaults );

		// Sanitize the slug (used in URLs and as array key).
		$plugin['slug'] = sanitize_key( $plugin['slug'] );

		// Sanitize text fields.
		$plugin['name']           = sanitize_text_field( $plugin['name'] );
		$plugin['version']        = sanitize_text_field( $plugin['version'] );
		$plugin['menu_title']     = sanitize_text_field( $plugin['menu_title'] );
		$plugin['capability']     = sanitize_key( $plugin['capability'] );
		$plugin['license_status'] = sanitize_key( $plugin['license_status'] );
		$plugin['license_expiry'] = sanitize_text_field( $plugin['license_expiry'] );
		$plugin['description']    = sanitize_text_field( $plugin['description'] );
		$plugin['icon']           = sanitize_html_class( $plugin['icon'] );
		$plugin['position']       = absint( $plugin['position'] );

		if ( ! empty( $plugin['slug'] ) ) {
			self::$plugins[ $plugin['slug'] ] = $plugin;
		}
	}

	/**
	 * Deregister a plugin from JezPress Manager
	 *
	 * @param string $slug Plugin slug to remove.
	 * @return bool True if removed, false if not found.
	 */
	public static function deregister( $slug ) {
		$slug = sanitize_key( $slug );

		if ( isset( self::$plugins[ $slug ] ) ) {
			unset( self::$plugins[ $slug ] );
			return true;
		}

		return false;
	}

	/**
	 * Get plugins sorted by position
	 *
	 * @return array Sorted plugins array.
	 */
	private static function get_sorted_plugins() {
		$plugins = self::$plugins;

		// Sort by position (lower number = higher in list), then alphabetically by name.
		uasort( $plugins, function ( $a, $b ) {
			if ( $a['position'] === $b['position'] ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
			return $a['position'] - $b['position'];
		} );

		return $plugins;
	}

	/**
	 * Get all registered plugins
	 *
	 * @return array
	 */
	public static function get_plugins() {
		return self::$plugins;
	}

	/**
	 * Check if JezPress Manager is active
	 *
	 * Helper function for other plugins to check availability.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return true;
	}

	/**
	 * Add admin menu and submenus
	 */
	public function add_admin_menu() {
		// Add top-level menu with 'none' icon - we'll use CSS for the icon.
		add_menu_page(
			__( 'JezPress Manager', 'jezpress-manager' ),
			__( 'JezPress Manager', 'jezpress-manager' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'none',
			58
		);

		// Add Dashboard submenu (replaces the default duplicate).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'jezpress-manager' ),
			__( 'Dashboard', 'jezpress-manager' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		// Add submenu for each registered plugin (sorted by position).
		foreach ( self::get_sorted_plugins() as $slug => $plugin ) {
			if ( ! empty( $plugin['callback'] ) && is_callable( $plugin['callback'] ) ) {
				add_submenu_page(
					self::MENU_SLUG,
					$plugin['name'],
					$plugin['menu_title'],
					$plugin['capability'],
					$slug,
					$plugin['callback']
				);
			}
		}
	}

	/**
	 * Redirect to dashboard on activation
	 */
	public function maybe_redirect_to_dashboard() {
		if ( get_transient( 'jezpress_manager_activation_redirect' ) ) {
			delete_transient( 'jezpress_manager_activation_redirect' );

			if ( ! isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Activation redirect, no user action performed
				wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
				exit;
			}
		}
	}

	/**
	 * Render the dashboard page
	 */
	public function render_dashboard() {
		$plugins = self::get_sorted_plugins();
		?>
		<div class="wrap jezpress-manager-wrap">
			<h1><?php esc_html_e( 'JezPress Manager', 'jezpress-manager' ); ?></h1>

			<div class="jezpress-dashboard-grid">
				<!-- Plugins List Card -->
				<div class="jezpress-card jezpress-card-plugins">
					<div class="jezpress-card-header">
						<h2><?php esc_html_e( 'Installed Plugins', 'jezpress-manager' ); ?></h2>
						<span class="jezpress-plugin-count"><?php echo esc_html( count( $plugins ) ); ?></span>
					</div>
					<div class="jezpress-card-body">
						<?php if ( empty( $plugins ) ) : ?>
							<div class="jezpress-empty-state">
								<span class="dashicons dashicons-admin-plugins"></span>
								<p><?php esc_html_e( 'No JezPress plugins registered yet.', 'jezpress-manager' ); ?></p>
								<p class="description"><?php esc_html_e( 'Install and activate JezPress plugins to see them here.', 'jezpress-manager' ); ?></p>
							</div>
						<?php else : ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th class="column-plugin"><?php esc_html_e( 'Plugin', 'jezpress-manager' ); ?></th>
										<th class="column-version"><?php esc_html_e( 'Version', 'jezpress-manager' ); ?></th>
										<th class="column-license"><?php esc_html_e( 'License', 'jezpress-manager' ); ?></th>
										<th class="column-actions"><?php esc_html_e( 'Actions', 'jezpress-manager' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $plugins as $slug => $plugin ) : ?>
										<tr>
											<td class="column-plugin">
												<div class="jezpress-plugin-name">
													<?php if ( ! empty( $plugin['icon'] ) ) : ?>
														<span class="dashicons <?php echo esc_attr( $plugin['icon'] ); ?> jezpress-plugin-icon"></span>
													<?php endif; ?>
													<strong><?php echo esc_html( $plugin['name'] ); ?></strong>
												</div>
												<?php if ( ! empty( $plugin['description'] ) ) : ?>
													<p class="description"><?php echo esc_html( $plugin['description'] ); ?></p>
												<?php endif; ?>
											</td>
											<td class="column-version">
												<code><?php echo esc_html( $plugin['version'] ); ?></code>
											</td>
											<td class="column-license">
												<?php echo wp_kses_post( $this->get_license_badge( $plugin['license_status'], $plugin['license_expiry'] ) ); ?>
											</td>
											<td class="column-actions">
												<?php if ( ! empty( $plugin['callback'] ) ) : ?>
													<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="button button-small">
														<?php esc_html_e( 'Settings', 'jezpress-manager' ); ?>
													</a>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>

				<!-- Support Card -->
				<div class="jezpress-card jezpress-card-support">
					<div class="jezpress-card-header">
						<h2><?php esc_html_e( 'Support & Contact', 'jezpress-manager' ); ?></h2>
					</div>
					<div class="jezpress-card-body">
						<ul class="jezpress-support-links">
							<li>
								<span class="dashicons dashicons-email-alt"></span>
								<div>
									<strong><?php esc_html_e( 'Email', 'jezpress-manager' ); ?></strong>
									<a href="mailto:jez@jezweb.net">jez@jezweb.net</a>
									<p class="description"><?php esc_html_e( 'We typically respond within a few hours.', 'jezpress-manager' ); ?></p>
								</div>
							</li>
							<li>
								<span class="dashicons dashicons-phone"></span>
								<div>
									<strong><?php esc_html_e( 'Phone', 'jezpress-manager' ); ?></strong>
									<a href="tel:1300024766">1300 024 766</a>
									<p class="description"><?php esc_html_e( 'Monday to Friday, 9am to 5pm AEST.', 'jezpress-manager' ); ?></p>
								</div>
							</li>
							<li>
								<span class="dashicons dashicons-location"></span>
								<div>
									<strong><?php esc_html_e( 'Jezweb Pty Ltd', 'jezpress-manager' ); ?></strong>
									<p class="description">
										5 Cowper St, Wallsend NSW 2287<br>
										ABN 88 127 346 730
									</p>
								</div>
							</li>
							<li>
								<span class="dashicons dashicons-admin-comments"></span>
								<div>
									<strong><?php esc_html_e( 'Contact Us', 'jezpress-manager' ); ?></strong>
									<a href="https://jezpress.com/contact" target="_blank" rel="noopener">
										<?php esc_html_e( 'Submit a Request', 'jezpress-manager' ); ?>
										<span class="dashicons dashicons-external"></span>
									</a>
								</div>
							</li>
						</ul>
					</div>
				</div>

				<!-- Quick Links Card -->
				<div class="jezpress-card jezpress-card-links">
					<div class="jezpress-card-header">
						<h2><?php esc_html_e( 'Quick Links', 'jezpress-manager' ); ?></h2>
					</div>
					<div class="jezpress-card-body">
						<ul class="jezpress-quick-links">
							<li>
								<a href="https://jezpress.com/plugins" target="_blank" rel="noopener">
									<span class="dashicons dashicons-admin-plugins"></span>
									<?php esc_html_e( 'Plugins', 'jezpress-manager' ); ?>
								</a>
							</li>
							<li>
								<a href="https://jezpress.com/hosting" target="_blank" rel="noopener">
									<span class="dashicons dashicons-cloud"></span>
									<?php esc_html_e( 'Hosting', 'jezpress-manager' ); ?>
								</a>
							</li>
							<li>
								<a href="https://jezpress.com/about" target="_blank" rel="noopener">
									<span class="dashicons dashicons-info"></span>
									<?php esc_html_e( 'About JezPress', 'jezpress-manager' ); ?>
								</a>
							</li>
						</ul>
						<hr style="margin: 15px 0; border: 0; border-top: 1px solid #f0f0f1;">
						<a href="https://jezweb.com.au" target="_blank" rel="noopener" class="jezpress-jezweb-link">
							<?php esc_html_e( 'Visit jezweb.com.au', 'jezpress-manager' ); ?>
							<span class="dashicons dashicons-external"></span>
						</a>
					</div>
				</div>
			</div>

			<div class="jezpress-footer">
				<p>
					<?php
					printf(
						/* translators: %s: JezPress Manager version */
						esc_html__( 'JezPress Manager v%s', 'jezpress-manager' ),
						esc_html( JEZPRESS_MANAGER_VERSION )
					);
					?>
					&bull;
					<a href="https://jezpress.com" target="_blank" rel="noopener">jezpress.com</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Get license status badge HTML
	 *
	 * @param string $status License status.
	 * @param string $expiry License expiry date.
	 * @return string Badge HTML.
	 */
	private function get_license_badge( $status, $expiry = '' ) {
		$badge = '';

		switch ( $status ) {
			case 'active':
				$badge = '<span class="jezpress-badge jezpress-badge-active">' . esc_html__( 'Active', 'jezpress-manager' ) . '</span>';
				if ( ! empty( $expiry ) ) {
					$badge .= '<br><small class="jezpress-expiry">' . sprintf(
						/* translators: %s: expiry date */
						esc_html__( 'Expires: %s', 'jezpress-manager' ),
						esc_html( $expiry )
					) . '</small>';
				}
				break;

			case 'expired':
				$badge = '<span class="jezpress-badge jezpress-badge-expired">' . esc_html__( 'Expired', 'jezpress-manager' ) . '</span>';
				$badge .= '<br><a href="https://jezpress.com/my-account" target="_blank" rel="noopener" class="jezpress-renew-link">' . esc_html__( 'Renew', 'jezpress-manager' ) . '</a>';
				break;

			case 'invalid':
				$badge = '<span class="jezpress-badge jezpress-badge-invalid">' . esc_html__( 'Invalid', 'jezpress-manager' ) . '</span>';
				break;

			default:
				$badge = '<span class="jezpress-badge jezpress-badge-none">' . esc_html__( 'Not Set', 'jezpress-manager' ) . '</span>';
				break;
		}

		return $badge;
	}

	/**
	 * Enqueue admin styles
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_styles( $hook ) {
		// Menu icon styles - load on all admin pages.
		wp_register_style( 'jezpress-manager-menu', false, array(), JEZPRESS_MANAGER_VERSION );
		wp_enqueue_style( 'jezpress-manager-menu' );
		wp_add_inline_style( 'jezpress-manager-menu', $this->get_menu_icon_styles() );

		// Dashboard styles - only load on JezPress Manager pages.
		if ( strpos( $hook, self::MENU_SLUG ) === false && strpos( $hook, 'jezpress' ) === false ) {
			return;
		}

		wp_register_style( 'jezpress-manager-dashboard', false, array(), JEZPRESS_MANAGER_VERSION );
		wp_enqueue_style( 'jezpress-manager-dashboard' );
		wp_add_inline_style( 'jezpress-manager-dashboard', $this->get_inline_styles() );
	}

	/**
	 * Get menu icon CSS for hover/active states
	 *
	 * @return string CSS styles.
	 */
	private function get_menu_icon_styles() {
		// Grey icon SVG (no fill, stroke only).
		$grey_svg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23a0a5aa' stroke-width='2'%3E%3Cpath d='M12 2L2 7l10 5 10-5-10-5z'/%3E%3Cpath d='M2 17l10 5 10-5'/%3E%3Cpath d='M2 12l10 5 10-5'/%3E%3C/svg%3E";

		// White icon SVG (no fill, stroke only).
		$white_svg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2'%3E%3Cpath d='M12 2L2 7l10 5 10-5-10-5z'/%3E%3Cpath d='M2 17l10 5 10-5'/%3E%3Cpath d='M2 12l10 5 10-5'/%3E%3C/svg%3E";

		return "
			#adminmenu .toplevel_page_jezpress-manager .wp-menu-image::before {
				content: '';
				display: block;
				width: 20px;
				height: 20px;
				background-image: url(\"{$grey_svg}\");
				background-repeat: no-repeat;
				background-position: center;
				background-size: 20px 20px;
				position: absolute;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
			}
			#adminmenu .toplevel_page_jezpress-manager:hover .wp-menu-image::before,
			#adminmenu .toplevel_page_jezpress-manager.wp-has-current-submenu .wp-menu-image::before,
			#adminmenu .toplevel_page_jezpress-manager.current .wp-menu-image::before {
				background-image: url(\"{$white_svg}\");
			}
			#adminmenu .toplevel_page_jezpress-manager .wp-menu-image {
				position: relative;
			}
		";
	}

	/**
	 * Get inline CSS styles
	 *
	 * @return string CSS styles.
	 */
	private function get_inline_styles() {
		return '
			.jezpress-manager-wrap {
				max-width: 100%;
			}
			.jezpress-dashboard-grid {
				display: grid;
				grid-template-columns: 1fr 350px;
				grid-template-rows: auto auto;
				gap: 20px;
				margin-top: 20px;
			}
			.jezpress-card {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
			}
			.jezpress-card-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 15px 20px;
				border-bottom: 1px solid #c3c4c7;
				background: #f6f7f7;
			}
			.jezpress-card-header h2 {
				margin: 0;
				font-size: 14px;
				font-weight: 600;
			}
			.jezpress-card-body {
				padding: 20px;
			}
			.jezpress-card-plugins {
				grid-column: 1;
				grid-row: 1 / 3;
			}
			.jezpress-card-support {
				grid-column: 2;
				grid-row: 1;
			}
			.jezpress-card-links {
				grid-column: 2;
				grid-row: 2;
			}
			.jezpress-plugin-count {
				background: #14b8a6;
				color: #fff;
				padding: 2px 8px;
				border-radius: 10px;
				font-size: 12px;
				font-weight: 600;
			}
			.jezpress-empty-state {
				text-align: center;
				padding: 40px 20px;
				color: #646970;
			}
			.jezpress-empty-state .dashicons {
				font-size: 48px;
				width: 48px;
				height: 48px;
				margin-bottom: 15px;
				color: #c3c4c7;
			}
			.jezpress-badge {
				display: inline-block;
				padding: 3px 10px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 500;
			}
			.jezpress-badge-active {
				background: #d4edda;
				color: #0a3622;
			}
			.jezpress-badge-expired {
				background: #fff3cd;
				color: #664d03;
			}
			.jezpress-badge-invalid {
				background: #f8d7da;
				color: #58151c;
			}
			.jezpress-badge-none {
				background: #e9ecef;
				color: #495057;
			}
			.jezpress-expiry {
				color: #646970;
				font-size: 11px;
			}
			.jezpress-renew-link {
				font-size: 11px;
			}
			.jezpress-support-links {
				list-style: none;
				margin: 0;
				padding: 0;
			}
			.jezpress-support-links li {
				display: flex;
				gap: 12px;
				padding: 12px 0;
				border-bottom: 1px solid #f0f0f1;
			}
			.jezpress-support-links li:last-child {
				border-bottom: none;
				padding-bottom: 0;
			}
			.jezpress-support-links li:first-child {
				padding-top: 0;
			}
			.jezpress-support-links .dashicons {
				color: #14b8a6;
				margin-top: 2px;
			}
			.jezpress-support-links strong {
				display: block;
				margin-bottom: 3px;
			}
			.jezpress-support-links .description {
				margin: 4px 0 0;
				font-size: 12px;
				color: #646970;
			}
			.jezpress-support-links a .dashicons-external {
				font-size: 14px;
				width: 14px;
				height: 14px;
				vertical-align: middle;
			}
			.jezpress-quick-links {
				list-style: none;
				margin: 0;
				padding: 0;
			}
			.jezpress-quick-links li {
				margin-bottom: 10px;
			}
			.jezpress-quick-links li:last-child {
				margin-bottom: 0;
			}
			.jezpress-quick-links a {
				display: flex;
				align-items: center;
				gap: 8px;
				text-decoration: none;
				color: #14b8a6;
			}
			.jezpress-quick-links a:hover {
				color: #135e96;
			}
			.jezpress-quick-links .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
			}
			.jezpress-jezweb-link {
				display: inline-flex;
				align-items: center;
				gap: 4px;
				font-size: 13px;
				text-decoration: none;
				color: #646970;
			}
			.jezpress-jezweb-link:hover {
				color: #14b8a6;
			}
			.jezpress-jezweb-link .dashicons {
				font-size: 14px;
				width: 14px;
				height: 14px;
			}
			.jezpress-footer {
				margin-top: 30px;
				padding-top: 15px;
				border-top: 1px solid #c3c4c7;
				color: #646970;
				font-size: 12px;
			}
			.jezpress-footer a {
				color: #646970;
			}
			.column-version {
				width: 80px;
			}
			.column-license {
				width: 120px;
			}
			.column-actions {
				width: 100px;
			}
			.jezpress-plugin-name {
				display: flex;
				align-items: center;
				gap: 8px;
			}
			.jezpress-plugin-icon {
				color: #14b8a6;
				font-size: 18px;
				width: 18px;
				height: 18px;
			}
			@media screen and (max-width: 960px) {
				.jezpress-dashboard-grid {
					grid-template-columns: 1fr;
				}
				.jezpress-card-plugins {
					grid-column: 1;
					grid-row: 1;
				}
				.jezpress-card-support {
					grid-column: 1;
					grid-row: 2;
				}
				.jezpress-card-links {
					grid-column: 1;
					grid-row: 3;
				}
			}
		';
	}
}
