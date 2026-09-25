<?php
namespace Arvand\Woocommerce;

defined( 'ABSPATH' ) || exit;

class MelliPayamak extends AbstractSMSGateway {

	public function get_id()    { return 'mellipayamak'; }
	public function get_label() { return 'ملی پیامک'; }

	public function get_settings_fields() {
		return array(
			array( 'id' => 'username', 'title' => 'نام کاربری',  'type' => 'text' ),
			array( 'id' => 'password', 'title' => 'رمز عبور',    'type' => 'password' ),
			array( 'id' => 'sender',   'title' => 'شماره ارسال', 'type' => 'text' ),
		);
	}

	public function send( $mobile, $message, array $config ) {
		$this->require_config( $config, array( 'username', 'password', 'sender' ) );

		$payload = array(
			'username' => $config['username'],
			'password' => $config['password'],
			'from'     => $config['sender'],
			'to'       => $mobile,
			'text'     => $message,
			'isflash'  => false,
		);

		$result = $this->http_request( 'https://rest.payamak-panel.com/api/SendSMS/SendSMS', array(
			'method'  => 'POST',
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $payload ),
		) );

		$ret_status = isset( $result['json']['RetStatus'] ) ? (int) $result['json']['RetStatus'] : 0;
		if ( $ret_status !== 1 ) {
			$msg = isset( $result['json']['StrRetStatus'] ) ? $result['json']['StrRetStatus'] : $result['body'];
			throw new \Exception( 'MelliPayamak error: ' . $msg );
		}

		return true;
	}
}
