<?php

/*
	(c) 2012, Michael Khalili

	http://www.michaelapproved.com/

	
	File: qa-plugin/mailing-list-manager/qa-plugin.php
	Version: 1.5.0
	Date: 2012-11-06 00:00:00 GMT
	Description: Adds your members email addresses to your MailChimp.com list.

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.gnu.org/licenses/gpl-2.0.html
*/

/*
	Plugin Name: Mailing List Manager
	Plugin URI: http://www.michaelapproved.com/
	Plugin Description: Adds your members email addresses to your MailChimp.com list.
	Plugin Version: 1.5
	Plugin Date: 2012-11-06
	Plugin Author: Michael Khalili
	Plugin Author URI: http://www.michaelapproved.com/
	Plugin License: GPLv2
	Plugin Minimum Question2Answer Version: 1.4
*/


	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../../');
		exit;
	}

	define('MA_MLM_BASE_PATH', dirname(__FILE__));

	//register the event module that deletes the cache files if a change happens.
	//qa_register_plugin_module('event', 'qa-ma-mailing-list-manager-event.php', 'qa_ma_mlm_event', 'Mailing List Manager Event Handler');

	//admin page
	qa_register_plugin_module('module', 'qa-ma-mailing-list-manager-admin.php', 'qa_ma_mlm_admin', 'Mailing List Manager Admin');

	//event module that catches new/confirmed member events.
	qa_register_plugin_module('event', 'qa-ma-mailing-list-manager-event.php', 'qa_ma_mlm_event', 'Mailing List Manager Event Handler');

	//Layer to add checkboxes to registration form
	qa_register_plugin_layer('qa-ma-mailing-list-manager-layer.php', 'Mailing List Manager Layer');
	
	//Layer to add checkboxes to registration form
	qa_register_plugin_module('page', 'qa-ma-mailing-list-manager-page.php', 'qa_ma_mlm_page', 'Page to export user email for import into 3rd party mailing list program.');

/*
	Omit PHP closing tag to help avoid accidental output
*/