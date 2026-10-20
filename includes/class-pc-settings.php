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
			'utm_campaign_template' => 'service-{slug}', // was contractor-{slug} until 0.5.0 (section renamed «Сервисы»)
			'utm_content_template'  => '{contractor_type}',

			// Yandex.Metrika counter ID for the client-side goals (partner_profile_view,
			// partner_click) — not assumed to already be available/discoverable on the
			// site, so it's a plain settings field rather than something we detect.
			'metrika_counter_id'    => '',

			// SEO plugin whose own meta field gets the short description — 'none' skips it.
			'seo_plugin'                    => 'none',

			// AI description generation (PC_LLM_Client). Provider + credentials;
			// generation is on-demand via buttons, so there's no enable flag.
			'ai_provider'           => 'deepseek', // deepseek | qwen
			'deepseek_api_key'      => '',
			'deepseek_model'        => 'deepseek-chat',
			'qwen_api_key'          => '',
			'qwen_model'            => 'qwen-plus',
			'qwen_base_url'         => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',

			// What the generator fills (PC_LLM_Client::fields()), and whether it may
			// replace a field that already has content. Off = only empty fields are
			// written, so manual edits and CSV data are never lost by accident.
			'gen_short'             => 1,
			'gen_about'             => 1,
			'gen_features'          => 0,
			'gen_faq'               => 0,
			'gen_overwrite'         => 0,
			'gen_prompt'            => '', // '' = PC_LLM_Client::DEFAULT_PROMPT

			// Feature on/off switches. `ratings`/`related_posts` used to mean "is the
			// third-party plugin active" — decided 2026-09-04 to bring both in-house
			// as the plugin's own code (class-pc-ratings.php/class-pc-related.php),
			// same call made for llm-agent-github-repos, so they're now plain feature
			// flags. Filter Everything stays genuinely third-party.
			'integration_ratings'       => 1, // own rating widget (partner-catalog/rating)
			'integration_related_posts' => 1, // own related-posts block (partner-catalog/related-posts)
			'integration_filters'       => 1, // own archive sidebar filters (partner-catalog/contractor-filters) — replaced Filter Everything 2026-09-29
			'integration_schema'        => 1, // JSON-LD on the single page (class-pc-schema.php) — off if an SEO plugin already emits its own graph for `contractor`
			'integration_recommend'     => 1, // «Где попробовать» module inside articles (class-pc-recommend.php)

			// Which archive layout the `partner-catalog/archive` pattern (used by
			// the theme's archive templates) renders: 'cards' (v2 mockup A —
			// sidebar filters + card grid/list) or 'table' (mockup B — quick
			// filters + comparison table). Both also exist as their own patterns.
			'archive_view'              => 'cards',

			// Post types the «Где попробовать» module is auto-inserted into
			// (after the 2nd H2). Comma-separated.
			'recommend_post_types'      => 'post',
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_migrate' ) );
	}

	/**
	 * 0.5.0: the section is «Сервисы», so the default UTM campaign became
	 * service-{slug}. A site that once saved its settings has the old default
	 * stored, which the new default would never replace — swap it once. A
	 * template someone customised is left alone.
	 */
	public static function maybe_migrate() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( is_array( $stored ) && isset( $stored['utm_campaign_template'] ) && 'contractor-{slug}' === $stored['utm_campaign_template'] ) {
			$stored['utm_campaign_template'] = 'service-{slug}';
			update_option( self::OPTION_KEY, $stored );
		}
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
