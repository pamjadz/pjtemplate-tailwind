<?php
/**
 * WooCommerce Product Compare
 *
 * Product comparison + AJAX live-search endpoint.
 *
 * @package Arvand\Woocommerce
 */

namespace Arvand\Woocommerce;

use WP_Query;
use WC_Product;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

final class Compare {

	private static ?self $instance = null;

	private const MAX_ITEMS     = 4;
	private const RELATED_LIMIT = 4;
	private const SEARCH_LIMIT  = 12;

	private function __construct() {
		add_action( 'wp_ajax_arvandwc_compare_find', [ $this, 'ajax_find' ], 999 );
		add_action( 'wp_ajax_nopriv_arvandwc_compare_find', [ $this, 'ajax_find' ], 999 );
	}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	private static function ids( mixed $ids ): array {
		$ids = is_array( $ids ) ? $ids : explode( ',', (string) $ids );
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Build a compare page URL for one or more product IDs.
	 */
	public static function page_url( int|string|array $ids = 0 ): string {
		$url = home_url( '/compare' );
		$ids = self::ids( $ids );

		return $ids ? add_query_arg( 'ids', implode( ',', $ids ), $url ) : $url;
	}

	/**
	 * Resolve mixed input (IDs, products, variations) into unique parent products.
	 *
	 * @return WC_Product[]
	 */
	private function resolve_products( mixed $items ): array {
		$out = [];

		foreach ( is_array( $items ) ? $items : [ $items ] as $item ) {
			$product = $item instanceof WC_Product ? $item : wc_get_product( absint( $item ) );

			if ( $product instanceof WC_Product_Variation ) {
				$product = wc_get_product( $product->get_parent_id() ) ?: $product;
			}

			if ( $product && $product->exists() ) {
				$out[ $product->get_id() ] = $product;
			}
		}

		return array_values( $out );
	}

	/**
	 * Collect and normalize attributes across products.
	 *
	 * @return array<string, array{label:string, values:string[], product_values:array<int,string[]>}>
	 */
	private function get_attributes( mixed $products ): array {
		$products = $this->resolve_products( $products );

		if ( ! $products ) {
			return [];
		}

		// Prime caches once for all products (avoids N+1 queries).
		$ids = array_map( static fn( WC_Product $p ) => $p->get_id(), $products );
		update_postmeta_cache( $ids );
		update_object_term_cache( $ids, 'product' );

		$attributes = [];

		foreach ( $products as $product ) {
			foreach ( $product->get_attributes() as $attribute ) {
				$name   = $attribute->get_name();
				$values = $attribute->is_taxonomy()
					? (array) wc_get_product_terms( $product->get_id(), $name, [ 'fields' => 'names' ] )
					: (array) $attribute->get_options();

				$attributes[ $name ] ??= [
					'label'          => wc_attribute_label( $name ),
					'values'         => [],
					'product_values' => [],
				];

				$attributes[ $name ]['product_values'][ $product->get_id() ] = $values;
				$attributes[ $name ]['values'] = array_values(
					array_unique( array_merge( $attributes[ $name ]['values'], $values ) )
				);
			}
		}

		return $attributes;
	}

	/**
	 * Comparison payload for a list of product IDs.
	 *
	 * @param string $output 'all' | 'products' | 'attributes'
	 */
	public function get_comparison_data( array $ids, string $output = 'all' ): array {
		$products = $this->resolve_products( array_slice( self::ids( $ids ), 0, self::MAX_ITEMS ) );
		$data     = [];

		if ( 'attributes' !== $output ) {
			$data['products'] = array_map( [ $this, 'format_product' ], $products );
		}

		if ( 'products' !== $output ) {
			$data['attributes'] = $this->get_attributes( $products );
		}

		return $data[ $output ] ?? $data;
	}

	/**
	 * Format a product for JSON output.
	 */
	public function format_product( WC_Product $product ): array {
		return [
			'id'         => $product->get_id(),
			'name'       => $product->get_name(),
			'permalink'  => $product->get_permalink(),
			'price_html' => $product->get_price_html(),
			'sku'        => $product->get_sku(),
			'in_stock'   => $product->is_in_stock(),
			'image'      => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' )
				?: wc_placeholder_img_src( 'woocommerce_thumbnail' ),
		];
	}

	public function ajax_find(): void {
		$query    = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		$selected = array_slice( self::ids( $_GET['ids'] ?? '' ), 0, self::MAX_ITEMS );
		$format   = fn( $items ) => array_map( [ $this, 'format_product' ], $this->resolve_products( $items ) );
		$found    = [];

		if ( '' !== $query ) {
			$args = [
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => self::SEARCH_LIMIT,
				's'              => $query,
				'post__not_in'   => $selected,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			];

			// Restrict the search to the categories of the selected products.
			$cats = $selected ? wp_get_object_terms( $selected, 'product_cat', [ 'fields' => 'ids' ] ) : [];

			if ( $cats && ! is_wp_error( $cats ) ) {
				$args['tax_query'] = [ [ 'taxonomy' => 'product_cat', 'terms' => $cats ] ];
			}

			$found = ( new WP_Query( $args ) )->posts;
		}

		wp_send_json( [
			'find'       => $query,
			'related'    => $selected ? $format( wc_get_related_products( $selected[0], self::RELATED_LIMIT, $selected ) ) : [],
			'selected'   => $format( $found ),
			'attributes' => $this->get_attributes( $selected ),
		]);
	}
}