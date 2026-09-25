<?php
defined('ABSPATH') || exit;

//Call This by  <button type="button" data-pjstack="#mobileMenu" aria-haspopup="dialog" aria-controls="mobileMenu">Menu</button>
?>

<div id="mobileMenu" class="offcanvas offcanvas-right" tabindex="-1" role="dialog" aria-labelledby="mobileMenuLabel" aria-hidden="true" aria-modal="true">
	<div class="offcanvas-header">
		<div id="mobileMenuLabel" class="offcanvas-title">Offcanvas</div>
		<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
	</div>
	<nav class="offcanvas-body px-0">
		<?php wp_nav_menu( ['theme_location' => (has_nav_menu('responsive') ? 'responsive' : 'primary'),'container' => 'ul', 'menu_class' => 'collapse-menu'] ); ?>
	</nav>
</div>