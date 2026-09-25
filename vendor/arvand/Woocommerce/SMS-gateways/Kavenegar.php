<?php
namespace Arvand\Woocommerce;

defined( 'ABSPATH' ) || exit;

class Kavenegar extends AbstractSMSGateway {

	public function get_id()    { return 'kavenegar'; }
	public function get_label() { return 'کاوه‌نگار'; }

	public function get_settings_fields() {
		return array(
			array( 'id' => 'api_key', 'title' => 'API Key',      'type' => 'password' ),
			array( 'id' => 'sender',  'title' => 'شماره ارسال', 'type' => 'text' ),
		);
	}

	public function send( $mobile, $message, array $config ) {
		$this->require_config( $config, array( 'api_key' ) );

		$url = sprintf( 'https://api.kavenegar.com/v1/%s/sms/send.json', rawurlencode( $config['api_key'] ) );

		$result = $this->http_request( $url, array(
			'method' => 'POST',
			'body'   => array(
				'receptor' => $mobile,
				'message'  => $message,
				'sender'   => isset( $config['sender'] ) ? $config['sender'] : '',
			),
		) );

		$status = isset( $result['json']['return']['status'] ) ? (int) $result['json']['return']['status'] : 0;
		if ( $status !== 200 ) {
			$msg = isset( $result['json']['return']['message'] ) ? $result['json']['return']['message'] : $result['body'];
			throw new \Exception( 'Kavenegar error: ' . $msg );
		}

		return true;
	}
}
