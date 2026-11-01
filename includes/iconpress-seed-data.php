<?php
/*
 * Project: IconPress development candidate.
 * Development version: 2026.10.31.001-dev
 * Copyright (C) 2026 Matthew C. Murphy.
 * Author: https://MatthewxMurphy.com; product/support: https://builtnotbegged.com.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
/**
 * IconPress seed data.
 *
 * The preloaded wall. The owner designed IconPress to ship full:
 * his own icons and links are in it from the first activation, and
 * every remaining slot is a house entry, so no slot ever renders
 * blank. This follows the original Mafia Icons behavior: the v1.0
 * install SQL seeded every slot with "Advertise YOUR Site Here!"
 * and a clear icon, v1.1 seeded 24 empty slot rows, and the shipped
 * defaults pointed house slots at the advertise page.
 *
 * Each entry is applied to the slot grid in order on activation,
 * cycling when the grid has more slots than entries. Every seeded
 * row is stored with is_default = 1, so the admin can edit,
 * replace, or clear any of them, and a Stripe purchase claims
 * seeded rows first.
 *
 * Icon values are file names inside assets/images/defaults/ and
 * are stored in the database with the "iconpress-default:" prefix,
 * resolved to real URLs at render time, so a site move never
 * breaks them.
 *
 * The house ad URL follows the original pattern: the advertise
 * page path on the owner's domain (the 2010 default was
 * http://www.joomlamafia.com/advertise-with-us.html). It is a
 * per-slot value and a site owner can point it anywhere.
 *
 * @package IconPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The seed dataset.
 *
 * @return array[] List of slot entries: title, url, icon, alt, rel.
 */
function iconpress_seed_entries() {
	$advertise = 'https://matthewxmurphy.com/advertise-with-us.html';
	$house_ad  = 'Advertise YOUR Site Here!';

	return array(
		// The owner's own entries first, a few of his own, as shipped.
		array(
			'title' => 'Matthew X Murphy',
			'url'   => 'https://matthewxmurphy.com',
			'icon'  => 'house-1.png',
			'alt'   => 'Matthew X Murphy',
			'rel'   => 'follow',
		),
		array(
			'title' => 'Built Not Begged',
			'url'   => 'https://matthewxmurphy.com',
			'icon'  => 'house-2.png',
			'alt'   => 'Built Not Begged',
			'rel'   => 'follow',
		),
		// House entries fill the rest of the wall, rotating the
		// bundled default icons. Even the blanks carry an icon.
		array(
			'title' => $house_ad,
			'url'   => $advertise,
			'icon'  => 'advertise-32.png',
			'alt'   => $house_ad,
			'rel'   => 'nofollow',
		),
		array(
			'title' => $house_ad,
			'url'   => $advertise,
			'icon'  => 'house-3.png',
			'alt'   => $house_ad,
			'rel'   => 'nofollow',
		),
		array(
			'title' => $house_ad,
			'url'   => $advertise,
			'icon'  => 'house-4.png',
			'alt'   => $house_ad,
			'rel'   => 'nofollow',
		),
		array(
			'title' => $house_ad,
			'url'   => $advertise,
			'icon'  => 'house-5.png',
			'alt'   => $house_ad,
			'rel'   => 'nofollow',
		),
		array(
			'title' => $house_ad,
			'url'   => $advertise,
			'icon'  => 'house-6.png',
			'alt'   => $house_ad,
			'rel'   => 'nofollow',
		),
		array(
			'title' => $house_ad,
			'url'   => $advertise,
			'icon'  => 'house-7.png',
			'alt'   => $house_ad,
			'rel'   => 'nofollow',
		),
		array(
			'title' => $house_ad,
			'url'   => $advertise,
			'icon'  => 'house-8.png',
			'alt'   => $house_ad,
			'rel'   => 'nofollow',
		),
		array(
			'title' => $house_ad,
			'url'   => $advertise,
			'icon'  => 'house-9.png',
			'alt'   => $house_ad,
			'rel'   => 'nofollow',
		),
		array(
			'title' => $house_ad,
			'url'   => $advertise,
			'icon'  => 'check-32.png',
			'alt'   => $house_ad,
			'rel'   => 'nofollow',
		),
		array(
			'title' => $house_ad,
			'url'   => $advertise,
			'icon'  => 'blank-32.png',
			'alt'   => $house_ad,
			'rel'   => 'nofollow',
		),
	);
}
