<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 */

defined('ABSPATH') || exit;

// get_template_part('components/offcanvas', 'mmenu');

if( boolval( $args['meta'] ?? false ) ) {
	wp_footer();
	echo '</body></html>';
	return;
}

?>

<footer id="siteFoot">
	Lorem ipsum dolor sit amet consectetur adipisicing elit. Optio laborum ratione iusto velit esse quos porro molestias magni unde, non aliquid veritatis, vitae explicabo ducimus dignissimos, assumenda facere quo? Numquam.
</footer>

<?php wp_footer(); ?>

</body>
</html>