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
        add_action('admin_init', array($this, 'sendFrameHeaders'), 0);
        add_action('template_redirect', array($this, 'renderCalendar'), 0);
        add_filter('admin_url', array($this, 'appendTokenToAdminUrl'), 10, 4);
        add_filter('redirect_post_location', array($this, 'appendTokenToRedirect'), 20, 2);
        add_action('admin_footer', array($this, 'printEmbedTokenScript'), 99);
    }

    public function appendTokenToAdminUrl($url, $path, $blogId, $scheme)
    {
        if ($this->token === '') {
            return $url;
        }

        $basename = basename((string)parse_url($url, PHP_URL_PATH));
        $allowed = array('admin-ajax.php', 'admin.php', 'edit.php', 'post.php', 'post-new.php', 'profile.php', 'user-edit.php', 'users.php');
        if (!in_array($basename, $allowed, true)) {
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
        $this->printEmbedTokenScript();
    }

    public function printEmbedTokenScript(): void
    {
        $token = $this->getTokenFromRequest();
        if ($token === '' || empty($this->validateToken($token))) {
            return;
        }
        ?>
        <script>
        (function() {
            var token = <?php echo wp_json_encode($token); ?>;
            var tokenParam = '<?php echo esc_js(self::TOKEN_PARAM); ?>';

            function appendToken(url) {
                if (!url || url === '#') {
                    return url;
                }

                try {
                    var next = new URL(url || window.location.href, window.location.href);
                    if (next.origin !== window.location.origin) {
                        return url;
                    }
                    next.searchParams.set(tokenParam, token);
                    next.searchParams.set('sln_erp_embed', '1');
                    return next.toString();
                } catch (error) {
                    if (String(url).indexOf(tokenParam + '=') !== -1) {
                        return url;
                    }

                    return url + (String(url).indexOf('?') === -1 ? '?' : '&')
                        + tokenParam + '=' + encodeURIComponent(token)
                        + '&sln_erp_embed=1';
                }
            }

            function isSameOriginAdmin(url) {
                try {
                    var next = new URL(url || '', window.location.href);
                    return next.origin === window.location.origin && next.pathname.indexOf('/wp-admin/') !== -1;
                } catch (error) {
                    return String(url).indexOf('/wp-admin/') !== -1;
                }
            }

            function isSameOrigin(url) {
                try {
                    return (new URL(url || '', window.location.href)).origin === window.location.origin;
                } catch (error) {
                    return false;
                }
            }

            function isCustomerLink(link) {
                if (!link || !link.href) {
                    return false;
                }

                if (link.classList && link.classList.contains('sln-icon--customerurl')) {
                    return true;
                }

                try {
                    var next = new URL(link.href, window.location.href);
                    var page = next.searchParams.get('page') || '';
                    var path = next.pathname.split('/').pop();
                    return page === 'salon-customers'
                        || path === 'user-edit.php'
                        || path === 'profile.php'
                        || path === 'users.php';
                } catch (error) {
                    return false;
                }
            }

            function blockCustomerLink(link) {
                link.href = '#';
                link.removeAttribute('target');
                link.setAttribute('aria-disabled', 'true');
                link.dataset.slnErpBlocked = '1';
                if (link.dataset.slnErpBlockedHandler === '1') {
                    return;
                }

                link.dataset.slnErpBlockedHandler = '1';
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                }, true);
            }

            function processLink(link) {
                if (!link || !link.href) {
                    return;
                }

                if (isCustomerLink(link)) {
                    blockCustomerLink(link);
                    return;
                }

                if (isSameOriginAdmin(link.href)) {
                    link.href = appendToken(link.href);
                    link.removeAttribute('target');
                }
            }

            function processForm(form) {
                if (form && form.action && isSameOriginAdmin(form.action)) {
                    form.action = appendToken(form.action);
                }
            }

            function processDataUrls(root) {
                ['data-src', 'data-url', 'data-href', 'data-src-template-edit-booking'].forEach(function(attribute) {
                    if (root.matches && root.matches('[' + attribute + ']')) {
                        var ownValue = root.getAttribute(attribute);
                        if (ownValue && isSameOriginAdmin(ownValue)) {
                            root.setAttribute(attribute, appendToken(ownValue));
                        }
                    }

                    root.querySelectorAll('[' + attribute + ']').forEach(function(element) {
                        var value = element.getAttribute(attribute);
                        if (value && isSameOriginAdmin(value)) {
                            element.setAttribute(attribute, appendToken(value));
                        }
                    });
                });
            }

            function process(root) {
                root = root || document;
                if (root.matches && root.matches('form')) {
                    processForm(root);
                }
                if (root.matches && root.matches('a[href]')) {
                    processLink(root);
                }
                root.querySelectorAll('form').forEach(processForm);
                root.querySelectorAll('a[href]').forEach(processLink);
                processDataUrls(root);

                if (window.ajaxurl) {
                    window.ajaxurl = appendToken(window.ajaxurl);
                }
            }

            document.addEventListener('click', function(event) {
                var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
                if (!link) {
                    return;
                }

                processLink(link);
                if (link.dataset.slnErpBlocked === '1') {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }, true);

            if (window.fetch) {
                var originalFetch = window.fetch;
                window.fetch = function(input, init) {
                    var url = typeof input === 'string' ? input : (input && input.url);
                    if (isSameOrigin(url)) {
                        init = init || {};
                        var headers = new Headers(init.headers || (input && input.headers) || {});
                        headers.set('X-SLN-ERP-TOKEN', token);
                        init.headers = headers;
                    }

                    return originalFetch.call(this, input, init);
                };
            }

            if (window.XMLHttpRequest) {
                var originalOpen = window.XMLHttpRequest.prototype.open;
                var originalSend = window.XMLHttpRequest.prototype.send;

                window.XMLHttpRequest.prototype.open = function(method, url) {
                    this.slnErpEmbedUrl = url;
                    return originalOpen.apply(this, arguments);
                };

                window.XMLHttpRequest.prototype.send = function() {
                    if (isSameOrigin(this.slnErpEmbedUrl)) {
                        this.setRequestHeader('X-SLN-ERP-TOKEN', token);
                    }

                    return originalSend.apply(this, arguments);
                };
            }

            process(document);

            if (window.MutationObserver) {
                new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) {
                                process(node);
                            }
                        });
                    });
                }).observe(document.documentElement, { childList: true, subtree: true });
            }
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
            <?php $this->printEmbedTokenScript(); ?>
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
