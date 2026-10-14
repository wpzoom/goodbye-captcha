=== WPBruiser ===
Contributors: wpzoom
Tags: antispam, spam protection, brute force, login security, comments
Requires at least: 6.5
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 3.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Stop spam bots and brute-force logins on your WordPress forms with an invisible check. No puzzles, no image challenges, no third-party service.

== Description ==

WPBruiser stops automated form submissions before WordPress ever processes them. Every protected form gets a hidden token, which the visitor's browser requests in the background. A real person never sees it or has to do anything. A bot that posts straight to your login, registration or comment endpoint arrives without a valid token and is turned away.

Because bad submissions are rejected at the door, they never land in your moderation queue and never create junk user accounts. You also don't have to ask visitors to solve a puzzle, which is often the step where real people give up on a form.

Everything runs on your own server. WPBruiser does not call remote APIs, does not load outside scripts or fonts and does not send your visitors' data anywhere.

**Notice:** WPBruiser was created by [Mihai Chelaru](https://profiles.wordpress.org/mihche/). As of version 3.2.0, it is maintained by [WPZOOM](https://profiles.wordpress.org/wpzoom/).

= Forms you can protect =

Turn protection on or off for each form on the plugin's settings screens:

* WordPress login
* WordPress registration
* WordPress lost password
* WordPress comments, with an optional character limit for the comment, name, email and website fields

= Supported third-party plugins =

* **Ultimate Member**: login, registration and password reset forms
* **WP-Members**: login and registration forms
* **Login With Ajax**: its AJAX login, registration and lost password forms
* **Jetpack**: the Jetpack contact form
* **MailChimp for WordPress**: every sign-up form you build with it
* **HTML Forms**: every form you build with it
* **wpDiscuz**: comments posted through its AJAX comment system

= Brute-force and hardening tools =

* Detects a login brute-force attack in progress and can email you when one starts
* Automatically blocks IP addresses that keep hammering your login
* Optionally rejects login attempts from IP addresses on the bundled lists of known web attackers and anonymous proxies, including Tor exit nodes
* Stops user enumeration through `/?author=N` scans, the oEmbed endpoint and the REST API
* Turns off XML-RPC completely (Jetpack's own XML-RPC requests are still allowed), or removes only the pingback methods
* Sends an email whenever an account with administrator rights signs in
* Hides the WordPress version and removes the RSD and Windows Live Writer header links
* Hides the website field and the notes text on the comment form

= IP address management =

* Allow list and block list for single addresses and ranges, IPv4 and IPv6
* Finds the visitor's real IP address behind CloudFlare, Incapsula, Amazon CloudFront, AWS ELB, Rackspace and Sucuri, and lets you register other trusted proxy headers

= Reports =

* Every blocked attempt is logged to a database table of its own
* Charts show which forms are attacked most, and a world map shows where attempts come from (the country lookup is done locally)
* Block or unblock an address straight from the reports screen
* Old log entries are deleted automatically after the number of days you choose, or you can turn logging off

= Everyday details =

* Test Mode, which emails you about every check so you can confirm protection works before you rely on it
* Network admin screens for WordPress Multisite
* Works with full-page caching: the token is requested fresh on every page view
* The front-end script is a single small file, loaded deferred so it doesn't hold up page rendering

= External services =

WPBruiser does not connect to any external service. It makes no remote API requests, collects no telemetry, runs no license or update checks of its own and loads no third-party fonts or scripts. All detection happens on your server, and the IP reputation and country data it uses ship inside the plugin.

= Support =

Found a bug or have a question? Start a topic on the [WordPress.org support forum](https://wordpress.org/support/plugin/goodbye-captcha/). It helps us a lot if you include your WordPress and PHP versions, the form or plugin involved, and the steps that reproduce the problem.

== Installation ==

**From your dashboard**

1. In the WordPress admin, open **Plugins > Add Plugin**.
2. Search for "WPBruiser", then click **Install Now** and **Activate**.

**Manually**

1. Download the plugin ZIP from WordPress.org.
2. Either upload it under **Plugins > Add Plugin > Upload Plugin**, or extract it and copy the `goodbye-captcha` folder into `wp-content/plugins/` on your server.
3. Activate WPBruiser on the **Plugins** screen.

**First steps after activating**

1. Open the new **WPBruiser** item in the admin menu.
2. Choose which forms to protect, on the WordPress tab and on the tabs for any supported plugins you use.
3. Optionally, turn on Test Mode, submit your forms yourself and check the notification emails. Turn Test Mode off when you're done.
4. If your site is behind a reverse proxy or CDN that isn't detected automatically, add its header under trusted proxy headers so the correct visitor IP is logged.

On a Multisite network, activate the plugin network-wide and manage it from the Network Admin.

== Frequently Asked Questions ==

= Will my visitors notice anything? =

No. There is nothing to click, type or solve. The check runs quietly in the browser while the form is on screen.

= Does it need JavaScript? =

Yes. The token that proves a submission came from a real browser is generated by a small script. Visitors who have JavaScript turned off will not be able to submit the forms you protect.

= How can I check that it is working? =

Turn on Test Mode in the plugin settings. While it is on, nothing is blocked. Instead, the notification address receives an email for each checked submission, so you can try your own forms and see the result. Turn Test Mode off afterwards. From then on, the Reports screen lists every attempt that was blocked.

= I use a page caching plugin. Is that a problem? =

No. The script requests a fresh token on every page view. If a cached copy of a page carries an expired security key, the script gets a new one first, so you don't have to exclude any pages from your cache.

= My site is behind Cloudflare or another proxy. Will the right IP be blocked? =

The common proxies and CDNs are recognised automatically. For anything else, add the proxy's HTTP header on the proxy headers settings screen. Otherwise every visitor may appear to have the proxy's IP address.

= I locked myself out. What now? =

Add your own IP address to the allow list. Allowed addresses skip all checks, including the block list. If you can't reach the dashboard, rename the `wp-content/plugins/goodbye-captcha` folder over FTP or with your host's file manager to deactivate the plugin. Then sign in, rename it back and add your address to the allow list.

= Does it send data to any server? =

No. See "External services" in the description.

= Does it replace a full security plugin? =

No. WPBruiser handles spam bots, brute-force logins and a few hardening options. It doesn't scan files, run a firewall or check your site's integrity, so you can run it alongside a plugin that does.

= I'm upgrading from the original WPBruiser. Will I lose my settings? =

No. Your settings, allow and block lists and logs are kept, and the upgrade routines run by themselves the first time the new version loads. Two things no longer load: the old paid add-ons, and the integrations for plugins that are closed or no longer maintained (ZM Ajax Login & Register, PlanSo Forms, UltraCommunity, Seamless Donations and Epoch).

== Screenshots ==

1. General settings, including Test Mode and log retention
2. Security screen with the brute-force options
3. Adding trusted addresses to the allow list
4. Adding addresses and ranges to the block list
5. Choosing which core WordPress forms to protect
6. WordPress hardening options
7. Jetpack contact form protection
8. Ultimate Member form protection
9. Protection settings for other supported plugins
10. Inspecting a blocked comment in the reports
11. Blocking an IP address from the reports
12. Blocked attempts by country
13. Detailed report of blocked attempts

== Copyright ==

WPBruiser, Copyright (C) 2014-2018 Mihai Chelaru.
Modifications and continued development, Copyright (C) 2026 WPZOOM.

WPBruiser (formerly GoodBye Captcha) was originally created by Mihai Chelaru and is distributed under the terms of the GNU General Public License, version 2 or (at your option) any later version. The original author's copyright notices are kept in the source files.

WPBruiser bundles the following third-party resources, each under its own license:

* phpCrypt, Copyright (C) 2005 Ryan Gilfether (GPLv3 or later)
* phpseclib 0.3.10 (BigInteger), Copyright Jim Wigginton (MIT)
* Bootstrap 3.4.1 and Glyphicons Halflings, Copyright Twitter, Inc. (MIT)
* Flot 0.8.3, Copyright IOLA and Ole Laursen (MIT)
* flot.tooltip 0.8.5, Krzysztof Urbas and Evan Steinkerchner (MIT)
* jVectorMap 1.2.2, Copyright Kirill Lebedev (MIT)
* Raphaël 2.3.0, Copyright Dmitry Baranovskiy (MIT)
* morris.js 0.5.1, Copyright Olly Smith (BSD-2-Clause)
* Multiselect 2.2.6, Copyright Adrian Crisan (MIT)

The Integrations screen shows the icons the integrated plugins publish on WordPress.org (Gravity Forms: the icon of its official Gravity Forms CLI add-on). Plugin names and logos are trademarks of their owners and are used only to identify the integrations.

== Changelog ==

= 3.3.0 =
- New: protection is on from the start. A new install protects WordPress login, registration, lost password and comments, plus every supported plugin that is already active.
- New: a Dashboard widget with blocked attempts for the last day, week and month, and the forms bots target most.
- New: administrators see a small "protected" note under every form WPBruiser covers, so it is easy to check what is protected. Visitors never see it, and it can be hidden in Settings.
- Fix: about one genuine visitor in ten could be rejected on any protected form. On sites without the old mcrypt extension (every PHP 7.2+ site), the bundled cipher library added no padding when the compressed token filled its last block exactly, and then removed real data from the end while decrypting. Tokens now always decrypt; 10,000 test round trips had no failures.
- New: an Integrations screen. Every integration, free and Pro, is a card that shows whether its plugin is active, with a switch to turn protection on or off (and a switch per form where there are several), plus filters and search. It replaces the separate Contact Forms, Membership, eCommerce and Others screens.
- New: an extension API for add-ons such as WPBruiser Pro. An add-on registers its own protection modules through the `wpbruiser_register_modules` filter and protects a form with `wpbruiser_get_token_field_html()`, `wpbruiser_is_option_enabled()` and `wpbruiser_verify_submission()`. Each add-on loads its own code; WPBruiser still only loads files from its own folder.
- New: the `wpbruiser_loaded` action, and the `wpbruiser_country_id_for_ip` filter for supplying your own IP-to-country data.
- New: the `wpbruiser_reject_reason` filter, which lets an add-on reject a submission that passed every other check (WPBruiser Pro uses it for rate limits), and add-on settings boxes on the Security screen.
- New: `wpbruiser_get_token_field_names()`, so integrations can keep the token fields out of stored submissions, and a `wpbruiser:token` browser event fired when a form receives its token, for forms that submit as JSON.
- Fix: Jetpack contact forms. Blocked submissions kept no fields in Reports, current Jetpack versions (which renamed the form class) were not recognised, and older ones could hit a PHP error on a blocked submission.
- Fix: a lost password request that fails the check now shows an error on the form. It used to redirect to the login page, which also broke WooCommerce's lost password form.
- The Contact Forms, Membership, eCommerce and Others screens now list modules added by add-ons. The eCommerce screen stays hidden until an add-on adds a module to it.
- Removed leftover references to the old paid extensions from the settings screens, the AJAX setup and brute-force login detection.
- New: the WPBruiser ninja, redrawn as a vector icon, on every WPBruiser screen, in the admin menu (tinted to match your admin colour scheme) and in the Dashboard widget.
- New: Settings and Get Pro links on the Plugins screen, and a WPBruiser Pro box in the settings sidebar that lists the Pro integrations and points out which of those plugins are active on your site. Both disappear once WPBruiser Pro is active.

= 3.2.1 =
- Audited every third-party integration against the plugin directory and removed those whose plugin is gone: ZM Ajax Login & Register (closed 2023), PlanSo Forms (closed 2021), UltraCommunity (30 installs, last updated 2020) and Seamless Donations (last updated 2024, tested only to 6.4). Support for the Epoch comments iframe was removed as well; that plugin has not been updated since 2015. The readme no longer lists Postmatic and WP User Control, both closed, and now lists HTML Forms and WP-Members, which are supported but were never mentioned.
- Bootstrap updated from 3.2.0 to 3.4.1
- Raphaël updated from 2.1.2 to 2.3.0 (minified build). The bundled morris.js was already the project's final 0.5.1 release under an upstream banner that still read 0.5.0; the banner now states the real version and the file's provenance.
- The client script is now a static file loaded with `wp_enqueue_script()` (deferred) and configured with `wp_localize_script()`. Previous versions printed an inline loader that fetched a PHP-generated script through a `?gdbc-client=` parameter handled before WordPress had finished loading, which also required including `pluggable.php` by hand. The token endpoint is now a registered `wp_ajax_nopriv_` action, and a nonce that has expired in a page-cached copy of a page is refreshed by the script through a second registered action, so page caches need no special handling.
- Forms injected by AJAX (Ultimate Member, ZM Ajax Login & Register) are detected with a `MutationObserver`; the inline "refresh tokens" scripts that used to travel with their markup are gone.
- The brute-force "site under attack" flag is stored as a transient. The plugin no longer writes any files: the file cache (which could fall back to `wp-content` itself), its `.htaccess`/`index.php` guards and seven unused cache back ends were removed.
- Every settings callback registered with `register_setting()` now runs the submitted values through a whitelist that drops unknown keys and coerces each field by its declared type before any module-specific validation.
- Admin styles that were printed inline from the settings partials and the Reports modal moved into the enqueued stylesheet; the Reports map data is localized instead of printed as an inline script.
- The browser fingerprint posted with each token request is unslashed and validated field by field; nonces are unslashed and sanitised before `wp_verify_nonce()`; a dead URL helper that read `$_SERVER` unsanitised was removed.

**Fixed**
- Made an implicitly nullable constructor parameter explicit and replaced a non-canonical cast, both deprecated by PHP 8.4/8.5.
- Upgrade routines and first-run defaults run on the `init` action instead of `plugins_loaded`, which removes the last remaining trigger of the "translation loading was triggered too early" notice on brand-new installs.
- A fresh installation now creates its attempts table, token secret and module defaults on activation. Table creation used to happen only inside the 1.x-to-2.0 upgrade step, which new installs correctly skip, so a brand-new install ended up with no table at all.

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

The releases below, 3.1.43 and earlier, were published by the plugin's original author, Mihai Chelaru.

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
