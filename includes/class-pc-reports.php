<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * «Сообщить о неточности» (v2 mockup, under the supplier page): a visitor
 * picks a reason from a fixed list — no free text, so nothing personal is
 * collected (same reasoning as the text-less ratings, see PC_Ratings).
 *
 * Counts per reason live in `_pc_reports` (reason => count) and show up as a
 * column in the contractor list. Updating «Условия проверены редакцией»
 * (`_pc_checked`) clears them: a fresh check answers the old reports.
 * One report per browser per supplier per day (cookie), no other anti-spam —
 * it is a hint for the editor, not a ticket system.
 */
class PC_Reports {

	const META         = '_pc_reports';
	const NONCE_ACTION = 'pc_report';

	/** reason key => label (keys are stored, don't rename). */
	public static function reasons() {
		return array(
			'price'  => 'Цена или тарифы',
			'vpn'    => 'Перестал работать без VPN',
			'promo'  => 'Промокод не действует',
			'closed' => 'Сервис закрылся',
			'other'  => 'Другое',
		);
	}

	public static function init() {
		add_action( 'wp_ajax_pc_report', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_pc_report', array( __CLASS__, 'handle' ) );
		add_action( 'added_post_meta', array( __CLASS__, 'maybe_reset' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'maybe_reset' ), 10, 3 );
		add_filter( 'manage_contractor_posts_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_contractor_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
	}

	/** @return array<string,int> */
	public static function get( $post_id ) {
		$raw = get_post_meta( $post_id, self::META, true );
		return is_array( $raw ) ? array_map( 'intval', $raw ) : array();
	}

	public static function handle() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$reason  = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
		if ( ! $post_id || 'contractor' !== get_post_type( $post_id ) || ! isset( self::reasons()[ $reason ] ) ) {
			wp_send_json_error( array( 'message' => 'Некорректный запрос.' ), 400 );
		}

		$cookie = 'pc_reported_' . $post_id;
		if ( isset( $_COOKIE[ $cookie ] ) ) {
			wp_send_json_success( array( 'message' => 'Спасибо, сообщение уже получено.' ) );
		}

		$reports            = self::get( $post_id );
		$reports[ $reason ] = ( $reports[ $reason ] ?? 0 ) + 1;
		update_post_meta( $post_id, self::META, $reports );

		setcookie( $cookie, '1', time() + DAY_IN_SECONDS, ( COOKIEPATH ? COOKIEPATH : '/' ), COOKIE_DOMAIN );

		wp_send_json_success( array( 'message' => 'Спасибо! Редакция перепроверит условия.' ) );
	}

	/** A new «проверено» date clears the reports it answers. */
	public static function maybe_reset( $meta_id, $post_id, $meta_key ) {
		if ( '_pc_checked' === $meta_key ) {
			delete_post_meta( $post_id, self::META );
		}
	}

	public static function add_column( $columns ) {
		$columns['pc_reports'] = 'Неточности';
		return $columns;
	}

	public static function render_column( $column, $post_id ) {
		if ( 'pc_reports' !== $column ) {
			return;
		}
		$reports = self::get( $post_id );
		if ( ! $reports ) {
			echo '—';
			return;
		}
		$parts = array();
		foreach ( $reports as $reason => $n ) {
			$parts[] = ( self::reasons()[ $reason ] ?? $reason ) . ': ' . $n;
		}
		printf( '<strong style="color:#b32d2e" title="%1$s">%2$d</strong>', esc_attr( implode( "\n", $parts ) ), (int) array_sum( $reports ) );
	}
}
