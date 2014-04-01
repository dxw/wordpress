/*global wp, Backbone, _, jQuery, WidgetCustomizer_exports */
/*exported WidgetCustomizer */
var WidgetCustomizer = ( function ($) {
	'use strict';

	var Widget,
		WidgetCollection,
		Sidebar,
		SidebarCollection,
		OldPreviewer,
		customize = wp.customize, self = {
		nonce: null,
		i18n: {
			save_btn_label: '',
			save_btn_tooltip: '',
			remove_btn_label: '',
			remove_btn_tooltip: '',
			error: ''
		},
		available_widgets: [], // available widgets for instantiating
		registered_widgets: [], // all widgets registered
		active_sidebar_control: null,
		previewer: null,
		saved_widget_ids: {},
		registered_sidebars: [],
		tpl: {
			move_widget_area: '',
			widget_reorder_nav: ''
		}
	};
	$.extend( self, WidgetCustomizer_exports );

	// Lots of widgets expect this old ajaxurl global to be available
	if ( typeof window.ajaxurl === 'undefined' ) {
		window.ajaxurl = wp.ajax.settings.url;
	}

	// Unfortunately many widgets try to look for instances under div#widgets-right,
	// so we have to add that ID to a container div in the customizer for compat
	$( '#customize-theme-controls' ).closest( 'div:not([id])' ).attr( 'id', 'widgets-right' );

	/**
	 * Set up model
	 */
	Widget = self.Widget = Backbone.Model.extend( {
		id: null,
		temp_id: null,
		classname: null,
		control_tpl: null,
		description: null,
		is_disabled: null,
		is_multi: null,
		multi_number: null,
		name: null,
		id_base: null,
		transport: 'refresh',
		params: [],
		width: null,
		height: null
	} );

	WidgetCollection = self.WidgetCollection = Backbone.Collection.extend( {
		model: Widget,

		// Controls searching on the current widget collection
		// and triggers an update event
		doSearch: function( value ) {

			// Don't do anything if we've already done this search
			// Useful because the search handler fires multiple times per keystroke
			if ( this.terms === value ) {
				return;
			}

			// Updates terms with the value passed
			this.terms = value;

			// If we have terms, run a search...
			if ( this.terms.length > 0 ) {
				this.search( this.terms );
			}

			// If search is blank, show all themes
			// Useful for resetting the views when you clean the input
			if ( this.terms === '' ) {
				this.reset( WidgetCustomizer_exports.available_widgets );
			}

			// Trigger an 'update' event
			this.trigger( 'update' );
		},

		// Performs a search within the collection
		// @uses RegExp
		search: function( term ) {
			var match, results, haystack;

			// Start with a full collection
			this.reset( WidgetCustomizer_exports.available_widgets, { silent: true } );

			// Escape the term string for RegExp meta characters
			term = term.replace( /[-\/\\^$*+?.()|[\]{}]/g, '\\$&' );

			// Consider spaces as word delimiters and match the whole string
			// so matching terms can be combined
			term = term.replace( / /g, ')(?=.*' );
			match = new RegExp( '^(?=.*' + term + ').+', 'i' );

			results = this.filter( function( data ) {
				haystack = _.union( data.get( 'name' ), data.get( 'id' ), data.get( 'description' ) );

				return match.test( haystack );
			});

			this.reset( results );
		}
	} );
	self.available_widgets = new WidgetCollection( self.available_widgets );

	Sidebar = self.Sidebar = Backbone.Model.extend( {
		after_title: null,
		after_widget: null,
		before_title: null,
		before_widget: null,
		'class': null,
		description: null,
		id: null,
		name: null,
		is_rendered: false
	} );

	SidebarCollection = self.SidebarCollection = Backbone.Collection.extend( {
		model: Sidebar
	} );
	self.registered_sidebars = new SidebarCollection( self.registered_sidebars );

	/**
	 * On DOM ready, initialize some meta functionality independent of specific
	 * customizer controls.
	 */
	self.init = function () {
		this.availableWidgetsPanel.setup();
	};
	wp.customize.bind( 'ready', function () {
		self.init();
	} );

	/**
	 * Sidebar Widgets control
	 * Note that 'sidebar_widgets' must match the Sidebar_Widgets_WP_Customize_Control::$type
	 */
	customize.controlConstructor.sidebar_widgets = customize.Control.extend( {

		/**
		 * Set up the control
		 */
		ready: function() {
			var control = this;
			control.control_section = control.container.closest( '.control-section' );
			control.section_content = control.container.closest( '.accordion-section-content' );
			control._setupModel();
			control._setupSortable();
			control._setupAddition();
			control._applyCardinalOrderClassNames();
		},

		/**
		 * Update ordering of widget control forms when the setting is updated
		 */
		_setupModel: function() {
			var control = this,
				registered_sidebar = self.registered_sidebars.get( control.params.sidebar_id );

			control.setting.bind( function( new_widget_ids, old_widget_ids ) {
				var widget_form_controls,
					sidebar_widgets_add_control,
					final_control_containers,
					removed_widget_ids = _( old_widget_ids ).difference( new_widget_ids );

				// Filter out any persistent widget_ids for widgets which have been deactivated
				new_widget_ids = _( new_widget_ids ).filter( function ( new_widget_id ) {
					var parsed_widget_id = parse_widget_id( new_widget_id );
					return !! self.available_widgets.findWhere( { id_base: parsed_widget_id.id_base } );
				} );

				widget_form_controls = _( new_widget_ids ).map( function ( widget_id ) {
					var widget_form_control = self.getWidgetFormControlForWidget( widget_id );
					if ( ! widget_form_control ) {
						widget_form_control = control.addWidget( widget_id );
					}
					return widget_form_control;
				} );

				// Sort widget controls to their new positions
				widget_form_controls.sort( function ( a, b ) {
					var a_index = _.indexOf( new_widget_ids, a.params.widget_id ),
						b_index = _.indexOf( new_widget_ids, b.params.widget_id );
					if ( a_index === b_index ) {
						return 0;
					}
					return a_index < b_index ? -1 : 1;
				} );

				sidebar_widgets_add_control = control.section_content.find( '.customize-control-sidebar_widgets' );

				// Append the controls to put them in the right order
				final_control_containers = _( widget_form_controls ).map( function( widget_form_controls ) {
					return widget_form_controls.container[0];
				} );

				// Re-sort widget form controls (including widgets form other sidebars newly moved here)
				sidebar_widgets_add_control.before( final_control_containers );
				control._applyCardinalOrderClassNames();

				// If the widget was dragged into the sidebar, make sure the sidebar_id param is updated
				_( widget_form_controls ).each( function ( widget_form_control ) {
					widget_form_control.params.sidebar_id = control.params.sidebar_id;
				} );

				// Cleanup after widget removal
				_( removed_widget_ids ).each( function ( removed_widget_id ) {

					// Using setTimeout so that when moving a widget to another sidebar, the other sidebars_widgets settings get a chance to update
					setTimeout( function () {
						var is_present_in_another_sidebar = false,
							removed_control,
							was_dragged_to_another_sidebar,
							inactive_widgets,
							removed_id_base,
							widget;

						// Check if the widget is in another sidebar
						wp.customize.each( function ( other_setting ) {
							if ( other_setting.id === control.setting.id || 0 !== other_setting.id.indexOf( 'sidebars_widgets[' ) || other_setting.id === 'sidebars_widgets[wp_inactive_widgets]' ) {
								return;
							}
							var other_sidebar_widgets = other_setting(), i;

							i = _.indexOf( other_sidebar_widgets, removed_widget_id );
							if ( -1 !== i ) {
								is_present_in_another_sidebar = true;
							}
						} );

						// If the widget is present in another sidebar, abort!
						if ( is_present_in_another_sidebar ) {
							return;
						}

						removed_control = self.getWidgetFormControlForWidget( removed_widget_id );

						// Detect if widget control was dragged to another sidebar
						was_dragged_to_another_sidebar = (
							removed_control &&
							$.contains( document, removed_control.container[0] ) &&
							! $.contains( control.section_content[0], removed_control.container[0] )
						);

						// Delete any widget form controls for removed widgets
						if ( removed_control && ! was_dragged_to_another_sidebar ) {
							wp.customize.control.remove( removed_control.id );
							removed_control.container.remove();
						}

						// Move widget to inactive widgets sidebar (move it to trash) if has been previously saved
						// This prevents the inactive widgets sidebar from overflowing with throwaway widgets
						if ( self.saved_widget_ids[removed_widget_id] ) {
							inactive_widgets = wp.customize.value( 'sidebars_widgets[wp_inactive_widgets]' )().slice();
							inactive_widgets.push( removed_widget_id );
							wp.customize.value( 'sidebars_widgets[wp_inactive_widgets]' )( _( inactive_widgets ).unique() );
						}

						// Make old single widget available for adding again
						removed_id_base = parse_widget_id( removed_widget_id ).id_base;
						widget = self.available_widgets.findWhere( { id_base: removed_id_base } );
						if ( widget && ! widget.get( 'is_multi' ) ) {
							widget.set( 'is_disabled', false );
						}
					} );

				} );
			} );

			// Update the model with whether or not the sidebar is rendered
			self.previewer.bind( 'rendered-sidebars', function ( rendered_sidebars ) {
				var is_rendered = !! rendered_sidebars[control.params.sidebar_id];
				registered_sidebar.set( 'is_rendered', is_rendered );
			} );

			// Show the sidebar section when it becomes visible
			registered_sidebar.on( 'change:is_rendered', function ( ) {
				var section_selector = '#accordion-section-sidebar-widgets-' + this.get( 'id' ), section;
				section = $( section_selector );
				if ( this.get( 'is_rendered' ) ) {
					section.stop().slideDown( function () {
						$( this ).css( 'height', 'auto' ); // so that the .accordion-section-content won't overflow
					} );
				} else {
					// Make sure that hidden sections get closed first
					if ( section.hasClass( 'open' ) ) {
						// it would be nice if accordionSwitch() in accordion.js was public
						section.find( '.accordion-section-title' ).trigger( 'click' );
					}
					section.stop().slideUp();
				}
			} );
		},

		/**
		 * Allow widgets in sidebar to be re-ordered, and for the order to be previewed
		 */
		_setupSortable: function () {
			var control = this;
			control.is_reordering = false;

			/**
			 * Update widget order setting when controls are re-ordered
			 */
			control.section_content.sortable( {
				items: '> .customize-control-widget_form',
				handle: '.widget-top',
				axis: 'y',
				connectWith: '.accordion-section-content:has(.customize-control-sidebar_widgets)',
				update: function () {
					var widget_container_ids = control.section_content.sortable( 'toArray' ), widget_ids;
					widget_ids = $.map( widget_container_ids, function ( widget_container_id ) {
						return $( '#' + widget_container_id ).find( ':input[name=widget-id]' ).val();
					} );
					control.setting( widget_ids );
				}
			} );

			/**
			 * Expand other customizer sidebar section when dragging a control widget over it,
			 * allowing the control to be dropped into another section
			 */
			control.control_section.find( '.accordion-section-title' ).droppable( {
				accept: '.customize-control-widget_form',
				over: function () {
					if ( ! control.control_section.hasClass( 'open' ) ) {
						control.control_section.addClass( 'open' );
						control.section_content.toggle( false ).slideToggle( 150, function () {
							control.section_content.sortable( 'refreshPositions' );
						} );
					}
				}
			} );

			/**
			 * Keyboard-accessible reordering
			 */
			control.container.find( '.reorder-toggle' ).on( 'click keydown', function( event ) {
				if ( event.type === 'keydown' && ! ( event.which === 13 || event.which === 32 ) ) { // Enter or Spacebar
					return;
				}

				control.toggleReordering( ! control.is_reordering );
			} );
		},

		/**
		 * Set up UI for adding a new widget
		 */
		_setupAddition: function () {
			var control = this;

			control.container.find( '.add-new-widget' ).on( 'click keydown', function( event ) {
				if ( event.type === 'keydown' && ! ( event.which === 13 || event.which === 32 ) ) { // Enter or Spacebar
					return;
				}

				if ( control.section_content.hasClass( 'reordering' ) ) {
					return;
				}

				// @todo Use an control.is_adding state
				if ( ! $( 'body' ).hasClass( 'adding-widget' ) ) {
					self.availableWidgetsPanel.open( control );
				} else {
					self.availableWidgetsPanel.close();
				}
			} );
		},

		/**
		 * Add classes to the widget_form controls to assist with styling
		 */
		_applyCardinalOrderClassNames: function () {
			var control = this;
			control.section_content.find( '.customize-control-widget_form' )
				.removeClass( 'first-widget' )
				.removeClass( 'last-widget' )
				.find( '.move-widget-down, .move-widget-up' ).prop( 'tabIndex', 0 );

			control.section_content.find( '.customize-control-widget_form:first' )
				.addClass( 'first-widget' )
				.find( '.move-widget-up' ).prop( 'tabIndex', -1 );
			control.section_content.find( '.customize-control-widget_form:last' )
				.addClass( 'last-widget' )
				.find( '.move-widget-down' ).prop( 'tabIndex', -1 );
		},


		/***********************************************************************
		 * Begin public API methods
		 **********************************************************************/

		/**
		 * Enable/disable the reordering UI
		 *
		 * @param {Boolean} toggle to enable/disable reordering
		 */
		toggleReordering: function ( toggle ) {
			var control = this;
			toggle = Boolean( toggle );
			if ( toggle === control.section_content.hasClass( 'reordering' ) ) {
				return;
			}

			control.is_reordering = toggle;
			control.section_content.toggleClass( 'reordering', toggle );

			if ( toggle ) {
				_( control.getWidgetFormControls() ).each( function ( form_control ) {
					form_control.collapseForm();
				} );
			}
		},

		/**
		 * @return {wp.customize.controlConstructor.widget_form[]}
		 */
		getWidgetFormControls: function () {
			var control = this, form_controls;

			form_controls = _( control.setting() ).map( function ( widget_id ) {
				var setting_id = widget_id_to_setting_id( widget_id ),
					form_control = customize.control( setting_id );

				if ( ! form_control ) {
					throw new Error( 'Unable to find widget_form control for ' + widget_id );
				}
				return form_control;
			} );
			return form_controls;
		},

		/**
		 * @param {string} widget_id or an id_base for adding a previously non-existing widget
		 * @returns {object} widget_form control instance
		 */
		addWidget: function ( widget_id ) {
			var control = this,
				control_html,
				customize_control_type = 'widget_form',
				customize_control,
				parsed_widget_id = parse_widget_id( widget_id ),
				widget_number = parsed_widget_id.number,
				widget_id_base = parsed_widget_id.id_base,
				widget = self.available_widgets.findWhere( {id_base: widget_id_base} ),
				setting_id,
				is_existing_widget,
				Constructor,
				widget_form_control,
				sidebar_widgets,
				setting_args;

			if ( ! widget ) {
				throw new Error( 'Widget unexpectedly not found.' );
			}
			if ( widget_number && ! widget.get( 'is_multi' ) ) {
				throw new Error( 'Did not expect a widget number to be supplied for a non-multi widget' );
			}

			// Set up new multi widget
			if ( widget.get( 'is_multi' ) && ! widget_number ) {
				widget.set( 'multi_number', widget.get( 'multi_number' ) + 1 );
				widget_number = widget.get( 'multi_number' );
			}

			control_html = $( '#widget-tpl-' + widget.get( 'id' ) ).html();
			if ( widget.get( 'is_multi' ) ) {
				control_html = control_html.replace( /<[^<>]+>/g, function ( m ) {
					return m.replace( /__i__|%i%/g, widget_number );
				} );
			} else {
				widget.set( 'is_disabled', true ); // Prevent single widget from being added again now
			}

			customize_control = $( '<li></li>' );
			customize_control.addClass( 'customize-control' );
			customize_control.addClass( 'customize-control-' + customize_control_type );
			customize_control.append( $( control_html ) );
			customize_control.find( '> .widget-icon' ).remove();
			if ( widget.get( 'is_multi' ) ) {
				customize_control.find( 'input[name="widget_number"]' ).val( widget_number );
				customize_control.find( 'input[name="multi_number"]' ).val( widget_number );
			}
			widget_id = customize_control.find( '[name="widget-id"]' ).val();
			customize_control.hide(); // to be slid-down below

			setting_id = 'widget_' + widget.get( 'id_base' );
			if ( widget.get( 'is_multi' ) ) {
				setting_id += '[' + widget_number + ']';
			}
			customize_control.attr( 'id', 'customize-control-' + setting_id.replace( /\]/g, '' ).replace( /\[/g, '-' ) );

			control.container.after( customize_control );

			// Only create setting if it doesn't already exist (if we're adding a pre-existing inactive widget)
			is_existing_widget = wp.customize.has( setting_id );
			if ( ! is_existing_widget ) {
				setting_args = {
					transport: 'refresh',
					previewer: control.setting.previewer
				};
				wp.customize.create( setting_id, setting_id, {}, setting_args );
			}

			Constructor = wp.customize.controlConstructor[customize_control_type];
			widget_form_control = new Constructor( setting_id, {
				params: {
					settings: {
						'default': setting_id
					},
					sidebar_id: control.params.sidebar_id,
					widget_id: widget_id,
					widget_id_base: widget.get( 'id_base' ),
					type: customize_control_type,
					is_new: ! is_existing_widget,
					width: widget.get( 'width' ),
					height: widget.get( 'height' ),
					is_wide: widget.get( 'is_wide' )
				},
				previewer: control.setting.previewer
			} );
			wp.customize.control.add( setting_id, widget_form_control );

			// Make sure widget is removed from the other sidebars
			wp.customize.each( function ( other_setting ) {
				if ( other_setting.id === control.setting.id ) {
					return;
				}
				if ( 0 !== other_setting.id.indexOf( 'sidebars_widgets[' ) ) {
					return;
				}
				var other_sidebar_widgets = other_setting().slice(), i;
				i = _.indexOf( other_sidebar_widgets, widget_id );
				if ( -1 !== i ) {
					other_sidebar_widgets.splice( i );
					other_setting( other_sidebar_widgets );
				}
			} );

			// Add widget to this sidebar
			sidebar_widgets = control.setting().slice();
			if ( -1 === _.indexOf( sidebar_widgets, widget_id ) ) {
				sidebar_widgets.push( widget_id );
				control.setting( sidebar_widgets );
			}

			customize_control.slideDown( function () {
				if ( is_existing_widget ) {
					widget_form_control.expandForm();
					widget_form_control.updateWidget( {
						instance: widget_form_control.setting(),
						complete: function ( error ) {
							if ( error ) {
								throw error;
							}
							widget_form_control.focus();
						}
					} );
				} else {
					widget_form_control.focus();
				}
			} );

			return widget_form_control;
		}

	} );

	/**
	 * Widget Form control
	 * Note that 'widget_form' must match the Widget_Form_WP_Customize_Control::$type
	 */
	customize.controlConstructor.widget_form = customize.Control.extend( {

		/**
		 * Set up the control
		 */
		ready: function() {
			var control = this;
			control._setupModel();
			control._setupWideWidget();
			control._setupControlToggle();
			control._setupWidgetTitle();
			control._setupReorderUI();
			control._setupHighlightEffects();
			control._setupUpdateUI();
			control._setupRemoveUI();
			control.hook( 'init' );
		},

		/**
		 * Hooks for widgets to support living in the customizer control
		 */
		hooks: {
			_default: {},
			rss: {
				formUpdated: function ( serialized_form ) {
					var control = this,
						old_widget_error = control.container.find( '.widget-error:first' ),
						new_widget_error = serialized_form.find( '.widget-error:first' );

					if ( old_widget_error.length && new_widget_error.length ) {
						old_widget_error.replaceWith( new_widget_error );
					} else if ( old_widget_error.length ) {
						old_widget_error.remove();
					} else if ( new_widget_error.length ) {
						control.container.find( '.widget-content' ).prepend( new_widget_error );
					}
				}
			}
		},

		/**
		 * Trigger an 'action' which a specific widget type can handle
		 *
		 * @param name
		 */
		hook: function ( name ) {
			var args = Array.prototype.slice.call( arguments, 1 ), handler;

			if ( this.hooks[this.params.widget_id_base] && this.hooks[this.params.widget_id_base][name] ) {
				handler = this.hooks[this.params.widget_id_base][name];
			} else if ( this.hooks._default[name] ) {
				handler = this.hooks._default[name];
			}
			if ( handler ) {
				handler.apply( this, args );
			}
		},

		/**
		 * Handle changes to the setting
		 */
		_setupModel: function () {
			var control = this, remember_saved_widget_id;

			// Remember saved widgets so we know which to trash (move to inactive widgets sidebar)
			remember_saved_widget_id = function () {
				self.saved_widget_ids[control.params.widget_id] = true;
			};
			wp.customize.bind( 'ready', remember_saved_widget_id );
			wp.customize.bind( 'saved', remember_saved_widget_id );

			control._update_count = 0;
			control.is_widget_updating = false;

			// Update widget whenever model changes
			control.setting.bind( function( to, from ) {
				if ( ! _( from ).isEqual( to ) && ! control.is_widget_updating ) {
					control.updateWidget( { instance: to } );
				}
			} );
		},

		/**
		 * Add special behaviors for wide widget controls
		 */
		_setupWideWidget: function () {
			var control = this,
				widget_inside,
				widget_form,
				customize_sidebar,
				position_widget,
				theme_controls_container;

			if ( ! control.params.is_wide ) {
				return;
			}

			widget_inside = control.container.find( '.widget-inside' );
			widget_form = widget_inside.find( '> .form' );
			customize_sidebar = $( '.wp-full-overlay-sidebar-content:first' );
			control.container.addClass( 'wide-widget-control' );

			control.container.find( '.widget-content:first' ).css( {
				'max-width': control.params.width,
				'min-height': control.params.height
			} );

			/**
			 * Keep the widget-inside positioned so the top of fixed-positioned
			 * element is at the same top position as the widget-top. When the
			 * widget-top is scrolled out of view, keep the widget-top in view;
			 * likewise, don't allow the widget to drop off the bottom of the window.
			 * If a widget is too tall to fit in the window, don't let the height
			 * exceed the window height so that the contents of the widget control
			 * will become scrollable (overflow:auto).
			 */
			position_widget = function () {
				var offset_top = control.container.offset().top,
					window_height = $( window ).height(),
					form_height = widget_form.outerHeight(),
					top;
				widget_inside.css( 'max-height', window_height );
				top = Math.max(
					0, // prevent top from going off screen
					Math.min(
						Math.max( offset_top, 0 ), // distance widget in panel is from top of screen
						window_height - form_height // flush up against bottom of screen
					)
				);
				widget_inside.css( 'top', top );
			};

			theme_controls_container = $( '#customize-theme-controls' );
			control.container.on( 'expand', function () {
				position_widget();
				customize_sidebar.on( 'scroll', position_widget );
				$( window ).on( 'resize', position_widget );
				theme_controls_container.on( 'expanded collapsed', position_widget );
			} );
			control.container.on( 'collapsed', function () {
				customize_sidebar.off( 'scroll', position_widget );
				$( window ).off( 'resize', position_widget );
				theme_controls_container.off( 'expanded collapsed', position_widget );
			} );

			// Reposition whenever a sidebar's widgets are changed
			wp.customize.each( function ( setting ) {
				if ( 0 === setting.id.indexOf( 'sidebars_widgets[' ) ) {
					setting.bind( function () {
						if ( control.container.hasClass( 'expanded' ) ) {
							position_widget();
						}
					} );
				}
			} );
		},

		/**
		 * Show/hide the control when clicking on the form title, when clicking
		 * the close button
		 */
		_setupControlToggle: function() {
			var control = this, close_btn;

			control.container.find( '.widget-top' ).on( 'click', function ( e ) {
				e.preventDefault();
				var sidebar_widgets_control = control.getSidebarWidgetsControl();
				if ( sidebar_widgets_control.is_reordering ) {
					return;
				}
				control.toggleForm();
			} );

			close_btn = control.container.find( '.widget-control-close' );
			// @todo Hitting Enter on this link does nothing; will be resolved in core with <http://core.trac.wordpress.org/ticket/26633>
			close_btn.on( 'click', function ( e ) {
				e.preventDefault();
				control.collapseForm();
				control.container.find( '.widget-top .widget-action:first' ).focus(); // keyboard accessibility
			} );
		},

		/**
		 * Update the title of the form if a title field is entered
		 */
		_setupWidgetTitle: function () {
			var control = this, update_title;

			update_title = function () {
				var title = control.setting().title,
					in_widget_title = control.container.find( '.in-widget-title' );

				if ( title ) {
					in_widget_title.text( ': ' + title );
				} else {
					in_widget_title.text( '' );
				}
			};
			control.setting.bind( update_title );
			update_title();
		},

		/**
		 * Set up the widget-reorder-nav
		 */
		_setupReorderUI: function () {
			var control = this,
				select_sidebar_item,
				move_widget_area,
				reorder_nav,
				update_available_sidebars;

			/**
			 * select the provided sidebar list item in the move widget area
			 *
			 * @param {jQuery} li
			 */
			select_sidebar_item = function ( li ) {
				li.siblings( '.selected' ).removeClass( 'selected' );
				li.addClass( 'selected' );
				var is_self_sidebar = ( li.data( 'id' ) === control.params.sidebar_id );
				control.container.find( '.move-widget-btn' ).prop( 'disabled', is_self_sidebar );
			};

			/**
			 * Add the widget reordering elements to the widget control
			 */
			control.container.find( '.widget-title-action' ).after( $( self.tpl.widget_reorder_nav ) );
			move_widget_area = $(
				_.template( self.tpl.move_widget_area, {
					sidebars: _( self.registered_sidebars.toArray() ).pluck( 'attributes' )
				} )
			);
			control.container.find( '.widget-top' ).after( move_widget_area );

			/**
			 * Update available sidebars when their rendered state changes
			 */
			update_available_sidebars = function () {
				var sidebar_items = move_widget_area.find( 'li' ), self_sidebar_item;
				self_sidebar_item = sidebar_items.filter( function(){
					return $( this ).data( 'id' ) === control.params.sidebar_id;
				} );
				sidebar_items.each( function () {
					var li = $( this ),
						sidebar_id,
						sidebar_model;

					sidebar_id = li.data( 'id' );
					sidebar_model = self.registered_sidebars.get( sidebar_id );
					li.toggle( sidebar_model.get( 'is_rendered' ) );
					if ( li.hasClass( 'selected' ) && ! sidebar_model.get( 'is_rendered' ) ) {
						select_sidebar_item( self_sidebar_item );
					}
				} );
			};
			update_available_sidebars();
			self.registered_sidebars.on( 'change:is_rendered', update_available_sidebars );

			/**
			 * Handle clicks for up/down/move on the reorder nav
			 */
			reorder_nav = control.container.find( '.widget-reorder-nav' );
			reorder_nav.find( '.move-widget, .move-widget-down, .move-widget-up' ).on( 'click keypress', function ( event ) {
				if ( event.type === 'keypress' && ( event.which !== 13 && event.which !== 32 ) ) {
					return;
				}
				$( this ).focus();

				if ( $( this ).is( '.move-widget' ) ) {
					control.toggleWidgetMoveArea();
				} else {
					var is_move_down = $( this ).is( '.move-widget-down' ),
						is_move_up = $( this ).is( '.move-widget-up' ),
						i = control.getWidgetSidebarPosition();

					if ( ( is_move_up && i === 0 ) || ( is_move_down && i === control.getSidebarWidgetsControl().setting().length - 1 ) ) {
						return;
					}

					if ( is_move_up ) {
						control.moveUp();
					} else {
						control.moveDown();
					}

					$( this ).focus(); // re-focus after the container was moved
				}
			} );

			/**
			 * Handle selecting a sidebar to move to
			 */
			control.container.find( '.widget-area-select' ).on( 'click keypress', 'li', function ( e ) {
				if ( event.type === 'keypress' && ( event.which !== 13 && event.which !== 32 ) ) {
					return;
				}
				e.preventDefault();
				select_sidebar_item( $( this ) );
			} );

			/**
			 * Move widget to another sidebar
			 */
			control.container.find( '.move-widget-btn' ).click( function () {
				control.getSidebarWidgetsControl().toggleReordering( false );

				var old_sidebar_id = control.params.sidebar_id,
					new_sidebar_id = control.container.find( '.widget-area-select li.selected' ).data( 'id' ),
					old_sidebar_widgets_setting,
					new_sidebar_widgets_setting,
					old_sidebar_widget_ids,
					new_sidebar_widget_ids,
					i;

				old_sidebar_widgets_setting = customize( 'sidebars_widgets[' + old_sidebar_id + ']' );
				new_sidebar_widgets_setting = customize( 'sidebars_widgets[' + new_sidebar_id + ']' );
				old_sidebar_widget_ids = Array.prototype.slice.call( old_sidebar_widgets_setting() );
				new_sidebar_widget_ids = Array.prototype.slice.call( new_sidebar_widgets_setting() );

				i = control.getWidgetSidebarPosition();
				old_sidebar_widget_ids.splice( i, 1 );
				new_sidebar_widget_ids.push( control.params.widget_id );

				old_sidebar_widgets_setting( old_sidebar_widget_ids );
				new_sidebar_widgets_setting( new_sidebar_widget_ids );

				control.focus();
			} );
		},

		/**
		 * Highlight widgets in preview when interacted with in the customizer
		 */
		_setupHighlightEffects: function() {
			var control = this;

			// Highlight whenever hovering or clicking over the form
			control.container.on( 'mouseenter click', function () {
				control.highlightPreviewWidget();
			} );

			// Highlight when the setting is updated
			control.setting.bind( function () {
				control.scrollPreviewWidgetIntoView();
				control.highlightPreviewWidget();
			} );

			// Highlight when the widget form is expanded
			control.container.on( 'expand', function () {
				control.scrollPreviewWidgetIntoView();
			} );
		},

		/**
		 * Set up event handlers for widget updating
		 */
		_setupUpdateUI: function () {
			var control = this,
				widget_content,
				save_btn,
				update_widget_debounced;

			widget_content = control.container.find( '.widget-content' );

			// Configure update button
			save_btn = control.container.find( '.widget-control-save' );
			save_btn.val( self.i18n.save_btn_label );
			save_btn.attr( 'title', self.i18n.save_btn_tooltip );
			save_btn.removeClass( 'button-primary' ).addClass( 'button-secondary' );
			save_btn.on( 'click', function ( e ) {
				e.preventDefault();
				control.updateWidget();
			} );

			update_widget_debounced = _.debounce( function () {
				// @todo For compatibility with other plugins, should we trigger a click event? What about form submit event?
				control.updateWidget();
			}, 250 );

			// Trigger widget form update when hitting Enter within an input
			control.container.find( '.widget-content' ).on( 'keydown', 'input', function( e ) {
				if ( 13 === e.which ) { // Enter
					e.preventDefault();
					control.updateWidget( { ignore_active_element: true } );
				}
			} );

			// Handle widgets that support live previews
			widget_content.on( 'change input propertychange', ':input', function ( e ) {
				if ( e.type === 'change' ) {
					control.updateWidget();
				} else if ( this.checkValidity && this.checkValidity() ) {
					update_widget_debounced();
				}
			} );

			// Remove loading indicators when the setting is saved and the preview updates
			control.setting.previewer.channel.bind( 'synced', function () {
				control.container.removeClass( 'previewer-loading' );
			} );
			self.previewer.bind( 'widget-updated', function ( updated_widget_id ) {
				if ( updated_widget_id === control.params.widget_id ) {
					control.container.removeClass( 'previewer-loading' );
				}
			} );

			// Update widget control to indicate whether it is currently rendered (cf. Widget Visibility)
			self.previewer.bind( 'rendered-widgets', function ( rendered_widgets ) {
				var is_rendered = !! rendered_widgets[control.params.widget_id];
				control.container.toggleClass( 'widget-rendered', is_rendered );
			} );
		},

		/**
		 * Set up event handlers for widget removal
		 */
		_setupRemoveUI: function () {
			var control = this,
				remove_btn,
				replace_delete_with_remove;

			// Configure remove button
			remove_btn = control.container.find( 'a.widget-control-remove' );
			// @todo Hitting Enter on this link does nothing; will be resolved in core with <http://core.trac.wordpress.org/ticket/26633>
			remove_btn.on( 'click', function ( e ) {
				e.preventDefault();

				// Find an adjacent element to add focus to when this widget goes away
				var adjacent_focus_target;
				if ( control.container.next().is( '.customize-control-widget_form' ) ) {
					adjacent_focus_target = control.container.next().find( '.widget-action:first' );
				} else if ( control.container.prev().is( '.customize-control-widget_form' ) ) {
					adjacent_focus_target = control.container.prev().find( '.widget-action:first' );
				} else {
					adjacent_focus_target = control.container.next( '.customize-control-sidebar_widgets' ).find( '.add-new-widget:first' );
				}

				control.container.slideUp( function() {
					var sidebars_widgets_control = self.getSidebarWidgetControlContainingWidget( control.params.widget_id ),
						sidebar_widget_ids,
						i;

					if ( ! sidebars_widgets_control ) {
						throw new Error( 'Unable to find sidebars_widgets_control' );
					}
					sidebar_widget_ids = sidebars_widgets_control.setting().slice();
					i = _.indexOf( sidebar_widget_ids, control.params.widget_id );
					if ( -1 === i ) {
						throw new Error( 'Widget is not in sidebar' );
					}
					sidebar_widget_ids.splice( i, 1 );
					sidebars_widgets_control.setting( sidebar_widget_ids );
					adjacent_focus_target.focus(); // keyboard accessibility
				} );
			} );

			replace_delete_with_remove = function () {
				remove_btn.text( self.i18n.remove_btn_label ); // wp_widget_control() outputs the link as "Delete"
				remove_btn.attr( 'title', self.i18n.remove_btn_tooltip );
			};
			if ( control.params.is_new ) {
				wp.customize.bind( 'saved', replace_delete_with_remove );
			} else {
				replace_delete_with_remove();
			}
		},

		/**
		 * Iterate over supplied inputs and create a signature string for all of them together.
		 * This string can be used to compare whether or not the form has all of the same fields.
		 *
		 * @param {jQuery} inputs
		 * @returns {string}
		 * @private
		 */
		_getInputsSignature: function ( inputs ) {
			var inputs_signatures = _( inputs ).map( function ( input ) {
				input = $( input );
				var signature_parts;
				if ( input.is( 'option' ) ) {
					signature_parts = [ input.prop( 'nodeName' ), input.prop( 'value' ) ];
				} else if ( input.is( ':checkbox, :radio' ) ) {
					signature_parts = [ input.prop( 'type' ), input.attr( 'id' ), input.attr( 'name' ), input.prop( 'value' ) ];
				} else {
					signature_parts = [ input.prop( 'nodeName' ), input.attr( 'id' ), input.attr( 'name' ), input.attr( 'type' ) ];
				}
				return signature_parts.join( ',' );
			} );
			return inputs_signatures.join( ';' );
		},

		/**
		 * Get the property that represents the state of an input.
		 *
		 * @param {jQuery|DOMElement} input
		 * @returns {string}
		 * @private
		 */
		_getInputStatePropertyName: function ( input ) {
			input = $( input );
			if ( input.is( ':radio, :checkbox' ) ) {
				return 'checked';
			} else if ( input.is( 'option' ) ) {
				return 'selected';
			} else {
				return 'value';
			}
		},

		/***********************************************************************
		 * Begin public API methods
		 **********************************************************************/

		/**
		 * @return {wp.customize.controlConstructor.sidebar_widgets[]}
		 */
		getSidebarWidgetsControl: function () {
			var control = this, setting_id, sidebar_widgets_control;

			setting_id = 'sidebars_widgets[' + control.params.sidebar_id + ']';
			sidebar_widgets_control = customize.control( setting_id );
			if ( ! sidebar_widgets_control ) {
				throw new Error( 'Unable to locate sidebar_widgets control for ' + control.params.sidebar_id );
			}
			return sidebar_widgets_control;
		},

		/**
		 * Submit the widget form via Ajax and get back the updated instance,
		 * along with the new widget control form to render.
		 *
		 * @param {object} [args]
		 * @param {Object|null} [args.instance=null]  When the model changes, the instance is sent here; otherwise, the inputs from the form are used
		 * @param {Function|null} [args.complete=null]  Function which is called when the request finishes. Context is bound to the control. First argument is any error. Following arguments are for success.
		 * @param {Boolean} [args.ignore_active_element=false] Whether or not updating a field will be deferred if focus is still on the element.
		 */
		updateWidget: function ( args ) {
			var control = this,
				instance_override,
				complete_callback,
				update_number,
				widget_content,
				element_id_to_refocus = null,
				active_input_selection_start = null,
				active_input_selection_end = null,
				params,
				data,
				inputs,
				processing,
				jqxhr;

			args = $.extend( {
				instance: null,
				complete: null,
				ignore_active_element: false
			}, args );

			instance_override = args.instance;
			complete_callback = args.complete;

			control._update_count += 1;
			update_number = control._update_count;

			widget_content = control.container.find( '.widget-content' );

			// Remove a previous error message
			widget_content.find( '.widget-error' ).remove();

			// @todo Support more selectors than IDs?
			if ( $.contains( control.container[0], document.activeElement ) && $( document.activeElement ).is( '[id]' ) ) {
				element_id_to_refocus = $( document.activeElement ).prop( 'id' );
				// @todo IE8 support: http://stackoverflow.com/a/4207763/93579
				try {
					active_input_selection_start = document.activeElement.selectionStart;
					active_input_selection_end = document.activeElement.selectionEnd;
				}
				catch( e ) {} // catch InvalidStateError in case of checkboxes
			}

			control.container.addClass( 'widget-form-loading' );
			control.container.addClass( 'previewer-loading' );
			processing = wp.customize.state( 'processing' );
			processing( processing() + 1 );

			params = {};
			params.action = 'update-widget';
			params.wp_customize = 'on';
			params.nonce = self.nonce;

			data = $.param( params );
			inputs = widget_content.find( ':input, option' );

			// Store the value we're submitting in data so that when the response comes back,
			// we know if it got sanitized; if there is no difference in the sanitized value,
			// then we do not need to touch the UI and mess up the user's ongoing editing.
			inputs.each( function () {
				var input = $( this ),
					property = control._getInputStatePropertyName( this );
				input.data( 'state' + update_number, input.prop( property ) );
			} );

			if ( instance_override ) {
				data += '&' + $.param( { 'sanitized_widget_setting': JSON.stringify( instance_override ) } );
			} else {
				data += '&' + inputs.serialize();
			}
			data += '&' + widget_content.find( '~ :input' ).serialize();

			jqxhr = $.post( wp.ajax.settings.url, data, function ( r ) {
				var message,
					sanitized_form,
					sanitized_inputs,
					has_same_inputs_in_response,
					is_instance_identical;

				// Check if the user is logged out.
				if ( '0' === r ) {
					self.previewer.preview.iframe.hide();
					self.previewer.login().done( function() {
						control.updateWidget( args );
						self.previewer.preview.iframe.show();
					} );
					return;
				}

				// Check for cheaters.
				if ( '-1' === r ) {
					self.previewer.cheatin();
					return;
				}

				if ( r.success ) {
					sanitized_form = $( '<div>' + r.data.form + '</div>' );

					control.hook( 'formUpdate', sanitized_form );

					sanitized_inputs = sanitized_form.find( ':input, option' );
					has_same_inputs_in_response = control._getInputsSignature( inputs ) === control._getInputsSignature( sanitized_inputs );

					if ( has_same_inputs_in_response ) {
						inputs.each( function ( i ) {
							var input = $( this ),
								sanitized_input = $( sanitized_inputs[i] ),
								property = control._getInputStatePropertyName( this ),
								state,
								sanitized_state;

							state = input.data( 'state' + update_number );
							sanitized_state = sanitized_input.prop( property );
							input.data( 'sanitized', sanitized_state );

							if ( state !== sanitized_state ) {

								// Only update now if not currently focused on it,
								// so that we don't cause the cursor
								// it will be updated upon the change event
								if ( args.ignore_active_element || ! input.is( document.activeElement ) ) {
									input.prop( property, sanitized_state );
								}
								control.hook( 'unsanitaryField', input, sanitized_state, state );

							} else {
								control.hook( 'sanitaryField', input, state );
							}
						} );
						control.hook( 'formUpdated', sanitized_form );
					} else {
						widget_content.html( sanitized_form.html() );
						if ( element_id_to_refocus ) {
							// not using jQuery selector so we don't have to worry about escaping IDs with brackets and other characters
							$( document.getElementById( element_id_to_refocus ) )
								.prop( {
									selectionStart: active_input_selection_start,
									selectionEnd: active_input_selection_end
								} )
								.focus();
						}
						control.hook( 'formRefreshed' );
					}

					/**
					 * If the old instance is identical to the new one, there is nothing new
					 * needing to be rendered, and so we can preempt the event for the
					 * preview finishing loading.
					 */
					is_instance_identical = _( control.setting() ).isEqual( r.data.instance );
					if ( ! is_instance_identical ) {
						control.is_widget_updating = true; // suppress triggering another updateWidget
						control.setting( r.data.instance );
						control.is_widget_updating = false;
					}

					if ( complete_callback ) {
						complete_callback.call( control, null, { no_change: is_instance_identical, ajax_finished: true } );
					}
				} else {
					message = self.i18n.error;
					if ( r.data && r.data.message ) {
						message = r.data.message;
					}
					if ( complete_callback ) {
						complete_callback.call( control, message );
					} else {
						widget_content.prepend( '<p class="widget-error"><strong>' + message + '</strong></p>' );
					}
				}
			} );
			jqxhr.fail( function ( jqXHR, textStatus ) {
				if ( complete_callback ) {
					complete_callback.call( control, textStatus );
				}
			} );
			jqxhr.always( function () {
				control.container.removeClass( 'previewer-loading' );
				control.container.removeClass( 'widget-form-loading' );
				inputs.each( function () {
					$( this ).removeData( 'state' + update_number );
				} );

				processing( processing() - 1 );
			} );
		},

		/**
		 * Expand the accordion section containing a control
		 * @todo it would be nice if accordion had a proper API instead of having to trigger UI events on its elements
		 */
		expandControlSection: function () {
			var section = this.container.closest( '.accordion-section' );
			if ( ! section.hasClass( 'open' ) ) {
				section.find( '.accordion-section-title:first' ).trigger( 'click' );
			}
		},

		/**
		 * Expand the widget form control
		 */
		expandForm: function () {
			this.toggleForm( true );
		},

		/**
		 * Collapse the widget form control
		 */
		collapseForm: function () {
			this.toggleForm( false );
		},

		/**
		 * Expand or collapse the widget control
		 *
		 * @param {boolean|undefined} [do_expand] If not supplied, will be inverse of current visibility
		 */
		toggleForm: function ( do_expand ) {
			var control = this, widget, inside, complete;

			widget = control.container.find( 'div.widget:first' );
			inside = widget.find( '.widget-inside:first' );
			if ( typeof do_expand === 'undefined' ) {
				do_expand = ! inside.is( ':visible' );
			}

			// Already expanded or collapsed, so noop
			if ( inside.is( ':visible' ) === do_expand ) {
				return;
			}

			if ( do_expand ) {
				// Close all other widget controls before expanding this one
				wp.customize.control.each( function ( other_control ) {
					if ( control.params.type === other_control.params.type && control !== other_control ) {
						other_control.collapseForm();
					}
				} );

				complete = function () {
					control.container.removeClass( 'expanding' );
					control.container.addClass( 'expanded' );
					control.container.trigger( 'expanded' );
				};
				if ( control.params.is_wide ) {
					inside.fadeIn( 'fast', complete );
				} else {
					inside.slideDown( 'fast', complete );
				}
				control.container.trigger( 'expand' );
				control.container.addClass( 'expanding' );
			} else {
				control.container.trigger( 'collapse' );
				control.container.addClass( 'collapsing' );
				complete = function () {
					control.container.removeClass( 'collapsing' );
					control.container.removeClass( 'expanded' );
					control.container.trigger( 'collapsed' );
				};
				if ( control.params.is_wide ) {
					inside.fadeOut( 'fast', complete );
				} else {
					inside.slideUp( 'fast', function() {
						widget.css( { width:'', margin:'' } );
						complete();
					} );
				}
			}
		},

		/**
		 * Expand the containing sidebar section, expand the form, and focus on
		 * the first input in the control
		 */
		focus: function () {
			var control = this;
			control.expandControlSection();
			control.expandForm();
			control.container.find( '.widget-content :focusable:first' ).focus();
		},

		/**
		 * Get the position (index) of the widget in the containing sidebar
		 *
		 * @throws Error
		 * @returns {Number}
		 */
		getWidgetSidebarPosition: function () {
			var control = this,
				sidebar_widget_ids,
				position;

			sidebar_widget_ids = control.getSidebarWidgetsControl().setting();
			position = _.indexOf( sidebar_widget_ids, control.params.widget_id );
			if ( position === -1 ) {
				throw new Error( 'Widget was unexpectedly not present in the sidebar.' );
			}
			return position;
		},

		/**
		 * Move widget up one in the sidebar
		 */
		moveUp: function () {
			this._moveWidgetByOne( -1 );
		},

		/**
		 * Move widget up one in the sidebar
		 */
		moveDown: function () {
			this._moveWidgetByOne( 1 );
		},

		/**
		 * @private
		 *
		 * @param {Number} offset 1|-1
		 */
		_moveWidgetByOne: function ( offset ) {
			var control = this,
				i,
				sidebar_widgets_setting,
				sidebar_widget_ids,
				adjacent_widget_id;

			i = control.getWidgetSidebarPosition();

			sidebar_widgets_setting = control.getSidebarWidgetsControl().setting;
			sidebar_widget_ids = Array.prototype.slice.call( sidebar_widgets_setting() ); // clone
			adjacent_widget_id = sidebar_widget_ids[i + offset];
			sidebar_widget_ids[i + offset] = control.params.widget_id;
			sidebar_widget_ids[i] = adjacent_widget_id;

			sidebar_widgets_setting( sidebar_widget_ids );
		},

		/**
		 * Toggle visibility of the widget move area
		 *
		 * @param {Boolean} [toggle]
		 */
		toggleWidgetMoveArea: function ( toggle ) {
			var control = this, move_widget_area;
			move_widget_area = control.container.find( '.move-widget-area' );
			if ( typeof toggle === 'undefined' ) {
				toggle = ! move_widget_area.hasClass( 'active' );
			}
			if ( toggle ) {
				// reset the selected sidebar
				move_widget_area.find( '.selected' ).removeClass( 'selected' );
				move_widget_area.find( 'li' ).filter( function () {
					return $( this ).data( 'id' ) === control.params.sidebar_id;
				} ).addClass( 'selected' );
				control.container.find( '.move-widget-btn' ).prop( 'disabled', true );
			}
			move_widget_area.toggleClass( 'active', toggle );
		},

		/**
		 * Inverse of WidgetCustomizer.getControlInstanceForWidget
		 * @return {jQuery}
		 */
		getPreviewWidgetElement: function () {
			var control = this,
				widget_customizer_preview = self.getPreviewWindow().wp.customize.WidgetCustomizerPreview;
			return widget_customizer_preview.getWidgetElement( control.params.widget_id );
		},

		/**
		 * Inside of the customizer preview, scroll the widget into view
		 */
		scrollPreviewWidgetIntoView: function () {
			// @todo scrollIntoView() provides a robust but very poor experience. Animation is needed. See https://github.com/x-team/wp-widget-customizer/issues/16
		},

		/**
		 * Highlight the widget control and section
		 */
		highlightSectionAndControl: function() {
			var control = this, target_element;

			if ( control.container.is( ':hidden' ) ) {
				target_element = control.container.closest( '.control-section' );
			} else {
				target_element = control.container;
			}

			$( '.widget-customizer-highlighted' ).removeClass( 'widget-customizer-highlighted' );
			target_element.addClass( 'widget-customizer-highlighted' );
			setTimeout( function () {
				target_element.removeClass( 'widget-customizer-highlighted' );
			}, 500 );
		},

		/**
		 * Add the widget-customizer-highlighted-widget class to the widget for 500ms
		 */
		highlightPreviewWidget: function () {
			var control = this, widget_el, root_el;

			widget_el = control.getPreviewWidgetElement();
			root_el = widget_el.closest( 'html' );
			root_el.find( '.widget-customizer-highlighted-widget' ).removeClass( 'widget-customizer-highlighted-widget' );
			widget_el.addClass( 'widget-customizer-highlighted-widget' );
			setTimeout( function () {
				widget_el.removeClass( 'widget-customizer-highlighted-widget' );
			}, 500 );
		}

	} );

	/**
	 * Capture the instance of the Previewer since it is private
	 */
	OldPreviewer = wp.customize.Previewer;
	wp.customize.Previewer = OldPreviewer.extend( {
		initialize: function( params, options ) {
			self.previewer = this;
			OldPreviewer.prototype.initialize.call( this, params, options );
			this.bind( 'refresh', this.refresh );
		}
	} );

	/**
	 * Given a widget control, find the sidebar widgets control that contains it.
	 * @param {string} widget_id
	 * @return {object|null}
	 */
	self.getSidebarWidgetControlContainingWidget = function ( widget_id ) {
		var found_control = null;
		// @todo this can use widget_id_to_setting_id(), then pass into wp.customize.control( x ).getSidebarWidgetsControl()
		wp.customize.control.each( function ( control ) {
			if ( control.params.type === 'sidebar_widgets' && -1 !== _.indexOf( control.setting(), widget_id ) ) {
				found_control = control;
			}
		} );
		return found_control;
	};

	/**
	 * Given a widget_id for a widget appearing in the preview, get the widget form control associated with it
	 * @param {string} widget_id
	 * @return {object|null}
	 */
	self.getWidgetFormControlForWidget = function ( widget_id ) {
		var found_control = null;
		// @todo We can just use widget_id_to_setting_id() here
		wp.customize.control.each( function ( control ) {
			if ( control.params.type === 'widget_form' && control.params.widget_id === widget_id ) {
				found_control = control;
			}
		} );
		return found_control;
	};

	/**
	 * @returns {Window}
	 */
	self.getPreviewWindow = function (){
		return $( '#customize-preview' ).find( 'iframe' ).prop( 'contentWindow' );
	};

	/**
	 * Available Widgets Panel
	 */
	self.availableWidgetsPanel = {
		active_sidebar_widgets_control: null,
		selected_widget_tpl: null,
		container: null,
		filter_input: null,

		/**
		 * Set up event listeners
		 */
		setup: function () {
			var panel = this;

			panel.container = $( '#available-widgets' );
			panel.filter_input = $( '#available-widgets-filter' ).find( 'input' );

			self.available_widgets.on( 'change update', panel.update_available_widgets_list );
			panel.update_available_widgets_list();

			// If the available widgets panel is open and the customize controls are
			// interacted with (i.e. available widgets panel is blurred) then close the
			// available widgets panel.
			$( '#customize-controls' ).on( 'click keydown', function ( e ) {
				var is_add_new_widget_btn = $( e.target ).is( '.add-new-widget, .add-new-widget *' );
				if ( $( 'body' ).hasClass( 'adding-widget' ) && ! is_add_new_widget_btn ) {
					panel.close();
				}
			} );

			// Close the panel if the URL in the preview changes
			self.previewer.bind( 'url', function () {
				panel.close();
			} );

			// Submit a selection when clicked or keypressed
			panel.container.find( '.widget-tpl' ).on( 'click keypress', function( event ) {

				// Only proceed with keypress if it is Enter or Spacebar
				if ( event.type === 'keypress' && ( event.which !== 13 && event.which !== 32 ) ) {
					return;
				}

				panel.submit( this );
			} );

			panel.filter_input.on( 'input keyup change', function( event ) {
				var first_visible_widget;

				self.available_widgets.doSearch( event.target.value );

				// Remove a widget from being selected if it is no longer visible
				if ( panel.selected_widget_tpl && ! panel.selected_widget_tpl.is( ':visible' ) ) {
					panel.selected_widget_tpl.removeClass( 'selected' );
					panel.selected_widget_tpl = null;
				}

				// If a widget was selected but the filter value has been cleared out, clear selection
				if ( panel.selected_widget_tpl && ! event.target.value ) {
					panel.selected_widget_tpl.removeClass( 'selected' );
					panel.selected_widget_tpl = null;
				}

				// If a filter has been entered and a widget hasn't been selected, select the first one shown
				if ( ! panel.selected_widget_tpl &&  event.target.value ) {
					first_visible_widget = panel.container.find( '> .widget-tpl:visible:first' );
					if ( first_visible_widget.length ) {
						panel.select( first_visible_widget );
					}
				}
			} );

			// Select a widget when it is focused on
			panel.container.find( ' > .widget-tpl' ).on( 'focus', function () {
				panel.select( this );
			} );

			panel.container.on( 'keydown', function ( event ) {
				var is_enter = ( event.which === 13 ),
					is_esc = ( event.which === 27 ),
					is_down = ( event.which === 40 ),
					is_up = ( event.which === 38 ),
					selected_widget_tpl = null,
					first_visible_widget = panel.container.find( '> .widget-tpl:visible:first' ),
					last_visible_widget = panel.container.find( '> .widget-tpl:visible:last' ),
					is_input_focused = $( event.target ).is( panel.filter_input );

				if ( is_down || is_up ) {
					if ( is_down ) {
						if ( is_input_focused ) {
							selected_widget_tpl = first_visible_widget;
						} else if ( panel.selected_widget_tpl && panel.selected_widget_tpl.nextAll( '.widget-tpl:visible' ).length !== 0 ) {
							selected_widget_tpl = panel.selected_widget_tpl.nextAll( '.widget-tpl:visible:first' );
						}
					} else if ( is_up ) {
						if ( is_input_focused ) {
							selected_widget_tpl = last_visible_widget;
						} else if ( panel.selected_widget_tpl && panel.selected_widget_tpl.prevAll( '.widget-tpl:visible' ).length !== 0 ) {
							selected_widget_tpl = panel.selected_widget_tpl.prevAll( '.widget-tpl:visible:first' );
						}
					}
					panel.select( selected_widget_tpl );
					if ( selected_widget_tpl ) {
						selected_widget_tpl.focus();
					} else {
						panel.filter_input.focus();
					}
					return;
				}

				// If enter pressed but nothing entered, don't do anything
				if ( is_enter && ! panel.filter_input.val() ) {
					return;
				}

				if ( is_enter ) {
					panel.submit();
				} else if ( is_esc ) {
					panel.close( { return_focus: true } );
				}
			} );
		},

		/**
		 * Updates widgets list.
		 */
		update_available_widgets_list: function() {
			var panel = self.availableWidgetsPanel;

			// First hide all widgets...
			panel.container.find( '.widget-tpl' ).hide();

			// ..and then show only available widgets which could be filtered
			self.available_widgets.each( function ( widget ) {
				var widget_tpl = $( '#widget-tpl-' + widget.id );
				widget_tpl.toggle( ! widget.get( 'is_disabled' ) );
				if ( widget.get( 'is_disabled' ) && widget_tpl.is( panel.selected_widget_tpl ) ) {
					panel.selected_widget_tpl = null;
				}
			} );
		},

		/**
		 * @param widget_tpl
		 */
		select: function ( widget_tpl ) {
			var panel = this;
			panel.selected_widget_tpl = $( widget_tpl );
			panel.selected_widget_tpl.siblings( '.widget-tpl' ).removeClass( 'selected' );
			panel.selected_widget_tpl.addClass( 'selected' );
		},

		submit: function ( widget_tpl ) {
			var panel = this, widget_id, widget;
			if ( ! widget_tpl ) {
				widget_tpl = panel.selected_widget_tpl;
			}
			if ( ! widget_tpl || ! panel.active_sidebar_widgets_control ) {
				return;
			}
			panel.select( widget_tpl );

			widget_id = $( panel.selected_widget_tpl ).data( 'widget-id' );
			widget = self.available_widgets.findWhere( {id: widget_id} );
			if ( ! widget ) {
				throw new Error( 'Widget unexpectedly not found.' );
			}
			panel.active_sidebar_widgets_control.addWidget( widget.get( 'id_base' ) );
			panel.close();
		},

		/**
		 * @param sidebars_widgets_control
		 */
		open: function ( sidebars_widgets_control ) {
			var panel = this;
			panel.active_sidebar_widgets_control = sidebars_widgets_control;

			// Wide widget controls appear over the preview, and so they need to be collapsed when the panel opens
			_( sidebars_widgets_control.getWidgetFormControls() ).each( function ( control ) {
				if ( control.params.is_wide ) {
					control.collapseForm();
				}
			} );

			$( 'body' ).addClass( 'adding-widget' );
			panel.container.find( '.widget-tpl' ).removeClass( 'selected' );
			self.available_widgets.doSearch( '' );
			panel.filter_input.focus();
		},

		/**
		 * Hide the panel
		 */
		close: function ( options ) {
			var panel = this;
			options = options || {};
			if ( options.return_focus && panel.active_sidebar_widgets_control ) {
				panel.active_sidebar_widgets_control.container.find( '.add-new-widget' ).focus();
			}
			panel.active_sidebar_widgets_control = null;
			panel.selected_widget_tpl = null;
			$( 'body' ).removeClass( 'adding-widget' );
			panel.filter_input.val( '' );
		}
	};

	/**
	 * @param {String} widget_id
	 * @returns {Object}
	 */
	function parse_widget_id( widget_id ) {
		var matches, parsed = {
			number: null,
			id_base: null
		};
		matches = widget_id.match( /^(.+)-(\d+)$/ );
		if ( matches ) {
			parsed.id_base = matches[1];
			parsed.number = parseInt( matches[2], 10 );
		} else {
			// likely an old single widget
			parsed.id_base = widget_id;
		}
		return parsed;
	}

	/**
	 * @param {String} widget_id
	 * @returns {String} setting_id
	 */
	function widget_id_to_setting_id( widget_id ) {
		var parsed = parse_widget_id( widget_id ), setting_id;

		setting_id = 'widget_' + parsed.id_base;
		if ( parsed.number ) {
			setting_id += '[' + parsed.number + ']';
		}
		return setting_id;
	}

	return self;
}( jQuery ));
