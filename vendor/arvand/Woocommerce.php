<?php
/**
 * WooCommerce Loader
 *
 * Boots SMS, Search and AttrbiutesGroup modules and adds
 * general WooCommerce customizations for the theme.
 *
 * @package Arvand\Woocommercecommerce
 */

namespace Arvand;

defined('ABSPATH') || exit;

final class Woocommerce {

	public function __construct() {
		if( !class_exists('WooCommerce') ) {
			return;
		}

		$this->bootstrap();

		add_filter('woocommerce_show_addons_page', '__return_false');

		//Default Attributes
		add_filter('woocommerce_product_get_default_attributes', [$this, 'default_best_variation'], 20, 2);

		//Loop outofstock products last
		add_filter('posts_clauses', [$this, 'orderby_instock_first'], 2000, 2);

		//Custom related products
		add_action('woocommerce_product_options_related', [$this, 'related_products_field']);
		add_filter('woocommerce_product_related_posts_query', [$this, 'related_products_query'], 999, 3);
		add_filter('woocommerce_product_related_posts_force_display', '__return_true');

		//Min, Max and Step quantity
		add_action('woocommerce_product_options_inventory_product_data', [$this, 'product_qty_fields'], 20);
		add_action('woocommerce_variation_options_inventory', [$this, 'variation_qty_fields'], 20, 3);

		add_filter('woocommerce_quantity_input_min', fn($num, $product) => $this->get_qty_meta($product, 'product_min_qty', 1), 999, 2);
		add_filter('woocommerce_quantity_input_max', fn($num, $product) => $this->get_qty_meta($product, 'product_max_qty', -1), 999, 2);
		add_filter('woocommerce_quantity_input_step', fn($num, $product) => $this->get_qty_meta($product, 'product_step_qty', 1), 999, 2);
		add_filter('woocommerce_available_variation', [$this, 'variation_qty_data'], 10, 3);

		//Save Metas
		add_action('woocommerce_process_product_meta', [$this, 'save_product_meta'], 10);
		add_action('woocommerce_save_product_variation', [$this, 'save_variation_meta'], 10, 2);
	}

	private function bootstrap(){
		Woocommerce\SMS::instance();
		Woocommerce\BIS::instance();
		Woocommerce\Search::instance();
		Woocommerce\AttrbiutesGroup::instance();
	}

	public static function instance(): self {
		static $instance;
		return $instance ??= new self();
	}

	/**
	 * Automatically select the best variation when no default attributes are set
	 * Or when the selected default variation is not available (out of stock/unpurchasable)
	 * Priority: Best discount > Cheapest > First available
	 */
	public function default_best_variation( $default_attributes, $product ){
		if( !$product instanceof \WC_Product_Variable ) {
			return $default_attributes;
		}

		$variation_ids = $product->get_children();
		if( empty( $variation_ids ) ) {
			return $default_attributes;
		}

		if( !empty( $default_attributes ) ) {
			$data_store   = \WC_Data_Store::load('product');
			$variation_id = $data_store->find_matching_product_variation( $product, $default_attributes );

			if( $variation_id > 0 ) {
				$variation = wc_get_product( $variation_id );
				if( $variation instanceof \WC_Product_Variation && $variation->is_purchasable() && $variation->is_in_stock() ) {
					return $default_attributes;
				}
			}
		}

		$children_hash = md5( implode(',', $variation_ids) );
		$cache_key     = 'arvand_auto_default_attrs_' . $product->get_id();
		$cached        = wp_cache_get( $cache_key, 'arvand_wc' );
		if( is_array( $cached ) && ( $cached['hash'] ?? '' ) === $children_hash && !empty( $cached['attrs'] ) ) {
			return $cached['attrs'];
		}

		$prices         = $product->get_variation_prices( true );
		$sale_prices    = $prices['sale_price'] ?? [];
		$regular_prices = $prices['regular_price'] ?? [];
		$current_prices = $prices['price'] ?? [];

		$best_discounted_id = null;
		$best_discount_rate = -1;
		$cheapest_id        = null;
		$cheapest_price     = PHP_FLOAT_MAX;
		$first_available_id = null;

		foreach( $variation_ids as $variation_id ) {
			if( !isset( $current_prices[ $variation_id ] ) ) {
				continue;
			}

			$price = (float) $current_prices[ $variation_id ];
			if( $price < 0 ) {
				continue;
			}

			$variation = wc_get_product( $variation_id );
			if( !$variation instanceof \WC_Product_Variation || !$variation->is_purchasable() || !$variation->is_in_stock() ) {
				continue;
			}

			if( is_null( $first_available_id ) ) {
				$first_available_id = $variation_id;
			}

			if( $price > 0 && $price < $cheapest_price ) {
				$cheapest_price = $price;
				$cheapest_id    = $variation_id;
			}

			$regular = (float) ( $regular_prices[ $variation_id ] ?? 0 );
			$sale    = (float) ( $sale_prices[ $variation_id ] ?? 0 );
			if( $sale > 0 && $regular > $sale ) {
				$discount_rate = ( $regular - $sale ) / $regular;
				if( $discount_rate > $best_discount_rate ) {
					$best_discount_rate = $discount_rate;
					$best_discounted_id = $variation_id;
				}
			}
		}

		$final_id = $best_discounted_id ?? $cheapest_id ?? $first_available_id;
		if( !$final_id ) {
			return $default_attributes;
		}

		$final_variation = wc_get_product( $final_id );
		if( !$final_variation instanceof \WC_Product_Variation ) {
			return $default_attributes;
		}

		$formatted_attributes = [];
		foreach( $final_variation->get_attributes() as $key => $value ) {
			if( '' !== $value ) {
				$formatted_attributes[ 'attribute_' === substr( $key, 0, 10 ) ? substr( $key, 10 ) : $key ] = $value;
			}
		}

		wp_cache_set( $cache_key, [
			'hash'  => $children_hash,
			'attrs' => $formatted_attributes,
		], 'arvand_wc', HOUR_IN_SECONDS );

		return $formatted_attributes;
	}

	public function orderby_instock_first( $clauses, $query ){
		global $wpdb;

		if( !is_woocommerce() || 'product_query' !== $query->get('wc_query') ) {
			return $clauses;
		}

		static $has_lookup = null;
		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		if( null === $has_lookup ) {
			$has_lookup = ( $wpdb->get_var( $wpdb->prepare('SHOW TABLES LIKE %s', $lookup) ) === $lookup );
		}

		if( !$has_lookup ) {
			return $clauses;
		}

		$clauses['join']    .= " LEFT JOIN {$lookup} AS arvand_stock ON ({$wpdb->posts}.ID = arvand_stock.product_id) ";
		$clauses['orderby']  = " CASE WHEN arvand_stock.stock_status = 'instock' THEN 0 ELSE 1 END ASC, " . $clauses['orderby'];

		return $clauses;
	}

	public function related_products_field(){
		global $post;
		$selected_products = get_post_meta( $post->ID, 'custom_related_products', true );
		$selected_products = array_filter( array_map('absint', explode(',', (string) $selected_products) ) ); ?>
		<div class="options_group">
			<p class="form-field">
				<label for="custom_related_products">محصولات مرتبط سفارشی</label>
				<select class="wc-product-search" multiple="multiple" data-multiple="true" style="width: 50%;" id="custom_related_products" name="custom_related_products[]" data-placeholder="جستجوی محصولات" data-action="woocommerce_json_search_products_and_variations">
					<?php
					foreach( $selected_products as $product_id ) {
						$product = wc_get_product( $product_id );
						if( is_object( $product ) ) {
							echo '<option value="' . esc_attr( $product_id ) . '" selected="selected">' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
						}
					}
					?>
				</select>
				<?php echo wc_help_tip('محصولات مرتبط سفارشی که می‌خواهید نمایش داده شوند'); ?>
			</p>
		</div> <?php
	}

	public function related_products_query( $query, $product_id, $args ){
		global $wpdb;
		$custom_ids = [];
		$custom_related_ids = get_post_meta( $product_id, 'custom_related_products', true );

		if( !empty( $custom_related_ids ) ) {
			$custom_ids = array_map('absint', explode(',', $custom_related_ids) );
			if( !empty( $custom_ids ) ) {
				$custom_ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s AND post_id IN (" . implode(',', array_fill( 0, count( $custom_ids ), '%d' ) ) . ")", array_merge( ['_stock_status', 'instock'], $custom_ids ) ) );
			}
		}

		$query['join']  = $query['join'] ?? '';
		$query['where'] = $query['where'] ?? '';

		if( strpos( $query['join'], "{$wpdb->postmeta} pm" ) === false ) {
			$query['join'] .= " INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id ";
		}

		if( strpos( $query['where'], "pm.meta_key = '_stock_status'" ) === false ) {
			$query['where'] .= $wpdb->prepare(" AND pm.meta_key = %s AND pm.meta_value = %s ", '_stock_status', 'instock');
		}

		if( !empty( $custom_ids ) ) {
			$combined_ids = array_unique( array_merge( $custom_ids, $query['post__in'] ?? [] ) );

			if( !empty( $args['posts_per_page'] ) ) {
				$combined_ids = array_slice( $combined_ids, 0, $args['posts_per_page'] );
			}

			$query['post__in'] = $combined_ids;

			if( !empty( $query['orderby'] ) ) {
				$query['orderby'] = "FIELD(p.ID, " . implode(',', $custom_ids) . "), " . $query['orderby'];
			}
		}

		return $query;
	}

	private function render_qty_fields( $product, $is_variation = false, $loop = null ){
		$prefix      = $is_variation ? 'variable_' : 'product_';
		$loop_suffix = $is_variation ? "[$loop]" : '';
		$label       = $is_variation ? 'مدیریت فروش تنوع' : 'مدیریت فروش';

		$fields = [
			'min'  => 'حداقل سفارش',
			'max'  => 'حداکثر سفارش',
			'step' => 'قدم سفارش',
		]; ?>
		<p class="form-field form-row dimensions_field">
			<label><?php echo esc_html( $label ); ?></label>
			<span class="wrap">
				<?php foreach( $fields as $key => $placeholder ):
					$name  = $prefix . $key . '_qty' . $loop_suffix;
					$value = $product->get_meta("product_{$key}_qty");
					$min   = $key === 'max' ? '-1' : '1';
					$placeholder = $is_variation ? 'از والد' : $placeholder; ?>
					<input type="number" name="<?php echo esc_attr( $name ); ?>" size="6" min="<?php echo esc_attr( $min ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" value="<?php echo esc_attr( $value ); ?>">
				<?php endforeach; ?>
			</span>
		</p> <?php
	}

	public function product_qty_fields(){
		global $product_object;
		echo '<div class="options_group show_if_simple show_if_variable">';
		$this->render_qty_fields( $product_object );
		echo '</div>';
	}

	public function variation_qty_fields( $loop, $variation_data, $variation ){
		$this->render_qty_fields( wc_get_product( $variation->ID ), true, $loop );
	}

	public function variation_qty_data( $variation_data, $product, $variation ){
		$variation_data['min_qty'] = $this->get_qty_meta( $variation, 'product_min_qty', 1 );
		$variation_data['max_qty'] = $this->get_qty_meta( $variation, 'product_max_qty', -1 );
		$variation_data['step']    = $this->get_qty_meta( $variation, 'product_step_qty', 1 );
		return $variation_data;
	}

	private function get_qty_meta( $product, $key, $default ){
		if( !$product ) return $default;

		$value = $product->get_meta( $key );

		if( $product->is_type('variation') && $value === '' ) {
			$parent = wc_get_product( $product->get_parent_id() );
			$value  = $parent ? $parent->get_meta( $key ) : '';
		}

		return ( $value !== '' && $value !== null ) ? $value : $default;
	}

	public function save_product_meta( $post_id ){
		$product = wc_get_product( $post_id );

		if( !$product ) {
			return;
		}

		//Custom related products
		if( isset( $_POST['custom_related_products'] ) ) {
			$product_ids = array_filter( array_map('absint', (array) $_POST['custom_related_products']) );
			if( empty( $product_ids ) ){
				$product->delete_meta_data('custom_related_products');
			} else {
				$product->update_meta_data('custom_related_products', implode(',', $product_ids) );
			}
		}

		//Min, Max and Step quantity
		foreach( ['min', 'max', 'step'] as $key ) {
			$field = "product_{$key}_qty";
			if( !isset( $_POST[ $field ] ) ) continue;
			$value = wc_clean( wp_unslash( $_POST[ $field ] ) );
			$product->update_meta_data( $field, $value );
		}

		//Attributes group template
		if( isset( $_POST['arvandwc_attr_template'] ) ) {
			$template = sanitize_key( wp_unslash( $_POST['arvandwc_attr_template'] ) );
			if( $template ){
				$product->update_meta_data( AttrbiutesGroup::META_TEMPLATE, $template );
			} else {
				$product->delete_meta_data( AttrbiutesGroup::META_TEMPLATE );
			}
		}

		$product->save();
	}

	public function save_variation_meta( $variation_id, $i ){
		$variation = wc_get_product( $variation_id );

		if( !$variation ) {
			return;
		}

		foreach( ['min', 'max', 'step'] as $key ) {
			$field = "variable_{$key}_qty";
			if( !isset( $_POST[ $field ][ $i ] ) ) continue;
			$value = wc_clean( wp_unslash( $_POST[ $field ][ $i ] ) );
			$variation->update_meta_data("product_{$key}_qty", $value );
		}

		$variation->save();
	}
}
