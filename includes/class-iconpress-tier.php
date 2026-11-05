<?php
/*
 * Project: IconPress development candidate.
 * Development version: 2026.10.31.001-dev
 * Copyright (C) 2026 Matthew C. Murphy.
 * Author: https://MatthewxMurphy.com; product/support: https://builtnotbegged.com.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
/**
 * IconPress tier gate: Free vs Pro.
 *
 * One codebase ships both tiers, the owner's model:
 * - Free: the preloaded wall at 16x16 and 32x32.
 * - Pro: adds 64x64 and 128x128 slot sizes and the Stripe store
 *   (automatic slot selling).
 *
 * Pro is active when any of these is true, in this order:
 * 1. The ICONPRESS_PRO constant is defined (the Pro package is
 *    produced by shipping this same tree with a one-line loader
 *    that defines it; see README.md, "How the Pro build is
 *    produced").
 * 2. The iconpress_pro_active filter returns true (a site can
 *    turn Pro on from its own code, for example a must-use
 *    plugin).
 * 3. A license key is saved in the plugin settings. The check
 *    here is a format check only (a non-empty key of at least 8
 *    characters): no license server exists yet, and nothing is
 *    sent anywhere. The iconpress_validate_license_key filter
 *    is the seam where a real validator plugs in later.
 *
 * @package IconPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IconPress_Tier {

	/**
	 * @return bool True when the Pro features are unlocked.
	 */
	public static function is_pro() {
		if ( defined( 'ICONPRESS_PRO' ) ) {
			return (bool) ICONPRESS_PRO;
		}
		$settings = IconPress_Settings::get();
		$key      = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';
		$licensed = '' !== $key && strlen( $key ) >= 8;
		/**
		 * Filters whether a saved license key counts as valid.
		 * Placeholder check: any key of 8 or more characters.
		 *
		 * @param bool   $licensed Whether the key passes.
		 * @param string $key      The saved license key.
		 */
		$licensed = (bool) apply_filters( 'iconpress_validate_license_key', $licensed, $key );
		/**
		 * Filters whether IconPress Pro is active on this site.
		 *
		 * @param bool $licensed Result of the license check.
		 */
		return (bool) apply_filters( 'iconpress_pro_active', $licensed );
	}

	/**
	 * Icon sizes the current tier may render, in pixels.
	 *
	 * @return int[]
	 */
	public static function sizes() {
		return self::is_pro() ? array( 16, 32, 64, 128 ) : array( 16, 32 );
	}

	/**
	 * Human label for a size, used in the admin.
	 *
	 * @param int $size Size in pixels.
	 * @return string
	 */
	public static function size_label( $size ) {
		$labels = array(
			16  => __( 'Small (16x16)', 'iconpress' ),
			32  => __( 'Medium (32x32)', 'iconpress' ),
			64  => __( 'Large (64x64)', 'iconpress' ),
			128 => __( 'Extra large (128x128)', 'iconpress' ),
		);
		return isset( $labels[ $size ] ) ? $labels[ $size ] : ( $size . 'x' . $size );
	}
}
