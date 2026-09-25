<?php
/**
 * WooCommerce Back In Stock Notifier
 *
 * Sends SMS on WooCommerce order status changes via configurable gateways.
 * Adds a "SMS Notifications" tab under WooCommerce → Settings.
 *
 * @package Arvand\Woocommerce
 */

namespace Arvand\Woocommerce;

use Exception;
use WP_Error;

defined( 'ABSPATH' ) || exit;

function bis_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bis_subscribers';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) DEFAULT NULL,
        date_subscribed datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY product_id (product_id),
        KEY email (email)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

class BIS {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bis_subscribers';

        // هوک‌های وردپرس
        add_action( 'wp_ajax_arvandwc_bis_notifier', [ $this, 'ajax_register' ] );
        add_action( 'wp_ajax_nopriv_arvandwc_bis_notifier', [ $this, 'ajax_register' ] );

		// هوک‌های تغییر موجودی
        add_action( 'woocommerce_product_set_stock_status', [ $this, 'stock_changed' ], 10, 3 );
        add_action( 'woocommerce_variation_set_stock_status', [ $this, 'stock_changed' ], 10, 3 );
        add_action( 'woocommerce_product_quick_edit_save', [ $this, 'quick_edit_save' ] );
        add_action( 'woocommerce_product_bulk_edit_save', [ $this, 'quick_edit_save' ] );

        // هوک‌های Action Scheduler
        add_action( 'bis_bulk_notify', [ $this, 'bulk_notify' ], 10, 2 );
        add_action( 'bis_send_notify', [ $this, 'send_notification' ], 10, 3 );

        // هوک‌های مدیریت و آمار
		add_action( 'woocommerce_product_options_inventory_product_data', [ $this, 'admin_stats' ] );
		add_filter( 'woocommerce_admin_report_columns', [ $this, 'analytics_columns' ], 10, 3 );
		add_filter( 'woocommerce_filter_products_export_columns', [ $this, 'export_columns' ] );
		add_filter( 'woocommerce_report_products_prepare_export_item', [ $this, 'export_item' ], 10, 2 );
    }

    public static function instance(): self {
		static $instance;
		return $instance ??= new self();
	}


    public function ajax_register() {
		global $current_user, $wpdb;
		try {
			if ( ! check_ajax_referer( 'wc_bis_signup', 'wc_bis_nonce', false ) ) {
				throw new Exception('درخواست نامعتبر');
			}

			$product_id = intval( $_POST['wc_bis_product_id'] ?? 0 );
			$email		= $current_user ? $current_user->user_email : sanitize_email( $_POST['wc_bis_email'] ?? '' );
			$phone		= $current_user ? $current_user->user_login : sanitize_text_field( $_POST['wc_bis_phone'] ?? '' );

			if ( ! $product_id || ! $phone ) {
				throw new Exception('اطلاعات کامل نیست');
			}

			$exists = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $this->table_name WHERE product_id = %d AND email = %s OR phone = %s", $product_id, $email, $phone) );

			if ( $exists ) {
				throw new Exception('قبلاً ثبت‌نام کرده‌اید');
			}

			$inserted = $wpdb->insert(
				$this->table_name,
				[
					'product_id'	=> $product_id,
					'email'			=> $email,
					'phone'			=> $phone
				],
				['%d', '%s', '%s']
			);

			if ( ! $inserted ) {
				throw new Exception('خطا در ثبت اطلاعات');
			}
			wp_send_json_success( 'ثبت شدید. به محض موجود شدن اطلاع می‌دهیم.' );
		} catch (Exception $e) {
			wp_send_json_error( $e->getMessage() );
		}
    }

    /**
     * تغییر وضعیت موجودی
     */
    public function stock_changed( $product_id, $stock_status, $product ) {
        if ( $stock_status !== 'instock' ) {
            return;
        }

        if ( ! get_post_meta( $product_id, '_bis_was_out_of_stock', true ) ) {
            return;
        }

        global $wpdb;
        $count = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $this->table_name WHERE product_id = %d", $product_id));

        if ( $count > 0 ) {
            as_schedule_single_action( time(), 'bis_bulk_notify', [ $product_id, $count ] );
            delete_post_meta( $product_id, '_bis_was_out_of_stock' );
        }
    }

    /**
     * پردازش تودهای اطلاع‌رسانی
     */
    public function bulk_notify( $product_id, $total ) {
        global $wpdb;

        $batch_size = 20;
        $offset = 0;
        $processed = 0;

        while ( $processed < $total ) {
            $subscribers = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $this->table_name WHERE product_id = %d LIMIT %d OFFSET %d", $product_id, $batch_size, $offset) );

            if ( empty( $subscribers ) ) {
                break;
            }

            foreach ( $subscribers as $sub ) {
                as_schedule_single_action(
                    time() + 2,
                    'bis_send_notify',
                    [ $sub->id, $product_id, $sub->email ]
                );
            }

            $processed += count( $subscribers );
            $offset += $batch_size;

            if ( $processed < $total ) {
                as_schedule_single_action(
                    time() + 10,
                    'bis_bulk_notify',
                    [ $product_id, $total ]
                );
                break;
            }
        }

        do_action( 'bis_bulk_done', $product_id, $processed );
    }

    /**
     * ارسال ایمیل به یک کاربر
     */
    public function send_notification( $subscriber_id, $product_id, $email ) {
        global $wpdb;

        $subscriber = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE id = %d",
            $subscriber_id
        ) );

        if ( ! $subscriber ) {
            return;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            $wpdb->delete( $this->table_name, [ 'id' => $subscriber_id ] );
            return;
        }

        $resubscribe_link = add_query_arg( [
            'bis_resubscribe' => 1,
            'product_id' => $product_id,
            'email' => urlencode( $email )
        ], home_url() );

        $subject = apply_filters( 'bis_subject', '✅ ' . $product->get_name() . ' موجود شد' );
        $message = apply_filters( 'bis_message',
            "سلام\n\nمحصول {$product->get_name()} موجود شد.\n" .
            "لینک خرید: " . get_permalink( $product_id ) . "\n\n" .
            "برای ثبت‌نام مجدد: $resubscribe_link"
        );

        wp_mail( $email, $subject, $message );

        if ( ! empty( $subscriber->phone ) ) {
            do_action( 'bis_sms', $subscriber->phone, $message, $product );
        }

        $wpdb->delete( $this->table_name, [ 'id' => $subscriber_id ] );
        do_action( 'bis_notified', $email, $product_id );
    }

    /**
     * ذخیره‌سازی سریع/تودهای
     */
    public function quick_edit_save( $product ) {
        if ( ! $product || ! $product->get_id() ) {
            return;
        }

        $product_id = $product->get_id();
        $status = $product->get_stock_status();
        $was_out = get_post_meta( $product_id, '_bis_was_out_of_stock', true );

        if ( $status === 'instock' && $was_out ) {
            $this->stock_changed( $product_id, 'instock', $product );
        } elseif ( $status === 'outofstock' && ! $was_out ) {
            global $wpdb;
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $this->table_name WHERE product_id = %d",
                $product_id
            ) );
            if ( $count > 0 ) {
                update_post_meta( $product_id, '_bis_was_out_of_stock', 'yes' );
            }
        }
    }

    /**
     * آمار در مدیریت محصول
     */
    public function admin_stats() {
        global $post, $wpdb;

        $product_id = $post->ID;
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $this->table_name WHERE product_id = %d",
            $product_id
        ) );

        echo '<div class="options_group">';
        woocommerce_wp_text_input( [
            'id'          => '_bis_waiting_count',
            'label'       => 'منتظران موجودی',
            'description' => 'تعداد کاربرانی که منتظر این محصول هستند',
            'value'       => $count,
            'custom_attributes' => [ 'readonly' => 'readonly' ]
        ] );
        echo '</div>';
    }

    /**
     * افزودن ستون به گزارش Analytics
     */
    public function analytics_columns( $columns, $context, $table ) {
        if ( 'products' !== $context ) {
            return $columns;
        }

        global $wpdb;
        $bis_table = $wpdb->prefix . 'bis_subscribers';

        $columns['bis_waiting_count'] = "(
            SELECT COUNT(*)
            FROM {$bis_table}
            WHERE {$bis_table}.product_id = {$table}.product_id
        ) AS bis_waiting_count";

        return $columns;
    }

    /**
     * افزودن عنوان ستون به CSV
     */
    public function export_columns( $columns ) {
        $columns['bis_waiting_count'] = 'تعداد منتظران موجودی';
        return $columns;
    }

    /**
     * نگاشت داده به خروجی CSV
     */
    public function export_item( $export_item, $item ) {
        $export_item['bis_waiting_count'] = $item['bis_waiting_count'] ?? 0;
        return $export_item;
    }
}
