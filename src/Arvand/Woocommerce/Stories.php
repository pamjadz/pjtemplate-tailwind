<?php
/**
 * WooCommerce Stories
 *
 * ذخیره‌سازی در wp_options به‌جای Custom Post Type
 *
 * @package Arvand\Woocommerce
 * @version 1.0.0
 */

namespace Arvand\Woocommerce;

defined( 'ABSPATH' ) || exit;

final class Stories {

	const OPTION     = 'arvandwc_stories';
	const PAGE_SLUG  = 'arvand-stories';
	const CAPABILITY = 'manage_woocommerce';

	protected static $instance;

	protected static $stories = null;

	protected $page_hook = '';

	protected function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'save' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
	}

	public static function instance() {
		return self::$instance ??= new self();
	}

	/* -----------------------------------------------------------------
	 * Data layer
	 * -------------------------------------------------------------- */

	/**
	 * همه‌ی استوری‌ها (به ترتیب مرتب‌سازی‌شده در ادمین).
	 *
	 * @return array<int, array{id:string, title:string, thumbnail:int, items:array}>
	 */
	public static function get(): array {
		if ( null === self::$stories ) {
			$stories       = get_option( self::OPTION, [] );
			self::$stories = is_array( $stories ) ? array_values( $stories ) : [];
		}
		return self::$stories;
	}

	/**
	 * پیدا کردن یک استوری با id.
	 */
	public static function find( string $id ): ?array {
		foreach ( self::get() as $story ) {
			if ( ( $story['id'] ?? '' ) === $id ) {
				return $story;
			}
		}

		return null;
	}

	/**
	 * آیتم‌های یک استوری مشخص.
	 *
	 * @return array<int, array{media:int, duration:int, link:string, link_title:string}>
	 */
	public static function items( string $story_id ): array {
		$story = self::find( $story_id );

		return $story['items'] ?? [];
	}

	/* -----------------------------------------------------------------
	 * Frontend render
	 * -------------------------------------------------------------- */

	/**
	 * خروجی HTML استوری‌ها.
	 * قالب‌بندی و رفتار (باز شدن viewer و ...) بر عهده‌ی تم شماست.
	 */
	public static function render(): string {
		$stories = self::get();

		if ( empty( $stories ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="arvand-stories">
			<?php foreach ( $stories as $story ) : ?>
				<?php $thumb = $story['thumbnail'] ? wp_get_attachment_image_url( $story['thumbnail'], 'medium' ) : ''; ?>
				<div class="arvand-stories__story" data-story-id="<?php echo esc_attr( $story['id'] ); ?>">

					<button type="button" class="arvand-stories__cover">
						<?php if ( $thumb ) : ?>
							<img class="arvand-stories__thumb" src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $story['title'] ); ?>">
						<?php endif; ?>
						<span class="arvand-stories__title"><?php echo esc_html( $story['title'] ); ?></span>
					</button>

					<div class="arvand-stories__items">
						<?php foreach ( $story['items'] as $item ) : ?>
							<?php
							$media_url = $item['media'] ? wp_get_attachment_url( $item['media'] ) : '';
							if ( ! $media_url ) {
								continue;
							}
							$is_video = wp_attachment_is( 'video', $item['media'] );
							?>
							<div class="arvand-stories__item" data-duration="<?php echo esc_attr( $item['duration'] ); ?>">
								<?php if ( $is_video ) : ?>
									<video class="arvand-stories__media" src="<?php echo esc_url( $media_url ); ?>" playsinline muted preload="none"></video>
								<?php else : ?>
									<img class="arvand-stories__media" src="<?php echo esc_url( $media_url ); ?>" alt="<?php echo esc_attr( $item['link_title'] ); ?>">
								<?php endif; ?>

								<?php if ( $item['link'] ) : ?>
									<a class="arvand-stories__link" href="<?php echo esc_url( $item['link'] ); ?>">
										<?php echo esc_html( $item['link_title'] ?: __( 'مشاهده بیشتر', 'arvand' ) ); ?>
									</a>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/* -----------------------------------------------------------------
	 * Admin
	 * -------------------------------------------------------------- */

	public function add_menu() {
		$this->page_hook = add_submenu_page(
			'woocommerce-marketing',
			__( 'استوری‌ها', 'arvand' ),
			__( 'استوری‌ها', 'arvand' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ $this, 'render_admin' ]
		);
	}

	public function assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	public function save() {
		if(
			! isset( $_POST['arvand_stories_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['arvand_stories_nonce'] ) ), 'arvand_stories_save' ) ||
			! current_user_can( self::CAPABILITY )
		) {
			return;
		}

		$raw = isset( $_POST['stories'] ) ? wp_unslash( (array) $_POST['stories'] ) : [];
		update_option( self::OPTION, $this->sanitize( $raw ), false );
		self::$stories = null;
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&updated=1' ) );
		exit;
	}

	/**
	 * اعتبارسنجی و یکدست‌سازی داده‌ی ورودی؛ ترتیب POST همان ترتیب drag & drop است.
	 */
	protected function sanitize( array $raw ): array {
		$clean = [];

		foreach ( $raw as $story ) {
			$story = (array) $story;

			$title = sanitize_text_field( $story['title'] ?? '' );
			$thumb = absint( $story['thumbnail'] ?? 0 );
			$items = [];

			foreach ( (array) ( $story['items'] ?? [] ) as $item ) {
				$item     = (array) $item;
				$media_id = absint( $item['media'] ?? 0 );

				if ( ! $media_id ) {
					continue;
				}

				$items[] = [
					'media'      => $media_id,
					'duration'   => max(5, absint( $item['duration'] ?? 5 ) ),
					'link'       => esc_url_raw( $item['link'] ?? '' ),
					'link_title' => sanitize_text_field( $item['link_title'] ?? '' ),
				];
			}

			if ( ! $title && ! $thumb && ! $items ) {
				continue;
			}

			$clean[] = [
				'id'        => sanitize_key( $story['id'] ?? '' ) ?: md5( $title ),
				'title'     => $title,
				'thumbnail' => $thumb,
				'items'     => $items,
			];
		}

		return array_values( $clean );
	}

	/* -----------------------------------------------------------------
	 * Admin UI
	 * -------------------------------------------------------------- */

	public function render_admin() {
		$stories = self::get();
		?>
		<div class="wrap arvand-stories-admin">
			<h1><?php esc_html_e( 'استوری‌ها', 'arvand' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'استوری‌ها ذخیره شدند.', 'arvand' ); ?></p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'arvand_stories_save', 'arvand_stories_nonce' ); ?>

				<div id="arvand-stories-list">
					<?php foreach ( $stories as $i => $story ) : ?>
						<?php $this->story_field( $story, $i ); ?>
					<?php endforeach; ?>
				</div>

				<p>
					<button type="button" class="button" id="arvand-add-story">+ <?php esc_html_e( 'افزودن استوری', 'arvand' ); ?></button>
				</p>

				<?php submit_button( __( 'ذخیره استوری‌ها', 'arvand' ) ); ?>
			</form>
		</div>

		<script type="text/html" id="arvand-tpl-story">
			<?php $this->story_field( [ 'id' => '', 'title' => '', 'thumbnail' => 0, 'items' => [] ], '__STORY__' ); ?>
		</script>
		<script type="text/html" id="arvand-tpl-item">
			<?php $this->item_field( [ 'media' => 0, 'duration' => 5, 'link' => '', 'link_title' => '' ], '__STORY__', '__ITEM__' ); ?>
		</script>

		<style>
			.arvand-stories-admin .arvand-story{background:#fff;border:1px solid #ccd0d4;margin:12px 0;padding:12px}
			.arvand-stories-admin .arvand-story__head{display:flex;align-items:center;gap:8px;margin-bottom:8px}
			.arvand-stories-admin .arvand-story__head input[type=text]{flex:1}
			.arvand-sort-handle{cursor:grab;color:#787c82;font-size:18px;line-height:1;padding:4px}
			.arvand-items-list{display:flex;flex-wrap:wrap;gap:10px;min-height:60px;border:1px dashed #c3c4c7;padding:10px;margin:8px 0}
			.arvand-item{background:#f6f7f7;border:1px solid #dcdcde;padding:8px;width:180px}
			.arvand-item label{display:block;font-size:11px;margin:6px 0 2px}
			.arvand-item input{width:100%}
			.arvand-media-preview{min-height:80px;background:#e5e5e5;display:flex;align-items:center;justify-content:center;margin-bottom:6px;overflow:hidden}
			.arvand-media-preview img,.arvand-media-preview video{max-width:100%;max-height:110px;display:block}
			.arvand-placeholder{background:#f0f0f1;border:1px dashed #2271b1;min-width:160px;min-height:60px}
			.arvand-remove{color:#b32d2e}
		</style>

		<script>
		jQuery(function($){
			var counter = 100000;

			function bindSortable( $list ){
				$list.sortable({
					handle: '.arvand-sort-handle--item',
					items: '> .arvand-item',
					connectWith: '.arvand-items-list',
					placeholder: 'arvand-placeholder'
				});
			}

			$('#arvand-stories-list').sortable({
				handle: '.arvand-sort-handle--story',
				items: '> .arvand-story',
				placeholder: 'arvand-placeholder'
			});
			bindSortable( $('.arvand-items-list') );

			$('#arvand-add-story').on('click', function(){
				var i = counter++;
				$('#arvand-stories-list').append( $('#arvand-tpl-story').html().replace(/__STORY__/g, i) );
				bindSortable( $('#arvand-stories-list .arvand-story').last().find('.arvand-items-list') );
			});

			$(document).on('click', '.arvand-add-item', function(){
				var $story = $(this).closest('.arvand-story');
				var i = $story.data('index');
				var j = counter++;
				$story.find('.arvand-items-list').append( $('#arvand-tpl-item').html().replace(/__STORY__/g, i).replace(/__ITEM__/g, j) );
			});

			$(document).on('click', '.arvand-remove', function(e){
				e.preventDefault();
				$(this).closest('.arvand-item, .arvand-story').remove();
			});

			var frame;
			$(document).on('click', '.arvand-select-media', function(e){
				e.preventDefault();
				var $wrap = $(this).closest('.arvand-media-field');
				var types = $wrap.data('types');

				frame = wp.media({
					title: '<?php echo esc_js( __( 'انتخاب رسانه', 'arvand' ) ); ?>',
					button: { text: '<?php echo esc_js( __( 'انتخاب', 'arvand' ) ); ?>' },
					library: types ? { type: types.split(',') } : undefined,
					multiple: false
				});

				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$wrap.find('input[type=hidden]').val(att.id);

					var html;
					if ( att.type === 'video' ) {
						html = '<video src="' + att.url + '" muted playsinline></video>';
					} else {
						var url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
						html = '<img src="' + url + '" alt="">';
					}
					$wrap.find('.arvand-media-preview').html(html);
				});

				frame.open();
			});
		});
		</script>
		<?php
	}

	/**
	 * بلاک یک استوری در ادمین.
	 */
	protected function story_field( array $story, $i ) {
		?>
		<div class="arvand-story" data-index="<?php echo esc_attr( $i ); ?>">
			<input type="hidden" name="stories[<?php echo esc_attr( $i ); ?>][id]" value="<?php echo esc_attr( $story['id'] ?? '' ); ?>">

			<div class="arvand-story__head">
				<span class="arvand-sort-handle arvand-sort-handle--story dashicons dashicons-move"></span>
				<input type="text" name="stories[<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $story['title'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'عنوان استوری', 'arvand' ); ?>">

				<div class="arvand-media-field arvand-story__thumb-field" data-types="image">
					<input type="hidden" name="stories[<?php echo esc_attr( $i ); ?>][thumbnail]" value="<?php echo esc_attr( $story['thumbnail'] ?? 0 ); ?>">
					<button type="button" class="button arvand-select-media"><?php esc_html_e( 'تصویر بندانگشتی', 'arvand' ); ?></button>
				</div>

				<button type="button" class="button-link arvand-remove"><?php esc_html_e( 'حذف استوری', 'arvand' ); ?></button>
			</div>

			<div class="arvand-items-list">
				<?php foreach ( (array) ( $story['items'] ?? [] ) as $j => $item ) : ?>
					<?php $this->item_field( $item, $i, $j ); ?>
				<?php endforeach; ?>
			</div>

			<button type="button" class="button button-small arvand-add-item">+ <?php esc_html_e( 'افزودن آیتم', 'arvand' ); ?></button>
		</div>
		<?php
	}

	/**
	 * بلاک یک آیتم در ادمین.
	 */
	protected function item_field( array $item, $i, $j ) {
		$media_id = absint( $item['media'] ?? 0 );
		?>
		<div class="arvand-item">
			<span class="arvand-sort-handle arvand-sort-handle--item dashicons dashicons-move"></span>

			<div class="arvand-media-field" data-types="video,image">
				<div class="arvand-media-preview">
					<?php
					if ( $media_id ) {
						if ( wp_attachment_is( 'video', $media_id ) ) {
							echo '<video src="' . esc_url( (string) wp_get_attachment_url( $media_id ) ) . '" muted playsinline></video>';
						} else {
							echo wp_get_attachment_image( $media_id, 'thumbnail' );
						}
					}
					?>
				</div>
				<input type="hidden" name="stories[<?php echo esc_attr( $i ); ?>][items][<?php echo esc_attr( $j ); ?>][media]" value="<?php echo esc_attr( $media_id ); ?>">
				<button type="button" class="button button-small arvand-select-media"><?php esc_html_e( 'انتخاب ویدیو / تصویر', 'arvand' ); ?></button>
			</div>

			<label><?php esc_html_e( 'مدت نمایش (ثانیه)', 'arvand' ); ?></label>
			<input type="number" min="1" name="stories[<?php echo esc_attr( $i ); ?>][items][<?php echo esc_attr( $j ); ?>][duration]" value="<?php echo esc_attr( $item['duration'] ?? 5 ); ?>">

			<label><?php esc_html_e( 'لینک', 'arvand' ); ?></label>
			<input type="url" dir="ltr" name="stories[<?php echo esc_attr( $i ); ?>][items][<?php echo esc_attr( $j ); ?>][link]" value="<?php echo esc_attr( $item['link'] ?? '' ); ?>">

			<label><?php esc_html_e( 'عنوان لینک', 'arvand' ); ?></label>
			<input type="text" name="stories[<?php echo esc_attr( $i ); ?>][items][<?php echo esc_attr( $j ); ?>][link_title]" value="<?php echo esc_attr( $item['link_title'] ?? '' ); ?>">

			<button type="button" class="button-link arvand-remove"><?php esc_html_e( 'حذف آیتم', 'arvand' ); ?></button>
		</div>
		<?php
	}
}