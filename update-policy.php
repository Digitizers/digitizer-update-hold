<?php
/**
 * Plugin Name:       Update Policy
 * Plugin URI:        https://github.com/Digitizers/update-policy
 * Description:       Holds a major WordPress release back for a set number of days after your site is first offered it, so your plugins and themes have time to catch up. Security and maintenance releases are never held.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.2
 * Author:            Digitizer
 * Author URI:        https://www.digitizer.co.il/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       update-policy
 * Domain Path:       /languages
 * Network:           true
 *
 * @package Update_Policy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UPDATE_POLICY_VERSION', '1.0.0' );
define( 'UPDATE_POLICY_PATH', plugin_dir_path( __FILE__ ) );
define( 'UPDATE_POLICY_BASENAME', plugin_basename( __FILE__ ) );

require_once UPDATE_POLICY_PATH . 'includes/class-update-policy-version.php';
require_once UPDATE_POLICY_PATH . 'includes/class-update-policy-offers.php';
require_once UPDATE_POLICY_PATH . 'includes/class-update-policy-settings.php';
require_once UPDATE_POLICY_PATH . 'includes/class-update-policy-core.php';
require_once UPDATE_POLICY_PATH . 'includes/class-update-policy-admin.php';

/**
 * Wire the plugin.
 *
 * The policy hooks are registered on every request, front end included: the
 * update transient is read there too, and a hold that applies on one screen
 * and not another is worse than no hold at all.
 */
function update_policy_boot() {
	Update_Policy_Core::init();

	if ( is_admin() ) {
		new Update_Policy_Admin();
	}
}
add_action( 'plugins_loaded', 'update_policy_boot' );

/**
 * Load the bundled translations.
 *
 * Kept deliberately. WordPress finds language packs from WordPress.org on its
 * own, but a pack exists only once the plugin's translation has been
 * completed there - and until it has, a Hebrew site would see English. The
 * catalog ships inside the plugin for that interval, and this is what loads
 * it. On init rather than earlier, which is where WordPress 6.7 asks for it.
 */
function update_policy_load_textdomain() {
	load_plugin_textdomain( 'update-policy', false, dirname( UPDATE_POLICY_BASENAME ) . '/languages' );
}
add_action( 'init', 'update_policy_load_textdomain' );

/**
 * A Settings link on the plugins list, pointing wherever the screen lives.
 *
 * @param array $links Action links.
 * @return array
 */
function update_policy_action_links( $links ) {
	if ( ! is_array( $links ) ) {
		return $links;
	}
	$settings = '<a href="' . esc_url( Update_Policy_Admin::settings_url() ) . '">' . esc_html__( 'Settings', 'update-policy' ) . '</a>';
	array_unshift( $links, $settings );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'update_policy_action_links' );
add_filter( 'network_admin_plugin_action_links_' . plugin_basename( __FILE__ ), 'update_policy_action_links' );
