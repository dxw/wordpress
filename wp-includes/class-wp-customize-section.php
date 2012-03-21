<?php
/**
 * Customize Section Class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */

class WP_Customize_Section {
	public $id;
	public $priority       = 10;
	public $capability     = 'edit_theme_options';
	public $theme_supports = '';
	public $title          = '';
	public $description    = '';
	public $settings;

	/**
	 * Constructor.
	 *
	 * @since 3.4.0
	 *
	 * @param string $id An specific ID of the section.
	 * @param array $args Section arguments.
	 */
	function __construct( $id, $args = array() ) {
		$this->id = $id;

		$keys = array_keys( get_class_vars( __CLASS__ ) );
		foreach ( $keys as $key ) {
			if ( isset( $args[ $key ] ) )
				$this->$key = $args[ $key ];
		}

		$this->settings = array(); // Users cannot customize the $settings array.

		return $this;
	}

	/**
	 * Check if the theme supports the section and check user capabilities.
	 *
	 * @since 3.4.0
	 *
	 * @return bool False if theme doesn't support the section or user doesn't have the capability.
	 */
	function check_capabilities() {
		if ( $this->capability && ! call_user_func_array( 'current_user_can', (array) $this->capability ) )
			return false;

		if ( $this->theme_supports && ! call_user_func_array( 'current_theme_supports', (array) $this->theme_supports ) )
			return false;

		return true;
	}

	/**
	 * Render the section.
	 *
	 * @since 3.4.0
	 */
	function render() {
		if ( ! $this->check_capabilities() )
			return;
		?>
		<li id="customize-section-<?php echo esc_attr( $this->id ); ?>" class="control-section customize-section">
			<h3 class="customize-section-title" title="<?php echo esc_attr( $this->description ); ?>"><?php echo esc_html( $this->title ); ?></h3>
			<ul class="customize-section-content">
				<?php foreach ( $this->settings as $setting ) : ?>
				<li id="customize-control-<?php echo esc_attr( $setting->id ); ?>" class="customize-control customize-control-<?php echo esc_attr( $setting->control ); ?>">
					<?php $setting->_render(); ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</li>
		<?php
	}
}
