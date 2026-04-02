<?php
/**
 * Breadcrumb renderer for Simple Custom Breadcrumb.
 *
 * Builds a breadcrumb trail by splitting the current URL path into segments
 * and resolving each segment to a human-readable label via WordPress objects
 * (page → taxonomy term → post → CPT → fallback capitalisation).
 *
 * @package Simple_Custom_Breadcrumb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SCB_Breadcrumb_Renderer
 */
class SCB_Breadcrumb_Renderer {

	/**
	 * Returns the rendered breadcrumb HTML string.
	 *
	 * @param array $overrides Optional per-instance style overrides. Supported
	 *                         keys: link_color, hover_color, current_color,
	 *                         separator_color, font_size, text_transform,
	 *                         padding, margin.  All must already be sanitized.
	 * @return string
	 */
	public function render( $overrides = array() ) {
		$opts  = SCB_Breadcrumb_Settings::get_options();
		$items = $this->build_trail();

		// Optionally hide on front page.
		if ( is_front_page() && empty( $opts['show_on_home'] ) ) {
			return '';
		}

		if ( empty( $items ) ) {
			return '';
		}

		$sep = ' <span class="scb-separator" aria-hidden="true">' . esc_html( $opts['separator_char'] ) . '</span> ';

		// Generate a unique ID and scoped inline style only when overrides are present.
		$instance_id  = '';
		$instance_css = '';

		if ( ! empty( $overrides ) ) {
			$instance_id = 'scb-breadcrumb-' . wp_unique_id();

			// Merge: start from globals, layer overrides on top.
			$merged = array(
				'link_color'      => isset( $overrides['link_color'] )      ? $overrides['link_color']      : sanitize_hex_color( $opts['link_color'] ),
				'hover_color'     => isset( $overrides['hover_color'] )     ? $overrides['hover_color']     : sanitize_hex_color( $opts['hover_color'] ),
				'current_color'   => isset( $overrides['current_color'] )   ? $overrides['current_color']   : sanitize_hex_color( $opts['active_color'] ),
				'separator_color' => isset( $overrides['separator_color'] ) ? $overrides['separator_color'] : sanitize_hex_color( $opts['separator_color'] ),
				'font_size'       => isset( $overrides['font_size'] )       ? (int) $overrides['font_size'] : absint( $opts['font_size'] ),
				'text_transform'  => isset( $overrides['text_transform'] )  ? $overrides['text_transform']  : $opts['text_transform'],
				'padding'         => isset( $overrides['padding'] )         ? $overrides['padding']         : sanitize_text_field( $opts['padding'] ),
				'margin'          => isset( $overrides['margin'] )          ? $overrides['margin']          : sanitize_text_field( $opts['margin'] ),
			);

			$sel = '#' . esc_attr( $instance_id );

			$css_rules = array(
				$sel . '{font-size:' . esc_attr( $merged['font_size'] ) . 'px;'
					. 'text-transform:' . esc_attr( $merged['text_transform'] ) . ';'
					. 'padding:' . esc_attr( $merged['padding'] ) . ';'
					. 'margin:' . esc_attr( $merged['margin'] ) . ';}',
				$sel . ' a{color:' . esc_attr( $merged['link_color'] ) . ';}',
				$sel . ' a:hover{color:' . esc_attr( $merged['hover_color'] ) . ';}',
				$sel . ' .scb-current{color:' . esc_attr( $merged['current_color'] ) . ';}',
				$sel . ' .scb-separator{color:' . esc_attr( $merged['separator_color'] ) . ';}',
			);

			$instance_css = '<style>' . implode( '', $css_rules ) . '</style>';
		}

		$nav_attrs  = 'class="scb-breadcrumb"';
		$nav_attrs .= ' aria-label="' . esc_attr__( 'Breadcrumb', 'simple-custom-breadcrumb' ) . '"';
		if ( $instance_id ) {
			$nav_attrs .= ' id="' . esc_attr( $instance_id ) . '"';
		}

		$html  = $instance_css;
		$html .= '<nav ' . $nav_attrs . '>';
		$html .= '<ol class="scb-list" itemscope itemtype="https://schema.org/BreadcrumbList">';

		$total = count( $items );
		foreach ( $items as $index => $item ) {
			$position = $index + 1;
			$is_last  = ( $position === $total );

			$html .= '<li class="scb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

			if ( $is_last ) {
				// Current page – no link, different colour.
				$html .= '<span class="scb-current" itemprop="name">' . esc_html( $item['label'] ) . '</span>';
			} else {
				$html .= '<a href="' . esc_url( $item['url'] ) . '" itemprop="item">';
				$html .= '<span itemprop="name">' . esc_html( $item['label'] ) . '</span>';
				$html .= '</a>';
				$html .= $sep;
			}

			$html .= '<meta itemprop="position" content="' . esc_attr( (string) $position ) . '" />';
			$html .= '</li>';
		}

		$html .= '</ol>';
		$html .= '</nav>';

		return $html;
	}

	// -------------------------------------------------------------------------
	// Trail builder
	// -------------------------------------------------------------------------

	/**
	 * Build an ordered array of breadcrumb items for the current page.
	 *
	 * Each item is an associative array with keys:
	 *   - 'label' (string) Human-readable label.
	 *   - 'url'   (string) Permalink (empty string for the last item).
	 *
	 * @return array<int, array{label: string, url: string}>
	 */
	private function build_trail() {
		$trail = array();

		// Always start with the home item.
		$trail[] = $this->get_home_item();

		if ( is_front_page() ) {
			return $trail;
		}

		// Special cases that don't follow the URL hierarchy.
		if ( is_search() ) {
			$trail[] = array(
				'label' => sprintf(
					/* translators: %s search query */
					esc_html__( 'Search: %s', 'simple-custom-breadcrumb' ),
					get_search_query()
				),
				'url'   => '',
			);
			return $trail;
		}

		if ( is_404() ) {
			$trail[] = array(
				'label' => esc_html__( '404 – Not Found', 'simple-custom-breadcrumb' ),
				'url'   => '',
			);
			return $trail;
		}

		if ( is_date() ) {
			$trail = array_merge( $trail, $this->get_date_items() );
			return $trail;
		}

		if ( is_author() ) {
			$trail = array_merge( $trail, $this->get_author_items() );
			return $trail;
		}

		// For all other pages (singular, archives, taxonomies, CPTs):
		// derive the breadcrumb entirely from the URL path segments so that
		// every intermediate directory is always represented.
		$trail = array_merge( $trail, $this->build_items_from_url_segments() );

		return $trail;
	}

	// -------------------------------------------------------------------------
	// URL-segments trail builder
	// -------------------------------------------------------------------------

	/**
	 * Build breadcrumb items by splitting the current URL path into segments.
	 *
	 * Each segment is resolved to a human-readable label through WordPress
	 * objects in priority order (page → taxonomy term → post → CPT → fallback).
	 * Every segment except the last becomes a clickable link; the last segment
	 * (current page) has an empty URL so the renderer can style it differently.
	 *
	 * @return array<int, array{label: string, url: string}>
	 */
	private function build_items_from_url_segments() {
		$items = array();

		// Use the sanitized request path from WordPress when available so that
		// the value is already validated by WordPress core; fall back to a
		// manually sanitised parse of REQUEST_URI.
		$request = isset( $GLOBALS['wp']->request ) ? $GLOBALS['wp']->request : '';
		if ( empty( $request ) ) {
			$raw_path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$request  = trim( wp_parse_url( $raw_path, PHP_URL_PATH ), '/' );
		}

		$path = trim( $request, '/' );

		// Strip the WordPress subdirectory prefix when installed in a subfolder.
		$home_path = trim( wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( ! empty( $home_path ) && 0 === strpos( $path, $home_path ) ) {
			$path = ltrim( substr( $path, strlen( $home_path ) ), '/' );
		}

		if ( empty( $path ) ) {
			return $items;
		}

		$segments       = explode( '/', $path );
		$cumulative_path = '';
		$total_segments = count( $segments );

		foreach ( $segments as $index => $slug ) {
			if ( empty( $slug ) ) {
				continue;
			}

			$cumulative_path .= $slug . '/';
			$is_last          = ( $index === $total_segments - 1 );

			$label = $this->get_label_for_segment( $slug, $cumulative_path );

			$items[] = array(
				'label' => $label,
				'url'   => $is_last ? '' : home_url( '/' . $cumulative_path ),
			);
		}

		return $items;
	}

	/**
	 * Resolve a URL slug to a human-readable label.
	 *
	 * Resolution order:
	 *   1. WordPress Page matching the full cumulative path (handles sub-pages).
	 *   2. WordPress Page matching the slug alone.
	 *   3. Taxonomy term with this slug (all public taxonomies, single query).
	 *   4. Published post / CPT post with this slug (single cross-type query).
	 *   5. Post-type object whose name matches the slug (CPT archive).
	 *   6. Fallback: capitalise slug, replace hyphens with spaces.
	 *
	 * @param string $slug            The URL segment slug.
	 * @param string $cumulative_path The cumulative URL path up to and including this segment (with trailing slash).
	 * @return string Human-readable label.
	 */
	private function get_label_for_segment( $slug, $cumulative_path = '' ) {
		// 1. Try WordPress Page by full cumulative path, then by slug alone.
		$page = get_page_by_path( rtrim( $cumulative_path, '/' ) );
		if ( ! $page instanceof WP_Post ) {
			$page = get_page_by_path( $slug );
		}
		if ( $page instanceof WP_Post ) {
			return get_the_title( $page );
		}

		// 2. Try taxonomy term with this slug — single query across all public taxonomies.
		$taxonomies = array_values( get_taxonomies( array( 'public' => true ), 'names' ) );
		if ( ! empty( $taxonomies ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomies,
					'slug'       => $slug,
					'hide_empty' => false,
					'number'     => 1,
				)
			);
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				return $terms[0]->name;
			}
		}

		// 3. Try published post with this slug — single cross-type query.
		$posts = get_posts(
			array(
				'name'          => $slug,
				'post_type'     => 'any',
				'post_status'   => 'publish',
				'numberposts'   => 1,
				'no_found_rows' => true,
			)
		);
		if ( ! empty( $posts ) ) {
			return get_the_title( $posts[0] );
		}

		// 4. Try CPT archive (slug might be the post-type name).
		$post_type_obj = get_post_type_object( $slug );
		if ( $post_type_obj ) {
			return $post_type_obj->labels->name;
		}

		// 5. Fallback: capitalise and replace hyphens with spaces.
		return ucwords( str_replace( '-', ' ', $slug ) );
	}

	// -------------------------------------------------------------------------
	// Item builders
	// -------------------------------------------------------------------------

	/**
	 * Build the "Home" item using the front page title when a static front page
	 * is set, otherwise the blog name.
	 *
	 * @return array{label: string, url: string}
	 */
	private function get_home_item() {
		$front_page_id = (int) get_option( 'page_on_front' );

		if ( $front_page_id ) {
			$label = get_the_title( $front_page_id );
		} else {
			$label = get_bloginfo( 'name' );
		}

		if ( empty( $label ) ) {
			$label = esc_html__( 'Home', 'simple-custom-breadcrumb' );
		}

		return array(
			'label' => $label,
			'url'   => home_url( '/' ),
		);
	}

	/**
	 * Build items for date archive pages.
	 *
	 * @return array<int, array{label: string, url: string}>
	 */
	private function get_date_items() {
		$items = array();

		if ( is_year() ) {
			$items[] = array(
				'label' => get_the_date( 'Y' ),
				'url'   => '',
			);
		} elseif ( is_month() ) {
			$items[] = array(
				'label' => get_the_date( 'Y' ),
				'url'   => get_year_link( (int) get_the_date( 'Y' ) ),
			);
			$items[] = array(
				'label' => get_the_date( 'F' ),
				'url'   => '',
			);
		} elseif ( is_day() ) {
			$items[] = array(
				'label' => get_the_date( 'Y' ),
				'url'   => get_year_link( (int) get_the_date( 'Y' ) ),
			);
			$items[] = array(
				'label' => get_the_date( 'F' ),
				'url'   => get_month_link( (int) get_the_date( 'Y' ), (int) get_the_date( 'm' ) ),
			);
			$items[] = array(
				'label' => get_the_date( 'j' ),
				'url'   => '',
			);
		}

		return $items;
	}

	/**
	 * Build items for author archive pages.
	 *
	 * @return array<int, array{label: string, url: string}>
	 */
	private function get_author_items() {
		$author = get_queried_object();
		if ( ! $author instanceof WP_User ) {
			return array();
		}

		return array(
			array(
				'label' => $author->display_name,
				'url'   => '',
			),
		);
	}

}
