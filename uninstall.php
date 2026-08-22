<?php
/**
 * Remove everything Update Policy stored.
 *
 * One option. On a network it is a site option, since a core update is
 * network-wide and so is the policy about it.
 *
 * @package Update_Policy
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'update_policy' );
if ( is_multisite() ) {
	delete_site_option( 'update_policy' );
}
