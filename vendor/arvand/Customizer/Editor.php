<?php
if ( ! class_exists( 'WP_Customize_Control' ) ) {
	return;
}

class Customizer_Editor extends WP_Customize_Control {
	public $type = 'customizer_editor';
	
	public $editor_settings = [];

	public function __construct( $manager, $id, $args = [] ) {
		parent::__construct( $manager, $id, $args );

		$default_settings = array(
			'textarea_name' => $this->id,
			'textarea_rows' => 10,
			'editor_class'  => 'customizer-editor',
			'media_buttons' => false,
			'quicktags'     => true,
			'tinymce'       => array(
				'toolbar1' => 'bold,italic,underline,strikethrough,bullist,numlist,blockquote,link,unlink,undo,redo',
			),
		);
		
		$this->editor_settings = wp_parse_args( $args['editor_settings'] ?? [], $default_settings );
	}

	/**
	 * افزودن اسکریپت‌ها و استایل‌های مورد نیاز
	 */
	public function enqueue(): void {
		wp_enqueue_editor();
		
		// wp_enqueue_script(
		// 	'customizer-editor-control',
		// 	get_template_directory_uri() . '/assets/js/customizer-editor.js', // مسیر را بر اساس قالب خود تنظیم کنید
		// 	array( 'jquery', 'customize-controls', 'editor' ),
		// 	'1.0.0',
		// 	true
		// );
		
		// // افزودن استایل اختصاصی
		// wp_enqueue_style(
		// 	'customizer-editor-control',
		// 	get_template_directory_uri() . '/assets/css/customizer-editor.css', // مسیر را بر اساس قالب خود تنظیم کنید
		// 	[],
		// 	'1.0.0'
		// );
	}


	public function render_content(): void {
		$value = $this->value();
		$settings = $this->editor_settings;
		$settings['textarea_name'] = $this->id;
		?>
		<label>
			<?php if ( ! empty( $this->label ) ) : ?>
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php endif; ?>
			
			<?php if ( ! empty( $this->description ) ) : ?>
				<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
			<?php endif; ?>
			
			<div class="customizer-editor-container">
				<?php wp_editor( $value, $this->id, $settings ); ?>
			</div>
		</label>
		<?php
	}

	public function to_json() {
		parent::to_json();
		
		$this->json['editor_settings'] = $this->editor_settings;
		$this->json['value'] = $this->value();
	}
}