<?php
/**
 * WordPress hardening from the WordPress screen: a custom login URL, a REST API
 * closed to visitors, no application passwords, and no PHP in the uploads folder.
 *
 * Runs on every request, the dashboard included, so it is started from
 * GoodByeCaptcha::startRunning() rather than from a public module.
 *
 * @package WPBruiser
 */

defined( 'ABSPATH' ) || exit;

final class GdbcHardening
{
	/** Defined as true in wp-config.php, brings back wp-login.php. */
	const DISABLE_LOGIN_URL_CONSTANT = 'WPBRUISER_DISABLE_LOGIN_URL';

	const UPLOADS_HTACCESS_MARKER = 'WPBruiser';

	/** @var string|null Custom login slug in use on this request. */
	private static $loginSlug = null;

	/** @var bool Whether this request is for the custom login URL. */
	private static $isCustomLoginRequest = false;

	/**
	 * Hooks whatever is switched on.
	 */
	public static function init()
	{
		$module = self::getModule();
		if(null === $module)
			return;

		if($module->getOption(GdbcWordPressTweaksAdminModule::WORDPRESS_APP_PASSWORDS_DISABLED))
		{
			add_filter('wp_is_application_passwords_available', '__return_false');
		}

		if($module->getOption(GdbcWordPressTweaksAdminModule::WORDPRESS_REST_API_VISITORS))
		{
			add_filter('rest_authentication_errors', array(__CLASS__, 'restrictRestApi'), 99);
		}

		$loginSlug = $module->getOption(GdbcWordPressTweaksAdminModule::WORDPRESS_CUSTOM_LOGIN_SLUG);
		if(!empty($loginSlug) && is_string($loginSlug) && !is_multisite() && !(defined(self::DISABLE_LOGIN_URL_CONSTANT) && constant(self::DISABLE_LOGIN_URL_CONSTANT)))
		{
			self::setUpCustomLoginUrl($loginSlug);
		}
	}

	/**
	 * `rest_authentication_errors`: refuses visitors who are not logged in, except on
	 * the namespaces left open and from addresses on the allow list.
	 *
	 * @param WP_Error|null|true $result Result so far.
	 * @return WP_Error|null|true
	 */
	public static function restrictRestApi($result)
	{
		if(!empty($result) || is_user_logged_in())
			return $result;

		$route = trim(self::getRestRoute(), '/');
		foreach(self::getRestAllowedNamespaces() as $namespace)
		{
			if($route === $namespace || 0 === strpos($route, $namespace . '/'))
				return $result;
		}

		if(GdbcIPUtils::isClientIpWhiteListed())
			return $result;

		return new WP_Error(
			'rest_login_required',
			__('The REST API on this site is only available to logged-in users.', 'goodbye-captcha'),
			array('status' => rest_authorization_required_code())
		);
	}

	/**
	 * The custom login page address, or null when none is set.
	 *
	 * @param string|null $slug Slug to build it for; the saved one by default.
	 * @return string|null
	 */
	public static function getCustomLoginUrl($slug = null)
	{
		if(null === $slug)
		{
			$module = self::getModule();
			$slug   = null !== $module ? $module->getOption(GdbcWordPressTweaksAdminModule::WORDPRESS_CUSTOM_LOGIN_SLUG) : null;
		}

		if(empty($slug) || !is_string($slug) || is_multisite())
			return null;

		return self::buildLoginUrl($slug);
	}

	/**
	 * Emails the new login address to the site admin, so it can be found again.
	 *
	 * @param string $slug New login slug.
	 */
	public static function emailNewLoginUrl($slug)
	{
		static $isSent = false;
		if($isSent)
			return;

		$isSent = true;

		$loginUrl = self::buildLoginUrl($slug);

		/* translators: %s: site name. */
		$subject = sprintf(__('[%s] Your new login address', 'goodbye-captcha'), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));

		/* translators: 1: login page address, 2: PHP constant. */
		$message = sprintf(__("WPBruiser moved the login page of your site. Log in here from now on:\n\n%1\$s\n\nwp-login.php no longer works. If you lose this address, add this line to wp-config.php to use wp-login.php again:\n\ndefine('%2\$s', true);", 'goodbye-captcha'), $loginUrl, self::DISABLE_LOGIN_URL_CONSTANT);

		wp_mail(get_option('admin_email'), $subject, $message);
	}

	/**
	 * Adds or removes the rules that stop PHP from running in the uploads folder.
	 *
	 * @param bool $block Whether to block PHP.
	 * @return bool Whether the .htaccess file was updated.
	 */
	public static function writeUploadsHtaccess($block)
	{
		$arrUploads = wp_upload_dir(null, false);
		if(!empty($arrUploads['error']) || empty($arrUploads['basedir']) || !is_dir($arrUploads['basedir']))
			return false;

		$htaccessFile = trailingslashit($arrUploads['basedir']) . '.htaccess';
		if(!$block)
			return self::removeUploadsHtaccessBlock($htaccessFile);

		if(!function_exists('insert_with_markers'))
			require_once ABSPATH . 'wp-admin/includes/misc.php';

		$arrRules = array(
			'<FilesMatch "\.(?i:php[0-9]?|phtml|phar|pht)$">',
			'	<IfModule mod_authz_core.c>',
			'		Require all denied',
			'	</IfModule>',
			'	<IfModule !mod_authz_core.c>',
			'		Order allow,deny',
			'		Deny from all',
			'	</IfModule>',
			'</FilesMatch>',
		);

		// insert_with_markers() creates the file when it is missing.
		if(!file_exists($htaccessFile) && !wp_is_writable($arrUploads['basedir']))
			return false;

		return (bool)insert_with_markers($htaccessFile, self::UPLOADS_HTACCESS_MARKER, $arrRules);
	}

	/**
	 * Takes the WPBruiser block out of the uploads .htaccess file, and deletes the
	 * file when nothing else is left in it.
	 *
	 * @param string $htaccessFile Path to the file.
	 * @return bool
	 */
	private static function removeUploadsHtaccessBlock($htaccessFile)
	{
		if(!file_exists($htaccessFile))
			return true;

		$contents  = (string)file_get_contents($htaccessFile); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$marker    = preg_quote(self::UPLOADS_HTACCESS_MARKER, '/');
		$remaining = preg_replace('/\R?# BEGIN ' . $marker . '\R.*?# END ' . $marker . '\R?/s', "\n", $contents);

		if($remaining === $contents)
			return true;

		if('' === trim($remaining))
			return wp_delete_file($htaccessFile) || !file_exists($htaccessFile);

		return false !== file_put_contents($htaccessFile, ltrim($remaining)); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Called on deactivation: takes the uploads rules out again.
	 */
	public static function removeUploadsHtaccessRules()
	{
		self::writeUploadsHtaccess(false);
	}

	/**
	 * `site_url` and `network_site_url`: points links to wp-login.php at the custom
	 * login address.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function filterLoginUrl($url)
	{
		return self::rewriteLoginUrl($url);
	}

	/**
	 * `wp_redirect`: WordPress redirects to wp-login.php itself after logging out and
	 * in other places, sometimes with a relative URL.
	 *
	 * @param string $location Redirect address.
	 * @return string
	 */
	public static function filterRedirect($location)
	{
		return self::rewriteLoginUrl($location);
	}

	/**
	 * `wp_loaded`: serves the login page on its custom address and hides wp-login.php
	 * and, from visitors who are not logged in, the dashboard.
	 */
	public static function routeLoginRequests()
	{
		global $pagenow;

		if(self::$isCustomLoginRequest)
		{
			self::serveLoginPage();
		}

		if('wp-login.php' === $pagenow)
		{
			// Password-protected posts post their password here.
			if(isset($_GET['action']) && 'postpass' === $_GET['action']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;

			self::renderNotFound();
		}

		if(is_admin() && !is_user_logged_in() && !wp_doing_ajax() && !wp_doing_cron() && 'admin-post.php' !== $pagenow)
		{
			// The dashboard would redirect to the login page and give its address away.
			wp_safe_redirect(home_url('/404'));
			exit;
		}
	}

	private static function setUpCustomLoginUrl($slug)
	{
		self::$loginSlug            = $slug;
		self::$isCustomLoginRequest = self::isCustomLoginRequest($slug);

		add_filter('site_url', array(__CLASS__, 'filterLoginUrl'), 10, 1);
		add_filter('network_site_url', array(__CLASS__, 'filterLoginUrl'), 10, 1);
		add_filter('wp_redirect', array(__CLASS__, 'filterRedirect'), 10, 1);

		// WordPress sends /login and /admin to the login page.
		remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);

		add_action('wp_loaded', array(__CLASS__, 'routeLoginRequests'), 1);
	}

	private static function isCustomLoginRequest($slug)
	{
		if(empty($_SERVER['REQUEST_URI']))
			return false;

		$requestPath = untrailingslashit((string)wp_parse_url(rawurldecode(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH)); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$homePath    = untrailingslashit((string)wp_parse_url(home_url(), PHP_URL_PATH));

		if($requestPath === $homePath . '/' . $slug)
			return true;

		// Without pretty permalinks the address is /?slug.
		return isset($_GET[$slug]) && ($requestPath === $homePath || $requestPath === $homePath . '/index.php'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	private static function serveLoginPage()
	{
		// wp-login.php expects to run in the global scope.
		global $error, $interim_login, $action, $user_login, $pagenow;

		$pagenow = 'wp-login.php';

		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Shows the theme's "not found" page.
	 */
	private static function renderNotFound()
	{
		global $wp_query;

		if(!defined('WP_USE_THEMES'))
			define('WP_USE_THEMES', true);

		wp();

		if(!is_404())
			$wp_query->set_404();

		status_header(404);
		nocache_headers();

		require ABSPATH . WPINC . '/template-loader.php';
		exit;
	}

	private static function rewriteLoginUrl($url)
	{
		if(null === self::$loginSlug || !is_string($url) || false === strpos($url, 'wp-login.php'))
			return $url;

		// Only addresses of this site, absolute or relative.
		$host = wp_parse_url($url, PHP_URL_HOST);
		if(!empty($host) && $host !== wp_parse_url(home_url(), PHP_URL_HOST))
			return $url;

		$path = (string)wp_parse_url($url, PHP_URL_PATH);
		if('wp-login.php' !== basename($path))
			return $url;

		$loginUrl = self::buildLoginUrl(self::$loginSlug, wp_parse_url($url, PHP_URL_SCHEME));
		$query    = wp_parse_url($url, PHP_URL_QUERY);

		return empty($query) ? $loginUrl : $loginUrl . (false === strpos($loginUrl, '?') ? '?' : '&') . $query;
	}

	private static function buildLoginUrl($slug, $scheme = null)
	{
		$homeUrl = home_url('/', in_array($scheme, array('http', 'https'), true) ? $scheme : null);

		if(get_option('permalink_structure'))
			return $homeUrl . user_trailingslashit($slug);

		return $homeUrl . '?' . $slug;
	}

	private static function getRestRoute()
	{
		global $wp;

		if(isset($wp->query_vars['rest_route']))
			return (string)$wp->query_vars['rest_route'];

		return '';
	}

	private static function getRestAllowedNamespaces()
	{
		$namespaces = self::getModule()->getOption(GdbcWordPressTweaksAdminModule::WORDPRESS_REST_API_ALLOWED_NAMESPACES);
		if(null === $namespaces)
			$namespaces = GdbcWordPressTweaksAdminModule::DEFAULT_REST_API_ALLOWED_NAMESPACES;

		return array_filter(array_map(function($namespace) {
			return trim(trim($namespace), '/');
		}, explode(',', (string)$namespaces)));
	}

	/**
	 * @return GdbcWordPressTweaksAdminModule|null
	 */
	private static function getModule()
	{
		return GdbcModulesController::getAdminModuleInstance(GdbcModulesController::MODULE_WORDPRESS_TWEAKS);
	}

	private function __construct()
	{}
}
