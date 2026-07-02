<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
    <div class="col-xs-12"><h2 class="sln-box-title"><?php esc_html_e('Stripe account informations','salon-booking-system');?></h2></div>
        <div class="col-xs-12 col-sm-4 sln-input--simple">
            <?php $adminSettings->row_input_text('pay_stripe_apiKey', __('Secret key', 'salon-booking-system')); ?>
            <p class="sln-input-help"><?php esc_html_e('Enter your Stripe api key', 'salon-booking-system');?></p>
        </div>
        <div class="col-xs-12 col-sm-4 sln-input--simple">
            <?php $adminSettings->row_input_text('pay_stripe_apiKeyPublic', __('Publishable key', 'salon-booking-system')); ?>
        <p class="sln-input-help"><?php esc_html_e('Enter your Stripe publishable api key','salon-booking-system');?></p>
        </div>
        <div class="col-xs-12 col-sm-4 sln-select">
            <?php $adminSettings->select_text('pay_stripe_method', __('Select a payment method', 'salon-booking-system'),
                    array(
                        'all' => array('label' => __('ALL', 'salon-booking-system'), 'id' => 'all'),
                        'card' => array('label' => __('CREDIT CARD', 'salon-booking-system'), 'id' => 'card'),
                        'bancontact' => array('label' => __('BANCONTACT', 'salon-booking-system'), 'id' => 'bancontact'),
                    )
                ); 
            ?>
        </div>
        

