<?php
/**
 * IconPress click-through endpoint.
 *
 * The original counted outbound clicks through a hit task
 * (mafiaicons_detail::hit() in v1.0) and a module redirector
 * (mod_mafiaicons_cnt.php): record the hit, then redirect to the
 * advertiser. IconPress does the same on ?iconpress_click=<id>.
 *
 * @package IconPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IconPress_Click {

	/**
	 * Hook the endpoint early on init.
	 */
	public static function init() {
		if ( ! isset( $_GET['iconpress_click'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public redirect endpoint; the value is an integer slot id and no state changes beyond a hit counter.
			return;
		}

		$id  = intval( $_GET['iconpress_click'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$row = $id > 0 ? IconPress_DB::get_slot( $id ) : null;

		if ( $row && IconPress_DB::is_filled( $row ) ) {
			IconPress_DB::add_hit( $id );
			nocache_headers();
			wp_redirect( esc_url_raw( (string) $row->url ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- The destination is the advertiser URL stored by an admin; an external redirect is the entire purpose of this endpoint.
			exit;
		}

		nocache_headers();
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
}
