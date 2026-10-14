<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JSON-LD (schema.org) for a single contractor — the one thing neither this
 * plugin nor llm-agent-github-repos emitted: PC_SEO/LAGR_SEO only ever wrote a
 * meta description into Yoast/RankMath. Without it the aggregate rating the
 * site collects is invisible to search engines, i.e. no star snippet, which is
 * the whole practical payoff of having ratings at all.
 *
 * One <script> with an @graph rather than several tags: the FAQ entries and the
 * service itself are separate entities, and a graph is how they stay linked
 * (the FAQPage is `about` the service) instead of looking like two unrelated
 * documents on one URL.
 *
 * Gated behind the `schema` feature flag because a full SEO plugin may already
 * output its own graph for the post type, and two conflicting descriptions of
 * the same URL are worse than one — turn this off if Yoast/RankMath is
 * configured to handle `contractor` itself.
 */
class PC_Schema {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 20 );
	}

	public static function output() {
		if ( ! is_singular( 'contractor' ) || ! PC_Settings::integration_enabled( 'schema' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$graph = array( self::main_entity( $post_id ) );

		$faq = self::faq_entity( $post_id );
		if ( $faq ) {
			$graph[] = $faq;
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		printf(
			"<script type=\"application/ld+json\">%s</script>\n",
			wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}

	private static function entity_id( $post_id ) {
		return get_permalink( $post_id ) . '#service';
	}

	private static function main_entity( $post_id ) {
		$types = wp_get_post_terms( $post_id, 'contractor_type', array( 'fields' => 'names' ) );
		$specs = wp_get_post_terms( $post_id, 'contractor_specialization', array( 'fields' => 'names' ) );
		$types = is_wp_error( $types ) ? array() : $types;
		$specs = is_wp_error( $specs ) ? array() : $specs;

		$stored_type = (string) get_post_meta( $post_id, '_pc_schema_type', true );
		$schema_type = array_key_exists( $stored_type, PC_Meta_Fields::schema_types() ) ? $stored_type : 'SoftwareApplication';

		$entity = array(
			'@type' => $schema_type,
			'@id'   => self::entity_id( $post_id ),
			'name'  => get_the_title( $post_id ),
			'url'   => get_permalink( $post_id ),
		);

		$description = trim( (string) get_post_meta( $post_id, '_pc_short_description', true ) );
		if ( '' !== $description ) {
			$entity['description'] = $description;
		}

		$image = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $image ) {
			// Organization expects `logo`, SoftwareApplication a plain image.
			$entity[ 'Organization' === $schema_type ? 'logo' : 'image' ] = $image;
		}

		// The partner's own site is `sameAs`, never `url` — `url` has to stay
		// the catalog page this markup describes, or the entity claims to BE
		// the external site.
		$website = (string) get_post_meta( $post_id, '_pc_website', true );
		if ( '' !== $website ) {
			$entity['sameAs'] = array( $website );
		}

		if ( 'SoftwareApplication' === $schema_type ) {
			$entity['applicationCategory'] = $types ? implode( ', ' , $types ) : 'BusinessApplication';
			// Required by Google for a SoftwareApplication result; this is a
			// catalog of web services, so there is one honest answer.
			$entity['operatingSystem'] = 'Web';
		}

		if ( $specs ) {
			$entity['keywords'] = implode( ', ', $specs );
		}

		$offer = self::offer( $post_id );
		if ( $offer ) {
			$entity['offers'] = $offer;
		}

		$rating = self::aggregate_rating( $post_id );
		if ( $rating ) {
			$entity['aggregateRating'] = $rating;
		}

		return $entity;
	}

	/**
	 * Offer/AggregateOffer built from the price type+value pair. Only emitted
	 * when the value is actually a number: "по запросу" or a usage-based tariff
	 * has no price to state, and inventing one ("0") would be wrong markup
	 * rather than merely incomplete markup.
	 */
	private static function offer( $post_id ) {
		$type  = (string) get_post_meta( $post_id, '_pc_price_type', true );
		$value = (string) get_post_meta( $post_id, '_pc_price_value', true );

		if ( 'free' === $type ) {
			return array(
				'@type'         => 'Offer',
				'price'         => 0,
				'priceCurrency' => 'RUB',
			);
		}

		if ( 'range' === $type ) {
			$parts = preg_split( '/[–—-]/u', $value );
			$low   = self::to_number( $parts[0] ?? '' );
			$high  = self::to_number( $parts[1] ?? '' );
			if ( null !== $low && null !== $high ) {
				return array(
					'@type'         => 'AggregateOffer',
					'lowPrice'      => $low,
					'highPrice'     => $high,
					'priceCurrency' => 'RUB',
				);
			}
			return array();
		}

		if ( in_array( $type, array( 'from', 'fixed', 'hourly', 'subscription' ), true ) ) {
			$number = self::to_number( $value );
			if ( null === $number ) {
				return array();
			}
			$offer = array(
				'@type'         => 'Offer',
				'price'         => $number,
				'priceCurrency' => 'RUB',
			);
			if ( 'subscription' === $type ) {
				$offer['priceSpecification'] = array(
					'@type'                => 'UnitPriceSpecification',
					'price'                => $number,
					'priceCurrency'        => 'RUB',
					'billingIncrement'     => 1,
					'unitCode'             => 'MON',
				);
			}
			return $offer;
		}

		return array();
	}

	/** "150 000" / "1 500,50" → float; anything not purely numeric → null. */
	private static function to_number( $raw ) {
		$clean = str_replace( array( ' ', "\xC2\xA0", ' ' ), '', (string) $raw );
		$clean = str_replace( ',', '.', $clean );
		if ( '' === $clean || ! is_numeric( $clean ) ) {
			return null;
		}
		return (float) $clean;
	}

	private static function aggregate_rating( $post_id ) {
		$count = PC_Ratings::get_count( $post_id );
		if ( $count < 1 ) {
			return array();
		}
		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => PC_Ratings::get_average( $post_id ),
			'ratingCount' => $count,
			'bestRating'  => 5,
			'worstRating' => 1,
		);
	}

	private static function faq_entity( $post_id ) {
		$items = PC_Meta_Fields::faq_items( $post_id );
		if ( empty( $items ) ) {
			return array();
		}

		$questions = array();
		foreach ( $items as $item ) {
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $item['q'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $item['a'],
				),
			);
		}

		return array(
			'@type'      => 'FAQPage',
			'@id'        => get_permalink( $post_id ) . '#faq',
			'about'      => array( '@id' => self::entity_id( $post_id ) ),
			'mainEntity' => $questions,
		);
	}
}
