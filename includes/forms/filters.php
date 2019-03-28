<?php
// Backward compatibility start.

/**
 * Remove old action which help to render donation form
 * Note: from version 2.0, donation form will be render with form api.
 *
 * @see   inludes/forms/api/give-form-api.php
 *
 * @since 2.0
 */
function give_remove_donation_form_fields_render_action() {
	$render_actions = array(
		// Action name               Callback
		'give_checkout_form_top' => 'give_output_donation_amount_top',
	);

	foreach ( $render_actions as $action_hook => $callback ) {
		if ( $priority = has_action( $action_hook, $callback ) ) {
			remove_action( $action_hook, $callback, $priority );
		}
	}
}

add_action( 'init', 'give_remove_donation_form_fields_render_action' );


/**
 * Fire the form releated action hooks.
 *
 * @since 2.0
 *
 * @param $form_html
 * @param $form
 *
 * @return mixed
 */
function give_donation_form_actions( $form_html, $form ) {
	if ( false === strpos( $form['id'], 'give-form-' ) ) {
		return $form_html;
	}

	ob_start();

	/**
	 * Fires while outputting donation form, before all other fields.
	 *
	 * @since 1.0
	 *
	 * @param int   $form_id The form ID.
	 * @param array $args    An array of form arguments.
	 */
	do_action( 'give_checkout_form_top', $form['donation_form_object']->ID, $form['donation_form_arguments'] );

	/**
	 * Fires while outputing donation form, for payment gatways fields.
	 *
	 * @since 1.7
	 *
	 * @param int   $form_id The form ID.
	 * @param array $args    An array of form arguments.
	 */
	do_action( 'give_payment_mode_select', $form['donation_form_object']->ID, $form['donation_form_arguments'] );

	/**
	 * Fires while outputing donation form, after all other fields.
	 *
	 * @since 1.0
	 *
	 * @param int   $form_id The form ID.
	 * @param array $args    An array of form arguments.
	 */
	do_action( 'give_checkout_form_bottom', $form['donation_form_object']->ID, $form['donation_form_arguments'] );

	$form_html = str_replace( '</form>', ob_get_clean() . '</form>', $form_html );

	return $form_html;
}

add_filter( 'give_form_api_render_form', 'give_donation_form_actions', 10, 2 );


/**
 * Fire the amount field related actions.
 *
 * @since 2.0
 *
 * @param $field_html
 * @param $field
 * @param $form
 *
 * @return string
 */
function give_donation_form_amount_field_actions( $field_html, $field, $form ) {
	if ( ! in_array( $field['type'], array( 'hidden', 'text' ) ) || 'give-amount' !== $field['id'] ) {
		return $field_html;
	}

	$form_id             = $form['donation_form_object']->ID;
	$form_args           = $form['donation_form_arguments'];
	$variable_pricing    = give_has_variable_prices( $form_id );
	$allow_custom_amount = get_post_meta( $form_id, '_give_custom_amount', true );
	$custom_amount_text  = get_post_meta( $form_id, '_give_custom_amount_text', true );


	ob_start();

	/**
	 * Fires while displaying donation form, before donation level fields.
	 *
	 * @since 1.0
	 *
	 * @param int   $form_id The form ID.
	 * @param array $args    An array of form arguments.
	 */
	do_action( 'give_before_donation_levels', $form_id, $form_args );


	// Field html.
	echo $field_html;

	/**
	 * Fires while displaying donation form, after donation amount field(s).
	 *
	 * @since 1.0
	 *
	 * @param int   $form_id The form ID.
	 * @param array $args    An array of form arguments.
	 */
	do_action( 'give_after_donation_amount', $form_id, $form_args );

	//Custom Amount Text
	if ( ! $variable_pricing && give_is_setting_enabled( $allow_custom_amount ) && ! empty( $custom_amount_text ) ) { ?>
		<p class="give-custom-amount-text"><?php echo $custom_amount_text; ?></p>
	<?php }

	// Remaining donation levels field and action moved to other callback.
	// @see includes/forms/filters.php:273

	return ob_get_clean();
}

add_filter( 'give_field_api_render_field', 'give_donation_form_amount_field_actions', 10, 3 );

// Backward compatibility end.


/**
 * Edit give-price-id hidden field in donation form.
 *
 * @since 2.0
 *
 * @param $form_args
 *
 * @return array
 */
function give_form_edit_price_id( $form_args ) {
	if ( ! empty( $form_args['fields'] ) ) {
		foreach ( $form_args['fields'] as $index => $field ) {
			if ( array_key_exists( 'give-price-id', array_keys( $form_args['fields'] ) ) ) {
				// Set form id.
				$form_id = $form_args['donation_form_object']->ID;

				// Price ID hidden field for variable (multi-level) donation forms.
				if ( give_has_variable_prices( $form_id ) ) {
					// Get default selected price ID.
					$prices   = apply_filters( 'give_form_variable_prices', give_get_variable_prices( $form_id ), $form_id );
					$price_id = 0;

					//loop through prices.
					foreach ( $prices as $price ) {
						if ( isset( $price['_give_default'] ) && $price['_give_default'] === 'default' ) {
							$price_id = $price['_give_id']['level_id'];
						};
					}

					// Form default price id.
					$form_args['fields'][ $index ]['value'] = $price_id;
				} else {
					unset( $form_args['fields'][ $index ] );
				}

				break;
			}
		}
	}

	return $form_args;
}

add_filter( 'give_form_args', 'give_form_edit_price_id' );

/**
 * Edit give-amount hidden field in donation form.
 *
 * @since 2.0
 *
 * @param $form_args
 *
 * @return array
 */
function give_form_edit_amount( $form_args ) {
	if ( ! empty( $form_args['fields'] ) ) {
		foreach ( $form_args['fields'] as $index => $field ) {
			if ( array_key_exists( 'give-amount', array_keys( $form_args['fields'] ) ) ) {
				// Set form id.
				$form_id             = $form_args['donation_form_object']->ID;
				$give_options        = give_get_settings();
				$allow_custom_amount = get_post_meta( $form_id, '_give_custom_amount', true );
				$currency_position   = isset( $give_options['currency_position'] ) ? $give_options['currency_position'] : 'before';
				$symbol              = give_currency_symbol( give_get_currency() );
				$currency_output     = '<span class="give-currency-symbol give-currency-position-' . $currency_position . '">' . $symbol . '</span>';
				$default_amount      = give_format_amount( give_get_default_form_amount( $form_id ) );

				// Set value for field.
				$form_args['fields'][ $index ]['value'] = $default_amount;

				if ( ! give_is_setting_enabled( $allow_custom_amount ) ) {
					$amount_text = sprintf(
						'%1$s<span id="give-amount-text" class="give-text-input give-amount-top">%2$s</span>%3$s',
						( $currency_position == 'before' ? $currency_output : '' ),
						$default_amount,
						( $currency_position == 'after' ? $currency_output : '' )
					);

					$form_args['fields'][ $index ]['after_field'] = '<div class="set-price give-donation-amount form-row-wide">' . $amount_text . '</div>';

				} else {
					$form_args['fields'][ $index ] = array(
						'type'                 => 'text',
						'id'                   => 'give-amount',
						'value'                => $default_amount,
						'label'                => esc_html__( 'Donation Amount:', 'give' ),
						'required'             => true,
						'wrapper_type'         => 'div',
						'before_field_label'   => ( $currency_position == 'before' ? $currency_output : '' ),
						'after_field'          => ( $currency_position == 'after' ? $currency_output : '' ),
						'before_field_wrapper' => '<div class="give-total-wrap">',
						'after_field_wrapper'  => '</div>',
						'label_attributes'     => array(
							'class' => 'give-hidden',
						),
						'field_attributes'     => array(
							'id'    => 'give-amount',
							'class' => 'give-text-input give-amount-top',
						),
						'wrapper_attributes'   => array(
							'class' => 'give-donation-amount form-row-wide',
						),
					);
				}


				break;
			}
		}
	}

	return $form_args;
}

add_filter( 'give_form_args', 'give_form_edit_amount' );

/**
 * Render give_donation levels field.
 *
 * @since 2.0
 *
 * @param $field_html
 * @param $field
 * @param $form
 *
 * @return mixed
 */
function give_form_render_give_donation_levels_field( $field_html, $field, $form ) {
	$form_id          = $form['donation_form_object']->ID;
	$form_args        = $form['donation_form_arguments'];
	$variable_pricing = give_has_variable_prices( $form_id );

	ob_start();

	//Output Variable Pricing Levels.
	if ( $variable_pricing ) {
		give_output_levels( $form_id );
	}

	/**
	 * Fires while displaying donation form, after donation level fields.
	 *
	 * @since 1.0
	 *
	 * @param int   $form_id The form ID.
	 * @param array $args    An array of form arguments.
	 */
	do_action( 'give_after_donation_levels', $form_id, $form_args );

	return ob_get_clean();
}

add_filter( 'give_field_api_render_give_donation_levels_field', 'give_form_render_give_donation_levels_field', 10, 3 );


/**
 * Set default values for give-payment-modes field
 *
 * @since 2.0
 *
 * @param array $field
 * @param array $form
 *
 * @return array
 */
function give_form_set_values_payment_modes_field( $field, $form ) {
	// Bailout.
	if ( 'payment-mode' !== $field['id'] ) {
		return $field;
	}

	$donation_form = $form['donation_form_object'];

	foreach ( $field['options'] as $key => $option) {
		$field['options'][ $key ]['label'] = $option['checkout_label'];
		$field['options'][ $key ]['field_attributes']['class'] = 'give-gateway';
		$field['options'][ $key ]['label_attributes']['class'] = 'give-gateway-option';

		$field['options'][ $key ]['for'] = $field['options'][ $key ]['field_attributes']['id'] = "give-gateway-{$key}-{$donation_form->ID}";
	}

	return $field;
}

add_filter( 'give_field_api_set_values', 'give_form_set_values_payment_modes_field', 0, 2 );

/**
 * Render give_donation levels field.
 *
 * @since 2.0
 *
 * @param string $field_html
 * @param array  $field
 * @param array  $form
 *
 * @return mixed
 */
function give_form_render_payment_mode_field( $field_html, $field, $form ) {
	// Bailout.
	if ( 'payment-mode' !== $field['id'] ) {
		return $field_html;
	}

	ob_start();

	echo '<div id="give-payment-mode-wrap">';
		/**
		 * Fires while selecting payment gateways, befire the gateways list.
		 *
		 * @since 1.7
		 */
		do_action( 'give_payment_mode_before_gateways' );

		echo $field_html;

		/**
		 * Fires while selecting payment gateways, before the gateways list.
		 *
		 * @since 1.7
		 */
		do_action( 'give_payment_mode_after_gateways' );

	echo '</div>';

	return ob_get_clean();
}

add_filter( 'give_field_api_render_radio_field', 'give_form_render_payment_mode_field', 0, 3 );


/**
 * Render give_payment_modes section
 *
 * @since 2.0
 *
 * @param string $field_html
 * @param array  $field
 * @param array  $form
 *
 * @return string
 */
function give_form_render_give_payment_modes_section( $field_html, $field, $form ) {
	// Bailout.
	if ( 'give-payment-modes' !== $field['id'] ) {
		return $field_html;
	}

	$form_id = $form_id = $form['donation_form_object']->ID;

	ob_start();

	/**
	 * Fires while selecting payment gateways, after the wrap div.
	 *
	 * @since 1.7
	 *
	 * @param int $form_id The form ID.
	 */
	do_action( 'give_payment_mode_before_gateways_wrap' );

	$before_wrap_div = ob_get_clean();


	ob_start();

	/**
	 * Fires while selecting payment gateways, after the wrap div.
	 *
	 * @since 1.7
	 *
	 * @param int $form_id The form ID.
	 */
	do_action( 'give_payment_mode_after_gateways_wrap' );

	$after_wrap_div = ob_get_clean();

	ob_start();

	/**
	 * Fires while selecting payment gateways, before the fields.
	 *
	 * @since 1.7
	 *
	 * @param int $form_id The form ID.
	 */
	do_action( 'give_payment_mode_top', $form_id );


	// add before and after wrap hook output to section.
	echo str_replace(
		array( '<legend', '</fieldset' ),
		array( "{$before_wrap_div}<legend", "{$after_wrap_div}</fieldset" ),
		$field_html
	);

	/**
	 * Fires while selecting payment gateways, after the fields.
	 *
	 * @since 1.7
	 *
	 * @param int $form_id The form ID.
	 */
	do_action( 'give_payment_mode_bottom', $form_id );

	echo '<div id="give_purchase_form_wrap">';
		/**
		 * Fire after payment field render.
		 *
		 * @since 1.7
		 */
		do_action( 'give_donation_form', $form_id, array() );
	echo '</div>';


	/**
	 * Fire after donation form render.
	 *
	 * @since 1.7
	 */
	do_action( 'give_donation_form_wrap_bottom', $form_id );

	return ob_get_clean();
}

add_filter( 'give_field_api_render_section', 'give_form_render_give_payment_modes_section', 0, 3 );
