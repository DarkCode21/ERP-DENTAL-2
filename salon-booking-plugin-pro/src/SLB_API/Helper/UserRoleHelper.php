<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API\Helper;

use SLN_Plugin;
use WP_User;

class UserRoleHelper
{
    const EDITOR_ROLE = 'editor';

    /** @var array */
    protected $allowed_roles;

    public function __construct()
    {
        $this->allowed_roles = apply_filters('sln_api_user_role_helper_allowed_roles', array(
            'administrator',
            SLN_Plugin::USER_ROLE_STAFF,
        ));

        if ($this->is_allow_editor_role()) {
            $this->allowed_roles[] = self::EDITOR_ROLE;
        }
    }

    public function is_allowed_user(WP_User $user)
    {
        return !empty(array_intersect((array)$user->roles, $this->allowed_roles));
    }

    public function is_allow_editor_role()
    {
        return SLN_Plugin::getInstance()->getSettings()->get('editors_manage_cap');
    }

    public function is_hide_customer_email()
    {
        $current_user = wp_get_current_user();

        return in_array('administrator', (array)$current_user->roles, true)
            ? 0
            : SLN_Plugin::getInstance()->getSettings()->get('hide_customers_email');
    }

    public function is_hide_customer_email_mail()
    {
        return SLN_Plugin::getInstance()->getSettings()->get('hide_customers_email');
    }

    public function is_hide_customer_phone()
    {
        $current_user = wp_get_current_user();

        return in_array('administrator', (array)$current_user->roles, true)
            ? 0
            : SLN_Plugin::getInstance()->getSettings()->get('hide_customers_phone');
    }

    public function is_hide_customer_phone_mail()
    {
        return SLN_Plugin::getInstance()->getSettings()->get('hide_customers_phone');
    }
}
