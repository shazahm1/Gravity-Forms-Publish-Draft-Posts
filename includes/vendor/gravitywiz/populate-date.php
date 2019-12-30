<?php
/**
 * Gravity Wiz // Gravity Forms // Populate Date
 *
 * Provides the ability to populate a Date field with a modified date based on the current date or a user-submitted date. If the
 * modified date is based on a user-submitted date, the modified date can only be populated once the form has been submitted.
 *
 * @version	  1.3
 * @author    David Smith <david@gravitywiz.com>
 * @license   GPL-2.0+
 * @link      http://gravitywiz.com/populate-dates-gravity-form-fields/
 */
class GW_Populate_Date {

	public function __construct( $args = array() ) {

		// set our default arguments, parse against the provided arguments, and store for use throughout the class
		$this->_args = wp_parse_args( $args, array(
			'form_id'         => false,
			'target_field_id' => false,
			'source_field_id' => false,
			'format'          => 'Y-m-d',
			'modifier'        => false,
			'min_date'        => false
		) );

		if( ! $this->_args['form_id'] || ! $this->_args['target_field_id'] ) {
			return;
		}

		// time for hooks
		add_action( 'init', array( $this, 'init' ) );

	}

	public function init() {

		// make sure we're running the required minimum version of Gravity Forms
		if( ! property_exists( 'GFCommon', 'version' ) || ! version_compare( GFCommon::$version, '1.8', '>=' ) ) {
			return;
		}

		if( $this->_args['source_field_id'] ) {
			add_action( 'gform_pre_submission', array( $this, 'populate_date_on_pre_submission' ) );
		} else {
			add_filter( 'gform_pre_render', array( $this, 'populate_date_on_pre_render' ) );
		}

	}

	public function populate_date_on_pre_render( $form ) {

		if( ! $this->is_applicable_form( $form ) ) {
			return $form;
		}

		foreach( $form['fields'] as &$field ) {
			if( $field['id'] == $this->_args['target_field_id'] ) {

				$key = sprintf( 'gwpd_%d_%d', $form['id'], $field['id'] );
				$value = $this->get_modified_date( $field );

				$field['allowsPrepopulate'] = true;
				$field['inputName'] = $key;

				add_filter("gform_field_value_{$key}", create_function( '', 'return \'' . $value . '\';' ) );

			}
		}

		return $form;
	}

	public function populate_date_on_pre_submission( $form ) {

		if( ! $this->is_applicable_form( $form ) ) {
			return;
		}

		foreach( $form['fields'] as &$field ) {
			if( $field['id'] == $this->_args['target_field_id'] ) {

				$timestamp = $this->get_source_timestamp( GFFormsModel::get_field( $form, $this->_args['source_field_id'] ) );
				$value = $this->get_modified_date( $field, $timestamp );

				$_POST[ "input_{$field['id']}" ] = $value;

			}
		}

	}

	public function get_source_timestamp( $field ) {

		$raw = rgpost( 'input_' . $field['id'] );
		if( is_array( $raw ) ) {
			$raw = array_filter( $raw );
		}

		list( $format, $divider ) = $field['dateFormat'] ? array_pad( explode( '_', $field['dateFormat' ] ), 2, 'slash' ) : array( 'mdy', 'slash' );
		$dividers = array( 'slash' => '/', 'dot' => '.', 'dash' => '-' );

		if( empty( $raw ) ) {
			$raw = date( implode( $dividers[ $divider ], str_split( $format ) ) );
		}

		$date = ! is_array( $raw ) ? explode( $dividers[ $divider ], $raw ) : $raw;

		$month = $date[ strpos( $format, 'm' ) ];
		$day   = $date[ strpos( $format, 'd' ) ];
		$year  = $date[ strpos( $format, 'y' ) ];

		$timestamp = mktime( 0, 0, 0, $month, $day, $year );

		return $timestamp;
	}

	public function get_modified_date( $field, $timestamp = false ) {

		if( ! $timestamp ) {
			$timestamp = current_time( 'timestamp' );
		}

		if( GFFormsModel::get_input_type( $field ) == 'date' ) {

			list( $format, $divider ) = $field['dateFormat'] ? array_pad( explode( '_', $field['dateFormat' ] ), 2, 'slash' ) : array( 'mdy', 'slash' );
			$dividers = array( 'slash' => '/', 'dot' => '.', 'dash' => '-' );

			$format = str_replace( 'y', 'Y', $format );
			$divider = $dividers[$divider];
			$format = implode( $divider, str_split( $format ) );

		} else {

			$format = $this->_args['format'];

		}

		if( $this->_args['modifier'] ) {
			$timestamp = strtotime( $this->_args['modifier'], $timestamp );
		}

		if( $this->_args['min_date'] ) {
			$min_timestamp = strtotime( $this->_args['min_date'] ) ? strtotime( $this->_args['min_date'] ) : $this->_args['min_date'];
			if( $min_timestamp > $timestamp ) {
				$timestamp = $min_timestamp;
			}
		}

		$date = date( $format, $timestamp );

		return $date;
	}

	function is_applicable_form( $form ) {

		$form_id = isset( $form['id'] ) ? $form['id'] : $form;

		return $form_id == $this->_args['form_id'];
	}

}

# Configuration

new GW_Populate_Date( array(
	                      'form_id' => 598,
	                      'target_field_id' => 2,
	                      'source_field_id' => 1,
	                      'modifier' => '+1 day'
                      ) );
