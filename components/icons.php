<?php
/**
 * Theme SVG Icons
 *
 * This template is placed after the body and shows the website icons.
 *
 * @package tabler
 * @version 3.44.0
 * @url https://tabler.com/icons
 */

defined( 'ABSPATH' ) || exit;

ob_start(); ?>
<!--SYMBOLS HERE-->
<symbol id="icon-test" data-if="is_user_logged_in() && is_post_type_archive('medical')">...</symbol>

<?php
$svgsymbols = ob_get_clean();
preg_match_all('/<symbol([^>]*)>(.*?)<\/symbol>/is', $svgsymbols, $symbols, PREG_SET_ORDER);
$output = '';

foreach ( $symbols as $symbol ) {
    $attrs   = $symbol[1];
    $content = $symbol[2];

    preg_match('/\sdata-if="([^"]+)"/', $attrs, $if_match);

    if ( ! isset($if_match[1]) ) {
        $output .= "<symbol$attrs>$content</symbol>";
        continue;
    }

    $attrs    = str_replace($if_match[0], '', $attrs);
    $if_value = $if_match[1];
    $render   = false;

    foreach ( preg_split('/\s*\|\|\s*/', $if_value) as $or_part ) {
        $and_ok = true;

        foreach ( preg_split('/\s*&&\s*/', $or_part) as $condition ) {
            $condition = trim($condition);

            if ( ! preg_match('/^(\w+)\s*\((.*?)\)$/', $condition, $m) ) {
                $and_ok = false;
                break;
            }

            $func = $m[1];
            $args = [];

            if ( $m[2] !== '' ) {
                preg_match_all('/\'[^\']*\'|"[^"]*"|[^,\s][^,]*/', $m[2], $arg_matches);
                foreach ( $arg_matches[0] as $arg ) {
                    $arg    = trim($arg);
                    $args[] = preg_match('/^[\'"](.*)[\'"]\s*$/', $arg, $qm)
                        ? $qm[1]
                        : ( $arg === 'true' ? true : ( $arg === 'false' ? false : ( is_numeric($arg) ? $arg + 0 : $arg ) ) );
                }
            }

            if ( ! is_callable($func) || ! call_user_func_array($func, $args) ) {
                $and_ok = false;
                break;
            }
        }

        if ( $and_ok ) {
            $render = true;
            break;
        }
    }

    if ( $render ) {
        $output .= "<symbol$attrs>$content</symbol>";
    }
}

if ( ! empty($output) ) {
    printf(
        '%2$s<svg xmlns="http://www.w3.org/2000/svg" class="hidden">%1$s</svg>%2$s',
        preg_replace(
            ['/\>[^\S ]+/s', '/[^\S ]+\</s', '/(\s)+/s', '/<!--(.|\s)*?-->/'],
            ['>', '<', '\\1', ''],
            $output
        ),
        PHP_EOL
    );
    echo PHP_EOL;
}

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */