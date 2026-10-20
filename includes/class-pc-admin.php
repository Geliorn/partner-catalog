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
			'Сервисы',
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
				<p class="description">Применяются ко всем исходящим ссылкам на сервисы, если у конкретной записи не заполнено ручное поле «UTM-параметры» (см. карточку сервиса) — тогда оно полностью заменяет автогенерацию для этой записи. Токены в шаблонах: <code>{slug}</code> (адрес записи), <code>{contractor_type}</code> (тип сервиса).</p>
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
							<p class="description">Нужен только для двух JS-целей: <code>partner_profile_view</code> (просмотр страницы сервиса) и <code>partner_click</code> (клик по ссылке на сайт сервиса). Сам код счётчика Метрики этот плагин не устанавливает — он должен уже быть на сайте.</p>
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
							<p class="description">Короткое описание сервиса автоматически пишется в meta description выбранного плагина при сохранении записи.</p>
						</td>
					</tr>
					<tr><th>Рейтинг сайта</th><td><label><input type="checkbox" name="integration_ratings" value="1" <?php checked( $s['integration_ratings'] ); ?>> показывать рейтинг (★) в паттернах — свой виджет, без стороннего плагина</label></td></tr>
					<tr><th>Альтернативы</th><td><label><input type="checkbox" name="integration_related_posts" value="1" <?php checked( $s['integration_related_posts'] ); ?>> блок «Альтернативы» внизу страницы сервиса — по типу/специализации</label></td></tr>
					<tr><th>Фильтры архива</th><td><label><input type="checkbox" name="integration_filters" value="1" <?php checked( $s['integration_filters'] ); ?>> боковая панель фильтров на архиве (partner-catalog/contractor-filters) — свой код, без стороннего плагина</label></td></tr>
					<tr><th>Разметка schema.org</th><td><label><input type="checkbox" name="integration_schema" value="1" <?php checked( $s['integration_schema'] ); ?>> JSON-LD на странице сервиса (рейтинг, цена, FAQ)</label><p class="description">Выключите, если SEO-плагин уже сам формирует разметку для типа записи «Сервис» — две разные схемы на одном URL хуже одной.</p></td></tr>
				</table>

				<h2>Вид каталога</h2>
				<table class="form-table">
					<tr>
						<th>Архив сервисов</th>
						<td>
							<select name="archive_view">
								<option value="cards" <?php selected( $s['archive_view'], 'cards' ); ?>>Карточки — фильтры сбоку, плитка/список</option>
								<option value="table" <?php selected( $s['archive_view'], 'table' ); ?>>Таблица — быстрые фильтры сверху, сравнение колонками</option>
							</select>
							<p class="description">Применяется к архиву и страницам типов/специализаций (шаблоны темы используют паттерн «Сервисы: архив»).</p>
						</td>
					</tr>
				</table>

				<h2>«Где попробовать» в статьях</h2>
				<table class="form-table">
					<tr><th>Модуль</th><td><label><input type="checkbox" name="integration_recommend" value="1" <?php checked( $s['integration_recommend'] ); ?>> вставлять блок «Где попробовать» в статьи после 2-го подзаголовка</label>
						<p class="description">Показываются 3 сервиса с приоритетом больше 0 (поле «Приоритет показа» в карточке сервиса), случайно с учётом приоритета. Если блок вставлен в статью вручную, автоматически он не добавляется.</p></td></tr>
					<tr><th>Типы записей</th><td><input type="text" name="recommend_post_types" value="<?php echo esc_attr( $s['recommend_post_types'] ); ?>" style="width:300px"> <span class="description">через запятую, например <code>post</code></span></td></tr>
				</table>

				<h2>AI-описания сервисов</h2>
				<p class="description">Генерация текстов по данным сервиса (тип, специализация, цена, тарифы, платформы, условия, уже заполненные поля). Кнопка «Сгенерировать (AI)» есть на странице редактирования каждого сервиса; массовая генерация — кнопкой под формой.</p>
				<table class="form-table">
					<tr><th>Что заполнять</th><td>
						<?php foreach ( PC_LLM_Client::fields() as $key => $f ) : ?>
							<label style="display:block;margin-bottom:4px"><input type="checkbox" name="<?php echo esc_attr( $f['setting'] ); ?>" value="1" <?php checked( $s[ $f['setting'] ] ); ?>> <?php echo esc_html( $f['label'] ); ?></label>
						<?php endforeach; ?>
					</td></tr>
					<tr><th>Заполненные поля</th><td><label><input type="checkbox" name="gen_overwrite" value="1" <?php checked( $s['gen_overwrite'] ); ?>> перезаписывать</label><p class="description">Выключено — генерация пишет только в пустые поля, ручные правки и данные из CSV не затираются. Включено — выбранные поля генерируются заново (при запуске с карточки сервиса).</p></td></tr>
					<tr><th>Промпт</th><td>
						<textarea name="gen_prompt" rows="12" style="width:100%;max-width:760px;font-family:monospace;font-size:12.5px"><?php echo esc_textarea( PC_LLM_Client::prompt() ); ?></textarea>
						<p class="description">Инструкция модели (system-сообщение). Следом модель получает название и сайт сервиса, title, H1 и description его главной страницы, затем данные карточки, а в конце — формат ответа (JSON с выбранными полями): его плагин дописывает сам, здесь его указывать не нужно. Очистите поле и сохраните, чтобы вернуть стандартный промпт.</p>
					</td></tr>
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

			<h2>Массовая генерация</h2>
			<?php $sel = PC_LLM_Client::enabled_fields(); ?>
			<p class="description">Заполнит выбранные выше поля (<?php echo esc_html( $sel ? implode( ', ', wp_list_pluck( array_intersect_key( PC_LLM_Client::fields(), array_flip( $sel ) ), 'short' ) ) : 'ничего не выбрано' ); ?>) у сервисов, где хотя бы одно из них <strong>пустое</strong>. Уже заполненные поля массовый запуск не трогает, даже если включена перезапись. До 10 сервисов за запуск, чтобы уложиться в лимит времени — запускайте повторно, пока счётчик не обнулится.</p>
			<?php
			$missing = $sel ? PC_LLM_Client::count_incomplete() : 0;
			$gen_url = wp_nonce_url( admin_url( 'admin-post.php?action=pc_generate_all' ), 'pc_generate_all' );
			?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $gen_url ); ?>" <?php echo PC_LLM_Client::ready() ? '' : 'onclick="alert(\'Сначала укажите API-ключ провайдера и сохраните настройки.\');return false;"'; ?>>Заполнить пустые поля</a>
				<span class="description" style="margin-left:8px">Сервисов с пустыми выбранными полями: <?php echo (int) $missing; ?></span>
			</p>
		</div>
		<?php
	}

	public static function handle_save_settings() {
		check_admin_referer( 'pc_save_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав.' );
		}

		$checkboxes  = array_merge(
			array( 'integration_ratings', 'integration_related_posts', 'integration_filters', 'integration_schema', 'integration_recommend', 'gen_overwrite' ),
			wp_list_pluck( PC_LLM_Client::fields(), 'setting' )
		);
		$text_fields = array(
			'utm_source', 'utm_medium', 'utm_campaign_template', 'utm_content_template', 'metrika_counter_id', 'seo_plugin',
			'ai_provider', 'deepseek_api_key', 'deepseek_model', 'qwen_api_key', 'qwen_model', 'qwen_base_url',
			'recommend_post_types',
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
		$values['archive_view'] = isset( $_POST['archive_view'] ) && 'table' === $_POST['archive_view'] ? 'table' : 'cards';
		if ( isset( $_POST['gen_prompt'] ) ) {
			// Stored only when it differs from the default, so a later default update still reaches sites that never edited it.
			$prompt               = trim( sanitize_textarea_field( wp_unslash( $_POST['gen_prompt'] ) ) );
			$values['gen_prompt'] = $prompt === trim( PC_LLM_Client::DEFAULT_PROMPT ) ? '' : $prompt;
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
		$ok  = PC_LLM_Client::ready() && PC_LLM_Client::generate_for_post( $post_id, (bool) PC_Settings::get( 'gen_overwrite' ) );
		$url = get_edit_post_link( $post_id, 'url' );
		wp_safe_redirect( add_query_arg( 'pc_gen', $ok ? 'ok' : 'fail', $url ? $url : admin_url() ) );
		exit;
	}

	/** Bulk: fill the empty selected fields of up to 10 contractors (never overwrites). */
	public static function handle_generate_all() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'pc_generate_all' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		$redirect = admin_url( 'admin.php?page=' . self::MAIN_SLUG );
		if ( ! PC_LLM_Client::ready() ) {
			wp_safe_redirect( add_query_arg( 'pc_bulk', 'notready', $redirect ) );
			exit;
		}
		// Up to 10 × (home-page fetch + model call) — well past the default 30 s.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$done = 0;
		foreach ( PC_LLM_Client::incomplete_ids( 10 ) as $id ) {
			$done += PC_LLM_Client::generate_for_post( $id, false ) ? 1 : 0;
		}
		wp_safe_redirect( add_query_arg( array( 'pc_bulk' => 'done', 'n' => $done ), $redirect ) );
		exit;
	}

	/** Success/error notices after single or bulk generation. */
	public static function maybe_generation_notice() {
		if ( isset( $_GET['pc_gen'] ) ) {
			if ( 'ok' === $_GET['pc_gen'] ) {
				echo '<div class="notice notice-success is-dismissible"><p>Тексты сгенерированы и сохранены.</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>Ничего не сгенерировано: проверьте провайдера и API-ключ, выбранные поля в настройках — или все они уже заполнены, а перезапись выключена.</p></div>';
			}
		}
		if ( isset( $_GET['pc_bulk'] ) ) {
			if ( 'notready' === $_GET['pc_bulk'] ) {
				echo '<div class="notice notice-error is-dismissible"><p>Не задан API-ключ выбранного AI-провайдера.</p></div>';
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>Заполнено сервисов: ' . (int) ( $_GET['n'] ?? 0 ) . '. Если счётчик ниже не обнулился — запустите ещё раз.</p></div>';
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
				<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:14px 20px"><div style="font-size:22px;font-weight:600"><?php echo esc_html( number_format_i18n( $totals['click'] ) ); ?></div><div>Клики на сайт сервиса</div></div>
				<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:14px 20px"><div style="font-size:22px;font-weight:600"><?php echo esc_html( $conv ); ?>%</div><div>Конверсия клик/переход</div></div>
			</div>

			<canvas id="pc-analytics-chart" width="900" height="260" style="max-width:100%;background:#fff;border:1px solid #ccd0d4;border-radius:4px"></canvas>
			<script type="application/json" id="pc-analytics-series"><?php echo wp_json_encode( $series ); ?></script>

			<h2 style="margin-top:30px">По сервисам</h2>
			<table class="widefat striped">
				<thead><tr><th>Сервис</th><th>Переходы</th><th>Клики</th><th>Конверсия</th></tr></thead>
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
		echo '<label>Сервис <select name="contractor_id"><option value="0">Все</option>';
		foreach ( $posts as $post ) {
			printf( '<option value="%1$d" %2$s>%3$s</option>', $post->ID, selected( $selected, $post->ID, false ), esc_html( $post->post_title ) );
		}
		echo '</select></label>';
	}
}
