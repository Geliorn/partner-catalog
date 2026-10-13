<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers post meta (register_post_meta -> show_in_rest, needed for Block
 * Bindings in patterns) and renders the plain-PHP meta box for `contractor`.
 * No ACF dependency, no repeater UI — contacts are a small fixed set of
 * common fields (phone/email/Telegram/VK) rather than a generic repeater,
 * to stay consistent with the rest of the stack (llm-agent-github-repos has
 * no repeater fields either) and keep manual entry simple.
 */
class PC_Meta_Fields {

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

	/** Schema.org @type for the single page — see PC_Schema. */
	public static function schema_types() {
		return array(
			'SoftwareApplication' => 'Сервис / ПО (SoftwareApplication) — по умолчанию',
			'Organization'        => 'Компания (Organization)',
		);
	}

	/** Meta keys shown/edited on a contractor post. */
	public static function meta_keys() {
		return array(
			'_pc_short_description' => 'string',
			'_pc_website'           => 'string',
			'_pc_phone'             => 'string',
			'_pc_email'             => 'string',
			'_pc_telegram'          => 'string',
			'_pc_vk'                => 'string',
			'_pc_price_type'        => 'string',
			'_pc_price_value'       => 'string',
			'_pc_features'          => 'string',
			'_pc_partnership'       => 'string',
			'_pc_founded_year'      => 'integer',
			'_pc_clients'           => 'string',
			'_pc_utm_override'      => 'string',
			// Promo code (PC_Promo + partner-catalog/promo block).
			'_pc_promo_code'        => 'string',
			'_pc_promo_description' => 'string',
			'_pc_promo_expires'     => 'string',
			'_pc_promo_checked'     => 'string',
			// Q&A for the partner-catalog/faq block + FAQPage schema.
			'_pc_faq'               => 'string',
			'_pc_schema_type'       => 'string',
		);
	}

	/** meta_keys() plus the boolean flags — everything this class owns. */
	public static function all_keys() {
		return array_merge( self::meta_keys(), array_fill_keys( array_keys( self::flags() ), 'string' ) );
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
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
		add_meta_box( 'pc_contractor_data', 'Данные подрядчика', array( __CLASS__, 'render_box' ), 'contractor', 'normal', 'high' );
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
				'<input type="%1$s" name="%2$s" value="%3$s" style="width:100%%">',
				esc_attr( $type ),
				esc_attr( $key ),
				esc_attr( $value )
			);
		}
		if ( ! empty( $extra['description'] ) ) {
			echo '<span class="description" style="display:block;margin-top:3px">' . wp_kses_post( $extra['description'] ) . '</span>';
		}
		echo '</p>';
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

		self::field( $post->ID, '_pc_website', 'Сайт', 'url' );

		echo '<h3>Контакты</h3>';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px">';
		self::field( $post->ID, '_pc_phone', 'Телефон', 'text' );
		self::field( $post->ID, '_pc_email', 'Email', 'email' );
		self::field( $post->ID, '_pc_telegram', 'Telegram (ссылка)', 'url' );
		self::field( $post->ID, '_pc_vk', 'VK (ссылка)', 'url' );
		echo '</div>';

		echo '<h3>Стоимость услуг</h3>';
		echo '<div style="display:flex;gap:12px;align-items:flex-start">';
		self::field( $post->ID, '_pc_price_type', 'Тип цены', 'select', array( 'choices' => self::price_types() ) );
		self::field( $post->ID, '_pc_price_value', 'Значение (без ₽ и без единиц — они добавляются автоматически по типу, кроме почасовой/подписки, где единицу можно дописать самому)', 'text' );
		echo '</div>';
		echo '<p class="description">Примеры по типу: «От» → 150000; «Диапазон» → 150000–500000; «Фиксированная сумма» → 800000; «Почасовая ставка» → 3000; «По запросу» — значение можно оставить пустым. Для тарифов вида «990 ₽/мес» единицу измерения допишите прямо в значении.</p>';

		echo '<h3>Условия использования</h3>';
		echo '<p class="description">Бейджи на карточке и чекбоксы в фильтре архива — самый частый вопрос о сервисе.</p>';
		foreach ( self::flags() as $flag_key => $flag_label ) {
			self::field( $post->ID, $flag_key, $flag_label, 'checkbox' );
		}

		echo '<h3>Промокод</h3>';
		self::field( $post->ID, '_pc_promo_code', 'Код', 'text', array( 'description' => 'Оставьте пустым, если промокода нет — блок и бейдж тогда не выводятся.' ) );
		self::field( $post->ID, '_pc_promo_description', 'Что даёт (одна строка)', 'text', array( 'description' => 'Например: «Скидка 10% на первый месяц для новых клиентов».' ) );
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px">';
		self::field( $post->ID, '_pc_promo_expires', 'Действует до', 'date', array( 'description' => 'Необязательно. После этой даты промокод не выводится.' ) );
		self::field( $post->ID, '_pc_promo_checked', 'Проверен (дата)', 'date', array( 'description' => 'Выводится как «Проверено …» — это и есть причина доверия к коду.' ) );
		echo '</div>';

		echo '<h3>Вопросы и ответы</h3>';
		self::field(
			$post->ID,
			'_pc_faq',
			'FAQ (блоки через пустую строку: первая строка — вопрос, остальные — ответ)',
			'textarea',
			array(
				'rows'        => 8,
				'description' => 'Пример:<br><code>Нужен ли VPN?<br>Нет, сервис работает с российских IP напрямую.<br><br>Как оплатить?<br>Картой РФ или по счёту для юрлиц.</code><br>Выводится блоком «Подрядчик: FAQ» и попадает в разметку FAQPage (schema.org).',
			)
		);

		echo '<h3>Дополнительно</h3>';
		self::field( $post->ID, '_pc_founded_year', 'Год основания', 'number' );
		self::field( $post->ID, '_pc_schema_type', 'Тип в разметке schema.org', 'select', array( 'choices' => self::schema_types() ) );
		self::field( $post->ID, '_pc_partnership', 'Партнёрства и сертификации (каждая — с новой строки)', 'textarea', array( 'rows' => 2, 'description' => 'Например: «Официальный партнёр 1С», «Microsoft Gold Partner».' ) );
		self::field( $post->ID, '_pc_features', 'Особенности сервиса (тезисно, каждая мысль — с новой строки; на карточке/странице выводится немаркированным списком)', 'textarea', array( 'rows' => 5, 'description' => 'Например: «Работаем 24/7», «Бесплатный аудит перед стартом», «Фиксированная цена без доплат».' ) );
		self::field( $post->ID, '_pc_clients', 'Ключевые клиенты (каждый — с новой строки)', 'textarea', array( 'rows' => 3 ) );

		echo '<h3>UTM-метки</h3>';
		self::field(
			$post->ID,
			'_pc_utm_override',
			'Ручные UTM-параметры для этой записи (необязательно)',
			'text',
			array( 'description' => 'Если заполнено — полностью заменяет автосгенерированные метки для этого подрядчика. Формат — строка query-параметров, например: <code>utm_source=vk&amp;utm_medium=cpc&amp;utm_campaign=osen2026</code>. Если оставить пустым — метки подставляются автоматически по шаблону из настроек плагина.' )
		);
	}

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['pc_contractor_nonce'] ) || ! wp_verify_nonce( $_POST['pc_contractor_nonce'], 'pc_save_contractor' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$textareas    = array( '_pc_short_description', '_pc_partnership', '_pc_features', '_pc_clients', '_pc_faq' );
		$urls         = array( '_pc_website', '_pc_telegram', '_pc_vk' );
		$price_types  = array_keys( self::price_types() );
		$schema_types = array_keys( self::schema_types() );

		// An unchecked checkbox sends nothing at all, so the loop below (which
		// skips missing keys) could never clear a flag — they have to be read
		// as "present or not" before it, not inside it.
		foreach ( array_keys( self::flags() ) as $flag_key ) {
			if ( isset( $_POST[ $flag_key ] ) ) {
				update_post_meta( $post_id, $flag_key, '1' );
			} else {
				delete_post_meta( $post_id, $flag_key );
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
			} elseif ( '_pc_email' === $key ) {
				update_post_meta( $post_id, $key, sanitize_email( $raw ) );
			} elseif ( '_pc_price_type' === $key ) {
				update_post_meta( $post_id, $key, in_array( $raw, $price_types, true ) ? $raw : '' );
			} elseif ( '_pc_schema_type' === $key ) {
				update_post_meta( $post_id, $key, in_array( $raw, $schema_types, true ) ? $raw : '' );
			} elseif ( in_array( $key, array( '_pc_promo_expires', '_pc_promo_checked' ), true ) ) {
				$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $raw ) ? $raw : '';
				update_post_meta( $post_id, $key, $date );
			} elseif ( 'integer' === $type ) {
				update_post_meta( $post_id, $key, absint( $raw ) );
			} else {
				update_post_meta( $post_id, $key, sanitize_text_field( $raw ) );
			}
		}

		if ( class_exists( 'PC_SEO' ) ) {
			PC_SEO::apply_meta_description( $post_id, get_post_meta( $post_id, '_pc_short_description', true ) );
		}
	}

	/** True when the record has a promo code that hasn't expired. */
	public static function has_live_promo( $post_id ) {
		if ( '' === trim( (string) get_post_meta( $post_id, '_pc_promo_code', true ) ) ) {
			return false;
		}
		$expires = (string) get_post_meta( $post_id, '_pc_promo_expires', true );
		return '' === $expires || $expires >= current_time( 'Y-m-d' );
	}

	/**
	 * Parsed `_pc_faq`: blank-line-separated blocks, first line the question,
	 * the rest the answer. Same "one notepad field, no repeater" convention as
	 * _pc_features — see PC_Meta_Fields::render_box()'s hint for the format.
	 *
	 * @return array<int, array{q:string, a:string}>
	 */
	public static function faq_items( $post_id ) {
		$raw = (string) get_post_meta( $post_id, '_pc_faq', true );
		if ( '' === trim( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( preg_split( '/(\r\n|\r|\n){2,}/', $raw ) as $chunk ) {
			$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $chunk ) ), 'strlen' ) );
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
}
