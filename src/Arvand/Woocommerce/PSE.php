<?php
/**
 * WooCommerce Product Search
 *
 * @package Arvand\Woocommerce
 */

namespace Arvand\Woocommerce;

defined( 'ABSPATH' ) || exit;

class PSE extends WP_REST_Controller {
	public function register_endpoints(){
		register_rest_route('arvand/v1', '/pse/torob', [
			'methods'				=> WP_REST_Server::READABLE,
			'callback'				=> [$this, 'get_products_torob_v1'],
			'permission_callback'	=> '__return_true',
		]);
		register_rest_route('arvand/v1', '/pse/emalls', [
			'methods'				=> WP_REST_Server::READABLE,
			'callback'				=> [$this, 'get_products_emalls_v1'],
			'permission_callback'	=> '__return_true',
		]);
	}

	public function get_products_torob( WP_REST_Request $request ){

	}

	public function get_products_emalls( WP_REST_Request $request ){
		$paged		= max(1, absint( $request->get_param('page') ));
		$per_page	= absint( $request->get_param('item_per_page') ) ?: 100;
		$per_page	= max(1, $per_page);
		$per_page	= min(100, $per_page);
		
		$results = wc_get_products([
			'limit' => $per_page,
			'page'  => $paged,
			'paginate' => true,
		]);

		$data = [
			'page_num'		=> $paged,
			'total_items'	=> $results->total,
			'pages_count'	=> $results->max_num_pages,
			'item_per_page'	=> $per_page,
			'products'		=> [],
		];

		foreach( $results->products as $product ){
			$cat				= get_primary_term( $product->get_id(), 'product_cat');
			$guarantee			= '';
			$display_product	= arvand_select_default_variation( $product );
			$data['products'][] = [
				'id'			=> $product->get_id(),
				'title'			=> $product->get_name(),
				'price'			=> wc_get_price_to_display( $display_product ),
				'old_price'		=> $product->is_on_sale() ? wc_get_price_to_display( $display_product, ['price' => $display_product->get_regular_price()] ) : null,
				'category'		=> $cat->name ?? '',
				'image'			=> ( $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'full', false) : wc_placeholder_img_src('full') ),
				'color'			=> '',
				'guarantee'		=> $guarantee,
				'is_available'	=> $product->is_in_stock(),
				'url'			=> $product->get_permalink(),
			];
		}

		wp_send_json($data);
	}
}