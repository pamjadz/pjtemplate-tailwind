<?php
/**
 * WooCommerce Product Search
 *
 * SKU + multi-word title search with keyword rewriting, in-stock-first
 * ordering, and a JSON live-search AJAX endpoint.
 *
 * @package Arvand\Woocommerce
 */

namespace Arvand\Woocommerce;

use WP_Query;

defined( 'ABSPATH' ) || exit;

final class Compare {
	protected static $instance;

	protected function __construct() {
		add_action( 'wp_ajax_arvandwc_compare_find', [ $this, 'ajax_find' ], 999 );
		add_action( 'wp_ajax_nopriv_arvandwc_compare_find',	[ $this, 'ajax_find' ], 999 );
	}

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function ajax_find(){
		$query		= sanitize_text_field( $_GET['q'] ?? '' );
		$products	= array_filter( explode(',', sanitize_text_field( $_GET['ids'] ?? '' ) ) );
		
		// TODO: user search in input for find related products, then click on them and should change selected items

		$output = [
			'find'		=> $query,
			'related'	=> wc_get_related_products($products[0], 4, $products),
			'selected'	=> [],
		];

		// TODO Convert this to wc_get_products
		$products = new WP_Query(['post_type' => 'product', 'posts_per_page' => 12, 's' => $query]);

		foreach ( $products as $this_product ) :
			// TODO: add product image, name, link and attrbiutes to $output[selected]
			// TODO: if product is variation display parent

			// $attributes = $product->get_attributes();
			// foreach ($attributes as $attribute) {
			// 	$name = $attribute->get_name();
			// 	if (!isset($all_attributes[$name])) {
			// 		$all_attributes[$name] = wc_attribute_label($name);
			// 	}
			// }
		endforeach;
	}
	
	public static function get_compare_link( ){
		return add_query_arg(['ids' => $product_id], home_url('/compare'));
	}
}
