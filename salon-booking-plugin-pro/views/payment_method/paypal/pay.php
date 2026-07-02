        <?php
        // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
        $deposit = $plugin->getSettings()->isPaymentDepositFixedAmount() ? $booking->getDeposit() : $booking->getDeposit(true);
        $total = $booking->getToPayAmount(false);
        ?>
        <?php if($payRemainingAmount): ?>
                <span class="sln-btn__info">
                    <i>
                <?php echo sprintf(
                        // translators: %s: name of the payment method
                        __('Pay <strong>%1$s</strong> as a remaining amount with %2$s', 'salon-booking-system'), $plugin->format()->moneyFormatted($booking->getRemaingAmountAfterPay()), $paymentMethod->getMethodLabel()) ?>
                    </i>
                </span>
        <?php else : ?>
                <?php if($deposit > 0): ?>
                    <span class="sln-btn__info">
                        <i>
                        <?php echo sprintf(
                                // translators: %s: name of the payment method
                                __('Pay <strong>%1$s</strong> as a deposit with %2$s', 'salon-booking-system'), $plugin->format()->moneyFormatted($deposit), $paymentMethod->getMethodLabel()) ?>
                        </i>
                    </span>
                <?php else : ?>
                    <span class="sln-btn__info">
                        <i>
                        <?php echo sprintf(
                                // translators: %s: name of the payment method
                                __('Pay <strong class=\'sln-total-price\'>%s</strong> with ', 'salon-booking-system'), $plugin->format()->moneyFormatted($total));
                        echo $paymentMethod->getMethodLabel(); ?>
                        </i>
                    </span>
                <?php endif ?>
        <?php endif ?>
            <a data-salon-data="<?php echo $ajaxData.'&mode='.$paymentMethod->getMethodKey() ?>" data-salon-toggle="direct"
        href="<?php echo $payUrl ?>" class="">
                    <?php //echo sprintf(__('Pay Now', 'salon-booking-system'), $paymentMethod->getMethodLabel()) ?>
                    <?php esc_html_e('Pay Now', 'salon-booking-system');?>
        </a>