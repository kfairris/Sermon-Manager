<?php
/**
 * Everything related to shortcodes.
 *
 * @package SM/Core/Shortcodes
 */

defined( 'ABSPATH' ) or die;

/**
 * Class SM_Shortcodes, initializes all the shortcodes.
 */
class SM_Shortcodes {
	/**
	 * Instance of this class
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Add all shortcodes to WP and assign functions
	 *
	 * @return void
	 */
	public function init() {
		// List all series or speakers in a simple unordered list.
		add_shortcode( 'list_sermons', array( self::get_instance(), 'display_sermons_list' ) );
		// Display all series or speakers in a grid of images.
		add_shortcode( 'sermon_images', array( self::get_instance(), 'display_images' ) );
		// Display the latest sermon series image (optional - by service type).
		add_shortcode( 'latest_series', array( self::get_instance(), 'display_latest_series_image' ) );
		// Main shortcode.
		add_shortcode( 'sermons', array( self::get_instance(), 'display_sermons' ) );
		// Add alternative shortcode for case when Sermon Browser is used at the same time.
		add_shortcode( 'sermons_sm', array( self::get_instance(), 'display_sermons' ) );
		// Filtering shortcode.
		add_shortcode( 'sermon_sort_fields', array( self::get_instance(), 'display_sermon_sorting' ) );

		// Load deprecated shortcode aliasing.
		$this->legacy_shortcodes();
	}

	/**
	 * Get new instance self or current one if exists.
	 *
	 * @return SM_Shortcodes
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * They are here for compatibility purposes.
	 */
	public function legacy_shortcodes() {
		add_shortcode( 'list-sermons', array( self::get_instance(), 'display_sermons_list' ) );
		add_shortcode( 'sermon-images', array( self::get_instance(), 'display_images' ) );
	}

	/**
	 * Display an unordered list of series, preachers, topics or books.
	 *
	 * @param array[] $atts Shortcode parameters.
	 *
	 * @type string   $atts ['display'] The taxonomy, possible options: series, preachers, topics, books.
	 * @type string   $atts ['order'] Sorting order, possible options: ASC, DESC.
	 * @type string   $atts ['ordrerby'] Possible options: id, count, name, slug, term_group, none.
	 *
	 * @return string List or error message.
	 */
	public function display_sermons_list( $atts ) {
		// Enqueue scripts and styles.
		if ( ! defined( 'SM_ENQUEUE_SCRIPTS_STYLES' ) ) {
			define( 'SM_ENQUEUE_SCRIPTS_STYLES', true );
		}

		// Unquote.
		if ( is_array( $atts ) || is_object( $atts ) ) {
			foreach ( $atts as &$att ) {
				$att = $this->_unquote( $att );
			}
		}

		// Default options.
		$args = array(
			'display' => 'series',
			'order'   => 'ASC',
			'orderby' => 'name',
		);

		// For compatibility.
		if ( ! empty( $atts['tax'] ) ) {
			$atts['display'] = $atts['tax'];
			unset( $atts['tax'] );
		}

		// For compatibility.
		if ( ! empty( $atts['taxonomy'] ) ) {
			$atts['display'] = $atts['taxonomy'];
			unset( $atts['taxonomy'] );
		}

		// Join default and user options.
		$args = shortcode_atts( $args, $atts, 'list_sermons' );

		// Check if we are using a SM taxonomy, and if we are, convert to valid taxonomy name.
		if ( $this->convert_taxonomy_name( $args['display'], true ) ) {
			$args['display'] = $this->convert_taxonomy_name( $args['display'], false );
		} elseif ( ! $this->convert_taxonomy_name( $args['display'], false ) ) {
			return '<strong>Error: Invalid "list" parameter.</strong><br> Possible values are: "series", "preachers", "topics" and "books".<br> You entered: "<em>' . $args['display'] . '</em>"';
		}

		$query_args = array(
			'taxonomy' => $args['display'],
			'orderby'  => $args['orderby'],
			'order'    => $args['order'],
		);

		if ( 'date' === $query_args['orderby'] ) {
			$query_args['orderby']        = 'meta_value_num';
			$query_args['meta_key']       = 'sermon_date';
			$query_args['meta_compare']   = '<=';
			$query_args['meta_value_num'] = time();
		}

		// Get items.
		$terms = get_terms( $query_args );

		if ( count( $terms ) > 0 ) {
			// Sort books by order.
			if ( 'wpfc_bible_book' === $args['display'] && 'book' === $args['orderby'] ) {
				// Book order.
				$books = array(
					'Genesis',
					'Exodus',
					'Leviticus',
					'Numbers',
					'Deuteronomy',
					'Joshua',
					'Judges',
					'Ruth',
					'1 Samuel',
					'2 Samuel',
					'1 Kings',
					'2 Kings',
					'1 Chronicles',
					'2 Chronicles',
					'Ezra',
					'Nehemiah',
					'Esther',
					'Job',
					'Psalm',
					'Proverbs',
					'Ecclesiastes',
					'Song of Songs',
					'Isaiah',
					'Jeremiah',
					'Lamentations',
					'Ezekiel',
					'Daniel',
					'Hosea',
					'Joel',
					'Amos',
					'Obadiah',
					'Jonah',
					'Micah',
					'Nahum',
					'Habakkuk',
					'Zephaniah',
					'Haggai',
					'Zechariah',
					'Malachi',
					'Matthew',
					'Mark',
					'Luke',
					'John',
					'Acts',
					'Romans',
					'1 Corinthians',
					'2 Corinthians',
					'Galatians',
					'Ephesians',
					'Philippians',
					'Colossians',
					'1 Thessalonians',
					'2 Thessalonians',
					'1 Timothy',
					'2 Timothy',
					'Titus',
					'Philemon',
					'Hebrews',
					'James',
					'1 Peter',
					'2 Peter',
					'1 John',
					'2 John',
					'3 John',
					'Jude',
					'Revelation',
					'Topical',
				);

				// Assign every book a number.
				foreach ( $terms as $term ) {
					$ordered_terms[ array_search( $term->name, $books ) ] = $term;
				}

				// Order the numbers (books).
				ksort( $ordered_terms );

				$terms = $ordered_terms;
			}

			$list = '<ul id="list-sermons">';
			foreach ( $terms as $term ) {
				$list .= '<li><a href="' . esc_url( get_term_link( $term, $term->taxonomy ) ) . '" title="' . $term->name . '">' . $term->name . '</a></li>';
			}
			$list .= '</ul>';

			return $list;
		} else {
			// If nothing has been found.
			return 'No ' . $this->convert_taxonomy_name( $args['display'], true ) . ' found.';
		}
	}

	/**
	 * Removes all sorts of quotes from a string.
	 *
	 * @see   http://unicode.org/cldr/utility/confusables.jsp?a=%22&r=None
	 *
	 * @param string $string String to unquote.
	 *
	 * @return mixed Unquoted string if string supplied, original variable otherwise.
	 *
	 * @since 2.9
	 */
	private function _unquote( $string ) {
		if ( ! is_string( $string ) ) {
			return $string;
		}

		return str_replace( array(
			"\x22",
			"\x27\x27",
			"\xCA\xBA",
			"\xCB\x9D",
			"\xCB\xAE",
			"\xCB\xB6",
			"\xD7\xB2",
			"\xD7\xB4",
			"\xE1\xB3\x93",
			"\xE2\x80\x9C",
			"\xE2\x80\x9D",
			"\xE2\x80\x9F",
			"\xE2\x80\xB3",
			"\xE2\x80\xB6",
			"\xE3\x80\x83",
			"\xEF\xBC\x82",
		), '', $string );
	}

	/**
	 * Used to convert user friendly names to taxonomy names, i.e. "series" => "wpfc_sermon_series".
	 * Or taxonomy names to user friendly ones.
	 *
	 * @param string $name     User friendly name or taxonomy name.
	 * @param bool   $new_name Should it return user friendly name.
	 *
	 * @return string|null null if nothing found, name otherwise
	 */
	public function convert_taxonomy_name( $name, $new_name ) {
		$old_taxonomies = array(
			'wpfc_sermon_series',
			'wpfc_preacher',
			'wpfc_sermon_topics',
			'wpfc_bible_book',
			'wpfc_service_type',
		);
		$new_taxonomies = array( 'series', 'preachers', 'topics', 'books', 'service_types' );

		if ( $new_name ) {
			if ( in_array( $name, $old_taxonomies ) ) {
				return $new_taxonomies[ array_search( $name, $old_taxonomies ) ];
			}

			// Return itself if it's already converted. try plural if (assumed) singular doesn't exist.
			foreach ( array( $name, $name . 's' ) as $name_s ) {
				if ( in_array( $name_s, $new_taxonomies ) ) {
					return $name_s;
				}
			}
		} else {
			// Try plural if (assumed) singular doesn't exist.
			foreach ( array( $name, $name . 's' ) as $name_s ) {
				if ( in_array( $name_s, $new_taxonomies ) ) {
					return $old_taxonomies[ array_search( $name_s, $new_taxonomies ) ];
				}
			}

			// Return itself if it's already converted.
			if ( in_array( $name, $old_taxonomies ) ) {
				return $name;
			}
		}

		return null;
	}

	/**
	 * Display all series or speakers in a grid of images
	 *
	 * @param array $atts Shortcode parameters.
	 *
	 * @type string $atts ['display'] The taxonomy, possible options: series, preachers.
	 * @type string $atts ['order'] Sorting order, possible options: ASC, DESC.
	 * @type string $atts ['ordrerby'] Possible options: id, count, name, slug, term_group, none.
	 * @type string $atts ['size'] Possible options: sermon_small, sermon_medium, sermon_wide, thumbnail, medium,
	 *       large, full, or any size added with add_image_size().
	 * @type bool   $atts ['hide_title'] Should we hide title, default false.
	 * @type bool   $atts ['show_description'] Should we show the description, default false.
	 *
	 * @return string Grid or error message.
	 */
	public function display_images( $atts = array() ) {
		// Enqueue scripts and styles.
		if ( ! defined( 'SM_ENQUEUE_SCRIPTS_STYLES' ) ) {
			define( 'SM_ENQUEUE_SCRIPTS_STYLES', true );
		}

		// Unquote.
		if ( is_array( $atts ) || is_object( $atts ) ) {
			foreach ( $atts as &$att ) {
				$att = $this->_unquote( $att );
			}
		}

		// Default args.
		$args = array(
			'display'          => 'series',
			'order'            => 'DESC',
			'orderby'          => 'name',
			'size'             => 'sermon_medium',
			'hide_title'       => false,
			'show_description' => false,
		);

		// For compatibility.
		if ( ! empty( $atts['tax'] ) ) {
			$atts['display'] = $atts['tax'];
			unset( $atts['tax'] );
		}

		// For compatibility.
		if ( ! empty( $atts['show_desc'] ) ) {
			$atts['show_description'] = $atts['show_desc'];
			unset( $atts['show_desc'] );
		}

		// Join default and user options.
		$args = shortcode_atts( $args, $atts, 'sermon_images' );

		// Convert to bool.
		$args['show_description'] = (bool) $args['show_description'];

		// Check if we are using a SM taxonomy, and if we are, convert to valid taxonomy name.
		if ( $this->convert_taxonomy_name( $args['display'], true ) ) {
			$args['display'] = $this->convert_taxonomy_name( $args['display'], false );
		} elseif ( ! $this->convert_taxonomy_name( $args['display'], false ) ) {
			return '<strong>Error: Invalid "list" parameter.</strong><br> Possible values are: "series", "preachers", "topics" and "books".<br> You entered: "<em>' . $args['display'] . '</em>"';
		}

		// Get images.
		$terms = apply_filters( 'sermon-images-get-terms', '', array(
			'taxonomy'  => $args['display'],
			'term_args' => array(
				'order'   => $args['order'],
				'orderby' => $args['orderby'],
			),
		) );

		// $terms will always return an array
		if ( ! empty( $terms ) ) {
			$list = '<ul id="wpfc_images_grid">';

			foreach ( (array) $terms as $term ) {
				$term_url = esc_url( get_term_link( $term, $term->taxonomy ) );

				$list .= '<li class="wpfc_grid_image">';
				$list .= '<a href="' . $term_url . '">' . wp_get_attachment_image( $term->image_id, $args['size'] ) . '</a>';
				if ( false == $args['hide_title'] || 'no' == $args['hide_title'] ) {
					$list .= '<h3 class="wpfc_grid_title"><a href="' . $term_url . '">' . $term->name . '</a></h3>';
				}
				if ( true == $args['show_description'] ) {
					if ( ! empty( $term->description ) ) {
						$list .= '<div class="taxonomy-description">' . $term->description . '</div>';
					}
				}
				$list .= '</li>';
			}

			$list .= '</ul>';

			return $list;
		} else {
			// If nothing has been found.
			return 'No ' . $this->convert_taxonomy_name( $args['display'], true ) . ' images found.';
		}
	}

	/**
	 * Display the latest sermon series image (optional - by service type).
	 *
	 * @param array $atts Shortcode options.
	 *
	 * @type string $atts ['image_class'] CSS class for image.
	 * @type string $atts ['size'] Image size. Possible options: sermon_small, sermon_medium, sermon_wide, thumbnail,
	 *       medium, large, full, or any size added with add_image_size().
	 * @type bool   $atts ['show_title'] false to hide the series title (true is the default).
	 * @type string $atts ['title_wrapper'] Possible options: p, h1, h2, h3, h4, h5, h6, div.
	 * @type string $atts ['title_class'] CSS class for title.
	 * @type string $atts ['service_type'] Service type ID/slug/name. Used to get latest series from that service type.
	 * @type bool   $atts ['show_description'] false to hide the series description (true is the default).
	 * @type string $atts ['wrapper_class'] CSS class for wrapper.
	 *
	 * @return string
	 */
	function display_latest_series_image( $atts = array() ) {
		// Enqueue scripts and styles.
		if ( ! defined( 'SM_ENQUEUE_SCRIPTS_STYLES' ) ) {
			define( 'SM_ENQUEUE_SCRIPTS_STYLES', true );
		}

		// Unquote.
		if ( is_array( $atts ) || is_object( $atts ) ) {
			foreach ( $atts as &$att ) {
				$att = $this->_unquote( $att );
			}
		}

		// Default options.
		$args = array(
			'image_class'      => 'latest-series-image',
			'size'             => 'large',
			'show_title'       => 'yes',
			'title_wrapper'    => 'h3',
			'title_class'      => 'latest-series-title',
			'service_type'     => '',
			'show_description' => 'yes',
			'wrapper_class'    => 'latest-series',
		);

		// For compatibility.
		if ( ! empty( $atts['show_desc'] ) ) {
			$atts['show_description'] = $atts['show_desc'];
			unset( $atts['show_desc'] );
		}

		// Join default and user options.
		$args = shortcode_atts( $args, $atts, 'latest_series' );

		// Get latest series.
		$latest_series = $this->get_latest_series( 0, $args['service_type'] );

		// If for some reason we couldn't get latest series.
		if ( null === $latest_series ) {
			return 'No latest series found.';
		}

		// Image ID.
		$series_image_id = $this->get_latest_series_image_id( $latest_series );

		// If for some reason we couldn't get latest series image.
		if ( null === $series_image_id ) {
			return 'No latest series image found.';
		}

		// Link to series.
		$series_link = get_term_link( $latest_series, 'wpfc_sermon_series' );
		// Image CSS class.
		$image_class = sanitize_html_class( $args['image_class'] );
		// Title wrapper tag name.
		$wrapper_options = array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' );
		if ( ! in_array( sanitize_text_field( $args['title_wrapper'] ), $wrapper_options ) ) {
			$args['title_wrapper'] = 'h3';
		}
		// Title CSS class.
		$title_class = sanitize_html_class( $args['title_class'] );

		$link_open  = '<a href="' . $series_link . '" title="' . $latest_series->name . '" alt="' . $latest_series->name . '">';
		$link_close = '</a>';

		$image = wp_get_attachment_image( $series_image_id, $args['size'], false, array( 'class' => $image_class ) );

		$title       = '';
		$description = '';
		if ( 'yes' === $args['show_title'] ) {
			$title = $latest_series->name;
			$title = '<' . $args['title_wrapper'] . ' class="' . $title_class . '">' . $title . '</' . $args['title_wrapper'] . '>';
		}
		if ( 'yes' === $args['show_description'] ) {
			$description = '<div class="latest-series-description">' . wpautop( $latest_series->description ) . '</div>';
		}

		$wrapper_class = sanitize_html_class( $args['wrapper_class'] );
		$before        = '<div class="' . $wrapper_class . '">';
		$after         = '</div>';

		$output = $before . $link_open . $image . $title . $link_close . $description . $after;

		return $output;
	}

	/**
	 * Get all sermon series as WP_Term object.
	 *
	 * @param int $latest_sermon Optional. Latest sermon ID. If not provided, it will try to get it automatically.
	 * @param int $service_type  Optional. Service Type for getting latest sermon ID.
	 *
	 * @return WP_Term|null
	 */
	public function get_latest_series( $latest_sermon = 0, $service_type = 0 ) {
		if ( empty( $latest_sermon ) ) {
			$latest_sermon = $this->get_latest_sermon_id( $service_type );
		}

		$latest_series = get_the_terms( $latest_sermon, 'wpfc_sermon_series' );

		if ( is_array( $latest_series ) && ! empty( $latest_series ) ) {
			return $latest_series[0];
		}

		return null;
	}

	/**
	 * Get the latest sermon ID.
	 *
	 * @param string|int $service_type Optional argument to get latest sermon from specified service type. Slug, name
	 *                                 and ID are accepted values.
	 *
	 * @return int|null Sermon ID on success, null on failure.
	 */
	public function get_latest_sermon_id( $service_type = 0 ) {
		$args = array(
			'post_type'              => 'wpfc_sermon',
			'posts_per_page'         => 1,
			'post_status'            => 'publish',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		);

		// If service type is set.
		if ( 0 !== $service_type ) {
			/*
			 * if it's not numeric, we will try to find it by slug;
			 * if not found by slug, we will try to find it by name.
			 */
			if ( ! is_numeric( $service_type ) ) {
				foreach ( array( 'slug', 'name' ) as $field ) {
					$service_type = get_term_by( $field, $service_type, 'wpfc_service_type' );

					if ( false !== $service_type ) {
						$service_type = intval( $service_type->term_id );
						break;
					}
				}
			} else {
				// Convert to int, if string number is used.
				$service_type = intval( $service_type );
			}

			if ( is_int( $service_type ) && term_exists( $service_type, 'wpfc_service_type' ) ) {
				$args['tax_query'] = array(
					'taxonomy' => 'wpfc_service_type',
					'terms'    => $service_type,
				);
			}
		}

		$latest_sermon = new WP_Query( $args );

		// If there is a post, return ID.
		if ( ! empty( $latest_sermon->post_count ) ) {
			wp_reset_postdata();

			return $latest_sermon->post->ID;
		}

		return null;
	}

	/**
	 * Get the image ID of the specified series ID.
	 * Will try to get image ID of latest series if $latest_series argument not set.
	 *
	 * @param int $series Series to get the image of.
	 *
	 * @return int|null
	 */
	function get_latest_series_image_id( $series = 0 ) {
		if ( 0 === $series ) {
			$series = $this->get_latest_series();

			if ( null === $series ) {
				return null;
			}
		}

		$associations = sermon_image_plugin_get_associations();
		$tt_id        = absint( $series->term_taxonomy_id );

		if ( array_key_exists( $tt_id, $associations ) ) {
			$id = absint( $associations[ $tt_id ] );

			return $id;
		}

		return null;
	}

	/**
	 * Renders sorting HTML.
	 *
	 * @param array $atts          Shortcode parameters.
	 *
	 * @type string $series_filter Do filtering in this specific series (slug).
	 * @type string $series        Force specific series to show. Slug only.
	 * @type string $preachers     Force specific preacher to show. Slug only.
	 * @type string $topics        Force specific topic to show. Slug only.
	 * @type string $books         Force specific book to show. Slug only.
	 * @type string $visibility    'none' to hide the forced fields, 'disable' to show them as disabled and 'suggest' to
	 *       just set the default value while allowing user to change it. Default 'suggest'.
	 *
	 * @return string Sorting HTML.
	 *
	 * @since 2.5.0 added shortcode parameters.
	 */
	public function display_sermon_sorting( $atts = array() ) {
		// Enqueue scripts and styles.
		if ( ! defined( 'SM_ENQUEUE_SCRIPTS_STYLES' ) ) {
			define( 'SM_ENQUEUE_SCRIPTS_STYLES', true );
		}

		// Unquote.
		if ( is_array( $atts ) || is_object( $atts ) ) {
			foreach ( $atts as &$att ) {
				$att = $this->_unquote( $att );
			}
		}

		// Default shortcode options.
		$args = array(
			'series_filter'       => '',
			'service_type_filter' => '',
			'series'              => '',
			'preachers'           => '',
			'topics'              => '',
			'books'               => '',
			'visibility'          => 'suggest',
			'hide_topics'         => '',
			'hide_series'         => '',
			'hide_preachers'      => '',
			'hide_books'          => '',
			'hide_service_types'  => 'yes',
		);

		// Merge default and user options.
		$args = shortcode_atts( $args, $atts, 'sermon_sort_fields' );

		return render_wpfc_sorting( $args );
	}

	/**
	 * Main sermon display code
	 *
	 * @param array $atts Shortcode parameters.
	 *
	 * @type int    $atts ['per_page'] How many sermons per page.
	 * @type string $atts ['sermons'] Include only these sermons. Separate with comma (,) with no spaces. IDs only.
	 * @type string $atts ['order'] Sorting order, possible options: ASC, DESC.
	 * @type string $atts ['orderby'] Sort by: date (default), none, ID, title, name, rand, comment_count.
	 * @type bool   $atts ['disable_pagination'] 1 to hide the pagination (default 0).
	 * @type bool   $atts ['image_size'] Image size. Possible values: sermon_small, sermon_medium, sermon_wide,
	 *       thumbnail, medium, large, full, or any size added with add_image_size(). (default is "post-thumbnail").
	 * @type string $atts ['filter_by'] Filter by series, preacher, topic, book, service_type.
	 * @type string $atts ['filter_value'] ID/slug of allowed filters.
	 * @type int    $atts ['year'] 4 digit year (e.g. 2011).
	 * @type int    $atts ['month'] Month number (from 1 to 12).
	 * @type int    $atts ['week'] Week of the year (from 0 to 53).
	 * @type int    $atts ['day'] Day of the month (from 1 to 31).
	 * @type string $atts ['after'] Date to retrieve posts after. Accepts strtotime()-compatible string.
	 * @type string $atts ['before'] Date to retrieve posts before. Accepts strtotime()-compatible string.
	 *
	 * @return string
	 */
	function display_sermons( $atts = array() ) {
		global $post_ID;

		// Enqueue scripts and styles.
		if ( ! defined( 'SM_ENQUEUE_SCRIPTS_STYLES' ) ) {
			define( 'SM_ENQUEUE_SCRIPTS_STYLES', true );
		}

		// Unquote.
		if ( is_array( $atts ) || is_object( $atts ) ) {
			foreach ( $atts as &$att ) {
				$att = $this->_unquote( $att );
			}
		}

		// Default options.
		$args = array(
			'per_page'           => get_option( 'posts_per_page' ) ?: 10,
			'sermons'            => false, // Show only sermon IDs that are set here.
			'order'              => 'DESC',
			'orderby'            => 'date',
			'disable_pagination' => 0,
			'image_size'         => 'post-thumbnail',
			'filter_by'          => '',
			'filter_value'       => '',
			'year'               => '',
			'month'              => '',
			'week'               => '',
			'day'                => '',
			'after'              => '',
			'before'             => '',
		);

		// Legacy convert.
		$old_options = array(
			'posts_per_page'  => 'per_page',
			'id'              => 'sermons',
			'hide_nav'        => 'hide_pagination',
			'taxonomy'        => 'filter_by',
			'tax_term'        => 'filter_value',
			'hide_pagination' => 'disable_pagination',
		);

		foreach ( $old_options as $old_option => $new_option ) {
			if ( ! empty( $atts[ $old_option ] ) ) {
				$args[ $new_option ] = $atts[ $old_option ];
				unset( $atts[ $old_option ] );
			}
		}

		// Merge default and user options.
		$args = shortcode_atts( $args, $atts, 'sermons' );

		// Set query args.
		$query_args = array(
			'post_type'      => 'wpfc_sermon',
			'posts_per_page' => $args['per_page'],
			'order'          => $args['order'],
			'paged'          => get_query_var( 'paged' ),
			'year'           => $args['year'],
			'month'          => $args['month'],
			'week'           => $args['week'],
			'day'            => $args['day'],
			'after'          => $args['after'],
			'before'         => $args['before'],
			'meta_query'     => array(
				'relation' => 'OR',
				array( // Check to see if date has been filled out.
					'key'     => 'sermon_date',
					'compare' => '<=',
					'value'   => time(),
				),
				array( // If no date has been added show these posts too.
					'key'     => 'sermon_date',
					'value'   => time(),
					'compare' => 'NOT EXISTS',
				),
			),
		);

		// Check if it's a valid ordering argument.
		if ( ! in_array( strtolower( $args['orderby'] ), array(
			'date',
			'id',
			'none',
			'title',
			'name',
			'rand',
			'comment_count',
		) ) ) {
			$args['orderby'] = 'date';
		}

		if ( 'date' === $args['orderby'] ) {
			$args['orderby'] = 'meta_value_num';
		}

		$query_args['orderby'] = $args['orderby'];

		// If we should show just specific sermons.
		if ( $args['sermons'] ) {
			$posts_in = explode( ',', $args['sermons'] );

			if ( ! empty( $posts_in ) ) {
				foreach ( $posts_in as &$post_in ) {
					// Remove if it's not an ID.
					if ( ! is_numeric( trim( $post_in ) ) ) {
						unset( $post_in );
						continue;
					}

					// Convert to int.
					$post_in = intval( trim( $post_in ) );
				}

				$query_args['post__in'] = $posts_in;
			}
		}

		// If we should filter by something.
		if ( $args['filter_by'] && $args['filter_value'] ) {
			// Term string to array.
			$terms = explode( ',', $args['filter_value'] );

			if ( ! empty( $terms ) ) {
				$field = 'slug';

				if ( is_numeric( $terms[0] ) ) {
					$field = 'id';
				}

				foreach ( $terms as &$term ) {
					$term = trim( $term );

					if ( 'id' === $field ) {
						// Remove if it's not an ID.
						if ( ! is_numeric( $term ) ) {
							unset( $term );
							continue;
						}

						// Convert to int.
						$term = intval( $term );
					}
				}

				$query_args['tax_query'] = array(
					array(
						'taxonomy' => $this->convert_taxonomy_name( $args['filter_by'], false ),
						'field'    => 'slug',
						'terms'    => $terms,
					),
				);
			}
		}

		foreach ( array( 'wpfc_preacher', 'wpfc_sermon_series', 'wpfc_sermon_topics', 'wpfc_bible_book' ) as $filter ) {
			if ( ! empty( $_GET[ $filter ] ) ) {
				if ( empty( $query_args['tax_query']['custom'] ) || empty( $query_args['tax_query'] ) ) {
					$query_args['tax_query'] = array();
				}

				$query_args['tax_query'][0][] = array(
					'taxonomy' => $filter,
					'field'    => 'slug',
					'terms'    => sanitize_title_for_query( $_GET[ $filter ] ),
				);

				$query_args['tax_query']['custom'] = true;
			}
		}

		if ( ! empty( $query_args['tax_query'] ) && count( $query_args['tax_query'] ) > 1 && ! empty( $query_args['tax_query']['custom'] ) ) {
			unset( $query_args['tax_query']['custom'] );
		}

		$query = new WP_Query( $query_args );

		// Set image size.
		add_filter( 'wpfc_sermon_excerpt_sermon_image_size', function () use ( $args ) {
			return $args['image_size'];
		} );

		define( 'WPFC_SM_SHORTCODE', true );

		if ( $query->have_posts() ) {
			ob_start(); ?>
			<div id="wpfc-sermons-shortcode">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					global $post;
					?>
					<?php echo apply_filters( 'sm_shortcode_sermons_single_output', '<div class="wpfc-sermon wpfc-sermon-shortcode">' . wpfc_sermon_excerpt_v2( true, $args ) . '</div>', $post ); ?>
				<?php endwhile; ?>

				<?php wp_reset_postdata(); ?>

				<?php if ( ! $args['disable_pagination'] ) : ?>
					<?php if ( function_exists( 'wp_pagenavi' ) ) : ?>
						<?php wp_pagenavi( array( 'query' => $query ) ); ?>
					<?php else : ?>
						<div id="wpfc-sermons-shortcode-navigation">
							<?php
							echo paginate_links( array(
								'base'     => rtrim( get_permalink( $post_ID ), '/' ) . '/%_%',
								'current'  => $query->get( 'paged' ),
								'total'    => $query->max_num_pages,
								'end_size' => 3,
							) );
							?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php
			$return = ob_get_clean();

			/**
			 * Allows to filter the complete output of the shortcode.
			 */
			return apply_filters( 'sm_shortcode_sermons_output', $return, $query );
		} else {
			return 'No sermons found.';
		}
	}
}

$sm_shortcodes = new SM_Shortcodes;
$sm_shortcodes->init();
