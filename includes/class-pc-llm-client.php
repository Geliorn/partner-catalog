<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI description generator for contractors. One OpenAI-compatible chat client
 * for the two supported providers (DeepSeek, Qwen/DashScope) — same pattern as
 * LAGR_LLM_Client in llm-agent-github-repos, kept self-contained so this plugin
 * has no dependency on the others.
 *
 * Produces a short card description (_pc_short_description) and a full page
 * description (post_content) from the contractor's structured data.
 */
class PC_LLM_Client {

	const SYSTEM_PROMPT = <<<PROMPT
Ты пишешь описание компании-поставщика (подрядчика услуг) для карточки в каталоге.
Аудитория — бизнес, который подбирает исполнителя под свою задачу.

Верни СТРОГО JSON-объект (без markdown-обёртки, без пояснений вокруг) с полями:
{
  "short_description": "1-2 предложения, до 200 символов — кто это и чем полезен",
  "full_description": "2-4 связных абзаца без заголовков и без маркированных списков — чем занимается, в чём силён, кому и когда подойдёт"
}

Ограничения:
— Пиши по-русски, по делу, как будто объясняешь коллеге.
— Без эмодзи и рекламных штампов («лидер рынка», «уникальный», «лучший», «под ключ» без смысла).
— Не придумывай факты, цифры, клиентов и сертификаты, которых нет во входных данных.
— Опирайся только на переданные данные (тип, специализация, регион, особенности, клиенты и т.п.).
PROMPT;

	/** Providers offered for contractor descriptions. */
	public static function providers() {
		return array(
			'deepseek' => 'DeepSeek напрямую',
			'qwen'     => 'Qwen (Alibaba DashScope)',
		);
	}

	/** OpenAI-compatible preset for the selected provider. */
	public static function provider_config( $provider = null ) {
		$s        = PC_Settings::get_all();
		$provider = $provider ? $provider : $s['ai_provider'];

		if ( 'qwen' === $provider ) {
			return array(
				'endpoint' => rtrim( $s['qwen_base_url'], '/' ) . '/chat/completions',
				'headers'  => array( 'Authorization' => 'Bearer ' . $s['qwen_api_key'] ),
				'model'    => $s['qwen_model'],
			);
		}
		return array(
			'endpoint' => 'https://api.deepseek.com/v1/chat/completions',
			'headers'  => array( 'Authorization' => 'Bearer ' . $s['deepseek_api_key'] ),
			'model'    => $s['deepseek_model'],
		);
	}

	/** Credentials for the selected provider present? */
	public static function ready( $provider = null ) {
		$s        = PC_Settings::get_all();
		$provider = $provider ? $provider : $s['ai_provider'];
		return 'qwen' === $provider ? '' !== (string) $s['qwen_api_key'] : '' !== (string) $s['deepseek_api_key'];
	}

	/**
	 * Generate and store the description for one contractor.
	 * Writes _pc_short_description + post_content. Returns true on success.
	 */
	public static function generate_for_post( $post_id ) {
		$result = self::generate( self::collect( $post_id ) );
		if ( ! $result ) {
			return false;
		}
		update_post_meta( $post_id, '_pc_short_description', $result['short'] );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $result['full'],
			)
		);
		if ( class_exists( 'PC_SEO' ) ) {
			PC_SEO::apply_meta_description( $post_id, $result['short'] );
		}
		return true;
	}

	/** Gather the structured data an LLM needs from a contractor post. */
	public static function collect( $post_id ) {
		$terms = function ( $tax ) use ( $post_id ) {
			$t = wp_get_post_terms( $post_id, $tax, array( 'fields' => 'names' ) );
			return is_wp_error( $t ) ? array() : $t;
		};
		return array(
			'title'        => get_the_title( $post_id ),
			'types'        => $terms( 'contractor_type' ),
			'specs'        => $terms( 'contractor_specialization' ),
			'website'      => (string) get_post_meta( $post_id, '_pc_website', true ),
			'features'     => (string) get_post_meta( $post_id, '_pc_features', true ),
			'price_type'   => (string) get_post_meta( $post_id, '_pc_price_type', true ),
			'price_value'  => (string) get_post_meta( $post_id, '_pc_price_value', true ),
			'conditions'   => self::condition_labels( $post_id ),
		);
	}

	/** Human-readable usage flags, so the generated description can mention them. */
	private static function condition_labels( $post_id ) {
		$labels = array();
		foreach ( PC_Meta_Fields::flags() as $meta_key => $label ) {
			if ( '1' === (string) get_post_meta( $post_id, $meta_key, true ) ) {
				$labels[] = $label;
			}
		}
		// Access / payment / platform facts (0.5.0) — the things an RF reader asks first.
		foreach ( PC_Meta_Fields::facts( $post_id, 10 ) as $fact ) {
			$labels[] = $fact[2];
		}
		return $labels;
	}

	/** Call the model and parse the JSON; null on failure. */
	public static function generate( array $data ) {
		$config  = self::provider_config();
		$payload = array(
			'model'       => $config['model'],
			'max_tokens'  => 1200,
			'temperature' => 0.6,
			'messages'    => array(
				array( 'role' => 'system', 'content' => self::SYSTEM_PROMPT ),
				array( 'role' => 'user', 'content' => self::build_prompt( $data ) ),
			),
		);

		$content = self::call( $config, $payload, 2 );
		if ( ! $content ) {
			return null;
		}
		$parsed = self::extract_json( $content );
		if ( ! $parsed || empty( $parsed['short_description'] ) || empty( $parsed['full_description'] ) ) {
			return null;
		}
		return array(
			'short' => sanitize_text_field( $parsed['short_description'] ),
			'full'  => wp_kses_post( wpautop( $parsed['full_description'] ) ),
		);
	}

	private static function build_prompt( array $d ) {
		$list = function ( $v ) {
			if ( is_array( $v ) ) {
				$v = implode( ', ', array_filter( $v ) );
			}
			$v = trim( (string) $v );
			return '' !== $v ? str_replace( array( "\r\n", "\n" ), '; ', $v ) : '—';
		};
		return sprintf(
			"Название: %s\nТип услуг: %s\nСпециализация: %s\nСайт: %s\nЦена: %s %s\nУсловия: %s\nОсобенности: %s",
			$list( $d['title'] ),
			$list( $d['types'] ),
			$list( $d['specs'] ),
			$list( $d['website'] ),
			$list( $d['price_type'] ),
			$list( $d['price_value'] ),
			$list( $d['conditions'] ?? array() ),
			$list( $d['features'] )
		);
	}

	private static function call( array $config, array $payload, $attempts_left ) {
		$response = wp_remote_post(
			$config['endpoint'],
			array(
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $config['headers'] ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( $attempts_left > 0 ) {
				sleep( 2 );
				return self::call( $config, $payload, $attempts_left - 1 );
			}
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || empty( $body['choices'][0]['message']['content'] ) ) {
			if ( $attempts_left > 0 ) {
				sleep( 2 );
				return self::call( $config, $payload, $attempts_left - 1 );
			}
			return null;
		}
		return trim( $body['choices'][0]['message']['content'] );
	}

	private static function extract_json( $content ) {
		$content = trim( $content );
		if ( 0 === strpos( $content, '```' ) ) {
			$content = preg_replace( '/^```[a-zA-Z]*\n?/', '', $content );
			$content = preg_replace( '/```$/', '', $content );
			$content = trim( $content );
		}
		$decoded = json_decode( $content, true );
		return is_array( $decoded ) ? $decoded : null;
	}
}
