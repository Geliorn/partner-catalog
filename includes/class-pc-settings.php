<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All plugin-wide settings live in one option array — same convention as
 * LAGR_Settings in llm-agent-github-repos.
 */
class PC_Settings {

	const OPTION_KEY = 'pc_settings';

	public static function defaults() {
		return array(
			// UTM template for the auto-generated mode. Per-contractor override
			// (_pc_utm_override meta) always wins when filled in — see
			// PC_Links::build_final_url(). Tokens: {slug}, {contractor_type}.
			'utm_source'            => 'llm-agent.ru',
			'utm_medium'            => 'partner-catalog',
			'utm_campaign_template' => 'contractor-{slug}',
			'utm_content_template'  => '{contractor_type}',

			// Yandex.Metrika counter ID for the client-side goals (partner_profile_view,
			// partner_click) — not assumed to already be available/discoverable on the
			// site, so it's a plain settings field rather than something we detect.
			'metrika_counter_id'    => '',

			// SEO plugin whose own meta field gets the short description — 'none' skips it.
			'seo_plugin'                    => 'none',

			// Feature on/off switches. `ratings`/`related_posts` used to mean "is the
			// third-party plugin active" — decided 2026-09-04 to bring both in-house
			// as the plugin's own code (class-pc-ratings.php/class-pc-related.php),
			// same call made for llm-agent-github-repos, so they're now plain feature
			// flags. Filter Everything stays genuinely third-party.
			'integration_ratings'       => 1, // own rating widget (partner-catalog/rating)
			'integration_related_posts' => 1, // own related-posts block (partner-catalog/related-posts)
			'integration_filters'       => 1, // own archive sidebar filters (partner-catalog/contractor-filters) — replaced Filter Everything 2026-09-29
		);
	}

	public static function init() {
		// Nothing to schedule — settings are just an option array, read on demand.
	}

	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $stored, self::defaults() );
	}

	public static function get( $key, $default = null ) {
		$all = self::get_all();
		return $all[ $key ] ?? $default;
	}

	public static function update( array $values ) {
		$all = self::get_all();
		foreach ( $values as $k => $v ) {
			$all[ $k ] = $v;
		}
		update_option( self::OPTION_KEY, $all );
	}

	/** Convenience wrapper for pattern/template code: PC_Settings::integration_enabled( 'ratings' ). */
	public static function integration_enabled( $name ) {
		return (bool) self::get( 'integration_' . $name, false );
	}
}
