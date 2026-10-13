<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's block patterns — the PHP implementation of the
 * approved mockups in docs/mockups/partner-catalog-pattern-*.html. Deliberately
 * NOT a copy of llm-agent-github-repos' patterns (repo stats — stars/forks/
 * language — make no sense here); the shared conventions are: Block Bindings
 * for per-post meta, the site-wide ratings block, and integration toggles
 * from PC_Settings gating optional markup.
 *
 * Patterns are insertable starting points only — same limitation noted in the
 * GitHub plugin's README: assigning them to actual templates is a separate,
 * manual editorial step in the Site Editor.
 */
class PC_Patterns {

	const CATEGORY = 'partner-catalog';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 30 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets() {
		wp_enqueue_style( 'pc-patterns', PC_PLUGIN_URL . 'assets/css/patterns.css', array(), PC_VERSION );

		if ( ! is_admin() && ( is_singular( 'contractor' ) || is_post_type_archive( 'contractor' ) || is_tax( array( 'contractor_type', 'contractor_specialization' ) ) ) ) {
			wp_enqueue_script( 'pc-analytics', PC_PLUGIN_URL . 'assets/js/pc-analytics.js', array(), PC_VERSION, true );
			wp_localize_script(
				'pc-analytics',
				'pcAnalytics',
				array(
					'metrikaCounterId' => PC_Settings::get( 'metrika_counter_id', '' ),
					'isSingle'         => is_singular( 'contractor' ),
					'contractorId'     => is_singular( 'contractor' ) ? get_queried_object_id() : 0,
					'contractorType'   => is_singular( 'contractor' ) ? implode( ',', wp_get_post_terms( get_queried_object_id(), 'contractor_type', array( 'fields' => 'slugs' ) ) ) : '',
				)
			);
		}
	}

	public static function register() {
		register_block_pattern_category( self::CATEGORY, array( 'label' => __( 'Partner Catalog', 'partner-catalog' ) ) );

		register_block_pattern(
			'partner-catalog/card',
			array(
				'title'       => 'Подрядчик: карточка',
				'description' => 'Одна карточка подрядчика — использовать как содержимое Post Template внутри блока «Цикл запроса» для contractor.',
				'categories'  => array( self::CATEGORY ),
				'blockTypes'  => array( 'core/post-template' ),
				'content'     => self::card_markup(),
			)
		);

		register_block_pattern(
			'partner-catalog/archive-grid',
			array(
				'title'       => 'Подрядчики: сетка архива',
				'description' => 'Сетка карточек для архива каталога/типа/специализации — наследует запрос архива + пагинация. Сортировка и фильтры живут в блоке «Фильтры подрядчиков» (боковая колонка шаблона).',
				'categories'  => array( self::CATEGORY ),
				'content'     => self::archive_grid_markup(),
			)
		);

		register_block_pattern(
			'partner-catalog/single-layout',
			array(
				'title'       => 'Подрядчик: страница записи',
				'description' => 'Полная разметка одиночной записи contractor — шапка, рейтинг, статистика, описание + боковая колонка контактов, похожие записи.',
				'categories'  => array( self::CATEGORY ),
				'content'     => self::single_layout_markup(),
			)
		);
	}

	// ------------------------------------------------------------- helpers

	private static function bound_paragraph( $meta_key, $placeholder = '', $font_size = 'small', $class = '' ) {
		$class_json = $class ? ',"className":"' . esc_attr( $class ) . '"' : '';
		$class_attr = $class ? ' ' . esc_attr( $class ) : '';
		return sprintf(
			'<!-- wp:paragraph {"fontSize":"%1$s"%4$s,"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"%2$s"}}}}} --><p class="has-%1$s-font-size%5$s">%3$s</p><!-- /wp:paragraph -->',
			esc_attr( $font_size ),
			esc_attr( $meta_key ),
			esc_html( $placeholder ),
			$class_json,
			$class_attr
		);
	}

	/**
	 * $outbound = true only for the tracked "Перейти на сайт" CTA (bound to the
	 * virtual _pc_outbound_url) — it alone gets the pc-outbound-link class that
	 * assets/js/pc-analytics.js listens for. Telegram/VK buttons bind straight
	 * to their own real meta and are NOT part of the /out/ click-tracking flow.
	 */
	private static function bound_button( $meta_key, $label, $class = '', $outbound = false ) {
		$attrs = array(
			'fontSize' => 'small',
			'metadata' => array(
				'bindings' => array(
					'url' => array(
						'source' => 'core/post-meta',
						'args'   => array( 'key' => $meta_key ),
					),
				),
			),
		);
		if ( $class ) {
			$attrs['className'] = $class;
		}

		$link_class = 'wp-block-button__link wp-element-button' . ( $outbound ? ' pc-outbound-link' : '' );
		$rel        = $outbound ? ' rel="nofollow noreferrer noopener"' : ' target="_blank" rel="noreferrer noopener"';
		$div_class  = 'wp-block-button has-custom-font-size has-small-font-size' . ( $class ? ' ' . esc_attr( $class ) : '' );

		return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button ' . wp_json_encode( $attrs ) . ' -->'
			. '<div class="' . esc_attr( $div_class ) . '"><a class="' . esc_attr( $link_class ) . '" href="#"' . $rel . '>' . esc_html( $label ) . '</a></div>'
			. '<!-- /wp:button --></div><!-- /wp:buttons -->';
	}

	/**
	 * Ratings widget — own block (`partner-catalog/rating`, class-pc-ratings.php),
	 * replacing WP-PostRatings as a dependency (decided 2026-09-04, same call
	 * made for llm-agent-github-repos). Still gated behind the "ratings"
	 * setting, now a plain feature on/off switch rather than "is the
	 * third-party plugin active".
	 */
	private static function ratings_block( $className = '', $interactive = true ) {
		if ( ! PC_Settings::integration_enabled( 'ratings' ) ) {
			return '';
		}
		$attrs = array(
			'size'        => $className ? 'large' : 'small',
			'interactive' => $interactive,
		);
		if ( $className ) {
			$attrs['className'] = $className;
		}
		return '<!-- wp:partner-catalog/rating ' . wp_json_encode( $attrs ) . ' /-->';
	}

	/** "Похожие подрядчики" — own block (`partner-catalog/related-posts`), replacing the Contextual Related Posts shortcode. */
	private static function related_posts_block() {
		if ( ! PC_Settings::integration_enabled( 'related_posts' ) ) {
			return '';
		}
		return '<!-- wp:heading {"level":3} --><h3>Похожие подрядчики</h3><!-- /wp:heading --><!-- wp:partner-catalog/related-posts /-->';
	}

	private static function static_paragraph( $text, $font_size = 'small' ) {
		return sprintf(
			'<!-- wp:paragraph {"fontSize":"%1$s"} --><p class="has-%1$s-font-size">%2$s</p><!-- /wp:paragraph -->',
			esc_attr( $font_size ),
			esc_html( $text )
		);
	}

	/**
	 * A bound_paragraph's "placeholder" only shows in the editor as an empty-state
	 * fallback — it is NOT a live front-end caption. The stats row needs a real
	 * caption under each number (see the approved single mockup), so this wraps
	 * the value with a second, always-visible static label underneath.
	 */
	private static function labeled_stat( $value_markup, $label ) {
		return '<!-- wp:group {"className":"pc-stat","layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap"}} --><div class="wp-block-group pc-stat">'
			. $value_markup
			. self::static_paragraph( $label, 'small' )
			. '</div><!-- /wp:group -->';
	}

	private static function meta_list( $meta_key, $style = 'bullet', $class = '' ) {
		$attrs           = array(
			'metaKey' => $meta_key,
			'style'   => $style,
		);
		if ( $class ) {
			$attrs['className'] = $class;
		}
		return '<!-- wp:partner-catalog/meta-list ' . wp_json_encode( $attrs ) . ' /-->';
	}

	// -------------------------------------------------------------- card

	private static function card_markup() {
		// Read-only on a card — voting only happens on the contractor's own
		// single page (partner-catalog/single-layout).
		$ratings = self::ratings_block( '', false );

		return '<!-- wp:group {"className":"pc-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group pc-card">

<!-- wp:group {"className":"pc-card-top","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group pc-card-top">
<!-- wp:post-featured-image {"width":"44px","height":"44px","className":"pc-logo"} /-->
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
<!-- wp:post-title {"isLink":true,"fontSize":"medium"} /-->
<!-- wp:post-terms {"term":"contractor_type","separator":" ","className":"pc-type-pill","fontSize":"small"} /-->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:partner-catalog/badges /-->

' . self::bound_paragraph( '_pc_short_description', 'Короткое описание', 'small', 'pc-card-desc' ) . '

<!-- wp:post-terms {"term":"contractor_specialization","separator":" ","className":"pc-spec-pill","fontSize":"small"} /-->

' . $ratings . '

<!-- wp:partner-catalog/price /-->

<!-- wp:group {"className":"pc-card-foot","layout":{"type":"flex","justifyContent":"space-between"}} -->
<div class="wp-block-group pc-card-foot">
' . self::meta_list( '_pc_partnership', 'badges', 'pc-partnership-badge' ) . '
<!-- wp:read-more {"content":"Подробнее →","fontSize":"small","textColor":"contrast","style":{"border":{"radius":"999px","width":"1px","color":"var:preset|color|light"},"spacing":{"padding":{"top":"0.35em","bottom":"0.35em","left":"1em","right":"1em"}},"typography":{"textDecoration":"none"}}} /-->
</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->';
	}

	// ---------------------------------------------------------- archive

	private static function archive_grid_markup() {
		$query = wp_json_encode(
			array(
				'perPage'  => 12,
				'pages'    => 0,
				'offset'   => 0,
				'postType' => 'contractor',
				'order'    => 'desc',
				'orderBy'  => 'date',
				'inherit'  => true,
			)
		);

		return '<!-- wp:query {"queryId":1,"query":' . $query . ',"displayLayout":{"type":"flex","columns":3},"className":"pc-tile-grid-wrap"} -->
<div class="wp-block-query">
<!-- wp:post-template {"className":"pc-tile-grid"} -->
' . self::card_markup() . '
<!-- /wp:post-template -->

<!-- wp:query-pagination -->
<!-- wp:query-pagination-previous /-->
<!-- wp:query-pagination-numbers /-->
<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph --><p>По этому запросу подрядчиков пока нет.</p><!-- /wp:paragraph -->
<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->';
	}

	// ------------------------------------------------------------ single

	private static function single_layout_markup() {
		$ratings = self::ratings_block( 'pc-single-rating' );
		$related = self::related_posts_block();

		return '<!-- wp:group {"className":"pc-single-header","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group pc-single-header">
<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group">
<!-- wp:post-featured-image {"width":"64px","height":"64px","className":"pc-logo-lg"} /-->
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
<!-- wp:post-title {"level":1} /-->
<!-- wp:post-terms {"term":"contractor_type","separator":" ","className":"pc-type-pill","fontSize":"small"} /-->
<!-- wp:post-terms {"term":"contractor_specialization","separator":" ","className":"pc-spec-pill","fontSize":"small"} /-->
<!-- wp:partner-catalog/badges /-->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"flex","orientation":"vertical","justifyContent":"right"}} -->
<div class="wp-block-group">
' . self::meta_list( '_pc_partnership', 'badges', 'pc-partnership-badge' ) . '
' . self::bound_button( '_pc_outbound_url', 'Перейти на сайт →', 'is-style-fill pc-cta', true ) . '
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->

' . $ratings . '

<!-- wp:group {"className":"pc-stats-row","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group pc-stats-row">
' . self::labeled_stat( self::bound_paragraph( '_pc_founded_year', '—', 'medium' ), 'год основания' ) . '
' . self::labeled_stat( '<!-- wp:partner-catalog/price {"fontSize":"medium"} /-->', 'стоимость' ) . '
</div>
<!-- /wp:group -->

<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%">
<!-- wp:heading {"level":3} --><h3>О компании</h3><!-- /wp:heading -->
<!-- wp:post-content /-->

<!-- wp:heading {"level":3} --><h3>Особенности сервиса</h3><!-- /wp:heading -->
' . self::meta_list( '_pc_features', 'bullet' ) . '

<!-- wp:heading {"level":3} --><h3>Ключевые клиенты</h3><!-- /wp:heading -->
' . self::meta_list( '_pc_clients', 'bullet' ) . '

<!-- wp:partner-catalog/faq /-->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"33.33%"} -->
<div class="wp-block-column" style="flex-basis:33.33%">
<!-- wp:partner-catalog/promo /-->

<!-- wp:group {"className":"pc-side-box","layout":{"type":"constrained"}} -->
<div class="wp-block-group pc-side-box">
<!-- wp:heading {"level":4} --><h4>Телефон</h4><!-- /wp:heading -->
' . self::bound_paragraph( '_pc_phone', '—', 'small' ) . '

<!-- wp:heading {"level":4} --><h4>Email</h4><!-- /wp:heading -->
' . self::bound_paragraph( '_pc_email', '—', 'small' ) . '

<!-- wp:heading {"level":4} --><h4>Соцсети</h4><!-- /wp:heading -->
' . self::bound_button( '_pc_telegram', 'Telegram', 'is-style-outline pc-social-btn' ) . '
' . self::bound_button( '_pc_vk', 'VK', 'is-style-outline pc-social-btn' ) . '
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->

' . $related;
	}
}
