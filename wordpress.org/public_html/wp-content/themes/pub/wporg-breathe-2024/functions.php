<?php
namespace WordPressdotorg\Make\Breathe_2024;

/**
 * Include locale specific styles.
 */
require_once get_theme_root() . '/wporg-parent-2021/inc/rosetta-styles.php';

/**
 * Sets up theme defaults.
 */
function after_setup_theme() {
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'title-tag' );

	remove_theme_support( 'custom-header' );
	remove_theme_support( 'custom-background' );

	remove_action( 'customize_register', 'breathe_customize_register' );
	remove_action( 'customize_preview_init', 'breathe_customize_preview_js' );
	remove_filter( 'wp_head', 'breathe_color_styles' );
	add_action( 'customize_register', __NAMESPACE__ . '\customize_register' );

	add_filter( 'o2_filtered_content', __NAMESPACE__ . '\append_last_updated', 10, 2 );

	// Customize Code Syntax Block syntax highlighting theme to use styles from theme.
	// Based on the plugin's docs, this should be default behavior but isn't.
	add_filter( 'mkaz_prism_css_path', function() {
		return '/assets/prism/prism.css';
	} );

	// Use the front-end style.css as the editor styles, not perfect, but looks better than without.
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\after_setup_theme', 11 );

/**
 * Enqueue styles.
 */
function wporg_breathe_styles() {
	wp_dequeue_style( 'wp4-styles' );
	wp_dequeue_style( 'breathe-style' );
	wp_enqueue_style( 'p2-breathe', get_template_directory_uri() . '/style.css' );

	wp_enqueue_style(
		'wporg-parent-2021-style',
		get_theme_root_uri() . '/wporg-parent-2021/build/style.css',
		[ 'wporg-global-fonts' ],
		filemtime( get_theme_root() . '/wporg-parent-2021/build/style.css' )
	);

	wp_enqueue_style(
		'wporg-parent-2021-block-styles',
		get_theme_root_uri() . '/wporg-parent-2021/build/block-styles.css',
		[ 'wporg-global-fonts' ],
		filemtime( get_theme_root() . '/wporg-parent-2021/build/block-styles.css' )
	);

	wp_enqueue_style( 'wporg-breathe', get_stylesheet_uri(), array( 'p2-breathe' ), filemtime( __DIR__ . '/style.css' ) );

	// Preload the heading font(s).
	if ( is_callable( 'global_fonts_preload' ) ) {
		/* translators: Subsets can be any of cyrillic, cyrillic-ext, greek, greek-ext, vietnamese, latin, latin-ext. */
		$subsets = _x( 'Latin', 'Heading font subsets, comma separated', 'wporg-breathe' );
		// All headings.
		global_fonts_preload( 'Inter', $subsets );
	}
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\wporg_breathe_styles', 11 );

/**
 * Merge the support theme's theme.json into the parent theme.json.
 *
 * @param WP_Theme_JSON_Data $theme_json Parsed support theme.json.
 *
 * @return WP_Theme_JSON_Data The updated theme.json settings.
 */
function wporg_breathe_merge_theme_json( $theme_json ) {
	$support_theme_json_data = $theme_json->get_data();
	$parent_theme_json_data = json_decode( file_get_contents( get_theme_root( 'wporg-parent-2021' ) . '/wporg-parent-2021/theme.json' ), true );

	if ( ! $parent_theme_json_data ) {
		return $theme_json;
	}

	$parent_theme = class_exists( 'WP_Theme_JSON_Gutenberg' )
		? new \WP_Theme_JSON_Gutenberg( $parent_theme_json_data )
		: new \WP_Theme_JSON( $parent_theme_json_data );

	// Build a new theme.json object based on the parent.
	$new_data = $parent_theme_json_data;
	$support_settings = $support_theme_json_data['settings'];
	$support_styles = $support_theme_json_data['styles'];

	if ( ! empty( $support_settings ) ) {
		$parent_settings = $parent_theme->get_settings();

		$new_data['settings'] = _recursive_array_merge( $parent_settings, $support_settings );
	}

	if ( ! empty( $support_styles ) ) {
		$parent_styles = $parent_theme_json_data['styles'];

		$new_data['styles'] = _recursive_array_merge( $parent_styles, $support_styles );
	}

	return $theme_json->update_with( $new_data );

}
add_filter( 'wp_theme_json_data_theme', __NAMESPACE__ . '\wporg_breathe_merge_theme_json' );

/**
 * Merge two arrays recursively, overwriting keys in the first array with keys from the second array.
 *
 * @param array $array1
 * @param array $array2
 *
 * @return array
 */
function _recursive_array_merge( $array1, $array2 ) {
	foreach ( $array2 as $key => $value ) {
		// If the key exists in the first array and both values are arrays, recursively merge them
		if ( isset( $array1[ $key ] ) && is_array( $value ) && is_array( $array1[ $key ] ) ) {
			// Check if both arrays are indexed (not associative)
			if ( array_values( $array1[ $key ] ) === $array1[ $key ] && array_values( $value ) === $value ) {
				// Use _merge_by_slug for indexed arrays
				$array1[ $key ] = _merge_by_slug( $array1[ $key ], $value );
			} else {
				// Use recursive merge for associative arrays
				$array1[ $key ] = _recursive_array_merge( $array1[ $key ], $value );
			}
		} else {
			$array1[ $key ] = $value;
		}
	}

	return $array1;
}

/**
 * Merge two (or more) arrays, de-duplicating by the `slug` key.
 *
 * If any values in later arrays have slugs matching earlier items, the earlier
 * items are overwritten with the later value.
 *
 * @param array ...$arrays A list of arrays of associative arrays, each item
 *                         must have a `slug` key.
 *
 * @return array The combined array, unique by `slug`. Empty if any item is
 *               missing a slug.
 */
function _merge_by_slug( ...$arrays ) {
	$combined = array_merge( ...$arrays );
	$result   = [];

	foreach ( $combined as $value ) {
		if ( ! isset( $value['slug'] ) ) {
			return [];
		}

		$found = array_search( $value['slug'], wp_list_pluck( $result, 'slug' ), true );
		if ( false !== $found ) {
			$result[ $found ] = $value;
		} else {
			$result[] = $value;
		}
	}

	return $result;
}

/**
 * Get the primary navigation menu object if it exists.
 */
function wporg_breathe_get_local_nav_menu_object() {
	$local_nav_menu_locations = get_nav_menu_locations();
	$local_nav_menu_object = isset( $local_nav_menu_locations['primary'] )
		? wp_get_nav_menu_object( $local_nav_menu_locations['primary'] )
		: false;

	return $local_nav_menu_object;
}

/**
 * Add a login link to the local nav if there is no logged in user.
 */
function _maybe_add_login_item_to_menu( $menus ) {
	if ( is_user_logged_in() ) {
		return $menus;
	}

	global $wp;
	$redirect_url = home_url( $wp->request );
	$login_item = array(
		'label' => __( 'Log in', 'wporg-breathe' ),
		'url' => wp_login_url( $redirect_url ),
	);

	if ( ! empty( $menus['breathe'] ) ) {
		$login_item['className'] = 'has-separator';
		$menus['breathe'][] = $login_item;
	} else {
		$menus['breathe'] = array( $login_item );
	}

	return $menus;
}

/**
 * Provide a list of local navigation menus.
 */
function wporg_breathe_add_site_navigation_menus( $menus ) {
	if ( is_admin() ) {
		return;
	}

	$local_nav_menu_object = wporg_breathe_get_local_nav_menu_object();

	if ( ! $local_nav_menu_object ) {
		return _maybe_add_login_item_to_menu( $menus );
	}

	$menu_items = wp_get_nav_menu_items( $local_nav_menu_object->term_id );

	if ( ! $menu_items || empty( $menu_items ) ) {
		return _maybe_add_login_item_to_menu( $menus );
	}

	$menu = array_map(
		function( $menu_item ) {
			global $wp;
			$is_current_page = trailingslashit( $menu_item->url ) === trailingslashit( home_url( $wp->request ) );

			return array(
				'label' => esc_html( $menu_item->title ),
				'url' => esc_url( $menu_item->url ),
				'className' => $is_current_page ? 'current-menu-item' : '',
			);
		},
		// Limit local nav items to 6
		array_slice( $menu_items, 0, 6 )
	);

	$menus['breathe'] = $menu;

	return _maybe_add_login_item_to_menu( $menus );
}
add_filter( 'wporg_block_navigation_menus', __NAMESPACE__ . '\wporg_breathe_add_site_navigation_menus' );

/**
 * Add postMessage support for site title and description in the customizer.
 *
 * @param WP_Customize_Manager $wp_customize The customizer object.
 */
function customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport = 'postMessage';

	if ( isset( $wp_customize->selective_refresh ) ) {
		$wp_customize->selective_refresh->add_partial( 'blogname', [
			'selector'            => '.site-title a',
			'container_inclusive' => false,
			'render_callback'     => __NAMESPACE__ . '\customize_partial_blogname',
		] );
	}
}

/**
 * noindex certain archives.
 */
function no_robots( $noindex ) {
	if ( is_tax( 'mentions' ) ) {
		$noindex = true;
	}

	if ( get_query_var( 'o2_recent_comments' ) ) {
		$noindex = true;
	}


	// This is used by https://github.com/WordPress/phpunit-test-reporter/blob/master/src/class-display.php on the test reporter page
	if ( isset( $_GET['rpage'] ) ) {
		$noindex = true;
	}

	return $noindex;
}
add_filter( 'wporg_noindex_request', __NAMESPACE__ . '\no_robots' );

/**
 * Renders the site title for the selective refresh partial.
 */
function customize_partial_blogname() {
	bloginfo( 'name' );
}

function scripts() {
	wp_enqueue_script( 'wporg-breathe-chapters', get_stylesheet_directory_uri() . '/js/chapters.js', array( 'jquery' ), '20200127' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts', 11 );

function inline_scripts() {
	$current_site = get_site();
	?>
	<script type="text/javascript">
		var el = document.getElementById( 'make-welcome-toggle' );
		if ( el ) {
			el.addEventListener( 'click', function( e ) {
				var $welcome = jQuery( '.make-welcome' ),
					$toggle  = $welcome.find( '#make-welcome-toggle'),
					$content = $welcome.find( '#make-welcome-content'),
					isHide   = ! $content.is( ':hidden' );

				// Toggle it
				$toggle.text( $toggle.data( isHide ? 'show' : 'hide' ) );
				$welcome.get( 0 ).classList.toggle( 'collapsed', isHide );
				$content.slideToggle();
				$welcome.find('.post-edit-link' ).toggle( ! isHide );

				// Remember it
				document.cookie = $content.data( 'cookie' ) + '=' +
					( isHide ? $content.data( 'hash' ) : '' ) +
					'; expires=Fri, 31 Dec 9999 23:59:59 GMT' +
					'; domain=<?php echo esc_js( $current_site->domain ); ?>' +
					'; path=<?php echo esc_js( $current_site->path ); ?>';
			} );
		}
	</script>
	<?php
}
add_action( 'wp_footer', __NAMESPACE__ . '\inline_scripts' );

function welcome_box() {
	$welcome      = get_page_by_path( 'welcome' );
	$cookie       = 'welcome-' . get_current_blog_id();
	$path         = get_blog_details()->path;
	$hash         = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : '';
	$content_hash = $welcome ? md5( $welcome->post_content ) : '';

	if ( ! $welcome ) {
		return;
	}

	$columns = preg_split( '|<hr\s*/?>|', $welcome->post_content );
	if ( count( $columns ) === 2 ) {
		$welcome->post_content = "<div class='content-area'>\n\n{$columns[0]}</div><div class='widget-area'>\n\n{$columns[1]}</div>";
	}

	setup_postdata( $welcome );
	$GLOBALS['post'] = $welcome; // setup_postdata() doesn't do this for us.

	// Disable Jetpack sharing buttons
	add_filter( 'sharing_show', '__return_false' );
	// Disable o2 showing the post inline
	add_filter( 'o2_post_fragment', '__return_empty_array' );
	?>
	<div class="make-welcome">
		<a href="#" id="secondary-toggle" onclick="return false;"><strong><?php _e( 'Menu' ); ?></strong></a>
		<div class="entry-meta">
			<?php edit_post_link( __( 'Edit', 'wporg' ), '', '', $welcome->ID, 'post-edit-link make-welcome-edit-post-link' ); ?>
			<button
				type="button"
				id="make-welcome-toggle"
				data-show="<?php esc_attr_e( 'Show welcome box', 'wporg' ); ?>"
				data-hide="<?php esc_attr_e( 'Hide welcome box', 'wporg' ); ?>"
			><span><?php _e( 'Hide welcome box', 'wporg' ); ?></span></button>
		</div>
		<div class="entry-content clear" id="make-welcome-content" data-cookie="<?php echo $cookie; ?>" data-hash="<?php echo $content_hash; ?>">
			<script type="text/javascript">
				const elContent = document.getElementById( 'make-welcome-content' );

				if ( elContent ) {
					const hasCookieSetToHidden = -1 !== document.cookie.indexOf( elContent.dataset.cookie + '=' + elContent.dataset.hash );
					const isHome = window.location.pathname === '<?php echo esc_js( $path ); ?>';

					if ( hasCookieSetToHidden || ! isHome ) {
						const elToggle = document.getElementById( 'make-welcome-toggle' );
						const elEditLink = document.getElementsByClassName( 'make-welcome-edit-post-link' );
						const elContainer = document.querySelector( '.make-welcome' );

						// It's hidden, hide it ASAP.
						elContent.className += " hidden";
						elToggle.innerText = elToggle.dataset.show;

						// Add class to welcome box container indicating collapsed state.
						elContainer && elContainer.classList.add( 'collapsed' );

						if ( elEditLink.length ) {
							elEditLink[0].className += " hidden";
						}
					}
				}
			</script>
			<?php the_content(); ?>
		</div>
	</div>
	<?php
	remove_filter( 'sharing_show', '__return_false' );
	remove_filter( 'o2_post_fragment', '__return_empty_array' );

	$GLOBALS['post'] = false; // wp_reset_postdata() may not do this.
	wp_reset_postdata();
}
add_action( 'wporg_breathe_after_header', __NAMESPACE__ . '\welcome_box' );

function javascript_notice() {
	?>
	<noscript class="js-disabled-notice">
		<?php _e( 'Please enable JavaScript to view this page properly.', 'wporg' ); ?>
	</noscript>
	<?php
}
add_action( 'wp_footer', __NAMESPACE__ . '\javascript_notice' );

/**
 * Adds each site's slug to the body class, so that CSS rules can target specific sites.
 *
 * @param array $classes Array of CSS classes.
 * @return array Array of CSS classes.
 */
function add_site_slug_to_body_class( $classes ) {
	$current_site = get_site( get_current_blog_id() );

	$classes[] = 'wporg-make';
	if ( $current_site ) {
		$classes[] = 'make-' . trim( $current_site->path, '/' );
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\add_site_slug_to_body_class' );

/**
 * Defines `link_before` and `link_after` to make icon items accessible for screen readers.
 *
 * @param object  $args  An object of wp_nav_menu() arguments.
 * @param WP_Post $item  Menu item data object.
 * @return object An object of wp_nav_menu() arguments.
 */
function add_screen_reader_text_for_icon_menu_items( $args, $item ) {
	if ( in_array( 'icon', $item->classes, true ) ) {
		$args->link_before = '<span class="screen-reader-text">';
		$args->link_after  = '</span>';
	}

	return $args;
}
add_filter( 'nav_menu_item_args', __NAMESPACE__ . '\add_screen_reader_text_for_icon_menu_items', 10, 2 );

/**
 * Disables Jetpack Mentions on any handbook page or comment.
 *
 * More precisely, this prevents the linked mentions from being shown. A more
 * involved approach (to include clearing meta-cached data) would be needed to
 * more efficiently prevent mentions from being looked for in the first place.
 *
 * @param string $linked  The linked mention.
 * @param string $mention The term being mentioned.
 * @return string
 */
function disable_mentions_for_handbook( $linked, $mention ) {
	if ( function_exists( 'wporg_is_handbook' ) && wporg_is_handbook() && ! is_single( 'credits' ) ) {
		return '@' . $mention;
	}

	return $linked;
}
add_filter( 'jetpack_mentions_linked_mention', __NAMESPACE__ . '\disable_mentions_for_handbook', 10, 2 );

/**
 * More contextual link title for post authors.
 *
 * @param array    $bootstrap_model O2 user model.
 * @param \WP_User $user_data       User data.
 *
 * @return array
 */
function user_model( $bootstrap_model, $user_data ) {
	/* translators: 1: User display_name; 2: User nice_name */
	$bootstrap_model['urlTitle'] = sprintf( __( 'Profile of %1$s (%2$s)', 'wporg' ), $user_data->display_name, '@' . $user_data->user_nicename );

	return $bootstrap_model;
}
add_filter( 'o2_user_model', __NAMESPACE__ . '\user_model', 10, 2 );

/**
 * Fixes bug in (or at least in using) SyntaxHighlighter code shortcodes that
 * causes double-encoding of `>` and '&' characters.
 *
 * @param string $content The text being handled as code.
 * @return string
 */
function fix_code_entity_encoding( $content ) {
	return str_replace( [ '&amp;gt;', '&amp;amp;' ], [ '&gt;', '&amp;' ], $content );
}
add_filter( 'syntaxhighlighter_htmlresult', __NAMESPACE__ . '\fix_code_entity_encoding', 20 );

/**
 * Appends a 'Last updated' to handbook pages.
 *
 * @param string $content Content of the current post.
 * @return Content of the current post.
 */
function append_last_updated( $content, $post ) {
	if ( ! function_exists( 'wporg_is_handbook' ) || ! wporg_is_handbook( $post->post_type ) ) {
		return $content;
	}

	$content .= sprintf(
		/* translators: %s: Date of last update. */
		'<p class="handbook-last-updated">' . __( 'Last updated: %s', 'wporg' ) . '</p>',
		sprintf(
			'<time datetime="%s">%s</time>',
			esc_attr( get_the_modified_date( DATE_W3C ) ),
			esc_html( get_the_modified_date() )
		)
	);

	return $content;
}

/**
 * Noindex some requests:
 *  - all o2 taxonomy pages, rather than the default of only noindexing archives with less than 3 posts
 *  - Posts/pages/etc with less than 100char.
 */
function maybe_noindex( $noindex ) {
	// Noindex all o2 taxonomy pages.
	if ( is_tax() || is_tag() || is_category() ) {
		$noindex = true;
	}

	// Noindex empty/short pages
	if ( is_singular() && strlen( get_the_content() ) < 100 ) {
		$noindex = true;
	}

	return $noindex;
}
add_filter( 'wporg_noindex_request', __NAMESPACE__ . '\maybe_noindex' );

/**
 * Outputs team icons represented via SVG images using the `svg` tag (as opposed to via CSS).
 *
 * While the SVG could easily, and more cleanly, be added via CSS, doing so would not allow the SVGs
 * to otherwise inherit the link colors (such as on :hover). If the theme changes to move the team
 * icon outside of the link, or if matching the link color is no longer required, then the SVG
 * definitions can be moved to CSS.
 *
 * Currently handles the following teams:
 * - Core Performance
 * - Openverse
 * - Playground
 *
 * Note: Defining a team's icon in this way also requires adjusting the site's styles to not expect
 * a ::before content of a dashicon font character. (Search style.css for: Adjustments for teams with SVG icons.)
 */
function add_svg_icon_to_site_name() {
	$site = get_site();

	if ( ! $site ) {
		return;
	}

	$svg = [];

	if ( '/openverse/' === $site->path ) :
		$svg = [
			'viewbox' => '0 16 200 200',
			'paths'   => [
				'M142.044 93.023c16.159 0 29.259-13.213 29.259-29.512 0-16.298-13.1-29.511-29.259-29.511s-29.259 13.213-29.259 29.511c0 16.299 13.1 29.512 29.259 29.512ZM28 63.511c0 16.24 12.994 29.512 29.074 29.512V34C40.994 34 28 47.19 28 63.511ZM70.392 63.511c0 16.24 12.994 29.512 29.074 29.512V34c-15.998 0-29.074 13.19-29.074 29.511ZM142.044 165.975c16.159 0 29.259-13.213 29.259-29.512 0-16.298-13.1-29.511-29.259-29.511s-29.259 13.213-29.259 29.511c0 16.299 13.1 29.512 29.259 29.512ZM70.392 136.414c0 16.257 12.994 29.544 29.074 29.544v-59.006c-15.999 0-29.074 13.204-29.074 29.462ZM28 136.414c0 16.34 12.994 29.544 29.074 29.544v-59.006c-16.08 0-29.074 13.204-29.074 29.462Z',
			],
		];

	elseif ( '/performance/' === $site->path ) :
		$svg = [
			'viewbox' => '0 8 94 94',
			'paths'   => [
				'M39.21 20.85h-11.69c-1.38 0-2.5 1.12-2.5 2.5v11.69c0 1.38 1.12 2.5 2.5 2.5h11.69c1.38 0 2.5-1.12 2.5-2.5v-11.69c0-1.38-1.12-2.5-2.5-2.5z',
				'M41.71,58.96v11.69c0,.66-.26,1.3-.73,1.77-.47,.47-1.11,.73-1.77,.73h-11.69c-.66,0-1.3-.26-1.77-.73-.47-.47-.73-1.11-.73-1.77v-21.37c0-.4,.1-.79,.28-1.14,.03-.06,.07-.12,.1-.18,.21-.33,.49-.61,.83-.82l11.67-7.04c.44-.27,.95-.39,1.47-.36,.51,.03,1,.23,1.4,.55,.26,.21,.47,.46,.63,.75,.16,.29,.26,.61,.29,.94,.02,.11,.02,.22,.02,.34v5.38s0,.07,0,.11v11.08s0,.04,0,.07Z',
				'M68.98,30.23v16.84c0,.33-.06,.65-.19,.96-.13,.3-.31,.58-.54,.81l-6.88,6.88c-.23,.23-.51,.42-.81,.54-.3,.13-.63,.19-.96,.19h-13.15c-.66,0-1.3-.26-1.77-.73-.47-.47-.73-1.11-.73-1.77v-11.69c0-.66,.26-1.3,.73-1.77,.47-.47,1.11-.73,1.77-.73h13.08s1.11,0,1.11-1.11-1.11-1.11-1.11-1.11h-13.08c-.66,0-1.3-.26-1.77-.73s-.73-1.11-.73-1.77v-11.69c0-.66,.26-1.3,.73-1.77,.47-.47,1.11-.73,1.77-.73h13.15c.33,0,.65,.06,.96,.19,.3,.13,.58,.31,.81,.54l6.88,6.88c.23,.23,.42,.51,.54,.81,.13,.3,.19,.63,.19,.96Z',
			],
		];

	elseif ( '/playground/' === $site->path ) :
		$svg = [
			'viewbox' => '0 0 20 20',
			'paths'   => [
				'M12.4226 14.5825C12.4286 14.3131 12.4061 14.0201 12.3503 13.7039C12.1329 12.4716 11.4189 11.0521 10.1826 9.81571C8.94619 8.57933 7.52669 7.8654 6.29442 7.64795C5.97853 7.5922 5.68592 7.56975 5.4168 7.57569C5.88224 9.00652 6.787 10.5205 8.1331 11.8666C9.47873 13.2123 10.9922 14.1169 12.4226 14.5825ZM3.36813 6.3275C3.14159 4.42798 3.59436 2.68578 4.7961 1.48404C7.41899 -1.13885 12.6165 -0.193889 16.4051 3.59468C20.1936 7.38324 21.1386 12.5808 18.5157 15.2036C17.3135 16.4058 15.5705 16.8585 13.6701 16.6314C13.4934 16.9719 13.2678 17.284 12.9919 17.5599C11.7227 18.8291 9.68842 19.0344 7.63054 18.3064C7.51496 18.6778 7.32136 19.0113 7.04626 19.2864C5.80767 20.525 3.3861 20.1116 1.63753 18.363C-0.111035 16.6144 -0.524454 14.1929 0.714135 12.9543C0.988909 12.6795 1.3219 12.486 1.69285 12.3704C0.96373 10.3116 1.16857 8.27616 2.43837 7.00636C2.7146 6.73012 3.02707 6.50429 3.36813 6.3275ZM5.06371 5.85902C4.95395 4.47114 5.33586 3.39703 6.02247 2.71042C6.81773 1.91516 8.13284 1.52866 9.85322 1.84145C11.5655 2.15275 13.5023 3.14466 15.1787 4.82105C16.8551 6.49745 17.847 8.43428 18.1583 10.1465C18.4711 11.8669 18.0846 13.182 17.2893 13.9773C16.6025 14.6641 15.5278 15.0461 14.1391 14.9359C14.3018 12.9332 13.3392 10.5196 11.4089 8.58933C9.47914 6.65953 7.06613 5.69685 5.06371 5.85902ZM11.8657 16.2252C11.8334 16.263 11.8 16.2991 11.7655 16.3335C11.2555 16.8435 10.3747 17.1369 9.13587 16.9183C7.90359 16.7009 6.48409 15.9869 5.24772 14.7506C4.01134 13.5142 3.29741 12.0947 3.07996 10.8624C2.86135 9.62355 3.15474 8.74274 3.66475 8.23273C3.6994 8.19808 3.73577 8.16443 3.77385 8.13186C4.33353 9.84112 5.39321 11.5795 6.90673 13.093C8.41961 14.6059 10.1572 15.6653 11.8657 16.2252ZM4.02134 15.9769C4.64712 16.6027 5.3237 17.1268 6.02062 17.5438C5.99738 17.8222 5.90615 17.9738 5.81988 18.06C5.70404 18.1759 5.41511 18.336 4.82501 18.2353C4.23996 18.1354 3.5143 17.787 2.86391 17.1366C2.21352 16.4862 1.86515 15.7606 1.76527 15.1755C1.66453 14.5854 1.82467 14.2965 1.94051 14.1806C2.02572 14.0954 2.17765 14.0031 2.45584 13.9799C2.87263 14.6761 3.39625 15.3518 4.02134 15.9769Z',
			],
			'pathFillRule' => 'evenodd',
			'pathClipRule' => 'evenodd',
			'pathStroke' => 'none',
		];

	endif;

	if ( empty( $svg['viewbox'] ) || empty( $svg['paths'] ) ) {
		return;
	}

	printf( '<svg aria-hidden="true" role="img" viewBox="%s" xmlns="http://www.w3.org/2000/svg">' . "\n", esc_attr( $svg['viewbox'] ) );

	foreach ( $svg['paths'] as $path ) {
		printf(
			"\t" . '<path d="%s" stroke="%s" fill="currentColor" fill-rule="%s" clip-rule="%s"/>' . "\n",
			esc_attr( $path ),
			esc_attr( $svg['pathStroke'] ?? 'currentColor' ),
			esc_attr( $svg['pathFillRule'] ),
			esc_attr( $svg['pathClipRule'] )
		);
	}

	echo "</svg>";
}
add_action( 'wporg_breathe_before_name', __NAMESPACE__ . '\add_svg_icon_to_site_name' );

/**
 * Register translations for plugins without their own GlotPress project.
 */
// wp-content/plugins/wporg-o2-posting-access/wporg-o2-posting-access.php
/* translators: %s: Post title */
__( 'Pending Review: %s', 'wporg' );
__( 'Submit for review', 'wporg' );
_n_noop( '%s post awaiting review', '%s posts awaiting review', 'wporg' );

/**
 * Modify the search block's form action for handbook pages.
 *
 * @param string $block_content The block content about to be appended.
 * @param array  $block         The full block, including name and attributes.
 * @return string Modified block content.
 */
function modify_handbook_search_block_action( $block_content, $block ) {
	if ( function_exists( 'wporg_is_handbook' ) && wporg_is_handbook() ) {
		$html = wp_html_split( $block_content );
		
		foreach ( $html as &$token ) {
			if ( 0 === strpos( $token, '<form' ) ) {
				$token = preg_replace(
					'/action="[^"]*"/',
					'action="' . esc_url( home_url( '/handbook/' ) ) . '"',
					$token
				);
				break;
			}
		}
		
		$block_content = implode( '', $html );
	}
	return $block_content;
}
add_filter( 'render_block_core/search', __NAMESPACE__ . '\modify_handbook_search_block_action', 10, 2 );

/**
 * Display navigation to next/previous pages when applicable
 * Customized to fix issues with heading hierarchy.
 */
function breathe_content_nav( $nav_id ) {
	global $wp_query, $post;

	// Don't print empty markup on single pages if there's nowhere to navigate.
	if ( is_single() ) {
		$previous = ( is_attachment() ) ? get_post( $post->post_parent ) : get_adjacent_post( false, '', true );
		$next = get_adjacent_post( false, '', false );

		if ( ! $next && ! $previous )
			return;
	}

	// Don't print empty markup in archives if there's only one page.
	if ( $wp_query->max_num_pages < 2 && ( is_home() || is_archive() || is_search() ) )
		return;

	$nav_class = ( is_single() ) ? 'navigation-post' : 'navigation-paging';

	?>
	<nav role="navigation" id="<?php echo esc_attr( $nav_id ); ?>" class="<?php echo $nav_class; ?>">
		<h2 class="screen-reader-text"><?php _e( 'Post navigation', 'p2-breathe' ); ?></h2>

	<?php if ( is_single() ) : // navigation links for single posts ?>

		<?php previous_post_link( '<div class="nav-previous">%link</div>', '<span class="meta-nav">' . _x( '&larr;', 'Previous post link', 'p2-breathe' ) . '</span> %title' ); ?>
		<?php next_post_link( '<div class="nav-next">%link</div>', '%title <span class="meta-nav">' . _x( '&rarr;', 'Next post link', 'p2-breathe' ) . '</span>' ); ?>

	<?php elseif ( $wp_query->max_num_pages > 1 && ( is_home() || is_archive() || is_search() ) ) : // navigation links for home, archive, and search pages ?>

		<?php if ( get_next_posts_link() ) : ?>
		<div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older posts', 'p2-breathe' ) ); ?></div>
		<?php endif; ?>

		<?php if ( get_previous_posts_link() ) : ?>
		<div class="nav-next"><?php previous_posts_link( __( 'Newer posts <span class="meta-nav">&rarr;</span>', 'p2-breathe' ) ); ?></div>
		<?php endif; ?>

	<?php endif; ?>

	</nav><!-- #<?php echo esc_html( $nav_id ); ?> -->
	<?php
}

/**
 * Modify rendering of the site-title block.
 * Insert the team icon before the anchor tag, if it exists.
 * 
 * On the project and updates sites, update the text and link so that in the local nav
 * it appears as if pages from these sites belong to the home site, and not separate blogs.
 */
function modify_site_title_block( $block_content, $block ) {
	ob_start();
	do_action('wporg_breathe_before_name', 'front');
	$icon = ob_get_clean();
	
	// Insert the icon inside the anchor tag, before the text
	$block_content = preg_replace(
		'/(<a\b[^>]*>)(.*?)(<\/a>)/',
		'$1' . $icon . '$2$3',
		$block_content
	);
	
	$site = get_site();
	// On the project and updates sites replace the link with a Make home page link
	if ( '/project/' === $site->path || '/updates/' === $site->path ) {
		$make_home_url = 'https://' . $site->domain;
		$block_content = preg_replace( 
			'/<a\b[^>]*>(.*?)<\/a>/',
			'<a target="_self" rel="home" href="' . esc_url( $make_home_url ) . '">' . 
			esc_html__( 'Make WordPress', 'wporg-breathe' ) . 
			'</a>', 
			$block_content 
		);
	}

	return $block_content;
}
add_filter( 'render_block_core/site-title', __NAMESPACE__ . '\modify_site_title_block', 10, 2 );
