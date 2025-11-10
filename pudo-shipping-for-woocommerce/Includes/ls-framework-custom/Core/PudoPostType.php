<?php

/**
 * @author The Courier Guy / Pudo
 */
class PudoPostType {



	private $identifier;
	private $options    = array();
	private $properties = array();
	private $taxonomies = array();

	/**
	 * PudoPostType constructor.
	 *
	 * @param string $identifier
	 * @param array  $options
	 */
	public function __construct( $identifier, $options = array() ) {
		$this->setIdentifier( $identifier );
		$this->setOptions( $options );
		$this->init( array( &$this, 'registerPostType' ) );
		add_filter( 'get_post_metadata', array( $this, 'filterPostMetaValue' ), 100, 4 );
	}

	/**
	 * @param $metaData
	 * @param $postId
	 * @param $metaKey
	 * @param $single
	 *
	 * @return mixed|string
	 */
	public function filterPostMetaValue( $metaData, $postId, $metaKey, $single ) {
		$result     = $metaData;
		$properties = $this->getProperties();
		if ( ( $this->getIdentifier() == get_post_type( $postId ) ) && $single && array_key_exists( $metaKey, $properties ) ) {
			remove_filter( 'get_post_metadata', array( $this, 'filterPostMetaValue' ), 100 );
			$result = get_post_meta( $postId, $metaKey, true );
			add_filter( 'get_post_metadata', array( $this, 'filterPostMetaValue' ), 100, 4 );
			$result = do_shortcode( $result );
		}

		return $result;
	}

	/**
	 * @param mixed $callbackFunction
	 */
	public function init( $callbackFunction ) {
		add_action( 'init', $callbackFunction, 999 );
	}

	/**
	 * @param mixed $callbackFunction
	 */
	public function adminInit( $callbackFunction ) {
		add_action( 'admin_init', $callbackFunction );
	}

	/**
	 *
	 */
	public function registerPostType() {
		$options         = $this->getOptions();
		$options['name'] = $this->getIdentifier();
		$options         = $this->addLabelOptions( $options );
		$postType        = get_post_type_object( $this->getIdentifier() );
		if ( empty( $postType ) ) {
			$postType            = get_post_type_object( 'post' );
			$options['_builtin'] = false;
		}
		$defaultPostTypeOptions = $this->getDefaultOptions( $postType );
		$options                = array_merge( $defaultPostTypeOptions, $options );
		$this->setOptions( $options );
		$identifier = $this->getIdentifier();
		register_post_type( $identifier, $options );
		$this->removeTaxonomies( $options );
		$this->removeSupports( $options );
		$this->registerTaxonomies();
		$this->addPostMetaUi();
		$this->savePost();
		$this->updateGlobalPostTypes();
	}

	/**
	 * @param string $title
	 * @param array  $options
	 *
	 * @see PudoPostType::addMetaBoxUi()
	 * @todo This first variable should be the identifier for the meta box, this is currently created from the title.
	 * @todo The $title variable should be part of the options array.
	 */
	public function addMetaBox( $title, $options = array() ) {
		$options['title'] = $title;
		if ( empty( $options['context'] ) ) {
			$options['context'] = 'normal';
		}
		$this->addProperties( $options );
	}

	/**
	 *
	 */
	public function addPostMetaUi() {
		$identifier = $this->getIdentifier();
		$properties = $this->getProperties();
		array_walk(
			$properties,
			function ( $options ) use ( $identifier ) {
				$this->addMetaBoxUi( $options );
			}
		);
	}

	/**
	 * @param $post
	 * @param $data
	 */
	public function renderMetaBox( $post, $data ) {
		wp_nonce_field( plugin_basename( __FILE__ ), 'pudo_x_nonce' );
		$inputs           = $data['args'][0];
		$metaData         = get_post_custom( $post->ID );
		$templateFilePath = __DIR__ . '/../Templates/';
		array_walk(
			$inputs,
			function ( $properties, $identifier ) use ( $post, $metaData, $templateFilePath ) {
				$type                  = $properties['property_type'];
				$formFieldTemplateFile = $templateFilePath . 'form-field-' . $type . '.php';
				if ( ! file_exists( $formFieldTemplateFile ) ) {
					$formFieldTemplateFile = $templateFilePath . 'form-field-text.php';
				}
				$value    = isset( $metaData[ $identifier ][0] ) ? $metaData[ $identifier ][0] : '';
				$readonly = '';
				if ( isset( $properties['readonly'] ) && $properties['readonly'] == true ) {
					$readonly = ' readonly="readonly"';
				}
				$placeholder = $properties['placeholder'];
				$description = $properties['description'];
				ob_start();
				include $templateFilePath . 'form-field-wrapper.php';
				$formField = ob_get_contents();
				ob_end_clean();
				echo $formField;
			}
		);
	}

	/**
	 * @param $identifier
	 * @param array      $properties
	 */
	public function addFeaturedImage( $identifier, $properties = array() ) {
		$postTypeIdentifier = $this->getIdentifier();
		if ( $identifier == '_thumbnail_id' ) {
			add_action(
				'do_meta_boxes',
				function () use ( $properties, $postTypeIdentifier ) {
					remove_meta_box( 'postimagediv', 'rotator', 'side' );
					add_meta_box(
						'postimagediv',
						__( $properties['display_name'] ),
						'post_thumbnail_meta_box',
						$postTypeIdentifier,
						'side',
						'default'
					);
				}
			);
		} else {
			if ( class_exists( 'MultiPostThumbnails' ) ) {
				new MultiPostThumbnails(
					array(
						'label'     => $properties['display_name'],
						'id'        => $identifier,
						'post_type' => $this->getIdentifier(),
					)
				);
			}
			add_filter(
				$postTypeIdentifier . '_' . $identifier . '_thumbnail_html',
				array( $this, 'removeImageSizeAttributes' )
			);
		}
	}

	/**
	 * @param string $html
	 *
	 * @return string $html
	 */
	public function removeImageSizeAttributes( $html ) {
		return preg_replace( '/(width|height)="\d*"/', '', $html );
	}

	/**
	 * @param array $taxonomies
	 */
	public function setTaxonomies( $taxonomies ) {
		$this->taxonomies = $taxonomies;
	}

	/**
	 * @param string $identifier
	 * @param array  $options
	 */
	public function addTaxonomy( $identifier, $options = array() ) {
		$taxonomies                = $this->getTaxonomies();
		$taxonomies[ $identifier ] = $options;
		$this->setTaxonomies( $taxonomies );
		$this->updateGlobalPostTypes();
	}

	/**
	 * @param $postType
	 *
	 * @return array
	 */
	private function getDefaultOptions( $postType ) {
		return array(
			'name'                  => $postType->name,
			'label'                 => $postType->label,
			'labels'                => json_decode( json_encode( $postType->labels ), true ),
			'description'           => $postType->description,
			'public'                => $postType->public,
			'hierarchical'          => $postType->hierarchical,
			'exclude_from_search'   => $postType->exclude_from_search,
			'publicly_queryable'    => $postType->publicly_queryable,
			'show_ui'               => $postType->show_ui,
			'show_in_menu'          => $postType->show_in_menu,
			'show_in_nav_menus'     => $postType->show_in_nav_menus,
			'show_in_admin_bar'     => $postType->show_in_admin_bar,
			'menu_position'         => $postType->menu_position,
			'menu_icon'             => $postType->menu_icon,
			'capability_type'       => $postType->capability_type,
			'map_meta_cap'          => $postType->map_meta_cap,
			'register_meta_box_cb'  => $postType->register_meta_box_cb,
			'has_archive'           => $postType->has_archive,
			'query_var'             => $postType->query_var,
			'can_export'            => $postType->can_export,
			'delete_with_user'      => $postType->delete_with_user,
			'_builtin'              => $postType->_builtin,
			'_edit_link'            => $postType->_edit_link,
			'rewrite'               => $postType->rewrite,
			'show_in_rest'          => $postType->show_in_rest,
			/*"rest_base" => $postType->rest_base,*/
			'rest_controller_class' => $postType->rest_controller_class,
		);
	}

	/**
	 * @param array $options
	 *
	 * @return array
	 */
	private function addLabelOptions( $options ) {
		if ( isset( $options['display_name_singular'] ) ) {
			$displayName       = $options['display_name_singular'];
			$displayNamePlural = $options['display_name_plural'];
			if ( ! empty( $displayNamePlural ) ) {
				$displayNamePlural = $displayName . 's';
			}
			$options['label']  = $displayNamePlural;
			$options['labels'] = array(
				'name'               => $displayNamePlural,
				'singular_name'      => $displayName,
				'add_new'            => 'Add New',
				'add_new_item'       => 'Add New ' . $displayName,
				'edit'               => 'Edit',
				'title'              => $displayName,
				'edit_item'          => 'Edit ' . $displayName,
				'new_item'           => 'New ' . $displayName,
				'view'               => 'View',
				'view_item'          => 'View ' . $displayNamePlural,
				'search_items'       => 'Search ' . $displayNamePlural,
				'not_found'          => 'No ' . $displayName . ' found',
				'not_found_in_trash' => 'No ' . $displayName . ' found in Trash',
				'parent'             => 'Parent ' . $displayName,
			);
		}

		return $options;
	}

	/**
	 * @param array $options
	 */
	private function removeTaxonomies( $options ) {
		if ( isset( $options['taxonomies'] ) ) {
			$identifier = $this->getIdentifier();
			$taxonomies = get_object_taxonomies( $identifier );
			array_walk(
				$taxonomies,
				function ( $value, $taxonomy ) use ( $options, $identifier ) {
					if ( ! in_array( $taxonomy, $options['taxonomies'] ) ) {
						unregister_taxonomy_for_object_type( $taxonomy, $this->getIdentifier() );
					}
				}
			);
		}
	}

	/**
	 * @param array $options
	 */
	private function removeSupports( $options ) {
		if ( isset( $options['supports'] ) ) {
			$identifier = $this->getIdentifier();
			$supports   = get_all_post_type_supports( $identifier );
			array_walk(
				$supports,
				function ( $value, $support ) use ( $options, $identifier ) {
					$supports = $options['supports'];
					if ( ! in_array( $support, $supports ) ) {
						remove_post_type_support( $identifier, $support );
					}
				}
			);
		}
	}

	/**
	 * @param $taxonomy
	 *
	 * @return array
	 */
	private function getDefaultTaxonomyOptions( $taxonomy ) {
		return array(
			'name'                  => $taxonomy->name,
			'label'                 => $taxonomy->label,
			'labels'                => json_decode( json_encode( $taxonomy->labels ), true ),
			'description'           => $taxonomy->description,
			'public'                => $taxonomy->public,
			'publicly_queryable'    => $taxonomy->publicly_queryable,
			'hierarchical'          => $taxonomy->hierarchical,
			'show_ui'               => $taxonomy->show_ui,
			'show_in_menu'          => $taxonomy->show_in_menu,
			'show_in_nav_menus'     => $taxonomy->show_in_nav_menus,
			'show_tagcloud'         => $taxonomy->show_tagcloud,
			'show_in_quick_edit'    => $taxonomy->show_in_quick_edit,
			'show_admin_column'     => $taxonomy->show_admin_column,
			'meta_box_cb'           => $taxonomy->meta_box_cb,
			'cap'                   => json_decode( json_encode( $taxonomy->cap ), true ),
			'rewrite'               => $taxonomy->rewrite,
			'update_count_callback' => $taxonomy->update_count_callback,
			'show_in_rest'          => $taxonomy->show_in_rest,
			'rest_controller_class' => $taxonomy->rest_controller_class,
			'_builtin'              => $taxonomy->_builtin,
		);
	}

	/**
	 * @param array $options
	 *
	 * @return array
	 */
	private function addTaxonomyLabelOptions( $options ) {
		if ( isset( $options['display_name_singular'] ) ) {
			$displayName       = $options['display_name_singular'];
			$displayNamePlural = $options['display_name_plural'];
			if ( ! empty( $displayNamePlural ) ) {
				$displayNamePlural = $displayName . 's';
			}
			$options['label']  = $displayNamePlural;
			$options['labels'] = array(
				'name'                  => __( $displayNamePlural, '' ),
				'singular_name'         => __( $displayName, '' ),
				'search_items'          => __( 'Search ' . $displayNamePlural, '' ),
				'all_items'             => __( 'All ' . $displayNamePlural, '' ),
				'parent_item'           => __( 'Parent ' . $displayName, '' ),
				'parent_item_colon'     => __( 'Parent ' . $displayName . ':', '' ),
				'edit_item'             => __( 'Edit ' . $displayName, '' ),
				'update_item'           => __( 'Update ' . $displayName, '' ),
				'add_new_item'          => __( 'Add New ' . $displayName, '' ),
				'new_item_name'         => __( 'New ' . $displayName, '' ),
				'choose_from_most_used' => __( 'Choose from the most used ' . $displayNamePlural, '' ),
			);
		}

		return $options;
	}

	/**
	 *
	 */
	private function registerTaxonomies() {
		$taxonomies         = $this->getTaxonomies();
		$postTypeIdentifier = $this->getIdentifier();
		array_walk(
			$taxonomies,
			function ( $options, $identifier ) use ( $postTypeIdentifier ) {
				$options['name'] = $identifier;
				$options         = $this->addTaxonomyLabelOptions( $options );
				$taxonomy        = get_taxonomy( $identifier );
				if ( empty( $taxonomy ) ) {
					$taxonomy = get_taxonomy( 'post_tag' );
				}
				$defaultOptions = $this->getDefaultTaxonomyOptions( $taxonomy );
				$options        = array_merge( $defaultOptions, $options );
				register_taxonomy( $identifier, $postTypeIdentifier, $options );
			}
		);
	}

	/**
	 * @param $options
	 *
	 * @see PudoPostType::addMetaBox()
	 * @todo This $identifier variable should be passed into the method.
	 */
	private function addMetaBoxUi( $options ) {
		$postTypeIdentifier = $this->getIdentifier();
		$this->adminInit(
			function () use ( $postTypeIdentifier, $options ) {
				$title      = $options['title'];
				$identifier = strtolower( str_replace( ' ', '_', $title ) );
				$formFields = $options['form_fields'];
				add_meta_box(
					$identifier,
					$title,
					array( $this, 'renderMetaBox' ),
					$postTypeIdentifier,
					$options['context'],
					'high',
					array( $formFields )
				);
			}
		);
	}

	/**
	 *
	 */
	private function savePost() {
		add_action(
			'save_post',
			function () {
				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
					return;
				}
				if ( ! empty( $_POST ) && ! wp_verify_nonce(
					isset( $_POST['pudo_x_nonce'] ) ? filter_var( $_POST['pudo_x_nonce'], FILTER_SANITIZE_STRING ) : '',
					plugin_basename( __FILE__ )
				) ) {
					return;
				}
				global $post;
				$identifier = $this->getIdentifier();
				if ( ( ! empty( $post ) ) && $post->post_type == $identifier ) {
					$properties = $this->getProperties();
					array_walk(
						$properties,
						function ( $options ) use ( $post ) {
							$formFields = $options['form_fields'];
							array_walk(
								$formFields,
								function ( $formField, $identifier ) use ( $post ) {
									if ( isset( $_POST[ $identifier ] ) && ( ! isset( $formField['readonly'] ) || $formField['readonly'] == false ) ) {
										$value = sanitize_text_field( $_POST[ $identifier ] );
										update_post_meta( $post->ID, $identifier, $value );
									} elseif ( empty( $_POST[ $identifier ] ) ) {
										$value = '';
										update_post_meta( $post->ID, $identifier, $value );
									}
								}
							);
						}
					);
				}
			}
		);
	}

	/**
	 * @return string
	 */
	private function getIdentifier() {
		return $this->identifier;
	}

	/**
	 * @param string $identifier
	 */
	private function setIdentifier( $identifier ) {
		$this->identifier = $identifier;
	}

	/**
	 * @return array
	 */
	private function getOptions() {
		return $this->options;
	}

	/**
	 * @param array $options
	 */
	private function setOptions( $options ) {
		$this->options = $options;
	}

	/**
	 * @return array
	 */
	private function getProperties() {
		return $this->properties;
	}

	/**
	 * @param array $properties
	 */
	private function setProperties( $properties ) {
		$this->properties = $properties;
	}

	/**
	 * @param array $options
	 */
	private function addProperties( $options = array() ) {
		$properties   = $this->getProperties();
		$properties[] = $options;
		$this->setProperties( $properties );
		$this->updateGlobalPostTypes();
	}

	/**
	 * @return array
	 */
	private function getTaxonomies() {
		return $this->taxonomies;
	}

	/**
	 *
	 */
	private function updateGlobalPostTypes() {
		if ( empty( $GLOBALS['custom_post_types'] ) ) {
			$GLOBALS['custom_post_types'] = array();
		}
		$GLOBALS['custom_post_types'][ $this->getIdentifier() ] = array(
			'options'    => $this->getOptions(),
			'properties' => $this->getProperties(),
			'taxonomies' => $this->getProperties(),
		);
	}
}
