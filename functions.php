<?php
/**
 * Core Functions
 *
 * @author 	Pouriya Amjadzadeh
 * @version 3.5.0
 * @package https://arvandec.com
 */

defined('ABSPATH') || exit;

// Autoload Composer
// require_once __DIR__ . '/vendor/autoload.php';

define( 'THEMEDIR', trailingslashit( get_template_directory() ) );
define( 'THEMEURL', trailingslashit( get_template_directory_uri() ) );

add_action( 'after_switch_theme', function(){
	update_option('thumbnail_size_w', 0);
	update_option('thumbnail_size_h', 0);
	update_option('thumbnail_crop', true);
	update_option('medium_size_w', 0);
	update_option('medium_size_h', 0);
	update_option('medium_crop', true);

	update_option('medium_large_size_w', 0);
	update_option('medium_large_size_h', 0);

	update_option('large_size_w', 0);
	update_option('large_size_h', 0);
	update_option('large_crop', true);

});

add_action( 'after_setup_theme', function(){
	global $content_width;
	$content_width = 1440;

	//Theme Supports
	add_theme_support('title-tag');
	add_theme_support( 'align-wide' );
	add_theme_support( 'custom-units' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ] );

	add_action( 'wp_before_admin_bar_render', function(){
		global $wp_admin_bar;
		$wp_admin_bar->remove_menu('wp-logo');
	}, 0 );

	//wp_head Cleanup
	remove_action('wp_head', 'wp_generator');
	remove_action('wp_head', 'wlwmanifest_link');
	remove_action('wp_head', 'rsd_link');
	remove_action( 'wp_head', 'feed_links', 2 );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action('template_redirect', 'rest_output_link_header', 11);

	remove_action('wp_head', 'wp_oembed_add_discovery_links');
	remove_action('wp_head', 'wp_oembed_add_host_js');

	// remove_action('wp_head', 'rel_canonical');
	remove_action( 'wp_head', 'index_rel_link' );
	remove_action( 'wp_head', 'start_post_rel_link', 10, 0 );
	remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 );
	remove_action( 'wp_head', 'adjacent_posts_rel_link', 10, 0 );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );

	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
	remove_filter('the_content_feed', 'wp_staticize_emoji');
	remove_filter('comment_text_rss', 'wp_staticize_emoji');
	add_filter('emoji_svg_url', '__return_false');
	add_filter('wp_img_tag_add_auto_sizes', '__return_false');

	//Disable Block Theme
	add_theme_support( 'disable-custom-gradients' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );

	add_action( 'wp_enqueue_scripts', function() {

		if( ! is_admin() ) {
			wp_deregister_script( 'jquery' );
			wp_enqueue_script( 'jquery', THEMEURL.'assets/libs/jquery.min.js', [], '3.7.1', [
				'in_footer'	=> false,
			]);

			wp_dequeue_style('wp-block-library');
			wp_dequeue_style('wp-block-library-theme');
			wp_dequeue_style('global-styles');
		}

		wp_register_style( 'splide', THEMEURL.'assets/libs/splide/splide-core.min.css', [], '4.1.3');
		wp_register_script( 'splide', THEMEURL.'assets/libs/splide/splide.min.js', [], '4.1.3', [
			'strategy'	=> 'defer',
			'in_footer'	=> true,
		]);

		if ( comments_open() ) wp_enqueue_script('comment-reply');

		wp_enqueue_style( 'stylesheet', THEMEURL.'assets/stylesheet.css' );
	}, 99 );

	add_action( 'wp_head', function(){
		//TODO:Preload Font
		// printf(
		// 	'<link rel="prefetch" as="font" href="%s" type="%s" crossorigin="anonymous">',
		// 	THEMEURL .'assets/media/FONTNAME.woff2',
		// 	'font/woff2',
		// );
		// echo PHP_EOL;
		// printf('<link rel="manifest" href="%s">', THEMEURL.'manifest.webmanifest');
	}, 2);

	add_filter( 'body_class', function( $classes ) {
		$classes = [];
		if( is_404() ) $classes[] = 'error404';
		$classes[] = ( is_rtl() ) ? 'rtl' : 'ltr';

		if( is_admin_bar_showing() ) $classes[] = 'admin_bar';
		return $classes;
	}, 99 );

	add_filter('header_class', function( $classes ) {
		$classes = [];
		return $classes;
	});

	add_action( 'wp_body_open', function(){
		get_template_part('parts/icons');
		echo PHP_EOL;
	}, 99);

	add_action( 'wp_footer', function(){
		printf('<script id="themejs" src="%s" data-ajax="%s" defer></script>', THEMEURL. 'assets/script.min.js', admin_url('admin-ajax.php') );
	}, 99);

	register_nav_menus([
		'primary'		=> __('Menu'),
	]);

	//Archive and Loop
	add_filter( 'max_srcset_image_width', '__return_false' );
	add_filter( 'wp_calculate_image_srcset', '__return_false' );
	add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

	//Widgets
	add_action('widgets_init', function() {
		register_sidebar([
			'id'			=> 'sidebar',
			'name'			=> __('Sidebar'),
			'before_widget'	=> '<section id="%1$s" class="widget %2$s">',
			'after_widget'	=> '</section>',
			'before_title'	=> '<div role="heading" aria-level="4" class="text-lg mb-4 widget-title">',
			'after_title'	=> '</div>',
		]);
	});
	
	//Classic Widgets
	add_filter( 'use_widgets_block_editor', '__return_false' );
	add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );

	//Add Custom class and widget-content wrapper to widgets
	add_filter('widget_form_callback', function( $instance, $widget ) {
		printf('<p><label for="%1$s">CSS Classes</label><input id="%1$s" type="text" name="%2$s" dir="ltr" value="%3$s" class="widefat"></p>', $widget->get_field_id('classes'), $widget->get_field_name('classes'), ( isset($instance['classes']) ? $instance['classes'] : null ));
		return $instance;
	}, 99, 2);

	add_filter( 'widget_update_callback', function( $instance, $new_instance ) {
		$instance['classes'] = $new_instance['classes'];
		return $instance;
	}, 10, 2);

	add_filter('dynamic_sidebar_params', function($params) {
		if( is_admin() ) return $params;
		
		global $wp_registered_widgets;
		$widget_id = $params[0]['widget_id'];
		$widget = $wp_registered_widgets[$widget_id] ?? null;
		
		if ($widget && is_object($widget['callback'][0] ?? null)) {
			$opt = get_option($widget['callback'][0]->option_name);
			$num = $widget['params'][0]['number'] ?? 0;
			if (!empty($opt[$num]['classes'])) {
				$params[0]['before_widget'] = preg_replace('/class="/', 'class="' . $opt[$num]['classes'] . ' ', $params[0]['before_widget'], 1);
			}
		}
		
		$callback = $widget['callback'];
		$orig_params = $params;
		
		$wp_registered_widgets[$widget_id]['callback'] = function() use ($callback, $orig_params) {
			ob_start();
			call_user_func_array($callback, $orig_params);
			$out = ob_get_clean();
			if (!trim($out)) return;
			$a = $orig_params[0];
			$has_title = str_contains($out, $a['before_title']) && str_contains($out, $a['after_title']);
			$out = preg_replace($has_title ? '/(' . preg_quote($a['after_title'], '/') . ')/' : '/(<div[^>]*class="[^"]*widget[^"]*"[^>]*>)/','$1<div class="widget-content">', $out, 1);
			echo preg_replace('/(.*)(' . preg_quote($a['after_widget'], '/') . ')/s', '$1</div>$2', $out);
		};
		
		return $params;
	}, 999);

	//Newest Users Registered First
	add_action( 'pre_get_users', function($query){
		$query->query_vars['orderby'] = 'user_registered';
		$query->query_vars['order'] = 'DESC';
	});
	add_filter( 'manage_users_columns', function($columns){
		$columns['registration_date'] = 'تاریخ عضویت';
		return $columns;
	});
	add_filter( 'manage_users_custom_column', function($row_output, $column_id_attr, $user) {
		if( $column_id_attr == 'registration_date' ){
			return sprintf('<span dir="ltr">%s</span>', date_i18n( 'Y-m-d H:i', strtotime( get_the_author_meta( 'registered', $user ) ) ) );
		}
		return $row_output;
	}, 10, 3);
	add_filter( 'pre_get_avatar', function( $avatar, $id_or_email, $args ) {
		$user = false;
		if( $id_or_email ){
			if( is_numeric( $id_or_email ) ){
				$user = get_user_by('id' , $id_or_email);
			} elseif( is_object($id_or_email) ) {
				if( isset( $id_or_email->user_id ) ) {
					$user = get_user_by( 'id' , $id_or_email->user_id );
				} else {
					$user = false;
				}
			} elseif( is_email($id_or_email) ) {
				$user = get_user_by('email', $id_or_email );   
			}
		}

		$avatar_src = '<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" viewBox="0 0 24 24"><path fill="#d0d4db" d="M0 0h24v24H0z"/><path fill="rgba(0,0,0,0.25)" d="M12 5.5c-1.7 0-3 1.3-3 3s1.3 3.5 3 3.5 3-2 3-3.6-1.3-2.9-3-2.9m4.4 13.2c1-.7 1.3-2 .6-3a6 6 0 0 0-5-2.7 6 6 0 0 0-5 2.6c-.7 1-.4 2.4.6 3A8 8 0 0 0 12 20a8 8 0 0 0 4.4-1.3"/></svg>';
		$avatar_src = 'data:image/svg+xml;base64,'.base64_encode( $avatar_src );
		
		//TODO:: if want localavatar
		// if( $user && get_user_meta( $user->ID, 'localavatar', true ) ){
		// 	$custom_avatar_id = absint( get_user_meta( $user->ID, 'localavatar', true ) );
		// 	if( $custom_avatar_id ) {
		// 		$custom_avatar_src = wp_get_attachment_image_url( $custom_avatar_id, 'full' );
		// 		if( $custom_avatar_src ) {
		// 			$avatar_src = $custom_avatar_src;
		// 		}
		// 	}
		// }

		$attrs = [
			'src'           => $avatar_src,
			'alt'           => $args['alt'] ?? '',
			'width'         => $args['width'] ?? '',
			'height'        => $args['height'] ?? '',
			'class'         => is_array($args['class'] ?? '') ? $args['class'] : [$args['class'] ?? ''],
			'loading'       => $args['loading'] ?? 'lazy',
			'fetchpriority' => $args['fetchpriority'] ?? '',
			'decoding'      => $args['decoding'] ?? '',
		];

		$attrs['class'] = array_merge( ["avatar", "avatar-{$args['size']}"], $attrs['class']);
		$attrs['class'] = implode(' ', array_filter($attrs['class']));

		if( isset($args['extra_attr']) && is_array($args['extra_attr']) ) {
			$extra_attrs = array_filter($args['extra_attr'], function($value) {
				return $value !== null;
			});
			$attrs = array_merge($attrs, $extra_attrs);
		}

		$html_attrs = implode(' ', array_filter(array_map(
			function($k, $v) {
				if( $v === null || $v === '' || $v === false ) {
					return '';
				}
				if( $v === 0 || $v === '0' ) {
					return $k . '="' . esc_attr($v) . '"';
				}
				return $k . '="' . esc_attr($v) . '"';
			},
			array_keys($attrs),
			$attrs
		)));

		return "<img {$html_attrs}>";
	} , 10, 3);

	//includes
	foreach (glob(THEMEDIR.'src/*.php') as $file) require_once $file;

	//Arvand Panel Style
	add_action( 'admin_enqueue_scripts',function(){
		wp_enqueue_style( 'arvandpanel', THEMEURL . 'assets/arvand-admin.css', false, '1.0.0' );
	});
	add_action( 'login_enqueue_scripts', function(){
		wp_enqueue_style( 'arvandpanel', THEMEURL . 'assets/arvand-admin.css', false, '1.0.0' );
	});
	add_filter( 'login_headerurl', function() {
		return home_url();
	});
	add_filter('admin_footer_text', function() {
		return 'توسعه داده شده توسط <a href="https://arvandec.com" target="_blank">آژانس دیجیتال مارکتینگ آروند</a> بر بستر <a href="https://wordpress.org" target="_blank">وردپرس</a>.';
	});

	//Allow SVG tags and attributes in wp_kses_post and Upload File
	add_filter('wp_kses_allowed_html', function( $allowed_tags ) {
		$svg_args = array(
			'svg'      => array(
				'class'           => true,
				'aria-hidden'     => true,
				'aria-labelledby' => true,
				'role'            => true,
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true, // Must be lowercase!
				'version'         => true,
				'xml:space'       => true,
			),
			'g'        => array(
				'fill' => true,
				'stroke' => true,
			),
			'path'     => array(
				'd'    => true,
				'fill' => true,
				'stroke' => true,
				'stroke-width' => true,
			),
			'circle'   => array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
				'fill' => true,
			),
			'rect'     => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'fill'   => true,
			),
			'line'     => array(
				'x1' => true,
				'y1' => true,
				'x2' => true,
				'y2' => true,
				'stroke' => true,
			),
			'polyline' => array(
				'points' => true,
				'fill'   => true,
			),
			'polygon'  => array(
				'points' => true,
				'fill'   => true,
			),
			'title'    => array(
				'title' => true,
			),
			'defs'     => array(),
			'linearGradient' => array(
				'id' => true,
				'x1' => true,
				'y1' => true,
				'x2' => true,
				'y2' => true,
			),
			'stop'     => array(
				'offset' => true,
				'stop-color' => true,
			),
		);
		return array_merge( $allowed_tags, $svg_args );
	}, 10, 2 );
	add_filter('upload_mimes', function( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	});
	add_filter( 'wp_prepare_attachment_for_js', function( $response, $attachment, $meta ) {
		if ( $response['mime'] === 'image/svg+xml' ) {
			$response['url'] = $response['url'];
			$response['sizes'] = [];
		}
		return $response;
	}, 10, 3 );


	//Email Customizition
	add_filter('wp_mail_from', fn($email) => str_replace('wordpress@', 'noreply@', $email), 999);
	add_filter('wp_mail_from_name', fn($name) => get_bloginfo('name'), 999);
	add_filter('wp_mail', function($attrs) {
		$message = $attrs['message'] ?? '';
		if (!preg_match('/^\s*<(html|!doctype\s+html)/i', $message) && !empty($message)) {
			$has_html = preg_match('/<[^>]+>/', $message);
			$content = $has_html ? wp_kses_post( $message ) : nl2br( esc_html( $message ) );
			ob_start();
			get_template_part('parts/wpmail', null, [
				'subject' => $attrs['subject'] ?? '',
				'message' => $content
			]);
			$attrs['message'] = ob_get_clean();
			$html_header = 'Content-Type: text/html; charset=UTF-8';
			if (isset($attrs['headers'])) {
				if (is_array($attrs['headers'])) {
					if (!in_array($html_header, $attrs['headers'])) {
						$attrs['headers'][] = $html_header;
					}
				} elseif (strpos($attrs['headers'], 'Content-Type:') === false) {
					$attrs['headers'] .= "\r\n" . $html_header;
				}
			} else {
				$attrs['headers'] = $html_header;
			}
		}
		return $attrs;
	}, 999);

	add_action('wp_ajax_arvand_contact_form', 'arvand_contact_form_cb');
	add_action('wp_ajax_nopriv_arvand_contact_form', 'arvand_contact_form_cb');
	function arvand_contact_form_cb() {
		check_ajax_referer( 'arvand-contactform' );
		$form_data	= isset( $_POST['form_data'] ) ? json_decode( stripslashes( $_POST['form_data'] ), true) : [];
		$output		= [ 'sent' => false, 'message' => null ];
		try {
			$name		= isset( $form_data['arvcfName'] ) ? sanitize_text_field( $form_data['arvcfName'] ) : '';
			$phone		= isset( $form_data['arvcfPhone'] ) ? sanitize_text_field( $form_data['arvcfPhone'] ) : '';
			$subject	= isset( $form_data['arvcfSubject'] ) ? sanitize_text_field( $form_data['arvcfSubject'] ) : '';
			$email		= isset( $form_data['arvcfEmail'] ) ? sanitize_email( $form_data['arvcfEmail'] ) : '';
			$message	= isset( $form_data['arvcfMessage'] ) ? sanitize_textarea_field( $form_data['arvcfMessage'] ) : '';

			if( empty( $name ) || empty( $phone )|| empty( $subject ) || empty( $message ) ){
				throw new Exception( __('Required fields must be completed.', 'tabeebo') );
			}

			if( ! empty( $email ) && ! is_email( $email ) ) {
				throw new Exception( __('Invalid email address', 'tabeebo' ) );
			}

			$sent = wp_mail( $atts['to'], $subject, $message );
			if( $sent ){
				$output['sent'] = true;
				$output['message'] = __('Your message was sent successfully', 'tabeebo' );
			} else {
				throw new Exception( ــ('Something went wrong. Your message could not be sent', 'tabeebo') );
			}
		} catch (Exception $e) {
			$output['message'] = $e->getMessage();
		}
		wp_send_json( $output );
	}

	//Plugins Compatibility
	add_theme_support( 'rank-math-breadcrumbs' );


	//Shortcodes
	add_shortcode( 'arvand_contact_form', function( $atts ) {
		ob_start();
		get_template_part( 'parts/contactus', 'form' );
		return ob_get_clean();
	});

	add_shortcode('the_logo', function($atts){
		$atts = shortcode_atts([
			'white'			=> false,
			'size'			=> null,
			'return'		=> 1,
		], $atts);
		
		return the_logo($atts['size'], $atts['white'], $atts);
	});
	
});

//------Theme Functions

function theme_assets_url( $path = null ){
	return THEMEURL.'assets/'.ltrim( $path, '/');
}

function header_class(){
	$classes = array_unique( apply_filters( 'header_class', [] ) );
	if( !empty( $classes ) ){
		printf(' class="%s"', esc_attr( implode(' ', $classes) ) );
	}
}

function the_logo( $size = 140 ){
	if( has_custom_logo() ){
		the_custom_logo();
	} else {
		$args = wp_parse_args( $args, [
			'link'		=> home_url(),
			'alt'		=> '',
			'class'		=> '',
			'return'	=> false
		]);

		if( is_numeric( $size ) ){
			$width = $size;
			$height = ceil( $size * 0.5 );
		} elseif( is_array( $size ) ){
			$width = absint( $size[0] );
			$height = absint( $size[1] ?? $width );
		}

		if( empty( $args['alt'] ) ){
			$alt['alt'] = [ get_bloginfo('name'), get_bloginfo('description') ];
			$alt['alt'] = implode( ' | ', array_filter($alt['alt']) );
		}

		$logo_class = trim(sprintf('custom-logo %s', $args['class']));
		$logo = sprintf('<img src="%s" title="%s" class="%s" width="%s" height="%s" />', theme_assets_url("media/logo.svg"), esc_attr( $args['alt'] ), esc_attr( $logo_class ), esc_attr( $width ), esc_attr( $height ) );

		if( false !== $args['link'] ){
			$link_to = trailingslashit( esc_url( $args['link'] ) );
			$link_now = trailingslashit("https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
			$aria_current = $link_now == $link_to ? ' aria-current="page"' : '';
			$logo = sprintf('<a href="%1$s" class="custom-logo-link" rel="home"%2$s>%3$s</a>', $link_to, $aria_current, $logo);
		}

		if( $args['return'] ){
			return $logo;
		} else {
			echo $logo;
		}
	}
}