<?php
/**
 * Plugin Name:       Digitizer Update Hold
 * Plugin URI:        https://github.com/Digitizers/digitizer-update-hold
 * Description:       Holds a major WordPress release back for a set number of days after your site is first offered it, so your plugins and themes have time to catch up. Security and maintenance releases are never held.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.2
 * Author:            Digitizer
 * Author URI:        https://www.digitizer.co.il/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       digitizer-update-hold
 * Network:           true
 *
 * @package Digitizer_Update_Hold
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIGITIZER_UPDATE_HOLD_VERSION', '1.0.0' );
define( 'DIGITIZER_UPDATE_HOLD_PATH', plugin_dir_path( __FILE__ ) );
define( 'DIGITIZER_UPDATE_HOLD_BASENAME', plugin_basename( __FILE__ ) );

require_once DIGITIZER_UPDATE_HOLD_PATH . 'includes/class-digitizer-update-hold-version.php';
require_once DIGITIZER_UPDATE_HOLD_PATH . 'includes/class-digitizer-update-hold-offers.php';
require_once DIGITIZER_UPDATE_HOLD_PATH . 'includes/class-digitizer-update-hold-settings.php';
require_once DIGITIZER_UPDATE_HOLD_PATH . 'includes/class-digitizer-update-hold-core.php';
require_once DIGITIZER_UPDATE_HOLD_PATH . 'includes/class-digitizer-update-hold-admin.php';

/**
 * Wire the plugin.
 *
 * The policy hooks are registered on every request, front end included: the
 * update transient is read there too, and a hold that applies on one screen
 * and not another is worse than no hold at all.
 */
function digitizer_update_hold_boot() {
	Digitizer_Update_Hold_Core::init();

	if ( is_admin() ) {
		new Digitizer_Update_Hold_Admin();
	}
}
add_action( 'plugins_loaded', 'digitizer_update_hold_boot' );

/**
 * A Settings link on the plugins list, pointing wherever the screen lives.
 *
 * @param array $links Action links.
 * @return array
 */
function digitizer_update_hold_action_links( $links ) {
	if ( ! is_array( $links ) ) {
		return $links;
	}
	$settings = '<a href="' . esc_url( Digitizer_Update_Hold_Admin::settings_url() ) . '">' . esc_html__( 'Settings', 'digitizer-update-hold' ) . '</a>';
	array_unshift( $links, $settings );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'digitizer_update_hold_action_links' );
add_filter( 'network_admin_plugin_action_links_' . plugin_basename( __FILE__ ), 'digitizer_update_hold_action_links' );
