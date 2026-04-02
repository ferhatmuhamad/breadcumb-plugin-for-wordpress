<?php
/**
 * Plugin Name:       Simple Custom Breadcrumb
 * Plugin URI:        https://github.com/ferhatmuhamad/breadcumb-plugin-for-wordpress
 * Description:       A lightweight, customisable breadcrumb plugin with admin colour-picker settings, shortcode support, and an optional Elementor widget.
 * Version:           1.0.0
 * Author:            ferhatmuhamad
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-custom-breadcrumb
 * Requires at least: 5.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCB_VERSION', '1.0.0' );
define( 'SCB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SCB_PLUGIN_DIR . 'includes/class-breadcrumb-settings.php';
require_once SCB_PLUGIN_DIR . 'includes/class-breadcrumb-renderer.php';

/**
 * Enqueue front-end stylesheet and output dynamic inline CSS from settings.
 */
function scb_enqueue_assets() {
	wp_enqueue_style(
		'simple-custom-breadcrumb',
		SCB_PLUGIN_URL . 'assets/css/breadcrumb-style.css',
		array(),
		SCB_VERSION
	);

	// Build dynamic CSS from saved options.
	$opts = SCB_Breadcrumb_Settings::get_options();

	$link_color    = sanitize_hex_color( $opts['link_color'] );
	$hover_color   = sanitize_hex_color( $opts['hover_color'] );
	$active_color  = sanitize_hex_color( $opts['active_color'] );
	$sep_color     = sanitize_hex_color( $opts['separator_color'] );
	$font_size     = absint( $opts['font_size'] );
	$text_trans    = in_array( $opts['text_transform'], array( 'none', 'uppercase', 'lowercase', 'capitalize' ), true )
		? $opts['text_transform'] : 'none';
	$padding       = sanitize_text_field( $opts['padding'] );
	$margin        = sanitize_text_field( $opts['margin'] );

	$dynamic_css = "
.scb-breadcrumb {
	font-size: {$font_size}px;
	text-transform: {$text_trans};
	padding: {$padding};
	margin: {$margin};
}
.scb-breadcrumb a {
	color: {$link_color};
}
.scb-breadcrumb a:hover {
	color: {$hover_color};
}
.scb-breadcrumb .scb-current {
	color: {$active_color};
}
.scb-breadcrumb .scb-separator {
	color: {$sep_color};
}
";

	wp_add_inline_style( 'simple-custom-breadcrumb', $dynamic_css );
}
add_action( 'wp_enqueue_scripts', 'scb_enqueue_assets' );

/**
 * Register [simple_breadcrumb] shortcode.
 */
function scb_shortcode( $atts ) {
	$atts = shortcode_atts( array(), $atts, 'simple_breadcrumb' );
	$renderer = new SCB_Breadcrumb_Renderer();
	return $renderer->render();
}
add_shortcode( 'simple_breadcrumb', 'scb_shortcode' );

/**
 * Register Elementor widget if Elementor is active.
 */
function scb_register_elementor_widget( $widgets_manager ) {
	require_once SCB_PLUGIN_DIR . 'includes/class-elementor-breadcrumb-widget.php';
	$widgets_manager->register( new SCB_Elementor_Breadcrumb_Widget() );
}
add_action( 'elementor/widgets/register', 'scb_register_elementor_widget' );

/**
 * Initialise the settings class.
 */
function scb_init() {
	new SCB_Breadcrumb_Settings();
}
add_action( 'plugins_loaded', 'scb_init' );
