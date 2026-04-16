<?php
/**
 * JezPress Updater Class
 *
 * Handles automatic updates from updates.jezpress.com
 *
 * @package JezPress_Manager
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JezPress Updater Class.
 */
class JezPress_Manager_Updater {

	/**
	 * Plugin file.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Plugin data.
	 *
	 * @var array
	 */
	private $plugin_data;

	/**
	 * Update server URL.
	 *
	 * @var string
	 */
	private $update_server = 'https://updates.jezpress.com';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Main plugin file path.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_slug = plugin_basename( dirname( $plugin_file ) );

		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
	}

	/**
	 * Get plugin data.
	 *
	 * @return array Plugin data.
	 */
	private function get_plugin_data() {
		if ( ! $this->plugin_data ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$this->plugin_data = get_plugin_data( $this->plugin_file );
		}
		return $this->plugin_data;
	}

	/**
	 * Get remote plugin info.
	 *
	 * @return object|false Plugin info or false on failure.
	 */
	private function get_remote_info() {
		$transient_key = 'jezpress_manager_update_info';
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			$this->update_server . '/api/v1/info?plugin=' . rawurlencode( $this->plugin_slug ),
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( empty( $data ) || empty( $data->success ) ) {
			return false;
		}

		// Map API response fields to expected WordPress format.
		$data->requires     = isset( $data->requires_wp ) ? $data->requires_wp : '';
		$data->tested       = isset( $data->tested_wp ) ? $data->tested_wp : '';
		$data->download_url = $this->update_server . '/api/v1/download?plugin=' . rawurlencode( $this->plugin_slug ) . '&version=' . rawurlencode( $data->version );

		set_transient( $transient_key, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Check for plugin update.
	 *
	 * @param object $transient Update transient.
	 * @return object Modified transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote_info = $this->get_remote_info();

		if ( false === $remote_info || ! isset( $remote_info->version ) ) {
			return $transient;
		}

		$plugin_data = $this->get_plugin_data();
		$plugin_file = plugin_basename( $this->plugin_file );

		if ( version_compare( $remote_info->version, $plugin_data['Version'], '>' ) ) {
			// Update available.
			$transient->response[ $plugin_file ] = (object) array(
				'slug'         => $this->plugin_slug,
				'plugin'       => $plugin_file,
				'new_version'  => $remote_info->version,
				'url'          => isset( $remote_info->homepage ) ? $remote_info->homepage : '',
				'package'      => isset( $remote_info->download_url ) ? $remote_info->download_url : '',
				'icons'        => isset( $remote_info->icons ) ? (array) $remote_info->icons : array(),
				'banners'      => isset( $remote_info->banners ) ? (array) $remote_info->banners : array(),
				'tested'       => isset( $remote_info->tested ) ? $remote_info->tested : '',
				'requires'     => isset( $remote_info->requires ) ? $remote_info->requires : '',
				'requires_php' => isset( $remote_info->requires_php ) ? $remote_info->requires_php : '',
			);
		} else {
			// Up to date — register in no_update so WordPress shows "Enable auto-updates" link.
			$transient->no_update[ $plugin_file ] = (object) array(
				'id'          => $plugin_file,
				'slug'        => $this->plugin_slug,
				'plugin'      => $plugin_file,
				'new_version' => $plugin_data['Version'],
				'url'         => '',
				'package'     => '',
			);
		}

		return $transient;
	}

	/**
	 * Plugin info for the WordPress plugins API.
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action API action.
	 * @param object             $args   API arguments.
	 * @return false|object Modified result.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( $this->plugin_slug !== $args->slug ) {
			return $result;
		}

		$remote_info = $this->get_remote_info();

		if ( false === $remote_info ) {
			return $result;
		}

		$plugin_data = $this->get_plugin_data();

		return (object) array(
			'name'           => isset( $remote_info->name ) ? $remote_info->name : $plugin_data['Name'],
			'slug'           => $this->plugin_slug,
			'version'        => isset( $remote_info->version ) ? $remote_info->version : $plugin_data['Version'],
			'author'         => isset( $remote_info->author ) ? $remote_info->author : $plugin_data['Author'],
			'author_profile' => isset( $remote_info->author_profile ) ? $remote_info->author_profile : '',
			'requires'       => isset( $remote_info->requires ) ? $remote_info->requires : '',
			'tested'         => isset( $remote_info->tested ) ? $remote_info->tested : '',
			'requires_php'   => isset( $remote_info->requires_php ) ? $remote_info->requires_php : '',
			'sections'       => isset( $remote_info->sections ) ? (array) $remote_info->sections : array(),
			'download_link'  => isset( $remote_info->download_url ) ? $remote_info->download_url : '',
			'banners'        => isset( $remote_info->banners ) ? (array) $remote_info->banners : array(),
			'icons'          => isset( $remote_info->icons ) ? (array) $remote_info->icons : array(),
			'last_updated'   => isset( $remote_info->last_updated ) ? $remote_info->last_updated : '',
			'changelog'      => isset( $remote_info->changelog ) ? $remote_info->changelog : '',
		);
	}

	/**
	 * Clear update cache after plugin update.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Update options.
	 */
	public function clear_cache( $upgrader, $options ) {
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			delete_transient( 'jezpress_manager_update_info' );
		}
	}
}
