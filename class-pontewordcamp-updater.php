<?php

/**
 * The plugin updater checker
 *
 * @link       https://fgrweb.es
 * @since      1.0.0
 *
 * @package    Ponte_WordCamp
 */
class Ponte_WordCamp_Updater {
	/**
	 * The plugin congiguration
	 *
	 * @var array
	 */
	private $plugin_config;
	/**
	 * The plugin bsename
	 *
	 * @var string
	 */
	private $plugin_basename;
	/**
	 * The plugin file
	 *
	 * @var string
	 */
	private $file;
	/**
	 * The GitHub repository info
	 *
	 * @var array
	 */
	private $github_response;
	/**
	 * The pluginn data
	 *
	 * @var array
	 */
	private $plugin_data;
	/**
	 * Is plugin active.
	 *
	 * @var boolean
	 */
	private $is_active;
	/**
	 * The class construct
	 *
	 * @param  array  $plugin_config The GitHub config.
	 * @param  string $file The plugin file.
	 * @return void
	 */
	public function __construct( $plugin_config, $file ) {
		$this->plugin_config   = $plugin_config;
		$this->file            = $file;
		$this->is_active       = false;
		$this->plugin_basename = plugin_basename( $this->file );
	}

	/**
	 * The check for updates init function.
	 *
	 * @return void
	 */
	public function fgr_check_update() {
		// info: https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5/
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'fgr_check_transient' ), 10, 1 );
		add_filter( 'http_request_args', array( $this, 'fgr_set_header_token' ), 10, 2 );
		add_filter( 'plugins_api', array( $this, 'fgr_plugin_popup' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'fgr_after_install' ), 10, 3 );
	}
	/**
	 * The check for updates transient function.
	 *
	 * @param  object $transient The transient object.
	 * @return object            The transient object.
	 */
	public function fgr_check_transient( $transient ) {
		// Check if transiente has checked property.
		if ( ! property_exists( $transient, 'checked' ) ) {
			return $transient;
		}
		$this->fgr_github_repository_info();
		$this->fgr_get_plugin_data();
		// Did WordPress checked updates?
		if ( $transient->checked[ $this->plugin_basename ] && is_array($this->github_response) ) {
			// Compare versions.
			if ( version_compare( $this->github_response['tag_name'], $transient->checked[ $this->plugin_basename ], 'gt' ) ) {
				// New version available.
				$plugin = array(
					'url'         => $this->plugin_data['PluginURI'],
					'slug'        => current( explode( '/', $this->plugin_basename ) ),
					'new_version' => $this->github_response['tag_name'],
					'package'     => $this->github_response['zipball_url'],
				);
				// Set the response basename object.
				$transient->response[ $this->plugin_basename ] = (object) $plugin;
			}
		}
		return $transient;
	}

	/**
	 * Get the GitHub repository info.
	 *
	 * @return array
	 */
	public function fgr_github_repository_info() {
		if ( null !== $this->github_response ) {
			return;
		}
		// REST API args.
		$args = array(
			'method'      => 'GET',
			'timeout'     => 5,
			'redirection' => 5,
			'httpversion' => '1.0',
			'headers'     => array(
				'Authorization' => 'token ' . $this->plugin_config['token'],
			),
			'sslverify'   => false,
		);
		// Get the response.
		$request = wp_remote_get( $this->plugin_config['github_uri'], $args );
		// Check for error.
		if ( is_wp_error( $request ) ) {
			return;
		}
		// Decode the response.
		$response = json_decode( wp_remote_retrieve_body( $request ), true );
		$response_code = wp_remote_retrieve_response_code( $request);

		if ( 200 !== $response_code ) {
			// https://developer.wordpress.org/reference/hooks/in_plugin_update_message-file/
			// https://wisdomplugin.com/add-inline-plugin-update-message/

			//$message = $response['message'];
			//$documentation_url = $response['documentation_url'];
			//echo '<p>Error: '. $message .', info: '. $documentation_url .'</p>';
			add_action( 'after_plugin_row_test-update-private/test-update-private.php', array( $this, 'fgr_message_error'), 10, 3 );
			return;
		}

		if ( is_array( $response ) ) {
			// Get the first item.
			$response = current( $response );
		}
		// Is there access token?
		if ( $this->plugin_config['token'] ) {
			// Update the zipball_url with the token.
			$response['zipball_url'] = add_query_arg( 'access_token', $this->plugin_config['token'], $response['zipball_url'] );
		}
		$this->github_response = $response;
	}

	/**
	 * Get the plugin data.
	 *
	 * @return array
	 */
	public function fgr_get_plugin_data() {
		if ( null !== $this->plugin_data ) {
			return;
		}
		$this->plugin_data = get_plugin_data( $this->file );
	}

	/**
	 * Change the args used on downloading the zipball.
	 *
	 * @param  array  $args HTTP request arguments.
	 * @param  string $url The downloading url.
	 * @return array
	 */
	public function fgr_set_header_token( $args, $url ) {
		$parse_url = wp_parse_url( $url );
		if ( 'api.github.com' === $parse_url['host'] && isset( $parse_url['query'] ) ) {
			parse_str( $parse_url['query'], $query );
			if ( isset( $query['access_token'] ) && $query['access_token'] ) {
				$args['headers']['Authorization'] = 'token ' . $query['access_token'];
				// Set is_active var.
				$this->is_active = is_plugin_active( $this->plugin_basename );
			}
		}
		return $args;
	}

	/**
	 * The details of the new version of the plugin to show in popup.
	 *
	 * @param  false|object|array $result The result object or array.
	 * @param  string             $action The type of information being requested from the Plugin Installation API.
	 * @param  object             $args   Plugin API arguments.
	 */
	public function fgr_plugin_popup( $result, $action, $args ) {
		if ( empty( $args->slug ) || 'plugin_information' !== $action || $args->slug !== current( explode( '/', $this->plugin_basename ) ) ) {
			return false;
		}
		$this->fgr_github_repository_info();
		$this->fgr_get_plugin_data();
		$plugin = array(
			'name'              => $this->plugin_data['Name'],
			'slug'              => $this->basename,
			'version'           => $this->github_response['tag_name'],
			'author'            => $this->plugin_data['AuthorName'],
			'author_profile'    => $this->plugin_data['AuthorURI'],
			'last_updated'      => $this->github_response['published_at'],
			'homepage'          => $this->plugin_data['PluginURI'],
			'short_description' => $this->plugin_data['Description'],
			'sections'          => array( 
				'Description' => $this->plugin_data['Description'],
				'Updates'     => $this->github_response['body'],
			),
			'download_link'     => $this->github_response['zipball_url'],
			'requires'          => $this->plugin_data['RequiresWP'],
			'tested'            => $this->plugin_data['TestedUpTo'],
		);
		return (object) $plugin;
	}

	/**
	 * Active plugin after install the new version.
	 *
	 * @param  bool  $response   Installation response.
	 * @param  array $hook_extra Extra arguments passed to hooked filters.
	 * @param  array $result     Installation result data.
	 * @return array
	 *
	 */
	public function fgr_after_install( $response, $hook_extra, $result ) {
		// Get FileSystem object.
		global $wp_filesystem;
		$install_directory = plugin_dir_path( $this->file );
		// Move file to plugins dir.
		$wp_filesystem->move( $result['destination'], $install_directory );
		$result['destination'] = $install_directory;
		if ( $this->is_active ) {
			activate_plugin( $this->plugin_basename );
		}
		return $result;
	}

	public function fgr_message_error($plugin_file, $plugin_data, $status) {

		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
		$plugin_slug = dirname(plugin_basename( __FILE__ ));
			?>

			<tr class="plugin-update-tr <?php echo $status;?>" id="<?php echo $plugin_slug; ?>-update" data-slug="<?php echo $plugin_slug; ?>" data-plugin="<?php echo $plugin_file ?>">
				<td colspan="<?php echo $wp_list_table->get_column_count(); ?>" class="update-message inline notice notice-error notice-alt">
						<p>
						Usted est?? utilizando una versi??n de WPML no registrada y no est?? recibiendo actualizaciones de compatibilidad y seguridad. <a href="https://dev-wp.cobee.io/wp-admin/plugin-install.php?tab=commercial&amp;repository=wpml&amp;action=register">Registrar ahora</a>
						</p>
				</td>
			</tr>

		<?php
	}
}