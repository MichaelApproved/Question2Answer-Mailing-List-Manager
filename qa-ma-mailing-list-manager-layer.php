<?php

/*
	(c) 2012, Michael Khalili

	http://www.michaelapproved.com/

    File: qa-plugin/ma-mailing-list-manager/qa-ma-mailing-list-manager-layer.php
    Version: 1.5
    Date: 2012-11-06
	Description: Adds signup checkboxes to your registration form.


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

	class qa_html_theme_layer extends qa_html_theme_base
	{
		
		function html() {
			
			//remove the textbox asking for content on the edit question/topic page.
			if ($this->template == 'register'){
				
//				echo '<pre>';
//				print_r($this->content);
//				echo '</pre>';
				
				//grab the serialized settings from db and unserialize them (if they exist)
				$saveSettingsSerialized = qa_opt('ma_mlm_settings');
				
				if ($saveSettingsSerialized == ''){
					$saveSettings = array();
				}else{
					$saveSettings = unserialize($saveSettingsSerialized);
				}


				if (isset($saveSettings['mc_api_key']) && 
						isset($saveSettings['list'])) {
					//loop through and look for an enabled list that also wants to display a checkbox on the registration page
					foreach($saveSettings['list'] as $listId => $listSettings) {
						
						if ($listSettings['enabled'] && 
								$listSettings['regcheckbox']) {
							
							//try to preserve the submited value, if the form was submitted.
							if (count($_POST)) {
								$checked = qa_post_text('ma_mlm_mc_list_regsubscribe_' . $listId);
							}else{
								$checked = isset($listSettings['regprecheck']) ? $listSettings['regprecheck'] : 0;
							}
							
							$this->content['form']['fields']['ma_mlm_mc_list_regsubscribe_' . $listId] = array(
								'label' => isset($listSettings['regtext']) ? $listSettings['regtext'] : 'Subscribe to the mailing list.',
								'type' => 'checkbox',
								'value' => $checked,
								'error' => '',
								'tags' => 'NAME="ma_mlm_mc_list_regsubscribe_' . $listId . '"',
							);

							
						}
						
					}
				}
				
				
			}
			qa_html_theme_base::html(); // call back through to the default function
			
		}
		

	}
	
