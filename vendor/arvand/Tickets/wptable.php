<?php

use Arvand\Tickets\Tickets;

if( !class_exists('WP_List_Table') ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Arvand_Tickets_Table extends WP_List_Table {
	private $per_page = 20;
	private $total_items = 0;

	public function __construct() {
		parent::__construct([
			'singular' => 'ticket',
			'plural'   => 'tickets',
			'ajax'     => true
		]);
	}


	public function get_columns() {
		$columns = [
			'cb'			=> '<input type="checkbox" />',
			'title'			=> __('Title'),
			'department'	=> 'دپارتمان',
			'user'			=> __('User'),
			'status'		=> __('Status'),
			'poritory'		=> 'الویت',
			'messages'		=> 'پیام‌ها',
			'date_created'	=> 'تاریخ ایجاد',
			'date_modified'	=> 'آخرین بروزرسانی',
		];

		$departments = Tickets::get_setting('departments', []);

		if( empty($departments) ){
			unset($columns['department']);
		}
		return $columns;
	}

	protected function get_sortable_columns() {
		return [
			'title'         => ['title', false],
			'status'        => ['status', false],
			'date_created'  => ['date_created', true],
			'date_modified' => ['date_modified', false],
		];
	}

	public function get_bulk_actions() {
		$actions = [
			'bulk_close'  => 'بستن موارد',
			'bulk_delete' => 'حذف موارد',
		];
		return $actions;
	}

	public function process_bulk_action() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$action = $this->current_action();

		if (!$action) {
			return;
		}

		// Verify nonce
		if (!isset($_REQUEST['_wpnonce']) || 
			!wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
			wp_die('عملیات غیرمجاز');
		}

		$ticket_ids = isset($_REQUEST['ids']) ? array_map('absint', $_REQUEST['ids']) : [];

		if (empty($ticket_ids)) {
			return;
		}

		switch ($action) {
			case 'bulk_close':
				foreach ($ticket_ids as $ticket_id) {
					$arvand_ticket->close_ticket($ticket_id);
				}
				$message = sprintf('%d تیکت بسته شد', count($ticket_ids));
				break;
			case 'bulk_delete':
				foreach ($ticket_ids as $ticket_id) {
					$arvand_ticket->delete_ticket($ticket_id);
				}
				$message = sprintf('%d تیکت حذف شد', count($ticket_ids));
				break;
		}
	}

	public function prepare_items() {
		global $wpdb;

		$this->process_bulk_action();

		$columns	= $this->get_columns();
		$hidden		= [];
		$sortable	= $this->get_sortable_columns();
		$primary	= 'title';
		$per_page	= $this->get_items_per_page('tickets_per_page', 20);
		$paged		= $this->get_pagenum();
		$offset		= ($paged - 1) * $per_page;

		$this->_column_headers = [$columns, $hidden, $sortable, $primary];

		$table = $wpdb->prefix . 'arvand_tickets';
		$where = ['1=1'];
		$where_values = [];

		// Search filter
		if (!empty($_REQUEST['s'])) {
			$search = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
			$where[] = '(title LIKE %s OR number LIKE %s)';
			$where_values[] = $search;
			$where_values[] = $search;
		}

		// Status filter
		if (!empty($_REQUEST['status']) && $_REQUEST['status'] !== 'all') {
			$where[] = 'status = %s';
			$where_values[] = sanitize_text_field($_REQUEST['status']);
		}

		// Department filter
		if (!empty($_REQUEST['department'])) {
			$where[] = 'department = %s';
			$where_values[] = sanitize_text_field($_REQUEST['department']);
		}

		// Date range filter
		if (!empty($_REQUEST['date_from'])) {
			$where[] = 'date_created >= %s';
			$where_values[] = sanitize_text_field($_REQUEST['date_from']) . ' 00:00:00';
		}
		if (!empty($_REQUEST['date_to'])) {
			$where[] = 'date_created <= %s';
			$where_values[] = sanitize_text_field($_REQUEST['date_to']) . ' 23:59:59';
		}

		$where_clause = implode(' AND ', $where);

		// Get total items
		if (!empty($where_values)) {
			$count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
			$this->total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
		} else {
			$this->total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where_clause}");
		}

		// Sorting
		$orderby = !empty($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'date_modified';
		$order = !empty($_REQUEST['order']) && $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';

		// Get items
		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		
		if (!empty($where_values)) {
			$items = $wpdb->get_results( $wpdb->prepare($query, array_merge($where_values, [$per_page, $offset])), ARRAY_A);
		} else {
			$items = $wpdb->get_results( $wpdb->prepare($query, [$per_page, $offset]), ARRAY_A);
		}

		$this->items = $items;

		// Set pagination
		$this->set_pagination_args([
			'total_items' => $this->total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil($this->total_items / $per_page)
		]);
	}

	public function column_default($item, $column_name) {
		switch( $column_name ) {
			case 'title':
				return sprintf(
					'<code dir="ltr">%s</code> <strong style="display:inline-block"><a href="%s" class="row-title">%s</a></strong>',
					esc_html($item['number']),
					esc_url( add_query_arg(['id' => $item['id']], admin_url('admin.php?page=tickets')) ),
					esc_html($item['title'])
				);
			case 'department':
				if (empty($item['department'])) {
					return '&ndash;';
				}
				
				$filter_url = add_query_arg(['page' => 'tickets', 'department' => $item['department']], admin_url('admin.php'));
				return sprintf(
					'<a href="%s">%s</a>',
					esc_url($filter_url),
					esc_html($item['department'])
				);
			case 'user':
				$user = get_userdata($item['user_base']);
				if( ! $user ) {
					return '&ndash;';
				}
				return sprintf('<a href="%s">%s</a>', esc_url(get_edit_user_link($user->ID)), esc_html($user->display_name));
			case 'date_created':
			case 'date_modified':
				$date = $column_name === 'date_modified' ? $item['date_modified'] : $item['date_created'];
				$date = strtotime($date);
				$formatted = wp_date('Y/m/d H:i', $date);
				return sprintf('<span dir="ltr" title="%s">%s</span>', esc_attr($formatted), esc_html($formatted) );
			case 'status':
				$status = $item['status'];
				$status_name = Tickets::get_status_name($status);
				$status_colors = [
					'pending'  => '#d63638',
					'answered' => '#00a32a',
					'closed'   => '#787c82',
					'deleted'  => '#dba617'
				];
				$color = $status_colors[$status] ?? '#2271b1';

				return sprintf(
					'<span class="status-badge" style="background-color:%s;">%s</span>',
					esc_attr($color),
					esc_html($status_name)
				);
			case 'poritory':
				$poritories	= [ 'low' => 'پایین', 'normal' => 'متوسط', 'high' => 'بالا' ];
				$status_name = $poritories[ $item['poritory'] ] ?? 'متوسط';
				return sprintf(
					'<span>%s</span>',
					esc_html( $status_name )
				);
			default:
				return $item[$column_name] ?? '&ndash;';
		}
	}

	/**
	 * Checkbox column
	 */
	public function column_cb($item) {
		return sprintf(
			'<input type="checkbox" name="ids[]" value="%s" />',
			esc_attr($item['id'])
		);
	}

	/**
	 * Messages count column
	 */
	public function column_messages($item) {
		global $wpdb;
		$messages_table = $wpdb->prefix . 'arvand_ticket_messages';
		
		$count = wp_cache_get('ticket_messages_count_' . $item['id'], 'arvand_tickets');
		
		if ($count === false) {
			$count = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$messages_table} WHERE ticket_id = %d",
				$item['id']
			));
			wp_cache_set('ticket_messages_count_' . $item['id'], $count, 'arvand_tickets', 300);
		}

		return sprintf(
			'<span class="messages-count">%d</span>',
			absint($count)
		);
	}

	/**
	 * Display extra tablenav (filters)
	 */
	protected function extra_tablenav($which) {
		if ($which !== 'top') {
			return;
		}

		$current_status = $_REQUEST['status'] ?? 'all';
		$current_department = $_REQUEST['department'] ?? '';
		?>
		<div class="alignleft actions">
			<?php
			$statuses = [
				'all'		=> 'همه وضعیت‌ها',
				'pending'	=> 'در انتظار پاسخ',
				'answered'	=> 'پاسخ داده شده',
				'closed'	=> 'بسته شده',
			];
			?>
			<select name="status" id="filter-by-status">
				<?php foreach ($statuses as $value => $label): ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($current_status, $value); ?>>
						<?php echo esc_html($label); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php
			$departments = Tickets::get_setting('departments', []);
			if( $departments ){
				echo '<select name="department" id="filter-by-department"><option value="">همه دپارتمان‌ها</option>';
				foreach ($departments as $dept){
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr($dept),
						selected($current_department, $dept, false),
						esc_html($dept),
					);
				}
				echo '</select>';
			}
			?>
		</div>

		<?php
	}

	public function no_items() {
		echo 'هیچ تیکتی یافت نشد.';
	}

	/**
	 * Enqueue admin scripts
	 */
	public static function enqueue_scripts() {
		?>
		<style>
		.no-items {
			text-align:center;
		}
		.status-badge {
			white-space: nowrap;
			display:inline-block;
			padding:3px 8px;
			border-radius:4px;
			font-size:12px;
			font-weight:600;
			color:#fff;
		}
		.messages-count {
			display: inline-block;
			background: #2271b1;
			color: #fff;
			padding: 2px 8px;
			border-radius: 10px;
			font-size: 11px;
			font-weight: 600;
		}
		</style>
		<?php
	}
}