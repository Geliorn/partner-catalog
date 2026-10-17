<?php
/**
 * «Где попробовать» placed by hand inside an article — same markup as the
 * automatic insertion (PC_Recommend::render()); its presence switches the
 * automatic one off for that post.
 *
 * @var array $attributes className.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo PC_Recommend::render( get_block_wrapper_attributes( array( 'class' => 'pc-rec' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
