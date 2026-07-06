<?php
defined('ABSPATH') || exit;

$ticket_content	= $_POST['ticket_message'] ?? ( isset( $_GET['fastreply'] ) ? ($settings['fast_replies'][absint($_GET['fastreply'])] ?? '') : '');
$ticket_content	= $ticket_content ? sanitize_textarea_field(urldecode(wp_unslash($ticket_content))) : '';
$departments	= $settings['departments'] ?: [];

?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html($ticket ? sprintf('تیکت #%s', $ticket->number) : 'ثبت تیکت جدید'); ?></h1>
	<a href="<?php echo esc_url(admin_url('admin.php?page=tickets')); ?>" class="page-title-action">بازگشت</a>
	<hr class="wp-header-end">

	<?php //TODO: Add Error ?>

	<form action="#" method="post" id="ticket-form" enctype="multipart/form-data">
		<?php
		wp_nonce_field('arvand_ticket_admin', 'arvand_ticket_admin');
		if( $ticket ) {
			printf('<input type="hidden" name="ticket_id" value="%d">', $ticket->id);
		}
		?>

		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="post-body-content" style="position:relative;">
					<?php if( !$ticket ): ?>
						<div id="titlediv" style="margin-bottom:12px;">
							<div id="titlewrap">
								<label class="screen-reader-text" for="title">عنوان تیکت</label>
								<input type="text" name="ticket_title" size="30" value="" id="title" placeholder="عنوان تیکت (حداقل 5 کاراکتر)" autocomplete="off" required minlength="5">
							</div>
						</div>
					<?php endif; ?>

					<div id="postdivrich">
						<?php
						wp_editor($ticket_content, 'ticket_content', [
							'teeny'         => true,
							'textarea_rows' => 6,
							'media_buttons' => false,
							'quicktags'     => false,
							'tinymce'       => [
								'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink',
								'toolbar2' => '',
							],
						]);
						?>
						<div class="attachment-section" style="margin-top:20px;display:flex;align-items:center;gap:12px">
							<button type="button" id="insert-attachment-button" class="button" style="display:flex;align-items:center;gap:4px"><span class="dashicons dashicons-admin-media"></span> پیوست فایل</button>
							<button type="button" id="remove-attachment" class="button-link" style="color:#b32d2e;display:none;"><span class="dashicons dashicons-no"></span></button>
							<input	type="hidden" name="arvand_ticket_attachment" id="ticket-attachment-id" value="">
						</div>
					</div>

					<?php if ($ticket): ?>
					<div id="commentsdiv" class="postbox" style="margin-top:20px;">
						<div class="postbox-header">
							<h2>
								<?php echo esc_html($ticket->title); ?>
								<?php if ($ticket->createdby === 'system'): ?>
									<small style="color:#787c82;font-weight:normal;">(ایجاد شده توسط سیستم)</small>
								<?php endif; ?>
							</h2>
						</div>
						<div class="inside">
							<div class="comments-box" style="margin-bottom:-1px">
								<?php if (!empty($ticket->messages)): ?>
									<?php foreach (array_reverse($ticket->messages) as $index => $message): ?>
										<?php
										$user				= get_user_by('ID', $message->user_id);
										$time				= strtotime( $message->date );
										$is_admin			= user_can($message->user_id, 'manage_options');
										$is_current_user	= $message->user_id == get_current_user_id();
										?>
										<div class="<?php echo $is_admin ? 'admin-message' : 'user-message'; ?>" style="border-bottom:1px solid #c3c4c7;padding:12px">
											<div class="message-header" style="margin-bottom:10px;">
												<strong><?php echo esc_html($user->display_name); ?></strong>
												<?php if ($is_admin): ?>
													<span class="admin-badge" style="display:inline-block;background:#00a32a;color:#fff;padding:2px 6px;border-radius:3px;font-size:10px;margin-right:5px;">پشتیبان</span>
												<?php endif; ?>
												<time datetime="<?php echo esc_attr(date('c', $time)); ?>" style="color:#787c82;font-size:12px;display:block"><?php echo human_time_diff($time, current_time('timestamp')) . ' پیش'; ?>(<?php echo wp_date('Y/m/d H:i', $time); ?>)</time>
											</div>

											<div class="message-content">
												<?php
												echo wp_kses_post($message->message);
												if( $message->attachment_id ){
													$attachment_url = wp_get_attachment_url($message->attachment_id);
													$attachment_title = get_the_title($message->attachment_id);
													printf(
														'<div style="margin-top:16px;"><a href="%s" target="_blank" rel="noopener" class="message-attachment"><span class="dashicons dashicons-media-document"></span>%s <small style="color:#787c82;font-size:11px;">(%s)</small></a></div>',
														esc_url($attachment_url),
														esc_html($attachment_title),
														size_format( filesize(get_attached_file($message->attachment_id)) )
													);
												}
												?>
											</div>
										</div>
									<?php endforeach; ?>
								<?php else: ?>
									<p style="text-align:center;color:#787c82;padding:20px;">هیچ پیامی وجود ندارد</p>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<?php endif; ?>
				</div>

				<div id="postbox-container-1" class="postbox-container">
					<div id="side-sortables">
						<div id="submitdiv" class="postbox">
							<div class="inside">
								<div class="submitbox" id="submitpost">
									<div id="minor-publishing">
										<div id="misc-publishing-actions">
											<?php if( ! empty( $departments ) ) : ?>
												<div class="misc-pub-section">
													<label for="ticket_department" class="label"><span class="dashicons dashicons-category"></span> دپارتمان :</label>
													<select name="ticket_department" id="ticket_department">
														<option value="">انتخاب کنید</option>
														<?php
														foreach ($departments as $dept){
															printf('<option value="%s"%s> %s</option>', esc_attr($dept), selected($ticket->department ?? '', $dept, false), esc_html($dept) );
														}
														?>
													</select>
												</div>
											<?php endif; ?>

											<?php if ($ticket): ?>
												<div class="misc-pub-section">
													<strong class="label"><span class="dashicons dashicons-info"></span> وضعیت‌ :</strong>
													<span class="status-badge" style="background:<?php 
														echo $ticket->status === 'closed' ? '#787c82' : 
															($ticket->status === 'answered' ? '#00a32a' : '#d63638'); 
													?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;">
														<?php echo esc_html($this->get_status_name($ticket->status)); ?>
													</span>
												</div>

												<div class="misc-pub-section">
													<strong class="label"><span class="dashicons dashicons-calendar"></span> ایجاد شده :</strong>
													<time datetime="<?php echo esc_attr(date('c', strtotime($ticket->date_created))); ?>" dir="ltr"><?php echo wp_date('Y/m/d H:i', strtotime($ticket->date_created)); ?></time>
												</div>

												<div class="misc-pub-section">
													<strong class="label"><span class="dashicons dashicons-update"></span> بروزرسانی :</strong>
													<time datetime="<?php echo esc_attr(date('c', strtotime($ticket->date_modified))); ?>" dir="ltr"><?php echo wp_date('Y/m/d H:i', strtotime($ticket->date_modified)); ?></time>
												</div>

												<?php
												$user_base = get_user_by('id', $ticket->user_base);
												$user_target = get_user_by('id', $ticket->user_target);
												if ($user_base): ?>
												<div class="misc-pub-section">
													<strong class="label">
														<span class="dashicons dashicons-admin-users"></span>
														کاربر :
													</strong>
													<div>
														<?php
														$display_users = sprintf('<a href="%s" target="_blank">%s</a>', esc_url( get_edit_user_link($user_base->ID) ), esc_html($user_base->display_name) );
														if( $user_target ){
															$display_users .= ' / '. sprintf('<a href="%s" target="_blank">%s</a>', esc_url( get_edit_user_link($user_target->ID) ), esc_html($user_target->display_name) );
														}
														echo $display_users;
														?>
													</div>
												</div>
												<?php endif; ?>

											<?php else: ?>
												<div class="misc-pub-section">
													<label for="ticket_user_base" class="label"><span class="dashicons dashicons-admin-users"></span> کاربر: </label>
													<select name="ticket_user_base" id="ticket_user_base" style="width:100%;">
														<option value="<?php echo $current_user->ID; ?>" selected>
															<?php echo esc_html($current_user->display_name); ?> (من)
														</option>
														<?php foreach ($users as $user): ?>
															<?php if ($user->ID != $current_user->ID): ?>
																<option value="<?php echo esc_attr($user->ID); ?>">
																	<?php echo esc_html($user->display_name); ?>
																</option>
															<?php endif; ?>
														<?php endforeach; ?>
													</select>
												</div>

												<div class="misc-pub-section">
													<label for="ticket_user_target" class="label"><span class="dashicons dashicons-businessperson"></span> مکالمه با:</label>
													<select name="ticket_user_target" id="ticket_user_target" style="width:100%;">
														<option value="0">اپراتور ها</option>
														<?php foreach ($users as $user): ?>
															<option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
														<?php endforeach; ?>
													</select>
												</div>
											<?php endif; ?>

										</div>
										<div class="clear"></div>
									</div>

									<div id="major-publishing-actions">
										<div id="delete-action">
											<?php if ($ticket && $ticket->status !== 'closed'): ?>
												<label class="submitdelete deletion">
													<input type="checkbox" name="close_ticket" value="yes" id="close-ticket-checkbox">
													بستن تیکت
												</label>
											<?php elseif ($ticket && $ticket->status === 'closed'): ?>
												<span style="color:#787c82;">
													<span class="dashicons dashicons-lock"></span>
													تیکت بسته شده است
												</span>
											<?php endif; ?>
										</div>
										<?php if(! $ticket || $ticket->status !== 'closed') : ?>
											<div id="publishing-action">
												<button type="submit" class="button button-primary button-large" id="submit-button"><?php echo esc_html( $ticket ? 'ارسال پاسخ' : 'ثبت و ارسال' ); ?></button>
											</div>
										<?php endif; ?>
										<div class="clear"></div>
									</div>
								</div>
							</div>
						</div>

						<?php if( $settings['fast_replies'] ) : ?>
							<div id="fastreplies" class="postbox">
								<div class="postbox-header"><h2>پیام‌های آماده</h2></div>
								<div class="inside" style="margin:0;padding:0;">
									<ul style="margin:0;padding:0;">
										<?php
										foreach( $settings['fast_replies'] as $reply ){
											printf('<li>%s</li>', $reply);
										}
										?>
									</ul>
								</div>
							</div>
						<?php endif; ?>

					</div>
				</div>
			</div>
			<!-- /post-body -->
			<br class="clear">
		</div>
		<!-- /poststuff -->
	</form>
</div>

<style>
.misc-pub-section {
	display: flex;
	justify-content: space-between;
	gap: 0.5rem;
	align-items: center;
	padding: 10px;
	border-bottom: 1px solid #f0f0f1;
}

.misc-pub-section:last-child {
	border-bottom: none;
}

.misc-pub-section .label{
	flex: 0 0 auto;
	display: flex;
	align-items: center;
	gap: 0.5rem;
	min-width: 90px;
}
.misc-pub-section .dashicons{color: #646970;}

.message-attachment {
	text-decoration: none;
	padding:8px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;display:inline-block;
	transition: all 0.2s;
}

.message-attachment:hover {
	background: #f6f7f7 !important;
}

#submit-button:disabled {
	cursor: not-allowed;
	opacity: 0.5;
}

#submit-button.loading .button-text {
	opacity: 0.6;
}

#submit-button.loading .spinner {
	display: inline-block !important;
	visibility: visible;
}

.ticket-actions .button {
	transition: all 0.2s;
}

.ticket-actions .button:hover {
	transform: translateY(-1px);
	box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#fastreplies ul{padding:0;margin:0;}
#fastreplies ul li{padding:12px;margin:0;tranistion:0.3s;cursor:pointer;}
#fastreplies ul li:not(:last-child){border-bottom:1px solid #dcdcde}
#fastreplies ul li:hover{background:#f6f7f7}

/* Responsive */
@media screen and (max-width: 782px) {
	.misc-pub-section {
		flex-direction: column;
		align-items: flex-start;
	}
	
	.misc-pub-section strong {
		min-width: auto;
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	var ticketMediaUploader;
	var ticketMediaUploaderDefault = $('#insert-attachment-button').html();
	$('#insert-attachment-button').on('click', function(e) {
		e.preventDefault();
		if (ticketMediaUploader) {
			ticketMediaUploader.open();
			return;
		}
		
		ticketMediaUploader = wp.media({
			title: 'انتخاب فایل پیوست',
			button: {
				text: 'انتخاب فایل'
			},
			multiple: false,
		});
		
		ticketMediaUploader.on('select', function() {
			var attachment = ticketMediaUploader.state().get('selection').first().toJSON();
			$('#ticket-attachment-id').val(attachment.id);
			$('#insert-attachment-button').html(`<span class="dashicons dashicons-paperclip"></span> ${attachment.filename}`);
			$('#remove-attachment').show();
		});
		ticketMediaUploader.open();
	});

	$('#fastreplies ul li').on('click', function(e){
		$('#ticket_message').html( $(this).html() );
	});

	$('#remove-attachment').on('click', function() {
		$('#insert-attachment-button').html(ticketMediaUploaderDefault);
		$('#ticket-attachment-id').val('');
		$(this).hide();
	});
	
	<?php if ($ticket): ?>
	$('#close-ticket-checkbox').on('change', function() {
		if ($(this).is(':checked')) {
			if (!confirm('با بستن تیکت، امکان ارسال پاسخ جدید وجود نخواهد داشت. ادامه می‌دهید؟')) {
				$(this).prop('checked', false);
			}
		}
	});
	<?php endif; ?>

	// Form validation
	<?php if (!$ticket): ?>
	$('#title').on('input', function() {
		var val = $(this).val();
		if (val.length > 0 && val.length < 5) {
			$(this).css('border-color', '#d63638');
		} else {
			$(this).css('border-color', '');
		}
	});
	<?php endif; ?>

});
</script>