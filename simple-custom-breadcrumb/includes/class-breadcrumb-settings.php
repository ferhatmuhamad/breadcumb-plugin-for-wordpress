<?php
/**
 * Admin settings page for Simple Custom Breadcrumb.
 *
 * @package Simple_Custom_Breadcrumb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SCB_Breadcrumb_Settings
 *
 * Registers the plugin settings page and all options.
 */
class SCB_Breadcrumb_Settings {

	/** @var string Option group/name stored in wp_options. */
	const OPTION_NAME = 'scb_settings';

	/**
	 * Constructor – hooks up WP admin actions.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	// -------------------------------------------------------------------------
	// Default values
	// -------------------------------------------------------------------------

	/**
	 * Returns merged saved options with sensible defaults.
	 *
	 * @return array<string,string>
	 */
	public static function get_options() {
		$defaults = array(
			'link_color'       => '#0073aa',
			'hover_color'      => '#005177',
			'active_color'     => '#555555',
			'separator_color'  => '#999999',
			'separator_char'   => '/',
			'font_size'        => '14',
			'text_transform'   => 'none',
			'padding'          => '8px 0',
			'margin'           => '0 0 16px 0',
			'show_on_home'     => '0',
		);

		$saved = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( $saved, $defaults );
	}

	// -------------------------------------------------------------------------
	// Admin menu
	// -------------------------------------------------------------------------

	/**
	 * Register the settings sub-menu under "Settings".
	 */
	public function add_settings_page() {
		add_options_page(
			esc_html__( 'Simple Breadcrumb Settings', 'simple-custom-breadcrumb' ),
			esc_html__( 'Simple Breadcrumb', 'simple-custom-breadcrumb' ),
			'manage_options',
			'simple-custom-breadcrumb',
			array( $this, 'render_settings_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Enqueue colour-picker scripts & styles in admin
	// -------------------------------------------------------------------------

	/**
	 * Enqueue wp-color-picker on the plugin settings page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_simple-custom-breadcrumb' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script(
			'scb-admin',
			SCB_PLUGIN_URL . 'assets/js/admin.js',
			array( 'wp-color-picker' ),
			SCB_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Settings registration
	// -------------------------------------------------------------------------

	/**
	 * Register setting, sections, and fields via the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'scb_settings_group',
			self::OPTION_NAME,
			array( $this, 'sanitize_options' )
		);

		// ---- Section: Colours ----
		add_settings_section(
			'scb_section_colors',
			esc_html__( 'Colours', 'simple-custom-breadcrumb' ),
			'__return_false',
			'simple-custom-breadcrumb'
		);

		$color_fields = array(
			'link_color'      => esc_html__( 'Link Colour', 'simple-custom-breadcrumb' ),
			'hover_color'     => esc_html__( 'Link Hover Colour', 'simple-custom-breadcrumb' ),
			'active_color'    => esc_html__( 'Current Page Colour', 'simple-custom-breadcrumb' ),
			'separator_color' => esc_html__( 'Separator Colour', 'simple-custom-breadcrumb' ),
		);

		foreach ( $color_fields as $id => $label ) {
			add_settings_field(
				$id,
				$label,
				array( $this, 'render_color_field' ),
				'simple-custom-breadcrumb',
				'scb_section_colors',
				array( 'id' => $id )
			);
		}

		// ---- Section: Separator ----
		add_settings_section(
			'scb_section_separator',
			esc_html__( 'Separator', 'simple-custom-breadcrumb' ),
			'__return_false',
			'simple-custom-breadcrumb'
		);

		add_settings_field(
			'separator_char',
			esc_html__( 'Separator Character', 'simple-custom-breadcrumb' ),
			array( $this, 'render_text_field' ),
			'simple-custom-breadcrumb',
			'scb_section_separator',
			array( 'id' => 'separator_char', 'size' => 4, 'desc' => esc_html__( 'Default: /', 'simple-custom-breadcrumb' ) )
		);

		// ---- Section: Typography ----
		add_settings_section(
			'scb_section_typography',
			esc_html__( 'Typography', 'simple-custom-breadcrumb' ),
			'__return_false',
			'simple-custom-breadcrumb'
		);

		add_settings_field(
			'font_size',
			esc_html__( 'Font Size (px)', 'simple-custom-breadcrumb' ),
			array( $this, 'render_number_field' ),
			'simple-custom-breadcrumb',
			'scb_section_typography',
			array( 'id' => 'font_size', 'min' => 8, 'max' => 72 )
		);

		add_settings_field(
			'text_transform',
			esc_html__( 'Text Transform', 'simple-custom-breadcrumb' ),
			array( $this, 'render_select_field' ),
			'simple-custom-breadcrumb',
			'scb_section_typography',
			array(
				'id'      => 'text_transform',
				'options' => array(
					'none'       => esc_html__( 'None', 'simple-custom-breadcrumb' ),
					'uppercase'  => esc_html__( 'Uppercase', 'simple-custom-breadcrumb' ),
					'lowercase'  => esc_html__( 'Lowercase', 'simple-custom-breadcrumb' ),
					'capitalize' => esc_html__( 'Capitalize', 'simple-custom-breadcrumb' ),
				),
			)
		);

		// ---- Section: Spacing ----
		add_settings_section(
			'scb_section_spacing',
			esc_html__( 'Spacing', 'simple-custom-breadcrumb' ),
			'__return_false',
			'simple-custom-breadcrumb'
		);

		add_settings_field(
			'padding',
			esc_html__( 'Padding', 'simple-custom-breadcrumb' ),
			array( $this, 'render_text_field' ),
			'simple-custom-breadcrumb',
			'scb_section_spacing',
			array( 'id' => 'padding', 'desc' => esc_html__( 'CSS shorthand, e.g. 8px 0', 'simple-custom-breadcrumb' ) )
		);

		add_settings_field(
			'margin',
			esc_html__( 'Margin', 'simple-custom-breadcrumb' ),
			array( $this, 'render_text_field' ),
			'simple-custom-breadcrumb',
			'scb_section_spacing',
			array( 'id' => 'margin', 'desc' => esc_html__( 'CSS shorthand, e.g. 0 0 16px 0', 'simple-custom-breadcrumb' ) )
		);

		// ---- Section: Display ----
		add_settings_section(
			'scb_section_display',
			esc_html__( 'Display', 'simple-custom-breadcrumb' ),
			'__return_false',
			'simple-custom-breadcrumb'
		);

		add_settings_field(
			'show_on_home',
			esc_html__( 'Show on Front Page', 'simple-custom-breadcrumb' ),
			array( $this, 'render_checkbox_field' ),
			'simple-custom-breadcrumb',
			'scb_section_display',
			array( 'id' => 'show_on_home', 'desc' => esc_html__( 'Display breadcrumb on the front / home page', 'simple-custom-breadcrumb' ) )
		);
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	/**
	 * Render a wp-color-picker input.
	 *
	 * @param array<string,string> $args Field args.
	 */
	public function render_color_field( $args ) {
		$opts  = self::get_options();
		$id    = esc_attr( $args['id'] );
		$value = esc_attr( $opts[ $args['id'] ] );
		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="scb-color-picker" />',
			$id,
			esc_attr( self::OPTION_NAME ),
			$value
		);
	}

	/**
	 * Render a plain text input.
	 *
	 * @param array<string,mixed> $args Field args.
	 */
	public function render_text_field( $args ) {
		$opts  = self::get_options();
		$id    = esc_attr( $args['id'] );
		$value = esc_attr( $opts[ $args['id'] ] );
		$size  = isset( $args['size'] ) ? (int) $args['size'] : 20;
		$desc  = isset( $args['desc'] ) ? '<p class="description">' . esc_html( $args['desc'] ) . '</p>' : '';

		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" size="%4$d" />%5$s',
			$id,
			esc_attr( self::OPTION_NAME ),
			$value,
			$size,
			wp_kses( $desc, array( 'p' => array( 'class' => array() ) ) )
		);
	}

	/**
	 * Render a number input.
	 *
	 * @param array<string,mixed> $args Field args.
	 */
	public function render_number_field( $args ) {
		$opts  = self::get_options();
		$id    = esc_attr( $args['id'] );
		$value = absint( $opts[ $args['id'] ] );
		$min   = isset( $args['min'] ) ? (int) $args['min'] : 1;
		$max   = isset( $args['max'] ) ? (int) $args['max'] : 200;

		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$d" min="%4$d" max="%5$d" style="width:70px" />',
			$id,
			esc_attr( self::OPTION_NAME ),
			$value,
			$min,
			$max
		);
	}

	/**
	 * Render a <select> field.
	 *
	 * @param array<string,mixed> $args Field args.
	 */
	public function render_select_field( $args ) {
		$opts     = self::get_options();
		$id       = esc_attr( $args['id'] );
		$current  = $opts[ $args['id'] ];
		$options  = $args['options'];

		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $id ) . ']">';
		foreach ( $options as $val => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $val ),
				selected( $current, $val, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array<string,string> $args Field args.
	 */
	public function render_checkbox_field( $args ) {
		$opts  = self::get_options();
		$id    = esc_attr( $args['id'] );
		$value = $opts[ $args['id'] ];
		$desc  = isset( $args['desc'] ) ? esc_html( $args['desc'] ) : '';

		printf(
			'<label><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1"%3$s /> %4$s</label>',
			$id,
			esc_attr( self::OPTION_NAME ),
			checked( '1', $value, false ),
			$desc
		);
	}

	// -------------------------------------------------------------------------
	// Sanitize callback
	// -------------------------------------------------------------------------

	/**
	 * Sanitize and validate all settings before saving.
	 *
	 * @param array<string,mixed> $input Raw POST values.
	 * @return array<string,string> Sanitized values.
	 */
	public function sanitize_options( $input ) {
		$clean = array();

		// Colours.
		foreach ( array( 'link_color', 'hover_color', 'active_color', 'separator_color' ) as $key ) {
			$clean[ $key ] = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '#000000';
		}

		// Separator character – allow only a few safe characters.
		$allowed_sep = array( '/', '>', '>>', '»', '›', '-', '|', '\\', '·' );
		$sep         = isset( $input['separator_char'] ) ? sanitize_text_field( $input['separator_char'] ) : '/';
		$clean['separator_char'] = in_array( $sep, $allowed_sep, true ) ? $sep : '/';

		// Font size.
		$clean['font_size'] = (string) min( 72, max( 8, absint( $input['font_size'] ?? 14 ) ) );

		// Text transform.
		$valid_tt             = array( 'none', 'uppercase', 'lowercase', 'capitalize' );
		$clean['text_transform'] = in_array( $input['text_transform'] ?? 'none', $valid_tt, true )
			? $input['text_transform']
			: 'none';

		// Spacing – allow only safe CSS shorthand values (numbers + units + spaces).
		foreach ( array( 'padding', 'margin' ) as $key ) {
			$raw = isset( $input[ $key ] ) ? $input[ $key ] : '';
			// Allow digits, px/em/rem/%, spaces, and dots only.
			$clean[ $key ] = preg_replace( '/[^0-9a-z%. ]/', '', strtolower( $raw ) );
		}

		// Display checkbox.
		$clean['show_on_home'] = ! empty( $input['show_on_home'] ) ? '1' : '0';

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Settings page HTML
	// -------------------------------------------------------------------------

	/**
	 * Output the admin settings page markup.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Simple Custom Breadcrumb – Settings', 'simple-custom-breadcrumb' ); ?></h1>

			<p><?php esc_html_e( 'Use the shortcode [simple_breadcrumb] to display the breadcrumb anywhere on your site or inside Elementor.', 'simple-custom-breadcrumb' ); ?></p>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'scb_settings_group' );
				do_settings_sections( 'simple-custom-breadcrumb' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
