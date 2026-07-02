<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

class SLN_Action_ErpCalendarEmbed
{
    private const AUDIENCE = 'salon-erp-calendar';
    private const EMBED_FLAG = 'sln_erp_calendar';
    private const TOKEN_PARAM = 'sln_erp_token';

    /** @var SLN_Plugin */
    private $plugin;

    /** @var array */
    private $payload = array();

    /** @var string */
    private $token = '';

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;

        add_action('init', array($this, 'authenticateRequest'), 0);
        add_action('send_headers', array($this, 'sendFrameHeaders'), 0);
        add_action('template_redirect', array($this, 'renderCalendar'), 0);
        add_filter('admin_url', array($this, 'appendTokenToAdminUrl'), 10, 4);
        add_filter('redirect_post_location', array($this, 'appendTokenToRedirect'), 20, 2);
        add_action('admin_footer-post.php', array($this, 'printEditorTokenScript'));
        add_action('admin_footer-post-new.php', array($this, 'printEditorTokenScript'));
    }

    public function appendTokenToAdminUrl($url, $path, $blogId, $scheme)
    {
        if ($this->token === '') {
            return $url;
        }

        $basename = basename((string)parse_url($url, PHP_URL_PATH));
        if (!in_array($basename, array('admin-ajax.php', 'post.php', 'post-new.php'), true)) {
            return $url;
        }

        return add_query_arg(array(
            self::TOKEN_PARAM => $this->token,
            'sln_erp_embed' => '1',
        ), $url);
    }

    public function appendTokenToRedirect($location, $postId)
    {
        $token = $this->getTokenFromRequest();
        if ($token === '' || empty($this->validateToken($token))) {
            return $location;
        }

        return add_query_arg(array(
            self::TOKEN_PARAM => $token,
            'sln_erp_embed' => '1',
        ), $location);
    }

    public function authenticateRequest(): void
    {
        $token = $this->getTokenFromRequest();
        if ($token === '') {
            return;
        }

        $payload = $this->validateToken($token);
        if (empty($payload)) {
            return;
        }

        $user = $this->getPayloadUser($payload);
        if (!$user instanceof WP_User) {
            return;
        }

        $this->payload = $payload;
        $this->token = $token;

        wp_set_current_user($user->ID);

        remove_action('admin_init', 'send_frame_options_header');
        remove_action('login_init', 'send_frame_options_header');
    }

    public function printEditorTokenScript(): void
    {
        $token = $this->getTokenFromRequest();
        if ($token === '' || empty($this->validateToken($token))) {
            return;
        }
        ?>
        <script>
        (function() {
            var token = <?php echo wp_json_encode($token); ?>;
            function appendToken(url) {
                try {
                    var next = new URL(url || window.location.href, window.location.href);
                    if (next.origin !== window.location.origin) {
                        return url;
                    }
                    next.searchParams.set('<?php echo esc_js(self::TOKEN_PARAM); ?>', token);
                    next.searchParams.set('sln_erp_embed', '1');
                    return next.toString();
                } catch (error) {
                    return url;
                }
            }

            document.querySelectorAll('form').forEach(function(form) {
                form.action = appendToken(form.action);
            });

            document.querySelectorAll('a[href]').forEach(function(link) {
                if (link.href.indexOf('/wp-admin/') !== -1) {
                    link.href = appendToken(link.href);
                }
            });
        })();
        </script>
        <?php
    }

    public function renderCalendar(): void
    {
        if (empty($_GET[self::EMBED_FLAG])) {
            return;
        }

        if ($this->token === '' || empty($this->payload)) {
            wp_die(
                esc_html__('Invalid ERP calendar token.', 'salon-booking-system'),
                '',
                array('response' => 403)
            );
        }

        if (!current_user_can('manage_salon') && !current_user_can('manage_options')) {
            wp_die(
                esc_html__('Sorry, you are not allowed access to this page.', 'salon-booking-system'),
                '',
                array('response' => 403)
            );
        }

        $this->sendFrameHeaders();
        nocache_headers();
        status_header(200);

        $calendar = new SLN_Admin_Calendar($this->plugin);
        $calendar->enqueueAssets();

        ?><!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e('Salon Calendar', 'salon-booking-system'); ?></title>
            <?php wp_head(); ?>
            <style>
                html,
                body {
                    margin: 0;
                    min-height: 100%;
                    background: #fff;
                }
                body {
                    padding: 12px;
                }
                #wpadminbar,
                .notice,
                .update-nag {
                    display: none !important;
                }
                html.wp-toolbar {
                    padding-top: 0 !important;
                }
            </style>
        </head>
        <body class="sln-erp-calendar-embed">
            <?php echo $this->plugin->loadView('admin/calendar', array()); ?>
            <?php wp_footer(); ?>
        </body>
        </html><?php
        exit;
    }

    public function sendFrameHeaders(): void
    {
        if ($this->token === '') {
            return;
        }

        header_remove('X-Frame-Options');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow', false);

        $frameAncestors = $this->getFrameAncestors();
        if ($frameAncestors !== '') {
            header('Content-Security-Policy: frame-ancestors ' . $frameAncestors);
        }
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return is_string($decoded) ? $decoded : '';
    }

    private function getFrameAncestors(): string
    {
        if (defined('SLN_ERP_ALLOWED_FRAME_ANCESTORS')) {
            return trim((string)SLN_ERP_ALLOWED_FRAME_ANCESTORS);
        }

        return trim((string)get_option('sln_erp_allowed_frame_ancestors', ''));
    }

    private function getPayloadUser(array $payload)
    {
        if (!empty($payload['user_id'])) {
            return get_user_by('id', absint($payload['user_id']));
        }

        if (!empty($payload['user'])) {
            return get_user_by('login', sanitize_user($payload['user']));
        }

        return false;
    }

    private function getSecret(): string
    {
        if (defined('SLN_ERP_EMBED_SECRET')) {
            return (string)SLN_ERP_EMBED_SECRET;
        }

        return (string)get_option('sln_erp_embed_secret', '');
    }

    private function getTokenFromRequest(): string
    {
        if (isset($_REQUEST[self::TOKEN_PARAM])) {
            return sanitize_text_field(wp_unslash($_REQUEST[self::TOKEN_PARAM]));
        }

        if (isset($_SERVER['HTTP_X_SLN_ERP_TOKEN'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_X_SLN_ERP_TOKEN']));
        }

        return '';
    }

    private function validateToken(string $token): array
    {
        $secret = $this->getSecret();
        if ($secret === '') {
            return array();
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return array();
        }

        $expected = hash_hmac('sha256', $parts[0], $secret);
        if (!hash_equals($expected, $parts[1])) {
            return array();
        }

        $payload = json_decode($this->base64UrlDecode($parts[0]), true);
        if (!is_array($payload)) {
            return array();
        }

        if (($payload['aud'] ?? '') !== self::AUDIENCE) {
            return array();
        }

        $now = time();
        if (empty($payload['exp']) || (int)$payload['exp'] < $now) {
            return array();
        }

        if (!empty($payload['iat']) && (int)$payload['iat'] > $now + 60) {
            return array();
        }

        return $payload;
    }
}
