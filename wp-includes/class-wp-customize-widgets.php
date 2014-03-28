<?php
/**
 * Customize Widgets Class
 *
 * Implements widget management in the Customizer.
 *
 * @since 3.9.0
 * @package WordPress
 * @subpackage Customize
 */
class WP_Customize_Widgets {
	const UPDATE_WIDGET_AJAX_ACTION    = 'update-widget';
	const UPDATE_WIDGET_NONCE_POST_KEY = 'update-sidebar-widgets-nonce';

	/**
	 * All id_bases for widgets defined in core
	 *
	 * @since 3.9.0
	 * @static
	 * @access protected
	 * @var array
	 */
	protected static $core_widget_id_bases = array(
		'archives',
		'calendar',
		'categories',
		'links',
		'meta',
		'nav_menu',
		'pages',
		'recent-comments',
		'recent-posts',
		'rss',
		'search',
		'tag_cloud',
		'text',
	);

	/**
	 * @since 3.9.0
	 * @static
	 * @access protected
	 * @var
	 */
	protected static $_customized;

	/**
	 * @since 3.9.0
	 * @static
	 * @access protected
	 * @var array
	 */
	protected static $_prepreview_added_filters = array();

	/**
	 * @since 3.9.0
	 * @static
	 * @access protected
	 * @var array
	 */
	static protected $rendered_sidebars = array();

	/**
	 * @since 3.9.0
	 * @static
	 * @access protected
	 * @var array
	 */
	static protected $rendered_widgets = array();

	/**
	 * Initial loader.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 */
	static function setup() {
		add_action( 'after_setup_theme',                       array( __CLASS__, 'setup_widget_addition_previews' ) );
		add_action( 'customize_controls_init',                 array( __CLASS__, 'customize_controls_init' ) );
		add_action( 'customize_register',                      array( __CLASS__, 'schedule_customize_register' ), 1 );
		add_action( 'customize_controls_enqueue_scripts',      array( __CLASS__, 'customize_controls_enqueue_deps' ) );
		add_action( 'customize_controls_print_footer_scripts', array( __CLASS__, 'output_widget_control_templates' ) );
		add_action( 'customize_preview_init',                  array( __CLASS__, 'customize_preview_init' ) );

		add_action( 'dynamic_sidebar',                         array( __CLASS__, 'tally_rendered_widgets' ) );
		add_filter( 'is_active_sidebar',                       array( __CLASS__, 'tally_sidebars_via_is_active_sidebar_calls' ), 10, 2 );
		add_filter( 'dynamic_sidebar_has_widgets',             array( __CLASS__, 'tally_sidebars_via_dynamic_sidebar_calls' ), 10, 2 );
	}

	/**
	 * Get an unslashed post value or return a default.
	 *
	 * @since 3.9.0
	 *
	 * @static
	 * @access public
	 *
	 * @param string $name    Post value.
	 * @param mixed  $default Default post value.
	 * @return mixed Unslashed post value or default value.
	 */
	static function get_post_value( $name, $default = null ) {
		if ( ! isset( $_POST[ $name ] ) ) {
			return $default;
		}
		return wp_unslash( $_POST[$name] );
	}

	/**
	 *
	 *
	 * Since the widgets get registered (widgets_init) before the customizer settings are set up (customize_register),
	 * we have to filter the options similarly to how the setting previewer will filter the options later.
	 *
	 * @since 3.9.0
	 *
	 * @static
	 * @access public
	 * @global WP_Customize_Manager $wp_customize
	 */
	static function setup_widget_addition_previews() {
		global $wp_customize;
		$is_customize_preview = (
			( ! empty( $wp_customize ) )
			&&
			( ! is_admin() )
			&&
			( 'on' === self::get_post_value( 'wp_customize' ) )
			&&
			check_ajax_referer( 'preview-customize_' . $wp_customize->get_stylesheet(), 'nonce', false )
		);

		$is_ajax_widget_update = (
			( defined( 'DOING_AJAX' ) && DOING_AJAX )
			&&
			self::get_post_value( 'action' ) === self::UPDATE_WIDGET_AJAX_ACTION
			&&
			check_ajax_referer( self::UPDATE_WIDGET_AJAX_ACTION, self::UPDATE_WIDGET_NONCE_POST_KEY, false )
		);

		$is_ajax_customize_save = (
			( defined( 'DOING_AJAX' ) && DOING_AJAX )
			&&
			self::get_post_value( 'action' ) === 'customize_save'
			&&
			check_ajax_referer( 'save-customize_' . $wp_customize->get_stylesheet(), 'nonce' )
		);

		$is_valid_request = ( $is_ajax_widget_update || $is_customize_preview || $is_ajax_customize_save );
		if ( ! $is_valid_request ) {
			return;
		}

		// Input from customizer preview.
		if ( isset( $_POST['customized'] ) ) {
			$customized = json_decode( self::get_post_value( 'customized' ), true );
		}

		// Input from ajax widget update request.
		else {
			$customized    = array();
			$id_base       = self::get_post_value( 'id_base' );
			$widget_number = (int) self::get_post_value( 'widget_number' );
			$option_name   = 'widget_' . $id_base;
			$customized[$option_name] = array();
			if ( false !== $widget_number ) {
				$option_name .= '[' . $widget_number . ']';
				$customized[$option_name][$widget_number] = array();
			}
		}

		$function = array( __CLASS__, 'prepreview_added_sidebars_widgets' );

		$hook = 'option_sidebars_widgets';
		add_filter( $hook, $function );
		self::$_prepreview_added_filters[] = compact( 'hook', 'function' );

		$hook = 'default_option_sidebars_widgets';
		add_filter( $hook, $function );
		self::$_prepreview_added_filters[] = compact( 'hook', 'function' );

		foreach ( $customized as $setting_id => $value ) {
			if ( preg_match( '/^(widget_.+?)(\[(\d+)\])?$/', $setting_id, $matches ) ) {
				$body     = sprintf( 'return %s::prepreview_added_widget_instance( $value, %s );', __CLASS__, var_export( $setting_id, true ) );
				$function = create_function( '$value', $body );
				$option   = $matches[1];

				$hook = sprintf( 'option_%s', $option );
				add_filter( $hook, $function );
				self::$_prepreview_added_filters[] = compact( 'hook', 'function' );

				$hook = sprintf( 'default_option_%s', $option );
				add_filter( $hook, $function );
				self::$_prepreview_added_filters[] = compact( 'hook', 'function' );

				/**
				 * Make sure the option is registered so that the update_option won't fail due to
				 * the filters providing a default value, which causes the update_option() to get confused.
				 */
				add_option( $option, array() );
			}
		}

		self::$_customized = $customized;
	}

	/**
	 *
	 *
	 * Ensure that newly-added widgets will appear in the widgets_sidebars.
	 * This is necessary because the customizer's setting preview filters are added after the widgets_init action,
	 * which is too late for the widgets to be set up properly.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param array $sidebars_widgets Array of
	 * @return array
	 */
	static function prepreview_added_sidebars_widgets( $sidebars_widgets ) {
		foreach ( self::$_customized as $setting_id => $value ) {
			if ( preg_match( '/^sidebars_widgets\[(.+?)\]$/', $setting_id, $matches ) ) {
				$sidebar_id = $matches[1];
				$sidebars_widgets[$sidebar_id] = $value;
			}
		}
		return $sidebars_widgets;
	}

	/**
	 *
	 *
	 * Ensure that newly-added widgets will have empty instances so that they will be recognized.
	 * This is necessary because the customizer's setting preview filters are added after the widgets_init action,
	 * which is too late for the widgets to be set up properly.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param array $instance    Widget instance.
	 * @param string $setting_id Widget setting ID.
	 * @return array Parsed widget instance.
	 */
	static function prepreview_added_widget_instance( $instance, $setting_id ) {
		if ( isset( self::$_customized[$setting_id] ) ) {
			$parsed_setting_id = self::parse_widget_setting_id( $setting_id );
			$widget_number     = $parsed_setting_id['number'];

			// Single widget
			if ( is_null( $widget_number ) ) {
				if ( false === $instance && empty( $value ) ) {
					$instance = array();
				}
			}
			// Multi widget
			else if ( false === $instance || ! isset( $instance[$widget_number] ) ) {
				if ( empty( $instance ) ) {
					$instance = array( '_multiwidget' => 1 );
				}
				if ( ! isset( $instance[$widget_number] ) ) {
					$instance[$widget_number] = array();
				}
			}
		}
		return $instance;
	}

	/**
	 * Remove filters added in setup_widget_addition_previews() which ensure that
	 * widgets are populating the options during widgets_init
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 */
	static function remove_prepreview_filters() {
		foreach ( self::$_prepreview_added_filters as $prepreview_added_filter ) {
			remove_filter( $prepreview_added_filter['hook'], $prepreview_added_filter['function'] );
		}
		self::$_prepreview_added_filters = array();
	}

	/**
	 * Make sure that all widgets get loaded into customizer; these actions are also done in the wp_ajax_save_widget()
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 */
	static function customize_controls_init() {
		do_action( 'load-widgets.php' );
		do_action( 'widgets.php' );
		do_action( 'sidebar_admin_setup' );
	}

	/**
	 * When in preview, invoke customize_register for settings after WordPress is
	 * loaded so that all filters have been initialized (e.g. Widget Visibility)
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer instance.
	 */
	static function schedule_customize_register( $wp_customize ) {
		if ( is_admin() ) { // @todo for some reason, $wp_customize->is_preview() is true here?
			self::customize_register( $wp_customize );
		} else {
			add_action( 'wp', array( __CLASS__, 'customize_register' ) );
		}
	}

	/**
	 * Register customizer settings and controls for all sidebars and widgets
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer instance.
	 */
	static function customize_register( $wp_customize = null ) {
		global $wp_registered_widgets, $wp_registered_widget_controls, $wp_registered_sidebars;
		if ( ! ( $wp_customize instanceof WP_Customize_Manager ) ) {
			$wp_customize = $GLOBALS['wp_customize'];
		}

		$sidebars_widgets = array_merge(
			array( 'wp_inactive_widgets' => array() ),
			array_fill_keys( array_keys( $GLOBALS['wp_registered_sidebars'] ), array() ),
			wp_get_sidebars_widgets()
		);

		$new_setting_ids = array();

		/*
		 * Register a setting for all widgets, including those which are active, inactive, and orphaned
		 * since a widget may get suppressed from a sidebar via a plugin (like Widget Visibility).
		 */
		foreach ( array_keys( $wp_registered_widgets ) as $widget_id ) {
			$setting_id   = self::get_setting_id( $widget_id );
			$setting_args = self::get_setting_args( $setting_id );
			$setting_args['sanitize_callback']    = array( __CLASS__, 'sanitize_widget_instance' );
			$setting_args['sanitize_js_callback'] = array( __CLASS__, 'sanitize_widget_js_instance' );
			$wp_customize->add_setting( $setting_id, $setting_args );
			$new_setting_ids[] = $setting_id;
		}

		foreach ( $sidebars_widgets as $sidebar_id => $sidebar_widget_ids ) {
			if ( empty( $sidebar_widget_ids ) ) {
				$sidebar_widget_ids = array();
			}
			$is_registered_sidebar = isset( $GLOBALS['wp_registered_sidebars'][$sidebar_id] );
			$is_inactive_widgets   = ( 'wp_inactive_widgets' === $sidebar_id );
			$is_active_sidebar     = ( $is_registered_sidebar && ! $is_inactive_widgets );

			/**
			 * Add setting for managing the sidebar's widgets
			 */
			if ( $is_registered_sidebar || $is_inactive_widgets ) {
				$setting_id   = sprintf( 'sidebars_widgets[%s]', $sidebar_id );
				$setting_args = self::get_setting_args( $setting_id );
				$setting_args['sanitize_callback']    = array( __CLASS__, 'sanitize_sidebar_widgets' );
				$setting_args['sanitize_js_callback'] = array( __CLASS__, 'sanitize_sidebar_widgets_js_instance' );
				$wp_customize->add_setting( $setting_id, $setting_args );
				$new_setting_ids[] = $setting_id;

				/**
				 * Add section to contain controls
				 */
				$section_id = sprintf( 'sidebar-widgets-%s', $sidebar_id );
				if ( $is_active_sidebar ) {
					$section_args = array(
						/* translators: %s: sidebar name */
						'title' => sprintf( __( 'Widgets: %s' ), $GLOBALS['wp_registered_sidebars'][$sidebar_id]['name'] ),
						'description' => $GLOBALS['wp_registered_sidebars'][$sidebar_id]['description'],
						'priority' => 1000 + array_search( $sidebar_id, array_keys( $wp_registered_sidebars ) ),
					);
					$section_args = apply_filters( 'customizer_widgets_section_args', $section_args, $section_id, $sidebar_id );
					$wp_customize->add_section( $section_id, $section_args );

					$control = new WP_Widget_Area_Customize_Control(
						$wp_customize,
						$setting_id,
						array(
							'section' => $section_id,
							'sidebar_id' => $sidebar_id,
							'priority' => count( $sidebar_widget_ids ), // place Add Widget & Reorder buttons at end
						)
					);
					$new_setting_ids[] = $setting_id;
					$wp_customize->add_control( $control );
				}
			}

			// Add a control for each active widget (located in a sidebar).
			foreach ( $sidebar_widget_ids as $i => $widget_id ) {

				// Skip widgets that may have gone away due to a plugin being deactivated.
				if ( ! $is_active_sidebar || ! isset( $GLOBALS['wp_registered_widgets'][$widget_id] ) ) {
					continue;
				}
				$registered_widget = $GLOBALS['wp_registered_widgets'][$widget_id];
				$setting_id = self::get_setting_id( $widget_id );
				$id_base = $GLOBALS['wp_registered_widget_controls'][$widget_id]['id_base'];
				assert( false !== is_active_widget( $registered_widget['callback'], $registered_widget['id'], false, false ) );
				$control = new WP_Widget_Form_Customize_Control(
					$wp_customize,
					$setting_id,
					array(
						'label' => $registered_widget['name'],
						'section' => $section_id,
						'sidebar_id' => $sidebar_id,
						'widget_id' => $widget_id,
						'widget_id_base' => $id_base,
						'priority' => $i,
						'width' => $wp_registered_widget_controls[$widget_id]['width'],
						'height' => $wp_registered_widget_controls[$widget_id]['height'],
						'is_wide' => self::is_wide_widget( $widget_id ),
					)
				);
				$wp_customize->add_control( $control );
			}
		}

		/*
		 * We have to register these settings later than customize_preview_init
		 * so that other filters have had a chance to run.
		 */
		if ( did_action( 'customize_preview_init' ) ) {
			foreach ( $new_setting_ids as $new_setting_id ) {
				$wp_customize->get_setting( $new_setting_id )->preview();
			}
		}

		self::remove_prepreview_filters();
	}

	/**
	 * Covert a widget_id into its corresponding customizer setting id (option name)
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param string $widget_id Widget ID.
	 * @return string Maybe-parsed widget ID.
	 */
	static function get_setting_id( $widget_id ) {
		$parsed_widget_id = self::parse_widget_id( $widget_id );
		$setting_id = sprintf( 'widget_%s', $parsed_widget_id['id_base'] );
		if ( ! is_null( $parsed_widget_id['number'] ) ) {
			$setting_id .= sprintf( '[%d]', $parsed_widget_id['number'] );
		}
		return $setting_id;
	}

	/**
	 * Core widgets which may have controls wider than 250, but can still be
	 * shown in the narrow customizer panel. The RSS and Text widgets in Core,
	 * for example, have widths of 400 and yet they still render fine in the
	 * customizer panel. This method will return all Core widgets as being
	 * not wide, but this can be overridden with the is_wide_widget_in_customizer
	 * filter.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param string $widget_id Widget ID.
	 * @return bool Whether or not the widget is a "wide" widget.
	 */
	static function is_wide_widget( $widget_id ) {
		global $wp_registered_widget_controls;
		$parsed_widget_id = self::parse_widget_id( $widget_id );
		$width = $wp_registered_widget_controls[$widget_id]['width'];
		$is_core = in_array( $parsed_widget_id['id_base'], self::$core_widget_id_bases );
		$is_wide = ( $width > 250 && ! $is_core );

		/**
		 * Filter whether the given widget is considered "wide".
		 *
		 * @since 3.9.0
		 *
		 * @param bool   $is_wide   Whether the widget is wide, Default false.
		 * @param string $widget_id Widget ID.
		 */
		$is_wide = apply_filters( 'is_wide_widget_in_customizer', $is_wide, $widget_id );
		return $is_wide;
	}

	/**
	 * Covert a widget ID into its id_base and number components.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param string $widget_id Widget ID.
	 * @return array Array containing a widget's id_base and number components.
	 */
	static function parse_widget_id( $widget_id ) {
		$parsed = array(
			'number' => null,
			'id_base' => null,
		);
		if ( preg_match( '/^(.+)-(\d+)$/', $widget_id, $matches ) ) {
			$parsed['id_base'] = $matches[1];
			$parsed['number']  = intval( $matches[2] );
		} else {
			// likely an old single widget
			$parsed['id_base'] = $widget_id;
		}
		return $parsed;
	}

	/**
	 * Convert a widget setting ID (option path) to its id_base and number components.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param string $setting_id Widget setting ID.
	 * @return WP_Error|array Array contain a widget's id_base and number components,
	 *                        or a WP_Error object.
	 */
	static function parse_widget_setting_id( $setting_id ) {
		if ( ! preg_match( '/^(widget_(.+?))(?:\[(\d+)\])?$/', $setting_id, $matches ) ) {
			return new WP_Error( 'invalid_setting_id', 'Invalid widget setting ID' );
		}

		$id_base = $matches[2];
		$number  = isset( $matches[3] ) ? intval( $matches[3] ) : null;
		return compact( 'id_base', 'number' );
	}

	/**
	 * Enqueue scripts and styles for customizer panel and export data to JS.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 */
	static function customize_controls_enqueue_deps() {
		wp_enqueue_style( 'customize-widgets' );
		wp_enqueue_script( 'customize-widgets' );

		// Export available widgets with control_tpl removed from model
		// since plugins need templates to be in the DOM
		$available_widgets = array();
		foreach ( self::get_available_widgets() as $available_widget ) {
			unset( $available_widget['control_tpl'] );
			$available_widgets[] = $available_widget;
		}

		$widget_reorder_nav_tpl = sprintf(
			'<div class="widget-reorder-nav"><span class="move-widget" tabindex="0">%1$s</span><span class="move-widget-down" tabindex="0">%2$s</span><span class="move-widget-up" tabindex="0">%3$s</span></div>',
			__( 'Move to another area&hellip;' ),
			__( 'Move down' ),
			__( 'Move up' )
		);

		$move_widget_area_tpl = str_replace(
			array( '{description}', '{btn}' ),
			array(
				( 'Select an area to move this widget into:' ), // @todo translate
				esc_html_x( 'Move', 'move widget' ),
			),
			'
				<div class="move-widget-area">
					<p class="description">{description}</p>
					<ul class="widget-area-select">
						<% _.each( sidebars, function ( sidebar ){ %>
							<li class="" data-id="<%- sidebar.id %>" title="<%- sidebar.description %>" tabindex="0"><%- sidebar.name %></li>
						<% }); %>
					</ul>
					<div class="move-widget-actions">
						<button class="move-widget-btn button-secondary" type="button">{btn}</button>
					</div>
				</div>
			'
		);

		// Why not wp_localize_script? Because we're not localizing, and it forces values into strings.
		global $wp_scripts;
		$exports = array(
			'update_widget_ajax_action' => self::UPDATE_WIDGET_AJAX_ACTION,
			'update_widget_nonce_value' => wp_create_nonce( self::UPDATE_WIDGET_AJAX_ACTION ),
			'update_widget_nonce_post_key' => self::UPDATE_WIDGET_NONCE_POST_KEY,
			'registered_sidebars' => array_values( $GLOBALS['wp_registered_sidebars'] ),
			'registered_widgets' => $GLOBALS['wp_registered_widgets'],
			'available_widgets' => $available_widgets, // @todo Merge this with registered_widgets
			'i18n' => array(
				'save_btn_label' => __( 'Apply' ),
				// @todo translate? do we want these tooltips?
				'save_btn_tooltip' => ( 'Save and preview changes before publishing them.' ),
				'remove_btn_label' => __( 'Remove' ),
				'remove_btn_tooltip' => ( 'Trash widget by moving it to the inactive widgets sidebar.' ),
				'error' => __('An error has occurred. Please reload the page and try again.'),
			),
			'tpl' => array(
				'widget_reorder_nav' => $widget_reorder_nav_tpl,
				'move_widget_area' => $move_widget_area_tpl,
			),
		);
		foreach ( $exports['registered_widgets'] as &$registered_widget ) {
			unset( $registered_widget['callback'] ); // may not be JSON-serializeable
		}

		$wp_scripts->add_data(
			'customize-widgets',
			'data',
			sprintf( 'var WidgetCustomizer_exports = %s;', json_encode( $exports ) )
		);
	}

	/**
	 * Render the widget form control templates into the DOM so that plugin scripts can manipulate them
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 */
	static function output_widget_control_templates() {
		?>
		<div id="widgets-left"><!-- compatibility with JS which looks for widget templates here -->
		<div id="available-widgets">
			<div id="available-widgets-filter">
				<input type="search" placeholder="<?php esc_attr_e( 'Find widgets&hellip;' ) ?>">
			</div>
			<?php foreach ( self::get_available_widgets() as $available_widget ): ?>
				<div id="widget-tpl-<?php echo esc_attr( $available_widget['id'] ) ?>" data-widget-id="<?php echo esc_attr( $available_widget['id'] ) ?>" class="widget-tpl <?php echo esc_attr( $available_widget['id'] ) ?>" tabindex="0">
					<?php echo $available_widget['control_tpl']; // xss ok ?>
				</div>
			<?php endforeach; ?>
		</div><!-- #available-widgets -->
		</div><!-- #widgets-left -->
		<?php
	}

	/**
	 * Get common arguments to supply when constructing a customizer setting
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param string $id        Widget setting ID.
	 * @param array  $overrides Array of setting overrides.
	 * @return array Possibly modified setting arguments.
	 */
	static function get_setting_args( $id, $overrides = array() ) {
		$args = array(
			'type' => 'option',
			'capability' => 'edit_theme_options',
			'transport' => 'refresh',
			'default' => array(),
		);
		$args = array_merge( $args, $overrides );
		$args = apply_filters( 'widget_customizer_setting_args', $args, $id );
		return $args;
	}

	/**
	 * Make sure that a sidebars_widgets[x] only ever consists of actual widget IDs.
	 * Used as sanitize_callback for each sidebars_widgets setting.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param array $widget_ids Array of widget IDs.
	 * @return array Array of sanitized widget IDs.
	 */
	static function sanitize_sidebar_widgets( $widget_ids ) {
		global $wp_registered_widgets;
		$widget_ids = array_map( 'strval', (array) $widget_ids );
		$sanitized_widget_ids = array();
		foreach ( $widget_ids as $widget_id ) {
			if ( array_key_exists( $widget_id, $wp_registered_widgets ) ) {
				$sanitized_widget_ids[] = $widget_id;
			}
		}
		return $sanitized_widget_ids;
	}

	/**
	 * Build up an index of all available widgets for use in Backbone models.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @see wp_list_widgets()
	 * @return array
	 */
	static function get_available_widgets() {
		static $available_widgets = array();
		if ( ! empty( $available_widgets ) ) {
			return $available_widgets;
		}

		global $wp_registered_widgets, $wp_registered_widget_controls;
		require_once ABSPATH . '/wp-admin/includes/widgets.php'; // for next_widget_id_number()

		$sort = $wp_registered_widgets;
		usort( $sort, array( __CLASS__, '_sort_name_callback' ) );
		$done = array();

		foreach ( $sort as $widget ) {
			if ( in_array( $widget['callback'], $done, true ) ) { // We already showed this multi-widget
				continue;
			}

			$sidebar = is_active_widget( $widget['callback'], $widget['id'], false, false );
			$done[]  = $widget['callback'];

			if ( ! isset( $widget['params'][0] ) ) {
				$widget['params'][0] = array();
			}

			$available_widget = $widget;
			unset( $available_widget['callback'] ); // not serializable to JSON

			$args = array(
				'widget_id' => $widget['id'],
				'widget_name' => $widget['name'],
				'_display' => 'template',
			);

			$is_disabled     = false;
			$is_multi_widget = (
				isset( $wp_registered_widget_controls[$widget['id']]['id_base'] )
				&&
				isset( $widget['params'][0]['number'] )
			);
			if ( $is_multi_widget ) {
				$id_base = $wp_registered_widget_controls[$widget['id']]['id_base'];
				$args['_temp_id']   = "$id_base-__i__";
				$args['_multi_num'] = next_widget_id_number( $id_base );
				$args['_add']       = 'multi';
			} else {
				$args['_add'] = 'single';
				if ( $sidebar && 'wp_inactive_widgets' !== $sidebar ) {
					$is_disabled = true;
				}
				$id_base = $widget['id'];
			}

			$list_widget_controls_args = wp_list_widget_controls_dynamic_sidebar( array( 0 => $args, 1 => $widget['params'][0] ) );
			$control_tpl = self::get_widget_control( $list_widget_controls_args );

			// The properties here are mapped to the Backbone Widget model
			$available_widget = array_merge(
				$available_widget,
				array(
					'temp_id' => isset( $args['_temp_id'] ) ? $args['_temp_id'] : null,
					'is_multi' => $is_multi_widget,
					'control_tpl' => $control_tpl,
					'multi_number' => ( $args['_add'] === 'multi' ) ? $args['_multi_num'] : false,
					'is_disabled' => $is_disabled,
					'id_base' => $id_base,
					'transport' => 'refresh',
					'width' => $wp_registered_widget_controls[$widget['id']]['width'],
					'height' => $wp_registered_widget_controls[$widget['id']]['height'],
					'is_wide' => self::is_wide_widget( $widget['id'] ),
				)
			);

			$available_widgets[] = $available_widget;
		}
		return $available_widgets;
	}

	/**
	 * Naturally order available widgets by name.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param array $widget_a The first widget to compare.
	 * @param array $widget_b The second widget to compare.
	 * @return int Reorder position for the current widget comparison.
	 */
	static function _sort_name_callback( $widget_a, $widget_b ) {
		return strnatcasecmp( $widget_a['name'], $widget_b['name'] );
	}

	/**
	 * Invoke wp_widget_control() but capture the output buffer and transform the markup
	 * so that it can be used in the customizer.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param array $args Widget control arguments.
	 * @return string Widget control form HTML markup.
	 */
	static function get_widget_control( $args ) {
		ob_start();
		call_user_func_array( 'wp_widget_control', $args );
		$replacements = array(
			'<form action="" method="post">' => '<div class="form">',
			'</form>' => '</div><!-- .form -->',
		);
		$control_tpl = ob_get_clean();
		$control_tpl = str_replace( array_keys( $replacements ), array_values( $replacements ), $control_tpl );
		return $control_tpl;
	}

	/**
	 * Add hooks for the customizer preview
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 */
	static function customize_preview_init() {
		add_filter( 'sidebars_widgets',   array( __CLASS__, 'preview_sidebars_widgets' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'customize_preview_enqueue' ) );
		add_action( 'wp_print_styles',    array( __CLASS__, 'inject_preview_css' ), 1 );
		add_action( 'wp_footer',          array( __CLASS__, 'export_preview_data' ), 20 );
	}

	/**
	 *
	 *
	 * When previewing, make sure the proper previewing widgets are used. Because wp_get_sidebars_widgets()
	 * gets called early at init (via wp_convert_widget_settings()) and can set global variable
	 * $_wp_sidebars_widgets to the value of get_option( 'sidebars_widgets' ) before the customizer
	 * preview filter is added, we have to reset it after the filter has been added.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param array $sidebars_widgets List of widgets for the current sidebar.
	 */
	static function preview_sidebars_widgets( $sidebars_widgets ) {
		$sidebars_widgets = get_option( 'sidebars_widgets' );
		unset( $sidebars_widgets['array_version'] );
		return $sidebars_widgets;
	}

	/**
	 * Enqueue scripts for the customizer preview
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 */
	static function customize_preview_enqueue() {
		wp_enqueue_script( 'customize-preview-widgets' );
		}

	/**
	 * Insert default style for highlighted widget at early point so theme
	 * stylesheet can override.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @action wp_print_styles
	 */
	static function inject_preview_css() {
		?>
		<style>
		.widget-customizer-highlighted-widget {
			outline: none;
			-webkit-box-shadow: 0 0 2px rgba(30,140,190,0.8);
			box-shadow: 0 0 2px rgba(30,140,190,0.8);
			position: relative;
			z-index: 1;
		}
		</style>
		<?php
	}

	/**
	 * At the very end of the page, at the very end of the wp_footer, communicate the sidebars that appeared on the page.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 */
	static function export_preview_data() {
		// Prepare customizer settings to pass to Javascript.
		$settings = array(
			'renderedSidebars'   => array_fill_keys( array_unique( self::$rendered_sidebars ), true ),
			'renderedWidgets'    => array_fill_keys( array_keys( self::$rendered_widgets ), true ),
			'registeredSidebars' => array_values( $GLOBALS['wp_registered_sidebars'] ),
			'registeredWidgets'  => $GLOBALS['wp_registered_widgets'],
			'l10n'               => array(
				'widgetTooltip' => ( 'Shift-click to edit this widget.' ),
			),
		);
		foreach ( $settings['registeredWidgets'] as &$registered_widget ) {
			unset( $registered_widget['callback'] ); // may not be JSON-serializeable
		}

		?>
		<script type="text/javascript">
			var _wpWidgetCustomizerPreviewSettings = <?php echo json_encode( $settings ); ?>;
		</script>
		<?php
	}

	/**
	 * Keep track of the widgets that were rendered
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param array $widget Rendered widget to tally.
	 */
	static function tally_rendered_widgets( $widget ) {
		self::$rendered_widgets[$widget['id']] = true;
	}

	/**
	 * Keep track of the times that is_active_sidebar() is called in the template, and assume that this
	 * means that the sidebar would be rendered on the template if there were widgets populating it.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param bool    $is_active  Whether the sidebar is active.
	 * @pasram string $sidebar_id Sidebar ID.
	 */
	static function tally_sidebars_via_is_active_sidebar_calls( $is_active, $sidebar_id ) {
		if ( isset( $GLOBALS['wp_registered_sidebars'][$sidebar_id] ) ) {
			self::$rendered_sidebars[] = $sidebar_id;
		}
		// We may need to force this to true, and also force-true the value for dynamic_sidebar_has_widgets
		// if we want to ensure that there is an area to drop widgets into, if the sidebar is empty.
		return $is_active;
	}

	/**
	 * Keep track of the times that dynamic_sidebar() is called in the template, and assume that this
	 * means that the sidebar would be rendered on the template if there were widgets populating it.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param bool   $has_widgets Whether the current sidebar has widgets.
	 * @param string $sidebar_id  Sidebar ID.
	 */
	static function tally_sidebars_via_dynamic_sidebar_calls( $has_widgets, $sidebar_id ) {
		if ( isset( $GLOBALS['wp_registered_sidebars'][$sidebar_id] ) ) {
			self::$rendered_sidebars[] = $sidebar_id;
		}
		/*
		 * We may need to force this to true, and also force-true the value for is_active_sidebar
		 * if we want to ensure that there is an area to drop widgets into, if the sidebar is empty.
		 */
		return $has_widgets;
	}

	/**
	 * Get a widget instance's hash key.
	 *
	 * Serialize an instance and hash it with the AUTH_KEY; when a JS value is
	 * posted back to save, this instance hash key is used to ensure that the
	 * serialized_instance was not tampered with, but that it had originated
	 * from WordPress and so is sanitized.
	 *
	 * @since 3.9.0
	 * @static
	 * @access protected
	 *
	 * @param array $instance Widget instance.
	 * @return string Widget instance's hash key.
	 */
	protected static function get_instance_hash_key( $instance ) {
		$hash = md5( AUTH_KEY . serialize( $instance ) );
		return $hash;
	}

	/**
	 * Sanitize a widget instance.
	 *
	 * Unserialize the JS-instance for storing in the options. It's important
	 * that this filter only get applied to an instance once.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @see Widget_Customizer::sanitize_widget_js_instance()
	 *
	 * @param array $value Widget instance to sanitize.
	 * @return array Sanitized widget instance.
	 */
	static function sanitize_widget_instance( $value ) {
		if ( $value === array() ) {
			return $value;
		}
		$invalid = (
			empty( $value['is_widget_customizer_js_value'] )
			||
			empty( $value['instance_hash_key'] )
			||
			empty( $value['encoded_serialized_instance'] )
		);
		if ( $invalid ) {
			return null;
		}
		$decoded = base64_decode( $value['encoded_serialized_instance'], true );
		if ( false === $decoded ) {
			return null;
		}
		$instance = unserialize( $decoded );
		if ( false === $instance ) {
			return null;
		}
		if ( self::get_instance_hash_key( $instance ) !== $value['instance_hash_key'] ) {
			return null;
		}
		return $instance;
	}

	/**
	 * Convert widget instance into JSON-representable format.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @see Widget_Customizer::sanitize_widget_instance()
	 *
	 * @param array $value Widget instance to convert to JSON.
	 * @return array JSON-converted widget instance.
	 */
	static function sanitize_widget_js_instance( $value ) {
		if ( empty( $value['is_widget_customizer_js_value'] ) ) {
			$serialized = serialize( $value );
			$value = array(
				'encoded_serialized_instance' => base64_encode( $serialized ),
				'title' => empty( $value['title'] ) ? '' : $value['title'],
				'is_widget_customizer_js_value' => true,
				'instance_hash_key' => self::get_instance_hash_key( $value ),
			);
		}
		return $value;
	}

	/**
	 * Strip out widget IDs for widgets which are no longer registered, such
	 * as the case when a plugin orphans a widget in a sidebar when it is deactivated.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param array $widget_ids List of widget IDs.
	 * @return array Parsed list of widget IDs.
	 */
	static function sanitize_sidebar_widgets_js_instance( $widget_ids ) {
		global $wp_registered_widgets;
		$widget_ids = array_values( array_intersect( $widget_ids, array_keys( $wp_registered_widgets ) ) );
		return $widget_ids;
	}

	/**
	 * Find and invoke the widget update and control callbacks.
	 *
	 * Requires that $_POST be populated with the instance data.
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @param  string $widget_id Widget ID.
	 * @return WP_Error|array Array containing the updated widget information. WP_Error, otherwise.
	 */
	static function call_widget_update( $widget_id ) {
		global $wp_registered_widget_updates, $wp_registered_widget_controls;

		$options_transaction = new Options_Transaction();

		$options_transaction->start();
		$parsed_id   = self::parse_widget_id( $widget_id );
		$option_name = 'widget_' . $parsed_id['id_base'];

		/*
		 * If a previously-sanitized instance is provided, populate the input vars
		 * with its values so that the widget update callback will read this instance
		 */
		$added_input_vars = array();
		if ( ! empty( $_POST['sanitized_widget_setting'] ) ) {
			$sanitized_widget_setting = json_decode( self::get_post_value( 'sanitized_widget_setting' ), true );
			if ( empty( $sanitized_widget_setting ) ) {
				$options_transaction->rollback();
				return new WP_Error( 'malformed_data', 'Malformed sanitized_widget_setting' );
			}

			$instance = self::sanitize_widget_instance( $sanitized_widget_setting );
			if ( is_null( $instance ) ) {
				$options_transaction->rollback();
				return new WP_Error( 'unsanitary_data', 'Unsanitary sanitized_widget_setting' );
			}

			if ( ! is_null( $parsed_id['number'] ) ) {
				$value = array();
				$value[$parsed_id['number']] = $instance;
				$key = 'widget-' . $parsed_id['id_base'];
				$_REQUEST[$key] = $_POST[$key] = wp_slash( $value );
				$added_input_vars[] = $key;
			} else {
				foreach ( $instance as $key => $value ) {
					$_REQUEST[$key] = $_POST[$key] = wp_slash( $value );
					$added_input_vars[] = $key;
				}
			}
		}

		// Invoke the widget update callback.
		foreach ( (array) $wp_registered_widget_updates as $name => $control ) {
			if ( $name === $parsed_id['id_base'] && is_callable( $control['callback'] ) ) {
				ob_start();
				call_user_func_array( $control['callback'], $control['params'] );
				ob_end_clean();
				break;
			}
		}

		// Clean up any input vars that were manually added
		foreach ( $added_input_vars as $key ) {
			unset( $_POST[$key] );
			unset( $_REQUEST[$key] );
		}

		// Make sure the expected option was updated.
		if ( 0 !== $options_transaction->count() ) {
			if ( count( $options_transaction->options ) > 1 ) {
				$options_transaction->rollback();
				return new WP_Error( 'unexpected_update', 'Widget unexpectedly updated more than one option.' );
			}

			$updated_option_name = key( $options_transaction->options );
			if ( $updated_option_name !== $option_name ) {
				$options_transaction->rollback();
				return new WP_Error( 'wrong_option', sprintf( 'Widget updated option "%1$s", but expected "%2$s".', $updated_option_name, $option_name ) );
			}
		}

		// Obtain the widget control with the updated instance in place.
		ob_start();
		$form = $wp_registered_widget_controls[$widget_id];
		if ( $form ) {
			call_user_func_array( $form['callback'], $form['params'] );
		}
		$form = ob_get_clean();

		// Obtain the widget instance.
		$option = get_option( $option_name );
		if ( null !== $parsed_id['number'] ) {
			$instance = $option[$parsed_id['number']];
		} else {
			$instance = $option;
		}

		$options_transaction->rollback();
		return compact( 'instance', 'form' );
	}

	/**
	 * Allow customizer to update a widget using its form, but return the new
	 * instance info via Ajax instead of saving it to the options table.
	 * Most code here copied from wp_ajax_save_widget()
	 *
	 * @since 3.9.0
	 * @static
	 * @access public
	 *
	 * @see wp_ajax_save_widget
	 * @todo Reuse wp_ajax_save_widget now that we have option transactions?
	 * @action wp_ajax_update_widget
	 */
	static function wp_ajax_update_widget() {

		if ( ! is_user_logged_in() ) {
			wp_die( 0 );
		}

		check_ajax_referer( self::UPDATE_WIDGET_AJAX_ACTION, self::UPDATE_WIDGET_NONCE_POST_KEY );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( -1 );
		}

		if ( ! isset( $_POST['widget-id'] ) ) {
			wp_send_json_error();
		}

		unset( $_POST[self::UPDATE_WIDGET_NONCE_POST_KEY], $_POST['action'] );

		do_action( 'load-widgets.php' );
		do_action( 'widgets.php' );
		do_action( 'sidebar_admin_setup' );

		$widget_id = self::get_post_value( 'widget-id' );
		$parsed_id = self::parse_widget_id( $widget_id );
		$id_base   = $parsed_id['id_base'];

		if ( isset( $_POST['widget-' . $id_base] ) && is_array( $_POST['widget-' . $id_base] ) && preg_match( '/__i__|%i%/', key( $_POST['widget-' . $id_base] ) ) ) {
			wp_send_json_error();
		}

		$updated_widget = self::call_widget_update( $widget_id ); // => {instance,form}
		if ( is_wp_error( $updated_widget ) ) {
			wp_send_json_error();
		}

		$form = $updated_widget['form'];
		$instance = self::sanitize_widget_js_instance( $updated_widget['instance'] );

		wp_send_json_success( compact( 'form', 'instance' ) );
	}
}

class Options_Transaction {

	/**
	 * @var array $options values updated while transaction is open
	 */
	public $options = array();

	protected $_ignore_transients = true;
	protected $_is_current = false;
	protected $_operations = array();

	function __construct( $ignore_transients = true ) {
		$this->_ignore_transients = $ignore_transients;
	}

	/**
	 * Determine whether or not the transaction is open
	 * @return bool
	 */
	function is_current() {
		return $this->_is_current;
	}

	/**
	 * @param $option_name
	 * @return boolean
	 */
	function is_option_ignored( $option_name ) {
		return ( $this->_ignore_transients && 0 === strpos( $option_name, '_transient_' ) );
	}

	/**
	 * Get the number of operations performed in the transaction
	 * @return bool
	 */
	function count() {
		return count( $this->_operations );
	}

	/**
	 * Start keeping track of changes to options, and cache their new values
	 */
	function start() {
		$this->_is_current = true;
		add_action( 'added_option', array( $this, '_capture_added_option' ), 10, 2 );
		add_action( 'updated_option', array( $this, '_capture_updated_option' ), 10, 3 );
		add_action( 'delete_option', array( $this, '_capture_pre_deleted_option' ), 10, 1 );
		add_action( 'deleted_option', array( $this, '_capture_deleted_option' ), 10, 1 );
	}

	/**
	 * @action added_option
	 * @param $option_name
	 * @param $new_value
	 */
	function _capture_added_option( $option_name, $new_value ) {
		if ( $this->is_option_ignored( $option_name ) ) {
			return;
		}
		$this->options[$option_name] = $new_value;
		$operation = 'add';
		$this->_operations[] = compact( 'operation', 'option_name', 'new_value' );
	}

	/**
	 * @action updated_option
	 * @param string $option_name
	 * @param mixed $old_value
	 * @param mixed $new_value
	 */
	function _capture_updated_option( $option_name, $old_value, $new_value ) {
		if ( $this->is_option_ignored( $option_name ) ) {
			return;
		}
		$this->options[$option_name] = $new_value;
		$operation = 'update';
		$this->_operations[] = compact( 'operation', 'option_name', 'old_value', 'new_value' );
	}

	protected $_pending_delete_option_autoload;
	protected $_pending_delete_option_value;

	/**
	 * It's too bad the old_value and autoload aren't passed into the deleted_option action
	 * @action delete_option
	 * @param string $option_name
	 */
	function _capture_pre_deleted_option( $option_name ) {
		if ( $this->is_option_ignored( $option_name ) ) {
			return;
		}
		global $wpdb;
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option_name ) ); // db call ok; no-cache ok
		$this->_pending_delete_option_autoload = $autoload;
		$this->_pending_delete_option_value    = get_option( $option_name );
	}

	/**
	 * @action deleted_option
	 * @param string $option_name
	 */
	function _capture_deleted_option( $option_name ) {
		if ( $this->is_option_ignored( $option_name ) ) {
			return;
		}
		unset( $this->options[$option_name] );
		$operation = 'delete';
		$old_value = $this->_pending_delete_option_value;
		$autoload  = $this->_pending_delete_option_autoload;
		$this->_operations[] = compact( 'operation', 'option_name', 'old_value', 'autoload' );
	}

	/**
	 * Undo any changes to the options since start() was called
	 */
	function rollback() {
		remove_action( 'updated_option', array( $this, '_capture_updated_option' ), 10, 3 );
		remove_action( 'added_option', array( $this, '_capture_added_option' ), 10, 2 );
		remove_action( 'delete_option', array( $this, '_capture_pre_deleted_option' ), 10, 1 );
		remove_action( 'deleted_option', array( $this, '_capture_deleted_option' ), 10, 1 );
		while ( 0 !== count( $this->_operations ) ) {
			$option_operation = array_pop( $this->_operations );
			if ( 'add' === $option_operation['operation'] ) {
				delete_option( $option_operation['option_name'] );
			}
			else if ( 'delete' === $option_operation['operation'] ) {
				add_option( $option_operation['option_name'], $option_operation['old_value'], null, $option_operation['autoload'] );
			}
			else if ( 'update' === $option_operation['operation'] ) {
				update_option( $option_operation['option_name'], $option_operation['old_value'] );
			}
		}
		$this->_is_current = false;
	}
}
