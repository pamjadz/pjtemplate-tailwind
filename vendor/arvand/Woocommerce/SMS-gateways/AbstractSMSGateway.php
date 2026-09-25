<?php
namespace Arvand\Woocommerce;

defined( 'ABSPATH' ) || exit;

interface GatewayInterface {
	public function get_id();
	public function get_label();
	public function get_settings_fields();
	public function send( $mobile, $message, array $config );
}

abstract class AbstractSMSGateway implements GatewayInterface {

	/**
	 * @return array{code:int, body:string, json:mixed}
	 * @throws \Exception
	 */
	protected function http_request( $url, array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'timeout' => 15,
			'method'  => 'GET',
		) );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'HTTP error: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_response_body( $response );
		$json = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			throw new \Exception( sprintf( 'HTTP %d response: %s', $code, $body ) );
		}

		return array( 'code' => $code, 'body' => $body, 'json' => $json );
	}

	protected function require_config( array $config, array $keys ) {
		foreach ( $keys as $key ) {
			if ( empty( $config[ $key ] ) ) {
				throw new \Exception( sprintf( 'Missing gateway setting: %s', $key ) );
			}
		}
	}
}
