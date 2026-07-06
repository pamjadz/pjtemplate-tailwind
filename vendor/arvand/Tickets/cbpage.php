<?php
/**
 * Tickets List Page - callback.php
 * Display tickets in WP_List_Table format
 */

// Security check
defined('ABSPATH') || exit;

// Process actions
if (isset($_GET['action']) && $_GET['action'] === 'close' && isset($_GET['id'])) {
	$ticket_id = absint($_GET['id']);	
	if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'close_ticket_' . $ticket_id)) {
		wp_die('عملیات غیرمجاز');
	}

	$closed = \Arvand\Tickets::instance()->close_ticket($ticket_id);
	
	if ($closed) {
		add_settings_error(
			'arvand_tickets',
			'ticket_closed',
			'تیکت با موفقیت بسته شد',
			'success'
		);
		set_transient('arvand_tickets_admin_notice', 'تیکت با موفقیت بسته شد', 30);
	}

	wp_safe_redirect(admin_url('admin.php?page=tickets'));
	exit;
}

require_once __DIR__.'/wptable.php';
$tickets_table = new Arvand_Tickets_Table();
$tickets_table->prepare_items();

Arvand_Tickets_Table::enqueue_scripts();
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<a href="<?php echo esc_url(add_query_arg(['page' => 'tickets', 'action' => 'new_ticket'], admin_url('admin.php'))); ?>" class="page-title-action">افزودن تیکت جدید</a>
	<a href="<?php echo esc_url(admin_url('admin.php?page=tickets-settings')); ?>" class="page-title-action">تنظیمات</a>

	<hr class="wp-header-end">

	<form method="POST" action="#">
		<input type="hidden" name="page" value="tickets" />
		<?php
		$tickets_table->search_box('جستجو در تیکت‌ها', 'ticket');
		$tickets_table->display();
		?>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	$('select[name="action"], select[name="action2"]').on('change', function() {
		var action = $(this).val();
		$(this).closest('form').on('submit', function(e) {
			if (!confirm('آیا از انجام این کار اطمینان دارید؟ این عملیات غیرقابل بازگشت است!')) {
				e.preventDefault();
				return false;
			}
		});
	});

	$('tbody input[type="checkbox"]').on('change', function() {
		if ($(this).prop('checked')) {
			$(this).closest('tr').addClass('selected');
		} else {
			$(this).closest('tr').removeClass('selected');
		}
	});

});
</script>