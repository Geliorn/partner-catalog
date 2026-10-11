<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes the short description into whichever SEO plugin's own meta field is
 * configured, so the meta description isn't left empty. 'none' skips this
 * entirely — mirrors LAGR_SEO in llm-agent-github-repos.
 */
class PC_SEO {

	public static function apply_meta_description( $post_id, $description ) {
		$plugin = PC_Settings::get( 'seo_plugin', 'none' );
		if ( 'none' === $plugin || empty( $description ) ) {
			return;
		}

		$excerpt = wp_trim_words( wp_strip_all_tags( $description ), 30, '…' );

		switch ( $plugin ) {
			case 'yoast':
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $excerpt );
				break;
			case 'rankmath':
				update_post_meta( $post_id, 'rank_math_description', $excerpt );
				break;
		}
	}
}
