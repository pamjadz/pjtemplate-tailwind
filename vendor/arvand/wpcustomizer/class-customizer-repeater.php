<?php
/**
 * Customizer Repeater Control — بهینه‌شده
 *
 * استفاده:
 *   $wp_customize->add_control(
 *       new Customizer_Repeater( $wp_customize, 'my_repeater', array(
 *           'label'           => __( 'آیتم‌ها', 'textdomain' ),
 *           'add_field_label' => __( 'افزودن آیتم', 'textdomain' ),
 *           'item_name'       => __( 'آیتم', 'textdomain' ),
 *           'fields'          => array(
 *               'title'     => array( 'type' => 'text',     'label' => __( 'عنوان', 'textdomain' ) ),
 *               'image_url' => array( 'type' => 'image',    'label' => __( 'تصویر', 'textdomain' ) ),
 *               'link'      => array( 'type' => 'url',      'label' => __( 'لینک',  'textdomain' ) ),
 *               'text'      => array( 'type' => 'textarea', 'label' => __( 'متن',   'textdomain' ) ),
 *               'color'     => array( 'type' => 'color',    'label' => __( 'رنگ',   'textdomain' ) ),
 *           ),
 *           'section'  => 'my_section',
 *           'settings' => 'my_repeater_setting',
 *       ) )
 *   );
 *
 * انواع فیلد پشتیبانی‌شده:
 *   text | textarea | url | image | color
 *
 * @package customizer-controls
 */

if ( ! class_exists( 'WP_Customize_Control' ) ) {
	return;
}

class Customizer_Repeater extends WP_Customize_Control {

	/** @var string نوع کنترل */
	public $type = 'customizer_repeater';

	/** @var string برچسب دکمهٔ افزودن آیتم جدید */
	public $add_field_label = '';

	/** @var string عنوان هر باکس آیتم */
	public $item_name = '';

	/**
	 * تعریف فیلدهای هر آیتم به‌صورت آرایهٔ انجمنی.
	 *
	 * هر کلید = شناسهٔ فیلد در JSON ذخیره‌شده
	 * هر مقدار = آرایه‌ای با کلیدهای:
	 *   'type'  (text|textarea|url|image|color)
	 *   'label' (رشتهٔ نمایشی)
	 *
	 * @var array<string, array{type: string, label: string}>
	 */
	public $fields = array();

	// ─── Allowed HTML برای echo امن ─────────────────────────────────────────
	private array $allowed_html = array();

	// ─── سازنده ─────────────────────────────────────────────────────────────

	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args );

		$this->add_field_label = ! empty( $args['add_field_label'] ) ? $args['add_field_label'] : esc_html__('Add');

		$this->item_name = ! empty( $args['item_name'] )
			? $args['item_name']
			: ( ! empty( $this->label ) ? $this->label : esc_html__( 'آیتم', 'your-textdomain' ) );

		if ( ! empty( $args['fields'] ) && is_array( $args['fields'] ) ) {
			$this->fields = $args['fields'];
		}

		$this->allowed_html = array_merge(
			wp_kses_allowed_html( 'post' ),
			array(
				'input' => array(
					'type'        => array(),
					'class'       => array(),
					'value'       => array(),
					'placeholder' => array(),
				),
			)
		);
	}

	// ─── Enqueue ────────────────────────────────────────────────────────────

	public function enqueue(): void {
		$ver      = '1.0.0';
		$base_uri = get_template_directory_uri() . '/assets/libs/customizer/';
		wp_enqueue_style('arvandcustomizer', $base_uri . 'style.css', array(), $ver );
		wp_enqueue_script('arvandcustomizer', $base_uri . 'repeater.js', array( 'jquery', 'jquery-ui-draggable', 'wp-color-picker' ), $ver, true );
		wp_enqueue_style('wp-color-picker');
	}

	public function render_content(): void {
		$raw      = $this->value();
		$decoded  = json_decode($raw, true);
		$items    = ( is_array( $decoded ) && ! empty( $decoded ) ) ? $decoded : array( array() );

		if ( empty( array_filter( $items ) ) ) {
			$default = json_decode( $this->setting->default, true );
			if ( is_array( $default ) ) {
				$items = $default;
				$raw   = wp_json_encode( $default );
			} else {
				$items = array( array() );
				$raw   = '';
			}
		}
		?>
		<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>

		<div class="customizer-repeater-general-control-repeater customizer-repeater-general-control-droppable">
			<?php
			foreach ( $items as $index => $item ) {
				$this->render_item( $item, $index );
			}
			?>
			<input type="hidden"
				id="customizer-repeater-<?php echo esc_attr( $this->id ); ?>-colector"
				class="customizer-repeater-colector"
				value="<?php echo esc_textarea( $raw ); ?>"
				<?php $this->link(); ?>
			/>
		</div>

		<button type="button" class="button add_field customizer-repeater-new-field">
			<?php echo esc_html( $this->add_field_label ); ?>
		</button>
		<?php
	}

	// ─── رندر یک آیتم ──────────────────────────────────────────────────────

	/**
	 * @param array $item   داده‌های آیتم (ممکن است خالی باشد)
	 * @param int   $index  شمارهٔ آیتم (صفر = اولین، دکمهٔ حذف مخفی است)
	 */
	private function render_item( array $item, int $index ): void {
		?>
		<div class="customizer-repeater-general-control-repeater-container customizer-repeater-draggable">
			<div class="customizer-repeater-customize-control-title">
				<?php echo esc_html( $this->item_name ); ?>
			</div>
			<div class="customizer-repeater-box-content-hidden">

				<?php foreach ( $this->fields as $key => $field ) : ?>
					<?php $this->render_field( $key, $field, $item[ $key ] ?? '' ); ?>
				<?php endforeach; ?>

				<input type="hidden" class="social-repeater-box-id"
					value="<?php echo esc_attr( $item['id'] ?? '' ); ?>">

				<button type="button" class="social-repeater-general-control-remove-field button"
					<?php echo ( $index === 0 ) ? 'style="display:none;"' : ''; ?>>
					<?php esc_html_e( 'حذف', 'your-textdomain' ); ?>
				</button>

			</div>
		</div>
		<?php
	}

	// ─── رندر یک فیلد بر اساس نوع ──────────────────────────────────────────

	/**
	 * @param string $key   شناسهٔ فیلد
	 * @param array  $field تنظیمات فیلد (type, label)
	 * @param mixed  $value مقدار ذخیره‌شده
	 */
	private function render_field( string $key, array $field, $value ): void {
		$type  = $field['type']  ?? 'text';
		$label = $field['label'] ?? $key;
		$class = 'customizer-repeater-field customizer-repeater-' . esc_attr( $key );

		echo '<span class="customize-control-title">' . esc_html( $label ) . '</span>';

		switch ( $type ) {
			case 'textarea':
				printf(
					'<textarea class="%s" placeholder="%s">%s</textarea>',
					esc_attr( $class ),
					esc_attr( $label ),
					esc_textarea( $value )
				);
				break;
			case 'image':
				printf(
					'<input type="text" class="%s widefat custom-media-url" value="%s">
					<input type="button" class="button button-secondary customizer-repeater-custom-media-button" value="%s">',
					esc_attr( $class ),
					esc_attr( $value ),
					esc_attr__( 'آپلود تصویر', 'your-textdomain' )
				);
				break;
			case 'color':
				printf(
					'<div class="%s"><input type="text" class="%s" data-type="color" value="%s"></div>',
					esc_attr( $class ),
					esc_attr( $class ),
					esc_attr( sanitize_hex_color( $value ) )
				);
				break;

			case 'url':
				printf(
					'<input type="text" class="%s" value="%s" placeholder="%s">',
					esc_attr( $class ),
					esc_url( $value ),
					esc_attr( $label )
				);
				break;
			default:
				printf(
					'<input type="text" class="%s" value="%s" placeholder="%s">',
					esc_attr( $class ),
					esc_attr( $value ),
					esc_attr( $label )
				);
		}
	}
}
