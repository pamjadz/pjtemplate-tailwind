<?php
/**
 * WooCommerce Customizations
 * 
 * @package YourTheme
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

if (!class_exists('WooCommerce')) {
	return;
}

// =============================================================================
// Common & Settings
// =============================================================================

/**
 * Hide WooCommerce addons page
 */
add_filter('woocommerce_show_addons_page', '__return_false');

/**
 * Add theme support for WooCommerce
 */
add_theme_support('woocommerce', [
	'thumbnail_image_width' => 300,
	'single_image_width'    => 600,
	'product_grid'          => [
		'default_rows'    => 3,
		'min_rows'        => 1,
		'max_rows'        => 8,
		'default_columns' => 4,
		'min_columns'     => 1,
		'max_columns'     => 6,
	],
]);

/**
 * Add custom store details fields to WooCommerce settings
 */
add_filter('woocommerce_general_settings', function($settings) {
	$new_settings = [];
	
	foreach ($settings as $setting) {
		$new_settings[] = $setting;
		
		if (isset($setting['id']) && 'woocommerce_store_postcode' === $setting['id']) {
			$new_settings[] = [
				'title'    => 'نام حقوقی فروشگاه',
				'type'     => 'text',
				'id'       => 'store_details[name]',
				'css'      => 'min-width:300px;',
				'default'  => '',
				'desc_tip' => true,
				'desc'     => 'نام رسمی/حقوقی شرکت یا فروشگاه',
			];
			
			$new_settings[] = [
				'title'    => 'شماره ثبت/کد اقتصادی',
				'type'     => 'text',
				'id'       => 'store_details[registration_number]',
				'css'      => 'min-width:300px;',
				'default'  => '',
				'desc_tip' => true,
				'desc'     => 'شماره ثبت شرکت یا کد اقتصادی',
			];
			
			$new_settings[] = [
				'title'              => 'شماره تلفن ثابت',
				'type'               => 'tel',
				'id'                 => 'store_details[tel]',
				'css'                => 'min-width:300px;',
				'default'            => '',
				'custom_attributes'  => ['dir' => 'ltr'],
				'desc_tip'           => true,
				'desc'               => 'شماره تلفن ثابت فروشگاه',
			];
			
			$new_settings[] = [
				'title'              => 'شماره همراه مدیریت',
				'type'               => 'tel',
				'id'                 => 'store_details[admin_phone]',
				'css'                => 'min-width:300px;',
				'default'            => '',
				'custom_attributes'  => ['dir' => 'ltr'],
				'desc'               => 'از این شماره برای اطلاع‌رسانی سفارشات استفاده می‌شود',
				'desc_tip'           => true,
			];
		}
	}
	
	return $new_settings;
});

/**
 * Remove default WooCommerce inline styles
 */
add_action('wp_print_styles', function() {
	wp_style_add_data('woocommerce-inline', 'after', '');
});

add_filter('woocommerce_enqueue_styles', '__return_empty_array');

/**
 * Dequeue unnecessary WooCommerce blocks styles and scripts
 */
add_action('wp_enqueue_scripts', function() {
	$styles_to_remove = [
		'wc-blocks-style',
		'wc-blocks-style-active-filters',
		'wc-blocks-style-add-to-cart-form',
		'wc-blocks-packages-style',
		'wc-blocks-style-all-products',
		'wc-blocks-style-all-reviews',
		'wc-blocks-style-attribute-filter',
		'wc-blocks-style-breadcrumbs',
		'wc-blocks-style-catalog-sorting',
		'wc-blocks-style-customer-account',
		'wc-blocks-style-featured-category',
		'wc-blocks-style-featured-product',
		'wc-blocks-style-mini-cart',
		'wc-blocks-style-price-filter',
		'wc-blocks-style-product-add-to-cart',
		'wc-blocks-style-product-button',
		'wc-blocks-style-product-categories',
		'wc-blocks-style-product-image',
		'wc-blocks-style-product-image-gallery',
		'wc-blocks-style-product-query',
		'wc-blocks-style-product-results-count',
		'wc-blocks-style-product-reviews',
		'wc-blocks-style-product-sale-badge',
		'wc-blocks-style-product-search',
		'wc-blocks-style-product-sku',
		'wc-blocks-style-product-stock-indicator',
		'wc-blocks-style-product-summary',
		'wc-blocks-style-product-title',
		'wc-blocks-style-rating-filter',
		'wc-blocks-style-reviews-by-category',
		'wc-blocks-style-reviews-by-product',
		'wc-blocks-style-product-details',
		'wc-blocks-style-single-product',
		'wc-blocks-style-stock-filter',
		'wc-blocks-style-cart',
		'wc-blocks-style-checkout',
		'wc-blocks-style-mini-cart-contents',
		'classic-theme-styles-inline',
	];
	
	foreach ($styles_to_remove as $style) {
		wp_deregister_style($style);
	}
	
	$scripts_to_remove = [
		'wc-blocks-middleware',
		'wc-blocks-data-store',
	];
	
	foreach ($scripts_to_remove as $script) {
		wp_deregister_script($script);
	}
	
	// Remove brand styles if exists
	wp_dequeue_style('brands-styles');
	wp_dequeue_script('wc-single-product');
}, 9999);

/**
 * Remove gallery noscript and no-js scripts
 */
add_action('init', function() {
	remove_action('wp_head', 'wc_gallery_noscript');
});

add_filter('body_class', function($classes) {
	remove_action('wp_footer', 'wc_no_js');
	return $classes;
});

/**
 * Custom rating HTML
 */
add_filter('woocommerce_product_get_rating_html', function($html, $rating, $count) {
	if (!$count) {
		return '';
	}
	
	$label = sprintf('از %s با %s رای', 5, number_format($count));
	$rating_display = number_format($rating, 1);
	
	$html = sprintf(
		'<span class="star-rating d-inline-flex align-items-center gap-05 fsz-16 fw-600 lh-20" role="img" aria-label="%s">',
		esc_attr($label)
	);
	$html .= '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" color="#F6A924" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/></svg> ';
	$html .= $rating_display;
	$html .= '</span>';
	
	return $html;
}, 10, 3);

/**
 * Custom stock HTML
 */
add_filter('woocommerce_get_stock_html', function($html, $product) {
	if (!$product->get_manage_stock()) {
		return $html;
	}
	
	$no_stock_amount = absint(get_option('woocommerce_notify_no_stock_amount', 0));
	$stock_amount = $product->get_stock_quantity();
	$display = false;
	$html = '';
	
	if ('outofstock' === $product->get_stock_status() || $stock_amount === $no_stock_amount) {
		// Out of stock - handled by availability
		return $html;
	}
	
	switch (get_option('woocommerce_stock_format')) {
		case 'low_amount':
			if ($stock_amount <= wc_get_low_stock_amount($product)) {
				$display = sprintf(
					'تنها %s عدد در انبار باقی مانده',
					wc_format_stock_quantity_for_display($stock_amount, $product)
				);
			}
			break;
		case 'no_amount':
			$display = false;
			break;
	}
	
	if ($product->backorders_allowed() && $product->backorders_require_notification()) {
		$display = '(قابل پیش‌سفارش)';
	}
	
	if ($display) {
		$html = sprintf(
			'<p class="product-stock d-flex align-items-center text-danger"><svg width="20" height="20"><use xlink:href="#icon-flag" /></svg> %s</p>',
			esc_html($display)
		);
	}
	
	return $html;
}, 99, 2);

/**
 * Remove grouped and external product types
 */
add_filter('product_type_selector', function($types) {
	unset($types['grouped'], $types['external']);
	return $types;
});

// =============================================================================
// Products And Metas
// =============================================================================

/**
 * Add custom related products selector
 */
add_action('woocommerce_product_options_related', function() {
	global $post;
	$selected_products = get_post_meta($post->ID, 'custom_related_products', true);
	$selected_products = explode(',', $selected_products) ?? [];
	$selected_products = array_filter( array_map('absint', $selected_products) ); ?>
	<div class="options_group">
		<p class="form-field">
			<label for="custom_related_products">محصولات مرتبط سفارشی</label>
			<select class="wc-product-search" multiple="multiple" data-multiple="true" style="width: 50%;" id="custom_related_products" name="custom_related_products[]" data-placeholder="جستجوی محصولات" data-action="woocommerce_json_search_products_and_variations">
				<?php
				if (!empty($selected_products)) {
					foreach ($selected_products as $product_id) {
						$product = wc_get_product($product_id);
						if (is_object($product)) {
							echo '<option value="' . esc_attr($product_id) . '" selected="selected">' . wp_kses_post($product->get_formatted_name()) . '</option>';
						}
					}
				}
				?>
			</select>
			<?php echo wc_help_tip('محصولات مرتبط سفارشی که می‌خواهید نمایش داده شوند'); ?>
		</p>
	</div> <?php
});

/**
 * Save product quantity metas
 */
add_action('woocommerce_process_product_meta', function($post_id) {
	$product = wc_get_product($post_id);
	
	if (!$product) {
		return;
	}

	if ( isset( $_POST['custom_related_products'] ) ) {
		$product_ids = array_filter( array_map('absint', (array) $_POST['custom_related_products']) );
		if( empty( $product_ids ) ){
			delete_post_meta($post_id, 'custom_related_products');
		} else {
			update_post_meta($post_id, 'custom_related_products', implode(',', $product_ids));
		}
	}
	
	$product->save();
});

// =============================================================================
// FrontEnd
// =============================================================================

/**
 * Custom template wrapper for cart, checkout, and account pages
 */
add_filter('template_include', function($template) {
	if (is_cart() || is_checkout() || is_account_page()) {
		$custom_template = wc_locate_template('page-wrap.php');
		if ($custom_template) {
			$template = $custom_template;
		}
	}
	return $template;
});

/**
 * Remove default content wrappers
 */
remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);

/**
 * Move archive description after main content
 */
add_action('woocommerce_after_main_content', function() {
	do_action('woocommerce_archive_description');
}, 20);

/**
 * Customize pagination
 */
add_filter('woocommerce_pagination_args', function($args) {
	$args['type'] = 'plain';
	$args['mid_size'] = 1;
	$args['end_size'] = 1;
	$args['prev_next'] = !wp_is_mobile();
	$args['prev_text'] = '&larr;';
	$args['next_text'] = '&rarr;';
	
	return $args;
});

/**
 * Remove default loop hooks
 */
remove_action('woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10);
remove_action('woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10);
remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);

/**
 * Customize catalog ordering
 */
add_filter('woocommerce_catalog_orderby', function($catalog_orders) {
	return [
		'menu_order' => 'پیشنهادی',
		'date'       => 'جدیدترین',
		'price'      => 'ارزان‌ترین',
		'price-desc' => 'گران‌ترین',
		'popularity' => 'پربازدید',
	];
}, 99);

/**
 * Sort in-stock products first
 */
add_filter('posts_clauses', function($clauses, $query) {
	global $wpdb;

	if (is_admin() || !$query->is_main_query() || (!is_shop() && !is_product_taxonomy())) {
		return $clauses;
	}

	if (strpos($clauses['join'], 'stock_status_meta') !== false) {
		return $clauses;
	}

	$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS stock_status_meta ON ({$wpdb->posts}.ID = stock_status_meta.post_id AND stock_status_meta.meta_key = '_stock_status')";
	
	$clauses['join'] .= "
		LEFT JOIN (
			SELECT 
				post_parent, 
				MIN(
					FIELD(
						variation_stock_meta.meta_value, 
						'instock', 
						'onbackorder', 
						'outofstock'
					)
				) AS best_stock_order
			FROM 
				{$wpdb->posts} AS variations
			INNER JOIN 
				{$wpdb->postmeta} AS variation_stock_meta ON (variations.ID = variation_stock_meta.post_id AND variation_stock_meta.meta_key = '_stock_status')
			WHERE 
				variations.post_type = 'product_variation'
			GROUP BY 
				post_parent
		) AS variation_stock_status ON ({$wpdb->posts}.ID = variation_stock_status.post_parent)
	";
	

	$stock_status_sort_field = "
		COALESCE(
			variation_stock_status.best_stock_order,
			FIELD(stock_status_meta.meta_value, 'instock', 'onbackorder', 'outofstock')
		)
	";

	$clauses['orderby'] = $stock_status_sort_field . " ASC, " . $clauses['orderby'];
	
	return $clauses;
}, 20, 2);


/**
 * Custom price display for variable products
 * Shows cheapest variation or on-sale variation
 */
add_filter('woocommerce_get_price_html', function($price, $product) {
	if (is_admin() || '' === $product->get_price()) {
		return $price;
	}
	
	$display_product = arvand_select_default_variation($product);
	
	if (!$display_product) {
		return '<span class="product-display-price out-of-stock">ناموجود</span>';
	}
	
	$args = apply_filters('wc_price_args', [
		'currency'           => '',
		'decimal_separator'  => wc_get_price_decimal_separator(),
		'thousand_separator' => wc_get_price_thousand_separator(),
		'decimals'           => wc_get_price_decimals(),
		'price_format'       => get_woocommerce_price_format(),
	]);
	
	$format_price = function($p) use ($args) {
		return is_numeric($p) 
			? number_format($p, $args['decimals'], $args['decimal_separator'], $args['thousand_separator']) 
			: $p;
	};
	
	$price = wc_get_price_to_display($display_product);
	
	if (apply_filters('woocommerce_price_trim_zeros', false) && $args['decimals'] > 0) {
		$price = wc_trim_zeros($price);
	}
	
	$output = '<span class="product-display-price leading-6">';
	
	if ($display_product->is_on_sale()) {
		$regular_price = wc_get_price_to_display($display_product, ['price' => $display_product->get_regular_price()]);
		$percent = $regular_price > 0 ? round(100 - ($price / $regular_price * 100)) : 0;
		
		$output .= '<span class="flex items-center gap-2">';
		$output .= '<del aria-hidden="true" class="text-muted font-bold" dir="ltr">' . $format_price($regular_price) . '</del> ';
		$output .= '<i class="bg-red-600 text-white rounded-4xl p-1 text-xs font-bold not-italic">' . $percent . '%</i> ';
		$output .= '</span>';
		$output .= '<span class="sr-only">' . esc_html(sprintf('قیمت اصلی: %s', $format_price($regular_price))) . '</span>';
		$output .= '<ins aria-hidden="true" class="no-underline font-bold text-base" dir="ltr">' . $format_price($price) . '</ins> ';
		$output .= '<small class="text-muted woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol($args['currency']) . '</small>';
		$output .= '<span class="sr-only">' . esc_html(sprintf('قیمت فعلی: %s', $format_price($price))) . '</span>';
	} else {
		$output .= $format_price($price) . ' <small class="text-muted woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol($args['currency']) . '</small>';
	}
	
	$output .= '</span>';
	
	return $output;
}, 999, 2);

/**
 * Helper function to select default variation intelligently
 * Priority: 1. Cheapest on-sale 2. Default attributes 3. Cheapest in-stock
 */
function arvand_select_default_variation( $product ) {
	if( !$product->is_type('variable')) {
		return $product->is_in_stock() ? $product : false;
	}
	
	$available_variations = $product->get_available_variations();
	
	if (empty($available_variations)) {
		return false;
	}
	
	$first_variation = null;
	$variations_with_discount = [];
	$variations_without_discount = [];
	
	foreach ($available_variations as $variation_data) {
		$variation = wc_get_product($variation_data['variation_id']);
		
		if (!$variation || !$variation->is_in_stock()) {
			continue;
		}
		
		if (is_null($first_variation)) {
			$first_variation = $variation;
		}
		
		$variation_price = wc_get_price_to_display($variation);
		$regular_price = wc_get_price_to_display($variation, ['price' => $variation->get_regular_price()]);
		
		if ($variation->is_on_sale() && $regular_price > $variation_price) {
			$variations_with_discount[] = [
				'product'       => $variation,
				'price'         => $variation_price,
				'regular_price' => $regular_price,
			];
		} else {
			$variations_without_discount[] = [
				'product'       => $variation,
				'price'         => $variation_price,
				'regular_price' => $regular_price,
			];
		}
	}
	
	// Priority 1: Cheapest on-sale variation
	if (!empty($variations_with_discount)) {
		usort($variations_with_discount, function($a, $b) {
			return $a['price'] <=> $b['price'];
		});
		return $variations_with_discount[0]['product'];
	}
	
	// Priority 2: Default variation
	$default_attributes = $product->get_default_attributes();
	if (!empty($default_attributes)) {
		$default_variation_id = $product->get_matching_variation($default_attributes);
		if ($default_variation_id) {
			$default_variation = wc_get_product($default_variation_id);
			if ($default_variation && $default_variation->is_in_stock()) {
				return $default_variation;
			}
		}
	}
	
	// Priority 3: Cheapest in-stock variation
	if (!empty($variations_without_discount)) {
		usort($variations_without_discount, function($a, $b) {
			return $a['price'] <=> $b['price'];
		});
		return $variations_without_discount[0]['product'];
	}
	
	return $first_variation;
}

/**
 * Custom archive description
 */
remove_action('woocommerce_archive_description', 'woocommerce_product_archive_description', 10);
remove_action('woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10);

add_action('woocommerce_archive_description', function() {
	$content = '';
	
	// Shop page description
	if (is_post_type_archive('product') && in_array(absint(get_query_var('paged')), [0, 1], true)) {
		$shop_page = get_post(wc_get_page_id('shop'));
		
		if ($shop_page) {
			$allowed_html = array_merge(
				wp_kses_allowed_html('post'),
				[
					'form'   => [
						'action' => true, 'accept' => true, 'accept-charset' => true,
						'enctype' => true, 'method' => true, 'name' => true, 'target' => true,
					],
					'input'  => [
						'type' => true, 'id' => true, 'class' => true,
						'placeholder' => true, 'name' => true, 'value' => true,
					],
					'button' => ['type' => true, 'class' => true, 'label' => true],
					'svg'    => [
						'hidden' => true, 'role' => true, 'focusable' => true,
						'xmlns' => true, 'width' => true, 'height' => true, 'viewbox' => true,
					],
					'path'   => ['d' => true],
				]
			);
			
			$content = wc_format_content(wp_kses($shop_page->post_content, $allowed_html));
		}
	}
	// Taxonomy description
	elseif (is_product_taxonomy() && absint(get_query_var('paged')) === 0) {
		$term = get_queried_object();
		if ($term && !is_wp_error($term)) {
			$content = apply_filters('woocommerce_taxonomy_archive_description_raw', $term->description, $term);
			$content = wc_format_content(wp_kses_post($content));
		}
	}
	
	if (!$content) {
		return;
	}
	?>
	<article class="product-desc heightlimit mt-5" aria-labelledby="product-desc-title" style="--heightlimit:250px">
		<h1 id="product-desc-title" class="page-title fsz-16 mb-3"><?php woocommerce_page_title(); ?></h1>
		<div class="limitcontent contentstyle"><?php echo $content; ?></div>
		<button type="button" aria-label="مشاهده بیشتر" class="btn p-0 btn-link btn-icon fsz-13 fw-700 limitmore" disabled>
			مشاهده بیشتر 
			<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
		</button>
	</article>
	<?php
}, 10);

/**
 * Add color picker to color attribute
 */
add_action('pa_color_edit_form_fields', function($term) {
	wp_enqueue_script('wp-color-picker');
	wp_enqueue_style('wp-color-picker');
	
	$colorhex = get_term_meta($term->term_id, 'color', true);
	?>
	<tr class="form-field term-group-wrap">
		<th scope="row">
			<label for="tag-colorhex">رنگ نمایشی</label>
		</th>
		<td>
			<input 
				type="text" 
				name="colorhex" 
				id="tag-colorhex" 
				value="<?php echo esc_attr($colorhex); ?>" 
				size="40"
			>
			<p class="description">کد رنگ hex برای نمایش در سایت</p>
			<script>
				jQuery(document).ready(function($) {
					$('#tag-colorhex').wpColorPicker();
				});
			</script>
		</td>
	</tr>
	<?php
}, 0);

/**
 * Save color attribute meta
 */
add_action('edit_pa_color', function($term_id) {
	if (isset($_POST['colorhex'])) {
		$meta = sanitize_hex_color($_POST['colorhex']);
		update_term_meta($term_id, 'color', $meta);
	}
}, 10);

/**
 * Add color visual to layered nav
 */
add_filter('woocommerce_layered_nav_term_html', function($term_html, $term, $link, $count) {
	if (is_a($term, 'WP_Term') && $term->taxonomy === 'pa_color') {
		$color = get_term_meta($term->term_id, 'color', true);
		$color = sanitize_hex_color($color);
		if ($color) {
			$term_html .= sprintf('<i class="color-term" style="background-color:%s"></i>', esc_attr($color) );
		}
	}
	return $term_html;
}, 98, 4);

/**
 * Customize dropdown variation for colors
 */
add_filter('woocommerce_dropdown_variation_attribute_options_html', function($html, $args) {
	if ($args['attribute'] !== 'pa_color') {
		return $html;
	}
	
	$html = '<div class="switch-variation colorselect">' . $html;
	
	foreach ($args['options'] as $option) {
		$term = get_term_by('slug', $option, 'pa_color');
		
		if (!$term) {
			continue;
		}
		
		$color = sanitize_hex_color(get_term_meta($term->term_id, 'color', true));
		
		$html .= sprintf(
			'<button type="button" data-target="attribute_%s" value="%s"%s style="--color:%s" title="%s" aria-label="%s"></button>',
			esc_attr($args['attribute']),
			esc_attr($option),
			disabled($args['selected'], $option, false),
			esc_attr($color ?: '#cccccc'),
			esc_attr($term->name),
			esc_attr($term->name)
		);
	}
	
	$html .= '</div>';
	
	return $html;
}, 99, 2);

/**
 * Single product customizations
 */
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);

// Move meta to top
add_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 10);

// Remove brand display if exists
if (isset($GLOBALS['WC_Brands'])) {
	remove_action('woocommerce_product_meta_end', [$GLOBALS['WC_Brands'], 'show_brand']);
}

/**
 * Customize product tabs
 */
add_filter('woocommerce_product_tabs', function($tabs) {
	global $post, $product;

	return $tabs;
});

/**
 * Force display related products
 */
add_filter('woocommerce_product_related_posts_force_display', '__return_true');

/**
 * Display Custom and only in-stock products in related products
 */
add_filter('woocommerce_product_related_posts_query', function($query, $product_id, $args) {
    global $wpdb;
    $custom_ids = [];
	$custom_related_ids = get_post_meta($product_id, 'custom_related_products', true);
	
    if( !empty($custom_related_ids) ) {
        $custom_ids = array_map('absint', explode(',', $custom_related_ids));
        if (!empty($custom_ids)) {
			$in_stock_custom_ids = $wpdb->get_col($wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s AND post_id IN (" . implode(',', array_fill(0, count($custom_ids), '%d')) . ")", array_merge(['_stock_status', 'instock'], $custom_ids)));
            $custom_ids = $in_stock_custom_ids;
        }
    }
    
    $query['join'] = $query['join'] ?? '';
    $query['where'] = $query['where'] ?? '';
    
    if (strpos($query['join'], "{$wpdb->postmeta} pm") === false) {
        $query['join'] .= " INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id ";
    }

    if (strpos($query['where'], "pm.meta_key = '_stock_status'") === false) {
        $query['where'] .= $wpdb->prepare(" AND pm.meta_key = %s AND pm.meta_value = %s ", '_stock_status', 'instock');
    }
    
	if( !empty( $custom_ids ) ) {
		$original_ids = [];
		if (!empty($query['post__in'])) {
			$original_ids = $query['post__in'];
		}
		$combined_ids = array_merge($custom_ids, $original_ids);
		$combined_ids = array_unique($combined_ids);
		
		if (!empty($args['posts_per_page'])) {
			$combined_ids = array_slice($combined_ids, 0, $args['posts_per_page']);
		}
		
		$query['post__in'] = $combined_ids;
		
		if (!empty($query['orderby'])) {
			$query['orderby'] = "FIELD(p.ID, " . implode(',', $custom_ids) . "), " . $query['orderby'];
		}
	}
    
    return $query;
}, 999, 3);

/**
 * Customize add to cart button text
 */
add_filter('woocommerce_product_single_add_to_cart_text', function($button_text, $product) {
	return 'افزودن به سبد';
}, 99, 2);

/**
 * Show variation prices
 */
add_filter('woocommerce_show_variation_price', '__return_true', 99);
add_filter('woocommerce_ajax_variation_threshold', fn() => 1000);
add_filter('woocommerce_hide_incompatible_variations', '__return_false');

/**
 * Remove single variation template
 */
remove_action('woocommerce_single_variation', 'woocommerce_single_variation', 10);

/**
 * Remove review rating display
 */
remove_action('woocommerce_review_before_comment_meta', 'woocommerce_review_display_rating', 10);

/**
 * Mini cart customizations
 */
remove_action('woocommerce_widget_shopping_cart_total', 'woocommerce_widget_shopping_cart_subtotal', 10);
remove_action('woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10);
remove_action('woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout', 20);

add_action('woocommerce_widget_shopping_cart_buttons', function() {
	printf(
		'<a href="%s" class="btn btn-secondary w-full p-3 mt-5 rounded-full">%s</a>',
		esc_url(wc_get_cart_url()),
		esc_html('مشاهده سبد خرید')
	);
}, 30);

/**
 * Cart fragments for AJAX updates
 */
add_filter('woocommerce_add_to_cart_fragments', function($fragments) {
	// Cart count
	$fragments['span.wc-cart-count'] = sprintf(
		'<span class="wc-cart-count">%s</span>',
		WC()->cart->get_cart_contents_count()
	);
	
	// Mini cart content
	ob_start();
	woocommerce_mini_cart();
	$mini_cart = ob_get_clean();
	$fragments['div.widget_shopping_cart_content'] = sprintf(
		'<div class="widget_shopping_cart_content">%s</div>',
		$mini_cart
	);

	return $fragments;
});

/**
 * Move cross-sells to after cart
 */
remove_action('woocommerce_cart_collaterals', 'woocommerce_cross_sell_display');
add_action('woocommerce_after_cart', 'woocommerce_cross_sell_display', 10);

/**
 * Remove empty cart message default
 */
remove_action('woocommerce_cart_is_empty', 'wc_empty_cart_message', 10);

/**
 * Disable add to cart notices (using custom notifications)
 */
add_filter('wc_add_to_cart_message_html', '__return_empty_string');
add_filter('woocommerce_cart_redirect_after_error', '__return_false');

/**
 * Checkout customizations
 */
remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
add_action('woocommerce_before_checkout_form', 'kalabala_purchase_steps', 0);
add_action('woocommerce_checkout_after_customer_details', 'woocommerce_checkout_payment', 20);

/**
 * Add shipping methods to checkout
 */
function arvand_wc_get_shipping_methods() {
	echo '<div class="arvand-checkout-shipping-methods">';
	
	if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) :
		do_action('woocommerce_review_order_before_shipping');
		wc_cart_totals_shipping_html();
		do_action('woocommerce_review_order_after_shipping');
	endif;
	
	echo '</div>';
}

add_action('woocommerce_checkout_after_customer_details', 'arvand_wc_get_shipping_methods', 10);

/**
 * Update shipping methods via AJAX
 */
add_filter('woocommerce_update_order_review_fragments', function($arr) {
	ob_start();
	arvand_wc_get_shipping_methods();
	$shipping_methods = ob_get_clean();
	$arr['div.arvand-checkout-shipping-methods'] = $shipping_methods;
	
	return $arr;
});

/**
 * Remove default payment from order review
 */
remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);

/**
 * Custom place order button
 */
add_action('woocommerce_checkout_order_review', function() {
	$order_button_text = 'اتصال به درگاه و پرداخت';
	
	do_action('woocommerce_review_order_before_submit');
	
	echo apply_filters(
		'woocommerce_order_button_html',
		sprintf(
			'<button type="submit" class="btn btn-block btn-primary fsz-15 fw-600 lh-30 mt-4" name="woocommerce_checkout_place_order" id="place_order" value="%s" data-value="%s">%s</button>',
			esc_attr($order_button_text),
			esc_attr($order_button_text),
			esc_html($order_button_text)
		)
	);
	
	do_action('woocommerce_review_order_after_submit');
	
	wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce');
}, 999);

/**
 * Move terms after order review
 */
add_action('woocommerce_checkout_after_order_review', function() {
	wc_get_template('checkout/terms.php');
}, 99);

/**
 * Set default country to Iran
 */
add_filter('default_checkout_billing_country', fn() => 'IR', 99);

/**
 * Customize checkout fields
 */
add_filter('woocommerce_checkout_fields', function($fields) {

	// Add national ID field
	$fields['billing']['billing_natid'] = [
		'type'        => 'tel',
		'label'       => 'شماره ملی/کداقتصادی',
		'priority'    => 30,
		'required'    => false,
		'placeholder' => '',
		'class'       => ['form-row-first'],
	];
	
	// Make email optional
	$fields['billing']['billing_email']['required'] = false;
	
	// Remove address line 2
	unset($fields['billing']['billing_address_2']);
	
	// Customize phone field
	$fields['billing']['billing_phone']['label'] = 'شماره همراه';
	$fields['billing']['billing_phone']['placeholder'] = '09…';
	$fields['billing']['billing_phone']['priority'] = 30;
	$fields['billing']['billing_phone']['required'] = true;
	
	// Make postcode optional
	$fields['billing']['billing_postcode']['required'] = false;
	
	// Move address to bottom
	$fields['billing']['billing_address_1']['priority'] = 200;
	$fields['billing']['billing_address_1']['class'][] = 'form-wide';
	
	// Shipping address customizations
	if (isset($fields['shipping']['shipping_address_1'])) {
		$fields['shipping']['shipping_address_1']['priority'] = 200;
		$fields['shipping']['shipping_address_1']['class'][] = 'form-wide';
	}
	
	if (isset($fields['shipping']['shipping_postcode'])) {
		$fields['shipping']['shipping_postcode']['required'] = false;
	}
	
	// Order comments
	$fields['order']['order_comments']['class'][] = 'form-wide';
	$fields['order']['order_comments']['custom_attributes']['rows'] = 8;
	
	return $fields;
});

/**
 * Customize address field labels
 */
add_filter('woocommerce_default_address_fields', function($address) {
	$address['postcode']['label'] = 'کد پستی';
	$address['postcode']['required'] = false;
	return $address;
});

/**
 * Simplify country field when only one country available
 */
add_filter('woocommerce_form_field', function($field, $key, $args, $value) {
	if ($args['type'] !== 'country') {
		return $field;
	}
	
	$countries = ('shipping_country' === $key) 
		? WC()->countries->get_shipping_countries() 
		: WC()->countries->get_allowed_countries();
	
	if (count($countries) === 1) {
		$custom_attributes = [];
		$args['custom_attributes'] = array_filter((array) $args['custom_attributes'], 'strlen');
		
		if ($args['maxlength']) {
			$args['custom_attributes']['maxlength'] = absint($args['maxlength']);
		}
		if ($args['minlength']) {
			$args['custom_attributes']['minlength'] = absint($args['minlength']);
		}
		if (!empty($args['autocomplete'])) {
			$args['custom_attributes']['autocomplete'] = $args['autocomplete'];
		}
		if (true === $args['autofocus']) {
			$args['custom_attributes']['autofocus'] = 'autofocus';
		}
		if ($args['description']) {
			$args['custom_attributes']['aria-describedby'] = $args['id'] . '-description';
		}
		
		if (!empty($args['custom_attributes']) && is_array($args['custom_attributes'])) {
			foreach ($args['custom_attributes'] as $attribute => $attribute_value) {
				$custom_attributes[] = esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
			}
		}
		
		$field = sprintf(
			'<input type="hidden" name="%s" id="%s" value="%s" %s class="country_to_state" readonly="readonly" />',
			esc_attr($key),
			esc_attr($args['id']),
			current(array_keys($countries)),
			implode(' ', $custom_attributes)
		);
	}
	
	return $field;
}, 999, 4);

/**
 * Update shipping methods in AJAX
 */
add_filter('woocommerce_update_order_review_fragments', function($arr) {
	ob_start();
	echo '<div class="checkout-shipping-methods">';
	
	if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) :
		do_action('woocommerce_review_order_before_shipping');
		$packages = WC()->shipping()->get_packages();
		wc_get_template('cart/cart-shipping.php', ['packages' => $packages]);
		do_action('woocommerce_review_order_after_shipping');
	endif;
	
	echo '</div>';
	$shipping_methods = ob_get_clean();
	$arr['.checkout-shipping-methods'] = $shipping_methods;
	
	return $arr;
}, 15);


/**
 * Remove order details table from thank you page
 */
remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);

/**
 * Customize address format for Iran
 */
add_filter('woocommerce_localisation_address_formats', function($address) {
	$address['IR'] = "{state}, {city}, {address_1}, {postcode}";
	return $address;
});

/**
 * Account page customizations
 */
$woocommerce_new_menu_items = ['wishlist', 'sessions'];

/**
 * Customize account menu items
 */
add_filter('woocommerce_account_menu_items', function($items) {
	return [
		'dashboard'       => 'پیشخوان',
		'wishlist'        => 'علاقمندی‌ها',
		'orders'          => 'سفارش‌ها',
		'edit-address'    => 'نشانی‌ها',
		'edit-account'    => 'اطلاعات شما',
		'sessions'        => 'نشست‌ها',
		'customer-logout' => 'خارج شدن',
	];
}, 999);

/**
 * Register custom account endpoints
 */
add_filter('woocommerce_get_query_vars', function($vars) use ($woocommerce_new_menu_items) {
	foreach ($woocommerce_new_menu_items as $e) {
		$vars[$e] = $e;
	}
	return $vars;
});

add_action('init', function() use ($woocommerce_new_menu_items) {
	foreach ($woocommerce_new_menu_items as $ep) {
		add_rewrite_endpoint($ep, EP_PAGES);
	}
});

/**
 * Wishlist endpoint content
 */
add_action('woocommerce_account_wishlist_endpoint', function() {
	global $current_user;
	$wishlist = get_user_wishlist($current_user->ID);
	wc_get_template('myaccount/wishlist.php', ['list' => $wishlist]);
});

/**
 * Sessions endpoint content
 */
add_action('woocommerce_account_sessions_endpoint', function() {
	global $current_user;
	$sessions = WP_Session_Tokens::get_instance($current_user->ID);
	$current_token = wp_get_session_token();
	
	// Handle session destruction
	if (isset($_GET['destroy']) && wp_verify_nonce($_GET['destroy'], 'pj_sessions_destroy')) {
		$sessions->destroy_others($current_token);
		echo '<div class="woocommerce-notices-wrapper">';
		wc_print_notice('از دیگر دستگاه‌ها خارج شدید', 'success');
		echo '</div>';
	}
	
	wc_get_template('myaccount/sessions.php', [
		'sessions'      => $sessions,
		'current_token' => $current_token,
	]);
});

/**
 * Remove unnecessary required fields from account details
 */
add_filter('woocommerce_save_account_details_required_fields', function($items) {
	unset($items['account_display_name'], $items['account_email']);
	return $items;
}, 10);

/**
 * Save additional account details
 */
add_action('woocommerce_save_account_details', function($user_id) {
	$meta_input = [];
	$user_update = ['ID' => $user_id];
	
	// Update display name from first and last name
	if (isset($_POST['account_first_name'])) {
		$fullname = sanitize_text_field($_POST['account_first_name']);
		if (isset($_POST['account_last_name'])) {
			$fullname .= ' ' . sanitize_text_field($_POST['account_last_name']);
		}
		$user_update['display_name'] = trim($fullname);
	}
	
	// National ID
	if (isset($_POST['account_natid'])) {
		$meta_input['account_natid'] = sanitize_text_field($_POST['account_natid']);
	}
	
	// Father's name
	if (isset($_POST['account_father'])) {
		$meta_input['account_father'] = sanitize_text_field($_POST['account_father']);
	}
	
	// Birthday
	if (isset($_POST['account_birthday'])) {
		$meta = wp_parse_args($_POST['account_birthday'], ['y' => 0, 'm' => 0, 'd' => 0]);
		$meta = array_map(function($val) {
			$val = absint(sanitize_text_field($val));
			return ($val === 0) ? false : $val;
		}, $meta);
		$meta = array_filter($meta);
		
		if (!empty($meta) && count($meta) === 3) {
			$date_string = "{$meta['y']}/{$meta['m']}/{$meta['d']}";
			
			if (function_exists('gregdate')) {
				$date_string = gregdate('Y-m-d', $date_string, 'eng');
				$meta_input['account_birthday'] = strtotime($date_string);
			} else {
				$meta_input['account_birthday'] = $date_string;
			}
		}
	}
	
	// Bank details
	if (isset($_POST['account_bank'])) {
		$meta = wp_parse_args((array) $_POST['account_bank'], [
			'name'  => '',
			'num'   => '',
			'card'  => '',
			'sheba' => '',
		]);
		$meta = array_map('sanitize_text_field', $meta);
		$meta_input['account_bank'] = $meta;
	}
	
	// Email
	if (isset($_POST['account_email'])) {
		$email = sanitize_email($_POST['account_email']);
		if (is_email($email)) {
			$user_update['user_email'] = $email;
		}
	}
	
	$user_update['meta_input'] = $meta_input;
	wp_update_user($user_update);
}, 12, 1);

/**
 * Customize my orders actions
 */
add_filter('woocommerce_my_account_my_orders_actions', function($actions, $order) {
	// Remove view action
	if (isset($actions['view'])) {
		unset($actions['view']);
	}
	
	return $actions;
}, 10, 2);

// =============================================================================
// Ajax Codes
// =============================================================================

/**
 * Update mini cart quantity via AJAX
 * Security: Check nonce and sanitize inputs
 */
add_action('wp_ajax_update_minicart_quantity', 'update_minicart_quantity');
add_action('wp_ajax_nopriv_update_minicart_quantity', 'update_minicart_quantity');

function update_minicart_quantity() {
	// Security check
	check_ajax_referer('update-minicart-qty', 'security');
	
	$cart_key = sanitize_text_field($_POST['cart_key']);
	$quantity = absint($_POST['quantity']);
	
	if (!$cart_key) {
		wp_send_json_error(['message' => 'کلید سبد نامعتبر است']);
	}
	
	if ($quantity === 0) {
		WC()->cart->remove_cart_item($cart_key);
	} else {
		WC()->cart->set_quantity($cart_key, $quantity, true);
	}
	
	WC()->cart->calculate_totals();
	
	wp_send_json_success([
		'cart_hash'  => WC()->cart->get_cart_hash(),
		'cart_count' => WC()->cart->get_cart_contents_count(),
		'subtotal'   => WC()->cart->get_cart_subtotal(),
	]);
}

// =============================================================================
// Require Other Files
// =============================================================================