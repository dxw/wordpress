<?php
/**
 * Customize Section Class.
 *
 * A UI container for controls, managed by the WP_Customize_Manager.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */
class WP_Customize_Section {

	/**
	 * WP_Customize_Manager instance.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var WP_Customize_Manager
	 */
	public $manager;

	/**
	 * Unique identifier.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var string
	 */
	public $id;

	/**
	 * Priority of the section which informs load order of sections.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var integer
	 */
	public $priority = 10;

	/**
	 * Panel in which to show the section, making it a sub-section.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var string
	 */
	public $panel = '';

	/**
	 * Capability required for the section.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var string
	 */
	public $capability = 'edit_theme_options';

	/**
	 * Theme feature support for the section.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var string|array
	 */
	public $theme_supports = '';

	/**
	 * Title of the section to show in UI.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var string
	 */
	public $title = '';

	/**
	 * Description to show in the UI.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var string
	 */
	public $description = '';

	/**
	 * Customizer controls for this section.
	 *
	 * @since 3.4.0
	 * @access public
	 * @var array
	 */
	public $controls;

	/**
	 * Constructor.
	 *
	 * Any supplied $args override class property defaults.
	 *
	 * @since 3.4.0
	 *
	 * @param WP_Customize_Manager $manager Customizer bootstrap instance.
	 * @param string               $id      An specific ID of the section.
	 * @param array                $args    Section arguments.
	 */
	public function __construct( $manager, $id, $args = array() ) {
		$keys = array_keys( get_object_vars( $this ) );
		foreach ( $keys as $key ) {
			if ( isset( $args[ $key ] ) )
				$this->$key = $args[ $key ];
		}

		$this->manager = $manager;
		$this->id = $id;

		$this->controls = array(); // Users cannot customize the $controls array.

		return $this;
	}

	/**
	 * Checks required user capabilities and whether the theme has the
	 * feature support required by the section.
	 *
	 * @since 3.4.0
	 *
	 * @return bool False if theme doesn't support the section or user doesn't have the capability.
	 */
	public final function check_capabilities() {
		if ( $this->capability && ! call_user_func_array( 'current_user_can', (array) $this->capability ) )
			return false;

		if ( $this->theme_supports && ! call_user_func_array( 'current_theme_supports', (array) $this->theme_supports ) )
			return false;

		return true;
	}

	/**
	 * Check capabilities and render the section.
	 *
	 * @since 3.4.0
	 */
	public final function maybe_render() {
		if ( ! $this->check_capabilities() )
			return;

		/**
		 * Fires before rendering a Customizer section.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Section $this WP_Customize_Section instance.
		 */
		do_action( 'customize_render_section', $this );
		/**
		 * Fires before rendering a specific Customizer section.
		 *
		 * The dynamic portion of the hook name, $this->id, refers to the ID
		 * of the specific Customizer section to be rendered.
		 *
		 * @since 3.4.0
		 */
		do_action( "customize_render_section_{$this->id}" );

		$this->render();
	}

	/**
	 * Render the section, and the controls that have been added to it.
	 *
	 * @since 3.4.0
	 */
	protected function render() {
		$classes = 'control-section accordion-section';
		if ( $this->panel ) {
			$classes .= ' control-subsection';
		}
		?>
		<li id="accordion-section-<?php echo esc_attr( $this->id ); ?>" class="<?php echo esc_attr( $classes ); ?>">
			<h3 class="accordion-section-title" tabindex="0"><?php echo esc_html( $this->title ); ?></h3>
			<ul class="accordion-section-content">
				<?php if ( ! empty( $this->description ) ) : ?>
				<li><p class="description customize-section-description"><?php echo $this->description; ?></p></li>
				<?php endif; ?>
				<?php
				foreach ( $this->controls as $control )
					$control->maybe_render();
				?>
			</ul>
		</li>
		<?php
	}
}

/**
 * Customize Panel Class.
 *
 * A UI container for sections, managed by the WP_Customize_Manager.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.0.0
 */
class WP_Customize_Panel extends WP_Customize_Section {

	/**
	 * Customizer sections for this panel.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var array
	 */
	public $sections;

	/**
	 * Constructor.
	 *
	 * Any supplied $args override class property defaults.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_Customize_Manager $manager Customizer bootstrap instance.
	 * @param string               $id      An specific ID of the section.
	 * @param array                $args    Section arguments.
	 */
	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args );

		$this->sections = array(); // Users cannot customize the $sections array.

		return $this;
	}

	/**
	 * Render the panel, and the sections that have been added to it.
	 *
	 * @since 4.0.0
	 */
	protected function render() {
		?>
		<li id="accordion-section-<?php echo esc_attr( $this->id ); ?>" class="control-section control-panel accordion-section">
			<h3 class="accordion-section-title" tabindex="0"><?php echo esc_html( $this->title ); ?></h3>
			<span class="control-panel-back" tabindex="0"><span class="screen-reader-text">Back to Customize</span></span>
			<ul class="accordion-sub-container control-panel-content">
				<li class="accordion-section control-section<?php if ( empty( $this->description ) ) echo ' cannot-expand'; ?>">
					<div class="accordion-section-title" tabindex="0">
						<span class="preview-notice"><?php
							/* translators: %s is the panel title in the Customize/Live Preview pane */
							echo sprintf( 'You are customizing %s', '<strong class="panel-title">' . esc_html( $this->title ) . '</strong>' );
						?></span>
					</div>
					<?php if ( ! empty( $this->description ) ) : ?>
						<div class="accordion-section-content description">
							<?php echo $this->description; ?>
						</div>
					<?php endif; ?>
				</li>
				<?php
				foreach ( $this->sections as $section ) {
					$section->maybe_render();
				}
				?>
			</ul>
		</li>
		<?php
	}
}
