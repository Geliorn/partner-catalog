<?php
/**
 * «Где попробовать» as a block: placed by hand inside an article (its
 * presence switches the automatic in-text insertion off for that post), or
 * in a template/pattern — the single post template (after the text) and the
 * repo page pattern of llm-agent-github-repos. Same markup as the automatic
 * insertion; never twice on one page (PC_Recommend::render_block()).
 *
 * @var array $attributes className.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo PC_Recommend::render_block( get_block_wrapper_attributes( array( 'class' => 'pc-rec' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
