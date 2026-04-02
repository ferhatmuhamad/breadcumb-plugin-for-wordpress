<?php
/**
 * Breadcrumb renderer for Simple Custom Breadcrumb.
 *
 * Builds a breadcrumb trail by walking the WordPress page/post/taxonomy
 * hierarchy from the front page to the currently-viewed object.
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
			// Nothing to add; the trail already contains only "Home".
			return $trail;
		}

		if ( is_singular() ) {
			$trail = array_merge( $trail, $this->get_singular_items() );
		} elseif ( is_post_type_archive() ) {
			$trail = array_merge( $trail, $this->get_post_type_archive_items() );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$trail = array_merge( $trail, $this->get_taxonomy_items() );
		} elseif ( is_date() ) {
			$trail = array_merge( $trail, $this->get_date_items() );
		} elseif ( is_author() ) {
			$trail = array_merge( $trail, $this->get_author_items() );
		} elseif ( is_search() ) {
			$trail[] = array(
				'label' => sprintf(
					/* translators: %s search query */
					esc_html__( 'Search: %s', 'simple-custom-breadcrumb' ),
					get_search_query()
				),
				'url'   => '',
			);
		} elseif ( is_404() ) {
			$trail[] = array(
				'label' => esc_html__( '404 – Not Found', 'simple-custom-breadcrumb' ),
				'url'   => '',
			);
		} elseif ( is_page() ) {
			$trail = array_merge( $trail, $this->get_page_items() );
		}

		return $trail;
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
	 * Build items for singular posts / pages / custom post types.
	 *
	 * @return array<int, array{label: string, url: string}>
	 */
	private function get_singular_items() {
		global $post;
		$items = array();

		if ( ! $post instanceof WP_Post ) {
			return $items;
		}

		$post_type = get_post_type( $post );

		if ( 'post' === $post_type ) {
			// Standard blog post: Home / Category / Post title.
			$categories = get_the_category( $post->ID );
			if ( ! empty( $categories ) ) {
				$category = $categories[0];
				// Walk up the category parent chain.
				$cat_trail = $this->get_term_ancestors( $category );
				foreach ( $cat_trail as $cat_item ) {
					$items[] = $cat_item;
				}
			}
			// Add the post itself (current – no URL).
			$items[] = array(
				'label' => get_the_title( $post ),
				'url'   => '',
			);
			return $items;
		}

		if ( 'page' === $post_type ) {
			return $this->get_page_items();
		}

		// Custom post type.
		$post_type_obj = get_post_type_object( $post_type );

		// Archive link for the CPT (if it has one).
		if ( $post_type_obj && $post_type_obj->has_archive ) {
			$items[] = array(
				'label' => $post_type_obj->labels->name,
				'url'   => get_post_type_archive_link( $post_type ),
			);
		} elseif ( $post_type_obj ) {
			// No public archive – still show the CPT name as a plain text ancestor.
			// Try to find a matching page with the same slug as the CPT.
			$archive_page = get_page_by_path( $post_type );
			if ( $archive_page ) {
				$items[] = array(
					'label' => get_the_title( $archive_page ),
					'url'   => get_permalink( $archive_page ),
				);
			}
		}

		// Taxonomy terms (first registered taxonomy for this CPT).
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $taxonomy->public || in_array( $taxonomy->name, array( 'post_format' ), true ) ) {
				continue;
			}
			$terms = get_the_terms( $post, $taxonomy->name );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term  = $terms[0];
				$term_trail = $this->get_term_ancestors( $term );
				foreach ( $term_trail as $term_item ) {
					$items[] = $term_item;
				}
			}
			break; // Only add one taxonomy level.
		}

		// The post itself (current).
		$items[] = array(
			'label' => get_the_title( $post ),
			'url'   => '',
		);

		return $items;
	}

	/**
	 * Build items for static WordPress pages (with parent-child hierarchy).
	 *
	 * @return array<int, array{label: string, url: string}>
	 */
	private function get_page_items() {
		global $post;
		$items = array();

		if ( ! $post instanceof WP_Post ) {
			return $items;
		}

		// Collect ancestor pages.
		$ancestors = array_reverse( get_post_ancestors( $post ) );
		foreach ( $ancestors as $ancestor_id ) {
			$items[] = array(
				'label' => get_the_title( $ancestor_id ),
				'url'   => get_permalink( $ancestor_id ),
			);
		}

		// The current page (no URL).
		$items[] = array(
			'label' => get_the_title( $post ),
			'url'   => '',
		);

		return $items;
	}

	/**
	 * Build items for post-type archive pages.
	 *
	 * @return array<int, array{label: string, url: string}>
	 */
	private function get_post_type_archive_items() {
		$post_type = get_query_var( 'post_type' );
		if ( is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		}
		$post_type_obj = get_post_type_object( $post_type );

		if ( ! $post_type_obj ) {
			return array();
		}

		return array(
			array(
				'label' => $post_type_obj->labels->name,
				'url'   => '',
			),
		);
	}

	/**
	 * Build items for taxonomy archive pages (category, tag, custom taxonomy).
	 *
	 * @return array<int, array{label: string, url: string}>
	 */
	private function get_taxonomy_items() {
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return array();
		}

		$items = array();

		// For custom taxonomies, include the associated CPT archive first.
		if ( ! in_array( $term->taxonomy, array( 'category', 'post_tag' ), true ) ) {
			$taxonomy_obj = get_taxonomy( $term->taxonomy );
			if ( $taxonomy_obj ) {
				$object_types = $taxonomy_obj->object_type;
				$cpt          = reset( $object_types );
				$cpt_obj      = get_post_type_object( $cpt );
				if ( $cpt_obj && $cpt_obj->has_archive ) {
					$items[] = array(
						'label' => $cpt_obj->labels->name,
						'url'   => get_post_type_archive_link( $cpt ),
					);
				}
			}
		}

		// Walk up term ancestors (grandparent → parent → current).
		$ancestor_trail = $this->get_term_ancestors( $term );
		// Last item in ancestor trail is the current term (no URL).
		foreach ( $ancestor_trail as $i => $term_item ) {
			if ( $i < count( $ancestor_trail ) - 1 ) {
				$items[] = $term_item;
			} else {
				// Current term.
				$items[] = array(
					'label' => $term_item['label'],
					'url'   => '',
				);
			}
		}

		return $items;
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

	// -------------------------------------------------------------------------
	// Helper: walk up a term's parent chain
	// -------------------------------------------------------------------------

	/**
	 * Build an ordered array of breadcrumb items from the root term ancestor
	 * down to (and including) the given term.
	 *
	 * @param WP_Term $term The term to start from.
	 * @return array<int, array{label: string, url: string}>
	 */
	private function get_term_ancestors( WP_Term $term ) {
		$chain = array( $term );

		$current = $term;
		while ( ! empty( $current->parent ) ) {
			$parent = get_term( $current->parent, $current->taxonomy );
			if ( ! $parent instanceof WP_Term ) {
				break;
			}
			array_unshift( $chain, $parent );
			$current = $parent;
		}

		$items = array();
		foreach ( $chain as $t ) {
			$items[] = array(
				'label' => $t->name,
				'url'   => get_term_link( $t ),
			);
		}

		return $items;
	}
}
