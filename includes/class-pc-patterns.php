<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's block patterns — the PHP implementation of the
 * approved v2 mockups in docs/mockups/partner-catalog-v2/ (archive A =
 * archive-a.html, archive B = archive-b.html, supplier page = single-c.html;
 * v1 mockups: docs/mockups/partner-catalog-pattern-*.html). Deliberately
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
				'title'       => 'Сервис: карточка',
				'description' => 'Одна карточка сервиса — содержимое «Шаблона записи» внутри «Цикла запроса» для contractor.',
				'categories'  => array( self::CATEGORY ),
				'blockTypes'  => array( 'core/post-template' ),
				'content'     => '<!-- wp:partner-catalog/card /-->',
			)
		);

		register_block_pattern(
			'partner-catalog/archive-cards',
			array(
				'title'       => 'Сервисы: архив — карточки',
				'description' => 'Вариант A: шапка, фильтры в боковой колонке, панель (число, чипы, сортировка, плитка/список), сетка карточек, пагинация. Наследует запрос архива.',
				'categories'  => array( self::CATEGORY ),
				'content'     => self::archive_cards_markup(),
			)
		);

		register_block_pattern(
			'partner-catalog/archive-table',
			array(
				'title'       => 'Сервисы: архив — таблица',
				'description' => 'Вариант B: шапка, вкладки по типам, быстрые фильтры, таблица сравнения с сортировкой по рейтингу и цене, пагинация. Наследует запрос архива.',
				'categories'  => array( self::CATEGORY ),
				'content'     => self::archive_table_markup(),
			)
		);

		// The slot the theme's archive templates use: which of the two layouts
		// it holds is a plugin setting (Partner Catalog → Настройки → Вид
		// каталога), so switching needs no Site Editor work.
		register_block_pattern(
			'partner-catalog/archive',
			array(
				'title'       => 'Сервисы: архив (по настройке)',
				'description' => 'Карточки или таблица — в зависимости от настройки «Вид каталога» плагина. Используется шаблонами архива темы.',
				'categories'  => array( self::CATEGORY ),
				'content'     => 'table' === PC_Settings::get( 'archive_view', 'cards' ) ? self::archive_table_markup() : self::archive_cards_markup(),
			)
		);

		// Kept for templates saved before 0.5.0: just the grid + pagination.
		register_block_pattern(
			'partner-catalog/archive-grid',
			array(
				'title'       => 'Сервисы: сетка карточек',
				'description' => 'Только сетка карточек с пагинацией (без шапки и фильтров) — наследует запрос архива.',
				'categories'  => array( self::CATEGORY ),
				'content'     => self::grid_markup(),
			)
		);

		register_block_pattern(
			'partner-catalog/single-layout',
			array(
				'title'       => 'Сервис: страница записи',
				'description' => 'Шапка во всю ширину (лого, «Подойдёт, если…», «Перейти на сайт», плитки), затем О сервисе → Особенности → Тарифы → Промокоды → Оценки посетителей → Частые вопросы → «Проверено редакцией» → Альтернативы.',
				'categories'  => array( self::CATEGORY ),
				'content'     => self::single_layout_markup(),
			)
		);
	}

	// ---------------------------------------------------------- archive

	private static function grid_markup() {
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

		return '<!-- wp:query {"queryId":1,"query":' . $query . ',"className":"pc-grid-query"} -->
<div class="wp-block-query pc-grid-query">
<!-- wp:post-template {"className":"pc-tile-grid","layout":{"type":"default"}} -->
<!-- wp:partner-catalog/card /-->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"className":"pc-pager","layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:query-pagination-previous {"label":"←"} /-->
<!-- wp:query-pagination-numbers /-->
<!-- wp:query-pagination-next {"label":"Далее →"} /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph {"className":"pc-empty"} --><p class="pc-empty">По этим условиям сервисов нет. Попробуйте убрать один из фильтров.</p><!-- /wp:paragraph -->
<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->';
	}

	private static function archive_cards_markup() {
		return '<!-- wp:partner-catalog/archive-head /-->

<!-- wp:columns {"align":"wide","className":"pc-arch-layout"} -->
<div class="wp-block-columns alignwide pc-arch-layout">
<!-- wp:column {"width":"250px","className":"pc-arch-side"} -->
<div class="wp-block-column pc-arch-side" style="flex-basis:250px">
<!-- wp:partner-catalog/contractor-filters /-->
</div>
<!-- /wp:column -->

<!-- wp:column {"className":"pc-arch-main"} -->
<div class="wp-block-column pc-arch-main">
<!-- wp:partner-catalog/archive-toolbar /-->

' . self::grid_markup() . '
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->';
	}

	private static function archive_table_markup() {
		return '<!-- wp:partner-catalog/archive-head {"lead":"Сравните условия: работает ли без VPN, как оплатить из России, сколько стоит. Данные проверяет редакция."} /-->

<!-- wp:partner-catalog/archive-table {"align":"wide"} /-->';
	}

	// ------------------------------------------------------------ single

	private static function single_layout_markup() {
		$ratings = PC_Settings::integration_enabled( 'ratings' ) ? '<!-- wp:partner-catalog/rating /-->' : '';
		$alts    = PC_Settings::integration_enabled( 'related_posts' ) ? '<!-- wp:partner-catalog/related-posts {"limit":3} /-->' : '';

		return '<!-- wp:partner-catalog/hero {"align":"full"} /-->

<!-- wp:group {"className":"pc-single-body","layout":{"type":"constrained","contentSize":"1000px"}} -->
<div class="wp-block-group pc-single-body">
<!-- wp:group {"className":"pc-sec pc-about","layout":{"type":"default"}} -->
<div class="wp-block-group pc-sec pc-about">
<!-- wp:heading {"className":"pc-sec-title"} --><h2 class="wp-block-heading pc-sec-title">О сервисе</h2><!-- /wp:heading -->
<!-- wp:post-content /-->
</div>
<!-- /wp:group -->

<!-- wp:partner-catalog/meta-list {"metaKey":"_pc_features","style":"checks","heading":"Особенности"} /-->

<!-- wp:partner-catalog/tariffs /-->

<!-- wp:partner-catalog/promo /-->

' . $ratings . '

<!-- wp:partner-catalog/faq /-->

<!-- wp:partner-catalog/verify /-->

' . $alts . '
</div>
<!-- /wp:group -->';
	}
}
