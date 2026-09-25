<?php
/**
 * Customizer
 */
defined('ABSPATH') || exit;

add_action( 'customize_register', function( $customizer ) {
	/**
	 * @param WP_Customize_Manager $wp_customize
	 * @param string $setting
	 * @param array $args
	 */
	$add_field = function(string $type, array $args = []) use ($wp_customize) {
		$setting_keys = ['default', 'transport', 'sanitize_callback', 'capability', 'theme_supports', 'type'];
		
		$setting_args = array_intersect_key($args, array_flip($setting_keys));
		$control_args = array_diff_key($args, array_flip($setting_keys));
		
		$settings = [
			'default'   => '',
			'transport' => 'refresh',
		];
		
		$control = [
			'type'			=> $type,
			'label'			=> '',
			'section'		=> '',
			'settings'		=> $args['settings'] ?? '',
			'input_attrs'	=> [],
		];
		
		if( isset( $args['multiple'] ) && true == $args['multiple'] ) {
			$control['input_attrs']['multiple'] = 'multiple';
		}
		
		if (empty($control['settings'])) {
			return;
		}
		
		$wp_customize->add_setting($control['settings'],  array_merge($settings, $setting_args));

		$control = array_merge($control, $control_args);
		
		switch ($type) {
			case 'image':
				$wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, $control['settings'], $control));
				break;
			case 'file':
				$wp_customize->add_control(new WP_Customize_Upload_Control($wp_customize, $control['settings'], $control));
				break;				
			case 'color':
				$wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, $control['settings'], $control));
				break;
			case 'repeater':
				if (class_exists('Customizer_Repeater')) {
					$wp_customize->add_control(new Customizer_Repeater($wp_customize, $control['settings'], $control));
				}
				break;
			default:
				$wp_customize->add_control($control['settings'], $control);
				break;
		}
	};

	$terms_options = function(array $args = []): array {
		$list	= [];
		$terms	= get_terms($args);
		if ( ! empty( $terms ) && ! is_wp_error($terms) ) {
			foreach( $terms as $term ){
				$list[ $term->term_id ] = esc_html( $term->name );
			}
		}
		return $list;
	};

	// $add_field('frontpage_slider', [
	// 	'type'			=> 'repeater',
	// 	'label'			=> __( 'اسلایدر', 'textdomain' ),
	// 	'section'		=> 'static_front_page',
	// 	'fields'		=> [
	// 		'title'	=> [
	// 			'type'	=> 'text',
	// 			'label'	=> 'عنوان'
	// 		],
	// 		'image' => [
	// 			'type'	=> 'image',
	// 			'label' => 'تصویر',
	// 		],
	// 		'link'	=> [
	// 			'type'	=> 'url',
	// 			'label'	=> 'لینک'
	// 		],
	// 	],
	// ]);

	// $add_field('text', [
	// 	'section'		=> 'static_front_page',
	// 	'settings'		=> '',
	// 	'label'			=> __( 'اسلایدر', 'textdomain' ),
	// ]);

});