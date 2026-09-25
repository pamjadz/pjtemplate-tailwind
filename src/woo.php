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

add_action('init', function() {
	remove_action('wp_head', 'wc_gallery_noscript');
});

add_filter('body_class', function($classes) {
	remove_action('wp_footer', 'wc_no_js');
	return $classes;
});

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
 * Custom price display for variable products
 * Shows cheapest variation or on-sale variation
 */
add_filter('woocommerce_get_price_html', function($price, $product) {
	if (is_admin() || '' === $product->get_price()) {
		return $price;
	}

}, 999, 2);

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
 * Cart fragments for AJAX updates
 */
add_filter('woocommerce_add_to_cart_fragments', function($fragments) {
	// Cart count
	$fragments['span.wc-cart-count'] = sprintf('<span class="wc-cart-count">%s</span>', WC()->cart->get_cart_contents_count() );
	
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
 * Remove empty cart message default
 */
remove_action('woocommerce_cart_is_empty', 'wc_empty_cart_message', 10);

/**
 * Disable add to cart notices (using custom notifications)
 */
add_filter('wc_add_to_cart_message_html', '__return_empty_string');
add_filter('woocommerce_cart_redirect_after_error', '__return_false');


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
 * Set default country to Iran
 */
add_filter('default_checkout_billing_country', fn() => 'IR', 99);


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