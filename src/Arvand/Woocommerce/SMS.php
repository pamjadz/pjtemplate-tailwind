<?php
/**
 * WooCommerce SMS Notifier
 *
 * Sends SMS on WooCommerce order status changes via configurable gateways.
 * Adds a "SMS Notifications" tab under WooCommerce → Settings.
 *
 * @package Arvand\Woocommerce
 */

namespace Arvand\Woocommerce;

defined( 'ABSPATH' ) || exit;


class SMS {
	const TAB_ID              = 'wc_sms';
	const OPTION_TEMPLATES    = 'wc_sms_templates';
	const OPTION_GATEWAY      = 'wc_sms_active_gateway';
	const OPTION_CONFIG       = 'wc_sms_gateway_config';
	const OPTION_ADMIN_PHONES = 'wc_sms_admin_phones';
	const OPTION_ADMIN_EVENTS = 'wc_sms_admin_events';

	/** Admin-notify event key for newly placed orders (alongside status keys). */
	const EVENT_NEW_ORDER = 'new_order';

	protected static $instance;

	protected $gateways = array();

	/** @var array|null|false Memoized active gateway; false = not resolved yet. */
	protected $active = false;

	protected function __construct() {
		$this->register_defaults();

		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 20, 4 );
		add_action( 'woocommerce_new_order',            array( $this, 'on_new_order' ), 20, 2 );

		add_filter( 'woocommerce_settings_tabs_array',            array( $this, 'add_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_' . self::TAB_ID,  array( $this, 'render_settings' ) );
		add_action( 'woocommerce_update_options_' . self::TAB_ID, array( $this, 'save_settings' ) );
	}

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/* -------- Gateway registry -------- */

	public function register_defaults() {
		$dir = __DIR__ . '/SMS-gateways';

		if ( is_dir( $dir ) ) {
			foreach ( glob( $dir . '/*.php' ) as $file ) {
				require_once $file;
			}
		}

		foreach ( get_declared_classes() as $class ) {
			if ( is_subclass_of( $class, __NAMESPACE__ . '\\AbstractSMSGateway' ) ) {
				$ref = new \ReflectionClass( $class );
				if ( $ref->isInstantiable() ) {
					$this->register( $ref->newInstance() );
				}
			}
		}

		do_action( 'wc_sms_register_gateways', $this );
	}

	public function register( GatewayInterface $gateway ) {
		$this->gateways[ $gateway->get_id() ] = $gateway;
	}

	public function all_gateways() {
		return $this->gateways;
	}

	public function get_gateway( $id ) {
		return isset( $this->gateways[ $id ] ) ? $this->gateways[ $id ] : null;
	}

	/**
	 * Resolve the active gateway with its saved config.
	 *
	 * @return array{gateway:GatewayInterface, id:string, config:array}|null
	 */
	protected function active_gateway() {
		if ( $this->active !== false ) {
			return $this->active;
		}

		$id      = get_option( self::OPTION_GATEWAY, '' );
		$gateway = $this->get_gateway( $id );

		if ( ! $gateway ) {
			return $this->active = null;
		}

		$config = get_option( self::OPTION_CONFIG, array() );

		return $this->active = array(
			'gateway' => $gateway,
			'id'      => $id,
			'config'  => isset( $config[ $id ] ) ? (array) $config[ $id ] : array(),
		);
	}

	/* -------- placeholders -------- */

	public static function placeholders() {
		return array(
			'{order_id}'        => 'شماره سفارش',
			'{order_status}'    => 'وضعیت سفارش',
			'{order_total}'     => 'مبلغ کل سفارش',
			'{first_name}'      => 'نام مشتری',
			'{last_name}'       => 'نام خانوادگی',
			'{full_name}'       => 'نام کامل',
			'{billing_phone}'   => 'شماره تماس',
			'{shipping_method}' => 'روش ارسال',
			'{payment_method}'  => 'روش پرداخت',
			'{site_name}'       => 'نام سایت',
			'{tracking_code}'   => 'کد رهگیری تراکنش',
		);
	}

	public static function replace_placeholder( $template, \WC_Order $order ) {
		$map = array(
			'{order_id}'        => $order->get_order_number(),
			'{order_status}'    => wc_get_order_status_name( $order->get_status() ),
			'{order_total}'     => wp_strip_all_tags( wc_price( $order->get_total() ) ),
			'{first_name}'      => $order->get_billing_first_name(),
			'{last_name}'       => $order->get_billing_last_name(),
			'{full_name}'       => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'{billing_phone}'   => $order->get_billing_phone(),
			'{shipping_method}' => $order->get_shipping_method(),
			'{payment_method}'  => $order->get_payment_method_title(),
			'{site_name}'       => get_bloginfo( 'name' ),
			'{tracking_code}'   => $order->get_transaction_id(),
		);

		$map = apply_filters( 'wc_sms_placeholders', $map, $order );

		return strtr( $template, $map );
	}

	/* -------- Notifier -------- */

	protected static function log( $message, array $context = array(), $level = 'error' ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		if ( ! empty( $context ) ) {
			$message .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE );
		}
		wc_get_logger()->log( $level, $message, array( 'source' => 'arvand-wc-sms' ) );
	}

	public function on_new_order( $order_id, $order = null ) {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$this->notify_customer( $order, $order->get_status() );
		$this->notify_admins( $order, self::EVENT_NEW_ORDER );
	}

	public function on_status_changed( $order_id, $from, $to, $order ) {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$this->notify_customer( $order, $to );
		$this->notify_admins( $order, $to );
	}

	/**
	 * Send the per-status template to the customer, if one is defined.
	 */
	protected function notify_customer( \WC_Order $order, $status ) {
		$status    = ltrim( $status, 'wc-' );
		$templates = get_option( self::OPTION_TEMPLATES, array() );
		$template  = isset( $templates[ $status ] ) ? trim( (string) $templates[ $status ] ) : '';

		if ( $template === '' ) {
			return;
		}

		$mobile = $order->get_billing_phone();
		if ( ! $mobile ) {
			self::log( 'No billing phone on order.', array( 'order_id' => $order->get_id() ) );
			return;
		}

		$this->send( array( $mobile ), self::replace_placeholder( $template, $order ), $order, $status );
	}

	/**
	 * Send a fixed notification to admin numbers for the given event
	 * (a status key, or EVENT_NEW_ORDER for newly placed orders).
	 */
	protected function notify_admins( \WC_Order $order, $event ) {
		$event  = ltrim( $event, 'wc-' );
		$events = (array) get_option( self::OPTION_ADMIN_EVENTS, array() );

		if ( ! in_array( $event, $events, true ) ) {
			return;
		}

		$phones = self::get_admin_phones();
		if ( empty( $phones ) ) {
			return;
		}

		if ( $event === self::EVENT_NEW_ORDER ) {
			$message = sprintf( 'سفارش جدید با شماره %s در سایت %s ثبت شد', $order->get_order_number(), get_bloginfo( 'name' ) );
		} else {
			$message = sprintf( 'سفارش %s به وضعیت %s تغییر پیدا کرد', $order->get_order_number(), wc_get_order_status_name( $event ) );
		}

		$message = apply_filters( 'wc_sms_admin_message', $message, $order, $event );

		$this->send( $phones, $message, $order, $event );
	}

	/**
	 * Send one message to a list of numbers via the active gateway.
	 */
	protected function send( array $mobiles, $message, \WC_Order $order, $context ) {
		$active = $this->active_gateway();

		if ( ! $active ) {
			self::log( 'No SMS gateway configured.', array( 'order_id' => $order->get_id() ) );
			return;
		}

		foreach ( array_unique( $mobiles ) as $mobile ) {
			try {
				$active['gateway']->send( $mobile, $message, $active['config'] );
			} catch ( \Throwable $e ) {
				self::log( 'SMS send failed: ' . $e->getMessage(), array(
					'order_id' => $order->get_id(),
					'gateway'  => $active['id'],
					'context'  => $context,
					'mobile'   => $mobile,
				) );
				$order->add_order_note( 'خطای ارسال پیامک به ' . $mobile . ': ' . $e->getMessage() );
			}
		}
	}

	public static function get_admin_phones() {
		$raw = (string) get_option( self::OPTION_ADMIN_PHONES, '' );
		if ( $raw === '' ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
	}

	protected function get_order_statuses() {
		$statuses = wc_get_order_statuses();
		unset( $statuses['wc-checkout-draft'] );
		return $statuses;
	}

	/**
	 * Admin-notify options: new-order event + all order statuses.
	 *
	 * @return array<string,string> key => label
	 */
	protected function get_admin_events() {
		$events = array( self::EVENT_NEW_ORDER => 'ثبت سفارش جدید' );

		foreach ( $this->get_order_statuses() as $status_key => $status_label ) {
			$events[ ltrim( $status_key, 'wc-' ) ] = $status_label;
		}

		return $events;
	}

	/* -------- Settings tab -------- */

	public function add_tab( $tabs ) {
		$tabs[ self::TAB_ID ] = 'پیامک اطلاع‌رسانی';
		return $tabs;
	}

	public function render_settings() {
		$active_gateway = get_option( self::OPTION_GATEWAY, '' );
		$templates      = get_option( self::OPTION_TEMPLATES, array() );
		$config         = get_option( self::OPTION_CONFIG, array() );
		$admin_phones   = (string) get_option( self::OPTION_ADMIN_PHONES, '' );
		$admin_events   = (array) get_option( self::OPTION_ADMIN_EVENTS, array() );
		$gateways       = $this->all_gateways();
		$statuses       = $this->get_order_statuses();
		?>
		<h2>تنظیمات پیامک اطلاع‌رسانی</h2>
		<p>در صورتی که برای یک وضعیت متن پیامک وارد شود، هنگام تغییر سفارش به آن وضعیت پیامک ارسال می‌شود.</p>

		<table class="form-table">
			<tr>
				<th scope="row">پنل پیامک</th>
				<td>
					<select name="wc_sms_active_gateway" id="wc_sms_active_gateway">
						<option value="">— انتخاب کنید —</option>
						<?php foreach ( $gateways as $id => $g ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $active_gateway, $id ); ?>>
								<?php echo esc_html( $g->get_label() ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<?php foreach ( $gateways as $id => $gateway ) : ?>
			<div class="wc-sms-gateway-config" data-gateway="<?php echo esc_attr( $id ); ?>" <?php echo $active_gateway === $id ? '' : 'style="display:none"'; ?>>
				<h3>تنظیمات <?php echo esc_html( $gateway->get_label() ); ?></h3>
				<table class="form-table">
					<?php foreach ( $gateway->get_settings_fields() as $field ) :
						$value = isset( $config[ $id ][ $field['id'] ] ) ? $config[ $id ][ $field['id'] ] : '';
						$name  = sprintf( 'wc_sms_gateway_config[%s][%s]', $id, $field['id'] );
						?>
						<tr>
							<th scope="row"><label><?php echo esc_html( $field['title'] ); ?></label></th>
							<td>
								<input type="<?php echo esc_attr( $field['type'] ); ?>"
									   name="<?php echo esc_attr( $name ); ?>"
									   value="<?php echo esc_attr( $value ); ?>"
									   class="regular-text" autocomplete="off" />
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endforeach; ?>

		<script>
		(function(){
			var sel = document.getElementById('wc_sms_active_gateway');
			if ( ! sel ) return;
			var panels = document.querySelectorAll('.wc-sms-gateway-config');
			sel.addEventListener('change', function(){
				panels.forEach(function(p){
					p.style.display = ( p.dataset.gateway === sel.value ) ? '' : 'none';
				});
			});
		})();
		</script>

		<h3>اطلاع‌رسانی به مدیران</h3>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="wc_sms_admin_phones">شماره‌های مدیران</label></th>
				<td>
					<input type="text" id="wc_sms_admin_phones" name="wc_sms_admin_phones"
						   value="<?php echo esc_attr( $admin_phones ); ?>"
						   class="regular-text" dir="ltr" autocomplete="off"
						   placeholder="09121234567, 09131234567" />
					<p class="description">شماره‌های مدیران را با , از هم جدا کنید.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">رویدادهای اطلاع‌رسانی</th>
				<td>
					<fieldset>
						<?php foreach ( $this->get_admin_events() as $event_key => $event_label ) : ?>
							<label style="display:inline-block;margin-inline-end:16px;">
								<input type="checkbox" name="wc_sms_admin_events[]"
									   value="<?php echo esc_attr( $event_key ); ?>"
									   <?php checked( in_array( $event_key, $admin_events, true ) ); ?> />
								<?php echo esc_html( $event_label ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description">در رویدادهای انتخاب‌شده، پیامک با متن ثابت برای مدیران ارسال می‌شود.</p>
				</td>
			</tr>
		</table>

		<h3>متن پیامک وضعیت‌های سفارش</h3>
		<p><strong>Placeholderهای قابل استفاده:</strong></p>
		<ul style="columns:2;max-width:900px;">
			<?php foreach ( self::placeholders() as $tag => $desc ) : ?>
				<li><code><?php echo esc_html( $tag ); ?></code> — <?php echo esc_html( $desc ); ?></li>
			<?php endforeach; ?>
		</ul>

		<table class="form-table">
			<?php foreach ( $statuses as $status_key => $status_label ) :
				$key   = ltrim( $status_key, 'wc-' );
				$value = isset( $templates[ $key ] ) ? $templates[ $key ] : '';
				?>
				<tr>
					<th scope="row"><label><?php echo esc_html( $status_label ); ?></label></th>
					<td>
						<textarea name="wc_sms_templates[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text" placeholder="در صورت خالی بودن، پیامکی برای این وضعیت ارسال نمی‌شود."><?php echo esc_textarea( $value ); ?></textarea>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><label>پیامک موجود شدن محصول</label></th>
				<td>
					<textarea name="wc_sms_templates[bis]" rows="3" class="large-text" placeholder="در صورت خالی بودن، این قابلیت غیرفعال می‌شود."><?php echo esc_textarea( $templates[ 'bis' ] ?? '' ); ?></textarea>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_settings() {
		if ( isset( $_POST['wc_sms_active_gateway'] ) ) {
			update_option( self::OPTION_GATEWAY, sanitize_key( wp_unslash( $_POST['wc_sms_active_gateway'] ) ) );
		}

		if ( isset( $_POST['wc_sms_gateway_config'] ) && is_array( $_POST['wc_sms_gateway_config'] ) ) {
			$raw     = wp_unslash( $_POST['wc_sms_gateway_config'] );
			$cleaned = array();
			foreach ( $raw as $gateway_id => $fields ) {
				if ( ! is_array( $fields ) ) {
					continue;
				}
				$gid = sanitize_key( $gateway_id );
				foreach ( $fields as $field_id => $val ) {
					$cleaned[ $gid ][ sanitize_key( $field_id ) ] = sanitize_text_field( $val );
				}
			}
			update_option( self::OPTION_CONFIG, $cleaned );
		}

		if ( isset( $_POST['wc_sms_templates'] ) && is_array( $_POST['wc_sms_templates'] ) ) {
			$raw     = wp_unslash( $_POST['wc_sms_templates'] );
			$cleaned = array();
			foreach ( $raw as $status_key => $text ) {
				$cleaned[ sanitize_key( $status_key ) ] = sanitize_textarea_field( $text );
			}
			update_option( self::OPTION_TEMPLATES, $cleaned );
		}

		$phones = isset( $_POST['wc_sms_admin_phones'] )
			? sanitize_text_field( wp_unslash( $_POST['wc_sms_admin_phones'] ) )
			: '';
		$phones = array_filter( array_map( 'trim', explode( ',', $phones ) ) );
		update_option( self::OPTION_ADMIN_PHONES, implode( ', ', $phones ) );

		$events = isset( $_POST['wc_sms_admin_events'] ) && is_array( $_POST['wc_sms_admin_events'] )
			? array_values( array_unique( array_map( 'sanitize_key', wp_unslash( $_POST['wc_sms_admin_events'] ) ) ) )
			: array();
		update_option( self::OPTION_ADMIN_EVENTS, $events );
	}
}
