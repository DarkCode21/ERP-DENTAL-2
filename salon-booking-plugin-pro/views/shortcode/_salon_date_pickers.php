<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var $date DateTime
 * @var $plugin SLN_Plugin
 * @var SLN_Shortcode_Salon_DateStep $step
 */

$size = $step->getShortcode()->getStyleShortcode();
$size = SLN_Enum_ShortcodeStyle::getSize($size);

$bb = $plugin->getBookingBuilder();

$assistantId = '';
if ( isset($_POST['sln']['attendant']) && ! empty($_POST['sln']['attendant']) ) {
    $assistantId = absint($_POST['sln']['attendant']);
} elseif ( isset($_POST['sln']['attendants']) && is_array($_POST['sln']['attendants']) ) {
    foreach ($_POST['sln']['attendants'] as $postedAttendantId) {
        if ($postedAttendantId) {
            $assistantId = absint($postedAttendantId);
            break;
        }
    }
}

$selectedServices = $bb->getAttendantsIds();
if (!$assistantId && !empty($selectedServices)) {
    $selectedAttendants = array_filter(array_unique(array_map('absint', array_values($selectedServices))));
    if (1 === count($selectedAttendants)) {
        $assistantId = reset($selectedAttendants);
    }
}

$serviceCount = $bb->get('service_count');
$serviceCount = is_array($serviceCount) ? $serviceCount : array();
?>
<?php ob_start();?>
<?php SLN_Form::fieldJSDate('sln[date]', $date, array('inline' => true))?>
<input name="sln[date]" type="hidden" value="<?php echo esc_html(SLN_plugin::getInstance()->format()->date($date)) ?>"/>
<?php $datepicker = ob_get_clean();
ob_start();?>
<div id="sln_timepicker_viewdate"></div>
<?php SLN_Form::fieldJSTime('sln[time]', $date, array('interval' => $plugin->getSettings()->get('interval'), 'inline' => true))?>
<input name="sln[time]" type="hidden" value="<?php echo esc_html(SLN_plugin::getInstance()->format()->time($date)) ?>"/>
<?php $timepicker = ob_get_clean();?>

<div class="col-xs-12 <?php echo '900' == $size ? 'col-md-4' : '' ?> sln-input sln-input--datepicker">
    <?php echo $datepicker ?>
    <button id="notify-assistant-btn">¿Avísame si se libera un hueco?</button>
</div>
<div class="col-xs-12 <?php echo '900' == $size ? 'col-md-4' : '' ?> sln-input sln-input--datepicker">
    <?php echo $timepicker ?>
</div>


<input type="hidden" name="sln[customer_timezone]" value="<?php echo $bb->get('customer_timezone') ?>">
<?php if((bool)SLN_Plugin::getInstance()->getSettings()->get('debug') && current_user_can( 'administrator' ) ): ?>
                <div id="sln-debug-div">
                    <div id="sln-debug-sticky-panel" style="width: 100%">
                        <div id="close-debug-table"><?php esc_html_e( 'Close', 'salon-booking-system') ?></div>
                        <input type="hidden" name="sln[debug]" value="1">
                        <div id="disable-debug-table"><?php esc_html_e( 'Disable', 'salon-booking-system' ) ?></div>
                        <nav class="sln-inpage_navbar_inner">
                            <ul id="sln-settings-links" class="nav nav-pills sln-inpage_navbar">
                                <li class="nav-item sln-inpage_navbaritem"><a href=<?php echo get_admin_url(). '/admin.php?page=salon-settings&tab=booking'; ?> class="nav-link nav-link1 sln-inpage_navbarlink" target="_blank"><?php esc_html_e( 'Booking rules', 'salon-booking-system' ) ?></a></li>
                                <li class="nav-item sln-inpage_navbaritem"><a href=<?php echo get_admin_url(). '/edit.php?post_type=sln_attendant' ?> class="nav-link nav-link1 sln-inpage_navbarlink" target="_blank"><?php esc_html_e( 'Assistants', 'salon-booking-system' ) ?></a></li>
                                <li class="nav-item sln-inpage_navbaritem"><a href=<?php echo get_admin_url(). '/edit.php?post_type=sln_service' ?> class="nav-link nav-link1 sln-inpage_navbarlink" target="_blank"><?php esc_html_e( 'Services', 'salon-booking-system') ?></a></li>
                            </ul>
                        </nav>
                        <div class="sln-debug-move"><div class="bar"></div><div class="bar"></div><div class="bar"></div></div>
                    </div>
                    <div id="sln-debug-attendants" class="sln-row">
                        <?php foreach(SLN_Helper_Availability_AdminRuleLog::getInstance()->getAttendats() as $attendant_deb): ?>
                            <div class=sln-debug-time-slote><?php echo $attendant_deb->getName(); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="sln-debug-table">
                        <?php foreach( SLN_Helper_Availability_AdminRuleLog::getInstance()->getLog() as $time => $rules ): ?>
                            <div class="sln-debug-time-slote">
                                <div class="sln-debug-popup">
                                    <?php $failedRule = '';
                                        foreach( $rules as $ruleName => $ruleValue ){
                                        echo '<p class="'. ( (!$ruleValue) ? 'sln-debug--failed"':'"' ).'>'. $ruleName. '</p>';
                                        if( !(bool)$ruleValue && empty( $failedRule ) ){
                                            $failedRule = $ruleName;
                                        }
                                    } ?>
                                </div>
                                <div class="sln-debug-time <?php echo ( !empty($failedRule) ) ? 'sln-debug--failed"' : '"' ; ?>">
                                    <?php echo "<p title=\"$failedRule\">". $time. '</p>'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="sln-debug-notifications"></div>
                    <?php SLN_Helper_Availability_AdminRuleLog::getInstance()->clear(); ?>
                </div>
            <?php endif; ?>

<style>
  #notify-assistant-btn {
    background-color: #0073aa;
    color: #fff !important;
    border: none;
    padding: 10px 15px;
    font-size: 14px;
    border-radius: 15px;
    cursor: pointer;
    margin-top: 20px !important;
    display: none;
    width: 100%;
    margin-bottom: 20px;
  }
  #notify-assistant-btn:hover {
    background-color: #006799;
  }
</style>

<script>
jQuery(document).ready(function($){
    var config = {
        ajaxUrl: (typeof salon !== 'undefined' && salon.ajax_url) ? salon.ajax_url : ((typeof myAjax !== 'undefined' && myAjax.ajax_url) ? myAjax.ajax_url : ''),
        assistantId: "<?php echo esc_js($assistantId); ?>",
        today: "<?php echo esc_js(SLN_TimeFunc::date('Y-m-d')); ?>",
        services: <?php echo wp_json_encode($selectedServices); ?>,
        serviceCount: <?php echo wp_json_encode($serviceCount); ?>
    };

    var $notifyBtn = $('#notify-assistant-btn');
    var requestTimer = null;

    if (!config.ajaxUrl || !$notifyBtn.length) {
        $notifyBtn.hide();
        if (window.console) {
            console.warn('Waitlist: AJAX URL o botón no disponible.', config);
        }
        return;
    }

    function getSelectedDate(){
        return $('input[name="sln[date]"]').val() || '';
    }

    function getIntervals(){
        var rawIntervals = $('#salon-step-date').attr('data-intervals');
        if (!rawIntervals) {
            return {};
        }

        try {
            return JSON.parse(rawIntervals);
        } catch (e) {
            return {};
        }
    }

    function getWaitlistDate(){
        var intervals = getIntervals();
        var fullDays = $.isArray(intervals.fullDays) ? intervals.fullDays : [];

        if (config.today && fullDays.indexOf(config.today) !== -1) {
            return config.today;
        }

        return getSelectedDate();
    }

    function getCustomerTimezone(){
        return $('input[name="sln[customer_timezone]"]').val() || '';
    }

    function setLoading(isLoading){
        $notifyBtn.prop('disabled', !!isLoading);
        if (isLoading) {
            $notifyBtn.data('label', $notifyBtn.text()).text('Comprobando disponibilidad...');
        } else if ($notifyBtn.data('label')) {
            $notifyBtn.text($notifyBtn.data('label'));
        }
    }

    function checkAvailability(){
        var bookingDate = getWaitlistDate();
        if (!bookingDate) {
            $notifyBtn.hide();
            return;
        }

        setLoading(true);
        if (window.console) {
            console.log('Waitlist check request', {
                assistant_id: config.assistantId,
                booking_date: bookingDate,
                services: config.services,
                service_count: config.serviceCount
            });
        }
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'check_assistant_availability',
                assistant_id: config.assistantId,
                booking_date: bookingDate,
                customer_timezone: getCustomerTimezone(),
                services: config.services,
                service_count: config.serviceCount
            }
        }).done(function(resp){
            if (window.console) {
                console.log('Waitlist check response', resp);
            }
            if (resp && resp.success && resp.data && resp.data.full) {
                $notifyBtn.show();
            } else {
                $notifyBtn.hide();
            }
        }).fail(function(xhr){
            if (window.console) {
                console.warn('Waitlist check failed', xhr && xhr.responseText ? xhr.responseText : xhr);
            }
            $notifyBtn.hide();
        }).always(function(){
            setLoading(false);
        });
    }

    function scheduleAvailabilityCheck(){
        window.clearTimeout(requestTimer);
        requestTimer = window.setTimeout(checkAvailability, 250);
    }

    $notifyBtn.on('click', function(e){
        e.preventDefault();
        var bookingDate = getWaitlistDate();
        if (!bookingDate) {
            return;
        }

        setLoading(true);
        $.ajax({
            url: config.ajaxUrl,
            type:'POST',
            dataType:'json',
            data:{
                action:'subscribe_waitlist_today',
                assistant_id: config.assistantId,
                booking_date: bookingDate,
                customer_timezone: getCustomerTimezone(),
                services: config.services,
                service_count: config.serviceCount
            }
        }).done(function(resp){
            if (window.console) {
                console.log('Waitlist subscribe response', resp);
            }
            if(resp && resp.success){
                alert('Te avisaremos si se libera un hueco.');
                $notifyBtn.hide();
            } else {
                var message = resp && resp.data ? resp.data : 'No se ha podido completar la suscripción.';
                alert('Error al suscribirte: ' + message);
                if (String(message).indexOf('Debes iniciar sesión') !== -1) {
                    window.location.href = 'https://booking.carlosking.es/booking-my-account/';
                }
            }
        }).always(function(){
            setLoading(false);
        });
    });

    $(document).on('change', 'input[name="sln[date]"]', scheduleAvailabilityCheck);
    $(document).on('click', '.sln-input--datepicker, .datetimepicker, .datepicker, .ui-datepicker-calendar', scheduleAvailabilityCheck);

    scheduleAvailabilityCheck();
});
</script>
