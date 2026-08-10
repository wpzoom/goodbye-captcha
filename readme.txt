=== WPBruiser ===
Contributors: mihche
Tags: captcha, antispam, anti-spam, spam, brute force
Requires at least: 6.5
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 3.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

An extremely powerful antispam plugin that blocks spam-bots without annoying captcha images.

== Description ==
**WPBruiser (formerly GoodBye Captcha) is an anti-spam and security plugin based on algorithms that identify spam bots without any annoying and hard to read captcha images.**

WPBruiser completely eliminates spam-bot signups, spam comments, even brute force attacks, the second you install it on your Wordpress website.  It is completely invisible to the end-user - no need to ever fill out a Captcha or other "human-detection" field ever again - and it just works!

Unlike other anti-spam plugins, which detect spam comments and signups after the fact and move them to your spam folder, which you then have to delete - using up not only your website's resources, but your time as well, WPBruiser prevents the bots from leaving spam in the first place. The result is that your site is not only spam free, it's faster and more secure.

In addition, WPBruiser is completely self-contained and does not need to connect to any outside service.  Your logins remain yours, 100%.

WPBruiser fights Brute Force attacks and eliminates spam-bots on comments, signup pages as well as login and password reset pages. At the click of a button, you can decide which forms to protect.

= Summary of WPBruiser features =
* Standard WordPress Login form integration
* Standard WordPress Register form integration
* Standard WordPress Forgot Password form integration
* Standard WordPress Comments form integration
* Ability to set the maximum number of characters for each comment field
* Logging with the ability to enable/disable it
* Automatically Block IP Addresses
* Automatically purge logs older than a certain number of days
* Manually white-list trusted IP Address (IPV4 and IPV6)
* Manually block/unblock IP Addresses (IPV4 and IPV6)
* Properly detects client IP Address when using CloudFlare, Incapsula, Cloudfront, RackSpace, Sucuri CloudProxy, AWS ELB
* Provides statistics, reports, maps and charts with all blocked spam attempts
* No requests to external APIs
* Can be switched to "Test Mode" - for testing
* Compatible with WordPress Multisite - network admin interface ready
* Compatible with cache plugins (WP Super Cache, W3 Total Cache, ZenCache, WP Fastest Cache and others)
* Invisible for end users (works in the background)
* Does not affect page loading times

= Brute Force Protection =
* Automatically detects Brute Force attacks
* Ability to automatically block IP Addresses
* Prevents User Enumeration
* Ability to block most dangerous IP addresses involved in brute force attacks
* Ability to block most dangerous Anonymous Proxy IP addresses including TOR Networks, TOR Nodes and TOR Exit Points
* Ability to Completely Disable XML-RPC service - **it seamlessly works with Jetpack plugin activated**
* Ability to Disable XML-RPC Pingbacks
* Email notifications when a Brute Force Attack is detected



**WPBruiser is also integrated with the following plugins:**

* **Postmatic** (https://wordpress.org/plugins/postmatic)
	WPBruiser offers protection for the entire email commenting system

* **Epoch** (https://wordpress.org/plugins/epoch)
	WPBruiser offers protection for the entire chat and commenting system

* **wpDiscuz** (https://wordpress.org/plugins/wpdiscuz/)
	WPBruiser offers protection for the entire commenting system

* **MailChimp for WordPress** (https://wordpress.org/plugins/mailchimp-for-wp)
	WPBruiser offers protection for all forms the user will create with MailChimp

* **Ultimate Member** (https://wordpress.org/plugins/ultimate-member)
	WPBruiser offers protection for Login, Registration and Reset Password forms

* **Jetpack by WordPress** (https://wordpress.org/plugins/jetpack)
	WPBruiser offers protection for JetPack Contact Form

* **ZM Ajax Login & Register** (https://wordpress.org/plugins/zm-ajax-login-register)
	WPBruiser offers protection for Login and Registration forms

* **Login With Ajax** (https://wordpress.org/plugins/login-with-ajax)
    WPBruiser offers protection for Login, Registration and Lost Password forms

* **WP User Control** (https://wordpress.org/plugins/wp-user-control)
	WPBruiser offers protection for Login, Registration and Lost Password forms

* **PlanSo Forms** (https://wordpress.org/plugins/planso-forms/)
	WPBruiser offers protection for all forms

* **Theme My Login** (https://wordpress.org/plugins/theme-my-login)
	WPBruiser offers protection for Login, Registration and Lost Password forms

* **Seamless Donations** (https://wordpress.org/plugins/seamless-donations)
	WPBruiser offers protection for the donation form


= External services =
This plugin does not connect to any external service. It sends no data off your server: no requests to remote APIs, no telemetry, no update or licensing calls, and no third-party fonts or scripts. All spam detection runs locally, and the IP reputation lists it uses ship inside the plugin.

= Technical support =
If you notice any problems by using this plugin, please notify us and we will investigate and fix the issues. Ideally your request should contain: URL of the website (if your site is public), Php version, WordPress version and all the steps in order to replicate the issue (if you are able to reproduce it somehow)

= Donate =
If you find this plugin useful, please consider making a small [donation](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=XVC3TSGEJQP2U). Thank you

== Installation ==
= Option 1 =
1. Download the zip file from WordPress plugin directory,
2. Unzip and upload all the files to the /wp-content/plugins/goodbye-captcha/ folder from your server,
3. Activate the plugin from WordPress Dashboard

= Option 2 =
1. Download the zip file from WordPress plugin directory,
2. Login into the administration panel,
3. Go to Plugins --> Add New --> Upload,
4. Click Choose File (Browse) and select the downloaded zip file,
5. Activate WPBruiser plugin

= Option 3 =
1. Login into your WordPress site,
2. Choose Plugins --> Add New,
3. Search for WPBruiser,
4. In the results page, click Install Now. (depending on your server, you might need to provide credentials for download),
5. Activate WPBruiser

After installation, a WPBruiser menu item will appear in the Settings section. Click on this in order to view plugin's administration page.

== Frequently Asked Questions ==

= How do I know is working? =
Just switch the plugin to Test Mode and start testing it yourself. The Administrator will receive email notifications.
If something is not working as expected, a warning message will be shown.

= Is WPBruiser safe? =
Yes. The algorithm behind the plugin was fully tested and there is no way a spider or robot can spam your sites.

= What forms can be secured by using WPBruiser WordPress plugin? =
All standard WordPress forms(Login, Register, Comment and Forgot Password) can be secured by using WPBruiser plugin.

= Are there any php extensions that need to be activated so this plugin could work? =
No. The plugin runs 100% without activating any additional php extensions.

= Does WPBruiser help me to block ip of the spammers? =
Yes. Starting with WPBruiser version 1.1.0, this feature is available.

= Does WPBruiser recognize IPV6 addresses and is it possible to block them? =
Yes. Starting with WPBruiser version 1.1.0, IPV6 is recognized and the administrator can block it with a single click.

= Why captcha is not user friendly? =
Studies shown that visual CAPTCHAs take around 5-10 seconds to complete and audio CAPTCHAs take much longer (around 20-30 seconds) to hear and solve.


== Screenshots ==
1. WPBruiser - Settings
2. WPBruiser - Security
3. WPBruiser - Security - WhiteList IP Address
4. WPBruiser - Security - BlackList IP Address
5. WPBruiser - WordPress
6. WPBruiser - Tweaking WordPress
7. WPBruiser - Protect Jetpack Contact Form
8. WPBruiser - UltimateMember protection
9. WPBruiser - Other plugins integrations
10. WPBruiser - Report - Blocked Comment
11. WPBruiser - Report - Block IP Address
12. WPBruiser - Report - By Location - Distribution
13. WPBruiser - Detailed Report


== Upgrade Notice ==

= 3.2.0 =
Security release. Fixes an unauthenticated PHP Object Injection vulnerability (CVE-2026-65556) and removes all remaining calls to the discontinued vendor service. All users should update immediately.

== Changelog ==

= 3.2.0 =
**Security**
- Fixed an unauthenticated PHP Object Injection vulnerability (CVE-2026-65556). The Ultimate Member integration deserialized attacker-controlled POST data (`custom_fields`) without restricting object instantiation, which could be chained with a POP gadget in WordPress, this plugin or another installed component to perform destructive actions or execute code. Reported via Patchstack.
- Hardened every remaining deserialization call in the plugin so that PHP objects can never be instantiated from stored, submitted or remotely fetched data.
- Removed all outbound requests to the discontinued vendor service, including a license-activation request that was sent with TLS certificate verification disabled. The plugin no longer contacts any external server.
- Removed the external module-loading code path, which searched other installed plugins' directories and executed PHP files found there. The plugin now only loads code that ships inside its own directory.
- Added direct-access (`ABSPATH`) protection to all plugin PHP files.
- Escaped all remaining output, including the client user agent shown in test-mode notification emails, which was previously written into the email unescaped.

**Internationalization**
- Every translatable string now passes a literal `goodbye-captcha` text domain instead of a PHP constant. WordPress.org can only extract strings from literal domains, so previously none of the plugin's ~356 strings could be translated at all.
- The declared text domain now matches the plugin slug, and bundled translations are loaded from the correct directory. The previous release looked for them in a folder that never existed.
- Replaced concatenated and interpolated translation strings with `sprintf()` placeholders so they can be translated correctly in every language.

**Removed**
- The newsletter subscription form, its bundled MailChimp submission library and its AJAX endpoint. The form was already unreachable and pointed at a mailing list that is no longer operated.
- The Extensions and Licenses admin pages, the licensing modules and the self-hosted plugin updater. These supported paid add-ons that are no longer distributed, and every endpoint they used is gone.
- Promotional links to the discontinued vendor website throughout the admin interface, the notification emails and the readme. That domain no longer belongs to the project.

**Fixed**
- PHP 8 notice: "spl_autoload_register(): Argument #2 ($do_throw) has been ignored". The deprecated argument is no longer passed.
- PHP 8 warning: "The magic method MchGdbcBasePublicPlugin::__wakeup() must have public visibility". The method is now public and throws, which both silences the warning and keeps the class from being rebuilt from serialized data.
- WordPress 6.7 notice: "Translation loading for the wp-bruiser domain was triggered too early". Admin page titles and notice messages are now built on the `init` action instead of `plugins_loaded`, so no translation function runs before `init`.
- The admin stylesheets no longer restyle `html` and `body`. Previous versions overrode the WordPress admin font, text colour and background on the plugin's screens; admin pages now inherit WordPress' own typography.
- Brute force monitoring now starts on the `init` action instead of while the plugin file is still loading. It reached the translated settings metadata, which was the remaining cause of the "translation loading was triggered too early" notice on every request carrying POST data.
- Fresh installations no longer run the 1.x to 3.x upgrade routines. The saved version was left unset on a new install, and because an unset version compares lower than every release, every historical migration step ran against a brand new database. This also removes the `version_compare(): Passing null` deprecation notices on PHP 8.1 and later.
- Fixed the Reports screen crashing when a blocked ZM Ajax Login Register attempt was inspected: the handler was an unfinished debug statement that dumped the raw object and stopped the page.

**Coding standards**
- The plugin now passes the WordPress Plugin Check with no outstanding errors or warnings. Where a rule genuinely does not apply — inspecting nonce-less public form submissions, querying the plugin's own custom table, comparing anti-spam tokens byte-for-byte — the exception is annotated in the code with the reason.
- Escaped every remaining output, added the missing `translators:` comments, and replaced `_e()` with `esc_html_e()`/`esc_attr_e()`.
- Replaced discouraged functions: `date()` with `gmdate()`, `strip_tags()` with `wp_strip_all_tags()`, `json_encode()` with `wp_json_encode()`, `parse_url()` with `wp_parse_url()`, `mt_rand()` with `wp_rand()`, and `wp_redirect()` with `wp_safe_redirect()`.
- Added `wp_unslash()` and sanitisation to request data that lacked it, and stopped writing sanitised values back into `$_POST`.
- Removed leftover debug code, including a `print_r()` that dumped a raw object and halted the Reports page whenever a blocked ZM Ajax Login Register attempt was viewed.
- Removed hidden files and the superseded client script from the package.

**Maintenance**
- Declared `Requires at least`, `Requires PHP` and the plugin license in the plugin header.
- Raised the minimum supported WordPress version to 4.7, which is what the REST API calls in the brute-force module actually need.
- Raised the minimum supported PHP version to 7.0.
- Updated `Tested up to` for current WordPress.
- Support links now point to the WordPress.org support forum.

= 3.1.43 =
- Fixed PHP 7.4 warnings
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs
- Refreshed Incapsula, Amazon and Cloudflare trusted IPs ranges

= 3.1.40 =
**Fixes**
- Ajax call javascript error

= 3.1.39 =
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs
- Refreshed Incapsula, Amazon and Cloudflare trusted IPs ranges

= 3.1.38 =
**Fixes**
- Compatibility with Mailchimp for WordPress
- PHP 7.4 warnings

**Improvements**
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs
- Refreshed Incapsula, Amazon and Cloudflare trusted IPs ranges


= 3.1.37 =
**Improvements**
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs
- Refreshed Incapsula, Amazon and Cloudflare trusted IPs ranges

= 3.1.36 =
**Fixes**
- Compatibility with latest JetPack Contact Form

= 3.1.35 =
**Improvements**
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs
- Refreshed Incapsula, Amazon and Cloudflare trusted IPs ranges

= 3.1.34 =
**Improvements**
- Refreshed Incapsula, Amazon and Cloudflare trusted IPs ranges


= 3.1.33 =
**Fixes**
- The plugin does not have a valid header issue

= 3.1.32 =
**Improvements**
- Refreshed Incapsula, Amazon and Cloudflare trusted IPs ranges


= 3.1.31 =
**Improvements**
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs
- Refreshed Incapsula, Amazon and Cloudflare trusted IPs ranges

= 3.1.30 =
* Fixed fatal error when using PHP 5.3.x


= 3.1.29 =
Refreshed Incapsula, Amazon and Cloudflare trusted IPs ranges

= 3.1.28 =
**Fixes**
- Fixed ajax compatibility with [Ultra Community - User Profiles & Membership Plugin](https://wordpress.org/plugins/ultra-community/)

**Improvements**
- Introducing new Extension - WPBruiser WPForms Protection



= 3.1.27 =
**Improvements**
- Refreshed Incapsula, Amazon and Cloudflare trusted IPs ranges


= 3.1.26 =
**Improvements**
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs
- Compatibility with WordPress 5.1


= 3.1.22 =
* Fixed compatibility with version 2.x of [Ultra Community - User Profile & Membership Plugin](https://demo.ultracommunity.com/)

= 3.1.21 =
**Improvements**
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs


= 3.1.19 =
* Fixed compatibility with UM 2.X

= 3.1.18 =
* Fixed PHP 7.1.x warnings and compatibility with PHP 7.2


= 3.1.15 =
**Fixes**
- Fixed the incompatibility with Gravity Forms Extension

= 3.1.14 =
**Improvements**
- Integrated with [HTML Forms Plugin](https://wordpress.org/plugins/html-forms/)


= 3.1.12 =
**Fixes**
- PHP7 warnings

**Improvements**
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs

= 3.1.11 =
* Fixed the PHP notice issue reported by @devonostendorf[https://wordpress.org/support/topic/php-notice-wpdbprepare-was-called-incorrectly-generated-in-wp-core-v4-8-3/]


= 3.1.9 =
* Added integration with [MailChimp Top Bar](https://wordpress.org/plugins/mailchimp-top-bar/)

= 3.1.6 =
**Improvements**
* Added integration with [Ultra Community](https://wordpress.org/plugins/ultra-community/) Membership plugin


**Fixes**
- A plugin vulnerability reported by Wordfence team

= 3.1.4 =
**Fixes**
- PHP7 warnings

**Improvements**
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs

= 3.1.3 =
**Improvements**
- Prevent oEmbed and WP Rest API user enumeration.

= 3.1.1 =
**Fixes**
- Compatibility with MailChimp for WP version 4.0.4 and up.

= 3.1 =
**Fixes**

- Compatibility with MailChimp for WP version 4.0.6 and up
- Compatibility with WordPress MU Domain Mapping plugin
- Fixed Password reset protection for WooCommerce
- Refreshed Country IPs

= 3.0.15 =
**Improvements**

- Compatibility with Avada theme
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs

= 3.0.14 =
**Fixes**

- Fixed the issue when protection for WooCommerce registration form was conflicting with protection for Standard WordPress registration


= 3.0.12 =

- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs

**Improvements**

- Improved loading speed
- Improved detection algorithm



= 3.0.11 =

**Improvements**
- Improved proxy detection feature

= 3.0.10 =

- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs

**Additions**

- Ability to register trusted proxy headers
- Email Notification when a user with Admin Capabilities has signed in

**New Premium Extensions**

- Country Blocking Extension
- Easy Forms for MailChimp Extension
- Easy Digital Downloads Extension
- AffiliateWP

= 3.0.9 =
Fixing repository issues

= 3.0.7 =
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs

= 3.0.6 =
Fixed corrupted files from WordPress Repository

= 3.0.5 =
- Fixed JavaScript error -
[Uncaught TypeError: Cannot read property 'call' of undefined](https://wordpress.org/support/topic/uncaught-typeerror-cannot-read-property-call-of-undefined)
 Thanks to [James Revillini](https://wordpress.org/support/profile/jrevillini)

= 3.0.4 =
**Fixes**
- W3TC refresh cache notice - reported by [Todd and roxchou](https://wordpress.org/support/topic/w3tc-empty-page-cache-message-always-on-when-activating-wpbruiser)
- bbPress menu disappear when WPBruiser is active - reported by [N3k0](https://wordpress.org/support/topic/bbpress-menu-dissapair)

**Additions**
- Integration with [wpDiscuz](https://wordpress.org/plugins/wpdiscuz)
- WooCommerce Extension


= 3.0.3 =
**Fixes**
- Compatibility with Query Monitor plugin
- Block Web Attackers IPs and Block Anonymous Proxy IPs options are getting deactivated
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs

= 3.0.2 =
- Removed jQuery dependency
- Added protection for Ultimate Member Modal Login
- Compatibility with WordPress 4.5 for Max Comments Fields Length
- Refreshed Country IPs
- Refreshed WebAttackers IPs
- Refreshed Proxy IPs

= 3.0.1 =

**Improvements**

- Ability to set up the Fields Maximum Length for each comment form field
- Added Extensions page
- Added `languages` folder to support translations
- Added a new filter `wpbruiser-scripts-in-head` - to explicitly render WPBruiser's script in head or footer


= 3.0 =

- Added IPv4 to country lookup

**Introducing premium extensions**

- Contact Form 7
- Gravity Forms
- Ninja Forms
- Formidable Forms
- Fast Secure Contact Form
- BuddyPress
- MemberPress


= 2.2.2 =

- Removed the MaxMind GeoIP Databases due to the licensing terms violation


= 2.2.1 =

- Fixed MySql error reported by [WHSajid](https://wordpress.org/support/topic/wp-db-error-2)

= 2.2.0 =

- Fixed issue when WPBruiser blocks post requests from some Amazon proxy servers
- Fixed blocked content extra slashes
- Improved Brute Force attacks detection
- Refreshed dangerous IPs lists

= 2.1.0 =

- Now GoodByeCaptcha is WPBruiser
- Fixed Disable Trackbacks/Pingbacks issue reported by [sixer](https://wordpress.org/support/topic/disable-trackbacks-pingbacks-disables-login-from-android-wp-app)
- Added compatibility with WP Deferred JavaScripts plugin
- Fixed warning notice reported by Sucuri


= 2.0.1 =
**Fixes**
- Compatibility with Login With Ajax plugin
- Compatibility with Google Apps Login plugin
- Compatibility with WP-Rocket plugin
- Compatibility with Autoptimize plugin
- Compatibility with Theme My Login plugin

= 2.0 =
**Additions**
- New admin interface
- Network admin interface
- Brute-Force protection
- White-list IPs
- Black-list IPs
- WordPress tweaks
