# دستورالعمل‌ها

- کلیه فایل‌های چندرسانه‌ای (تصاویر، ویدئو، صوت و ...) را در مسیر `assets/media` قرار دهید.
- کتابخانه‌ها را در مسیر `assets/libs` قرار دهید. برای هر کتابخانه یک پوشه با نام همان کتابخانه ایجاد کرده و سپس آن را **enqueue** کنید.
- فایل‌های موجود در مسیر `src/inc` به‌صورت خودکار در هسته پوسته فراخوانی می‌شوند.
- اگر پروژه ووکامرسی هست کلیه هوک ها و توابع را در فایل src/inc/woo.php قرار دهید. درغیر این صورت فایل را حذف کنید.

---

### Collapse

```html
<button class="btn-collapse" aria-expanded="false" aria-controls="collapse-content" id="collapse-button">
	Section 1
</button>

<div id="collapse-content" role="region" aria-labelledby="collapse-button" class="hidden">
	<p>Content for section 1</p>
</div>
```

### Tablist

```html
<div class="tablist">
	<div role="tablist">
		<button type="button" class="btn-collapse" id="unstyled-tabs-1-title"
			aria-selected="true" aria-controls="unstyled-tabs-1" role="tab">Tab 1</button>
		<button type="button" class="btn-collapse" id="unstyled-tabs-2-title"
			aria-selected="false" aria-controls="unstyled-tabs-2" role="tab">Tab 2</button>
		<button type="button" class="btn-collapse" id="unstyled-tabs-3-title"
			aria-selected="false" aria-controls="unstyled-tabs-3" role="tab">Tab 3</button>
	</div>

	<div id="unstyled-tabs-1" role="tabpanel" aria-labelledby="unstyled-tabs-1-title">
		This is the <em>first</em> item's tab body.
	</div>
	<div id="unstyled-tabs-2" class="hidden" role="tabpanel" aria-labelledby="unstyled-tabs-2-title">
		This is the <em>second</em> item's tab body.
	</div>
	<div id="unstyled-tabs-3" class="hidden" role="tabpanel" aria-labelledby="unstyled-tabs-3-title">
		This is the <em>third</em> item's tab body.
	</div>
</div>
```

### Accordion

```html
<div class="accordion">
	<div class="accordion-item">
		<button class="btn-collapse" aria-expanded="false"
				aria-controls="section1" id="accordion1id">Section 1</button>
		<div id="section1" role="region" aria-labelledby="accordion1id" class="hidden">
			<p>Content for section 1</p>
		</div>
	</div>

	<div class="accordion-item">
		<button class="btn-collapse" aria-expanded="false"
				id="accordion2id" aria-controls="section2">Section 2</button>
		<div id="section2" role="region" aria-labelledby="accordion2id" class="hidden">
			<p>Content for section 2</p>
		</div>
	</div>
</div>
```

---

## کتابخانه PJTailwind

> برای استفاده از **Modal**، **Drawer** و **Offcanvas** لازم است فایل این کتابخانه را فراخوانی کنید.
نحوه استفاده از کد ها در ادامه توضیح داده شده.

### Modal

```html
<button type="button" data-modal="#exampleModal" aria-haspopup="dialog" aria-controls="exampleModal">Modal</button>

<div id="exampleModal" class="modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" aria-modal="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content card max-w-2xl rounded-2xl">
			<div class="modal-header">
				<h3 id="exampleModalLabel" class="modal-title">عنوان مودال</h3>
				<button type="button" class="btn-close" aria-label="بستن مودال">&times</button>
			</div>
			<div class="modal-body"></div>
		</div>
	</div>
</div>
```

### Offcanvas (right / left / bottom / top)

```html
<button type="button" data-offcanvas="#offcanvasStart" aria-haspopup="dialog" aria-controls="offcanvasStart">از راست</button>

<div id="offcanvasStart" class="offcanvas offcanvas-right" tabindex="-1" role="dialog"
	aria-labelledby="offcanvasStartLabel" aria-hidden="true" aria-modal="true">
	<div class="offcanvas-header">
		<h3 id="offcanvasStartLabel" class="offcanvas-title">منوی کناری</h3>
		<button type="button" class="btn-close" aria-label="بستن منو"></button>
	</div>
	<div class="offcanvas-body">lorem</div>
</div>
```

### Drawer

```html
<button type="button" data-drawer="#simpleDrawer" aria-haspopup="dialog" aria-controls="simpleDrawer">open</button>

<div id="simpleDrawer" class="drawer" tabindex="-1" role="dialog"
	aria-labelledby="simpleDrawerLabel" aria-hidden="true" aria-modal="true">
	<div class="drawer-handle" aria-label="دستگیره کشو" role="separator"></div>
	<div class="drawer-header">
		<h3 id="simpleDrawerLabel" class="drawer-title">محتوای طولانی</h3>
	</div>
	<div class="drawer-body">
		Lorem ipsum dolor sit amet consectetur, adipisicing elit. Tenetur, accusantium id optio voluptates
		veritatis aliquid quibusdam ut.
	</div>
</div>
```

### Events or API

```javascript
pjtail.open(element); //open element
pjtail.close(element); //close element

//On show or hide trigger events
element.addEventListener('PJTYPE:show', (e) => {
	console.log( e.detail.trigger );
});
element.addEventListener('PJTYPE:hide', (e) => {
	console.log( e.detail.trigger );
});

//PJTYPE == pjmodal,pjoffcanvas, pjdrawer
```

---

## نمونه کدهای JS برای پروژه ها

### Contact Form Ajax

```javascript
//Jquery Function for Ajax
$(document).on('submit', 'form.arvand-contactform', function(e){
	e.preventDefault();
	const $FORM = $(this);
	const params = $FORM.serializeArray();
	$.post(ajaxURL, {action: 'arvand_contact_form', form_data: params}, function(resp){
		if( resp.success ) {
			//Display success message by resp.message
		} else {
			//Display error message by resp.message
		}
	});
});
```

### Like Dislike Button

```javascript
$(document).on('click', '[data-ilikeit] button', function(e){
	e.preventDefault();
	const $btn		= $(this);
	const $wrap		= $btn.closest('[data-ilikeit]');
	const params	= $wrap.data('ilikeit').split(':');

	if( $btn.hasClass('btn-like') ){
		params.push('like');
	} else {
		params.push('dislike');
	}

	$wrap.find('button').prop('disabled', true).find('.count').html('&hellip;');
	$.post(ajaxURL, {action: 'entry_user_reaction', params: params}, function(resp){
		if( resp.success ) {
			$wrap.find('.btn-like .count').html(resp.data.like);
			$wrap.find('.btn-dislike .count').html(resp.data.dislike);
			$btn.toggleClass('fill');
		} else {
			alert( resp.data );
		}
	});
	$wrap.find('button').prop('disabled', false);
});
```

### Scroll SPY
```javascript
document.querySelectorAll('[data-scrollspy]').forEach(element => {
	const wrapper = document.getElementById(element.dataset.scrollspy);
	if( ! wrapper ) {
		console.error('Scrollspy wrapper not found for:', element.dataset.scrollspy);
		return;
	}
	const items = [...element.querySelectorAll('a')];
	if( items.length === 0) {
		console.warn('No items found inside:', element);
		return;
	}

	const firstItem = items[0];
	firstItem.classList.add('is-active');
	let ticking = false;
	window.addEventListener('scroll', () => {
		if( !ticking ) {
			window.requestAnimationFrame(() => {
				const firstTarget = document.querySelector(firstItem.hash);
				if (!firstTarget) return;
				const spygap = firstTarget.offsetTop - wrapper.offsetHeight;
				if (window.pageYOffset <= spygap) {
					items.forEach(link => link.classList.remove('is-active'));
					firstItem.classList.add('is-active');
					element.scrollLeft = firstItem.offsetLeft - element.offsetLeft;
				} else {
					items.forEach(link => {
						const section = document.querySelector(link.hash);
						if (!section) return;

						const sectionTop = section.offsetTop - 100;
						const sectionBottom = sectionTop + section.offsetHeight;

						if (window.pageYOffset >= sectionTop && window.pageYOffset <= sectionBottom) {
							items.forEach(l => l.classList.remove('is-active'));
							link.classList.add('is-active');
							element.scrollLeft = link.offsetLeft - element.offsetLeft;
						}
					});
				}
				ticking = false;
			});
			ticking = true;
		}
	});
});
```

### Tooltip
```javascript
document.querySelectorAll('[data-tooltip]').forEach(el => {
	let tip = null;

	el.addEventListener('mouseenter', () => {
		tip = Object.assign(document.createElement('span'), {
			className: 'tooltip',
			textContent: el.dataset.tooltip
		});
		document.body.appendChild(tip);
		if (el.dataset.tooltipClass) {
			el.dataset.tooltipClass.split(' ').forEach(c => c.trim() && tip.classList.add(c));
		}
		const r = el.getBoundingClientRect();
		const tr = tip.getBoundingClientRect();
		tip.style.cssText = `top:${r.top - tr.height - 8 + scrollY}px;left:${r.left + r.width/2 - tr.width/2}px;`;
		setTimeout(() => tip.style.opacity = '1', 10);
	});
	
	el.addEventListener('mouseleave', () => {
		tip && (tip.style.opacity = '0', setTimeout(() => tip.remove(), 200));
	});
});
```

### Expand More Content
```javascript
document.querySelectorAll('.limit-maxheight').forEach(function(container) {
	const content = container.querySelector('.content');
	const btn = container.querySelector('.more-button');
	
	let maxH = parseFloat(getComputedStyle(content).maxHeight);
	if (isNaN(maxH)) maxH = 150;
	if (content.scrollHeight > maxH + 1) {
		btn.disabled = false;
		btn.onclick = () => {
			content.style.maxHeight = 'none';
			btn.remove();
		};
	}
});
```
```css
.tooltip{
	@apply absolute opacity-0 z-9999 text-card bg-foreground rounded-lg py-1.5 px-2 text-xs transition-opacity;
	&:after {
		content: '';
		top: 100%;
		left: 50%;
		position: absolute;
		transform: translateX(-50%);
		border-width: 6px;
		border-style: solid;
		border-color: var(--color-foreground) transparent transparent transparent;
	}
}
```

### Form Validation
```javascript
document.querySelectorAll('.form-validation').forEach(form => {
	form.addEventListener('submit', (e) => {
		if( !form.checkValidity() ) {
			e.preventDefault();
			e.stopPropagation();
		}
		form.classList.add('was-validated')
	}, false);
	const inputs = form.querySelectorAll('input, textarea, select');
	inputs.forEach(input => {
		input.oninvalid = e => {
			e.target.setCustomValidity('');
			if( !e.target.validity.valid && e.target.title.length > 0 ) {
				e.target.setCustomValidity( e.target.title );
			}
		}
		input.oninput = e => {
			e.target.setCustomValidity('');
		}
	});
});
```