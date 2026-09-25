<?php
/**
 * Support Tickets Class
 *
 * @author Pouriya Amjadzadeh
 * @version 6.1.0
 */

namespace Arvand;

final class Tickets {
	private string $table;
	private string $messages_table;
	private static ?self $_instance = null;
	private array $settings_cache = [];
	private bool $db_initialized = false;

	private const DEFAULT_SETTINGS = [
		'max_upload_size'     => 5,
		'allowed_extensions'  => ['.jpeg', '.jpg', '.png', '.gif', '.pdf', '.zip'],
		'departments'         => [],
		'fast_replies'        => [],
		'auto_close_days'     => 7,
	];

	public static function instance(): self {
		if ( is_null( self::$_instance ) ) {
			global $wpdb;
			self::$_instance = new self();
			self::$_instance->table          = $wpdb->prefix . 'arvand_tickets';
			self::$_instance->messages_table = $wpdb->prefix . 'arvand_ticket_messages';
		}
		
		if ( ! self::$_instance->db_initialized ) {
			self::$_instance->setup_db();
			self::$_instance->db_initialized = true;
		}

		return self::$_instance;
	}

	private function setup_db(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql_tickets = "CREATE TABLE IF NOT EXISTS {$this->table} (
			id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
			number VARCHAR(15) NOT NULL,
			title VARCHAR(100) NOT NULL,
			createdby VARCHAR(20) NOT NULL DEFAULT 'user',
			status VARCHAR(10) NOT NULL DEFAULT 'pending',
			user_base MEDIUMINT UNSIGNED NOT NULL,
			user_target MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
			date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			date_modified DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			department VARCHAR(30) DEFAULT NULL,
			poritory VARCHAR(10) DEFAULT 'normal',
			PRIMARY KEY (id),
			UNIQUE KEY number (number),
			KEY user_base (user_base),
			KEY status (status),
			KEY date_modified (date_modified),
			KEY user_base_status (user_base, status)
		) $charset_collate;";

		$sql_messages = "CREATE TABLE IF NOT EXISTS {$this->messages_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id MEDIUMINT UNSIGNED NOT NULL,
			user_id MEDIUMINT UNSIGNED NOT NULL,
			message TEXT NOT NULL,
			attachment_id MEDIUMINT UNSIGNED DEFAULT 0,
			date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY ticket_id (ticket_id),
			KEY user_id (user_id),
			KEY date (date)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_tickets );
		dbDelta( $sql_messages );
	}

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'wp_loaded', [ $this, 'maybe_schedule_auto_close' ] );
		add_action( 'close_open_tickets', [ $this, 'close_open_tickets_callback' ] );
	}

	public function admin_init(): void {
		if ( isset( $_GET['page'], $_GET['id'] ) && $_GET['page'] === 'tickets' ) {
			$ticket = $this->get_ticket( absint( $_GET['id'] ) );
			if ( ! $ticket ) {
				wp_die( 'شناسه تیکت نامعتبر است', 'شناسه نامعتبر' );
			}
		}

		if ( isset( $_REQUEST['arvand_ticket_admin'] ) && wp_verify_nonce( $_REQUEST['arvand_ticket_admin'], 'arvand_ticket_admin' ) ) {
			$ticket_id         = absint( $_REQUEST['ticket_id'] ?? 0 );
			$ticket            = $ticket_id > 0 ? $this->get_ticket( $ticket_id ) : false;
			$ticket_title      = $_POST['ticket_title'] ?? '';
			$ticket_content    = $_POST['ticket_content'] ?? '';
			$ticket_attachment = absint( $_POST['arvand_ticket_attachment'] ?? 0 );
			$ticket_user_base  = absint( $_POST['ticket_user_base'] ?? 0 );

			if ( ! $ticket ) {
				$submitted = $this->submit_ticket( [
					'title'       => $ticket_title,
					'content'     => $ticket_content,
					'user_base'   => $ticket_user_base,
					'user_target' => ( $_POST['ticket_user_target'] ?? 0 ),
					'attachment'  => $ticket_attachment,
				] );
				if ( is_wp_error( $submitted ) ) {
					wp_die( $submitted->get_error_message() );
				}
				wp_safe_redirect( add_query_arg( [ 'id' => $submitted->id ], self::admin_ticket_url() ) );
				exit;
			} else {
				if ( isset( $_POST['close_ticket'] ) ) {
					$this->close_ticket( $ticket->id );
				}
				if ( ! empty( $ticket_content ) ) {
					$this->reply_ticket( $ticket->id, $ticket_content, $ticket_attachment, 0, true );
				}
				wp_safe_redirect( add_query_arg( [ 'id' => $ticket->id ], self::admin_ticket_url() ) );
				exit;
			}
		}

		if ( isset( $_REQUEST['arvand_settings_nonce'] ) && wp_verify_nonce( $_REQUEST['arvand_settings_nonce'], 'arvand_ticket_settings' ) ) {
			$fast_replies = array_unique( array_filter( array_map( 'sanitize_textarea_field', $_POST['fast_replies'] ?? [] ) ) );
			$settings     = [
				'departments'        => array_values( array_filter( array_map( 'sanitize_text_field', explode( "\n", $_POST['departments'] ?? '' ) ) ) ),
				'max_upload_size'    => absint( $_POST['max_upload_size'] ),
				'allowed_extensions' => array_values( array_filter( array_map( 'sanitize_text_field', explode( ',', $_POST['allowed_extensions'] ) ) ) ),
				'auto_close_days'    => absint( $_POST['auto_close_days'] ),
				'fast_replies'       => $fast_replies,
			];
			$this->settings_cache = $settings;
			update_option( 'arvand_ticket_settings', $settings );
		}
	}

	public static function get_setting( string $key = null, $default = null ) {
		if ( empty( self::$_instance->settings_cache ) ) {
			self::$_instance->settings_cache = wp_parse_args( (array) get_option( 'arvand_ticket_settings' ), self::DEFAULT_SETTINGS );
		}
		if ( null !== $key ) {
			return self::$_instance->settings_cache[ $key ] ?? $default ?? self::DEFAULT_SETTINGS[ $key ] ?? null;
		}
		return self::$_instance->settings_cache;
	}

	protected static function admin_ticket_url(): string {
		return add_query_arg( [ 'page' => 'tickets' ], admin_url( 'admin.php' ) );
	}

	public function admin_menu(): void {
		global $wpdb;

		add_menu_page(
			__('Tickets Support'),
			__('Support'),
			'manage_options',
			'tickets',
			[ $this, 'tickets_cb_page' ],
			'dashicons-sos',
			20
		);

		add_submenu_page(
			'tickets',
			'تنظیمات تیکت',
			__('Settings'),
			'manage_options',
			'tickets-settings',
			[ $this, 'settings_page' ]
		);

		$cache_key     = 'arvand_tickets_pending_count';
		$pending_count = wp_cache_get( $cache_key );

		if ( $pending_count === false ) {
			$pending_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$this->table} WHERE status = %s", 'pending' ) );
			wp_cache_set( $cache_key, $pending_count, '', 300 );
		}

		if ( $pending_count > 0 ) {
			global $menu;
			foreach ( $menu as $key => $menu_item ) {
				if ( $menu_item[2] === 'tickets' ) {
					$menu[ $key ][0] .= " <span class='update-plugins count-{$pending_count}'><span class='plugin-count'>" .
						number_format_i18n( $pending_count ) . '</span></span>';
					break;
				}
			}
		}
	}

	public function settings_page(): void {
		$settings = self::get_setting();
		include_once __DIR__ . '/settings.php';
	}

	public function tickets_cb_page(): void {
		global $current_user;
		$base_url = self::admin_ticket_url();
		$settings = self::get_setting();

		if ( isset( $_GET['id'] ) || ( isset( $_GET['action'] ) && $_GET['action'] === 'new_ticket' ) ) {
			$ticket = false;
			if ( isset( $_GET['id'] ) ) {
				$ticket = $this->get_ticket( absint( $_GET['id'] ) );
			}
			$users = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );
			include_once __DIR__ . '/form.php';
			return;
		}

		include_once __DIR__ . '/cbpage.php';
	}

	private function generate_number(): string|\WP_Error {
		global $wpdb;
		$max_attempts = 10;

		for ( $i = 0; $i < $max_attempts; $i++ ) {
			$t   = time();
			$num = substr( $t, 0, 4 ) . wp_rand( 10, 99 ) . substr( $t, 4, 1 );
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$this->table} WHERE `number` = %s", $num ) );
			if ( $exists === 0 ) {
				return $num;
			}
		}

		return new \WP_Error( 'number_generation_failed', 'خطا در تولید شماره تیکت، لطفاً دوباره تلاش کنید' );
	}


	private function upload_attachment( $file ): int|\WP_Error {
		if ( is_numeric( $file ) ) {
			$file = absint( $file );
			if ( $file === 0 ) {
				return 0;
			}
			if ( false !== get_attached_file( $file ) ) {
				return $file;
			}
			return new \WP_Error( 'invalid_attachment', 'شناسه پیوست نامعتبر است' );
		}

		if ( ! $file || ! isset( $_FILES[ $file ] ) || empty( $_FILES[ $file ]['name'] ) ) {
			return 0;
		}

		$attachment = $_FILES[ $file ];

		$max_size = self::get_setting( 'max_upload_size' ) * MB_IN_BYTES;
		if ( $attachment['size'] > $max_size ) {
			return new \WP_Error( 'file_too_large', sprintf( 'حجم فایل بیش از حد مجاز است. حداکثر: %s', size_format( $max_size ) ) );
		}

		$allowed_extensions = self::get_setting( 'allowed_extensions' );
		$file_ext           = '.' . strtolower( pathinfo( $attachment['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $file_ext, $allowed_extensions, true ) ) {
			return new \WP_Error( 'invalid_file_type', 'فرمت فایل مجاز نیست. فرمت‌های مجاز: ' . implode( ', ', $allowed_extensions ) );
		}

		$wp_filetype = wp_check_filetype_and_ext( $attachment['tmp_name'], $attachment['name'] );
		if ( ! $wp_filetype['type'] ) {
			return new \WP_Error( 'invalid_mime_type', 'نوع فایل نامعتبر است' );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$_FILES[ $file ]['name'] = 'ticket_' . uniqid() . '_' . sanitize_file_name( $attachment['name'] );
		$attachment_id           = media_handle_upload( $file, 0 );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return $attachment_id;
	}

	public function submit_ticket( array $args = [] ) {
		global $wpdb, $current_user;

		$args = wp_parse_args( $args, [
			'title'      => null,
			'content'    => null,
			'poritory'   => 'normal',
			'department' => '',
			'attachment' => 'arvand_ticket_attachment',
			'user_base'  => $current_user->ID,
			'user_target'=> 0,
			'system'     => false,
		] );

		try {
			$args['title']			= sanitize_text_field( $args['title'] );
			$args['content']		= sanitize_textarea_field( $args['content'] );
			$args['user_base']		= absint( $args['user_base'] );
			$args['user_target']	= absint( $args['user_target'] );
			$args['department']		= sanitize_text_field( $args['department'] );
			$args['poritory']		= in_array( $args['poritory'], [ 'low', 'normal', 'high' ] ) ? $args['poritory'] : 'normal';

			if ( empty( $args['title'] ) || strlen( $args['title'] ) < 4 ) {
				throw new \Exception( 'عنوان تیکت باید حداقل 4 کاراکتر باشد' );
			}
			if ( empty( $args['content'] ) || strlen( $args['content'] ) < 10 ) {
				throw new \Exception( 'متن تیکت باید حداقل 10 کاراکتر باشد' );
			}
			if ( ! get_user_by( 'ID', $args['user_base'] ) ) {
				throw new \Exception( 'شناسه کاربر معتبر نیست' );
			}
			if ( $args['user_target'] !== 0 && ! get_user_by( 'ID', $args['user_target'] ) ) {
				throw new \Exception( 'شناسه کاربر مقصد معتبر نیست' );
			}
			if ( $args['user_target'] !== 0 && $args['user_target'] === $args['user_base'] ) {
				throw new \Exception( 'با خودتان امکان ایجاد مکالمه ندارید!' );
			}

			$departments = self::get_setting( 'departments' );
			if ( ! empty( $args['department'] ) && ! in_array( $args['department'], $departments, true ) ) {
				$args['department'] = $departments[0] ?? '';
			}

			$attachment_id = $this->upload_attachment( $args['attachment'] );
			if ( is_wp_error( $attachment_id ) ) {
				throw new \Exception( $attachment_id->get_error_message() );
			}

			$number = $this->generate_number();
			if ( is_wp_error( $number ) ) {
				throw new \Exception( $number->get_error_message() );
			}

			$inserted = $wpdb->insert(
				$this->table,
				[
					'number'        => $number,
					'title'         => $args['title'],
					'createdby'     => $args['system'] ? 'system' : 'user',
					'status'        => $args['system'] ? 'answered' : 'pending',
					'date_created'  => current_time( 'mysql', true ),
					'date_modified' => current_time( 'mysql', true ),
					'user_base'     => $args['user_base'],
					'user_target'   => $args['user_target'],
					'department'    => $args['department'],
					'poritory'		=> $args['poritory'],
				],
				[ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
			);

			if ( $inserted === false ) {
				throw new \Exception( 'خطا در ایجاد تیکت' );
			}

			$ticket_id = $wpdb->insert_id;
			$this->insert_message( $ticket_id, $args['user_base'], $args['content'], $attachment_id );

			$ticket = $this->get_ticket( $ticket_id );
			do_action( 'arvand_ticket_submitted', $ticket );

			return $ticket;

		} catch ( \Exception $e ) {
			return new \WP_Error( 'ticket_submit_error', $e->getMessage() );
		}
	}

	private function insert_message( int $ticket_id, int $user_id, string $content, int $attachment_id = 0 ): bool {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->messages_table,
			[
				'ticket_id'     => $ticket_id,
				'user_id'       => $user_id,
				'message'       => wp_kses_post( $content ),
				'attachment_id' => $attachment_id,
				'date'          => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%d', '%s' ]
		);

		return $inserted !== false;
	}

	/**
	 * @param int $ticket_id
	 * @param string $content
	 * @param string|int|null $attachment 
	 * @param int $user_id  0 = current user
	 * @param bool $is_operator 
	 */
	public function reply_ticket( int $ticket_id, string $content = '', string|int|null $attachment = 'arvand_ticket_attachment', int $user_id = 0, bool $is_operator = false ) {
		global $wpdb;

		$ticket = $this->get_ticket( $ticket_id );
		if ( ! $ticket ) {
			return new \WP_Error( 'invalid_ticket_id', 'شناسه تیکت نامعتبر' );
		}

		$user_id = $user_id > 0 ? $user_id : get_current_user_id();

		if ( $ticket->status === 'closed' ) {
			return new \WP_Error( 'ticket_closed', 'این تیکت بسته شده است!' );
		}

		$content = wp_kses_post( $content );
		if ( empty( $content ) ) {
			return new \WP_Error( 'empty_content', 'متن پیام الزامی است' );
		}

		$attachment_id = $this->upload_attachment( $attachment );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$message_inserted = $this->insert_message( $ticket->id, $user_id, $content, $attachment_id );
		if ( ! $message_inserted ) {
			return new \WP_Error( 'message_insert_failed', 'خطا در ثبت پیام' );
		}

		$wpdb->update(
			$this->table,
			[
				'date_modified'	=> current_time( 'mysql', true ),
				'status'		=> $is_operator ? 'answered' : 'pending',
			],
			[ 'id' => $ticket->id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		do_action( $is_operator ? 'arvand_ticket_reply_operator' : 'arvand_ticket_reply_user', $ticket );

		return true;
	}

	public static function poritory_name( $poritory ){
		$poritories = [ 'low' => 'پایین', 'normal' => 'متوسط', 'high' => 'بالا' ];
		return $poritories[ $poritory ] ?? 'متوسط';
	}

	public static function get_ticket( $ticket_id, int $user_id = null ) {
		global $wpdb;
		$instance = self::$_instance;

		$sql = $wpdb->prepare( "SELECT * FROM {$instance->table} WHERE id = %s OR number = %s", sanitize_text_field( $ticket_id ), sanitize_text_field( $ticket_id ) );

		if ( $user_id ) {
			$sql .= $wpdb->prepare( ' AND (user_base = %d OR user_target = %d)', absint( $user_id ), absint( $user_id ) );
		}

		$ticket = $wpdb->get_row( $sql );

		if ( ! $ticket ) {
			return false;
		}

		$messages = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$instance->messages_table} WHERE ticket_id = %d ORDER BY date ASC", $ticket->id )
		);

		$ticket->messages		= $messages ?: [];
		$ticket->status_name	= self::get_status_name( $ticket->status );

		$ticket->poritory_name	= self::poritory_name($ticket->poritory);

		return $ticket;
	}

	public static function get_tickets( array $args = [] ): array {
		global $wpdb;
		$instance = self::$_instance;

		$args = wp_parse_args( $args, [
			'user_id'  => get_current_user_id(),
			'per_page' => -1,
			'status'   => 'any',
			'paged'    => 1,
		] );

		$where  = [ 'user_base = %d' ];
		$values = [ absint( $args['user_id'] ) ];

		if ( $args['status'] !== 'any' ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		$args['paged']    = max( 1, absint( $args['paged'] ) );
		$args['per_page'] = intval( $args['per_page'] ) <= 0 ? 0 : absint( $args['per_page'] );

		$sql = $wpdb->prepare("SELECT * FROM {$instance->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC', $values);

		if ( $args['per_page'] > 0 ) {
			$offset = ( $args['paged'] - 1 ) * $args['per_page'];
			$sql   .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['per_page'], $offset );
		}

		$tickets = $wpdb->get_results( $sql );
		if ( empty( $tickets ) ) {
			return [];
		}

		$ticket_ids     = array_map( fn( $t ) => (int) $t->id, $tickets );
		$ids_placeholder = implode( ',', array_fill( 0, count( $ticket_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$all_messages = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$instance->messages_table} WHERE ticket_id IN ($ids_placeholder) ORDER BY date ASC", $ticket_ids )
		);

		$messages_by_ticket = [];
		foreach ( $all_messages as $msg ) {
			$messages_by_ticket[ $msg->ticket_id ][] = $msg;
		}

		$result     = [];

		foreach ( $tickets as $ticket ) {
			$ticket->messages		= $messages_by_ticket[ $ticket->id ] ?? [];
			$ticket->status_name	= self::get_status_name( $ticket->status );
			$ticket->poritory_name	= self::poritory_name($ticket->poritory);
			$result[]				= $ticket;
		}

		return $result;
	}

	public static function get_status_name( string $status ): string {
		$statuses = [
			'closed'   => 'بسته‌شده',
			'answered' => 'پاسخ‌داده',
			'pending'  => 'بررسی',
		];
		return $statuses[ $status ] ?? $status;
	}

	public function close_ticket( $ticket_id ): bool {
		global $wpdb;
		$ticket_id = absint( $ticket_id );

		if ( $ticket_id <= 0 ) {
			return false;
		}

		$updated = $wpdb->update(
			$this->table,
			[ 'status' => 'closed', 'date_modified' => current_time( 'mysql', true ) ],
			[ 'id' => $ticket_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		if ( $updated !== false ) {
			do_action( 'arvand_ticket_closed', $this->get_ticket( $ticket_id ) );
			return true;
		}

		return false;
	}

	public function delete_ticket( int $ticket_id ): bool {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$ticket_id = absint( $ticket_id );
		if ( $ticket_id <= 0 || ! $this->get_ticket( $ticket_id ) ) {
			return false;
		}

		$wpdb->delete( $this->messages_table, [ 'ticket_id' => $ticket_id ], [ '%d' ] );
		$wpdb->delete( $this->table, [ 'id' => $ticket_id ], [ '%d' ] );

		return true;
	}

	public function maybe_schedule_auto_close(): void {
		if ( ! wp_next_scheduled( 'close_open_tickets' ) ) {
			wp_schedule_event( time(), 'daily', 'close_open_tickets' );
		}
	}

	public function close_open_tickets_callback(): void {
		global $wpdb;

		$days_before      = absint( self::get_setting( 'auto_close_days' ) );
		$cutoff_date      = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_before} days" ) );

		$tickets = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$this->table} WHERE date_modified <= %s AND status NOT IN ('closed', 'deleted')", $cutoff_date)
		);

		if ( empty( $tickets ) ) {
			return;
		}

		foreach ( $tickets as $ticket ) {
			if ( $this->close_ticket( $ticket->id ) ) {
				do_action( 'arvand_ticket_auto_closed', $ticket );
			}
		}
	}
}