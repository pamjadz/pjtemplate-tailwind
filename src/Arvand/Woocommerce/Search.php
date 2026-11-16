<?php
/**
 * WooCommerce Product Search
 *
 * SKU + multi-word title search with keyword rewriting, in-stock-first
 * ordering, and a JSON live-search AJAX endpoint.
 *
 * @package Arvand\Woocommerce
 */

namespace Arvand\Woocommerce;

use WP_Query;

defined( 'ABSPATH' ) || exit;

final class Search {
	const OPTION_KEYWORDS = 'arvandwc_searchreplace';
	const CACHE_GROUP     = 'arvandwc';

	protected static $instance;

	private ?string $search_order_case = null;

	protected function __construct() {
		add_action( 'admin_menu', [ $this, 'register_settings_subpage' ], 99 );

		add_filter( 'posts_search',  [ $this, 'product_search_modify' ],  99, 2 );
		add_filter( 'posts_join',    [ $this, 'product_search_join' ],    99, 2 );
		add_filter( 'posts_orderby', [ $this, 'product_search_orderby' ], 99, 2 );

		add_action( 'wp_ajax_wc_live_search_products',        [ $this, 'ajax_livesearch_callback' ], 999 );
		add_action( 'wp_ajax_nopriv_wc_live_search_products', [ $this, 'ajax_livesearch_callback' ], 999 );
	}

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function is_product_search( WP_Query $wp_query ): bool {
		if ( ! $wp_query->get( 's' ) ) {
			return false;
		}

		$post_type = (array) $wp_query->get( 'post_type' );

		return in_array( 'product', $post_type, true );
	}

	/* -------- Admin: keyword rewrite settings -------- */

	public function register_settings_subpage() {
		add_submenu_page(
			'woocommerce',
			'Arvand Live Search',
			'کلید‌های جستجو',
			'manage_options',
			'livesearch',
			[ $this, 'callback_settings_subpage' ],
			10
		);
	}

	private function get_tr_html( array $item = [] ): string {
		return sprintf(
			'<th scope="row" class="check-column"><button type="button" class="removetarget button-link" style="color:#cc5050"><span class="dashicons dashicons-trash"></span></button></th>
			<td><input type="text" class="regular-text" style="width:100%%" name="keys[]" value="%s"></td>
			<td><input type="text" class="regular-text" style="width:100%%" name="target[]" value="%s"></td>',
			esc_attr( $item['keys'] ?? '' ),
			esc_attr( $item['target'] ?? '' )
		);
	}

	public function callback_settings_subpage() {
		if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['submit'], self::OPTION_KEYWORDS ) ) {
			$keys    = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['keys'] ?? [] ) ) );
			$targets = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['target'] ?? [] ) ) );
			$data    = [];

			foreach ( $keys as $i => $keyword ) {
				$keyword = trim( $keyword );
				$target  = trim( $targets[ $i ] ?? '' );

				if ( $keyword === '' || $target === '' ) {
					continue;
				}

				$keyword = array_unique( array_filter( array_map( 'trim', explode( '|', $keyword ) ) ) );
				$data[]  = [ 'keys' => implode( '|', $keyword ), 'target' => $target ];
			}

			update_option( self::OPTION_KEYWORDS, $data );
		}

		$data = get_option( self::OPTION_KEYWORDS ) ?: [ [ 'keys' => '', 'target' => '' ] ];
		?>
		<form action="#" method="post" class="wrap">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column"></td>
						<th scope="col" class="manage-column">کلمات کلیدی</th>
						<th scope="col" class="manage-column">تبدیل به</th>
					</tr>
				</thead>
				<tbody id="targetlists">
					<?php foreach ( $data as $row ) echo '<tr>' . $this->get_tr_html( $row ) . '</tr>'; ?>
				</tbody>
				<tfoot>
					<td colspan="3">
						<button type="button" class="button" data-row="<?php echo esc_attr( $this->get_tr_html() ); ?>">افزودن</button>
						<button type="submit" name="submit" value="<?php echo wp_create_nonce( self::OPTION_KEYWORDS ); ?>" class="button button-primary">ذخیره تغییرات</button>
					</td>
				</tfoot>
			</table>
		</form>
		<script>
			jQuery(document).ready(function ($) {
				$(document).on('click', '[data-row]', function () {
					$('#targetlists').append(`<tr>${$(this).data('row')}</tr>`);
				});
				$(document).on('click', '.removetarget', function (e) {
					e.preventDefault();
					if (confirm('آیا از حذف این لیست مطمئن هستید؟')) $(this).parents('tr').remove();
				});
			});
		</script>
		<?php
	}

	/* -------- Query filters -------- */

	/**
	 * Rewrite the search term when it matches a configured keyword.
	 */
	private function apply_filter_to_query( string $query ): string {
		static $patterns = null;

		$query = trim( $query );
		if ( $query === '' ) {
			return $query;
		}

		if ( $patterns === null ) {
			$patterns = (array) get_option( self::OPTION_KEYWORDS, [] );
		}

		$escaped = preg_quote( $query, '/' );

		foreach ( $patterns as $item ) {
			foreach ( explode( '|', $item['keys'] ) as $key ) {
				if ( preg_match( '/' . $escaped . '/i', $key ) ) {
					return $item['target'];
				}
			}
		}

		return $query;
	}

	public function product_search_modify( $search, $wp_query ) {
		global $wpdb;

		if ( ! $this->is_product_search( $wp_query ) ) {
			return $search;
		}

		$search_term = sanitize_text_field( $wp_query->get( 's' ) );
		if ( $search_term === '' ) {
			return $search;
		}

		// Exact SKU match wins outright.
		$product_id = wc_get_product_id_by_sku( $search_term );
		if ( $product_id ) {
			$this->search_order_case = null;
			return " AND ({$wpdb->posts}.ID = " . absint( $product_id ) . ')';
		}

		// Full phrase (after keyword rewrite) first, then individual words.
		$terms = [ $this->apply_filter_to_query( $search_term ) ];

		$words = array_unique( array_filter( preg_split( '/\s+/', $search_term ) ) );
		if ( count( $words ) > 1 ) {
			$terms = array_merge( $terms, $words );
		}

		$clauses     = [];
		$order_cases = [];

		foreach ( array_values( array_unique( $terms ) ) as $i => $term ) {
			$like          = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like( $term ) . '%' );
			$clauses[]     = $like;
			$order_cases[] = "WHEN {$like} THEN " . ( $i + 1 );
		}

		$this->search_order_case = 'CASE ' . implode( ' ', $order_cases ) . ' ELSE ' . ( count( $order_cases ) + 1 ) . ' END';

		return ' AND (' . implode( ' OR ', $clauses ) . ')';
	}

	public function product_search_join( $join, $wp_query ) {
		global $wpdb;

		if ( ! $this->is_product_search( $wp_query ) ) {
			return $join;
		}

		if ( strpos( $join, 'stock_lookup' ) === false ) {
			$join .= " LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup AS stock_lookup ON ({$wpdb->posts}.ID = stock_lookup.product_id) ";
		}

		return $join;
	}

	public function product_search_orderby( $orderby, $wp_query ) {
		if ( ! $this->is_product_search( $wp_query ) ) {
			return $orderby;
		}

		$relevance = $this->search_order_case ? $this->search_order_case . ' ASC,' : '';

		return "
			CASE WHEN stock_lookup.stock_status = 'instock' THEN 0 ELSE 1 END ASC,
			{$relevance}
			{$orderby}
		";
	}

	/* -------- AJAX live search (JSON) -------- */

	public function ajax_livesearch_callback() {
		$query = sanitize_text_field( wp_unslash( $_GET['query'] ?? '' ) );

		if ( $query === '' || mb_strlen( $query ) < 2 ) {
			wp_send_json_error( [ 'message' => 'عبارت جستجو کوتاه است' ], 400 );
		}

		$cache_key = 'livesearch_' . md5( mb_strtolower( $query ) );
		$payload   = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $payload ) {
			$payload = $this->build_livesearch_payload( $query );
			wp_cache_set( $cache_key, $payload, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );
		}

		wp_send_json_success( $payload );
	}

	/**
	 * @return array{query:string, results:array, product_count:int, more_url:string|null}
	 */
	private function build_livesearch_payload( string $query ): array {
		$products = ( new WP_Query( [
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => 5,
			's'                      => $query,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] ) )->posts;

		$results = [];

		foreach ( $products as $product_id ) {
			$title = get_the_title( $product_id );

			$results[] = [
				'type'  => 'products',
				'name'  => $title,
				'url'   => get_permalink( $product_id ),
				'exact' => 0 === strcasecmp( $title, $query ),
			];
		}

		foreach ( [ 'categories' => 'product_cat', 'brands' => 'product_brand' ] as $type => $taxonomy ) {
			$terms = get_terms( [
				'taxonomy'   => $taxonomy,
				'search'     => $query,
				'number'     => 5,
				'hide_empty' => true,
			] );

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}

				$results[] = [
					'type'  => $type,
					'name'  => $term->name,
					'url'   => $link,
					'exact' => 0 === strcasecmp( $term->name, $query ),
				];
			}
		}

		// Exact matches first, then categories > brands > products.
		$order = [ 'categories' => 1, 'brands' => 2, 'products' => 3 ];
		usort( $results, static function ( $a, $b ) use ( $order ) {
			return ( $b['exact'] <=> $a['exact'] ) ?: ( $order[ $a['type'] ] <=> $order[ $b['type'] ] );
		} );

		$product_count = count( $products );

		return [
			'query'			=> $query,
			'results'		=> $results,
			'product_count'	=> $product_count,
			'more_url'		=> $product_count > 4 ? add_query_arg( [ 's' => $query, 'post_type' => 'product' ], wc_get_page_permalink( 'shop' ) ) : null,
		];
	}
}
