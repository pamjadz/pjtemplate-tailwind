<?php 
/**
 * The template for displaying comment item
 *
 * @see 	https://developer.wordpress.org/reference/classes/wp_comment/
 * @author 	Pouriya Amjadzadeh
 * @version 3.0.1
 */

defined('ABSPATH') || exit;
?>

<li <?php comment_class($args['has_children'] ? 'no_child' :'has_children') ?> id="comment-<?php comment_ID() ?>">
	<div class="commnetbody<?php if( get_option( 'show_avatars' ) ) echo ' show-avatar'; ?>" itemscope itemprop="comment" itemtype="https://schema.org/Comment">
		<meta itemprop="parentItem" itemscope itemtype="https://schema.org/Article" itemid="<?php echo get_permalink(); ?>">
		<?php if( get_option( 'show_avatars' ) ) echo get_avatar($comment, 60, '', get_comment_author()); ?>
		<div class="comment-meta">
			<cite itemprop="author" itemscope itemtype="https://schema.org/Person"><span itemprop="name"><?php comment_author(); ?></span></cite>
			<time class="comment_date" itemprop="dateCreated" datetime="<?php echo gmdate('c', get_comment_time('U') ); ?>"><?php comment_time( get_option('date_format') ); ?></time>
			<?php
			comment_reply_link(wp_parse_args([
				'depth'		=> $depth,
				'max_depth'	=> $args['max_depth']
			]), $args);
			?>
		</div>
		<div class="comment_text" itemprop="text">
			<?php
			if( $comment->comment_approved == '0' ) printf('<p class="text-info mb-2" role="alert">%s<p>', esc_html__('Your comment is awaiting moderation.'));
			comment_text();
			?>
		</div>
	</div>