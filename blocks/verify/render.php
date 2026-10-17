<?php
/**
 * «Условия проверены редакцией ДАТА · Сообщить о неточности» — the last line
 * of the supplier page content (the date is shown only here, decided
 * 2026-10-17). The report is a fixed list of reasons, no text (PC_Reports).
 *
 * @var array $attributes Unused beyond className.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id || 'contractor' !== get_post_type( $post_id ) ) {
	return;
}

$checked = (string) get_post_meta( $post_id, '_pc_checked', true );

echo '<div ' . get_block_wrapper_attributes( // phpcs:ignore WordPress.Security.EscapeOutput
	array(
		'class'         => 'pc-verify',
		'data-post-id'  => $post_id,
		'data-nonce'    => wp_create_nonce( PC_Reports::NONCE_ACTION ),
		'data-ajax-url' => admin_url( 'admin-ajax.php' ),
	)
) . '>';
if ( '' !== $checked ) {
	echo '<span class="pc-checked">' . PC_Render::icon( 'shield' ) . 'Условия проверены редакцией ' . esc_html( PC_Meta_Fields::format_date( $checked ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
echo '<span class="pc-report"><button type="button" class="pc-report-btn" aria-expanded="false">Сообщить о неточности</button>';
echo '<span class="pc-report-pop" role="dialog" aria-label="Что неточно?"><b>Что изменилось?</b>';
foreach ( PC_Reports::reasons() as $key => $label ) {
	printf( '<button type="button" data-reason="%1$s">%2$s</button>', esc_attr( $key ), esc_html( $label ) );
}
echo '</span></span></div>';
