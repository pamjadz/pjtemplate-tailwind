const themejs = document.getElementById('themejs'), ajaxURL = themejs.dataset.ajax;

document.addEventListener('DOMContentLoaded', () => {
	//Sticky Header
	const siteHead = document.getElementById('siteHead');
	if( siteHead ){
		document.documentElement.style.setProperty('--headernav', `${siteHead.offsetHeight}px`);
		if( siteHead.classList.contains('sticky') ) {
			new IntersectionObserver(([e]) => e.target.classList.toggle('sticked', e.intersectionRatio < 1), {threshold:1}).observe( siteHead );
		} else if ( siteHead.classList.contains('fixed') ) {
			const isSticked = () => window.scrollY > 10;
			siteHead.classList.toggle('sticked', isSticked());
			window.addEventListener('scroll', () => siteHead.classList.toggle('sticked', isSticked()));
		}
	}
	
	//Splidejs
	document.querySelectorAll( '.splide' ).forEach(el => {
		if( el.dataset.splide ) {
			new Splide( el ).mount();
		}
	});
});

if( typeof jQuery !== 'undefined' ){
	jQuery(document).ready(function($){
		function wpajax(method, data, success = null, before = null) {
			return $.ajax({
				type: method,
				url: ajaxURL,
				data: data,
				beforeSend: before,
				success: success,
				error: function(xhr, status, error) {
					console.error('AJAX Error:', error);
				}
			});
		}

		//Collapse 
		$(document).on('click', '.btn-collapse', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const targetId = $btn.attr('aria-controls');
			if (!targetId) {
				console.error('خطا: aria-controls تعریف نشده است');
				return;
			}
			
			const $target = $('#' + targetId);
			if (!$target.length) {
				console.error(`خطا: المان با ID "${targetId}" یافت نشد`);
				return;
			}
			
			const $tablist = $btn.closest('.tablist');
			
			if ($tablist.length) {
				$tablist.find('.btn-collapse').attr('aria-selected', 'false');
				$tablist.find('[role="tabpanel"]').addClass('hidden');
				$btn.attr('aria-selected', 'true');
				$target.removeClass('hidden');
			} else {
				const isCurrentlyOpen = $btn.attr('aria-expanded') === 'true';
				const $accordion = $btn.closest('.accordion');
				if ($accordion.length && $accordion.hasClass('accordion-single')) {
					$accordion.find('.btn-collapse').not($btn).attr('aria-expanded', 'false');
					// $accordion.find('[role="region"]').not($target).addClass('hidden');
				}
				$btn.attr('aria-expanded', isCurrentlyOpen ? 'false' : 'true');
				$target.toggleClass('hidden', isCurrentlyOpen);
			}
		});

		//Offcanvas mmenu
		// $('.collapse-menu li').click(function (e) {
		// 	if(this != e.target) return;
		// 	e.preventDefault();
		// 	$(this).toggleClass('item-opened').find('> ul').slideToggle();
		// });
	});
}