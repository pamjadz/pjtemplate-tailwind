<?php
/**
 * Parsi date conversion class.
 *
 * Converts WordPress display dates to the Jalali (Shamsi) calendar, but ONLY
 * when the active locale is fa_IR and no other Persian-date plugin is present.
 * All display conversion is funnelled through the single modern `wp_date`
 * filter, which every core date helper (the_date, get_the_time, comment dates,
 * modified dates, …) routes through since WordPress 5.3.
 *
 * @author  Pouria Amjadzadeh, Mobin Ghasempoor, Claude
 * @package Arvand
 * @version 2.0
 */

namespace Arvand\Parsidate {

	defined( 'ABSPATH' ) || exit;
	class Parsidate {
		public static array $sessions			= [ 'بهار', 'تابستان', 'پاییز', 'زمستان' ];
		public static array $day_names			= [ 'یکشنبه', 'دوشنبه', 'سه شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' ];
		public static array $day_names_small	= [ 'ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش' ];
		public static array $months				= [ 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' ];
		private static array $j_days_in_month	= [ 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 ];
		private static array $j_days_sum		= [ 0, 31, 62, 93, 124, 155, 186, 216, 246, 276, 306, 336 ];
		private static array $g_days_sum		= [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];

		private function __construct() {
			add_filter( 'wp_date', [ $this, 'filter_wp_date' ], 10, 4 );

			add_filter( 'pre_get_document_title', [$this, 'fix_arabic_chars'], 1000 );
			add_filter( 'the_title', [$this, 'fix_arabic_chars'], 1000 );
			add_filter( 'the_content', [$this, 'fix_arabic_chars'], 1000 );
			add_filter( 'comment_text', [$this, 'fix_arabic_chars'], 1000 );
			add_filter( 'wp_list_categories', [$this, 'fix_arabic_chars'], 1000 );
			add_filter( 'the_excerpt', [$this, 'fix_arabic_chars'], 1000 );
		}

		public static function instance(): ?self {
			static $instance = null;

			if ( $instance instanceof self ) {
				return $instance;
			}
			if ( ! self::should_load() ) {
				return null;
			}

			return $instance = new self();
		}

		private static function should_load(): bool {
			if ( self::has_conflicting_plugin() ) {
				return false;
			}
			return get_locale() === 'fa_IR' || self::is_multilingual();
		}

		private static function has_conflicting_plugin(): bool {
			$active = defined( 'WP_PARSIDATE_VERSION' ) || defined( 'WPJALALI_VERSION' );
			return (bool) $active;
		}

		private static function is_multilingual(): bool {
			return defined( 'POLYLANG_VERSION' ) || defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'pll_current_language' );
		}

		/* -----------------------------------------------------------------
		 * WordPress display filter.
		 * ----------------------------------------------------------------- */

		/**
		 * @param string             $date      Formatted date (unused, we rebuild it).
		 * @param string             $format    PHP date format.
		 * @param int                $timestamp Unix timestamp (UTC).
		 * @param \DateTimeZone|null  $timezone  Target timezone.
		 */
		public function filter_wp_date( $date, $format, $timestamp, $timezone ) {
			if ( '' === (string) $format || get_locale() !== 'fa_IR' ) {
				return $date;
			}

			$tz       = $timezone instanceof \DateTimeZone ? $timezone : self::timezone();
			$datetime = ( new \DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( $tz );
			return self::persian_date( $format, $datetime );
		}

		/**
		 * @param string             $content content of string.
		 */
		public function fix_arabic_chars( $string ){
			return str_replace(
				['ي', 'ك', 'ة', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
				['ی', 'ک', 'ه', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
				$string,
			);
		}

		/* -----------------------------------------------------------------
		 * Conversion core (static, timezone-aware).
		 * ----------------------------------------------------------------- */
		public static function is_persian_leap_year( int $year ): bool {
			return in_array( $year % 33, [ 1, 5, 9, 13, 17, 22, 26, 30 ], true );
		}

		private static function is_gregorian_leap_year( int $year ): bool {
			return ( $year % 4 === 0 && $year % 100 !== 0 ) || $year % 400 === 0;
		}

		private static function timezone(): \DateTimeZone {
			return function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( date_default_timezone_get() ?: 'UTC' );
		}

		/**
		 * Normalize the accepted date inputs into a DateTimeImmutable.
		 *
		 * @param \DateTimeInterface|int|string|null $date
		 */
		private static function to_datetime( $date ): \DateTimeImmutable {
			$tz = self::timezone();

			if ( $date instanceof \DateTimeInterface ) {
				return \DateTimeImmutable::createFromInterface( $date );
			}
			if ( null === $date || '' === $date || 'now' === $date ) {
				return new \DateTimeImmutable( 'now', $tz );
			}
			if ( is_numeric( $date ) ) {
				return ( new \DateTimeImmutable( '@' . (int) $date ) )->setTimezone( $tz );
			}

			return new \DateTimeImmutable( (string) $date, $tz );
		}

		/**
		 * Format a Jalali date string, mirroring PHP's date() tokens.
		 *
		 * @param \DateTimeInterface|int|string $date
		 */
		public static function persian_date( string $format, $date = 'now' ): string {
			$dt        = self::to_datetime( $date );
			$timestamp = $dt->getTimestamp();

			$gy      = (int) $dt->format( 'Y' );
			$gm      = (int) $dt->format( 'n' );
			$gd      = (int) $dt->format( 'j' );
			$wday    = (int) $dt->format( 'w' );
			$hours   = (int) $dt->format( 'G' );
			$minutes = (int) $dt->format( 'i' );
			$seconds = (int) $dt->format( 's' );

			[ $year, $month, $day ] = self::gregorian_to_persian( $gy, $gm, $gd );
			$month = (int) $month;
			$day   = (int) $day;

			$out = '';
			$len = strlen( $format );

			for ( $i = 0; $i < $len; $i++ ) {
				$char = $format[ $i ];
				if ( '\\' === $char ) {
					$out .= $format[ ++$i ] ?? '';
					continue;
				}

				switch ( $char ) {
					// --- day ---
					case 'd':
						$out .= str_pad( (string) $day, 2, '0', STR_PAD_LEFT );
						break;
					case 'j':
						$out .= $day;
						break;
					case 'D':
						$out .= self::$day_names_small[ $wday ];
						break;
					case 'l':
						$out .= self::$day_names[ $wday ];
						break;
					case 'N':
						$out .= self::persian_week_day( $wday ) + 1;
						break;
					case 'w':
						$out .= self::persian_week_day( $wday );
						break;
					case 'z':
						$out .= ( $month === 12 && self::is_persian_leap_year( $year ) )
							? 30 + $day
							: self::$j_days_sum[ $month - 1 ] + $day;
						break;
					case 'S':
						$out .= 'ام';
						break;
					// --- week ---
					case 'W':
						$out .= intval( ( self::$j_days_sum[ $month - 1 ] + $day ) / 7 ) + 1;
						break;
					// --- month ---
					case 'F':
					case 'M':
						$out .= self::$months[ $month - 1 ];
						break;
					case 'f':
						$out .= self::$sessions[ intdiv( $month - 1, 3 ) ];
						break;
					case 'm':
						$out .= str_pad( (string) $month, 2, '0', STR_PAD_LEFT );
						break;
					case 'n':
						$out .= $month;
						break;
					case 't':
						$out .= ( $month === 12 && self::is_persian_leap_year( $year ) )
							? 30
							: self::$j_days_in_month[ $month - 1 ];
						break;
					// --- year ---
					case 'L':
						$out .= self::is_persian_leap_year( $year ) ? 1 : 0;
						break;
					case 'o':
					case 'Y':
						$out .= $year;
						break;
					case 'y':
						$out .= substr( (string) $year, -2 );
						break;
					// --- time ---
					case 'a':
						$out .= $hours < 12 ? 'ق.ظ' : 'ب.ظ';
						break;
					case 'A':
						$out .= $hours < 12 ? 'قبل از ظهر' : 'بعد از ظهر';
						break;
					case 'g':
						$out .= $hours % 12 === 0 ? 12 : $hours % 12;
						break;
					case 'G':
						$out .= $hours;
						break;
					case 'h':
						$out .= str_pad( (string) ( $hours % 12 === 0 ? 12 : $hours % 12 ), 2, '0', STR_PAD_LEFT );
						break;
					case 'H':
						$out .= str_pad( (string) $hours, 2, '0', STR_PAD_LEFT );
						break;
					case 'i':
						$out .= str_pad( (string) $minutes, 2, '0', STR_PAD_LEFT );
						break;
					case 's':
						$out .= str_pad( (string) $seconds, 2, '0', STR_PAD_LEFT );
						break;
					// --- full date/time ---
					case 'r':
						$out .= self::$day_names[ $wday ] . '، ' . $day . ' ' . self::$months[ $month - 1 ] . ' ' . $year
							. ' ' . str_pad( (string) $hours, 2, '0', STR_PAD_LEFT )
							. ':' . str_pad( (string) $minutes, 2, '0', STR_PAD_LEFT )
							. ':' . str_pad( (string) $seconds, 2, '0', STR_PAD_LEFT );
						break;
					case 'U':
						$out .= $timestamp;
						break;
					// --- timezone-dependent tokens: keep the real value ---
					case 'c':
					case 'e':
					case 'I':
					case 'O':
					case 'P':
					case 'T':
					case 'Z':
					case 'u':
					case 'v':
						$out .= $dt->format( $char );
						break;
					default:
						$out .= $char;
				}
			}

			return $out;
		}

		/**
		 * Convert a Jalali date string back to a Gregorian formatted date.
		 *
		 * @param string $persian_date e.g. "1375/10/23".
		 */
		public static function gregorian_date( string $format, string $persian_date = '' ): string {
			preg_match_all( '!\d+!', $persian_date, $matches );
			$m = $matches[0];

			[ $year, $month, $day ] = self::persian_to_gregorian( (int) $m[0], (int) $m[1], (int) $m[2] );

			return date(
				$format,
				mktime(
					(int) ( $m[3] ?? 0 ),
					(int) ( $m[4] ?? 0 ),
					(int) ( $m[5] ?? 0 ),
					$month,
					$day,
					$year
				)
			);
		}

		public static function gregorian_to_persian( int $gy, int $gm, int $gd ): array {
			$day_of_year = self::$g_days_sum[ $gm - 1 ] + $gd;

			if ( self::is_gregorian_leap_year( $gy ) && $gm > 2 ) {
				$day_of_year++;
			}

			$d_33 = (int) ( ( ( $gy - 16 ) % 132 ) * 0.0305 );
			$leap = $gy % 4;
			$a    = ( ( $d_33 === 1 || $d_33 === 2 ) && ( $d_33 === $leap || $leap === 1 ) ) ? 78 : ( ( $d_33 === 3 && $leap === 0 ) ? 80 : 79 );
			$b    = ( $d_33 === 3 || $d_33 < ( $leap - 1 ) || $leap === 0 ) ? 286 : 287;

			if ( (int) ( ( $gy - 10 ) / 63 ) === 30 ) {
				$b--;
				$a++;
			}

			if ( $day_of_year > $a ) {
				$jy = $gy - 621;
				$jd = $day_of_year - $a;
			} else {
				$jy = $gy - 622;
				$jd = $day_of_year + $b;
			}

			for ( $i = 0; $i < 11 && $jd > self::$j_days_in_month[ $i ]; $i++ ) {
				$jd -= self::$j_days_in_month[ $i ];
			}
			$jm = $i + 1;

			return [
				$jy,
				str_pad( (string) $jm, 2, '0', STR_PAD_LEFT ),
				str_pad( (string) $jd, 2, '0', STR_PAD_LEFT ),
			];
		}

		public static function persian_to_gregorian( int $jy, int $jm, int $jd ): array {
			$doyj = self::$j_days_sum[ $jm - 1 ] + $jd;
			$d4   = ( $jy + 1 ) % 4;
			$d33  = (int) ( ( ( $jy - 55 ) % 132 ) * 0.0305 );
			$a    = ( $d33 !== 3 && $d4 <= $d33 ) ? 287 : 286;
			$b    = ( ( $d33 === 1 || $d33 === 2 ) && ( $d33 === $d4 || $d4 === 1 ) ) ? 78 : ( ( $d33 === 3 && $d4 === 0 ) ? 80 : 79 );

			if ( (int) ( ( $jy - 19 ) / 63 ) === 20 ) {
				$a--;
				$b++;
			}

			if ( $doyj <= $a ) {
				$gy = $jy + 621;
				$gd = $doyj + $b;
			} else {
				$gy = $jy + 622;
				$gd = $doyj - $a;
			}

			$gm = 0;
			foreach ( [ 31, ( $gy % 4 === 0 ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ] as $index => $days ) {
				$gm = $index + 1;
				if ( $gd <= $days ) {
					break;
				}
				$gd -= $days;
			}

			return [ $gy, $gm, $gd ];
		}

		/** Map PHP weekday (0=Sun) to Persian weekday (0=Shanbe … 6=Jom'e). */
		private static function persian_week_day( int $wday ): int {
			return ( $wday + 1 ) % 7;
		}
	}
}

namespace {
	if ( ! function_exists( 'gregdate' ) ) {
		function gregdate( string $format, string $persian_date, $lang = '' ): string {
			return \Arvand\Parsidate\Parsidate::gregorian_date( $format, $persian_date );
		}
	}

	if ( ! function_exists( 'parsidate' ) ) {
		function parsidate( string $format, $datetime = 'now' ): string {
			return \Arvand\Parsidate\Parsidate::persian_date( $format, $datetime );
		}
	}

	if ( ! function_exists( 'jdate' ) ) {
		function jdate( string $format, string|int $timestamp = 'now', $none = '', string $time_zone = 'Asia/Tehran' ): string {
			return \Arvand\Parsidate\Parsidate::persian_date( $format, $timestamp );
		}
	}
}
