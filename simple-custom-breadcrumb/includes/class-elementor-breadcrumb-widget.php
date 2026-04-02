<?php
/**
 * Elementor widget for Simple Custom Breadcrumb.
 *
 * @package Simple_Custom_Breadcrumb
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Class SCB_Elementor_Breadcrumb_Widget
 */
class SCB_Elementor_Breadcrumb_Widget extends \Elementor\Widget_Base {

/**
 * Widget name (slug).
 *
 * @return string
 */
public function get_name() {
return 'simple_custom_breadcrumb';
}

/**
 * Widget title shown in the Elementor panel.
 *
 * @return string
 */
public function get_title() {
return esc_html__( 'Simple Breadcrumb', 'simple-custom-breadcrumb' );
}

/**
 * Widget icon (Elementor icon class).
 *
 * @return string
 */
public function get_icon() {
return 'eicon-navigation-horizontal';
}

/**
 * Widget categories.
 *
 * @return string[]
 */
public function get_categories() {
return array( 'general' );
}

/**
 * Register widget controls.
 * No extra controls needed – all styling is handled by the Settings page.
 */
protected function register_controls() {
$this->start_controls_section(
'section_info',
array(
'label' => esc_html__( 'Breadcrumb', 'simple-custom-breadcrumb' ),
)
);

$this->add_control(
'info',
array(
'type'            => \Elementor\Controls_Manager::RAW_HTML,
'raw'             => esc_html__(
'The breadcrumb is rendered automatically based on the current page. Colours and other settings can be adjusted in Settings > Simple Breadcrumb.',
'simple-custom-breadcrumb'
),
'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
)
);

$this->end_controls_section();
}

/**
 * Render the widget output on the front end.
 */
protected function render() {
$renderer = new SCB_Breadcrumb_Renderer();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $renderer->render();
}
}
