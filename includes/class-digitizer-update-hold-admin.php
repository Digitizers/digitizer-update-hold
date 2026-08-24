<?php
/**
 * Digitizer Update Hold - the settings screen, and the notice that keeps a
 * hold from being invisible.
 *
 * @package Digitizer_Update_Hold
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings screen and hold notice.
 */
class Digitizer_Update_Hold_Admin {

	const PAGE_SLUG = 'digitizer-update-hold';

	/**
	 * Hook the screen, the two form handlers and the notices.
	 */
	public function __construct() {
		add_action( 'admin_post_digitizer_update_hold_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_digitizer_update_hold_release', array( $this, 'handle_release' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_notices' ) );
		// Core updates on a network are managed from the network Updates
		// screen, which fires its own notices hook. Without this the network
		// administrator sees a missing update and no explanation for it.
		add_action( 'network_admin_notices', array( $this, 'maybe_show_notices' ) );

		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'register_network_menu' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
		}
	}

	/**
	 * Settings > Digitizer Update Hold on a single site.
	 */
	public function register_menu() {
		add_options_page(
			__( 'Digitizer Update Hold', 'digitizer-update-hold' ),
			__( 'Digitizer Update Hold', 'digitizer-update-hold' ),
			'update_core',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Network Admin > Settings > Digitizer Update Hold on a network, where core
	 * updates are administered.
	 */
	public function register_network_menu() {
		add_submenu_page(
			'settings.php',
			__( 'Digitizer Update Hold', 'digitizer-update-hold' ),
			__( 'Digitizer Update Hold', 'digitizer-update-hold' ),
			'manage_network_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * The URL of the settings screen, wherever it lives.
	 *
	 * @return string
	 */
	public static function settings_url() {
		return is_multisite()
			? network_admin_url( 'settings.php?page=' . self::PAGE_SLUG )
			: admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * The page slug WordPress was asked for, read defensively.
	 *
	 * A page value that is an array TypeErrors on PHP 8 during admin_notices,
	 * which is where this is read from.
	 *
	 * @return string
	 */
	private static function current_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check, not a state change.
		if ( ! isset( $_GET['page'] ) || ! is_scalar( $_GET['page'] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check, not a state change.
		return sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Say what is being held, wherever someone would look for it.
	 *
	 * A hold nobody can see is indistinguishable from a site whose updates are
	 * broken, so this runs on the Updates screen and the dashboard as well as
	 * on the plugin's own page.
	 */
	public function maybe_show_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag set by our own post-save redirect.
		if ( self::PAGE_SLUG === self::current_page() && isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'digitizer-update-hold' ) . '</p></div>';
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$where  = is_object( $screen ) ? (string) $screen->id : '';
		if ( ! in_array( $where, array( 'update-core', 'dashboard', 'update-core-network', 'dashboard-network' ), true ) ) {
			return;
		}
		if ( ! Digitizer_Update_Hold_Settings::may_decide() ) {
			return;
		}

		foreach ( Digitizer_Update_Hold_Core::held_majors() as $branch => $held ) {
			$this->render_hold_notice( $branch, $held );
		}
	}

	/**
	 * One notice for one held branch.
	 *
	 * @param string $branch Branch string.
	 * @param array  $held   version, seen and until timestamps.
	 */
	private function render_hold_notice( $branch, $held ) {
		$format = get_option( 'date_format' );
		?>
		<div class="notice notice-info">
			<p>
				<strong>
					<?php
					printf(
						/* translators: %s: WordPress version */
						esc_html__( 'WordPress %s is available, and this site is holding it back.', 'digitizer-update-hold' ),
						esc_html( $held['version'] )
					);
					?>
				</strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: date the update was first offered, 2: date the hold ends */
					esc_html__( 'It was first offered here on %1$s, and the hold ends on %2$s. Major releases are held so that the plugins and themes on this site have time to catch up with them; security and maintenance releases are installed as usual and are not affected.', 'digitizer-update-hold' ),
					esc_html( date_i18n( $format, (int) $held['seen'] ) ),
					esc_html( date_i18n( $format, (int) $held['until'] ) )
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'digitizer_update_hold_release_' . $branch ); ?>
				<input type="hidden" name="action" value="digitizer_update_hold_release" />
				<input type="hidden" name="branch" value="<?php echo esc_attr( $branch ); ?>" />
				<p>
					<button type="submit" class="button"><?php esc_html_e( 'Offer it now anyway', 'digitizer-update-hold' ); ?></button>
					<a class="button-link" href="<?php echo esc_url( self::settings_url() ); ?>"><?php esc_html_e( 'Update hold settings', 'digitizer-update-hold' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Save the hold length.
	 */
	public function handle_save() {
		if ( ! Digitizer_Update_Hold_Settings::may_decide() ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'digitizer-update-hold' ) );
		}
		check_admin_referer( 'digitizer_update_hold_settings' );

		$policy              = Digitizer_Update_Hold_Settings::all();
		$policy['hold_days'] = isset( $_POST['digitizer_update_hold_days'] ) ? absint( wp_unslash( $_POST['digitizer_update_hold_days'] ) ) : Digitizer_Update_Hold_Settings::DEFAULT_DAYS;
		Digitizer_Update_Hold_Settings::save( $policy );

		wp_safe_redirect( add_query_arg( 'saved', 1, self::settings_url() ) );
		exit;
	}

	/**
	 * Lift the hold on one release line, from the notice's button.
	 */
	public function handle_release() {
		$branch = isset( $_POST['branch'] ) ? sanitize_text_field( wp_unslash( $_POST['branch'] ) ) : '';
		if ( ! Digitizer_Update_Hold_Settings::may_decide() ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'digitizer-update-hold' ) );
		}
		check_admin_referer( 'digitizer_update_hold_release_' . $branch );

		Digitizer_Update_Hold_Core::release( $branch );

		wp_safe_redirect( is_multisite() ? network_admin_url( 'update-core.php' ) : admin_url( 'update-core.php' ) );
		exit;
	}

	/**
	 * The settings screen.
	 */
	public function render_page() {
		if ( ! Digitizer_Update_Hold_Settings::may_decide() ) {
			return;
		}
		$policy = Digitizer_Update_Hold_Settings::all();
		$held   = Digitizer_Update_Hold_Core::held_majors();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Digitizer Update Hold', 'digitizer-update-hold' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'A major WordPress release is held back for a while after this site is first offered it, so that the plugins and themes running here have time to catch up with it. Security and maintenance releases are never held: they are what keeps the site safe, and WordPress installs them on its own.', 'digitizer-update-hold' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'digitizer_update_hold_settings' ); ?>
				<input type="hidden" name="action" value="digitizer_update_hold_save" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="digitizer-update-hold-days"><?php esc_html_e( 'Hold major releases for', 'digitizer-update-hold' ); ?></label></th>
						<td>
							<input name="digitizer_update_hold_days" id="digitizer-update-hold-days" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr( (string) $policy['hold_days'] ); ?>" />
							<?php esc_html_e( 'days', 'digitizer-update-hold' ); ?>
							<p class="description"><?php esc_html_e( 'Counted from the day this site first saw the release, not from the day it was published. Set to 0 to hold nothing.', 'digitizer-update-hold' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Currently held', 'digitizer-update-hold' ); ?></h2>
			<?php if ( ! $held ) : ?>
				<p><?php esc_html_e( 'Nothing is being held back right now.', 'digitizer-update-hold' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $held as $branch => $row ) : ?>
						<li>
							<?php
							printf(
								/* translators: 1: WordPress version, 2: date the hold ends */
								esc_html__( 'WordPress %1$s, until %2$s', 'digitizer-update-hold' ),
								esc_html( $row['version'] ),
								esc_html( date_i18n( get_option( 'date_format' ), (int) $row['until'] ) )
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
