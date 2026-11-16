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

	public function get_products_torob_v1( WP_REST_Request $request ){

	}

	public function get_products_emalls_v1( WP_REST_Request $request ){		
		$results = $this->get_products( $request->get_param('page'), $request->get_param('item_per_page') );

		$data = [
			'page_num'		=> $results['current_page'],
			'total_items'	=> $results['total'],
			'pages_count'	=> $results['max_page'],
			'item_per_page'	=> $results['per_page'],
			'products'		=> [],
		];

		foreach( $results['products'] as $product ){
			$cat				= get_primary_term( $product->get_id(), 'product_cat');
			$guarantee			= '';
			$data['products'][] = [
				'id'			=> $product->get_id(),
				'title'			=> $product->get_name(),
				'price'			=> wc_get_price_to_display( $product ),
				'old_price'		=> $product->is_on_sale() ? wc_get_price_to_display( $product, ['price' => $product->get_regular_price()] ) : null,
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

	private function get_products($page, $limit){
		$page		= max(1, absint( $page ) );
		$limit		= max(10, min(100, absint( $limit ) ) );

		$results	= wc_get_products([
			'limit'		=> $limit,
			'page'		=> $page,
			'paginate'	=> true,
		]);

		return [
			'current_page'		=> $page,
			'per_page'			=> $limit,
			'max_page'			=> $results->max_num_pages,
			'total'				=> $results->total,
			'products'			=> $results->products,
		];
	}
}