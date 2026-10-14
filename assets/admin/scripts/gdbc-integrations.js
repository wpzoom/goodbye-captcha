/**
 * WPBruiser: Integrations screen.
 *
 * Saves the switches over AJAX as soon as they change, and filters the cards.
 * Configuration comes from wp_localize_script() as window.GdbcIntegrationsConfig.
 */
( function ( window, document ) {
	'use strict';

	var config = window.GdbcIntegrationsConfig || {};
	var root = document.querySelector( '.gdbc-int' );

	if ( ! root || ! config.ajaxUrl ) {
		return;
	}

	var i18n = config.i18n || {};
	var liveRegion = document.getElementById( 'gdbc-int-live' );

	function announce( message ) {
		if ( ! liveRegion ) {
			return;
		}
		liveRegion.textContent = '';
		window.setTimeout( function () {
			liveRegion.textContent = message;
		}, 50 );
	}

	function format( template, values ) {
		var index = 0;
		return String( template ).replace( /%(\d+\$)?[ds]/g, function ( match, position ) {
			var i = position ? parseInt( position, 10 ) - 1 : index++;
			return values[ i ] !== undefined ? values[ i ] : match;
		} );
	}

	function statusText( enabled, total ) {
		if ( enabled < 1 ) {
			return i18n.off;
		}
		if ( total > 1 && enabled < total ) {
			return format( i18n.partial, [ enabled, total ] );
		}
		return i18n.on;
	}

	function setChecked( button, checked ) {
		button.setAttribute( 'aria-checked', checked ? 'true' : 'false' );
	}

	function updateCard( card, forms ) {
		var enabled = 0;

		forms.forEach( function ( form ) {
			if ( form.Enabled ) {
				enabled++;
			}
			var formSwitch = card.querySelector( '.gdbc-switch[data-option="' + window.CSS.escape( form.Option ) + '"]' );
			if ( formSwitch ) {
				setChecked( formSwitch, form.Enabled );
			}
		} );

		var mainSwitch = card.querySelector( '[data-role="main-switch"]' );
		if ( mainSwitch ) {
			setChecked( mainSwitch, enabled > 0 );
		}

		var status = card.querySelector( '[data-role="status"]' );
		if ( status ) {
			status.textContent = statusText( enabled, forms.length );
		}

		card.setAttribute( 'data-enabled', enabled > 0 ? '1' : '0' );
		card.classList.toggle( 'is-on', enabled > 0 );

		updateCounts();
		applyFilters();
	}

	function updateCounts() {
		var counter = root.querySelector( '[data-count-for="enabled"]' );
		if ( counter ) {
			counter.textContent = root.querySelectorAll( '.gdbc-int-card[data-enabled="1"]' ).length;
		}
	}

	function save( card, button, option, enabled ) {
		var name = ( card.querySelector( '.gdbc-int-card__title' ) || {} ).textContent || '';
		var body = new window.URLSearchParams();

		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		body.append( 'module', card.getAttribute( 'data-module' ) );
		body.append( 'option', option || '' );
		body.append( 'enabled', enabled ? '1' : '0' );

		card.classList.add( 'is-saving' );
		button.setAttribute( 'aria-busy', 'true' );

		window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} )
			.then( function ( response ) {
				return response.json().catch( function () {
					return { success: false };
				} );
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( result && result.data && result.data.message ? result.data.message : i18n.error );
				}
				updateCard( card, result.data.forms );
				announce( format( enabled ? i18n.turnedOn : i18n.turnedOff, [ name ] ) );
			} )
			.catch( function ( error ) {
				// Put the switch back the way it was.
				setChecked( button, ! enabled );
				showError( card, error && error.message ? error.message : i18n.error );
			} )
			.then( function () {
				card.classList.remove( 'is-saving' );
				button.removeAttribute( 'aria-busy' );
			} );
	}

	function showError( card, message ) {
		var existing = card.querySelector( '.gdbc-int-error' );
		if ( ! existing ) {
			existing = document.createElement( 'p' );
			existing.className = 'gdbc-int-error';
			existing.setAttribute( 'role', 'alert' );
			card.insertBefore( existing, card.querySelector( '.gdbc-int-card__foot' ) );
		}
		existing.textContent = message;
		window.setTimeout( function () {
			if ( existing.parentNode ) {
				existing.parentNode.removeChild( existing );
			}
		}, 6000 );
	}

	root.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.gdbc-switch' );
		if ( ! button || button.getAttribute( 'aria-busy' ) === 'true' ) {
			return;
		}

		var card = button.closest( '.gdbc-int-card' );
		var enabled = button.getAttribute( 'aria-checked' ) !== 'true';

		setChecked( button, enabled ); // show the change straight away
		save( card, button, button.getAttribute( 'data-option' ), enabled );
	} );

	// Filters and search.
	var activeFilter = 'all';
	var searchInput = document.getElementById( 'gdbc-int-search' );
	var emptyMessage = root.querySelector( '.gdbc-int-empty' );

	function matchesFilter( card ) {
		switch ( activeFilter ) {
			case 'enabled':
				return card.getAttribute( 'data-enabled' ) === '1';
			case 'free':
			case 'pro':
				return card.getAttribute( 'data-tier' ) === activeFilter;
			default:
				return true;
		}
	}

	function applyFilters() {
		var term = searchInput ? searchInput.value.trim().toLowerCase() : '';
		var visible = 0;

		root.querySelectorAll( '.gdbc-int-section' ).forEach( function ( section ) {
			var visibleInSection = 0;

			section.querySelectorAll( '.gdbc-int-card' ).forEach( function ( card ) {
				var show = matchesFilter( card ) && ( '' === term || card.getAttribute( 'data-search' ).indexOf( term ) !== -1 );
				card.hidden = ! show;
				visibleInSection += show ? 1 : 0;
			} );

			// A section with nothing to show is hidden, heading included.
			section.hidden = 0 === visibleInSection;
			visible += visibleInSection;
		} );

		if ( emptyMessage ) {
			emptyMessage.hidden = visible > 0;
		}
	}

	root.querySelectorAll( '.gdbc-int-filters a[data-filter]' ).forEach( function ( filterLink ) {
		filterLink.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			activeFilter = filterLink.getAttribute( 'data-filter' );
			root.querySelectorAll( '.gdbc-int-filters a[data-filter]' ).forEach( function ( other ) {
				var isCurrent = other === filterLink;
				other.classList.toggle( 'current', isCurrent );
				if ( isCurrent ) {
					other.setAttribute( 'aria-current', 'true' );
				} else {
					other.removeAttribute( 'aria-current' );
				}
			} );
			applyFilters();
		} );
	} );

	if ( searchInput ) {
		searchInput.addEventListener( 'input', applyFilters );
	}
} )( window, document );
