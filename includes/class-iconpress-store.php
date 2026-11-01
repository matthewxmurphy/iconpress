<?php
/*
 * Project: IconPress development candidate.
 * Development version: 2026.10.31.001-dev
 * Copyright (C) 2026 Matthew C. Murphy.
 * Author: https://MatthewxMurphy.com; product/support: https://builtnotbegged.com.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
/**
 * IconPress store (Pro): automatic slot selling through Stripe.
 *
 * The flow:
 * 1. The [iconpress_buy] shortcode renders a small form: ad text,
 *    link URL, icon image URL (optional), email, and an optional
 *    slot pick. Price and duration come from the plugin settings,
 *    never from the request.
 * 2. On submit, an order row is created (status pending) and a
 *    Stripe Checkout session is opened with the Stripe API over
 *    HTTPS (no SDK bundled, plain wp_remote_post). The buyer pays
 *    on Stripe's hosted page and lands back on the site.
 * 3. Stripe calls the REST webhook (iconpress/v1/stripe-webhook).
 *    The signature is verified by hand against the webhook secret
 *    from the plugin settings. On checkout.session.completed the
 *    order is fulfilled: the first claimable slot (a seeded house
 *    entry first, then a truly empty slot, then an expired
 *    placement) is filled with the buyer's icon, link, and title,
 *    dated for the purchased duration. Expiry after that is the
 *    normal date-window logic: when the term ends the slot falls
 *    back to a house ad on its own.
 * 4. Moderation setting: auto (the slot goes live the moment the
 *    webhook lands) or hold (the slot is saved unpublished and
 *    the order is marked hold until an admin publishes it from
 *    the Icons Manager).
 *
 * Stripe keys and the webhook secret live only in the plugin
 * settings. They are never bundled, never hardcoded, and never
 * printed anywhere except the settings form itself.
 *
 * @package IconPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IconPress_Store {

	const NONCE_ACTION = 'iconpress_buy';
	const QUERY_ERROR  = 'iconpress_buy_error';
	const QUERY_DONE   = 'iconpress_bought';

	/**
	 * Wire the store hooks. Safe to call on the free tier too:
	 * every surface self-gates on IconPress_Tier::is_pro().
	 */
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'init', array( __CLASS__, 'maybe_handle_buy' ), 20 );
		add_shortcode( 'iconpress_buy', array( __CLASS__, 'buy_shortcode' ) );
	}

	/**
	 * Is the store open right now? Pro tier, sales switched on,
	 * and both Stripe keys present.
	 *
	 * @return bool
	 */
	public static function available() {
		if ( ! IconPress_Tier::is_pro() ) {
			return false;
		}
		$s = IconPress_Settings::get();
		return ! empty( $s['sales_enabled'] )
			&& '' !== trim( (string) $s['stripe_secret_key'] )
			&& '' !== trim( (string) $s['stripe_publishable_key'] );
	}

	/* ------------------------------------------------------------------
	 * Buy form
	 * ------------------------------------------------------------------ */

	/**
	 * The [iconpress_buy] shortcode: the public buy-a-slot form.
	 * Renders nothing when the store is not open, so a page can
	 * carry the shortcode safely before sales start.
	 *
	 * @return string
	 */
	public static function buy_shortcode() {
		if ( ! self::available() ) {
			return '';
		}
		$s = IconPress_Settings::get();

		wp_enqueue_style( 'iconpress', ICONPRESS_URL . 'assets/css/iconpress.css', array(), ICONPRESS_VERSION );

		$html = '<div class="iconpress-buy">';

		if ( isset( $_GET[ self::QUERY_DONE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag after the Stripe redirect.
			$html .= '<p class="iconpress-buy-done">' . esc_html__( 'Thank you. Your payment is confirmed and your slot is being placed on the wall.', 'iconpress' ) . '</p>';
		}
		if ( isset( $_GET[ self::QUERY_ERROR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only error code from our own redirect.
			$code  = sanitize_key( wp_unslash( $_GET[ self::QUERY_ERROR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$texts = array(
				'fields' => __( 'Please fill in the ad text, a valid link URL, and a valid email address.', 'iconpress' ),
				'slot'   => __( 'That slot is no longer available. Please pick another.', 'iconpress' ),
				'stripe' => __( 'The payment page could not be opened. Please try again in a moment.', 'iconpress' ),
				'spam'   => __( 'Your submission was flagged as spam.', 'iconpress' ),
			);
			if ( isset( $texts[ $code ] ) ) {
				$html .= '<p class="iconpress-buy-error">' . esc_html( $texts[ $code ] ) . '</p>';
			}
		}

		/* translators: 1: price, 2: currency code, 3: number of days. */
		$offer = sprintf(
			__( 'Buy a slot on the icon wall: %1$s %2$s for %3$d days. Your icon and link go live as soon as payment clears.', 'iconpress' ),
			number_format( (float) $s['slot_price'], 2 ),
			strtoupper( (string) $s['slot_currency'] ),
			(int) $s['slot_duration_days']
		);
		if ( 'hold' === $s['sales_moderation'] ) {
			$offer .= ' ' . esc_html__( 'New slots are reviewed by the site owner before they go live.', 'iconpress' );
		}

		$slots   = IconPress_DB::get_slots();
		$options = '<option value="0">' . esc_html__( 'First available slot', 'iconpress' ) . '</option>';
		foreach ( $slots as $row ) {
			if ( IconPress_DB::is_claimable( $row ) ) {
				/* translators: %d: slot position number. */
				$options .= '<option value="' . intval( $row->id ) . '">' . esc_html( sprintf( __( 'Slot %d', 'iconpress' ), (int) $row->ordering ) ) . '</option>';
			}
		}

		$html .= '<p class="iconpress-buy-offer">' . esc_html( $offer ) . '</p>'
			. '<form class="iconpress-buy-form" method="post" action="">'
			. wp_nonce_field( self::NONCE_ACTION, '_wpnonce', true, false )
			. '<p><label>' . esc_html__( 'Ad text', 'iconpress' ) . '<br>'
			. '<input type="text" name="ip_title" maxlength="250" required></label></p>'
			. '<p><label>' . esc_html__( 'Link URL', 'iconpress' ) . '<br>'
			. '<input type="text" name="ip_url" maxlength="500" required></label></p>'
			. '<p><label>' . esc_html__( 'Icon image URL (optional; your site favicon is used when empty)', 'iconpress' ) . '<br>'
			. '<input type="text" name="ip_icon_url" maxlength="500"></label></p>'
			. '<p><label>' . esc_html__( 'Email (for your receipt and renewal notices)', 'iconpress' ) . '<br>'
			. '<input type="email" name="ip_email" maxlength="190" required></label></p>'
			. '<p><label>' . esc_html__( 'Slot', 'iconpress' ) . '<br>'
			. '<select name="ip_slot">' . $options . '</select></label></p>'
			. '<p class="iconpress-buy-hp" style="display:none"><label>Leave this empty'
			. '<input type="text" name="ip_website" tabindex="-1" autocomplete="off"></label></p>'
			. '<p><button type="submit" name="iconpress_buy_submit" value="1">' . esc_html__( 'Continue to payment', 'iconpress' ) . '</button></p>'
			. '</form></div>';

		return $html;
	}

	/* ------------------------------------------------------------------
	 * Checkout
	 * ------------------------------------------------------------------ */

	/**
	 * Handle a buy form submission: validate, record the order,
	 * open a Stripe Checkout session, and send the buyer to it.
	 * Runs on init so the redirect can still fire.
	 */
	public static function maybe_handle_buy() {
		if ( ! isset( $_POST['iconpress_buy_submit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified on the next lines before anything is used.
			return;
		}
		if ( ! self::available() ) {
			return;
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );

		// Honeypot: a filled hidden field means a bot.
		if ( ! empty( $_POST['ip_website'] ) ) {
			wp_safe_redirect( add_query_arg( self::QUERY_ERROR, 'spam', $back ) );
			exit;
		}

		$title    = isset( $_POST['ip_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ip_title'] ) ) : '';
		$url      = isset( $_POST['ip_url'] ) ? trim( (string) wp_unslash( $_POST['ip_url'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Scheme handling and esc_url_raw happen immediately below.
		$icon_url = isset( $_POST['ip_icon_url'] ) ? esc_url_raw( wp_unslash( $_POST['ip_icon_url'] ) ) : '';
		$email    = isset( $_POST['ip_email'] ) ? sanitize_email( wp_unslash( $_POST['ip_email'] ) ) : '';
		$wanted   = isset( $_POST['ip_slot'] ) ? intval( $_POST['ip_slot'] ) : 0;

		if ( '' !== $url && ! preg_match( '#^(https?|ftp)://#i', $url ) ) {
			$url = 'https://' . $url;
		}
		$url = esc_url_raw( $url );

		if ( '' === $title || '' === $url || '' === $email || ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( self::QUERY_ERROR, 'fields', $back ) );
			exit;
		}
		if ( $wanted > 0 ) {
			$slot = IconPress_DB::get_slot( $wanted );
			if ( ! $slot || ! IconPress_DB::is_claimable( $slot ) ) {
				wp_safe_redirect( add_query_arg( self::QUERY_ERROR, 'slot', $back ) );
				exit;
			}
		}

		$s           = IconPress_Settings::get();
		$amount      = (int) round( (float) $s['slot_price'] * 100 );
		$order_id    = IconPress_DB::create_order(
			array(
				'wanted_slot'   => $wanted,
				'email'         => $email,
				'title'         => $title,
				'url'           => $url,
				'icon_url'      => $icon_url,
				'amount'        => $amount,
				'currency'      => strtolower( (string) $s['slot_currency'] ),
				'duration_days' => (int) $s['slot_duration_days'],
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql' ),
			)
		);
		if ( ! $order_id ) {
			wp_safe_redirect( add_query_arg( self::QUERY_ERROR, 'stripe', $back ) );
			exit;
		}

		$session = self::create_checkout_session( $order_id, $amount, $s, $email, $title, $back );
		if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
			IconPress_DB::update_order( $order_id, array( 'status' => 'failed' ) );
			wp_safe_redirect( add_query_arg( self::QUERY_ERROR, 'stripe', $back ) );
			exit;
		}
		IconPress_DB::update_order( $order_id, array( 'session_id' => (string) $session['id'] ) );

		wp_redirect( $session['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Stripe-hosted checkout URL.
		exit;
	}

	/**
	 * Create a Stripe Checkout session with the Stripe API.
	 * Price and duration come from the settings via the order,
	 * never from the buyer.
	 *
	 * @param int    $order_id Order id.
	 * @param int    $amount   Amount in the currency's smallest unit.
	 * @param array  $s        Settings.
	 * @param string $email    Buyer email.
	 * @param string $title    Ad text (used in the product name).
	 * @param string $back     Page the buyer came from.
	 * @return array|WP_Error Decoded session (id and url) or error.
	 */
	private static function create_checkout_session( $order_id, $amount, $s, $email, $title, $back ) {
		$success_base = '' !== trim( (string) $s['buy_success_url'] ) ? (string) $s['buy_success_url'] : $back;
		$cancel_base  = '' !== trim( (string) $s['buy_cancel_url'] ) ? (string) $s['buy_cancel_url'] : $back;
		$duration     = (int) $s['slot_duration_days'];

		$body = array(
			'mode'                => 'payment',
			'success_url'         => add_query_arg( self::QUERY_DONE, '1', $success_base ),
			'cancel_url'          => $cancel_base,
			'customer_email'      => $email,
			'client_reference_id' => (string) $order_id,
			'line_items'          => array(
				array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => strtolower( (string) $s['slot_currency'] ),
						'unit_amount'  => $amount,
						'product_data' => array(
							/* translators: 1: ad text, 2: number of days. */
							'name' => sprintf( __( 'Icon wall slot: %1$s (%2$d days)', 'iconpress' ), $title, $duration ),
						),
					),
				),
			),
			'metadata'            => array( 'iconpress_order_id' => (string) $order_id ),
			'payment_intent_data' => array(
				'metadata' => array( 'iconpress_order_id' => (string) $order_id ),
			),
		);

		$response = wp_remote_post(
			'https://api.stripe.com/v1/checkout/sessions',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . trim( (string) $s['stripe_secret_key'] ),
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) ) {
			return new WP_Error( 'stripe_api', __( 'Stripe rejected the checkout session request.', 'iconpress' ) );
		}
		return $data;
	}

	/* ------------------------------------------------------------------
	 * Webhook
	 * ------------------------------------------------------------------ */

	/**
	 * Register the webhook route: POST /wp-json/iconpress/v1/stripe-webhook.
	 * Public by necessity (Stripe calls it); every call is
	 * authenticated by the Stripe signature instead.
	 */
	public static function register_routes() {
		register_rest_route(
			'iconpress/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Webhook entry point.
	 *
	 * @param WP_REST_Request $request The Stripe event call.
	 * @return WP_REST_Response
	 */
	public static function handle_webhook( $request ) {
		$s      = IconPress_Settings::get();
		$secret = trim( (string) $s['stripe_webhook_secret'] );
		if ( '' === $secret ) {
			return new WP_REST_Response( array( 'error' => 'webhook_not_configured' ), 400 );
		}
		$payload   = $request->get_body();
		$signature = (string) $request->get_header( 'stripe_signature' );
		if ( ! self::verify_signature( $payload, $signature, $secret ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_signature' ), 400 );
		}

		$event = json_decode( $payload );
		if ( ! $event || ! isset( $event->type ) ) {
			return new WP_REST_Response( array( 'error' => 'bad_payload' ), 400 );
		}
		if ( 'checkout.session.completed' !== $event->type ) {
			return new WP_REST_Response( array( 'received' => true, 'handled' => false ), 200 );
		}

		$session  = $event->data->object;
		$order_id = 0;
		if ( isset( $session->metadata->iconpress_order_id ) ) {
			$order_id = intval( $session->metadata->iconpress_order_id );
		} elseif ( isset( $session->client_reference_id ) ) {
			$order_id = intval( $session->client_reference_id );
		}
		$order = $order_id > 0 ? IconPress_DB::get_order( $order_id ) : null;
		if ( ! $order && isset( $session->id ) ) {
			$order = IconPress_DB::get_order_by_session( (string) $session->id );
		}
		if ( ! $order ) {
			// Unknown order: acknowledge so Stripe stops retrying.
			return new WP_REST_Response( array( 'received' => true, 'handled' => false ), 200 );
		}
		if ( in_array( $order->status, array( 'complete', 'hold', 'unplaced' ), true ) ) {
			// Already fulfilled: Stripe retries are expected and safe.
			return new WP_REST_Response( array( 'received' => true, 'handled' => true ), 200 );
		}
		if ( isset( $session->payment_status ) && 'paid' !== $session->payment_status ) {
			return new WP_REST_Response( array( 'received' => true, 'handled' => false ), 200 );
		}

		self::fulfill_order( $order, $s );
		return new WP_REST_Response( array( 'received' => true, 'handled' => true ), 200 );
	}

	/**
	 * Stripe webhook signature check, done by hand (no SDK):
	 * HMAC SHA256 of "<timestamp>.<payload>" with the webhook
	 * secret, compared in constant time against every v1
	 * signature in the header, with a five minute tolerance.
	 *
	 * @param string $payload Raw request body.
	 * @param string $header  Stripe-Signature header value.
	 * @param string $secret  Webhook signing secret.
	 * @return bool
	 */
	private static function verify_signature( $payload, $header, $secret ) {
		$timestamp  = null;
		$signatures = array();
		foreach ( explode( ',', (string) $header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $kv ) ) {
				continue;
			}
			if ( 't' === $kv[0] ) {
				$timestamp = $kv[1];
			} elseif ( 'v1' === $kv[0] ) {
				$signatures[] = $kv[1];
			}
		}
		if ( null === $timestamp || ! $signatures ) {
			return false;
		}
		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Place a paid order on the wall.
	 *
	 * @param object $order Order row.
	 * @param array  $s     Settings.
	 */
	private static function fulfill_order( $order, $s ) {
		$slot  = IconPress_DB::find_claimable_slot( (int) $order->wanted_slot );
		$today = current_time( 'Y-m-d' );
		$days  = max( 1, (int) $order->duration_days );

		$icon_url = trim( (string) $order->icon_url );
		if ( '' === $icon_url ) {
			// No icon supplied: use the buyer site's own favicon,
			// the same pattern the original sample data used.
			$host   = wp_parse_url( (string) $order->url, PHP_URL_HOST );
			$scheme = wp_parse_url( (string) $order->url, PHP_URL_SCHEME );
			if ( $host ) {
				$icon_url = ( $scheme ? $scheme : 'https' ) . '://' . $host . '/favicon.ico';
			}
		}

		if ( ! $slot ) {
			// Paid, but the wall is full of live placements. The
			// order stays visible on the Sales screen for the
			// admin to place by hand.
			IconPress_DB::update_order(
				(int) $order->id,
				array(
					'status'  => 'unplaced',
					'paid_at' => current_time( 'mysql' ),
				)
			);
			/** This action fires when a paid order cannot be auto-placed. */
			do_action( 'iconpress_order_unplaced', (int) $order->id );
			return;
		}

		$hold = 'hold' === $s['sales_moderation'];
		IconPress_DB::save_slot(
			array(
				'title'      => (string) $order->title,
				'url'        => (string) $order->url,
				'icon_url'   => $icon_url,
				'icon_id'    => 0,
				'alt'        => (string) $order->title,
				'email'      => (string) $order->email,
				'hits'       => 0,
				'published'  => $hold ? 0 : 1,
				'start_date' => $today,
				'end_date'   => gmdate( 'Y-m-d', strtotime( $today . ' +' . ( $days - 1 ) . ' days' ) ),
				'price'      => (int) $order->amount / 100,
				/* translators: %d: order number. */
				'notes'      => sprintf( __( 'Sold through the IconPress store (Stripe). Order %d.', 'iconpress' ), (int) $order->id ),
				'rel'        => 'sponsored',
				'is_default' => 0,
			),
			(int) $slot->id
		);
		IconPress_DB::update_order(
			(int) $order->id,
			array(
				'status'  => $hold ? 'hold' : 'complete',
				'slot_id' => (int) $slot->id,
				'paid_at' => current_time( 'mysql' ),
			)
		);
		/** This action fires after a paid order is placed on the wall. */
		do_action( 'iconpress_order_paid', (int) $order->id, (int) $slot->id );
	}
}
