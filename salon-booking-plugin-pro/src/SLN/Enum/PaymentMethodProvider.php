<?php

class SLN_Enum_PaymentMethodProvider
{

    private static $labels = array();

    private static $classes = array();

    public static function toArray()
    {
        if (!self::$labels) {
            throw new Exception('No Payment Provider');
        }
        return self::$labels;
    }

    public static function getLabel($key)
    {
        if (!isset(self::$labels[$key])) {
            throw new Exception(sprintf('label not found "%s"', esc_html($key)));
        }
        return self::$labels[$key];
    }

    /**
     * @param $key
     * @param SLN_Plugin $plugin
     * @return SLN_Action_Sms_Abstract
     * @throws Exception
     */
    public static function getService($key, SLN_Plugin $plugin)
    {
        $name = self::getServiceName($key);
        return new $name($plugin, $key, self::getLabel($key));
    }

    public static function getServiceName($key){
        if (!isset(self::$classes[$key])) {
            throw new Exception(sprintf('payment method "%s" not found', esc_html($key)));
        }
        return self::$classes[$key];
    }

    public static function addService($key, $label, $class){
        self::$labels[$key] = $label;
        self::$classes[$key] = $class;
    }
}