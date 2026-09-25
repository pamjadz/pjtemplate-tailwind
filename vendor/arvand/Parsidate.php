<?php
/**
 * Parsi date Conversation class
 *
 * @author Pouria Amjadzadeh, Mobin Ghasempoor
 * @package Arvand
 * @version 1.0
 */

namespace Arvand {
	defined( 'ABSPATH' ) || exit;

	class ParsiDate {
		protected static $instance;
		public static $sessions				= ['بهار', 'تابستان', 'پاییز', 'زمستان'];
		public static $day_names			= ['یکشنبه', 'دوشنبه', 'سه شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
		public static $day_names_small		= ['ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش'];
		public static $j_days_in_month		= [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
		public static $months				= ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

		private $j_days_sum_month = array( 0, 0, 31, 62, 93, 124, 155, 186, 216, 246, 276, 306, 336 );
		private $g_days_sum_month = array( 0, 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

		/**
		 * WPP_ParsiDate::IsPerLeapYear()
		 * check year is leap
		 *
		 * @param mixed $year
		 *
		 * @return boolean
		 */
		public function IsPerLeapYear( $year ) {
			return in_array( $year % 33, array( 1, 5, 9, 13, 17, 22, 26, 30 ), true );
		}

		/**
		 * WPP_ParsiDate::IsLeapYear()
		 * check year is leap
		 *
		 * @param mixed $year
		 *
		 * @return boolean
		 */
		private function IsLeapYear( $year ) {
			return ( ( $year % 4 ) == 0 && ( $year % 100 ) != 0 ) || ( ( $year % 400 ) == 0 );
		}

		/**
		 * WPP_ParsiDate::persian_date()
		 * convert gregorian datetime to persian datetime
		 *
		 * @param mixed  $format
		 * @param string $date
		 *
		 * @return string
		 */
		public static function persian_date( $format, $date = 'now') {
			$timestamp = is_numeric( $date ) && (int) $date == $date ? $date : strtotime( $date );
			$date      = getdate( $timestamp );

			list( $date['year'], $date['mon'], $date['mday'] ) = $this->gregorian_to_persian( $date['year'], $date['mon'], $date['mday'] );
			$date['mon']  = (int) $date['mon'];
			$date['mday'] = (int) $date['mday'];

			$out = '';
			$len = strlen( $format );

			for ( $i = 0; $i < $len; $i++ ) {
				switch ( $format[ $i ] ) {
					// day
					case 'd':
						$out .= ( $date['mday'] < 10 ) ? '0' . $date['mday'] : $date['mday'];
						break;
					case 'D':
						$out .= self::$day_names_small[ $date['wday'] ];
						break;
					case 'l':
						$out .= self::$day_names[ $date['wday'] ];
						break;
					case 'j':
						$out .= $date['mday'];
						break;
					case 'N':
						$out .= $this->week_day( $date['wday'] ) + 1;
						break;
					case 'w':
						$out .= $this->week_day( $date['wday'] );
						break;
					case 'z':
						if ( $date['mon'] == 12 && $this->IsPerLeapYear( $date['year'] ) ) {
							$out .= 30 + $date['mday'];
						} else {
							$out .= $this->j_days_in_month[ $date['mon'] ] + $date['mday'];
						}
						break;
					// week
					case 'W':
						$yday = $this->j_days_sum_month[ $date['mon'] - 1 ] + $date['mday'];
						$out .= intval( $yday / 7 );
						break;
					// month
					case 'f':
						$mon = $date['mon'];
						if ( $mon < 4 ) {
							$out .= $this->sessions[0];
						} elseif ( $mon < 7 ) {
							$out .= $this->sessions[1];
						} elseif ( $mon < 10 ) {
							$out .= $this->sessions[2];
						} else {
							$out .= $this->sessions[3];
						}
						break;
					case 'M':
					case 'F':
						$out .= self::$months[ $date['mon'] ];
						break;
					case 'm':
						$out .= ( $date['mon'] < 10 ) ? '0' . $date['mon'] : $date['mon'];
						break;
					case 'n':
						$out .= $date['mon'];
						break;
					case 'S':
						$out .= 'ام';
						break;
					case 't':
						if ( $date['mon'] == 12 && $this->IsPerLeapYear( $date['year'] ) ) {
							$out .= 30;
						} else {
							$out .= $this->j_days_in_month[ $date['mon'] - 1 ];
						}
						break;
					// year
					case 'L':
						$out .= ( ( $date['year'] % 4 ) == 0 ) ? 1 : 0;
						break;
					case 'o':
					case 'Y':
						$out .= $date['year'];
						break;
					case 'y':
						$out .= substr( $date['year'], 2, 2 );
						break;
					// time
					case 'a':
						$out .= ( $date['hours'] < 12 ) ? 'ق.ظ' : 'ب.ظ';
						break;
					case 'A':
						$out .= ( $date['hours'] < 12 ) ? 'قبل از ظهر' : 'بعد از ظهر';
						break;
					case 'B':
						$out .= (int) ( 1 + ( $date['mon'] / 3 ) );
						break;
					case 'g':
						$out .= ( $date['hours'] > 12 ) ? $date['hours'] - 12 : $date['hours'];
						break;
					case 'G':
						$out .= $date['hours'];
						break;
					case 'h':
						$hour = ( $date['hours'] > 12 ) ? $date['hours'] - 12 : $date['hours'];
						$out .= ( $hour < 10 ) ? '0' . $hour : $hour;
						break;
					case 'H':
						$out .= ( $date['hours'] < 10 ) ? '0' . $date['hours'] : $date['hours'];
						break;
					case 'i':
						$out .= ( $date['minutes'] < 10 ) ? '0' . $date['minutes'] : $date['minutes'];
						break;
					case 's':
						$out .= ( $date['seconds'] < 10 ) ? '0' . $date['seconds'] : $date['seconds'];
						break;
					// full date time
					case 'r':
						$out = self::$day_names[ $date['wday'] ] . ',' . $date['mday'] . ' ' . self::$months[ $date['mon'] ] . ' ' . $date['year'] . ' ' . $date['hours'] . ':' . ( ( $date['minutes'] < 10 ) ? '0' . $date['minutes'] : $date['minutes'] ) . ':' . ( ( $date['seconds'] < 10 ) ? '0' . $date['seconds'] : $date['seconds'] );
						break;
					case 'U':
						$out = $timestamp;
						break;
					// others (not applicable / no timezone handling)
					case 'c':
					case 'e':
					case 'I':
					case 'O':
					case 'P':
					case 'T':
					case 'Z':
					case 'u':
						break;
					default:
						$out .= $format[ $i ];
				}
			}

			return $out;
		}

		/**
		 * WPP_ParsiDate::gregorian_to_persian()
		 * convert gregorian date to persian date
		 *
		 * @param mixed $gy
		 * @param mixed $gm
		 * @param mixed $gd
		 *
		 * @return array
		 */
		public function gregorian_to_persian( $gy, $gm, $gd ) {
			$dayOfYear = $this->g_days_sum_month[ (int) $gm ] + $gd;

			if ( $this->IsLeapYear( $gy ) && $gm > 2 ) {
				$dayOfYear++;
			}

			$d_33 = (int) ( ( ( $gy - 16 ) % 132 ) * 0.0305 );
			$leap = $gy % 4;
			$a    = ( ( $d_33 == 1 || $d_33 == 2 ) && ( $d_33 == $leap || $leap == 1 ) ) ? 78 : ( ( $d_33 == 3 && $leap == 0 ) ? 80 : 79 );
			$b    = ( $d_33 == 3 || $d_33 < ( $leap - 1 ) || $leap == 0 ) ? 286 : 287;

			if ( (int) ( ( $gy - 10 ) / 63 ) == 30 ) {
				$b--;
				$a++;
			}

			if ( $dayOfYear > $a ) {
				$jy = $gy - 621;
				$jd = $dayOfYear - $a;
			} else {
				$jy = $gy - 622;
				$jd = $dayOfYear + $b;
			}

			for ( $i = 0; $i < 11 && $jd > $this->j_days_in_month[ $i ]; $i++ ) {
				$jd -= $this->j_days_in_month[ $i ];
			}

			$jm = ++$i;

			return array( $jy, strlen( $jm ) == 1 ? '0' . $jm : $jm, strlen( $jd ) == 1 ? '0' . $jd : $jd );
		}

		/**
		 * Get day of the week shamsi/jalali
		 *
		 * @param int $wday
		 *
		 * @return int
		 * @author Parsa Kafi
		 */
		private function week_day( $wday ) {
			return $wday == 6 ? 0 : ++$wday;
		}

		/**
		 * WPP_ParsiDate::getInstance()
		 * create instance of WPP_ParsiDate class
		 *
		 * @return WPP_ParsiDate
		 */
		public static function getInstance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * WPP_ParsiDate::gregorian_date()
		 * convert persian datetime to gregorian datetime
		 *
		 * @param mixed $format
		 * @param mixed $persiandate
		 *
		 * @return false|string
		 */
		public static function gregorian_date( $format, $persiandate = '' ) {
			preg_match_all( '!\d+!', $persiandate, $matches );
			$matches = $matches[0];
			list( $year, $mon, $day ) = $this->persian_to_gregorian( $matches[0], $matches[1], $matches[2] );
			return date(
				$format,
				mktime(
					isset( $matches[3] ) ? $matches[3] : 0,
					isset( $matches[4] ) ? $matches[4] : 0,
					isset( $matches[5] ) ? $matches[5] : 0,
					$mon,
					$day,
					$year
				)
			);
		}

		/**
		 * WPP_ParsiDate::persian_to_gregorian()
		 * convert persian date to gregorian date
		 *
		 * @param mixed $jy
		 * @param mixed $jm
		 * @param mixed $jd
		 *
		 * @return array
		 */
		public function persian_to_gregorian( $jy, $jm, $jd ) {
			$doyj = ( $jm - 2 > -1 ? $this->j_days_sum_month[ (int) $jm ] + $jd : $jd );
			$d4   = ( $jy + 1 ) % 4;
			$d33  = (int) ( ( ( $jy - 55 ) % 132 ) * .0305 );
			$a    = ( $d33 != 3 && $d4 <= $d33 ) ? 287 : 286;
			$b    = ( ( $d33 == 1 || $d33 == 2 ) && ( $d33 == $d4 || $d4 == 1 ) ) ? 78 : ( ( $d33 == 3 && $d4 == 0 ) ? 80 : 79 );

			if ( (int) ( ( $jy - 19 ) / 63 ) == 20 ) {
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

			foreach ( array( 0, 31, ( $gy % 4 == 0 ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ) as $gm => $days ) {
				if ( $gd <= $days ) {
					break;
				}
				$gd -= $days;
			}

			return [$gy, $gm, $gd];
		}
	}
}
namespace {
	if( ! function_exists('gregdate') ){
		function gregdate( $input, $datetime ) {
			return \Arvand\Parsidate::gregorian_date($input, $datetime);
		}
	}
	if( ! function_exists('parsidate') ){
		function parsidate( $input, $datetime ) {
			return \Arvand\Parsidate::persian_date($input, $datetime);
		}
	}	
	if( ! function_exists('jdate') ){
		function jdate( string $format , string|int $timestamp = 'now', $none = '', string $time_zone = 'Asia/Tehran' ) {
			return parsidate($format, $timestamp);
		}
	}
}