<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outbound links to a partner's own site never point straight at that site —
 * they go through /out/{id}/ first, which (a) counts a click in PC_Stats and
 * (b) appends UTM parameters, then 302-redirects. One endpoint, two jobs, no
 * duplicated logic. See docs/partner-catalog-plan.md section 7.
 *
 * Patterns need this URL inside a Query Loop (card partial reused in the
 * archive/related blocks), where only Block Bindings' core/post-meta source
 * works reliably per post (a shortcode would resolve against the wrong post —
 * same issue already documented for the ratings shortcode in the GitHub
 * plugin). There is no real "_pc_outbound_url" postmeta row — get_post_metadata
 * is short-circuited below to compute it on the fly, purely so the button's
 * url binding in class-pc-patterns.php has something to read.
 */
class PC_Links {

	const QUERY_VAR = 'pc_out';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rule' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_redirect' ) );
		add_filter( 'get_post_metadata', array( __CLASS__, 'provide_virtual_outbound_url' ), 10, 4 );
		add_filter( 'is_protected_meta', array( __CLASS__, 'unprotect_virtual_key' ), 10, 2 );
	}

	public static function register_rewrite_rule() {
		add_rewrite_rule( '^out/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	public static function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function build_redirect_url( $contractor_id ) {
		return home_url( '/out/' . (int) $contractor_id . '/' );
	}

	public static function provide_virtual_outbound_url( $value, $object_id, $meta_key, $single ) {
		if ( '_pc_outbound_url' !== $meta_key ) {
			return $value;
		}
		$url = self::build_redirect_url( $object_id );
		return $single ? $url : array( $url );
	}

	public static function unprotect_virtual_key( $protected, $meta_key ) {
		if ( '_pc_outbound_url' === $meta_key ) {
			return false;
		}
		return $protected;
	}

	public static function maybe_handle_redirect() {
		$raw = get_query_var( self::QUERY_VAR );
		if ( '' === $raw || null === $raw ) {
			return;
		}

		$contractor_id = absint( $raw );
		$post          = $contractor_id ? get_post( $contractor_id ) : null;

		if ( ! $post || 'contractor' !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$website = get_post_meta( $contractor_id, '_pc_website', true );
		if ( empty( $website ) ) {
			wp_safe_redirect( get_permalink( $contractor_id ) );
			exit;
		}

		PC_Stats::increment( $contractor_id, 'click' );

		wp_redirect( esc_url_raw( self::build_final_url( $contractor_id, $website ) ), 302 );
		exit;
	}

	/**
	 * Manual override (per-contractor `_pc_utm_override`) always wins when
	 * filled in; otherwise builds from the global template in settings, with
	 * {slug}/{contractor_type} token substitution. Either way, the partner's
	 * real URL is only ever touched here — never printed raw in a pattern.
	 */
	public static function build_final_url( $contractor_id, $website ) {
		$override = get_post_meta( $contractor_id, '_pc_utm_override', true );
		if ( ! empty( $override ) ) {
			parse_str( $override, $params );
			return add_query_arg( array_map( 'sanitize_text_field', $params ), $website );
		}

		$post  = get_post( $contractor_id );
		$slug  = $post ? $post->post_name : '';
		$types = wp_get_post_terms( $contractor_id, 'contractor_type', array( 'fields' => 'names' ) );
		$type  = is_array( $types ) && ! empty( $types ) ? implode( '-', $types ) : '';

		$settings = PC_Settings::get_all();
		$tokens   = array( '{slug}', '{contractor_type}' );
		$replace  = array( $slug, $type );

		$params = array_filter(
			array(
				'utm_source'   => $settings['utm_source'],
				'utm_medium'   => $settings['utm_medium'],
				'utm_campaign' => str_replace( $tokens, $replace, $settings['utm_campaign_template'] ),
				'utm_content'  => str_replace( $tokens, $replace, $settings['utm_content_template'] ),
			)
		);

		return add_query_arg( array_map( 'sanitize_text_field', $params ), $website );
	}
}
