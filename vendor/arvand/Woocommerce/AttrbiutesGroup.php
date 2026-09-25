<?php
/**
 * WC Group Attributes
 *
 * Adds support for grouping WooCommerce product attributes and saving them as
 *
 * @package   Arvand
 * @since     1.0.0
 */

namespace Arvand\Woocommerce;

defined( 'ABSPATH' ) || exit;

class AttrbiutesGroup {
	private const OPTION_GROUPS    = 'arvandwc_attribute_groups';
	private const OPTION_TEMPLATES = 'arvandwc_attribute_groups_templates';
	private const OPTION_ENABLED   = 'arvandwc_attribute_groups_enabled';

	/** Product meta holding the loaded template key. Saved by Load::save_product_meta(). */
	public const META_TEMPLATE = '_arvandwc_attr_template';

	protected static $instance;

	protected function __construct() {
		add_filter( 'woocommerce_get_settings_products', [$this, 'add_enable_setting'], 10, 2 );

		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( 'admin_menu', [$this, 'register_menus'] );
		add_action( 'admin_enqueue_scripts', [$this, 'enqueue_assets'], 99 );

		add_action( 'woocommerce_product_options_attributes', [$this, 'show_attribute_group_toolbar'] );

		add_action( 'wp_ajax_arvandwc_attrbiutes_render_group_order', [$this, 'render_order_thickbox'] );
		add_action( 'wp_ajax_arvandwc_attrbiutes_save_group_order', [$this, 'save_group_order'] );
	}

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function is_enabled(): bool {
		return 'yes' === get_option( self::OPTION_ENABLED, 'no' );
	}

	/**
	 * Checkbox in WooCommerce → Settings → Products to toggle the whole feature.
	 */
	public function add_enable_setting( array $settings, string $current_section ): array {
		if ( $current_section !== '' ) {
			return $settings;
		}

		$settings[] = [
			'title' => 'گروه‌بندی ویژگی‌ها',
			'type'  => 'title',
			'id'    => 'arvandwc_attribute_groups_section',
		];
		$settings[] = [
			'title'   => 'گروه‌بندی ویژگی‌ها',
			'desc'    => 'فعال‌سازی گروه‌بندی ویژگی‌های محصولات (گروه‌ها، قالب‌ها و نمایش گروه‌بندی‌شده)',
			'id'      => self::OPTION_ENABLED,
			'type'    => 'checkbox',
			'default' => 'no',
		];
		$settings[] = [
			'type' => 'sectionend',
			'id'   => 'arvandwc_attribute_groups_section',
		];

		return $settings;
	}

	public function enqueue_assets( string $hook ): void {
		$group_pages = [ 'product_page_wc-attribute-groups', 'product_page_wc-attribute-templates' ];

		if ( in_array( $hook, $group_pages, true ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
			add_thickbox();
		}

	}

	public function register_menus(): void {
		add_submenu_page(
			'edit.php?post_type=product',
			'گروه‌بندی ویژگی‌ها',
			'گروه‌بندی ویژگی‌ها',
			'manage_options',
			'wc-attribute-groups',
			[ $this, 'render_groups_page' ],
			10
		);

		add_submenu_page(
			'edit.php?post_type=product',
			'قالب ویژگی‌ها',
			'قالب ویژگی‌ها',
			'manage_options',
			'wc-attribute-templates',
			[ $this, 'render_templates_page' ],
			11
		);
	}

	public static function get_groups(): array {
		return (array) get_option( self::OPTION_GROUPS, [] );
	}

	private function get_templates(): array {
		return (array) get_option( self::OPTION_TEMPLATES, [] );
	}

	private function save_groups( array $data ): void {
		update_option( self::OPTION_GROUPS, $data );
	}

	private function save_templates( array $data ): void {
		update_option( self::OPTION_TEMPLATES, $data );
	}

	private function make_key( string $label ): string {
		return substr( md5( $label . microtime() ), 0, 12 );
	}

	/**
	 * Human label for a stored attribute id ('weight', 'dimensions', or a taxonomy id).
	 */
	private function attr_label( $id ): string {
		if ( 'weight' === $id ) {
			return __( 'Weight', 'woocommerce' );
		}
		if ( 'dimensions' === $id ) {
			return __( 'Dimensions', 'woocommerce' );
		}

		$attr = wc_get_attribute( (int) $id );
		return $attr ? $attr->name : '';
	}

	private function render_admin_page( string $page_title, array $table_args, callable $form ): void {
		$defaults = [
			'headings' => [],
			'rows'     => null,
			'empty'    => __( 'No items found.', 'arvand' ),
		];
		$table = wp_parse_args( $table_args, $defaults );
		?>
		<div class="wrap woocommerce">
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<div id="col-container">
				<div id="col-right">
					<div class="col-wrap">
						<table class="widefat wp-list-table" style="width:100%">
							<thead>
								<tr>
								<?php foreach ( $table['headings'] as $label ) : ?>
									<th><?php echo esc_html( $label ); ?></th>
								<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php
								if ( is_callable( $table['rows'] ) ) {
									$has_rows = (bool) call_user_func( $table['rows'] );
								} else {
									$has_rows = false;
								}
								if ( ! $has_rows ) :
									$cols = count( $table['headings'] ) ?: 2;
									printf(
										'<tr><td colspan="%d">%s</td></tr>',
										esc_attr( $cols ),
										esc_html( $table['empty'] )
									);
								endif;
								?>
							</tbody>
						</table>
					</div>
				</div>

				<div id="col-left">
					<div class="col-wrap">
					<?php call_user_func( $form ); ?>
					</div>
				</div>
			</div>
			<script>
			jQuery(function($) {
				$('.js-delete').on('click', function() {
					return confirm(<?php echo wp_json_encode('آیا از حذف این آیتم اطمینان دارید؟'); ?>);
				});
			});
			</script>
		</div> <?php
	}

	public function render_groups_page(): void {
		$groups    = $this->handle_group_actions();
		$taxs      = wc_get_attribute_taxonomies();
		$edit_key  = isset( $_GET['edit'], $groups[ $_GET['edit'] ] ) ? sanitize_key( $_GET['edit'] ) : null;
		$edit      = $edit_key ? $groups[ $edit_key ] : null;

		$taxs_temp = [
			'weight' => __( 'Weight', 'woocommerce' ),
			'dimensions' => __( 'Dimensions', 'woocommerce' ),
		];
		foreach ( $taxs as $tax ){
			$taxs_temp[ $tax->attribute_id ] = $tax->attribute_label;
		}
		$taxs = $taxs_temp;

		$this->render_admin_page(
			get_admin_page_title(),
			[
				'headings' => [ __('Name'), __( 'Attributes', 'woocommerce' ) ],
				'empty'    => __( 'No groups found.', 'arvand' ),
				'rows'     => function() use ( $groups ) {
					if ( empty( $groups ) ) {
						return false;
					}

					$thick_base = [
						'action'	=> 'arvandwc_attrbiutes_render_group_order',
						'nonce'		=> wp_create_nonce( 'group_order_nonce' ),
						'TB_iframe'	=> true,
						'width'		=> 600,
						'height'	=> 550,
					];
					foreach ( $groups as $k => $g ) :
						$thick_url = add_query_arg( array_merge( [ 'group_id' => $k ], $thick_base ), admin_url( 'admin-ajax.php' ) );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $g['label'] ); ?></strong>
								<div class="row-actions">
									<span class="edit">
										<a href="<?php echo esc_url( add_query_arg( 'edit', $k ) ); ?>"><?php esc_html_e( 'Edit' ); ?></a> |
									</span>
									<span class="delete">
										<a class="js-delete" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'delete', $k ), 'arvand-delete-group' ) ); ?>"><?php esc_html_e( 'Delete' ); ?></a>
									</span>
								</div>
							</td>
							<td>
								<?php echo $this->format_attr_names( $g['attrs'] ?? [] ); ?>
								<div class="row-actions">
									<span class="order"><a href="<?php echo esc_url( $thick_url ); ?>" class="thickbox"><?php esc_html_e('Reorder'); ?></a></span>
								</div>
							</td>
						</tr>
						<?php
					endforeach;
					return true;
				},
			],

			function() use ( $edit, $edit_key, $taxs ) { ?>
				<div class="form-wrap">
					<h2><?php echo $edit ? 'ویرایش گروه' : 'افزودن گروه جدید'; ?></h2>
					<form method="post" action="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>">
						<?php wp_nonce_field( 'arvand-save-group' ); ?>
						<input type="hidden" name="group_key" value="<?php echo esc_attr( $edit_key ?? '' ); ?>">

						<div class="form-field">
							<label for="group_label"><?php esc_html_e( 'Name' ); ?></label>
							<input id="group_label" name="group_label" type="text" required value="<?php echo esc_attr( $edit['label'] ?? '' ); ?>">
						</div>

						<div class="form-field">
							<label><?php esc_html_e( 'Attributes', 'woocommerce' ); ?></label>
							<select id="sp_group_attrs" name="group_attrs[]" class="wc-enhanced-select regular-text" multiple>
								<?php
								foreach ( $taxs as $tax_key => $tax_label ) : ?>
									<option value="<?php echo esc_attr( $tax_key ); ?>" <?php selected( in_array( $tax_key, $edit['attrs'] ?? [] ) ); ?>>
										<?php echo esc_html( $tax_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<p class="submit">
							<button type="submit" class="button button-primary">
								<?php echo $edit ? esc_html__( 'Update', 'arvand' ) : esc_html__( 'Add Group', 'arvand' ); ?>
							</button>
							<?php if ( $edit ) : ?>
								<a href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'arvand' ); ?></a>
							<?php endif; ?>
						</p>
					</form>
				</div> <?php
			}
		);
	}

	private function handle_group_actions(): array {
		$groups = self::get_groups();

		if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
			return $groups;
		}

		if ( isset( $_GET['delete'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'arvand-delete-group' ) ) {
			$k = sanitize_key( $_GET['delete'] );
			if ( isset( $groups[ $k ] ) ) {
				unset( $groups[ $k ] );
				$this->save_groups( $groups );
			}
		}

		if ( isset( $_POST['group_label'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'arvand-save-group' ) ) {
			$label = sanitize_text_field( wp_unslash( $_POST['group_label'] ) );
			$key   = sanitize_key( $_POST['group_key'] ?? '' ) ?: $this->make_key( $label );
			$attrs = array_map( 'sanitize_text_field', (array) ( $_POST['group_attrs'] ?? [] ) );
			$attrs = array_values( array_unique( array_filter( $attrs ) ) );

			$groups[ $key ] = [ 'label' => $label, 'attrs' => $attrs ];
			$this->save_groups( $groups );
		}

		return self::get_groups();
	}

	public function render_templates_page(): void {
		$templates = $this->handle_template_actions();
		$groups    = self::get_groups();
		$edit_key  = isset( $_GET['edit'], $templates[ $_GET['edit'] ] ) ? sanitize_key( $_GET['edit'] ) : null;
		$edit      = $edit_key ? $templates[ $edit_key ] : null;
		$this->render_admin_page(
			get_admin_page_title(),
			[
				'headings' => [ __( 'Template' ), __( 'Attributes', 'woocommerce' ) ],
				'empty'    => __( 'No templates found.', 'arvand' ),
				'rows'     => function() use ( $templates, $groups ) {
					if ( empty( $templates ) ) {
						return false;
					}
					$thick_base = [
						'action'	=> 'arvandwc_attrbiutes_render_group_order',
						'nonce'		=> wp_create_nonce( 'group_order_nonce' ),
						'TB_iframe'	=> true,
						'width'		=> 600,
						'height'	=> 550,
					];
					foreach ( $templates as $k => $tpl ) :
						$thick_url = add_query_arg( array_merge( [ 'template_id' => $k ], $thick_base ), admin_url( 'admin-ajax.php' ) );
						$labels = array_filter(
							array_map( fn( $gk ) => $groups[ $gk ]['label'] ?? null, $tpl['groups'] ?? [] )
						);
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $tpl['label'] ); ?></strong>
								<div class="row-actions">
									<span class="edit">
										<a href="<?php echo esc_url( add_query_arg( 'edit', $k ) ); ?>"><?php esc_html_e( 'Edit' ); ?></a> |
									</span>
									<span class="delete">
										<a class="js-delete" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'delete', $k ), 'arvand-delete-template' ) ); ?>"><?php esc_html_e( 'Delete' ); ?></a>
									</span>
								</div>
							</td>
							<td>
								<?php echo $labels ? esc_html( implode( ' | ', $labels ) ) : '<span class="na">&ndash;</span>'; ?>
								<div class="row-actions">
									<span class="order"><a href="<?php echo esc_url( $thick_url ); ?>" class="thickbox"><?php esc_html_e('Reorder'); ?></a></span>
								</div>
							</td>
						</tr>
						<?php
					endforeach;
					return true;
				},
			],

			// ---- col-left form ----
			function() use ( $edit, $edit_key, $groups ) {
				?>
				<div class="form-wrap">
					<h2><?php echo $edit ? esc_html__( 'Edit Template', 'arvand' ) : esc_html__( 'Add New Template', 'arvand' ); ?></h2>
					<form method="post" action="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>">
						<?php wp_nonce_field( 'arvand-save-template' ); ?>
						<input type="hidden" name="template_key" value="<?php echo esc_attr( $edit_key ?? '' ); ?>">

						<div class="form-field">
							<label for="template_label"><?php esc_html_e( 'Name', 'arvand' ); ?></label>
							<input id="template_label" name="template_label" type="text" required
								value="<?php echo esc_attr( $edit['label'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'e.g. Mobile, Laptop, Clothing…', 'arvand' ); ?>">
						</div>

						<div class="form-field">
							<label><?php esc_html_e( 'Attribute Groups', 'arvand' ); ?></label>
							<?php if ( $groups ) : ?>
								<select id="sp_template_groups" name="template_groups[]" class="wc-enhanced-select regular-text" multiple>
									<?php foreach ( $groups as $gk => $g ) : ?>
										<option value="<?php echo esc_attr( $gk ); ?>" <?php selected( in_array( $gk, $edit['groups'] ?? [], true ) ); ?>>
											<?php echo esc_html( $g['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<p class="description">
									<?php printf(
										esc_html__( 'No groups available. %s first.', 'arvand' ),
										'<a href="' . esc_url( admin_url( 'edit.php?post_type=product&page=wc-attribute-groups' ) ) . '">' . esc_html__( 'Create a group', 'arvand' ) . '</a>'
									); ?>
								</p>
							<?php endif; ?>
						</div>

						<p class="submit">
							<button type="submit" class="button button-primary" <?php disabled( ! $groups ); ?>>
								<?php echo $edit ? esc_html__( 'Update', 'arvand' ) : esc_html__( 'Add Template', 'arvand' ); ?>
							</button>
							<?php if ( $edit ) : ?>
								<a href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'arvand' ); ?></a>
							<?php endif; ?>
						</p>
					</form>
				</div>
				<?php
			}
		);
	}

	private function handle_template_actions(): array {
		$templates = $this->get_templates();

		if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
			return $templates;
		}

		if ( isset( $_GET['delete'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'arvand-delete-template' ) ) {
			$k = sanitize_key( $_GET['delete'] );
			if ( isset( $templates[ $k ] ) ) {
				unset( $templates[ $k ] );
				$this->save_templates( $templates );
			}
		}

		if ( isset( $_POST['template_label'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'arvand-save-template' ) ) {
			$label  = sanitize_text_field( wp_unslash( $_POST['template_label'] ) );
			$key    = sanitize_key( $_POST['template_key'] ?? '' ) ?: $this->make_key( $label );
			$groups = array_values( array_filter( array_map( 'sanitize_key', (array) ( $_POST['template_groups'] ?? [] ) ) ) );

			$templates[ $key ] = [
				'label'  => $label,
				'groups' => $groups,
			];

			$this->save_templates( $templates );
		}

		return $this->get_templates();
	}

	private function format_attr_names( array $ids ): string {
		if ( empty( $ids ) ) {
			return '<span class="na">&ndash;</span>';
		}

		$names = array_filter( array_map( [ $this, 'attr_label' ], $ids ) );

		return $names
			? esc_html( implode( ' | ', $names ) )
			: '<span class="na">&ndash;</span>';
	}

	public function show_attribute_group_toolbar(): void {
		$groups    = self::get_groups();
		$templates = $this->get_templates();

		if ( empty( $groups ) && empty( $templates ) ) {
			return;
		}

		global $post;
		$current_template = $post ? (string) get_post_meta( $post->ID, self::META_TEMPLATE, true ) : '';

		$taxs      = wc_get_attribute_taxonomies();
		$tax_by_id = [];
		foreach ( $taxs as $tax ) {
			$tax_by_id[ (string) $tax->attribute_id ] = 'pa_' . $tax->attribute_name;
		}

		$groups_data = [];
		foreach ( $groups as $key => $group ) {
			$groups_data[ $key ] = [
				'label' => $group['label'],
				'attrs' => array_values( array_filter( array_map(
					fn( $id ) => $tax_by_id[ (string) $id ] ?? null,
					$group['attrs'] ?? []
				) ) ),
			];
		}

		$templates_data = [];
		foreach ( $templates as $key => $tpl ) {
			$attrs = [];
			foreach ( $tpl['groups'] ?? [] as $gk ) {
				if ( isset( $groups_data[ $gk ] ) ) {
					$attrs = array_merge( $attrs, $groups_data[ $gk ]['attrs'] );
				}
			}
			$templates_data[ $key ] = [
				'label' => $tpl['label'],
				'attrs' => array_values( array_unique( $attrs ) ),
			];
		}
		?>
		<div class="toolbar">
			<div class="actions">
				<?php if ( $groups ) : ?>
					<select id="wc_attr_group">
						<option value=""><?php esc_html_e( 'Attribute Groups', 'arvand' ); ?></option>
						<?php foreach ( $groups as $key => $group ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $group['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" id="load_attr_group" class="button button-primary">
						<?php esc_html_e( 'Load Group', 'arvand' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $templates ) : ?>
					<select id="wc_attr_template">
						<option value=""><?php esc_html_e( 'Attribute Templates', 'arvand' ); ?></option>
						<?php foreach ( $templates as $key => $tpl ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_template, $key ); ?>><?php echo esc_html( $tpl['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" id="load_attr_template" class="button button-primary">
						<?php esc_html_e( 'Load Template', 'arvand' ); ?>
					</button>
					<input type="hidden" name="arvandwc_attr_template" id="arvandwc_attr_template" value="<?php echo esc_attr( $current_template ); ?>">
				<?php endif; ?>
			</div>
		</div>

		<script>
		jQuery(function($) {
			var groups    = <?php echo wp_json_encode( $groups_data ); ?>;
			var templates = <?php echo wp_json_encode( $templates_data ); ?>;
			var ajaxUrl   = typeof woocommerce_admin_meta_boxes !== 'undefined' ? woocommerce_admin_meta_boxes.ajax_url : ajaxurl;
			var nonce     = typeof woocommerce_admin_meta_boxes !== 'undefined' ? woocommerce_admin_meta_boxes.add_attribute_nonce : '';
			var $wrapper  = $('#product_attributes');

			function addAttributeRow(taxonomy, index, done) {
				$.ajax({
					type:    'post',
					url:     ajaxUrl,
					data: {
						action:   'woocommerce_add_attribute',
						taxonomy: taxonomy,
						i:        index,
						security: nonce,
					},
					success: function(response) {
						var $attributes  = $wrapper.find('.product_attributes');
						var product_type = $('select#product-type').val();

						$attributes.append(response);

						if ( 'variable' !== product_type ) {
							$attributes.find('.enable_variation').hide();
						}

						$('select.attribute_taxonomy').find('option[value="' + taxonomy + '"]').attr('disabled', 'disabled');
						$('select.attribute_taxonomy').val('');
						$( document.body ).trigger('wc-enhanced-select-init');

						if ( typeof attribute_row_indexes === 'function' ) {
							attribute_row_indexes();
						}

						$attributes.find('.woocommerce_attribute').last().find('h3').trigger('click');
						$( document.body ).trigger('woocommerce_added_attribute');

						done();
					},
					error: done,
				});
			}

			function loadAttrs(taxonomies) {
				if ( ! taxonomies || ! taxonomies.length ) {
					alert(<?php echo wp_json_encode( __( 'No attributes found for this selection.', 'arvand' ) ); ?>);
					return;
				}

				var existing = {};
				$wrapper.find('.woocommerce_attribute').each(function() {
					var t = $(this).data('taxonomy');
					if ( t ) existing[t] = true;
				});

				var toAdd = taxonomies.filter(function(t) { return ! existing[t]; });

				if ( ! toAdd.length ) {
					alert(<?php echo wp_json_encode( __( 'All attributes in this selection are already added.', 'arvand' ) ); ?>);
					return;
				}

				var baseIndex = $wrapper.find('.woocommerce_attribute').length;

				$wrapper.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });

				(function addNext(i) {
					if ( i >= toAdd.length ) {
						$wrapper.unblock();
						return;
					}
					addAttributeRow(toAdd[i], baseIndex + i, function() {
						addNext(i + 1);
					});
				}(0));
			}

			$('#load_attr_group').on('click', function() {
				var key = $('#wc_attr_group').val();
				if ( ! key || ! groups[key] ) {
					alert(<?php echo wp_json_encode( __( 'Please select an attribute group.', 'arvand' ) ); ?>);
					return;
				}
				loadAttrs(groups[key].attrs);
			});

			$('#load_attr_template').on('click', function(){
				var key = $('#wc_attr_template').val();
				if ( ! key || ! templates[key] ) {
					alert(<?php echo wp_json_encode( __( 'Please select a template.', 'arvand' ) ); ?>);
					return;
				}
				if ( $wrapper.find('.woocommerce_attribute').length ) {
					if ( ! confirm(<?php echo wp_json_encode( __( 'This will remove all current attributes and load the template. Continue?', 'arvand' ) ); ?>) ) return;
					$wrapper.find('.product_attributes').empty();
					$('select.attribute_taxonomy option').removeAttr('disabled');
				}
				$('#arvandwc_attr_template').val(key);
				loadAttrs(templates[key].attrs);
			});
		});
		</script>

		<?php
	}

	public function render_order_thickbox(): void {
		check_ajax_referer( 'group_order_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'arvand' ) );
		}

		$is_template	= isset( $_GET['template_id'] );
		$groups			= self::get_groups();

		if( $is_template ) {
			$target		= $this->get_templates();
			$target_id	= sanitize_key( $_GET['template_id'] ?? '' );
			$target		= $target[ $target_id ] ?? false;
		} else {
			$target_id	= sanitize_key( $_GET['group_id'] ?? '' );
			$target		= $groups[ $target_id ] ?? false;
		}

		if ( ! $target ) {
			wp_die( esc_html__( 'No data found.', 'arvand' ) );
		}
		
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="utf-8">
			<title><?php esc_html_e( 'Reorder Attributes', 'arvand' ); ?></title>
			<?php
			wp_print_styles( 'common' );
			wp_print_styles( 'forms' );
			wp_print_styles( 'buttons' );
			wp_print_styles( 'dashicons' );
			wp_print_scripts('jquery-ui-sortable');
			?>
			<style>
				#group-order-container { padding: 20px; }
				.sortable-list{
					list-style: none;
					padding: 0;
					margin: 20px 0;
					max-height: 400px;
					overflow-y: auto;
				}
				.sortable-list li {
					padding: 8px 12px;
					margin: 5px 0;
					background: #f9f9f9;
					border: 1px solid #ddd;
					border-radius: 3px;
					cursor: move;
					transition: background 0.2s;
				}
				.sortable-list li:hover { background: #f0f0f0; }
				.sortable-list li .dashicons {
					margin-inline-end: 8px;
					color: #999;
				}
				.sortable-list .ui-sortable-helper {
					background: #fff;
					box-shadow: 0 2px 8px rgba(0,0,0,0.15);
				}
				.order-actions {
					margin-top: 20px;
					text-align: end;
				}
			</style>
		</head>
		<body class="wp-core-ui">
			<div id="group-order-container">
				<ul id="sortable-groups" class="sortable-list">
					<?php
					if( $is_template ){
						foreach ( $target['groups'] as $id ) :
							$group = $groups[ $id ] ?? false;
							if ( ! $group ) continue;
							printf(
								'<li data-group-id="%s"><span class="dashicons dashicons-menu"></span> %s</li>',
								esc_attr( $id ),
								esc_html( $group['label'] )
							);
						endforeach;
					} else {
						foreach ( $target['attrs'] as $id ) :
							$attr = $this->attr_label( $id );
							if ( ! $attr ) continue;
							printf(
								'<li data-group-id="%s"><span class="dashicons dashicons-menu"></span> %s</li>',
								esc_attr( $id ),
								esc_html( $attr )
							);
						endforeach;
					}
					?>
				</ul>
				<div class="order-actions">
					<button type="button" class="button button-primary" id="save-group-order"><?php esc_html_e('Save'); ?></button>
				</div>
			</div>

			<script>
			jQuery(function($) {
				$('#sortable-groups').sortable({
					placeholder: 'ui-state-highlight',
					opacity: 0.8,
					cursor: 'move',
				});

				$('#save-group-order').on('click', function() {
					var order = $('#sortable-groups').sortable('toArray', { attribute: 'data-group-id' });
					var $btn  = $(this);

					$btn.prop('disabled', true).text(<?php echo wp_json_encode( __( 'Saving…' ) ); ?>);

					$.post('<?php echo admin_url('admin-ajax.php'); ?>', {
						action:   'arvandwc_attrbiutes_save_group_order',
						target_id: <?php echo wp_json_encode( $target_id ); ?>,
						nonce:    <?php echo wp_json_encode( wp_create_nonce( 'save_group_order_nonce' ) ); ?>,
						order:    order,
						istemplate: <?php echo $is_template ? 1 : 0; ?>,
					}, function(response) {
						if ( ! response.success ) {
							alert(response.data || <?php echo wp_json_encode( __( 'Error saving order.', 'arvand' ) ); ?>);
							$btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Save' ) ); ?>);
						} else {
							window.parent.location.reload();
						}
					});
				});
			});
			</script>
		</body>
		</html>
		<?php
		wp_die();
	}

	public function save_group_order(): void {
		check_ajax_referer( 'save_group_order_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'arvand' ) );
		}

		$target_id		= sanitize_key( $_POST['target_id'] ?? '' );
		$order			= array_map( 'sanitize_text_field', $_POST['order'] ?? [] );
		$is_template	= boolval( $_POST['istemplate'] ?? false );

		if ( ! $target_id || empty( $order ) ) {
			wp_send_json_error( __( 'Invalid data.', 'arvand' ) );
		}

		if( $is_template ){
			$templates = $this->get_templates();
			if ( ! isset( $templates[ $target_id ] ) ) {
				wp_send_json_error( __( 'Template not found.', 'arvand' ) );
			}
			$allowed = array_map( 'sanitize_key', $templates[ $target_id ]['groups'] );
			$templates[ $target_id ]['groups'] = array_values( array_filter( $order, fn( $id ) => in_array( $id, $allowed, true ) ) );
			$this->save_templates( $templates );
		} else {
			$groups = self::get_groups();
			if ( ! isset( $groups[ $target_id ] ) ) {
				wp_send_json_error( __( 'Group not found.', 'arvand' ) );
			}
			$allowed = array_map( 'sanitize_text_field', $groups[ $target_id ]['attrs'] );
			$groups[ $target_id ]['attrs'] = array_values( array_filter( $order, fn( $id ) => in_array( $id, $allowed, true ) ) );
			update_option( self::OPTION_GROUPS, $groups );
		}

		wp_send_json_success();
	}

	public static function get_product_attribute_groups( array $product_attributes = [], int $product_id = 0 ) {
		$all_groups = self::get_groups();
		if ( empty( $all_groups ) || empty( $product_attributes ) ) {
			return false;
		}

		// Lookup: attribute id ('weight', 'dimensions', or taxonomy id) => [ group_id, position ].
		// First group wins when an attribute appears in several groups.
		$lookup = [];
		foreach ( $all_groups as $group_id => $group ) {
			foreach ( $group['attrs'] ?? [] as $pos => $attr_id ) {
				$lookup[ (string) $attr_id ] ??= [ $group_id, $pos ];
			}
		}

		$grouped  = [];
		$position = [];

		foreach ( $product_attributes as $key => $value ) {
			$name = str_replace( [ 'attribute_', 'pa_' ], '', $key );
			$id   = in_array( $name, [ 'weight', 'dimensions' ], true )
				? $name
				: (string) wc_attribute_taxonomy_id_by_name( $name );

			if ( ! isset( $lookup[ $id ] ) ) {
				continue;
			}

			[ $group_id, $pos ] = $lookup[ $id ];

			$grouped[ $group_id ][ $key ]  = $value;
			$position[ $group_id ][ $key ] = $pos;
		}

		if ( empty( $grouped ) ) {
			return false;
		}

		$display_groups = [];
		$grouped_keys   = [];

		foreach ( $all_groups as $group_id => $group ) {
			if ( ! isset( $grouped[ $group_id ] ) ) {
				continue;
			}

			$attrs = $grouped[ $group_id ];
			uksort( $attrs, fn( $a, $b ) => $position[ $group_id ][ $a ] <=> $position[ $group_id ][ $b ] );

			$display_groups[ $group_id ] = [
				'label' => $group['label'],
				'attrs' => $attrs,
				'order' => $group['attrs'] ?? [],
			];

			$grouped_keys += $attrs;
		}

		// Order groups by the template saved on the product, if any.
		$product_id = $product_id ?: (int) get_the_ID();
		$tpl_key    = $product_id ? (string) get_post_meta( $product_id, self::META_TEMPLATE, true ) : '';

		if ( $tpl_key ) {
			$templates = (array) get_option( self::OPTION_TEMPLATES, [] );
			$tpl_order = $templates[ $tpl_key ]['groups'] ?? [];

			if ( $tpl_order ) {
				$ordered = [];
				foreach ( $tpl_order as $gid ) {
					if ( isset( $display_groups[ $gid ] ) ) {
						$ordered[ $gid ] = $display_groups[ $gid ];
						unset( $display_groups[ $gid ] );
					}
				}
				$display_groups = $ordered + $display_groups;
			}
		}

		$ungrouped = array_diff_key( $product_attributes, $grouped_keys );
		if ( ! empty( $ungrouped ) ) {
			$display_groups['other'] = [
				'label' => __( 'Other', 'woocommerce' ),
				'attrs' => $ungrouped,
			];
		}

		return $display_groups;
	}
}