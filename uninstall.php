<?php
/**
 * Remove everything Digitizer Update Hold stored.
 *
 * One option. On a network it is a site option, since a core update is
 * network-wide and so is the policy about it.
 *
 * @package Digitizer_Update_Hold
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'digitizer_update_hold' );
if ( is_multisite() ) {
	delete_site_option( 'digitizer_update_hold' );
}
