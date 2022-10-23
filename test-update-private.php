<?php
/**
 * WordCamp Pontevedra 2022 demo plugin.
 *
 * @link              https://fgrweb.es
 * @since             1.0.0
 * @package           Ponte_WordCamp
 *
 * @wordpress-plugin
 * Plugin Name:       Test update private
 * Plugin URI:        https://fgrweb.es/
 * Description:       Plugin demo para WordCamp Pontevedra 2022.
 * Version:           1.0.4
 * Author:            Fernando Garcia Rebolledo
 * Author URI:        https://fgrweb.es
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pontewordcamp
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Begins execution of plugin.
 *
 * @return void
 */
function run_pontewordcamp() {
	if ( is_admin() ) {
		include_once plugin_dir_path( __FILE__ ) . '/class-pontewordcamp-updater.php';
		$config  = array(
			// coge la última release. info: https://docs.github.com/es/rest/releases/releases#get-the-latest-release
			'github_uri' => 'https://api.github.com/repos/bubuku/test-update-private/releases/latest',
			'token'      => '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',
		);
		$updater = new Ponte_WordCamp_Updater( $config, __FILE__ );
		$updater->fgr_check_update();
	}
}

run_pontewordcamp();