<?php
/**
 * Plugin Name:       IconPress
 * Plugin URI:        https://github.com/matthewxmurphy/iconpress
 * Description:       Development Free candidate. An icon wall of linked icons with placement slots, date windows, click counting, and a preloaded set of house icons. The WordPress rebuild of Mafia Icons from JoomlaMafia (2010). Display it with the [iconpress_wall] shortcode, the IconPress Wall block, or the IconPress Wall widget. Pro/store behavior is outside this candidate's verification.
 * Version:           2026.10.31.001-dev
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Matthew C. Murphy / Built Not Begged
 * Author URI:        https://MatthewxMurphy.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       iconpress
 * Domain Path:       /languages
 *
 * Copyright (C) 2026 Matthew Murphy / Built Not Begged.
 * Copyright (C) 2026 Matthew C. Murphy.
 * Maintained by Matthew C. Murphy, 2026, https://MatthewxMurphy.com.
 * SPDX-License-Identifier: GPL-2.0-or-later
 * Developed by MatthewxMurphy.com - https://matthewxmurphy.com
 * A Built Not Begged project - https://builtnotbegged.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 *
 * @package IconPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ICONPRESS_VERSION', '2026.10.31.001-dev' );
define( 'ICONPRESS_FILE', __FILE__ );
define( 'ICONPRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ICONPRESS_URL', plugin_dir_url( __FILE__ ) );

require_once ICONPRESS_DIR . 'includes/class-iconpress-settings.php';
require_once ICONPRESS_DIR . 'includes/class-iconpress-tier.php';
require_once ICONPRESS_DIR . 'includes/iconpress-seed-data.php';
require_once ICONPRESS_DIR . 'includes/class-iconpress-db.php';
require_once ICONPRESS_DIR . 'includes/class-iconpress-render.php';
require_once ICONPRESS_DIR . 'includes/class-iconpress-click.php';
require_once ICONPRESS_DIR . 'includes/class-iconpress-widget.php';
require_once ICONPRESS_DIR . 'includes/class-iconpress-store.php';

if ( is_admin() ) {
	require_once ICONPRESS_DIR . 'admin/class-iconpress-admin.php';
	IconPress_Admin::hooks();
}

register_activation_hook( __FILE__, array( 'IconPress_DB', 'install' ) );

IconPress_Store::hooks();

add_action( 'plugins_loaded', array( 'IconPress_DB', 'maybe_upgrade' ) );
add_action( 'init', array( 'IconPress_Click', 'init' ), 1 );
add_action( 'init', 'iconpress_register_block' );
add_action( 'widgets_init', 'iconpress_register_widget' );
add_shortcode( 'iconpress_wall', array( 'IconPress_Render', 'shortcode' ) );

/**
 * Register the IconPress Wall block with a server render callback.
 */
function iconpress_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	register_block_type(
		ICONPRESS_DIR . 'blocks/icon-wall',
		array(
			'render_callback' => 'iconpress_block_render',
		)
	);
}

/**
 * Block render callback. Shares the shortcode/widget renderer.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function iconpress_block_render( $attributes ) {
	return IconPress_Render::wall(
		array(
			'width'  => isset( $attributes['width'] ) ? intval( $attributes['width'] ) : 0,
			'height' => isset( $attributes['height'] ) ? intval( $attributes['height'] ) : 0,
			'size'   => isset( $attributes['size'] ) ? intval( $attributes['size'] ) : 0,
		)
	);
}

/**
 * Register the IconPress Wall widget.
 */
function iconpress_register_widget() {
	register_widget( 'IconPress_Widget' );
}
