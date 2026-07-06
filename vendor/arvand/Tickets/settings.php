<div class="wrap">
	<h1>تنظیمات سیستم تیکت</h1>
	<form method="post" action="#" id="arvand-settings-form">
		<?php wp_nonce_field('arvand_ticket_settings', 'arvand_settings_nonce'); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="max_upload_size">حداکثر حجم آپلود (MB)</label></th>
				<td><input type="number" id="max_upload_size" name="max_upload_size" value="<?php echo esc_attr( $settings['max_upload_size'] ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="allowed_extensions">فرمت‌های مجاز فایل پیوست</label></th>
				<td>
					<input type="text" name="allowed_extensions" value="<?php echo esc_attr( implode(', ', $settings['allowed_extensions']) ); ?>" class="regular-text">
					<p class="description">با کاما جدا کنید، مثال: .jpg, .png, .pdf</p>
				</td>
			</tr>
			<tr>
				<th scope="row">دپارتمان‌ها</th>
				<td>
					<textarea name="departments" rows="5" class="regular-text"><?php echo esc_textarea( implode("\n", $settings['departments']) ); ?></textarea>
					<p class="description">هر دپارتمان را در یک خط وارد کنید</p>
				</td>
			</tr>
			<tr>
				<th scope="row">بستن خودکار بعد از (روز)</th>
				<td>
					<input type="number" name="auto_close_days" value="<?php echo esc_attr( $settings['auto_close_days'] ); ?>" class="small-text">
				</td>
			</tr>
			<tr>
				<th scope="row">پیام های آماده</th>
				<td>
					
					<div id="fast_replies_wrapper">
						<?php
						foreach($settings['fast_replies'] as $reply ){
							printf('<textarea name="fast_replies[]" class="regular-text" style="display:block;margin-bottom:10px">%s</textarea>', esc_textarea( $reply ) );
						}
						?>
					</div>
					<button type="button" id="add_fast_reply" class="button button-sm">پیام جدید</button>
				</td>
			</tr>
		</table>
		<?php submit_button('ذخیره تنظیمات'); ?>
	</form>
</div>
<script>
jQuery(document).ready(function($) {
	$(document).on('click', '#add_fast_reply', function(e){
		e.preventDefault();
		$('#fast_replies_wrapper').append(`<textarea name="fast_replies[]" class="regular-text" style="display:block;margin-bottom:10px"></textarea>`);
	});
});
</script>