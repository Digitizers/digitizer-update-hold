<?php
/**
 * Digitizer Update Hold - the stored policy.
 *
 * One option, read and written through here so the multisite question is
 * answered in a single place: a core update is network-wide, and so is the
 * decision to hold one.
 *
 * @package Digitizer_Update_Hold
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The stored policy.
 */
class Digitizer_Update_Hold_Settings {

	const OPTION       = 'digitizer_update_hold';
	const DEFAULT_DAYS = 30;

	/**
	 * The whole policy, with defaults filled in.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = is_multisite() ? get_site_option( self::OPTION, array() ) : get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array(
			'hold_days' => isset( $saved['hold_days'] ) ? (int) $saved['hold_days'] : self::DEFAULT_DAYS,
			'seen'      => ( isset( $saved['seen'] ) && is_array( $saved['seen'] ) ) ? $saved['seen'] : array(),
			'released'  => ( isset( $saved['released'] ) && is_array( $saved['released'] ) ) ? $saved['released'] : array(),
		);
	}

	/**
	 * Persist the policy.
	 *
	 * @param array $policy Full policy array.
	 * @return void
	 */
	public static function save( $policy ) {
		$clean = array(
			'hold_days' => max( 0, (int) $policy['hold_days'] ),
			'seen'      => array(),
			'released'  => array(),
		);
		foreach ( (array) $policy['seen'] as $branch => $stamp ) {
			$branch = Digitizer_Update_Hold_Version::branch( $branch );
			if ( '' !== $branch && (int) $stamp > 0 ) {
				$clean['seen'][ $branch ] = (int) $stamp;
			}
		}
		foreach ( (array) $policy['released'] as $branch => $yes ) {
			$branch = Digitizer_Update_Hold_Version::branch( $branch );
			if ( '' !== $branch && $yes ) {
				$clean['released'][ $branch ] = 1;
			}
		}

		if ( is_multisite() ) {
			update_site_option( self::OPTION, $clean );
			return;
		}
		update_option( self::OPTION, $clean );
	}

	/**
	 * The configured hold, in days.
	 *
	 * @return int
	 */
	public static function hold_days() {
		$policy = self::all();
		/**
		 * Filter the number of days a major release is held back.
		 *
		 * @param int $days Days, 0 to disable the hold entirely.
		 */
		return (int) apply_filters( 'digitizer_update_hold_days', $policy['hold_days'] );
	}

	/**
	 * Whether this user may change the policy or release a hold.
	 *
	 * The update_core capability is what WordPress requires to install a core update, and on
	 * multisite core grants it to network administrators only - which is the
	 * same answer this needs, without a second rule to keep in step.
	 *
	 * @return bool
	 */
	public static function may_decide() {
		if ( is_multisite() && ! current_user_can( 'manage_network_options' ) ) {
			return false;
		}
		return current_user_can( 'update_core' );
	}
}
