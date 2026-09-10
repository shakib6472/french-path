/**
 * Package edit screen: the ordered sublevel picker.
 *
 * Vanilla JS, no jQuery. Reordering uses buttons rather than drag and drop so
 * it works with a keyboard and needs no library.
 */
( function () {
	'use strict';

	var data = window.frenchPathData || {};

	var list = document.getElementById( 'french-path-course-list' );
	var empty = document.getElementById( 'french-path-course-empty' );
	var picker = document.getElementById( 'french-path-course-add' );
	var addButton = document.getElementById( 'french-path-course-add-button' );

	if ( ! list || ! picker || ! addButton ) {
		return;
	}

	function refreshEmpty() {
		if ( ! empty ) {
			return;
		}

		empty.hidden = list.children.length > 0;
	}

	function has( courseId ) {
		return !! list.querySelector( '[data-course="' + courseId + '"]' );
	}

	function makeItem( courseId, title ) {
		var item = document.createElement( 'li' );
		item.className = 'french-path-item';
		item.setAttribute( 'data-course', courseId );

		var field = document.createElement( 'input' );
		field.type = 'hidden';
		field.name = 'french_path_courses[]';
		field.value = courseId;

		var label = document.createElement( 'span' );
		label.className = 'french-path-item-title';
		label.textContent = title;

		var actions = document.createElement( 'span' );
		actions.className = 'french-path-item-actions';

		[
			[ 'french-path-up', '↑', data.moveUp || 'Move up' ],
			[ 'french-path-down', '↓', data.moveDown || 'Move down' ],
			[ 'french-path-remove', '×', data.remove || 'Remove' ]
		].forEach( function ( spec ) {
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'button-link ' + spec[ 0 ];
			button.textContent = spec[ 1 ];
			button.setAttribute( 'aria-label', spec[ 2 ] );
			actions.appendChild( button );
		} );

		item.appendChild( field );
		item.appendChild( label );
		item.appendChild( actions );

		return item;
	}

	addButton.addEventListener( 'click', function () {
		var courseId = picker.value;
		var option = picker.options[ picker.selectedIndex ];

		if ( ! courseId || ! option ) {
			return;
		}

		if ( has( courseId ) ) {
			window.alert( data.alreadyAdded || 'That sublevel is already in this package.' );
			return;
		}

		list.appendChild( makeItem( courseId, option.textContent ) );
		refreshEmpty();
	} );

	list.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button' );

		if ( ! button ) {
			return;
		}

		var item = button.closest( '.french-path-item' );

		if ( ! item ) {
			return;
		}

		if ( button.classList.contains( 'french-path-remove' ) ) {
			item.remove();
			refreshEmpty();
			return;
		}

		if ( button.classList.contains( 'french-path-up' ) && item.previousElementSibling ) {
			list.insertBefore( item, item.previousElementSibling );
			return;
		}

		if ( button.classList.contains( 'french-path-down' ) && item.nextElementSibling ) {
			list.insertBefore( item.nextElementSibling, item );
		}
	} );

	refreshEmpty();
}() );
