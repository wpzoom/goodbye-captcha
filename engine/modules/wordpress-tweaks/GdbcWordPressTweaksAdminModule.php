<?php
/**
 * Copyright (C) 2015 Mihai Chelaru
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

defined( 'ABSPATH' ) || exit;

class GdbcWordPressTweaksAdminModule extends GdbcBaseAdminModule
{

	CONST WORDPRESS_REMOVE_RSD_HEADER           = 'HideRSDHeader';
	CONST WORDPRESS_REMOVE_WLW_HEADER           = 'HideWLWHeader';

	CONST WORDPRESS_HIDE_VERSION                = 'HideVersion';

	CONST WORDPRESS_XML_RPC_FULLY_DISABLED      = 'XmlRpcFullyDisabled';
	CONST WORDPRESS_XML_RPC_PINGBACKS_DISABLED   = 'XmlRpcPingDisabled';

	CONST WORDPRESS_COMMENTS_TRACKBACKS_DISABLED   = 'CommentsTrackBackDisabled';

	CONST WORDPRESS_COMMENTS_FORM_WEBSITE_FIELD = 'CommentsWebsiteFieldHidden';
	CONST WORDPRESS_COMMENTS_FORM_NOTES_FIELDS  = 'CommentsNoteHidden'; // hides allowed tags and text like "Your email address will not be published"

	CONST WORDPRESS_CUSTOM_LOGIN_SLUG           = 'CustomLoginSlug';
	CONST WORDPRESS_REST_API_VISITORS           = 'RestApiLoggedInOnly';
	CONST WORDPRESS_REST_API_ALLOWED_NAMESPACES = 'RestApiAllowedNamespaces';
	CONST WORDPRESS_APP_PASSWORDS_DISABLED      = 'AppPasswordsDisabled';
	CONST WORDPRESS_UPLOADS_PHP_BLOCKED         = 'UploadsPhpBlocked';

	CONST DEFAULT_REST_API_ALLOWED_NAMESPACES   = 'oembed/1.0, contact-form-7, wc/store, jetpack, litespeed, wordfence';

	// Login slugs that would clash with WordPress' own paths.
	private static $arrReservedLoginSlugs = array('wp-admin', 'wp-login', 'wp-login-php', 'login', 'admin', 'dashboard', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'xmlrpc', 'xmlrpc-php');

	protected function __construct()
	{
		parent::__construct();
	}

	public function getDefaultOptions()
	{
		static $arrDefaultSettingOptions = null;
		if(null !== $arrDefaultSettingOptions)
			return $arrDefaultSettingOptions;

		$arrDefaultSettingOptions = array(

			self::WORDPRESS_CUSTOM_LOGIN_SLUG => array(
				'Id'          => 9,
				'Value'       => NULL,
				'LabelText'   => __('Custom Login URL', 'goodbye-captcha'),
				'Description' => $this->getCustomLoginSlugDescription(),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_TEXT,
			),

			self::WORDPRESS_REST_API_VISITORS => array(
				'Id'          => 10,
				'Value'       => NULL,
				'LabelText'   => __('Restrict the REST API', 'goodbye-captcha'),
				'Description' => __('Only logged-in users can use the REST API, except for the plugins listed below. Stops bots from reading your users, posts and settings through it.', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::WORDPRESS_REST_API_ALLOWED_NAMESPACES => array(
				'Id'          => 11,
				'Value'       => self::DEFAULT_REST_API_ALLOWED_NAMESPACES,
				'LabelText'   => __('REST API Open to Visitors', 'goodbye-captcha'),
				'Description' => __('REST API namespaces visitors can still use, separated by commas. If a form or shop feature stops working for visitors, add its plugin\'s namespace here, for example the part after /wp-json/ in its requests.', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_TEXT,
				'InputClass'  => 'large-text',
			),

			self::WORDPRESS_APP_PASSWORDS_DISABLED => array(
				'Id'          => 12,
				'Value'       => NULL,
				'LabelText'   => __('Disable Application Passwords', 'goodbye-captcha'),
				'Description' => __('Turns off application passwords, which let apps and scripts log in to the REST API and XML-RPC. Leave this off if you use the WordPress mobile app or connect external services that way.', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::WORDPRESS_UPLOADS_PHP_BLOCKED => array(
				'Id'          => 13,
				'Value'       => NULL,
				'LabelText'   => __('Block PHP in Uploads', 'goodbye-captcha'),
				'Description' => __('Stops PHP files in the uploads folder from running, so a malicious file uploaded through a vulnerable plugin cannot be executed. Adds rules to the uploads folder\'s .htaccess file, which Apache and LiteSpeed servers use. On Nginx, ask your host to deny PHP in /wp-content/uploads/.', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::WORDPRESS_HIDE_VERSION => array(
				'Id'          => 1,
				'Value'       => NULL,
				'LabelText'   => __('Hide WordPress Version', 'goodbye-captcha'),
				'Description' => __('This will hide your WordPress version information from potential attackers', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::WORDPRESS_REMOVE_RSD_HEADER => array(
				'Id'          => 2,
				'Value'       => NULL,
				'LabelText'   => __('Remove RSD Header', 'goodbye-captcha'),
				'Description' => __('Removes the RSD (Really Simple Discovery) header. The header is useful only if your blog is integrated with external services such as Flickr', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::WORDPRESS_REMOVE_WLW_HEADER => array(
				'Id'          => 3,
				'Value'       => NULL,
				'LabelText'   => __('Remove WLW Header', 'goodbye-captcha'),
				'Description' => __('Removes the WLW (Windows Live Writer Header) header. The header is useful only if you use Windows Live Writer', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::WORDPRESS_XML_RPC_FULLY_DISABLED => array(
				'Id'          => 4,
				'Value'       => NULL,
				'LabelText'   => __('Completely Disable XML-RPC', 'goodbye-captcha'),
				'Description' => __('<b>It seamlessly works with Jetpack plugin</b>. Do not enable if there is other system such as Android/IOS app that uses your XML-RPC service.', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::WORDPRESS_XML_RPC_PINGBACKS_DISABLED => array(
				'Id'          => 5,
				'Value'       => NULL,
				'LabelText'   => __('Disable XML-RPC Pingbacks', 'goodbye-captcha'),
				'Description' => __('Removes the Pingbacks methods from the XML-RPC service. This will also remove the X-Pingback header', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),


//			self::WORDPRESS_COMMENTS_TRACKBACKS_DISABLED => array(
//				'Id'          => 6,
//				'Value'       => NULL,
//				'LabelText'   => __('Disable Comments Trackbacks', 'goodbye-captcha'),
//				'Description' => __('Blocks all Comments Trackbacks', 'goodbye-captcha'),
//				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
//			),


			self::WORDPRESS_COMMENTS_FORM_WEBSITE_FIELD => array(
				'Id'          => 7,
				'Value'       => NULL,
				'LabelText'   => __('Hide Comments Website Field', 'goodbye-captcha'),
				'Description' => __('Hides Comments Form Website Url', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),


			self::WORDPRESS_COMMENTS_FORM_NOTES_FIELDS => array(
				'Id'          => 8,
				'Value'       => NULL,
				'LabelText'   => __('Hide Comments Form Notes Fields', 'goodbye-captcha'),
				'Description' => __('Hides form allowed tags and text like "Your email address will not be published"', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

		);

		// The custom login URL is for single sites; a network has a login page per site.
		if(is_multisite())
			unset($arrDefaultSettingOptions[self::WORDPRESS_CUSTOM_LOGIN_SLUG]);

		return $arrDefaultSettingOptions;

	}

	public function validateModuleSettingsFields($arrSettingOptions)
	{
		$arrSettingOptions = $this->sanitizeSubmittedOptions($arrSettingOptions);

		if(isset($arrSettingOptions[self::WORDPRESS_CUSTOM_LOGIN_SLUG]))
		{
			$arrSettingOptions[self::WORDPRESS_CUSTOM_LOGIN_SLUG] = $this->validateCustomLoginSlug($arrSettingOptions[self::WORDPRESS_CUSTOM_LOGIN_SLUG]);
		}

		if(isset($arrSettingOptions[self::WORDPRESS_REST_API_ALLOWED_NAMESPACES]))
		{
			$arrNamespaces = array_filter(array_unique(array_map(function($namespace) {
				return trim(preg_replace('#[^a-z0-9_./-]#', '', strtolower($namespace)), '/');
			}, explode(',', (string)$arrSettingOptions[self::WORDPRESS_REST_API_ALLOWED_NAMESPACES]))));

			$arrSettingOptions[self::WORDPRESS_REST_API_ALLOWED_NAMESPACES] = implode(', ', $arrNamespaces);
		}

		$blockUploadsPhp = !empty($arrSettingOptions[self::WORDPRESS_UPLOADS_PHP_BLOCKED]);
		if($blockUploadsPhp !== (bool)$this->getOption(self::WORDPRESS_UPLOADS_PHP_BLOCKED) || $blockUploadsPhp)
		{
			if(!GdbcHardening::writeUploadsHtaccess($blockUploadsPhp) && $blockUploadsPhp)
			{
				unset($arrSettingOptions[self::WORDPRESS_UPLOADS_PHP_BLOCKED]);
				$this->registerErrorMessage(__('PHP in uploads could not be blocked: the .htaccess file in the uploads folder is not writable.', 'goodbye-captcha'));
				return $arrSettingOptions;
			}
		}

		$this->registerSuccessMessage(__('Your changes were successfully saved!', 'goodbye-captcha'));

		return $arrSettingOptions;
	}

	/**
	 * Checks a new login slug. An unusable one keeps the current slug and shows why.
	 *
	 * @param string $slug Submitted slug.
	 * @return string|null
	 */
	private function validateCustomLoginSlug($slug)
	{
		$currentSlug = $this->getOption(self::WORDPRESS_CUSTOM_LOGIN_SLUG);
		$slug        = sanitize_title((string)$slug);

		if('' === $slug)
			return null;

		if(in_array($slug, self::$arrReservedLoginSlugs, true) || strlen($slug) < 4)
		{
			$this->registerErrorMessage(__('That login URL cannot be used. Choose a word of at least 4 letters that WordPress does not use itself.', 'goodbye-captcha'));
			return $currentSlug;
		}

		if(get_page_by_path($slug, OBJECT, get_post_types(array('public' => true))))
		{
			$this->registerErrorMessage(__('A page or post already uses that address. Choose a different login URL.', 'goodbye-captcha'));
			return $currentSlug;
		}

		if($slug !== $currentSlug)
		{
			GdbcHardening::emailNewLoginUrl($slug);
		}

		return $slug;
	}

	/**
	 * The custom login URL description, with the current address when one is set.
	 *
	 * @return string
	 */
	private function getCustomLoginSlugDescription()
	{
		$description = __('Moves the login page to an address only you know, such as "my-login". wp-login.php and, for visitors who are not logged in, wp-admin show a "not found" page. Leave empty to keep the standard login page.', 'goodbye-captcha');

		$loginUrl = GdbcHardening::getCustomLoginUrl();
		if(null !== $loginUrl)
		{
			/* translators: %s: the login page address. */
			$description .= '<br /><strong>' . sprintf(esc_html__('Your login page: %s', 'goodbye-captcha'), '<code>' . esc_html($loginUrl) . '</code>') . '</strong>';
		}

		/* translators: %s: PHP constant. */
		$description .= '<br />' . sprintf(esc_html__('Locked out? Add %s to wp-config.php to use wp-login.php again.', 'goodbye-captcha'), '<code>define(\'' . GdbcHardening::DISABLE_LOGIN_URL_CONSTANT . '\', true);</code>');

		return $description;
	}


//	public  function renderModuleSettingsSectionHeader(array $arrSectionInfo)
//	{
//		echo '<h3>' . esc_html__('Tweaking WordPress', 'goodbye-captcha') . '</h3><hr />';
//	}


	public function getFormattedBlockedContent(GdbcAttemptEntity $attemptEntity)
	{
		return null;
	}

	public static function getInstance()
	{
		static $adminInstance = null;
		return null !== $adminInstance ? $adminInstance : $adminInstance = new self();
	}

}