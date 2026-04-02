=== Simple Custom Breadcrumb ===
Contributors: ferhatmuhamad
Tags: breadcrumb, elementor, navigation, custom post types, schema
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, customisable breadcrumb plugin with admin colour-picker settings, shortcode support, and an optional Elementor widget.

== Description ==

Simple Custom Breadcrumb automatically detects the page hierarchy from the
WordPress front page down to the currently viewed content and renders a clean,
accessible breadcrumb trail.

**Features:**

* Auto-detects WordPress Pages (with parent/child hierarchy), Posts, Custom
  Post Types, Categories, Tags, and Custom Taxonomies.
* All breadcrumb items are links **except** the last (current) item, which is
  styled with a distinct colour.
* Admin settings page (Settings > Simple Breadcrumb) with colour pickers for:
  * Link colour
  * Link hover colour
  * Current page text colour
  * Separator colour
* Configurable separator character (default `/`; supports `>`, `»`, etc.).
* Font size, text transform, padding and margin controls.
* Optional display on the front page.
* Use via shortcode: `[simple_breadcrumb]`
* Optional Elementor widget (registers automatically when Elementor is active).
* Structured data markup (Schema.org `BreadcrumbList`).
* Mobile responsive.
* Proper escaping and sanitisation throughout.

== Installation ==

1. Upload the `simple-custom-breadcrumb` folder to the `/wp-content/plugins/`
   directory, or install via the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Visit **Settings > Simple Breadcrumb** to adjust colours and other options.
4. Add `[simple_breadcrumb]` to any page, post, or Elementor widget to display
   the breadcrumb.

== Usage ==

= Shortcode =

Place `[simple_breadcrumb]` anywhere in your content or in an Elementor HTML
widget / shortcode widget.

= Elementor Widget =

When Elementor is active, a **Simple Breadcrumb** widget appears in the
General category of the Elementor panel. Drag it onto your page to add the
breadcrumb.

= Breadcrumb structure examples =

* `Home`                                         (front page)
* `Home / Products`                              (products archive)
* `Home / Products / Accessories`                (product category)
* `Home / Products / Accessories / Product Name` (single product)
* `Home / Blog`                                  (blog index)
* `Home / Blog / Category Name / Post Title`     (single post)

The last item is **not** a link and uses the "Current Page Colour" set in the
admin settings.

== Frequently Asked Questions ==

= How do I change the separator character? =

Go to **Settings > Simple Breadcrumb** and change the "Separator Character"
field. Supported values: `/`, `>`, `>>`, `»`, `›`, `-`, `|`, `\`, `·`.

= How do I display the breadcrumb in Elementor? =

Use the built-in **Simple Breadcrumb** widget in the Elementor panel, or add a
Shortcode widget and type `[simple_breadcrumb]`.

= Can I use this without Elementor? =

Yes. The plugin works independently of Elementor. Use the `[simple_breadcrumb]`
shortcode or call `do_shortcode('[simple_breadcrumb]')` from your theme.

= The Home label shows my site name instead of the front page title. =

The plugin reads the front-page title from **Settings > Reading > Your homepage
displays > A static page**. If no static front page is set, it falls back to
the site name from **Settings > General > Site Title**.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
