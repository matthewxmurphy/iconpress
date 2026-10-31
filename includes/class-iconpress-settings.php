<?php
/**
 * IconPress settings.
 *
 * One option array (iconpress_settings) holds the wall configuration.
 * The keys map to the original Mafia Icons settings:
 * v1.0 stored them in the #__mafiaicons_config table row
 * (Size, Wide, Tall, nofollow, Target, IconBG, DivBG, HvrBorder,
 * HvrBG, HvrFntW, HvrOpac); v1.1 moved them to component params
 * (size, width, height, default_title, default_url, default_alt,
 * target, hover_border, hover_background, hover_weight,
 * hover_opacity, divbg).
 *
 * @package IconPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IconPress_Settings {

	const OPTION_KEY = 'iconpress_settings';

	/**
	 * Shipped defaults, taken from the originals.
	 * size 32, width 6, height 4 and the hover values are the
	 * com_mafiaicons v1.1 manifest defaults (April 2010).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'size'                 => 32,
			'width'                => 6,
			'height'               => 4,
			'default_title'        => 'Advertise YOUR Site Here!',
			'default_url'          => '',
			'default_alt'          => '',
			'link_target'          => '_blank',
			'default_rel'          => 'nofollow',
			'icon_bg'              => '',
			'div_bg'               => '#dddddd',
			'hover_border'         => '#ffffff',
			'hover_background'     => '#006699',
			'hover_weight'         => '900',
			'hover_opacity'        => '0.80',
			'track_clicks'         => 1,
			'show_powered'         => 1,
			// Pro tier and store. The Stripe values are the site
			// owner's own keys, entered on the settings screen.
			// They are never bundled and never hardcoded.
			'license_key'          => '',
			'sales_enabled'        => 0,
			'stripe_publishable_key' => '',
			'stripe_secret_key'    => '',
			'stripe_webhook_secret' => '',
			'slot_price'           => '15.00',
			'slot_currency'        => 'usd',
			'slot_duration_days'   => 30,
			'sales_moderation'     => 'auto',
			'buy_success_url'      => '',
			'buy_cancel_url'       => '',
		);
	}

	/**
	 * @return array Current settings merged over the defaults.
	 */
	public static function get() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/**
	 * Sanitize a raw settings array (used by register_setting and saves).
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$out = self::defaults();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		// The Pro fields are only rendered on the settings form
		// when they apply, so keys absent from a submission keep
		// their saved values instead of resetting to defaults.
		$current = self::get();

		// Sizes are tier gated: the free tier renders 16 and 32.
		// Pro adds 64 and 128. (The 2026.10.1 build also allowed
		// 48; the owner's tier split retires it, and a saved 48
		// falls back to 32 here.)
		$size    = isset( $input['size'] ) ? intval( $input['size'] ) : 32;
		$allowed = class_exists( 'IconPress_Tier' ) ? IconPress_Tier::sizes() : array( 16, 32 );
		$out['size'] = in_array( $size, $allowed, true ) ? $size : 32;

		$out['width']  = isset( $input['width'] ) ? max( 1, min( 24, intval( $input['width'] ) ) ) : 6;
		$out['height'] = isset( $input['height'] ) ? max( 1, min( 24, intval( $input['height'] ) ) ) : 4;

		$out['default_title'] = isset( $input['default_title'] ) ? sanitize_text_field( $input['default_title'] ) : $out['default_title'];
		$out['default_url']   = isset( $input['default_url'] ) ? esc_url_raw( $input['default_url'] ) : '';
		$out['default_alt']   = isset( $input['default_alt'] ) ? sanitize_text_field( $input['default_alt'] ) : '';

		$targets = array( '_blank', '_self', '_parent', '_top' );
		$out['link_target'] = ( isset( $input['link_target'] ) && in_array( $input['link_target'], $targets, true ) ) ? $input['link_target'] : '_blank';

		$rels = array( 'nofollow', 'sponsored', 'nofollow sponsored', 'follow', 'external' );
		$out['default_rel'] = ( isset( $input['default_rel'] ) && in_array( $input['default_rel'], $rels, true ) ) ? $input['default_rel'] : 'nofollow';

		$out['icon_bg']          = isset( $input['icon_bg'] ) ? self::sanitize_color( $input['icon_bg'], '' ) : '';
		$out['div_bg']           = isset( $input['div_bg'] ) ? self::sanitize_color( $input['div_bg'], '#dddddd' ) : '#dddddd';
		$out['hover_border']     = isset( $input['hover_border'] ) ? self::sanitize_color( $input['hover_border'], '#ffffff' ) : '#ffffff';
		$out['hover_background'] = isset( $input['hover_background'] ) ? self::sanitize_color( $input['hover_background'], '#006699' ) : '#006699';

		$weight = isset( $input['hover_weight'] ) ? preg_replace( '/[^0-9a-zA-Z]/', '', (string) $input['hover_weight'] ) : '900';
		$out['hover_weight'] = $weight !== '' ? $weight : '900';

		$opacity = isset( $input['hover_opacity'] ) ? floatval( $input['hover_opacity'] ) : 0.8;
		$out['hover_opacity'] = number_format( max( 0, min( 1, $opacity ) ), 2, '.', '' );

		$out['track_clicks'] = ! empty( $input['track_clicks'] ) ? 1 : 0;
		$out['show_powered'] = ! empty( $input['show_powered'] ) ? 1 : 0;

		// Pro tier and store settings.
		$out['license_key'] = isset( $input['license_key'] ) ? sanitize_text_field( $input['license_key'] ) : $current['license_key'];

		// A checkbox: when the store section was part of the
		// submitted form (its price field is present), an absent
		// checkbox means off. When the section was not rendered
		// at all (free tier), keep the saved value.
		$store_section_posted = isset( $input['slot_price'] );
		$out['sales_enabled'] = $store_section_posted
			? ( ! empty( $input['sales_enabled'] ) ? 1 : 0 )
			: $current['sales_enabled'];

		foreach ( array( 'stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret' ) as $key_field ) {
			$out[ $key_field ] = isset( $input[ $key_field ] ) ? sanitize_text_field( $input[ $key_field ] ) : $current[ $key_field ];
		}

		if ( isset( $input['slot_price'] ) ) {
			$out['slot_price'] = number_format( max( 0, floatval( $input['slot_price'] ) ), 2, '.', '' );
		} else {
			$out['slot_price'] = $current['slot_price'];
		}

		if ( isset( $input['slot_currency'] ) ) {
			$currency = preg_replace( '/[^a-z]/', '', strtolower( (string) $input['slot_currency'] ) );
			$out['slot_currency'] = 3 === strlen( $currency ) ? $currency : 'usd';
		} else {
			$out['slot_currency'] = $current['slot_currency'];
		}

		$out['slot_duration_days'] = isset( $input['slot_duration_days'] )
			? max( 1, min( 3650, intval( $input['slot_duration_days'] ) ) )
			: $current['slot_duration_days'];

		$out['sales_moderation'] = ( isset( $input['sales_moderation'] ) && in_array( $input['sales_moderation'], array( 'auto', 'hold' ), true ) )
			? $input['sales_moderation']
			: ( isset( $input['sales_moderation'] ) ? 'auto' : $current['sales_moderation'] );

		$out['buy_success_url'] = isset( $input['buy_success_url'] ) ? esc_url_raw( $input['buy_success_url'] ) : $current['buy_success_url'];
		$out['buy_cancel_url']  = isset( $input['buy_cancel_url'] ) ? esc_url_raw( $input['buy_cancel_url'] ) : $current['buy_cancel_url'];

		return $out;
	}

	/**
	 * Accept only simple CSS color values (hex or named), else fallback.
	 *
	 * @param string $value    Raw value.
	 * @param string $fallback Fallback when invalid.
	 * @return string
	 */
	private static function sanitize_color( $value, $fallback ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return $fallback;
		}
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ) {
			return strtolower( $value );
		}
		if ( preg_match( '/^[a-zA-Z]{1,20}$/', $value ) ) {
			return strtolower( $value );
		}
		return $fallback;
	}
}
