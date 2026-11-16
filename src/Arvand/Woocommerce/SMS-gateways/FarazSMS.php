<?php
namespace Arvand\Woocommerce;

defined( 'ABSPATH' ) || exit;

class FarazSMS extends AbstractSMSGateway {

	public function get_id()    { return 'farazsms'; }
	public function get_label() { return 'فراز اس‌ام‌اس'; }

	public function get_settings_fields() {
		return array(
			array( 'id' => 'username', 'title' => 'نام کاربری',  'type' => 'text' ),
			array( 'id' => 'password', 'title' => 'رمز عبور',    'type' => 'password' ),
			array( 'id' => 'sender',   'title' => 'شماره ارسال', 'type' => 'text' ),
		);
	}

	public function send( $mobile, $message, array $config ) {
		$this->require_config( $config, array( 'username', 'password', 'sender' ) );

		$result = $this->http_request( 'https://ippanel.com/services.jspd', array(
			'method' => 'POST',
			'body'   => array(
				'uname'   => $config['username'],
				'pass'    => $config['password'],
				'from'    => $config['sender'],
				'to'      => wp_json_encode( array( $mobile ) ),
				'message' => $message,
				'op'      => 'send',
			),
		) );

		$ok = is_array( $result['json'] ) && isset( $result['json'][0] ) && (int) $result['json'][0] > 1000;
		if ( ! $ok ) {
			throw new \Exception( 'FarazSMS error: ' . $result['body'] );
		}

		return true;
	}
}
