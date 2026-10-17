<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's dynamic blocks. meta-list/price exist because
 * core/post-meta bindings can only echo a raw field verbatim — they can't
 * turn multiline text into a list or apply the price type/value conditional
 * (see each block's render.php for the specific reason). rating/related-posts
 * replace WP-PostRatings/Contextual Related Posts as dependencies (decided
 * 2026-09-04, see class-pc-ratings.php).
 */
class PC_Blocks {

	public static function init() {
		add_filter( 'block_type_metadata', array( __CLASS__, 'set_asset_version' ) );
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_block_type( PC_PLUGIN_DIR . 'blocks/meta-list' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/price' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/rating' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/related-posts' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/contractor-filters' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/badges' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/promo' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/faq' );
		// 0.5.0 — v2 mockups (docs/mockups/partner-catalog-v2/).
		register_block_type( PC_PLUGIN_DIR . 'blocks/card' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/archive-head' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/archive-toolbar' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/archive-table' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/hero' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/tariffs' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/verify' );
		register_block_type( PC_PLUGIN_DIR . 'blocks/recommended' );
	}

	/**
	 * block.json's viewScript/style assets are cache-busted with its own
	 * top-level "version" field, NOT the plugin's PC_VERSION — left unset,
	 * WP falls back to a fixed value that never changes across a redeploy,
	 * so a browser/CDN cache can keep serving a stale view.js indefinitely
	 * after we ship a fix (same bug found live on the sibling
	 * llm-agent-github-repos plugin's repo-filters block). Stamp every
	 * block.json this plugin registers with PC_VERSION so the existing
	 * "bump PC_VERSION on deploy" habit actually busts these too.
	 */
	public static function set_asset_version( $metadata ) {
		if ( isset( $metadata['file'] ) && str_starts_with( $metadata['file'], wp_normalize_path( PC_PLUGIN_DIR ) ) ) {
			$metadata['version'] = PC_VERSION;
		}
		return $metadata;
	}
}
