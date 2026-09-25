<?php
defined('ABSPATH') || exit;

$args = wp_parse_args($args, [
	'subject' => '',
	'message' => '',
]);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<?php
if( ! empty( $args['subject'] ) ){
printf('<title>%s</title>', $args['subject'] );
}
?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body,h1,h2,h3,h4,h5,h6,html,p{margin:0;padding:0}
body,html{height:100%;background:#f3faf9;color:#222;}
.wrapper{border-collapse:collapse;width:100%;max-width:800px;margin:0 auto}
.content h2,a{color:#2e7173}.mb{margin-bottom:20px}.footer,.header{padding:20px 0;text-align:center}.content{padding:32px;line-height:30px;border-radius:12px;background-color:#fff;border:1px solid #e2e8f0}hr{background-color:#e2e8f0;opacity:1;border:none;height:1px;margin:24px 0}.content h2{font-size:24px;font-weight:700}.content p:not(:last-child){margin-bottom:12px}.footer{font-size:13px}.footer p{padding:10px 0}.button{font-size:15px;padding:10px 18px;display:inline-block;border-radius:6px;color:#fff;text-decoration:none;background-color:#2e7173}@media only screen and (max-width:600px){table{width:95%}.footer,.header{padding:10px}}</style>
</head>
<body>
<table style="color:#083A39;font-family:Tahoma;background-color:#f3faf9;border-collapse:collapse;border:none;width:100%;height:100%;">
	<tr>
		<td style="padding:16px;vertical-align:top;">
			<table class="wrapper">
				<tr>
					<td class="header"><?php the_logo(); ?></td>
				</tr>
				<tr>
					<td class="content">
						<?php
						if( ! empty( $args['subject'] ) ){
							printf('<h2 class="mb">%s</h2>', $args['subject'] );
						}
						echo $args['message'];
						?>
					</td>
				</tr>
				<tr>
					<td class="footer">
						<p><?php printf(__('&copy; %s %s. All rights reserved.', 'tabeebo'), gmdate('Y'), get_bloginfo('name') ); ?></p>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>