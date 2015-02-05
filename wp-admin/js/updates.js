window.wp = window.wp || {};

(function( $, wp ) {

	wp.updates = {};

	/**
	 * User nonce for ajax calls.
	 *
	 * @since 4.2.0
	 *
	 * @var string
	 */
	wp.updates.ajaxNonce = window._wpUpdatesSettings.ajax_nonce;

	/**
	 * Localized strings.
	 *
	 * @since 4.2.0
	 *
	 * @var object
	 */
	wp.updates.l10n = window._wpUpdatesSettings.l10n;

	/**
	 * Flag if we're waiting for an install/update to complete.
	 *
	 * @since 4.2.0
	 *
	 * @var bool
	 */
	wp.updates.updateLock = false;

	/**
	 * If the user tries to install/update a plugin while an install/update is
	 * already happening, it can be placed in this queue to perform later.
	 *
	 * @since 4.2.0
	 *
	 * @var array
	 */
	wp.updates.updateQueue = [];

	/**
	 * Decrement update counts throughout the various menus.
	 *
	 * @since 3.9.0
	 *
	 * @param {string} updateType
	 */
	wp.updates.decrementCount = function( upgradeType ) {
		var count,
		    pluginCount,
		    $adminBarUpdateCount = $( '#wp-admin-bar-updates .ab-label' ),
		    $dashboardNavMenuUpdateCount = $( 'a[href="update-core.php"] .update-plugins' )
		    $pluginsMenuItem = $( '#menu-plugins' );


		count = $adminBarUpdateCount.text();
		count = parseInt( count, 10 ) - 1;
		if ( count < 0 || isNaN( count ) ) {
			return;
		}
		$( '#wp-admin-bar-updates .ab-item' ).removeAttr( 'title' );
		$adminBarUpdateCount.text( count );


		$dashboardNavMenuUpdateCount.each( function( index, elem ) {
			elem.className = elem.className.replace( /count-\d+/, 'count-' + count );
		} );
		$dashboardNavMenuUpdateCount.removeAttr( 'title' );
		$dashboardNavMenuUpdateCount.find( '.update-count' ).text( count );

		if ( 'plugin' === upgradeType ) {
			pluginCount = $pluginsMenuItem.find( '.plugin-count' ).eq(0).text();
			pluginCount = parseInt( pluginCount, 10 ) - 1;
			if ( pluginCount < 0 || isNaN( pluginCount ) ) {
				return;
			}
			$pluginsMenuItem.find( '.plugin-count' ).text( pluginCount );
			$pluginsMenuItem.find( '.update-plugins' ).each( function( index, elem ) {
				elem.className = elem.className.replace( /count-\d+/, 'count-' + pluginCount );
			} );

			if (pluginCount > 0 ) {
				$( '.subsubsub .upgrade .count' ).text( '(' + pluginCount + ')' );
			} else {
				$( '.subsubsub .upgrade' ).remove();
			}
		}
	};

	/**
	 * Send an Ajax request to the server to update a plugin.
	 *
	 * @since 4.2.0
	 *
	 * @param {string} plugin
	 * @param {string} slug
	 */
	wp.updates.updatePlugin = function( plugin, slug ) {
		var $message;
		if ( 'plugins' === pagenow || 'plugins-network' === pagenow ) {
			$message = $( '#' + slug ).next().find( '.update-message' );
		} else if ( 'plugin-install' === pagenow ) {
			$message = $( '.plugin-card-' + slug ).find( '.update-now' );
		}

		$message.addClass( 'updating-message' );
		$message.text( wp.updates.l10n.updating );

		if ( wp.updates.updateLock ) {
			wp.updates.updateQueue.push( {
				type: 'update-plugin',
				data: {
					plugin: plugin,
					slug: slug
				}
			} );
			return;
		}

		wp.updates.updateLock = true;

		var data = {
			'action':      'update-plugin',
			'_ajax_nonce': wp.updates.ajaxNonce,
			'plugin':      plugin,
			'slug':        slug
		};

		$.ajax( {
			type:      'post',
			url:       ajaxurl,
			data:      data,
			complete:  wp.updates.updateRequestComplete
		} );
	};

	/**
	 * After an update attempt has completed, deal with the response.
	 *
	 * @since 4.2.0
	 *
	 * @param  {jqXHR} jqxhr The jQuery XMLHttpRequest for the request.
	 */
	wp.updates.updateRequestComplete = function( jqxhr ) {
		wp.updates.updateLock = false;
		if ( jqxhr.responseJSON && jqxhr.responseJSON.success ) {
			wp.updates.updateSuccess( jqxhr.responseJSON );
		} else {
			var alertText = wp.updates.l10n.updateFailed;
			if ( jqxhr.responseJSON && jqxhr.responseJSON.data && jqxhr.responseJSON.data.error ) {
				 alertText += ': ' + jqxhr.responseJSON.data.error;
			}
			alert( alertText );
			if ( jqxhr.responseJSON && jqxhr.responseJSON.data && jqxhr.responseJSON.data.slug ) {
				wp.updates.updateError( jqxhr.responseJSON );
			}
		}
		/**
		 * Check the queue.
		 */
		wp.updates.queueChecker();
	}

	/**
	 * On a successful plugin update, update the UI with the result.
	 *
	 * @since 4.2.0
	 *
	 * @param {object} response
	 */
	wp.updates.updateSuccess = function( response ) {
		var $message;
		if ( 'plugins' === pagenow || 'plugins-network' === pagenow ) {
			$message = $( '#' + response.data.slug ).next().find( '.update-message' );
			$( '#' + response.data.slug ).addClass( 'updated' ).removeClass( 'update' );
			$( '#' + response.data.slug + '-update' ).addClass( 'updated' ).removeClass( 'update' );
		} else if ( 'plugin-install' === pagenow ) {
			$message = $( '.plugin-card-' + response.data.slug ).find( '.update-now' );
			$message.addClass( 'button-disabled' );
		}

		$message.removeClass( 'updating-message' ).addClass( 'updated-message' );
		$message.text( wp.updates.l10n.updated );

		wp.updates.decrementCount( 'plugin' );
	};

	/**
	 * On a plugin update error, update the UI appropriately.
	 *
	 * @since 4.2.0
	 *
	 * @param {object} response
	 */
	wp.updates.updateError = function( response ) {
		var $message;
		if ( 'plugins' === pagenow || 'plugins-network' === pagenow ) {
			$message = $( '#' + response.data.slug ).next().find( '.update-message' );
		} else if ( 'plugin-install' === pagenow ) {
			$message = $( '.plugin-card-' + response.data.slug ).find( '.update-now' );
		}
		$message.removeClass( 'updating-message' );
		$message.text( wp.updates.l10n.updateFailed );
	};

	/**
	 * Send an Ajax request to the server to install a plugin.
	 *
	 * @since 4.2.0
	 *
	 * @param {string} slug
	 */
	wp.updates.installPlugin = function( slug ) {
		var $message = $( '.plugin-card-' + slug ).find( '.install-now' );

		$message.addClass( 'updating-message' );
		$message.text( wp.updates.l10n.installing );

		if ( wp.updates.updateLock ) {
			wp.updates.updateQueue.push( {
				type: 'install-plugin',
				data: {
					slug: slug
				}
			} );
			return;
		}

		wp.updates.updateLock = true;

		var data = {
			'action':      'install-plugin',
			'_ajax_nonce': wp.updates.ajaxNonce,
			'slug':        slug
		};

		$.ajax( {
			type:     'post',
			url:      ajaxurl,
			data:     data,
			complete: wp.updates.installRequestComplete
		} );
	};


	/**
	 * After an installation attempt has completed, deal with the response.
	 *
	 * @since 4.2.0
	 *
	 * @param {jqXHR} jqxhr The jQuery XMLHttpRequest for the request.
	 */
	wp.updates.installRequestComplete = function( jqxhr ) {
		wp.updates.updateLock = false;
		if ( jqxhr.responseJSON && jqxhr.responseJSON.success ) {
			wp.updates.installSuccess( jqxhr.responseJSON );
		} else {
			var alertText = wp.updates.l10n.installFailed;
			if ( jqxhr.responseJSON && jqxhr.responseJSON.data && jqxhr.responseJSON.data.error ) {
				 alertText += ': ' + jqxhr.responseJSON.data.error;
			}
			alert( alertText );
			if ( jqxhr.responseJSON && jqxhr.responseJSON.data && jqxhr.responseJSON.data.slug ) {
				wp.updates.installError( jqxhr.responseJSON );
			}
		}
		/**
		 * Check the queue.
		 */
		wp.updates.queueChecker();
	};

	/**
	 * On plugin install success, update the UI with the result.
	 *
	 * @since 4.2.0
	 *
	 * @param {object} response
	 */
	wp.updates.installSuccess = function( response ) {
		var $message = $( '.plugin-card-' + response.data.slug ).find( '.install-now' );

		$message.removeClass( 'updating-message' ).addClass( 'updated-message button-disabled' );
		$message.text( wp.updates.l10n.installed );
	};

	/**
	 * On plugin install failure, update the UI appropriately.
	 *
	 * @since 4.2.0
	 *
	 * @param {object} response
	 */
	wp.updates.installError = function( response ) {
		var $message = $( '.plugin-card-' + response.data.slug ).find( '.install-now' );

		$message.removeClass( 'updating-message' );
		$message.text( wp.updates.l10n.installNow );
	};


	/**
	 * If an install/update job has been placed in the queue, queueChecker pulls it out and runs it.
	 *
	 * @since 4.2.0
	 */
	wp.updates.queueChecker = function() {
		if ( wp.updates.updateLock || wp.updates.updateQueue.length <= 0 ) {
			return;
		}

		var job = wp.updates.updateQueue.shift();

		switch ( job.type ) {
			case 'update-plugin':
				wp.updates.updatePlugin( job.data.plugin, job.data.slug );
				break;
			case 'install-plugin':
				wp.updates.installPlugin( job.data.slug );
				break;
			default:
				console.log( 'Failed to exect queued update job.' );
				console.log( job );
				break;
		}
	};

	$( document ).ready( function() {
		$( '.plugin-update-tr .update-link' ).on( 'click', function( e ) {
			e.preventDefault();
			var $row = $( e.target ).parents( '.plugin-update-tr' );
			wp.updates.updatePlugin( $row.data( 'plugin' ), $row.data( 'slug' ) );
		} );

		$( '#bulk-action-form' ).on( 'submit', function( e ) {
			var checkbox, plugin, slug;

			if ( $( '#bulk-action-selector-top' ).val() == 'update-selected' ) {
				e.preventDefault();

				$( 'input[name="checked[]"]:checked' ).each( function( index, elem ) {
					$checkbox = $( elem );
					plugin = $checkbox.val();
					slug = $checkbox.parents( 'tr' ).prop( 'id' );

					wp.updates.updatePlugin( plugin, slug );

					$checkbox.attr( 'checked', false );
				} );
			}
		} );

		$( '.plugin-card .update-now' ).on( 'click', function( e ) {
			e.preventDefault();
			$button = $( e.target );
			wp.updates.updatePlugin( $button.data( 'plugin' ), $button.data( 'slug' ) );
		} );

		$( '.plugin-card .install-now' ).on( 'click', function( e ) {
			e.preventDefault();
			$button = $( e.target );
			if ( $button.hasClass( 'button-disabled' ) ) {
				return;
			}
			wp.updates.installPlugin( $button.data( 'slug' ) );
		} );
	} );

	$( window ).on( 'message', function( e ) {
		var event = e.originalEvent,
			message,
			loc = document.location,
			expectedOrigin = loc.protocol + '//' + loc.hostname;

		if ( event.origin !== expectedOrigin ) {
			return;
		}

		message = $.parseJSON( event.data );

		if ( typeof message.action === 'undefined' || message.action !== 'decrementUpdateCount' ) {
			return;
		}

		wp.updates.decrementCount( message.upgradeType );

	} );

})( jQuery, window.wp );
