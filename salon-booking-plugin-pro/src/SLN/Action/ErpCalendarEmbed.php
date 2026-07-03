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
        add_action('admin_init', array($this, 'sendFrameHeaders'), PHP_INT_MAX);
        add_action('admin_head', array($this, 'sendFrameHeaders'), 0);
        add_action('login_init', array($this, 'sendFrameHeaders'), 0);
        add_action('login_init', array($this, 'sendFrameHeaders'), PHP_INT_MAX);
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

        return $this->appendEmbedArgsToUrl((string)$url, $this->token);
    }

    public function appendTokenToRedirect($location, $postId)
    {
        $token = $this->getTokenFromRequest();
        if ($token === '' || empty($this->validateToken($token))) {
            return $location;
        }

        return $this->appendEmbedArgsToUrl((string)$location, $token);
    }

    private function appendEmbedArgsToUrl(string $url, string $token): string
    {
        if ($url === '' || $url === '#') {
            return $url;
        }

        $fragment = '';
        $hashPosition = strpos($url, '#');
        if ($hashPosition !== false) {
            $fragment = substr($url, $hashPosition);
            $url = substr($url, 0, $hashPosition);
        }

        $args = array();
        if (strpos($url, self::TOKEN_PARAM . '=') === false) {
            $args[] = self::TOKEN_PARAM . '=' . rawurlencode($token);
        }
        if (strpos($url, 'sln_erp_embed=') === false) {
            $args[] = 'sln_erp_embed=1';
        }
        if (empty($args)) {
            return $url . $fragment;
        }

        return $url . $this->querySeparator($url) . implode('&', $args) . $fragment;
    }

    private function querySeparator(string $url): string
    {
        if (strpos($url, '?') === false) {
            return '?';
        }

        $lastCharacter = substr($url, -1);
        return $lastCharacter === '?' || $lastCharacter === '&' ? '' : '&';
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

        if (!user_can($user, 'manage_salon') && !user_can($user, 'manage_options')) {
            return;
        }

        $this->payload = $payload;
        $this->token = $token;

        wp_set_current_user($user->ID);
        $this->primeAuthCookies($user, (int)($payload['exp'] ?? (time() + HOUR_IN_SECONDS)));

        $this->disableFrameOptionsHeader();
        $this->sendFrameHeaders();
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

                if (!isSameOrigin(url)) {
                    return url;
                }

                var value = String(url);
                var hash = '';
                var hashIndex = value.indexOf('#');
                if (hashIndex !== -1) {
                    hash = value.substring(hashIndex);
                    value = value.substring(0, hashIndex);
                }

                var args = [];
                if (!hasQueryParam(value, tokenParam)) {
                    args.push(tokenParam + '=' + encodeURIComponent(token));
                }
                if (!hasQueryParam(value, 'sln_erp_embed')) {
                    args.push('sln_erp_embed=1');
                }
                if (args.length === 0) {
                    return url;
                }

                return value + querySeparator(value) + args.join('&') + hash;
            }

            function querySeparator(url) {
                if (String(url).indexOf('?') === -1) {
                    return '?';
                }

                return /[?&]$/.test(String(url)) ? '' : '&';
            }

            function hasQueryParam(url, name) {
                var escaped = String(name).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return (new RegExp('(?:[?&])' + escaped + '=')).test(String(url));
            }

            function hasToken(url) {
                return hasQueryParam(url, tokenParam);
            }

            function injectEmbedStyles() {
                if (document.getElementById('sln-erp-embed-style')) {
                    return;
                }

                var style = document.createElement('style');
                style.id = 'sln-erp-embed-style';
                style.textContent = '.sln-bootstrap.sln-calendar-plugin-update-notice--wrapper{display:none!important;}';
                (document.head || document.documentElement).appendChild(style);
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
                [
                    'data-src',
                    'data-url',
                    'data-href',
                    'data-src-template-edit-booking',
                    'data-src-template-new-booking',
                    'data-src-template-duplicate-booking',
                    'data-src-template-duplicate_clone-booking'
                ].forEach(function(attribute) {
                    if (root.matches && root.matches('[' + attribute + ']')) {
                        var ownValue = root.getAttribute(attribute);
                        if (ownValue && isSameOriginAdmin(ownValue)) {
                            setDataUrl(root, attribute, appendToken(ownValue));
                        }
                    }

                    root.querySelectorAll('[' + attribute + ']').forEach(function(element) {
                        var value = element.getAttribute(attribute);
                        if (value && isSameOriginAdmin(value)) {
                            setDataUrl(element, attribute, appendToken(value));
                        }
                    });
                });
            }

            function setDataUrl(element, attribute, value) {
                element.setAttribute(attribute, value);

                if (!window.jQuery || attribute.indexOf('data-') !== 0) {
                    return;
                }

                var dataName = attribute.substring(5);
                var camelName = dataName.replace(/-([a-z])/g, function(all, letter) {
                    return letter.toUpperCase();
                });

                window.jQuery(element).data(dataName, value);
                window.jQuery(element).data(camelName, value);
            }

            function processFrame(frame) {
                if (!frame || !frame.getAttribute) {
                    return;
                }

                var src = frame.getAttribute('src');
                if (src && isSameOriginAdmin(src) && !hasToken(src)) {
                    frame.setAttribute('src', appendToken(src));
                }
            }

            function process(root) {
                root = root || document;
                injectEmbedStyles();
                if (root.matches && root.matches('form')) {
                    processForm(root);
                }
                if (root.matches && root.matches('a[href]')) {
                    processLink(root);
                }
                if (root.matches && root.matches('iframe[src]')) {
                    processFrame(root);
                }
                root.querySelectorAll('form').forEach(processForm);
                root.querySelectorAll('a[href]').forEach(processLink);
                root.querySelectorAll('iframe[src]').forEach(processFrame);
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

            if (window.jQuery) {
                window.jQuery(document).on('sln.iframeEditor.ready', function(event, bookingId, bookingDate, srcTemplate, editorLink) {
                    if (!editorLink || !isSameOriginAdmin(editorLink)) {
                        return;
                    }

                    var editor = document.querySelector('.booking-editor');
                    if (!editor) {
                        return;
                    }

                    var nextUrl = appendToken(editorLink);
                    setDataUrl(editor, 'data-' + srcTemplate, nextUrl);

                    window.setTimeout(function() {
                        var currentSrc = editor.getAttribute('src') || '';
                        if (!currentSrc || currentSrc === editorLink || (isSameOriginAdmin(currentSrc) && !hasToken(currentSrc))) {
                            editor.setAttribute('src', nextUrl);
                        }
                    }, 0);
                });
            }

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
                .update-nag,
                .sln-bootstrap.sln-calendar-plugin-update-notice--wrapper {
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
        $token = $this->token !== '' ? $this->token : $this->getTokenFromRequest();
        if ($token === '' || empty($this->validateToken($token))) {
            return;
        }

        $this->disableFrameOptionsHeader();
        header_remove('X-Frame-Options');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow', false);

        $frameAncestors = $this->getFrameAncestors();
        if ($frameAncestors !== '') {
            header('Content-Security-Policy: frame-ancestors ' . $frameAncestors);
        }
    }

    private function disableFrameOptionsHeader(): void
    {
        remove_action('admin_init', 'send_frame_options_header');
        remove_action('login_init', 'send_frame_options_header');
    }

    private function primeAuthCookies(WP_User $user, int $expiration): void
    {
        if (!function_exists('wp_generate_auth_cookie')) {
            return;
        }

        $expiration = max($expiration, time() + MINUTE_IN_SECONDS);
        $sessionToken = class_exists('WP_Session_Tokens')
            ? WP_Session_Tokens::get_instance($user->ID)->create($expiration)
            : '';

        if (defined('AUTH_COOKIE')) {
            $_COOKIE[AUTH_COOKIE] = wp_generate_auth_cookie($user->ID, $expiration, 'auth', $sessionToken);
        }

        if (defined('SECURE_AUTH_COOKIE')) {
            $_COOKIE[SECURE_AUTH_COOKIE] = wp_generate_auth_cookie($user->ID, $expiration, 'secure_auth', $sessionToken);
        }

        if (defined('LOGGED_IN_COOKIE')) {
            $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($user->ID, $expiration, 'logged_in', $sessionToken);
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
        $ancestors = '';
        if (defined('SLN_ERP_ALLOWED_FRAME_ANCESTORS')) {
            $ancestors = trim((string)SLN_ERP_ALLOWED_FRAME_ANCESTORS);
        } else {
            $ancestors = trim((string)get_option('sln_erp_allowed_frame_ancestors', ''));
        }

        return $ancestors !== '' && strpos($ancestors, "'self'") === false ? "'self' " . $ancestors : $ancestors;
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
