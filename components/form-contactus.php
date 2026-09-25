<?php 
/**
 * The template for displaying comment item
 *
 * @see 	https://developer.wordpress.org/reference/classes/wp_comment/
 * @author 	Pouriya Amjadzadeh
 * @version 3.0.0
 */

defined('ABSPATH') || exit;
?>

<form action="#" method="post" class="arvand-contactform">
	<div class="grid grid-cols-2 gap-4 mb-4">
		<p>
			<label class="inline-block mb-2 text-sm" for="arvcfName">نام و نام‌خانوادگی</label>
			<input type="text" id="arvcfName" name="arvcfName" value="<?php echo esc_attr( wp_unslash( $_POST['arvcfName'] ?? '' ) ); ?>" class="form-control bg-background" placeholder="نام و نام‌خانوادگی" aria-required="true" required>
		</p>
		<p>
			<label class="inline-block mb-2 text-sm" for="arvcfPhone">شماره همراه</label>
			<input type="tel" id="arvcfPhone" name="arvcfPhone" value="<?php echo esc_attr( wp_unslash( $_POST['arvcfPhone'] ?? '' ) ); ?>" class="form-control bg-background" placeholder="شماره همراه" aria-required="true" required></p>
		<p>
			<label class="inline-block mb-2 text-sm" for="arvcfSubject">موضوع پیام</label>
			<select id="arvcfSubject" name="arvcfSubject" value="<?php echo esc_attr( wp_unslash( $_POST['arvcfSubject'] ?? '' ) ); ?>" class="form-control bg-background" aria-required="true" required>
				<option value="استعلام سفارش">استعلام سفارش</option>
				<option value="مشکلات فنی پروژه">مشکلات فنی پروژه</option>
				<option value="پیشنهادات و انتقادات">پیشنهادات و انتقادات</option>
			</select>
		</p>
		<p>
			<label class="inline-block mb-2 text-sm" for="arvcfEmail">ایمیل (اختیاری)</label>
			<input type="email" id="arvcfEmail" name="arvcfEmail" value="<?php echo esc_attr( wp_unslash( $_POST['arvcfEmail'] ?? '' ) ); ?>" class="form-control bg-background placeholder:direction-rtl" dir="ltr" placeholder="پست الکترونیک (دلخواه)">
		</p>
	</div>
	<textarea name="arvcfMessage" class="form-control bg-background" rows="5" placeholder="متن پیام" required><?php echo isset( $_POST['arvcfMessage'] ) ? esc_textarea( wp_unslash( $_POST['arvcfMessage'] ) ) : ''; ?></textarea>
	<div class="flex items-center mt-4 gap-4">
		<div class="flex-1">
			<input type="text" name="arvcfSecux" value="" class="scale-0 opacity-0">
		</div>
		<button type="submit" class="flex-none w-full btn bg-primary text-white hover:bg-primary-300 lg:w-auto">ارسال پیام <i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" aria-hidden="true" focusable="false"><use href="#icon-arrow-action"></use></svg></i></button>
	</div>
	<script>
		jQuery(document).ready(function($){
			$(document).on('submit', 'form.arvand-contactform', function(e){
				e.preventDefault();
				const $FORM = $(this);
				const params = $FORM.serializeArray();
				const $button = $FORM.find('.btn-send');
				let defaultIcon = $button.find('.icon').html();

				$button.prop('disabled', true).find('.icon').html('<svg aria-label="loading" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><circle cx="4" cy="12" r="3"><animate id="svgSpinners3DotsBounce0" attributeName="cy" begin="0;svgSpinners3DotsBounce1.end+0.25s" calcMode="spline" dur="0.6s" keySplines=".33,.66,.66,1;.33,0,.66,.33" values="12;6;12"></animate></circle><circle cx="12" cy="12" r="3"><animate attributeName="cy" begin="svgSpinners3DotsBounce0.begin+0.1s" calcMode="spline" dur="0.6s" keySplines=".33,.66,.66,1;.33,0,.66,.33" values="12;6;12"></animate></circle><circle cx="20" cy="12" r="3"><animate id="svgSpinners3DotsBounce1" attributeName="cy" begin="svgSpinners3DotsBounce0.begin+0.2s" calcMode="spline" dur="0.6s" keySplines=".33,.66,.66,1;.33,0,.66,.33" values="12;6;12"></animate></circle></svg>');
				$.post(ajaxURL, {action: 'arvand_contact_form', form_data: $.param(params), _ajax_nonce: '<?php echo wp_create_nonce( 'arvand-contactform' ); ?>'}, function(resp){
					// showToast(resp.message, (resp.success ? 'success' : 'error'), 5000);
					$button.prop('disabled', false).html( defaultIcon );
				});
			});
		});
	</script>
</form>