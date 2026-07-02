<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
    <div class="col-xs-12"><h2 class="sln-box-title"><?php esc_html_e('Paypal account informations', 'salon-booking-system');?></h2></div>
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-4">
            <div class="sln-input--simple"><?php $adminSettings->row_input_text('pay_paypal_email', __('Your PayPal account e-mail', 'salon-booking-system'));?></div>
        </div>
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-4">
            <div class="sln-checkbox">
        	<?php $adminSettings->row_input_checkbox('pay_paypal_test', __('Enable PayPal sandbox', 'salon-booking-system'));?>
            </div>
        	<div class="sln-box-maininfo">
            <p class="sln-box-info"><?php esc_html_e('Check this option to test PayPal payments
using your PayPal Sandbox account.', 'salon-booking-system');?></p>
        </div>
        </div>

