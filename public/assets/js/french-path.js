/**
 * Front end behaviour.
 *
 * Only the buying options dialog. Vanilla JS, no jQuery, no dependencies.
 */
( function () {
	'use strict';

	function dialogFor( trigger ) {
		var id = trigger.getAttribute( 'data-french-path-open' );

		return id ? document.getElementById( id ) : null;
	}

	document.addEventListener( 'click', function ( event ) {
		var open = event.target.closest( '[data-french-path-open]' );

		if ( open ) {
			var dialog = dialogFor( open );

			if ( dialog && typeof dialog.showModal === 'function' ) {
				event.preventDefault();
				dialog.showModal();
			}

			return;
		}

		var close = event.target.closest( '[data-french-path-close]' );

		if ( close ) {
			var owner = close.closest( 'dialog' );

			if ( owner ) {
				event.preventDefault();
				owner.close();
			}

			return;
		}

		// A click on the backdrop lands on the dialog element itself.
		if ( 'DIALOG' === event.target.tagName ) {
			event.target.close();
		}
	} );
}() );
