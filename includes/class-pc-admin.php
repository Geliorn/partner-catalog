<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu: settings + the analytics dashboard (docs/partner-catalog-plan.md
 * section 7.3). The CPT screen and its taxonomies attach themselves to the
 * same top-level menu via show_in_menu — same convention as LAGR_Admin.
 */
class PC_Admin {

	const SETTINGS_SLUG  = 'pc-settings';
	const ANALYTICS_SLUG = 'pc-analytics';
	const MAIN_SLUG      = 'pc-main';

	/**
	 * The real admin_enqueue_scripts hook suffix for the analytics page —
	 * reconstructing it (e.g. "{sanitized-menu-title}_page_{slug}") would mean
	 * guessing how WP transliterates the Cyrillic top-level menu label, so we
	 * just keep the exact string add_submenu_page() hands back.
	 */
	private static $analytics_hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_pc_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_pc_generate_description', array( __CLASS__, 'handle_generate_description' ) );
		add_action( 'admin_post_pc_generate_all', array( __CLASS__, 'handle_generate_all' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_dashboard_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_generation_notice' ) );
	}

	public static function register_menu() {
		add_menu_page(
			'Partner Catalog',
			'Подрядчики',
			'manage_options',
			self::MAIN_SLUG,
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-groups',
			26
		);
		add_submenu_page( self::MAIN_SLUG, 'Настройки', 'Настройки', 'manage_options', self::MAIN_SLUG, array( __CLASS__, 'render_settings_page' ) );
		self::$analytics_hook = (string) add_submenu_page( self::MAIN_SLUG, 'Аналитика', 'Аналитика', 'manage_options', self::ANALYTICS_SLUG, array( __CLASS__, 'render_analytics_page' ) );
	}

	public static function enqueue_dashboard_assets( $hook ) {
		if ( ! self::$analytics_hook || $hook !== self::$analytics_hook ) {
			return;
		}
		wp_enqueue_script( 'pc-admin-dashboard', PC_PLUGIN_URL . 'assets/js/pc-admin-dashboard.js', array(), PC_VERSION, true );
	}

	// -------------------------------------------------------------- settings

	public static function render_settings_page() {
		$s = PC_Settings::get_all();
		?>
		<div class="wrap">
			<h1>Настройки — Partner Catalog</h1>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success"><p>Настройки сохранены.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pc_save_settings' ); ?>
				<input type="hidden" name="action" value="pc_save_settings">

				<h2>UTM-метки (авто-режим)</h2>
				<p class="description">Применяются ко всем исходящим ссылкам на поставщиков, если у конкретной записи не заполнено ручное поле «UTM-параметры» (см. карточку подрядчика) — тогда оно полностью заменяет автогенерацию для этой записи. Токены в шаблонах: <code>{slug}</code> (адрес записи), <code>{contractor_type}</code> (тип сервиса).</p>
				<table class="form-table">
					<tr><th>utm_source</th><td><input type="text" name="utm_source" value="<?php echo esc_attr( $s['utm_source'] ); ?>" style="width:300px"></td></tr>
					<tr><th>utm_medium</th><td><input type="text" name="utm_medium" value="<?php echo esc_attr( $s['utm_medium'] ); ?>" style="width:300px"></td></tr>
					<tr><th>utm_campaign (шаблон)</th><td><input type="text" name="utm_campaign_template" value="<?php echo esc_attr( $s['utm_campaign_template'] ); ?>" style="width:300px"></td></tr>
					<tr><th>utm_content (шаблон)</th><td><input type="text" name="utm_content_template" value="<?php echo esc_attr( $s['utm_content_template'] ); ?>" style="width:300px"></td></tr>
				</table>

				<h2>Яндекс.Метрика</h2>
				<table class="form-table">
					<tr>
						<th>ID счётчика</th>
						<td>
							<input type="text" name="metrika_counter_id" value="<?php echo esc_attr( $s['metrika_counter_id'] ); ?>" style="width:150px">
							<p class="description">Нужен только для двух JS-целей: <code>partner_profile_view</code> (просмотр страницы подрядчика) и <code>partner_click</code> (клик по ссылке на сайт поставщика). Сам код счётчика Метрики этот плагин не устанавливает — он должен уже быть на сайте.</p>
						</td>
					</tr>
				</table>

				<h2>Интеграции с другими плагинами</h2>
				<table class="form-table">
					<tr>
						<th>SEO-плагин</th>
						<td>
							<select name="seo_plugin">
								<?php foreach ( array( 'none' => 'Не использовать', 'yoast' => 'Yoast SEO', 'rankmath' => 'Rank Math' ) as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['seo_plugin'], $val ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Короткое описание подрядчика автоматически пишется в meta description выбранного плагина при сохранении записи.</p>
						</td>
					</tr>
					<tr><th>Рейтинг сайта</th><td><label><input type="checkbox" name="integration_ratings" value="1" <?php checked( $s['integration_ratings'] ); ?>> показывать рейтинг (♡) в паттернах — свой виджет, без стороннего плагина</label></td></tr>
					<tr><th>Похожие подрядчики</th><td><label><input type="checkbox" name="integration_related_posts" value="1" <?php checked( $s['integration_related_posts'] ); ?>> блок «Похожие подрядчики» на странице записи — свой код по типу/специализации, без стороннего плагина</label></td></tr>
					<tr><th>Фильтры архива</th><td><label><input type="checkbox" name="integration_filters" value="1" <?php checked( $s['integration_filters'] ); ?>> боковая панель фильтров на архиве (partner-catalog/contractor-filters) — свой код, без стороннего плагина</label></td></tr>
					<tr><th>Разметка schema.org</th><td><label><input type="checkbox" name="integration_schema" value="1" <?php checked( $s['integration_schema'] ); ?>> JSON-LD на странице подрядчика (рейтинг, цена, FAQ)</label><p class="description">Выключите, если SEO-плагин уже сам формирует разметку для типа записи «Подрядчик» — две разные схемы на одном URL хуже одной.</p></td></tr>
				</table>

				<h2>AI-описания поставщиков</h2>
				<p class="description">Генерация короткого (для карточки) и полного (для страницы) описания по данным поставщика. Кнопка «Сгенерировать описание» есть на странице редактирования каждого поставщика; массовая генерация — кнопкой под формой.</p>
				<table class="form-table">
					<tr><th>Провайдер</th><td>
						<select name="ai_provider">
							<?php foreach ( PC_LLM_Client::providers() as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['ai_provider'], $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
				</table>
				<h3>DeepSeek напрямую</h3>
				<table class="form-table">
					<tr><th>API Key</th><td><input type="password" name="deepseek_api_key" value="<?php echo esc_attr( $s['deepseek_api_key'] ); ?>" style="width:400px"></td></tr>
					<tr><th>Модель</th><td><input type="text" name="deepseek_model" value="<?php echo esc_attr( $s['deepseek_model'] ); ?>" style="width:400px"></td></tr>
				</table>
				<h3>Qwen (Alibaba DashScope)</h3>
				<table class="form-table">
					<tr><th>API Key</th><td><input type="password" name="qwen_api_key" value="<?php echo esc_attr( $s['qwen_api_key'] ); ?>" style="width:400px"></td></tr>
					<tr><th>Модель</th><td><input type="text" name="qwen_model" value="<?php echo esc_attr( $s['qwen_model'] ); ?>" style="width:400px"> <span class="description">напр. qwen-plus, qwen-max, qwen-turbo</span></td></tr>
					<tr><th>Base URL</th><td><input type="text" name="qwen_base_url" value="<?php echo esc_attr( $s['qwen_base_url'] ); ?>" style="width:400px"> <span class="description">compatible-mode host DashScope (междунар. или КНР)</span></td></tr>
				</table>

				<?php submit_button( 'Сохранить настройки' ); ?>
			</form>

			<h2>Массовая генерация описаний</h2>
			<p class="description">Сгенерирует описание для поставщиков <strong>без короткого описания</strong> (до 10 за один запуск, чтобы уложиться в лимит времени — запускайте повторно, пока есть записи).</p>
			<?php
			$missing = self::count_contractors_without_description();
			$gen_url = wp_nonce_url( admin_url( 'admin-post.php?action=pc_generate_all' ), 'pc_generate_all' );
			?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $gen_url ); ?>" <?php echo PC_LLM_Client::ready() ? '' : 'onclick="alert(\'Сначала укажите API-ключ провайдера и сохраните настройки.\');return false;"'; ?>>Сгенерировать для всех без описания</a>
				<span class="description" style="margin-left:8px">Без описания: <?php echo (int) $missing; ?></span>
			</p>
		</div>
		<?php
	}

	/** Contractors that still have no short description. */
	private static function count_contractors_without_description() {
		$q = new WP_Query( array(
			'post_type'      => 'contractor',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => '_pc_short_description', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_pc_short_description', 'value' => '', 'compare' => '=' ),
			),
		) );
		return (int) $q->found_posts;
	}

	public static function handle_save_settings() {
		check_admin_referer( 'pc_save_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав.' );
		}

		$checkboxes  = array( 'integration_ratings', 'integration_related_posts', 'integration_filters', 'integration_schema' );
		$text_fields = array(
			'utm_source', 'utm_medium', 'utm_campaign_template', 'utm_content_template', 'metrika_counter_id', 'seo_plugin',
			'ai_provider', 'deepseek_api_key', 'deepseek_model', 'qwen_api_key', 'qwen_model', 'qwen_base_url',
		);

		$values = array();
		foreach ( $text_fields as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$values[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}
		foreach ( $checkboxes as $key ) {
			$values[ $key ] = isset( $_POST[ $key ] ) ? 1 : 0;
		}

		PC_Settings::update( $values );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MAIN_SLUG . '&saved=1' ) );
		exit;
	}

	// --------------------------------------------------------- AI generation

	/** Generate the description for one contractor (edit-screen button). */
	public static function handle_generate_description() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		check_admin_referer( 'pc_generate_desc_' . $post_id );
		if ( ! $post_id || 'contractor' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		$ok  = PC_LLM_Client::ready() && PC_LLM_Client::generate_for_post( $post_id );
		$url = get_edit_post_link( $post_id, 'url' );
		wp_safe_redirect( add_query_arg( 'pc_gen', $ok ? 'ok' : 'fail', $url ? $url : admin_url() ) );
		exit;
	}

	/** Bulk-generate for up to 10 contractors that have no short description yet. */
	public static function handle_generate_all() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'pc_generate_all' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		$redirect = admin_url( 'admin.php?page=' . self::MAIN_SLUG );
		if ( ! PC_LLM_Client::ready() ) {
			wp_safe_redirect( add_query_arg( 'pc_bulk', 'notready', $redirect ) );
			exit;
		}
		$ids = get_posts( array(
			'post_type'      => 'contractor',
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => '_pc_short_description', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_pc_short_description', 'value' => '', 'compare' => '=' ),
			),
		) );
		$done = 0;
		foreach ( $ids as $id ) {
			$done += PC_LLM_Client::generate_for_post( $id ) ? 1 : 0;
		}
		wp_safe_redirect( add_query_arg( array( 'pc_bulk' => 'done', 'n' => $done ), $redirect ) );
		exit;
	}

	/** Success/error notices after single or bulk generation. */
	public static function maybe_generation_notice() {
		if ( isset( $_GET['pc_gen'] ) ) {
			if ( 'ok' === $_GET['pc_gen'] ) {
				echo '<div class="notice notice-success is-dismissible"><p>Описание сгенерировано и сохранено.</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>Не удалось сгенерировать описание — проверьте провайдера и API-ключ.</p></div>';
			}
		}
		if ( isset( $_GET['pc_bulk'] ) ) {
			if ( 'notready' === $_GET['pc_bulk'] ) {
				echo '<div class="notice notice-error is-dismissible"><p>Не задан API-ключ выбранного AI-провайдера.</p></div>';
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>Сгенерировано описаний: ' . (int) ( $_GET['n'] ?? 0 ) . '. Если остались записи без описания — запустите ещё раз.</p></div>';
			}
		}
	}

	// ------------------------------------------------------------ analytics

	private static function get_filters() {
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : gmdate( 'Y-m-d' );
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-29 days', strtotime( $date_to ) ) );

		return array(
			'date_from'                 => $date_from,
			'date_to'                   => $date_to,
			'contractor_id'             => isset( $_GET['contractor_id'] ) ? absint( $_GET['contractor_id'] ) : 0,
			'contractor_type'           => isset( $_GET['contractor_type'] ) ? absint( $_GET['contractor_type'] ) : 0,
			'contractor_specialization' => isset( $_GET['contractor_specialization'] ) ? absint( $_GET['contractor_specialization'] ) : 0,
		);
	}

	public static function render_analytics_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав.' );
		}

		$filters = self::get_filters();
		$totals  = PC_Stats::get_totals( $filters );
		$series  = PC_Stats::get_daily_series( $filters );
		$top     = PC_Stats::get_top_contractors( $filters, 30 );
		$conv    = $totals['view'] > 0 ? round( $totals['click'] / $totals['view'] * 100, 1 ) : 0;

		?>
		<div class="wrap">
			<h1>Аналитика — Partner Catalog</h1>

			<form method="get" style="margin:16px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::ANALYTICS_SLUG ); ?>">
				<label>С <input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>"></label>
				<label>По <input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>"></label>
				<?php self::render_term_select( 'contractor_type', 'Тип сервиса', $filters['contractor_type'] ); ?>
				<?php self::render_term_select( 'contractor_specialization', 'Специализация', $filters['contractor_specialization'] ); ?>
				<?php self::render_contractor_select( $filters['contractor_id'] ); ?>
				<button class="button button-primary">Показать</button>
			</form>

			<div style="display:flex;gap:16px;margin-bottom:20px">
				<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:14px 20px"><div style="font-size:22px;font-weight:600"><?php echo esc_html( number_format_i18n( $totals['view'] ) ); ?></div><div>Переходы (просмотры)</div></div>
				<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:14px 20px"><div style="font-size:22px;font-weight:600"><?php echo esc_html( number_format_i18n( $totals['click'] ) ); ?></div><div>Клики на сайт поставщика</div></div>
				<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:14px 20px"><div style="font-size:22px;font-weight:600"><?php echo esc_html( $conv ); ?>%</div><div>Конверсия клик/переход</div></div>
			</div>

			<canvas id="pc-analytics-chart" width="900" height="260" style="max-width:100%;background:#fff;border:1px solid #ccd0d4;border-radius:4px"></canvas>
			<script type="application/json" id="pc-analytics-series"><?php echo wp_json_encode( $series ); ?></script>

			<h2 style="margin-top:30px">По подрядчикам</h2>
			<table class="widefat striped">
				<thead><tr><th>Подрядчик</th><th>Переходы</th><th>Клики</th><th>Конверсия</th></tr></thead>
				<tbody>
				<?php if ( empty( $top ) ) : ?>
					<tr><td colspan="4">Пока нет данных за выбранный период.</td></tr>
				<?php endif; ?>
				<?php foreach ( $top as $row ) : ?>
					<?php
					$views     = (int) $row['views'];
					$clicks    = (int) $row['clicks'];
					$row_conv  = $views > 0 ? round( $clicks / $views * 100, 1 ) : 0;
					$post_link = get_edit_post_link( (int) $row['contractor_id'] );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $post_link ); ?>"><?php echo esc_html( get_the_title( (int) $row['contractor_id'] ) ); ?></a></td>
						<td><?php echo esc_html( $views ); ?></td>
						<td><?php echo esc_html( $clicks ); ?></td>
						<td><?php echo esc_html( $row_conv ); ?>%</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function render_term_select( $taxonomy, $label, $selected ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return;
		}
		echo '<label>' . esc_html( $label ) . ' <select name="' . esc_attr( $taxonomy ) . '"><option value="0">Все</option>';
		foreach ( $terms as $term ) {
			printf( '<option value="%1$d" %2$s>%3$s</option>', $term->term_id, selected( $selected, $term->term_id, false ), esc_html( $term->name ) );
		}
		echo '</select></label>';
	}

	private static function render_contractor_select( $selected ) {
		$posts = get_posts( array( 'post_type' => 'contractor', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<label>Подрядчик <select name="contractor_id"><option value="0">Все</option>';
		foreach ( $posts as $post ) {
			printf( '<option value="%1$d" %2$s>%3$s</option>', $post->ID, selected( $selected, $post->ID, false ), esc_html( $post->post_title ) );
		}
		echo '</select></label>';
	}
}
