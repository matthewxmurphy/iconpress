<?php
/*
 * Project: IconPress development candidate.
 * Development version: 2026.10.31.001-dev
 * Copyright (C) 2026 Matthew C. Murphy.
 * Author: https://MatthewxMurphy.com; product/support: https://builtnotbegged.com.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
/**
 * IconPress wall renderer.
 *
 * One renderer serves the shortcode, the Gutenberg block, and the
 * widget, the way the original mod_mafiaicons module was the single
 * front-end surface for the wall. Filled slots link out with the
 * configured target and rel; empty, unpublished, and expired slots
 * render as house ads using the default title, alt, and URL, the
 * behavior of MafiaIconsHelper::getIcons() in the original.
 *
 * The hover tooltip is pure CSS, driven by CSS custom properties
 * built from the settings, the modern equivalent of the original
 * generated stylesheet. No JavaScript, no jQuery, on the front end.
 *
 * @package IconPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IconPress_Render {

	/**
	 * Render the wall.
	 *
	 * @param array $atts Optional overrides: width, height, size (0 = use settings).
	 * @return string HTML.
	 */
	public static function wall( $atts = array() ) {
		$settings = IconPress_Settings::get();

		$width  = ! empty( $atts['width'] ) ? max( 1, min( 24, intval( $atts['width'] ) ) ) : (int) $settings['width'];
		$height = ! empty( $atts['height'] ) ? max( 1, min( 24, intval( $atts['height'] ) ) ) : (int) $settings['height'];
		$size   = ! empty( $atts['size'] ) ? intval( $atts['size'] ) : (int) $settings['size'];
		// Sizes are tier gated: free renders 16 and 32, Pro adds
		// 64 and 128. Anything outside the tier falls back to
		// the tier's own setting, then to 32.
		$allowed = IconPress_Tier::sizes();
		if ( ! in_array( $size, $allowed, true ) ) {
			$size = in_array( (int) $settings['size'], $allowed, true ) ? (int) $settings['size'] : 32;
		}

		$cells = $width * $height;
		$rows  = IconPress_DB::get_slots( $cells );

		self::enqueue_assets();

		$vars = array(
			'--ip-size:' . $size . 'px',
			'--ip-cols:' . $width,
		);
		if ( '' !== $settings['div_bg'] ) {
			$vars[] = '--ip-divbg:' . $settings['div_bg'];
		}
		if ( '' !== $settings['icon_bg'] ) {
			$vars[] = '--ip-iconbg:' . $settings['icon_bg'];
		}
		$vars[] = '--ip-hover-border:' . $settings['hover_border'];
		$vars[] = '--ip-hover-bg:' . $settings['hover_background'];
		$vars[] = '--ip-hover-weight:' . $settings['hover_weight'];
		$vars[] = '--ip-hover-opacity:' . $settings['hover_opacity'];

		$html = '<div class="iconpress-wall" style="' . esc_attr( implode( ';', $vars ) ) . '">';
		for ( $i = 0; $i < $cells; $i++ ) {
			$row   = isset( $rows[ $i ] ) ? $rows[ $i ] : null;
			$html .= self::cell( $row, $settings, $size );
		}
		$html .= '</div>';

		if ( ! empty( $settings['show_powered'] ) ) {
			$html .= '<div class="iconpress-powered">'
				. sprintf(
					/* translators: %s: IconPress plugin name, linked. */
					esc_html__( 'Powered by %s', 'iconpress' ),
					'<a href="https://matthewxmurphy.com" target="_blank" rel="noopener">IconPress</a>'
				)
				. '</div>';
		}

		return $html;
	}

	/**
	 * Render one grid cell.
	 *
	 * @param object|null $row      Slot row, or null for a virtual empty slot.
	 * @param array       $settings Settings array.
	 * @param int         $size     Icon size in px.
	 * @return string
	 */
	private static function cell( $row, $settings, $size ) {
		$clear_icon = ICONPRESS_URL . 'assets/images/clear.gif';

		if ( $row && IconPress_DB::is_filled( $row ) ) {
			$title = (string) $row->title;
			$alt   = '' !== trim( (string) $row->alt ) ? (string) $row->alt : $title;
			$icon  = self::icon_src( $row, $clear_icon );
			$href  = self::outbound_href( $row, $settings );
			$rel   = '' !== trim( (string) $row->rel ) ? (string) $row->rel : (string) $settings['default_rel'];
			$class = 'iconpress-cell';
		} else {
			$title = (string) $settings['default_title'];
			$alt   = '' !== trim( (string) $settings['default_alt'] ) ? (string) $settings['default_alt'] : $title;
			// The fallback cell carries the bundled AD tile, not a
			// blank: no slot on the wall ever renders naked, even
			// one the seeder has not reached.
			$icon  = ICONPRESS_URL . 'assets/images/defaults/advertise-32.png';
			$href  = '' !== trim( (string) $settings['default_url'] ) ? (string) $settings['default_url'] : self::current_url();
			$rel   = (string) $settings['default_rel'];
			$class = 'iconpress-cell iconpress-empty';
		}

		// The original rel vocabulary: "follow" means no rel attribute
		// beyond the target behavior; anything else is printed as is.
		$rel_attr = ( 'follow' === $rel ) ? '' : ' rel="' . esc_attr( $rel ) . '"';
		if ( '_blank' === $settings['link_target'] && false === strpos( $rel, 'noopener' ) && 'follow' !== $rel ) {
			$rel_attr = ' rel="' . esc_attr( trim( $rel . ' noopener' ) ) . '"';
		} elseif ( '_blank' === $settings['link_target'] && 'follow' === $rel ) {
			$rel_attr = ' rel="noopener"';
		}

		$tip = '<span class="iconpress-tip"><span class="iconpress-tip-title">' . esc_html( $title ) . '</span>';
		if ( $alt !== $title && '' !== $alt ) {
			$tip .= '<span class="iconpress-tip-text">' . esc_html( $alt ) . '</span>';
		}
		$tip .= '</span>';

		return '<div class="' . esc_attr( $class ) . '">'
			. '<a href="' . esc_url( $href ) . '" target="' . esc_attr( $settings['link_target'] ) . '"' . $rel_attr . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $rel_attr is built from a whitelist and escaped above.
			. '<img src="' . esc_url( $icon ) . '" width="' . intval( $size ) . '" height="' . intval( $size ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">'
			. $tip
			. '</a></div>';
	}

	/**
	 * Resolve the icon image URL for a filled slot.
	 *
	 * @param object $row     Slot row.
	 * @param string $fallback Placeholder image URL.
	 * @return string
	 */
	private static function icon_src( $row, $fallback ) {
		if ( ! empty( $row->icon_id ) ) {
			$url = wp_get_attachment_image_url( (int) $row->icon_id, 'full' );
			if ( $url ) {
				return $url;
			}
		}
		if ( ! empty( $row->icon_url ) ) {
			return IconPress_DB::resolve_icon_url( (string) $row->icon_url );
		}
		return $fallback;
	}

	/**
	 * Where a click on a filled slot goes: through the counting
	 * endpoint when tracking is on (the original hit task), or
	 * straight to the advertiser URL when it is off.
	 *
	 * @param object $row      Slot row.
	 * @param array  $settings Settings array.
	 * @return string
	 */
	private static function outbound_href( $row, $settings ) {
		if ( ! empty( $settings['track_clicks'] ) ) {
			return add_query_arg( 'iconpress_click', (int) $row->id, home_url( '/' ) );
		}
		return (string) $row->url;
	}

	/**
	 * Best current page URL for house ads without a default URL,
	 * matching the v1.0 module, which linked empty slots to the
	 * page being viewed.
	 *
	 * @return string
	 */
	private static function current_url() {
		$permalink = get_permalink();
		return $permalink ? $permalink : home_url( '/' );
	}

	/**
	 * Register and enqueue the front-end stylesheet once.
	 */
	private static function enqueue_assets() {
		wp_enqueue_style( 'iconpress', ICONPRESS_URL . 'assets/css/iconpress.css', array(), ICONPRESS_VERSION );
	}

	/**
	 * Shortcode handler: [iconpress_wall width="" height="" size=""].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'width'  => 0,
				'height' => 0,
				'size'   => 0,
			),
			is_array( $atts ) ? $atts : array(),
			'iconpress_wall'
		);
		return self::wall( $atts );
	}
}
