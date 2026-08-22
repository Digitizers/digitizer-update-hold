<?php
/**
 * Update Policy - the WordPress wiring.
 *
 * The only file here with side effects, and the two halves are kept apart on
 * purpose: applying the hold is a read and writes nothing, recording a first
 * sighting happens after WordPress has stored an update check of its own.
 *
 * A read filter that writes turns every page load into a side effect, and
 * state written into WordPress's own stored value outlives the module that put
 * it there. Disabling this module restores WordPress's behaviour in the same
 * request; the stamps stay in the option, inert, and are picked up again if it
 * is switched back on.
 *
 * @package Update_Policy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The WordPress wiring - the only class with side effects.
 */
class Update_Policy_Core {

	/**
	 * Register the filters.
	 */
	public static function init() {
		// On a network the plugin is network-activated (Network: true in the
		// header), so it runs on every site and the policy is one site option.
		// A core update is network-wide; a per-site switch for it would only
		// ever look like protection while providing none.

		add_filter( 'site_transient_update_core', array( __CLASS__, 'apply_hold' ) );
		add_action( 'set_site_transient_update_core', array( __CLASS__, 'record_sightings' ) );

		// Also once per admin page load. A module switched on while a major is
		// already offered has missed the check that would have stamped it, and
		// an unstamped hold has no dates to show - the notice would say the
		// site first saw the release in 1970. Idempotent: it writes only when
		// a branch has no stamp yet.
		add_action( 'admin_init', array( __CLASS__, 'record_sightings' ) );

		// The unattended path has to agree with the visible one. Major core
		// auto-updates are off by default, so this is belt and braces - but a
		// site that turned them on should not quietly bypass the hold.
		add_filter( 'allow_major_auto_core_updates', array( __CLASS__, 'allow_major_auto' ) );
	}

	/**
	 * The version this site is running.
	 *
	 * @return string
	 */
	public static function installed_version() {
		return isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
	}

	/**
	 * Remove held majors from the offers WordPress is about to report.
	 *
	 * @param mixed $transient The update_core site transient.
	 * @return mixed
	 */
	public static function apply_hold( $transient ) {
		if ( ! is_object( $transient ) || ! isset( $transient->updates ) || ! is_array( $transient->updates ) ) {
			return $transient;
		}
		$policy = Update_Policy_Settings::all();
		$kept   = Update_Policy_Offers::filter(
			$transient->updates,
			self::installed_version(),
			$policy['seen'],
			$policy['released'],
			Update_Policy_Settings::hold_days(),
			time()
		);
		if ( count( $kept ) === count( $transient->updates ) ) {
			return $transient;
		}

		// Copied rather than edited: the object is WordPress's, and other
		// readers of this transient in the same request are entitled to it
		// unchanged if this module is later switched off mid-request.
		$out          = clone $transient;
		$out->updates = $kept;
		return $out;
	}

	/**
	 * Record the first time this site was offered each major.
	 *
	 * Runs after the write rather than before it, so the check WordPress just
	 * performed is already recorded when this reads its result.
	 *
	 * @return void
	 */
	public static function record_sightings() {
		$raw = self::stored_transient();
		if ( ! is_object( $raw ) || ! isset( $raw->updates ) || ! is_array( $raw->updates ) ) {
			return;
		}
		$policy  = Update_Policy_Settings::all();
		$stamped = Update_Policy_Offers::stamp( $policy['seen'], $raw->updates, self::installed_version(), time() );
		if ( $stamped === $policy['seen'] ) {
			return;
		}
		$policy['seen'] = $stamped;
		Update_Policy_Settings::save( $policy );
	}

	/**
	 * The stored transient, read past this module's own filter.
	 *
	 * Reading it through get_site_transient() would hand back the filtered
	 * copy, and a major that is currently held would then never be recorded -
	 * so it would stay held forever, which is the one outcome this module must
	 * not produce.
	 *
	 * @return mixed
	 */
	private static function stored_transient() {
		remove_filter( 'site_transient_update_core', array( __CLASS__, 'apply_hold' ) );
		$raw = get_site_transient( 'update_core' );
		add_filter( 'site_transient_update_core', array( __CLASS__, 'apply_hold' ) );
		return $raw;
	}

	/**
	 * Refuse unattended major updates while any major is held.
	 *
	 * @param mixed $allow Whatever WordPress or another plugin decided.
	 * @return mixed
	 */
	public static function allow_major_auto( $allow ) {
		return self::held_majors() ? false : $allow;
	}

	/**
	 * The majors currently held: branch => offered version.
	 *
	 * @return array
	 */
	public static function held_majors() {
		$raw = self::stored_transient();
		if ( ! is_object( $raw ) || ! isset( $raw->updates ) || ! is_array( $raw->updates ) ) {
			return array();
		}
		$policy    = Update_Policy_Settings::all();
		$installed = self::installed_version();
		$days      = Update_Policy_Settings::hold_days();
		$now       = time();

		$out = array();
		foreach ( Update_Policy_Offers::majors( $raw->updates, $installed ) as $branch => $version ) {
			$stamp = isset( $policy['seen'][ $branch ] ) ? (int) $policy['seen'][ $branch ] : 0;
			if ( empty( $policy['released'][ $branch ] ) && Update_Policy_Version::is_held( $stamp, $days, $now ) ) {
				$out[ $branch ] = array(
					'version' => $version,
					'seen'    => $stamp,
					'until'   => Update_Policy_Version::held_until( $stamp, $days ),
				);
			}
		}
		return $out;
	}

	/**
	 * Lift the hold on one branch, permanently.
	 *
	 * @param string $branch Branch string, e.g. 7.1.
	 * @return bool
	 */
	public static function release( $branch ) {
		$branch = Update_Policy_Version::branch( $branch );
		if ( '' === $branch || ! Update_Policy_Settings::may_decide() ) {
			return false;
		}
		$policy                        = Update_Policy_Settings::all();
		$policy['released'][ $branch ] = 1;
		Update_Policy_Settings::save( $policy );
		return true;
	}
}
