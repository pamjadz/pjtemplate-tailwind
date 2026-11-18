
<?php

defined( 'ABSPATH' ) || exit;

class Arvand_WC_Product_Variable extends WC_Product_Variable {

    private const CACHE_GROUP = 'arvandwc_default_variations';
    private const CACHE_TTL   = HOUR_IN_SECONDS * 6;

    private static array $request_cache = [];

    /**
     * Register hooks.
     */
    public static function boot(): void {
        add_filter( 'woocommerce_product_class', [ self::class, 'product_class' ], 20, 2 );
        add_filter( 'woocommerce_product_get_default_attributes', [ self::class, 'filter_default_attributes' ], 20, 2);
        foreach ( 
            ['woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation', 'woocommerce_delete_product_variation', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status'] as $hook ) {
            add_action( $hook, [ self::class, 'invalidate_cache' ], 20, 1 );
        }
    }

    /**
     * Use custom variable product class.
     */
    public static function product_class( $class, $type ) {
        return 'variable' === $type ? self::class : $class;
    }

    /**
     * Cache key.
     */
    private function cache_key(): string {
        return 'best_variation_' . $this->get_id();
    }

    /**
     * Cache version/context.
     */
    private function cache_version(): string {

        $prefix = WC_Cache_Helper::get_cache_prefix(
            'product_' . $this->get_id()
        );

        $context = (string) apply_filters(
            'arvand_best_variation_cache_context',
            '',
            $this
        );

        $defaults = $this->get_default_attributes( 'edit' );

        return md5( wp_json_encode( [
            $prefix,
            $context,
            $defaults,
        ] ) );
    }

    /**
     * Read cached selected variation ID.
     *
     * @return int|null Null means cache miss.
     */
    private function get_cached_variation_id(): ?int {
        $key     = $this->cache_key();
        $version = $this->cache_version();
        if ( isset( self::$request_cache[ $key ] ) && self::$request_cache[ $key ]['version'] === $version ) {
            return (int) self::$request_cache[ $key ]['id'];
        }
        $cached = wp_using_ext_object_cache() ? wp_cache_get( $key, self::CACHE_GROUP ) : get_transient( $key );

        if ( is_array( $cached ) && isset( $cached['version'], $cached['id'] ) && $cached['version'] === $version ) {
            self::$request_cache[ $key ] = $cached;
            return (int) $cached['id'];
        }

        return null;
    }

    /**
     * Cache selected variation ID.
     */
    private function cache_variation_id( int $id ): void {

        $key = $this->cache_key();

        $data = [
            'version' => $this->cache_version(),
            'id'      => $id,
        ];

        self::$request_cache[ $key ] = $data;

        if ( wp_using_ext_object_cache() ) {
            wp_cache_set(
                $key,
                $data,
                self::CACHE_GROUP,
                self::CACHE_TTL
            );
        } else {
            set_transient( $key, $data, self::CACHE_TTL );
        }
    }

    /**
     * Check if variation is available.
     */
    private static function is_eligible( $variation ): bool {

        return $variation instanceof WC_Product_Variation
            && $variation->exists()
            && 'publish' === $variation->get_status()
            && $variation->is_visible()
            && $variation->is_purchasable()
            && $variation->is_in_stock()
            && $variation->variation_is_active();
    }

    /**
     * Find and cache preferred variation ID.
     */
    public function get_default_variation_id(): int {

        $cached = $this->get_cached_variation_id();

        if ( null !== $cached ) {
            return $cached;
        }

        $defaults = $this->get_default_attributes( 'edit' );

        /**
         * First: manually configured default variation.
         */
        if ( ! empty( $defaults ) ) {

            $match = [];

            foreach ( $defaults as $key => $value ) {
                if ( '' !== $value ) {
                    $match[ 'attribute_' . $key ] = $value;
                }
            }

            if ( ! empty( $match ) ) {

                $store = WC_Data_Store::load( 'product' );

                $id = (int) $store->find_matching_product_variation(
                    $this,
                    $match
                );

                if ( $id ) {
                    $variation = wc_get_product( $id );

                    if ( self::is_eligible( $variation ) ) {
                        $this->cache_variation_id( $id );
                        return $id;
                    }
                }
            }
        }

        /**
         * Second: use WooCommerce cached variation prices.
         */
        $prices = $this->get_variation_prices( false );

        $current = $prices['price'] ?? [];
        $regular = $prices['regular_price'] ?? [];
        $sale    = $prices['sale_price'] ?? [];

        if ( empty( $current ) ) {
            $this->cache_variation_id( 0 );
            return 0;
        }

        $candidates = [];

        foreach ( $current as $id => $price ) {

            if ( '' === $price || ! is_numeric( $price ) ) {
                continue;
            }

            $price = (float) $price;

            if ( $price < 0 ) {
                continue;
            }

            $reg = (float) ( $regular[ $id ] ?? 0 );
            $sale_price = (float) ( $sale[ $id ] ?? 0 );

            $discount = 0.0;

            if (
                $reg > 0 &&
                $sale_price > 0 &&
                $reg > $sale_price &&
                abs( $price - $sale_price ) < 0.00001
            ) {
                $discount = ( $reg - $sale_price ) / $reg;
            }

            $candidates[] = [
                'id'       => (int) $id,
                'price'    => $price,
                'discount' => $discount,
            ];
        }

        /**
         * Sort:
         * 1. Highest discount
         * 2. Lowest positive price
         * 3. First available (including free)
         */
        usort( $candidates, static function( $a, $b ) {

            if ( $a['discount'] !== $b['discount'] ) {
                return $b['discount'] <=> $a['discount'];
            }

            $a_price = $a['price'] > 0
                ? $a['price']
                : PHP_FLOAT_MAX;

            $b_price = $b['price'] > 0
                ? $b['price']
                : PHP_FLOAT_MAX;

            if ( $a_price !== $b_price ) {
                return $a_price <=> $b_price;
            }

            return $a['id'] <=> $b['id'];
        } );

        /**
         * Load candidate objects only until a valid match.
         */
        foreach ( $candidates as $candidate ) {

            $variation = wc_get_product( $candidate['id'] );

            if ( ! self::is_eligible( $variation ) ) {
                continue;
            }

            $this->cache_variation_id( $variation->get_id() );

            return $variation->get_id();
        }

        $this->cache_variation_id( 0 );

        return 0;
    }

    /**
     * Get selected variation object.
     *
     * @return WC_Product_Variation|false
     */
    public function get_default_variation() {

        $id = $this->get_default_variation_id();

        if ( ! $id ) {
            return false;
        }

        $variation = wc_get_product( $id );

        return $variation instanceof WC_Product_Variation
            ? $variation
            : false;
    }

    /**
     * Auto-select product default attributes.
     */
    public static function filter_default_attributes(
        $attributes,
        $product
    ) {

        if ( ! $product instanceof self ) {
            return $attributes;
        }

        $variation = $product->get_default_variation();

        if ( ! $variation ) {
            return $attributes;
        }

        $selected = $variation->get_attributes();

        // Preserve manually selected values for "Any" attributes.
        foreach ( $selected as $key => $value ) {

            if ( '' === $value ) {
                unset( $selected[ $key ] );
            }
        }

        return array_merge( $attributes, $selected );
    }

    /**
     * Regular price.
     */
    public function get_regular_price( $context = 'view' ) {

        $variation = $this->get_default_variation();

        return $variation
            ? $variation->get_regular_price( $context )
            : parent::get_regular_price( $context );
    }

    /**
     * Sale price.
     */
    public function get_sale_price( $context = 'view' ) {

        $variation = $this->get_default_variation();

        return $variation
            ? $variation->get_sale_price( $context )
            : parent::get_sale_price( $context );
    }

    /**
     * Active price.
     */
    public function get_price( $context = 'view' ) {

        $variation = $this->get_default_variation();

        return $variation
            ? $variation->get_price( $context )
            : parent::get_price( $context );
    }

    /**
     * Sale status.
     */
    public function is_on_sale( $context = 'view' ) {

        $variation = $this->get_default_variation();

        return $variation
            ? $variation->is_on_sale( $context )
            : parent::is_on_sale( $context );
    }

    /**
     * Formatted price HTML.
     */
    public function get_price_html( $price = '' ) {

        $variation = $this->get_default_variation();

        if ( ! $variation ) {
            return parent::get_price_html( $price );
        }

        $price = $variation->get_price_html();

        return apply_filters( 'woocommerce_variable_price_html', $price, $this);
    }

    /**
     * Invalidate selected variation cache.
     */
    public static function invalidate_cache( $product ): void {

        $id = $product instanceof WC_Product
            ? $product->get_id()
            : (int) $product;

        if ( ! $id ) {
            return;
        }

        $parent_id = wp_get_post_parent_id( $id );
        $id = $parent_id ?: $id;

        $key = 'best_variation_' . $id;

        unset( self::$request_cache[ $key ] );

        wp_cache_delete( $key, self::CACHE_GROUP );
        delete_transient( $key );
    }
}

/**
 * Initialize after WooCommerce loads.
 */
add_action( 'woocommerce_init', [
    Arvand_WC_Product_Variable::class,
    'boot',
] );
