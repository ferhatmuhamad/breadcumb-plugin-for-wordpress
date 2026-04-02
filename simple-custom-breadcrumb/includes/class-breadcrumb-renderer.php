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
	 * @return string
	 */
	public function render() {
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

		$html  = '<nav class="scb-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'simple-custom-breadcrumb' ) . '">';
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
