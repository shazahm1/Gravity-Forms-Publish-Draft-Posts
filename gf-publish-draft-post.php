<?
/**
 * Plugin Name:       Gravity Forms: Publish Draft Post
 * Plugin URI:
 * Description:       Allow Gravity Forms to publish a draft post. Requires the Populate Anything addon.
 * Version:           1.0
 * Author:            Steven A. Zahm
 * Author URI:        https://connections-pro.com
 * Contributor:       shazahm1@hotmail.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       gf_publish_draft_post
 * Domain Path:       /languages
 *
 * @link              https://connections-pro.com
 * @since             1.0
 * @package           gf_publish_draft_post
 *
 * @wordpress-plugin
 */

add_action(
	'gform_loaded',
	function() {

		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		GFForms::include_addon_framework();
		GFAddOn::register( 'GF_Post_Draft_To_Publish' );
		new Gravity_Forms_Publish_Post_Draft();
	},
	5
);

class GF_Post_Draft_To_Publish extends GFAddOn {

	protected $_slug        = 'gravityformspostdraftopublish';

	protected $_title       = 'Gravity Forms Publish Post Draft';

	protected $_short_title = 'Publish Draft Post';

	/**
	 * @var object|null $_instance If available, contains an instance of this class.
	 */
	private static $_instance = NULL;

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @return object $_instance An instance of this class.
	 */
	public static function get_instance() {

		if ( self::$_instance == NULL ) {

			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Configures the settings which should be rendered on the Form Settings > Simple Add-On tab.
	 *
	 * @param array $form The form object.
	 *
	 * @return array
	 */
	public function form_settings_fields( $form ) {

		return array(
			array(
				'title'  => $this->_short_title,
				'fields' => array(
					array(
						'label'   => 'Enable Publish Post Draft',
						'type'    => 'checkbox',
						'name'    => 'enabled',
						'choices' => array(
							array(
								'label' => 'Enabled',
								'name'  => 'ppd-enabled',
							),
						),
					),
					array(
						'label' => 'Field ID for Post ID',
						'type'  => 'text',
						'name'  => 'ppd-field_id',
						'class' => 'small',
					),
					array(
						'label' => 'Field ID for Post Title',
						'type'  => 'text',
						'name'  => 'ppd-post_title_id',
						'class' => 'small',
					),
					array(
						'label' => 'Field ID for Post Content',
						'type'  => 'text',
						'name'  => 'ppd-post_content_id',
						'class' => 'small',
					),
					array(
						'label' => 'Field ID for Post Date',
						'type'  => 'text',
						'name'  => 'ppd-post_date_id',
						'class' => 'small',
					),
					array(
						'label' => 'Field ID for Post Time',
						'type'  => 'text',
						'name'  => 'ppd-post_time_id',
						'class' => 'small',
					),
					array(
						'label'   => 'Post Datetime Modifier',
						'type'    => 'text',
						'name'    => 'ppd-post_datetime_modifier',
						'tooltip' => 'Datetime modifier to apply to the selected post publish date time fields.',
						'class'   => 'small',
					),
				),
			),
		);

	}
}

class Gravity_Forms_Publish_Post_Draft {

	private $option = array(
		'post_id_field'          => 0,
		'post_title_id'          => 0,
		'post_content_id'        => 0,
		'post_date_id'           => 0,
		'post_time_id'           => 0,
		'post_datetime_modifier' => FALSE,
	);

	public function __construct() {

		add_filter( 'gform_form_post_get_meta', array( $this, 'maybe_add_hooks' ), 10 );
	}

	public function maybe_add_hooks( $form ) {

		$addon    = GF_Post_Draft_To_Publish::get_instance();
		$settings = $addon->get_form_settings( $form );

		if ( is_array( $settings ) && array_key_exists(
				'ppd-enabled',
				$settings
			) && '1' === $settings['ppd-enabled'] ) {

			$this->option['post_id_field']   = rgar( $settings, 'ppd-field_id', 0 );
			$this->option['post_title_id']   = rgar( $settings, 'ppd-post_title_id', 0 );
			$this->option['post_content_id'] = rgar( $settings, 'ppd-post_content_id', 0 );

			$this->option['post_date_id'] = rgar( $settings, 'ppd-post_date_id', 0 );
			$this->option['post_time_id'] = rgar( $settings, 'ppd-post_time_id', 0 );

			$this->option['post_datetime_modifier'] = rgar( $settings, 'ppd-post_datetime_modifier', FALSE );

			add_action(
				"gform_after_submission_{$form['id']}",
				array( $this, 'update_post_status' ),
				10,
				2
			);

			add_filter(
				"gform_disable_post_creation_{$form['id']}",
				array( $this, 'disable_post_creation' ),
				10,
				3
			);

			add_filter(
				"gform_pre_render_{$form['id']}",
				array( $this, 'populate_date_on_pre_render' )
			);

		}

		return $form;
	}

	public function disable_post_creation( $is_disabled, $form, $entry ) {

		$is_disabled = TRUE;

		return $is_disabled;
	}

	/**
	 * Callback for the `gform_after_submission_{id}` action.
	 *
	 * @param array $entry The Entry object.
	 * @param array $form  The form object.
	 */
	public function update_post_status( $entry, $form ) {

		$field_id = $this->option['post_id_field'];

		if ( empty( $field_id ) ) {

			return;
		}

		$post = get_post( rgar( $entry, $field_id ) );

		if ( $post instanceof WP_Post ) {

			$post->post_status = 'publish';

			if ( $this->option['post_title_id'] && rgar( $entry, $this->option['post_title_id'] ) ) {

				$post->post_title = rgar( $entry, $this->option['post_title_id'] );
			}

			if ( $this->option['post_content_id'] && rgar( $entry, $this->option['post_content_id'] ) ) {

				$post->post_content = rgar( $entry, $this->option['post_content_id'] );
			}

			if ( ( $this->option['post_date_id'] && rgar( $entry, $this->option['post_date_id'] ) ) &&
			     ( $this->option['post_time_id'] && rgar( $entry, $this->option['post_time_id'] ) )
			) {

				$date = rgar( $entry, $this->option['post_date_id'] ) . ' ' . rgar( $entry, $this->option['post_time_id'] );

				if ( rgblank( trim( $date ) ) ) {

					$date = current_time( 'mysql' );

				} else {

					$date = date( 'Y-m-d H:i:s', strtotime( $date ) );
				}

				if ( $this->option['post_datetime_modifier'] ) {

					$timestamp = strtotime( $this->option['post_datetime_modifier'], strtotime( $date ) );
					$date      = date( 'Y-m-d H:i:s', $timestamp );
				}

				$post->post_date     = $date;
				$post->post_date_gmt = get_gmt_from_date( $date, 'Y-m-d H:i:s' );

				// String comparison to work around far future dates (year 2038+) on 32-bit systems.
				if ( $post->post_date_gmt > gmdate( 'Y-m-d H:i:59' ) ) {

					$post->post_status = 'future';
				}
			}

			//error_log( json_encode( $post, 128 ) );
			wp_update_post( $post );

			add_post_meta( $post->ID, '_post_publisher', get_current_user_id() );

			check_and_publish_future_post( $post->ID );
		}

	}

	/**
	 * Callback for the `gform_pre_render_{id}` filter.
	 *
	 * @param array $form The form object.
	 *
	 * @return mixed
	 */
	public function populate_date_on_pre_render( $form ) {

		foreach ( $form['fields'] as &$field ) {

			if ( $field['id'] == $this->option['post_date_id'] && GFFormsModel::get_input_type( $field ) === 'date' ) {

				$key = sprintf( 'sat_pd_%d_%d', $form['id'], $field['id'] );

				$field['allowsPrepopulate'] = TRUE;
				$field['inputName']         = $key;

				add_filter(
					"gform_field_value_{$key}",
					function( $value ) {

						$timestamp = current_time( 'timestamp' );

						//if ( $this->option['post_datetime_modifier'] ) {
						//
						//	$timestamp = strtotime( $this->option['post_datetime_modifier'], $timestamp );
						//}

						$value = date( 'm/d/Y', $timestamp );

						return $value;
					}
				);
			}

			if ( $field['id'] == $this->option['post_time_id'] && GFFormsModel::get_input_type( $field ) === 'time' ) {

				$key = sprintf( 'sat_pt_%d_%d', $form['id'], $field['id'] );

				$field['allowsPrepopulate'] = TRUE;
				$field['inputName']         = $key;

				//var_dump( $field );
				add_filter(
					"gform_field_value_{$key}",
					function( $value ) {

						$timestamp = current_time( 'timestamp' );
						$value     = date( 'g:i A', $timestamp );

						return $value;
					}
				);
			}
		}

		return $form;
	}
}
