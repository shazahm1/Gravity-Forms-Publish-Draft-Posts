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
						//'label'         => 'Post Status',
						'label' => 'Field ID for Post Status',
						//'type'  => 'select',
						'type'  => 'text',
						'name'  => 'ppd-status',
						'class' => 'small',
						//'choices'       => array(
						//	array(
						//		'label' => 'Published',
						//		'value' => 'publish',
						//	),
						//	array(
						//		'label' => 'Pending Review',
						//		'value' => 'pending',
						//	),
						//	array(
						//		'label' => 'Draft',
						//		'value' => 'draft',
						//	),
						//	array(
						//		'label' => 'Private',
						//		'value' => 'private',
						//	),
						//),
						//'default_value' => 'publish',
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
					array(
						'label'   => 'Post Lock Checkbox',
						'type'    => 'text',
						'name'    => 'ppd-post_lock_id',
						'tooltip' => 'Enter the field ID of the checkbox which will trigger the post lock.',
						'class'   => 'small',
					),
					array(
						'label'   => 'Post Lock Interval',
						'type'    => 'text',
						'name'    => 'ppd-post_interval',
						'tooltip' => 'Enter the number of seconds a post should be locked.',
						'class'   => 'small',
						'default_value' => 600,
					),
				),
			),
		);

	}
}

class Gravity_Forms_Publish_Post_Draft {

	private $option = array(
		'post_id_field'          => 0,
		'post_status'            => 'publish',
		'post_title_id'          => 0,
		'post_content_id'        => 0,
		'post_date_id'           => 0,
		'post_time_id'           => 0,
		'post_datetime_modifier' => FALSE,
		'post_lock_id'           => 0,
		'post_interval'          => 600,
	);

	public function __construct() {

		add_filter( 'gform_form_post_get_meta', array( $this, 'maybe_add_hooks' ), 10 );

		// AJAX action needs added here so it is registered in time with WP.
		add_action( 'wp_ajax_gf-post-lock', array( __CLASS__, 'post_lock' ) );
	}

	public function maybe_add_hooks( $form ) {

		$addon    = GF_Post_Draft_To_Publish::get_instance();
		$settings = $addon->get_form_settings( $form );

		if ( is_array( $settings ) && array_key_exists(
				'ppd-enabled',
				$settings
			) && '1' === $settings['ppd-enabled'] ) {

			$this->option['post_id_field']   = rgar( $settings, 'ppd-field_id', 0 );
			$this->option['post_status']     = rgar( $settings, 'ppd-status', 'publish' );
			//$this->option['post_status']     = rgar( $settings, 'ppd-status', 0 );
			$this->option['post_title_id']   = rgar( $settings, 'ppd-post_title_id', 0 );
			$this->option['post_content_id'] = rgar( $settings, 'ppd-post_content_id', 0 );

			$this->option['post_date_id'] = rgar( $settings, 'ppd-post_date_id', 0 );
			$this->option['post_time_id'] = rgar( $settings, 'ppd-post_time_id', 0 );

			$this->option['post_datetime_modifier'] = rgar( $settings, 'ppd-post_datetime_modifier', FALSE );

			$this->option['post_lock_id']  = rgar( $settings, 'ppd-post_lock_id', 0 );
			$this->option['post_interval'] = rgar( $settings, 'ppd-post_interval', 600 );

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
				array( $this, 'filter_posts' ),
				10,
				3
			);

			add_filter(
				"gform_pre_render_{$form['id']}",
				array( $this, 'populate_date_on_pre_render' )
			);

			add_filter(
				"gform_pre_render_{$form['id']}",
				array( $this, 'on_change' )
			);

			add_filter(
				"gform_enqueue_scripts_{$form['id']}",
				array( $this, 'enqueue_scripts' )
			);

		}

		return $form;
	}

	/**
	 * Enqueue the `wp-util` library so the `wp.ajax` utils can be used.
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( 'wp-util' );
	}

	/**
	 * Callback for the `wp_ajax_` action.
	 *
	 * Set post lock meta.
	 */
	public static function post_lock() {

		if ( ! isset( $_POST['post_ID'] ) || empty( $_POST['post_ID'] ) ) {

			wp_send_json_error( 'Post ID is required.' );
		}

		$post_ID = absint( $_POST['post_ID'] );

		$result = check_ajax_referer( 'gf_post_lock', 'nonce', false );

		if ( false === $result ) {

			wp_send_json_error( 'Check AJAX referer failed.' );
		}

		$result = wp_set_post_lock( $post_ID );

		wp_send_json_success( $result );
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

		$post_id = rgar( $entry, $field_id );
		$post    = get_post( $post_id );

		if ( $post instanceof WP_Post ) {

			$current_time = current_time( 'mysql' );

			//$post->post_status   = $this->option['post_status'];
			$post->post_status   = 'draft';
			$post->post_date     = $current_time;
			$post->post_date_gmt = get_gmt_from_date( $current_time, 'Y-m-d H:i:s' );

			wp_update_post( $post );
			$post = get_post( $post_id );

			if ( $this->option['post_status'] && rgar( $entry, $this->option['post_status'] ) ) {

				$post->post_status = rgar( $entry, $this->option['post_status'] );
			}

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
	 * Remove posts that are currently locked from the post choice options.
	 *
	 * @param array $form         The form object.
	 * @param bool  $ajax         Is AJAX enabled.
	 * @param array $field_values An array of dynamic population parameter keys with their corresponding values to be
	 *                            populated.
	 *
	 * @return array
	 */
	public function filter_posts( $form, $ajax, $field_values ) {

		$post_lock_interval = function() { return $this->option['post_interval']; };

		add_filter( 'wp_check_post_lock_window', $post_lock_interval );

		foreach ( $form['fields'] as &$field ) {

			// Process only the Post ID field.
			if ( (int) $this->option['post_id_field'] !== (int) $field['id'] ) continue;

			// Check for the `choices` index and ensure it is an array.
			if ( ! isset( $field->choices ) || ! is_array( $field->choices ) ) continue;

			foreach ( $field->choices as $i => &$choice ) {

				// If post is locked, remove it from choices.
				if ( false !==  wp_check_post_lock( $choice['value'] ) ) {

					unset( $field->choices[ $i ] );
				}
			}

		}

		remove_filter( 'wp_check_post_lock_window', $post_lock_interval );

		return $form;
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

	/**
	 * Callback for the `gform_pre_render_{id}` filter.
	 *
	 * Add an on change event to set the post lock meta data.
	 *
	 * @param array $form The form object.
	 *
	 * @return array
	 */
	public function on_change( $form ) {

		$json = wp_json_encode( $this->option );

		?>
		<script type="text/javascript">
			var postLockOptions = <?php echo $json; ?>;
			console.log( postLockOptions );
		</script>
		<script type="text/javascript">
			gform.addAction(
				'gform_input_change',
				function( elem, formId, fieldId ) {

					if ( <?php echo "\"{$this->option['post_lock_id']}.1\"" ?> === fieldId ) {

						// console.log( fieldId );
						var field  = jQuery( elem );
						var choose = jQuery( '#input_' + formId + '_' + postLockOptions.post_id_field );
						var nonce  = '<?php echo wp_create_nonce( 'gf_post_lock' ); ?>';

						if ( field.is( ':checked' ) ) {

							choose.prop( 'disabled', true );
							console.log( 'isChecked' );

							wp.ajax.send(
								'gf-post-lock',
								{
									data: {
										nonce: nonce,
										post_ID: choose.children( 'option:selected' ).val(),
									}
								}
							);

						} else {

							choose.prop( 'disabled', false );
							console.log( 'notChecked' );
						}
					}

					// console.log( fieldId, elem );
				},
				10
			);
		</script>
		<?php

		return $form;
	}
}
