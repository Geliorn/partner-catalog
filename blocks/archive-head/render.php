<?php
/**
 * Breadcrumbs + H1 + lead for the contractor archive. Replaces the theme's
 * core/query-title, which printed "Archives: Подрядчики". On a type /
 * specialization page the H1 is the term name and the lead its description.
 *
 * @var array $attributes title, lead.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$archive_url = get_post_type_archive_link( 'contractor' );
$title       = (string) ( $attributes['title'] ?? 'AI-сервисы' );
$lead        = (string) ( $attributes['lead'] ?? '' );
$crumbs      = array( '<a href="' . esc_url( home_url( '/' ) ) . '">Главная</a>' );

$term = is_tax() ? get_queried_object() : null;
if ( $term instanceof WP_Term ) {
	$crumbs[] = '<a href="' . esc_url( $archive_url ) . '">Сервисы</a>';
	$crumbs[] = '<span>' . esc_html( $term->name ) . '</span>';
	$title    = $term->name;
	$lead     = wp_strip_all_tags( term_description( $term ) );
} else {
	$crumbs[] = '<span>Сервисы</span>';
}

echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'pc-arch-head' ) ) . '>';
echo '<nav class="pc-crumbs" aria-label="Хлебные крошки">' . implode( '<span aria-hidden="true">›</span>', $crumbs ) . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
echo '<h1 class="pc-arch-title">' . esc_html( $title ) . '</h1>';
if ( '' !== trim( $lead ) ) {
	echo '<p class="pc-arch-lead">' . esc_html( $lead ) . '</p>';
}
echo '</div>';
