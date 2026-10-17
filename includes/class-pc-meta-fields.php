<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers post meta (register_post_meta -> show_in_rest, needed for Block
 * Bindings in patterns) and renders the plain-PHP meta box for `contractor`.
 * No ACF dependency, no repeater UI — consistent with the rest of the stack
 * (llm-agent-github-repos has no repeater fields either). Contacts
 * (phone/email/Telegram/VK), partnerships and founded year were removed in
 * 0.4.0; "Ключевые клиенты" (_pc_clients) in 0.5.0 — the stored values stay in
 * the DB, they just have no UI/output any more.
 *
 * 0.5.0 (v2 mockups, docs/mockups/partner-catalog-v2/) adds the fields the
 * card, the supplier page and the archive filters are built from: «Подойдёт,
 * если…», access/payment/platform facts, tariffs, several promo codes, the
 * editorial "checked on" date and a manual priority for the recommendation
 * module in articles.
 */
class PC_Meta_Fields {

	/** Bumped when maybe_migrate() has new work to do. */
	const MIGRATION_OPTION  = 'pc_meta_migration';
	const MIGRATION_VERSION = '0.5.0';

	/**
	 * Derived, never edited by hand: the latest expiry among the record's live
	 * promo codes ('9999-12-31' for a code without an end date). A single
	 * comparable value is what lets the "Есть промокод" filter stay a plain
	 * meta_query even though the codes live in one multi-line field.
	 */
	const PROMO_UNTIL_KEY = '_pc_promo_live_until';

	/** Derived numeric price for "сначала дешевле" sorting (see price_sort_value()). */
	const PRICE_SORT_KEY = '_pc_price_sort';

	/**
	 * Price type — see docs/partner-catalog-plan.md section 4 ("Стоимость услуг
	 * — универсальное поле"). `subscription`/`usage`/`free` added in 0.4.0: the
	 * original five types were modelled on an integrator (project work), and
	 * an AI service is almost never priced that way — it is a monthly plan, a
	 * per-token/per-request charge, or free. Without them the most common case
	 * in this catalog had to be typed by hand into the value field, which is
	 * exactly the per-record inconsistency the select exists to prevent.
	 */
	public static function price_types() {
		return array(
			'free'         => 'Бесплатно',
			'subscription' => 'Подписка (₽/мес)',
			'usage'        => 'По потреблению (токены/запросы)',
			'from'         => 'От',
			'range'        => 'Диапазон',
			'fixed'        => 'Фиксированная сумма (за проект)',
			'hourly'       => 'Почасовая ставка',
			'on_request'   => 'По запросу',
		);
	}

	/**
	 * Boolean "условия использования" flags — the pricing badges the reference
	 * catalog leads its listing with, and the single most asked question about
	 * an AI service. Checkbox meta rather than taxonomy terms: there are
	 * exactly three, they never grow, and as terms they would show up in the
	 * term cloud/navigation, which is the opposite of what the tag cleanup is
	 * for. Stored as '1' / absent so a meta_query on '1' is enough (PC_Filters).
	 */
	public static function flags() {
		return array(
			'_pc_is_free'       => 'Полностью бесплатный',
			'_pc_has_free_tier' => 'Есть бесплатный тариф',
			'_pc_has_trial'     => 'Есть пробный период или демо',
		);
	}

	/** Payment methods — checkbox meta, same '1'/absent convention as flags(). */
	public static function payment_methods() {
		return array(
			'_pc_pay_card'    => 'Карта РФ',
			'_pc_pay_sbp'     => 'СБП',
			'_pc_pay_invoice' => 'Счёт для юрлиц',
			'_pc_pay_foreign' => 'Только зарубежная карта',
		);
	}

	/** Where the service runs — checkbox meta. Labels are the short card/table form. */
	public static function platforms() {
		return array(
			'_pc_plat_web'      => 'Веб',
			'_pc_plat_telegram' => 'Telegram',
			'_pc_plat_api'      => 'API',
			'_pc_plat_app'      => 'Приложение',
			'_pc_plat_selfhost' => 'Self-hosted',
		);
	}

	/** _pc_vpn values. '' = not stated, so the fact is simply not shown. */
	public static function vpn_options() {
		return array(
			''    => 'Не указано',
			'no'  => 'Работает без VPN',
			'yes' => 'Нужен VPN',
		);
	}

	/** Every checkbox-style key this class owns (flags + payment + platforms + data-in-RF). */
	public static function checkbox_keys() {
		return array_merge(
			array_keys( self::flags() ),
			array_keys( self::payment_methods() ),
			array_keys( self::platforms() ),
			array( '_pc_data_ru' )
		);
	}

	/** Meta keys shown/edited on a contractor post. */
	public static function meta_keys() {
		return array(
			'_pc_short_description' => 'string',
			'_pc_fit'               => 'string',
			'_pc_website'           => 'string',
			'_pc_checked'           => 'string',
			'_pc_price_type'        => 'string',
			'_pc_price_value'       => 'string',
			'_pc_tariffs'           => 'string',
			'_pc_vpn'               => 'string',
			'_pc_features'          => 'string',
			'_pc_promos'            => 'string',
			'_pc_faq'               => 'string',
			'_pc_priority'          => 'integer',
			'_pc_utm_override'      => 'string',
		);
	}

	/** meta_keys() plus the checkboxes and derived keys — everything this class owns. */
	public static function all_keys() {
		return array_merge(
			self::meta_keys(),
			array_fill_keys( self::checkbox_keys(), 'string' ),
			array(
				self::PROMO_UNTIL_KEY => 'string',
				self::PRICE_SORT_KEY  => 'number',
			)
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'init', array( __CLASS__, 'maybe_migrate' ), 30 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_contractor', array( __CLASS__, 'save_meta' ) );
		add_filter( 'is_protected_meta', array( __CLASS__, 'unprotect_for_bindings' ), 10, 2 );
	}

	/**
	 * All our meta keys are underscore-prefixed (stays out of the default
	 * Custom Fields metabox — we render our own). Block Bindings' core/post-meta
	 * source refuses "protected" meta (is_protected_meta() — true for any
	 * leading underscore) regardless of show_in_rest, so every binding used in
	 * class-pc-patterns.php / the blocks in class-pc-blocks.php would silently
	 * fall back to its placeholder without this filter — same fix already
	 * applied in llm-agent-github-repos.
	 */
	public static function unprotect_for_bindings( $protected, $meta_key ) {
		if ( array_key_exists( $meta_key, self::all_keys() ) ) {
			return false;
		}
		return $protected;
	}

	public static function register_meta() {
		foreach ( self::all_keys() as $key => $type ) {
			register_post_meta(
				'contractor',
				$key,
				array(
					'show_in_rest' => true,
					'single'       => true,
					'type'         => $type,
				)
			);
		}
	}

	public static function add_meta_boxes() {
		add_meta_box( 'pc_contractor_data', 'Данные сервиса', array( __CLASS__, 'render_box' ), 'contractor', 'normal', 'high' );
	}

	private static function field( $post_id, $key, $label, $type = 'text', $extra = array() ) {
		$value = get_post_meta( $post_id, $key, true );

		// A checkbox reads as "[x] label", not as a bold caption with a lone
		// box under it, so it skips the shared <label> header below.
		if ( 'checkbox' === $type ) {
			printf(
				'<p style="margin:6px 0"><label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label></p>',
				esc_attr( $key ),
				checked( $value, '1', false ),
				esc_html( $label )
			);
			return;
		}

		echo '<p style="margin:10px 0"><label style="display:block;font-weight:600;margin-bottom:3px">' . esc_html( $label ) . '</label>';

		if ( 'select' === $type ) {
			echo '<select name="' . esc_attr( $key ) . '">';
			foreach ( $extra['choices'] as $choice_value => $choice_label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $choice_value ),
					selected( $value, $choice_value, false ),
					esc_html( $choice_label )
				);
			}
			echo '</select>';
		} elseif ( 'textarea' === $type ) {
			printf(
				'<textarea name="%1$s" rows="%2$d" style="width:100%%">%3$s</textarea>',
				esc_attr( $key ),
				intval( $extra['rows'] ?? 3 ),
				esc_textarea( $value )
			);
		} else {
			printf(
				'<input type="%1$s" name="%2$s" value="%3$s" style="width:100%%"%4$s>',
				esc_attr( $type ),
				esc_attr( $key ),
				esc_attr( $value ),
				'number' === $type ? ' min="0" max="100" step="1"' : ''
			);
		}
		if ( ! empty( $extra['description'] ) ) {
			echo '<span class="description" style="display:block;margin-top:3px">' . wp_kses_post( $extra['description'] ) . '</span>';
		}
		echo '</p>';
	}

	/** A row of checkboxes laid out in columns — payment / platforms / flags. */
	private static function checkbox_grid( $post_id, array $boxes ) {
		echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0 18px">';
		foreach ( $boxes as $key => $label ) {
			self::field( $post_id, $key, $label, 'checkbox' );
		}
		echo '</div>';
	}

	public static function render_box( $post ) {
		wp_nonce_field( 'pc_save_contractor', 'pc_contractor_nonce' );

		echo '<h3 style="margin-top:0">Основное</h3>';
		self::field( $post->ID, '_pc_short_description', 'Короткое описание (1–2 предложения, для карточки в архиве)', 'textarea', array( 'rows' => 2 ) );

		if ( class_exists( 'PC_LLM_Client' ) ) {
			$gen_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=pc_generate_description&post_id=' . $post->ID ),
				'pc_generate_desc_' . $post->ID
			);
			$ready = PC_LLM_Client::ready();
			echo '<p style="margin:4px 0 14px">';
			echo '<a class="button" href="' . esc_url( $gen_url ) . '"' . ( $ready ? '' : ' onclick="alert(\'Укажите API-ключ AI-провайдера в настройках Partner Catalog.\');return false;"' ) . '>✨ Сгенерировать описание (AI)</a>';
			echo ' <span class="description">по сохранённым данным (тип, специализация, особенности…); перезапишет короткое и полное описание. Сначала сохраните запись.</span>';
			echo '</p>';
		}

		self::field( $post->ID, '_pc_fit', 'Подойдёт, если… (продолжение фразы, выводится в шапке страницы)', 'text', array( 'description' => 'Например: «нужны разные модели в одном окне без VPN и зарубежной карты». Без точки в конце.' ) );
		self::field( $post->ID, '_pc_website', 'Сайт', 'url' );
		self::field( $post->ID, '_pc_checked', 'Условия проверены редакцией (дата)', 'date', array( 'description' => 'Выводится внизу страницы: «Условия проверены редакцией ДД.ММ.ГГГГ». Обновляйте после каждой сверки цен и условий.' ) );

		echo '<h3>Стоимость услуг</h3>';
		echo '<div style="display:flex;gap:12px;align-items:flex-start">';
		self::field( $post->ID, '_pc_price_type', 'Тип цены', 'select', array( 'choices' => self::price_types() ) );
		self::field( $post->ID, '_pc_price_value', 'Значение (без ₽ и без единиц — они добавляются автоматически по типу, кроме почасовой/подписки, где единицу можно дописать самому)', 'text' );
		echo '</div>';
		echo '<p class="description">Примеры по типу: «От» → 150000; «Диапазон» → 150000–500000; «Фиксированная сумма» → 800000; «Почасовая ставка» → 3000; «По запросу» — значение можно оставить пустым. Для тарифов вида «990 ₽/мес» единицу измерения допишите прямо в значении.</p>';
		self::field(
			$post->ID,
			'_pc_tariffs',
			'Тарифы (каждый с новой строки: название | цена | что входит)',
			'textarea',
			array(
				'rows'        => 4,
				'description' => 'Пример:<br><code>Бесплатный | 0 ₽ | 20 запросов в день к базовым моделям<br>Старт | 690 ₽/мес | GPT-4.x и DeepSeek без дневного лимита<br>Про | 1 490 ₽/мес | Все модели, API-доступ</code><br>Выводится таблицей «Тарифы» на странице. Пусто — раздела нет.',
			)
		);

		echo '<h3>Доступ и оплата</h3>';
		self::field( $post->ID, '_pc_vpn', 'Доступ из РФ', 'select', array( 'choices' => self::vpn_options() ) );
		self::checkbox_grid( $post->ID, self::payment_methods() );
		self::field( $post->ID, '_pc_data_ru', 'Данные хранятся в РФ', 'checkbox' );

		echo '<h3>Платформы</h3>';
		self::checkbox_grid( $post->ID, self::platforms() );

		echo '<h3>Условия использования</h3>';
		echo '<p class="description">Пилюли на карточке и фильтры архива.</p>';
		self::checkbox_grid( $post->ID, self::flags() );

		echo '<h3>Промокоды</h3>';
		self::field(
			$post->ID,
			'_pc_promos',
			'Промокоды (через пустую строку; 1-я строка — код, 2-я — что даёт, дальше необязательные строки «Для:», «До:», «Проверен:»)',
			'textarea',
			array(
				'rows'        => 8,
				'description' => 'Пример:<br><code>NEUROHUB10<br>−10% на первый месяц любого тарифа<br>Для: новых клиентов<br>До: 31.12.2026<br>Проверен: 13.10.2026<br><br>PROYEAR500<br>+500 запросов к Claude при оплате за год<br>Для: тарифа «Про»</code><br>Просроченные коды скрываются сами. Пусто — блока «Промокоды» нет.',
			)
		);

		echo '<h3>Вопросы и ответы</h3>';
		self::field(
			$post->ID,
			'_pc_faq',
			'FAQ (блоки через пустую строку: первая строка — вопрос, остальные — ответ)',
			'textarea',
			array(
				'rows'        => 8,
				'description' => 'Пример:<br><code>Нужен ли VPN?<br>Нет, сервис работает с российских IP напрямую.<br><br>Как оплатить?<br>Картой РФ или по счёту для юрлиц.</code><br>Выводится блоком «Частые вопросы» и попадает в разметку FAQPage (schema.org).',
			)
		);

		echo '<h3>Дополнительно</h3>';
		self::field( $post->ID, '_pc_features', 'Особенности сервиса (тезисно, каждая мысль — с новой строки)', 'textarea', array( 'rows' => 5, 'description' => 'Например: «Работаем 24/7», «Бесплатный аудит перед стартом», «Фиксированная цена без доплат».' ) );

		echo '<h3>Рекомендации в статьях</h3>';
		self::field(
			$post->ID,
			'_pc_priority',
			'Приоритет показа (0–100)',
			'number',
			array( 'description' => 'Вес в блоке «Где попробовать» внутри статей: чем выше, тем чаще показывается. 0 или пусто — не показывать в блоке.' )
		);

		echo '<h3>UTM-метки</h3>';
		self::field(
			$post->ID,
			'_pc_utm_override',
			'Ручные UTM-параметры для этой записи (необязательно)',
			'text',
			array( 'description' => 'Если заполнено — полностью заменяет автосгенерированные метки для этого сервиса. Формат — строка query-параметров, например: <code>utm_source=vk&amp;utm_medium=cpc&amp;utm_campaign=osen2026</code>. Если оставить пустым — метки подставляются автоматически по шаблону из настроек плагина.' )
		);
	}

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['pc_contractor_nonce'] ) || ! wp_verify_nonce( $_POST['pc_contractor_nonce'], 'pc_save_contractor' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$textareas   = array( '_pc_short_description', '_pc_features', '_pc_faq', '_pc_tariffs', '_pc_promos' );
		$urls        = array( '_pc_website' );
		$price_types = array_keys( self::price_types() );

		// An unchecked checkbox sends nothing at all, so the loop below (which
		// skips missing keys) could never clear one — they have to be read as
		// "present or not" before it, not inside it.
		foreach ( self::checkbox_keys() as $box_key ) {
			if ( isset( $_POST[ $box_key ] ) ) {
				update_post_meta( $post_id, $box_key, '1' );
			} else {
				delete_post_meta( $post_id, $box_key );
			}
		}

		foreach ( self::meta_keys() as $key => $type ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_POST[ $key ] );

			if ( in_array( $key, $textareas, true ) ) {
				update_post_meta( $post_id, $key, sanitize_textarea_field( $raw ) );
			} elseif ( in_array( $key, $urls, true ) ) {
				update_post_meta( $post_id, $key, esc_url_raw( $raw ) );
			} elseif ( '_pc_price_type' === $key ) {
				update_post_meta( $post_id, $key, in_array( $raw, $price_types, true ) ? $raw : '' );
			} elseif ( '_pc_vpn' === $key ) {
				update_post_meta( $post_id, $key, array_key_exists( $raw, self::vpn_options() ) ? $raw : '' );
			} elseif ( '_pc_checked' === $key ) {
				update_post_meta( $post_id, $key, preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $raw ) ? $raw : '' );
			} elseif ( 'integer' === $type ) {
				update_post_meta( $post_id, $key, min( 100, absint( $raw ) ) );
			} else {
				update_post_meta( $post_id, $key, sanitize_text_field( $raw ) );
			}
		}

		self::refresh_derived( $post_id );

		if ( class_exists( 'PC_SEO' ) ) {
			PC_SEO::apply_meta_description( $post_id, get_post_meta( $post_id, '_pc_short_description', true ) );
		}
	}

	/** Recomputes the derived keys (promo expiry, sortable price). Call after any write. */
	public static function refresh_derived( $post_id ) {
		$until = '';
		foreach ( self::promo_items( $post_id ) as $promo ) {
			$candidate = '' === $promo['until'] ? '9999-12-31' : $promo['until'];
			if ( $candidate > $until ) {
				$until = $candidate;
			}
		}
		if ( '' === $until ) {
			delete_post_meta( $post_id, self::PROMO_UNTIL_KEY );
		} else {
			update_post_meta( $post_id, self::PROMO_UNTIL_KEY, $until );
		}

		update_post_meta( $post_id, self::PRICE_SORT_KEY, self::price_sort_value( $post_id ) );
	}

	/**
	 * One-time upgrade to 0.5.0: the single promo code (four fields) moves into
	 * the multi-code `_pc_promos` notepad as its first block, then the derived
	 * keys are computed for every record so filters/sorting work immediately.
	 * The old promo fields are left in the DB untouched.
	 */
	public static function maybe_migrate() {
		if ( get_option( self::MIGRATION_OPTION ) === self::MIGRATION_VERSION ) {
			return;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'contractor',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $ids as $id ) {
			$old_code = trim( (string) get_post_meta( $id, '_pc_promo_code', true ) );
			if ( '' !== $old_code && '' === trim( (string) get_post_meta( $id, '_pc_promos', true ) ) ) {
				$lines = array( $old_code, trim( (string) get_post_meta( $id, '_pc_promo_description', true ) ) );
				if ( '' === $lines[1] ) {
					$lines[1] = 'Промокод';
				}
				$expires = (string) get_post_meta( $id, '_pc_promo_expires', true );
				$checked = (string) get_post_meta( $id, '_pc_promo_checked', true );
				if ( '' !== $expires ) {
					$lines[] = 'До: ' . self::format_date( $expires );
				}
				if ( '' !== $checked ) {
					$lines[] = 'Проверен: ' . self::format_date( $checked );
				}
				update_post_meta( $id, '_pc_promos', implode( "\n", $lines ) );
			}
			self::refresh_derived( $id );
		}

		update_option( self::MIGRATION_OPTION, self::MIGRATION_VERSION, false );
	}

	// ------------------------------------------------------------ parsing

	/** 'Y-m-d' -> 'd.m.Y' (fixed Russian form, see blocks/promo/render.php). */
	public static function format_date( $ymd ) {
		$ts = strtotime( $ymd . ' 12:00:00' );
		return $ts ? wp_date( 'd.m.Y', $ts ) : '';
	}

	/** 'd.m.Y' or 'Y-m-d' -> 'Y-m-d', or '' when unparseable. */
	private static function parse_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $raw, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ? sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] ) : '';
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $raw : '';
		}
		return '';
	}

	/** Blank-line-separated blocks of a notepad field, each as its non-empty trimmed lines. */
	private static function blocks( $raw ) {
		$out = array();
		foreach ( preg_split( '/(\r\n|\r|\n){2,}/', (string) $raw ) as $chunk ) {
			$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $chunk ) ), 'strlen' ) );
			if ( $lines ) {
				$out[] = $lines;
			}
		}
		return $out;
	}

	/**
	 * Parsed `_pc_faq`: blank-line-separated blocks, first line the question,
	 * the rest the answer. Same "one notepad field, no repeater" convention as
	 * _pc_features — see PC_Meta_Fields::render_box()'s hint for the format.
	 *
	 * @return array<int, array{q:string, a:string}>
	 */
	public static function faq_items( $post_id ) {
		$out = array();
		foreach ( self::blocks( get_post_meta( $post_id, '_pc_faq', true ) ) as $lines ) {
			if ( count( $lines ) < 2 ) {
				continue; // a question with no answer is not a FAQ entry.
			}
			$out[] = array(
				'q' => array_shift( $lines ),
				'a' => implode( ' ', $lines ),
			);
		}
		return $out;
	}

	/**
	 * Parsed `_pc_promos`, LIVE codes only (expired ones are dropped here, so
	 * every consumer — block, badge, filter's derived key — agrees).
	 *
	 * @param bool $include_expired Admin/migration use only.
	 * @return array<int, array{code:string, offer:string, for:string, until:string, checked:string}>
	 */
	public static function promo_items( $post_id, $include_expired = false ) {
		$today = current_time( 'Y-m-d' );
		$out   = array();
		foreach ( self::blocks( get_post_meta( $post_id, '_pc_promos', true ) ) as $lines ) {
			if ( count( $lines ) < 2 ) {
				continue; // a code without "what it gives" is not shown.
			}
			$item = array(
				'code'    => array_shift( $lines ),
				'offer'   => array_shift( $lines ),
				'for'     => '',
				'until'   => '',
				'checked' => '',
			);
			foreach ( $lines as $line ) {
				if ( preg_match( '/^(для|до|проверен[оа]?)\s*:\s*(.+)$/iu', $line, $m ) ) {
					$label = mb_strtolower( $m[1] );
					if ( 'для' === $label ) {
						$item['for'] = $m[2];
					} elseif ( 'до' === $label ) {
						$item['until'] = self::parse_date( $m[2] );
					} else {
						$item['checked'] = self::parse_date( $m[2] );
					}
				}
			}
			if ( ! $include_expired && '' !== $item['until'] && $item['until'] < $today ) {
				continue;
			}
			$out[] = $item;
		}
		return $out;
	}

	/** True when the record has at least one promo code that hasn't expired. */
	public static function has_live_promo( $post_id ) {
		return (bool) self::promo_items( $post_id );
	}

	/**
	 * Parsed `_pc_tariffs`: one tariff per line, «название | цена | что входит».
	 *
	 * @return array<int, array{name:string, price:string, desc:string}>
	 */
	public static function tariff_items( $post_id ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $post_id, '_pc_tariffs', true ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 3 ) );
			if ( '' === $parts[0] ) {
				continue;
			}
			$out[] = array(
				'name'  => $parts[0],
				'price' => $parts[1] ?? '',
				'desc'  => $parts[2] ?? '',
			);
		}
		return $out;
	}

	/** Non-empty lines of a multi-line meta field (features). */
	public static function lines( $post_id, $meta_key ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $post_id, $meta_key, true ) ) ), 'strlen' ) );
	}

	// -------------------------------------------------------------- price

	/**
	 * Formats `_pc_price_type` + `_pc_price_value` into one display string, or
	 * '' when there's nothing to show. Shared by the price block, the card and
	 * the table so all three say the same thing.
	 */
	public static function price_text( $post_id ) {
		$type  = (string) get_post_meta( $post_id, '_pc_price_type', true );
		$value = trim( (string) get_post_meta( $post_id, '_pc_price_value', true ) );

		switch ( $type ) {
			case 'free':
				return 'Бесплатно';
			case 'subscription':
				// The value may already carry its own unit ("990 ₽/мес" typed by
				// hand for an odd plan), so only a bare number gets the suffix.
				if ( '' === $value ) {
					return '';
				}
				return preg_match( '/^[\d\s\xC2\xA0.,–-]+$/', $value ) ? 'от ' . $value . ' ₽/мес' : $value;
			case 'usage':
				return '' !== $value ? $value : 'Оплата по потреблению';
			case 'from':
				return '' !== $value ? 'от ' . $value . ' ₽' : '';
			case 'range':
				return '' !== $value ? $value . ' ₽' : '';
			case 'fixed':
				return '' !== $value ? $value . ' ₽' : '';
			case 'hourly':
				return '' !== $value ? $value . ' ₽/час' : '';
			case 'on_request':
				return 'По запросу';
		}
		return '';
	}

	/** The small grey line under the price: what the number means. */
	public static function price_note( $post_id ) {
		$type  = (string) get_post_meta( $post_id, '_pc_price_type', true );
		$notes = array(
			'fixed'  => 'за проект',
			'from'   => '',
			'usage'  => 'по потреблению',
			'hourly' => '',
		);
		$note = $notes[ $type ] ?? '';
		if ( 'free' !== $type && ( '1' === get_post_meta( $post_id, '_pc_has_free_tier', true ) || '1' === get_post_meta( $post_id, '_pc_is_free', true ) ) ) {
			$note = trim( $note . ( $note ? ' · ' : '' ) . 'есть бесплатный тариф' );
		}
		return $note;
	}

	/**
	 * A number to sort by: free = 0, "по запросу"/unknown = last, otherwise the
	 * first number in the value (so "150 000–500 000" sorts as 150000).
	 */
	public static function price_sort_value( $post_id ) {
		$type = (string) get_post_meta( $post_id, '_pc_price_type', true );
		if ( 'free' === $type || '1' === get_post_meta( $post_id, '_pc_is_free', true ) ) {
			return 0;
		}
		$value = (string) get_post_meta( $post_id, '_pc_price_value', true );
		if ( preg_match( '/\d[\d\s\xC2\xA0]*(?:[.,]\d+)?/u', $value, $m ) ) {
			return (float) str_replace( array( ' ', "\xC2\xA0", ',' ), array( '', '', '.' ), $m[0] );
		}
		return 999999999;
	}

	// -------------------------------------------------------------- facts

	/**
	 * The key facts, ordered by what an RF visitor asks first: VPN → payment →
	 * self-hosting/data → where it runs. Each: [tone ok|warn|neutral, icon, text].
	 *
	 * @return array<int, array{0:string,1:string,2:string}>
	 */
	public static function facts( $post_id, $max = 3 ) {
		$out = array();
		$vpn = (string) get_post_meta( $post_id, '_pc_vpn', true );
		if ( 'no' === $vpn ) {
			$out[] = array( 'ok', 'globe', 'Работает без VPN' );
		} elseif ( 'yes' === $vpn ) {
			$out[] = array( 'warn', 'globe', 'Нужен VPN' );
		}

		$pay = array();
		foreach ( array( '_pc_pay_card', '_pc_pay_sbp', '_pc_pay_invoice' ) as $key ) {
			if ( '1' === get_post_meta( $post_id, $key, true ) ) {
				$pay[] = self::payment_methods()[ $key ];
			}
		}
		if ( $pay ) {
			$out[] = array( 'ok', 'card', implode( ' · ', $pay ) );
		} elseif ( '1' === get_post_meta( $post_id, '_pc_pay_foreign', true ) ) {
			$out[] = array( 'warn', 'card', 'Только зарубежная карта' );
		}

		if ( '1' === get_post_meta( $post_id, '_pc_plat_selfhost', true ) ) {
			$out[] = array( 'ok', 'server', 'Можно развернуть у себя' );
		}
		if ( '1' === get_post_meta( $post_id, '_pc_data_ru', true ) ) {
			$out[] = array( 'ok', 'shield', 'Данные хранятся в РФ' );
		}

		$plat = self::platform_labels( $post_id );
		if ( $plat ) {
			$out[] = array( 'neutral', 'layers', implode( ' · ', $plat ) );
		}

		return array_slice( $out, 0, $max );
	}

	/** @return string[] Platform labels set on the record. */
	public static function platform_labels( $post_id ) {
		$out = array();
		foreach ( self::platforms() as $key => $label ) {
			if ( '1' === get_post_meta( $post_id, $key, true ) ) {
				$out[] = $label;
			}
		}
		return $out;
	}
}
