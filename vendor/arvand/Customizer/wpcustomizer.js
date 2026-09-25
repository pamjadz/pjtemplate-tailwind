( function ( $ ) {
	'use strict';
	function uid( prefix ) {
		return ( prefix || 'cr' ) + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2,7);
	}
	const entityMap = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;', '/':'&#x2F;', '\\':'&#92;' };
	function escapeHtml( str ) {
		return String( str ).replace( /\r?\n/g, '<br />' ).replace( /[&<>"'\/\\]/g, s => entityMap[s] );
	}

	function collectItemValues( $container ) {
		const values = {};
		let id = $container.find( '.social-repeater-box-id' ).val();
		if ( ! id ) {
			id = uid( 'cr-item' );
			$container.find( '.social-repeater-box-id' ).val( id );
		}
		values.id = id;

		$container.find( '.customizer-repeater-field' ).each( function () {
			const $el  = $( this );
			const key  = ( $el.attr( 'class' ) || '' )
				.split( /\s+/ )
				.map( c => c.replace( 'customizer-repeater-', '' ) )
				.find( c => c && c !== 'field' );
			if ( ! key ) return;

			const raw  = $el.val() || '';
			const type = $el.is( 'textarea' ) ? 'textarea'
				: $el.hasClass( 'custom-media-url' )  ? 'image'
				: $el.attr( 'data-type' ) || 'text';
			values[ key ] = ( type === 'textarea' ) ? escapeHtml( raw ) : raw;
		} );
		return values;
	}

	function refreshAll() {
		$( '.customizer-repeater-general-control-repeater' ).each( function () {
			const $repeater = $( this );
			const items     = [];

			$repeater.find('.customizer-repeater-general-control-repeater-container' ).each( function () {
				const vals = collectItemValues( $( this ) );
				const hasValue = Object.entries( vals ).some( ( [ k, v ] ) => k !== 'id' && v !== '' );
				if ( hasValue ) items.push( vals );
			});

			$repeater.find( '.customizer-repeater-colector' )
				.val( JSON.stringify( items ) )
				.trigger( 'change' );
		} );
	}

	function initMediaUpload() {
		$( 'body' ).on( 'click', '.customizer-repeater-custom-media-button', function () {
			const $urlInput = $( this ).siblings( '.custom-media-url' );
			wp.media.editor.send.attachment = function ( props, attachment ) {
				const sizeUrl = attachment.sizes?.[ props.size ]?.url || attachment.url;
				$urlInput.val( sizeUrl ).trigger( 'change' );
			};

			wp.media.editor.open( this );
			return false;
		} );
	}

	function cloneItem( $btn ) {
		const $wrapper = $btn.parent();
		const $first   = $wrapper.find( '.customizer-repeater-general-control-repeater-container:first' );
		const $clone   = $first.clone( true, true );
		const newId    = uid( 'cr-item' );

		$clone.find( 'input[type="text"], input[type="url"], textarea' ).val( '' );
		$clone.find( '.social-repeater-box-id' ).val( newId );
		$clone.find( '.wp-picker-container' ).each( function () {
			const $old   = $( this );
			const cls    = $old.find( 'input[type="text"]' ).attr( 'class' ) || '';
			const $input = $( '<input type="text">' ).addClass( cls );
			$old.replaceWith( $input );
			$input.wpColorPicker( colorPickerOptions );
		} );
		$clone.find( '.social-repeater-general-control-remove-field' ).show();

		$wrapper.find( '.customizer-repeater-colector' ).before( $clone );
		refreshAll();
	}

	const colorPickerOptions = {
		change: () => refreshAll()
	};
	$( function () {
		const $controls = $( '#customize-theme-controls' );
		$controls.on( 'click', '.customizer-repeater-customize-control-title', function () {
			const $content = $( this ).next();
			$content.slideToggle( 'medium', function () {
				$( this ).prev().toggleClass( 'repeater-expanded', $( this ).is( ':visible' ) );
				if ( $( this ).is( ':visible' ) ) $( this ).css( 'display', 'block' );
			} );
		} );
		$controls.on( 'click', '.customizer-repeater-new-field', function () {
			cloneItem( $( this ) );
			return false;
		} );
		$controls.on( 'click', '.social-repeater-general-control-remove-field', function () {
			$( this ).closest( '.customizer-repeater-general-control-repeater-container' )
				.hide( 400, function () {
					$( this ).remove();
					refreshAll();
				} );
			return false;
		} );

		$controls.on(
			'input change',
			'.customizer-repeater-field, .custom-media-url',
			function () { refreshAll(); }
		);

		$( 'input.customizer-repeater-field[data-type="color"]' ).wpColorPicker( colorPickerOptions );

		$( '.customizer-repeater-general-control-droppable' ).sortable( {
			axis:   'y',
			update: refreshAll
		});

		initMediaUpload();
	} );

} )( jQuery );
