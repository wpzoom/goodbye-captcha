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

class GdbcWordpressAdminPage  extends GdbcBaseAdminPage
{
	public function __construct($pageMenuTitle, $pageBrowserTitle, $pluginSlug)
	{
		parent::__construct($pageMenuTitle, $pageBrowserTitle, $pluginSlug);

		if(GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_WORDPRESS))
		{
			$this->registerGroupedModules(array(
				new MchGdbcGroupedModules(__('WordPress Standard Forms Settings', 'goodbye-captcha'), array(
						GdbcWordPressAdminModule::getInstance(),
					)
				)
			));


			$this->registerGroupedModules(array(
				new MchGdbcGroupedModules(__('Tweaking WordPress', 'goodbye-captcha'), array(
						GdbcWordPressTweaksAdminModule::getInstance(),
					)
				)
			));

		}

	}

	public function registerPageMetaBoxes()
	{
		parent::registerPageMetaBoxes();

		if($this->getPageLayoutColumns() <= 1)
			return;

		add_meta_box(
				"gdbc-help-xml-rpc",
				__('Disabling XML-RPC Service', 'goodbye-captcha'),
				array( $this, 'renderXmlRpcMetaBox' ),
				$this->getAdminScreenId(),
				'side',
				'core',
				null
		);

		add_meta_box(
				"gdbc-help-xml-rpc-pingback",
				__('Disabling XML-RPC Pingbacks', 'goodbye-captcha'),
				array( $this, 'renderXmlRpcPingbacksMetaBox' ),
				$this->getAdminScreenId(),
				'side',
				'core',
				null
		);

	}

	public function renderXmlRpcMetaBox()
	{
		$textInfo  = __('XML-RPC is used in WordPress as an API for third-party clients such as WordPress mobile apps, popular weblog clients like Windows Writer or popular plugins such as Jetpack.', 'goodbye-captcha');
		$textInfo .= __('If you use any application which calls your XML-RPC Service, <b>do not</b> Completely Disable XML-RPC. Otherwise, completely disabling XML-RPC it is strongly recommended.', 'goodbye-captcha');

		echo '<p style="text-align: justify">' . wp_kses_post($textInfo) . '</p>';


		$textInfo  = '<div class = "mch-meta-notice-info">';
		$textInfo .= '<span><b>' . esc_html__('Feel free to Completely Disable XML-RPC if you are using Jetpack plugin', 'goodbye-captcha') . '</b>. '
		          . esc_html__("WPBruiser simply allows Jetpack's XML-RPC Requests", 'goodbye-captcha') . '</span>';
		$textInfo .= '</div>';

		echo wp_kses_post($textInfo);

	}


	public function renderXmlRpcPingbacksMetaBox()
	{
		$textInfo  = __('XML-RPC is used for Pingbacks and Trackbacks which can be heavily misused to start <a href="https://blog.sucuri.net/2014/03/more-than-162000-wordpress-sites-used-for-distributed-denial-of-service-attack.html">DDoS attacks</a>.', 'goodbye-captcha');

		echo '<p style="text-align: justify">' . wp_kses_post($textInfo) . '</p>';

		$textInfo  = '<div class = "mch-meta-notice-info">';
		$textInfo .= '<span>' . esc_html__('If the', 'goodbye-captcha') . ' <b>' . esc_html__('Completely Disable XML-RPC', 'goodbye-captcha') . '</b> '
		          . esc_html__('option is checked, enabling/disabling this option has no effect!', 'goodbye-captcha') . '</span>';
		$textInfo .= '</div>';

		echo wp_kses_post($textInfo);
	}


}