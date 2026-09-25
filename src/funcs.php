<?php
/**
 * Helper Functions for development
 * 
 * @author Pouriya Amjadzadeh
 * @version 2.5
 */

defined('ABSPATH') || exit;

if( ! function_exists('console_log') ) {
	function console_log( ...$args ) {
		if( empty( $args ) ) {
			return;
		}
	
		// Use Query Monitor if available
		if( class_exists('QM') && ! DOING_AJAX ) {
			foreach( $args as $log ) {
				do_action( 'qm/debug', $log );
			}
			return;
		}
		
		// Log to file
		if( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );
			$caller = isset( $backtrace[0] ) ? basename( $backtrace[0]['file'] ) . ':' . $backtrace[0]['line'] : 'unknown';
			$log_message = date( 'Y-m-d H:i:s' ) . " [{$caller}]" . PHP_EOL;
			foreach( $args as $log ) {
				$log_message .= print_r( $log, true ) . PHP_EOL;
			}
			error_log( $log_message );
		}
	}
}

if( ! function_exists('is_dev') ){
	function is_dev(){
		global $current_user;
		$username	= $current_user->user_login ?? '';
		$useremail	= $current_user->user_email ?? '';

		return in_array( strtolower( $username ), ['09120747280', '09137377857', 'arvandec', 'arvand'] ) || in_array( strtolower( $useremail ), ['info@arvandec.com', 'arvandec@gmail.com', 'p.amjadzadeh@gmail.com'] );
	}
}

if( ! function_exists('get_user_ip') ) {
	/**
	 * get current user ip
	 * 
	 * @return string user ip4 or ip6
	 */
	function get_user_ip( $allow_private = false ) {
		static $server_keys = [
			'HTTP_CLIENT_IP', 
			'HTTP_X_FORWARDED_FOR', 
			'HTTP_X_FORWARDED', 
			'HTTP_X_CLUSTER_CLIENT_IP', 
			'HTTP_FORWARDED_FOR', 
			'HTTP_FORWARDED', 
			'REMOTE_ADDR'
		];
		
		foreach ( $server_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
				
				$flags = FILTER_FLAG_NO_RES_RANGE;
				if ( ! $allow_private ) {
					$flags |= FILTER_FLAG_NO_PRIV_RANGE;
				}
				
				if ( filter_var( $ip, FILTER_VALIDATE_IP, $flags ) ) {
					return sanitize_text_field( $ip );
				}
			}
		}
		
		return $allow_private ? '127.0.0.1' : '0.0.0.0';
	}
}

if( ! function_exists('has_user_role') ) {
	/**
	 * Check if user has one or more specific roles
	 * 
	 * @param string|array $role Single role or array of roles to check
	 * @param int|WP_User|null $user_id User ID, WP_User object, or null for current user
	 * @param string $mode 'any' or 'all' - check if user has any or all of the roles
	 * @return bool True if user has any of the specified roles
	 */
	function has_user_role( $role, $user_id = null, $mode = 'any' ) {
		$user = null;
		if( $user_id instanceof WP_User ) {
			$user = $user_id;
		} elseif( $user_id ) {
			$user = get_user_by( 'ID', (int) $user_id );
		} else {
			$user = wp_get_current_user();
		}
		
		if( ! $user || ! $user->exists() ) {
			return false;
		}
		
		$required_roles = (array) $role;
		$user_roles = (array) $user->roles;
		
		if( empty( $required_roles ) ) {
			return false;
		}
		
		if( $mode === 'all' ) {
			return ! array_diff( $required_roles, $user_roles );
		} else {
			return ! empty( array_intersect( $required_roles, $user_roles ) );
		}
	}
}

if( ! function_exists('current_page_url') ) {
	function current_page_url() {
		$current_url = home_url( add_query_arg( null, null ) );	
		return esc_url( $current_url );
	}
}

if( ! function_exists('clamp') ) {
	/**
	 * Clamp/Constrain a number between minimum and maximum values
	 * 
	 * @param int|float $number The number to clamp
	 * @param int|float $min Minimum allowed value
	 * @param int|float|null $max Maximum allowed value (null for no upper bound)
	 * @return int|float The clamped number (same type as input when possible)
	 */
    function clamp( $number, $min, $max = null ) {
		$original_type = gettype( $number );
		$number = (float) $number;
		$min = (float) $min;

        $number = max( $min, $number );
        
		if( ! is_null( $max ) ) {
			$max = (float) $max;
			$number = min( $max, $number );
		}
        
		if( $original_type === 'integer' && $number == (int) $number ) {
            return (int) $number;
        }
        
        return $number;
    }
	function filter_min_max_number( $number, $min, $max = null ) {
		_deprecated_function( __FUNCTION__, '1.0.0', 'clamp' );
		return clamp( $number, $min, $max = null );
	}
}

if( ! function_exists('sanitize_number_field') ){
	/**
	 * Convert a non-english number to english
	 * 
	 * @param string|number $number The number to clamp
	 * @return string|number|false converted string
	 */
	function sanitize_number_field( $number = '' ) {
		if( ! is_scalar( $number ) ) {
			return false;
		}
		
		$persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
		$arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
		$english = range(0, 9);
		
		$number = str_replace($persian, $english, (string) $number);
		$number = str_replace($arabic, $english, $number);

		return ($number === '' || $number === null) ? false : $number;
	}
}

if ( ! function_exists('sanitize_phone_ir') ) {
	function sanitize_phone_ir( $number = '' ) {
		$number = sanitize_number_field( $number );
		if ( ! $number ) {
			return false;
		}
		
		$number = preg_replace( '/[^0-9]|^(98|\+98|0098)/', '', $number );
		$number = preg_replace( '/^9/', '09', $number );
		return preg_match( '/^09[0-9]{9}$/', $number ) ? $number : false;
	}
}

if( ! function_exists('sanitize_natid_ir') ) {
	function sanitize_natid_ir( $code = '' ) {
		if( ! is_string( $code ) && ! is_numeric( $code ) ) {
			return false;
		}

		$code = preg_replace( '/[^0-9]/', '', sanitize_number_field( (string) $code ) );
		
		if( strlen( $code ) !== 10 ) {
			return false;
		}
		
		if( $code[0] == $code[1] && preg_match( '/^(\d)\1{9}$/', $code ) ) {
			return false;
		}
		
		$sum = 0;
		for( $i = 0; $i < 9; $i++ ) {
			$sum += ( $code[$i] * (10 - $i) );
		}
		
		$remainder = $sum % 11;
		$control = $code[9];

		$valid = ( $remainder < 2 && $control == $remainder ) || ( $remainder >= 2 && $control == (11 - $remainder) );

		return $valid ? $code : false;
	}
}

if( ! function_exists('natid_exists') ){
	function natid_exists( $natid = '' ): boolean {
		$meta	= 'natid';
		$natid	= sanitize_natid_ir( $natid );
		if( $natid ){
			global $wpdb;
			$user_id = $wpdb->get_var( $wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", $meta, $natid) );
			return $user_id ? absint( $user_id ) : false;
		}
		return false;
	}
}

if( ! function_exists('wp_enqueue') ){
	function wp_enqueue( string $handle ){
		wp_enqueue_script($handle);
		wp_enqueue_style($handle);
	}
}

if( ! function_exists('get_related_posts') ) {
	function get_related_posts( $numbers = 5, $post_id = null, $terms = ['category', 'post_tag'], $args = [] ) {
		if( ! $post_id ) {
			$post_id = get_the_ID();
		}
		
		if( ! $post_id ) {
			return false;
		}
		
		$cache_key = md5( serialize( [ $numbers, $post_id, $terms, $args ] ) );
		$cached_result = wp_cache_get( $cache_key, 'related_posts' );
		
		if( false !== $cached_result ) {
			return $cached_result;
		}
		
		$args = wp_parse_args( $args, [
			'post_type'				=> get_post_type( $post_id ),
			'post__not_in'			=> [ $post_id ],
			'posts_per_page'		=> $numbers,
			'ignore_sticky_posts'	=> true,
			'no_found_rows'			=> true,
		]);
		
		$tax_queries = [];
		
		if( ! empty( $terms ) && is_array( $terms ) ) {
			foreach( $terms as $taxonomy ) {
				$post_terms = get_the_terms( $post_id, $taxonomy );
				if( ! empty( $post_terms ) && ! is_wp_error( $post_terms ) ) {
					$term_ids = wp_list_pluck( $post_terms, 'term_id' );
					$tax_queries[] = [
						'taxonomy'	=> $taxonomy,
						'terms'		=> $term_ids,
						'field'		=> 'term_id',
						'operator'	=> 'IN',
					];
				}
			}
		}
		
		if( ! empty( $tax_queries ) ) {
			if( count( $tax_queries ) > 1 ) {
				$args['tax_query'] = array_merge( ['relation' => 'OR'], $tax_queries );
			} else {
				$args['tax_query'] = $tax_queries;
			}
		}
		
		$query = new WP_Query( $args );
		wp_cache_set( $cache_key, $query, 'arvand', (HOUR_IN_SECONDS * 12) );
		return $query;
	}
}

if( ! function_exists('get_primary_term') ) {
	function get_primary_term( $post_id = null, $taxonomy = 'category' ) {
		$primary = null;

		if( ! $post_id ) {
			$post_id = get_the_ID();
		}
		
		if( ! $post_id ) {
			return false;
		}

		if( class_exists('WPSEO_Primary_Term') ) {
			$primary_term_obj = new WPSEO_Primary_Term( $taxonomy, $post_id );
			$primary_term_id = $primary_term_obj->get_primary_term();			
			if( $primary_term_id ) {
				$primary = get_term( $primary_term_id, $taxonomy );
			}
		}
		
		if( ! $primary && class_exists('RankMath') ) {
			$meta_key = 'rank_math_primary_' . $taxonomy;
			$primary_term_id = get_post_meta( $post_id, $meta_key, true );
			if( $primary_term_id ) {
				$primary = get_term( $primary_term_id, $taxonomy );
			}
		}
		
		if( ! $primary && defined( 'AIOSEO_VERSION' ) ) {
			$primary_term_id = get_post_meta( $post_id, '_aioseo_primary_term_' . $taxonomy, true );
			if( $primary_term_id ) {
				$primary = get_term( $primary_term_id, $taxonomy );
			}
		}

		if( ! $primary ) {
			$terms = wp_get_post_terms( $post_id, $taxonomy );
			if( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach( $terms as $term ) {
					if( $term->parent == 0 ) {
						$primary = $term;
						break;
					}
				}
				if( ! $primary ) {
					$primary = reset( $terms );
				}
			}
		}
		
		if( $primary && ! is_wp_error( $primary ) ) {
			return $primary;
		}
		
		return false;
	}
}

if( ! function_exists('get_estimated_reading') ) {
	function get_estimated_reading( $post_id = null, $wpm = 180 ) {
		if( ! $post_id ) {
			$post_id = get_the_ID();
			if( ! $post_id ) return 0;
		}
		
		$content = get_post_field( 'post_content', $post_id );
		if( empty( $content ) ) return 0;
		
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content );
		$words = str_word_count( $content, 0 );
		$image_count = substr_count( strtolower( get_post_field( 'post_content', $post_id ) ), '<img ' );
		$words += ceil( $image_count * 10 * ($wpm / 60) ); // 15 seconds per image

		if( $words <= 0 ) return 0;
		
		return ceil( $words / $wpm );
	}
}

if( ! function_exists('wp_get_media_image_sizes') ){
	function wp_get_media_image_sizes( $size = '' ) {
        static $sizes = null;

        if( is_null( $sizes ) ) {
			$sizes = [];
			$default_sizes = ['thumbnail', 'medium', 'medium_large', 'large'];
            
			foreach( $default_sizes as $_size ) {
				$width	= get_option( "{$_size}_size_w" );
				$height	= get_option( "{$_size}_size_h" );
				$crop	= get_option( "{$_size}_crop" );
				if( $width && $height ) {
					$sizes[$_size] = [
						'width'   => (int) $width,
						'height'  => (int) $height,
						'crop'    => (bool) $crop,
					];
				}
            }

			$additional_sizes = wp_get_additional_image_sizes();
			
			foreach( $additional_sizes as $_size => $data ) {
				$sizes[$_size] = [
					'width'   => (int) $data['width'],
					'height'  => (int) $data['height'],
					'crop'    => (bool) ($data['crop'] ?? false),
				];
			}
            
            ksort( $sizes );
        }
        
        if( $size ) {
            return isset( $sizes[$size] ) ? $sizes[$size] : false;
        }
        
        return $sizes;
    }
}

if( ! function_exists('placeholder_image') ){
	/**
     * Generate a placeholder image as data URI using SVG
     * 
     * @param array|int $size Width and height [width, height] or single number for square
     * @param string|null $bgcolor Background color (CSS color value)
     * @return string Data URI for placeholder image
     */
	function placeholder_image( $size = [], $bgcolor = null ){
		if( is_numeric( $size ) ) {
			$size = [$size, $size];
		} elseif( empty( $size ) ) {
			$size = [1, 1];
		}
		$size = array_map( 'absint', $size );
        $size[0] = max( 1, $size[0] );
        $size[1] = max( 1, $size[1] );

		if( ! $bgcolor ){
			$bgcolor = '#eef0f5';
		}

		$svg = sprintf( '<svg viewBox="0 0 %1$s %2$s" width="%1$s" height="%2$s" xmlns="http://www.w3.org/2000/svg"><rect width="100%%" height="100%%" fill="%3$s"/></svg>', $size[0], $size[1], $bgcolor );
		return 'data:image/svg+xml;base64,'.base64_encode( $svg );
	}
}

if( ! function_exists('the_default_thumbnail') ){
	/**
	 * Retrieves the post thumbnail or a default placeholder image if none exists
	 * 
	 * @param string|array|int $size	Image size: registered name (string) OR custom dimensions [width, height] (array) OR square size in pixels (int)
	 * @param int|null         $post_id	Post ID. Defaults to current post ID if null
	 * @param array            $attr	Additional HTML attributes for the img tag
	 * @return string HTML img tag for the thumbnail or placeholder
	 */
	function get_default_thumbnail( $size = 'thumbnail', $post_id = null, $attr = [] ) {
		if( ! $post_id ) $post_id = get_the_ID();
		
		if( has_post_thumbnail( $post_id ) ) {
			return get_the_post_thumbnail( $post_id, $size, $attr );
		}
		
		$attr = wp_parse_args( $attr, [
			'src'		=> null,
			'alt'		=> null,
			'width'		=> 1,
			'height'	=> 1,
			'loading'	=> 'lazy',
			'decoding'	=> 'async',
			'color'		=> null,
			'class'		=> 'default-thumbnail',
		]);
		

		if( is_numeric( $size ) ) {
			$attr['width'] = (int) $size;
			$attr['height'] = (int) $size;
		} elseif( is_string( $size ) && function_exists('wp_get_media_image_sizes') ) {
			$size_data = wp_get_media_image_sizes( $size );
			if( is_array( $size_data ) ) {
				$attr['width'] = $size_data['width'];
				$attr['height'] = $size_data['height'];
			}
		} elseif( is_array( $size ) ) {
			$attr['width'] = isset( $size[0] ) ? (int) $size[0] : 1;
			$attr['height'] = isset( $size[1] ) ? (int) $size[1] : $attr['width'];
		}

		$attr['width'] = max( 1, $attr['width'] );
		$attr['height'] = max( 1, $attr['height'] );
		
		$attr['src'] = placeholder_image( [ $attr['width'], $attr['height'] ], $attr['color'] );
		$attr['alt'] = $attr['alt'] ?: get_the_title( $post_id );
		
		unset( $attr['color'] );
		

		$html = '<img';
		foreach ( $attr as $name => $value ) {
			if( $value !== null && $value !== '' ) {
				$html .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
			}
		}
		$html .= ' />';
		
		return $html;
	}

	function the_default_thumbnail( $size = 'thumbnail', $attr = [], $post_id = null ){
		echo get_default_thumbnail( $size, $post_id, $attr );
	}
}

if ( ! function_exists('get_arvand_cache') ) {
	/**
	 * Get cached data with unified interface
	 *
	 * @param string|int $key   Cache key
	 * @param string     $group Cache group (ignored for transients)
	 * @param bool       $force Force refresh (ignored for transients)
	 * @param mixed      $found Reference to store whether key was found
	 * @return mixed Cached value or false if not found
	 */
	function get_arvand_cache( string|int $key, string $group = '', bool $force = false, mixed &$found = null ): mixed {
		$key = (string) $key;
		
		if ( wp_using_ext_object_cache() ) {
			$value = wp_cache_get( $key, $group, $force, $found );
			return $value;
		}
		
		// Transient fallback
		$value = get_transient( $key );
		$found = ( false !== $value );
		
		return $value;
	}
}

if ( ! function_exists('set_arvand_cache') ) {
	/**
	 * Set cached data with unified interface
	 *
	 * @param string|int $key      Cache key
	 * @param mixed      $data     Data to cache
	 * @param string     $group    Cache group (ignored for transients)
	 * @param int        $expire   Expiration time in seconds (default: 0 = no expiry)
	 * @return bool True on success, false on failure
	 */
	function set_arvand_cache( string|int $key, mixed $data, string $group = '', int $expire = 0 ): bool {
		$key = (string) $key;
		
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_set( $key, $data, $group, $expire );
		}
		
		return set_transient( $key, $data, $expire );
	}
}

if ( ! function_exists('delete_arvand_cache') ) {
	/**
	 * Delete cached data with unified interface
	 *
	 * @param string|int $key   Cache key
	 * @param string     $group Cache group (ignored for transients)
	 * @return bool True on success, false on failure
	 */
	function delete_arvand_cache( string|int $key, string $group = '' ): bool {
		$key = (string) $key;
		
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_delete( $key, $group );
		}
		
		return delete_transient( $key );
	}
}

if( ! function_exists('the_breadcrumbs') ){
	function the_breadcrumbs( $custom_class = '', $args = [] ) {
		$default = apply_filters( 'the_breadcrumbs_args', [
			'force_theme' => false,
			'separator'   => '/',
			'before'      => sprintf(
				'<nav id="breadcrumbs" class="%s" aria-label="فهرست راهنما">',
				esc_attr( trim( 'breadcrumbs ' . $custom_class ) )
			),
			'after'       => '</nav>',
			'home_text'   => get_bloginfo( 'name' ),
			'echo'        => true,
		]);

		$args = wp_parse_args( $args, $default );
		$force_theme = (bool) $args['force_theme'];
		$html        = '';

		if ( function_exists( 'yoast_breadcrumb' ) && ! $force_theme ) {
			$html = yoast_breadcrumb( $args['before'], $args['after'], false );
		} elseif ( function_exists( 'rank_math_the_breadcrumbs' ) && ! $force_theme ) {
			ob_start();
			rank_math_the_breadcrumbs( [
				'wrap_before' => $args['before'],
				'wrap_after'  => $args['after'],
				'separator'   => $args['separator'],
			] );
			$html = ob_get_clean();
		} elseif ( function_exists( 'woocommerce_breadcrumb' ) && ! $force_theme ) {
			ob_start();
			woocommerce_breadcrumb( [
				'wrap_before' => $args['before'],
				'wrap_after'  => $args['after'],
				'delimiter'   => $args['separator'],
				'home'        => $args['home_text'],
			] );
			$html = ob_get_clean();
		} else {
			$home     = trailingslashit( home_url() );
			$blog_id  = absint( get_option( 'page_for_posts' ) );
			$blog_url = $blog_id ? trailingslashit( get_permalink( $blog_id ) ) : '';
			$is_wc    = function_exists( 'is_woocommerce' );
			$shop_id  = $is_wc ? (int) wc_get_page_id( 'shop' ) : 0;
			$shop_url = $shop_id > 0 ? trailingslashit( get_permalink( $shop_id ) ) : '';

			$items = [];
			$add   = static function( string $label, string $url = '' ) use ( &$items ): void {
				$items[] = compact( 'label', 'url' );
			};

			$add_term_ancestors = static function( int $term_id, string $taxonomy ) use ( $add ): void {
				foreach ( array_reverse( get_ancestors( $term_id, $taxonomy ) ) as $id ) {
					$t = get_term( $id, $taxonomy );
					if ( $t && ! is_wp_error( $t ) ) {
						$add( $t->name, get_term_link( $t ) );
					}
				}
			};

			$add( $args['home_text'], $home );

			if ( $is_wc && is_woocommerce() ) {

				if ( $shop_url && $shop_url === $home && is_shop() ) {
					goto render;
				}

				if ( ! is_shop() && $shop_id > 0 ) {
					$add( get_the_title( $shop_id ), $shop_url );
				}

				if ( is_product_category() || is_product_tag() ) {
					$term = get_queried_object();
					$add_term_ancestors( $term->term_id, $term->taxonomy );
					$add( $term->name );

				} elseif ( is_product() ) {
					$terms = wc_get_product_terms( get_the_ID(), 'product_cat', [
						'orderby' => 'parent',
						'order'   => 'DESC',
					] );
					if ( $terms ) {
						$cat = $terms[0];
						$add_term_ancestors( $cat->term_id, 'product_cat' );
						$add( $cat->name, get_term_link( $cat ) );
					}
					$add( get_the_title() );

				} else {
					if ( $shop_id > 0 ) {
						$add( get_the_title( $shop_id ) );
					}
				}

			} elseif ( is_single() && 'post' === get_post_type() ) {
				if ( $blog_id && $blog_url !== $home ) {
					$add( get_the_title( $blog_id ), $blog_url );
				}
				$cats = get_the_category();
				if ( $cats ) {
					usort( $cats, static fn( $a, $b )
						=> count( get_ancestors( $b->term_id, 'category' ) )
						-  count( get_ancestors( $a->term_id, 'category' ) )
					);
					$cat = $cats[0];
					$add_term_ancestors( $cat->term_id, 'category' );
					$add( $cat->name, get_category_link( $cat->term_id ) );
				}
				$add( get_the_title() );
			} elseif ( is_page() ) {
				foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $id ) {
					$add( get_the_title( $id ), get_permalink( $id ) );
				}
				$add( get_the_title() );
			} elseif ( is_category() ) {

				if ( $blog_id && $blog_url !== $home ) {
					$add( get_the_title( $blog_id ), $blog_url );
				}
				$cat = get_queried_object();
				$add_term_ancestors( $cat->term_id, 'category' );
				$add( $cat->name );

			} elseif ( is_tax() ) {
				$term    = get_queried_object();
				$tax_obj = get_taxonomy( $term->taxonomy );
				if ( ! empty( $tax_obj->object_type[0] ) ) {
					$pt = get_post_type_object( $tax_obj->object_type[0] );
					if ( $pt && $pt->has_archive ) {
						$add( $pt->label, get_post_type_archive_link( $pt->name ) );
					}
				}
				$add_term_ancestors( $term->term_id, $term->taxonomy );
				$add( $term->name );
			} elseif ( is_post_type_archive() ) {

				$add( get_queried_object()->label );

			} elseif ( is_single() ) {

				$pt = get_post_type_object( get_post_type() );
				if ( $pt && $pt->has_archive ) {
					$add( $pt->label, get_post_type_archive_link( $pt->name ) );
				}
				$add( get_the_title() );

			} elseif ( is_home() ) {

				if ( ! $blog_id || $blog_url === $home ) {
					goto render;
				}
				$add( get_the_title( $blog_id ) );

			} elseif ( is_tag() ) {

				if ( $blog_id && $blog_url !== $home ) {
					$add( get_the_title( $blog_id ), $blog_url );
				}
				$add( single_tag_title( '', false ) );

			} elseif ( is_author() ) {

				if ( $blog_id && $blog_url !== $home ) {
					$add( get_the_title( $blog_id ), $blog_url );
				}
				$add( get_the_author() );

			} elseif ( is_date() ) {

				if ( $blog_id && $blog_url !== $home ) {
					$add( get_the_title( $blog_id ), $blog_url );
				}
				$add( get_the_date() );

			} elseif ( is_search() ) {

				$add( get_search_query() );

			} elseif ( is_404() ) {

				$add( '404' );
			}

			$items = (array) apply_filters( 'breadcrumbs_items', $items, $args );

			render:

			if ( ! empty( $items ) && count( $items ) > 1 ) {
				$total = count( $items );
				$sep   = sprintf(
					'<span role="presentation" aria-hidden="true">%s</span>',
					$args['separator']
				);

				$list = '';
				foreach ( $items as $i => $item ) {
					$is_last	= ( $i === $total - 1 );
					$label		= $item['label'];
					$url		= ! empty( $item['url'] ) ? esc_url( $item['url'] ) : '';
					$list		.= ( ! $is_last && $url ) ? '<a href="' . $url . '">' . $label . '</a>' : '<span class="last" aria-current="page">' . $label . '</span>';

					if ( ! $is_last ) {
						$list .= $sep;
					}
				}

				$html = $args['before'] . $list . $args['after'];
			}

		}

		if ( $args['echo'] ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			return $html;
		}
	}
}

if ( ! function_exists('the_pagination') ) {
	function the_pagination( array $args = [] ) {
		global $wp_query;
		$query = $args['query'] ?? $wp_query;
		$big   = $args['big'] ?? 999999999;
		$paged = $args['current'] ?? get_query_var( 'paged' );
		$paged = max( 1, absint( $paged ) );
		$base = str_replace( $big, '%#%', esc_url( get_pagenum_link( $big, false ) ) );
		
		$defaults = [
			'type'			=> 'plain',
			'add_args'		=> [],
			'mid_size'		=> 1,
			'end_size'		=> 2,
			'base'			=> $base,
			'total'			=> $query->max_num_pages,
			'current'		=> $paged,
			'prev_text'		=> sprintf('<span class="sr-only">%s</span>%s', esc_html__( 'Previous page' ), is_rtl() ? '&rarr;' : '&larr;'),
			'next_text'		=> sprintf('<span class="sr-only">%s</span>%s', esc_html__( 'Next page' ), is_rtl() ? '&larr;' : '&rarr;'),
			'before_tag'	=> '<nav class="%s" role="navigation" aria-label="%s">',
			'after_tag'		=> '</nav>',
			'aria_label'	=> esc_html__( 'Page navigation' ),
			'class'			=> '',
			'echo'			=> true,
		];

		$args = wp_parse_args( $args, $defaults );

		if ( $args['total'] <= 1 ) {
			return;
		}

		$before_tag = $args['before_tag'];
		$after_tag  = $args['after_tag'];
		$aria_label = $args['aria_label'];
		$nav_class  = trim( 'pagination '.$args['class'] );
		$nav_class	= array_filter( array_unique( explode(' ', $nav_class) ) );

		$excluded_args = [ 'before_tag', 'after_tag', 'aria_label', 'class', 'query', 'echo' ];
		$paginate_args = array_diff_key( $args, array_flip( $excluded_args ) );

		$pagination_html = paginate_links( $paginate_args );
		
		if ( empty( $pagination_html ) ) {
			return;
		}

		$before_tag = sprintf( $before_tag, esc_attr( implode(' ', $nav_class) ), esc_attr( $aria_label ) );

		$output = sprintf("%s\n\t%s\n%s", $before_tag, $pagination_html, $after_tag);

		if ( $args['echo'] ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			return $output;
		}
	}
}

if( ! function_exists('arvand_human_time_diff') ){
	function arvand_human_time_diff( $from, $to = 0, $format = null ){
		if ( empty( $to ) ) {
			$to = time();
		}

		$diff = absint( $to - $from );
		$max_diff = WEEK_IN_SECONDS * 1;

		if( $diff > $max_diff ){
			if( ! $format ) $format = get_option('date_format');
			return wp_date( $format, $from );
		} else {
			return sprintf(__('%s ago'), human_time_diff( $from, $to ) );
		}
	}
}

if( ! function_exists('socialmeida_list') ){
	function socialmeida_list(): array {
		return [
			'instagram'	=> 'اینستاگرام',
			'whatsapp'	=> 'واتس‌اپ',
			'telegram'	=> 'تلگرام',
			'facebook'	=> 'فیسبوک',
			'linkedin'	=> 'لینکدین',
			'youtube'	=> 'یوتیوب',
			'aparat'	=> 'آپارات',
			'eitaa'		=> 'ایتا',
			'bale'		=> 'پیام‌رسان بله',
			'rubika'	=> 'روبیکا',
		];
	}
}

