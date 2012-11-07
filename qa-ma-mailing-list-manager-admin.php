<?php

/*
	(c) 2012, Michael Khalili

	http://www.michaelapproved.com/

    File: qa-plugin/ma-mailing-list-manager/qa-ma-mailing-list-manager-admin.php
    Version: 1.5
    Date: 2012-11-06
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

    class qa_ma_mlm_admin {
        
        var $directory;
        var $urltoroot;
		var $mcNewsletterLists;
		var $options;
        
		//this runs before the module is used.
        function load_module($directory, $urltoroot)
        {
			//file system path to the plugin directory
            $this->directory=$directory;
                        
			//url path to the plugin relative to the current page request.
            $this->urltoroot=$urltoroot;
			
        }
        
                //a request for the default value for $option
        function option_default($option)
        {
            if ($option == 'ma_mlm_settings') {
                return '';
            }
		}
        
        function admin_form()
        {
           
            //default form as unsaved
            $saved=false;

			//only show the mail chimp API options when needed so the admin form doesn't slow down all the time while waiting for MC API to respond.
			$showSettings = false;
			
			//default to no message being displayed
			$okDisplay = null;
			
			//grab the serialized settings from db and unserialize them (if they exist)
			$optionsSerialized = qa_opt('ma_mlm_settings');

			if ($optionsSerialized == ''){
				$this->options = array();
			}else{
				$this->options = unserialize($optionsSerialized);
			}			
			
			
            //has the save button been pressed?
            if (qa_clicked('ma_mlm_save_button')) {
				$table_exists = qa_db_read_one_value(qa_db_query_sub("SHOW TABLES LIKE '^mamlmConfirm'"),true);
				if(!$table_exists) {
					qa_db_query_sub(
						'CREATE TABLE IF NOT EXISTS ^mamlmConfirm (
						userId bigint(20) unsigned NOT NULL,
						created datetime NOT NULL,
						listProvider varchar(10) NOT NULL,
						listId varchar(100) NOT NULL,
						listSettings varchar(1000) NOT NULL,
						UNIQUE (userId, listProvider, listId)
						) ENGINE=MyISAM  DEFAULT CHARSET=utf8'
					);
				}
                
				//clear out the old options.
				$this->options = array();
				
				$this->options['mc_api_key'] = qa_post_text('ma_mlm_mc_api_key');
				
				//grab the ma_mlm_mcNewsletterLists and unserialize it so we can loop through
				$mcNewsletterListsSerializedEncoded = qa_post_text('ma_mlm_mcNewsletterLists');
				$mcNewsletterListsSerialized = html_entity_decode($mcNewsletterListsSerializedEncoded, ENT_QUOTES, "UTF-8");
				$this->mcNewsletterLists = unserialize($mcNewsletterListsSerialized);
				
				if (isset($this->mcNewsletterLists['list'])) {
					//loop through and save each of the settings from the list.
					foreach($this->mcNewsletterLists['list'] as $listId => $listName ){

						//using the listId, save the other fields for this submit
						$this->options['list'][$listId]['enabled'] = (int)qa_post_text('ma_mlm_mc_list_enabled_' . $listId);
						$this->options['list'][$listId]['name'] = $listName;
						$this->options['list'][$listId]['regcheckbox'] = (int)qa_post_text('ma_mlm_mc_list_regcheckbox_' . $listId);
						$this->options['list'][$listId]['regtext'] = qa_post_text('ma_mlm_mc_list_regtext_' . $listId);
						$this->options['list'][$listId]['regprecheck'] = (int)qa_post_text('ma_mlm_mc_list_regprecheck_' . $listId);
						$this->options['list'][$listId]['afterconf'] = (int)qa_post_text('ma_mlm_mc_list_afterconf_' . $listId);
						$this->options['list'][$listId]['confsend'] = (int)qa_post_text('ma_mlm_mc_list_confsend_' . $listId);

					}
				}
				
				
				//serialize the array so we can save it as one value in the db
				$optionsSerialized = serialize($this->options);
				
				qa_opt('ma_mlm_settings', $optionsSerialized);
				
				
                //mark form as saved
                $saved=true;
				
				//set the message to display
				$okDisplay = 'Options saved';
				
				//show the settings
				$showSettings = true;
            }
			
			
			//Show the settings if the "Show Settings" is clicked
			if (qa_clicked('ma_mlm_show_settings')) {
				//show the settings
				$showSettings = true;
			}
			
			
			if ($showSettings) {
				//run the code that gets the Mail Chimp lists
				$this->mcNewsletterLists = $this->mcLists();
				
				//build the form.
				
				//Serialize the list array and store it in a hidden field
				$mcNewsletterListsSerialized = serialize($this->mcNewsletterLists);
				$form['hidden']['ma_mlm_mcNewsletterLists'] = htmlentities($mcNewsletterListsSerialized, ENT_QUOTES, "UTF-8");

				//'ok' displays a message above the form. Used here to display a success message if the form has been saved.
				//files contains an array of field options.
				
				$form['ok'] = $okDisplay;
				$form['fields']['mc_api_key'] = array(
							'label' => 'MailChimp.com API Key',
							'type' => 'textbox',
							'value' => isset($this->options['mc_api_key']) ? $this->options['mc_api_key'] : '',
							'tags' => 'NAME="ma_mlm_mc_api_key"',
							'error' => isset($this->mcNewsletterLists['error_message']) ? $this->mcNewsletterLists['error_message'] : ''
						);

				$form['buttons']['save'] = array(
							'label' => 'Save Changes',
							'tags' => 'NAME="ma_mlm_save_button"',
							'note' => '<div style="text-align: left;">To import existing users, you can export <a href="./ma-mlm-export" target="_blank">all emails</a> or <a href="./ma-mlm-export?confirmed=true" target="_blank">only confirmed emails</a> and import them on the <a href="http://MailChimp.com" target="_blank">Mail Chimp</a> website.</div>',
						);


				//Were we able to pull up the newsletter list?
				if ($this->mcNewsletterLists['success']) {
					$mcListSelectedMsg = '';
					$mcListSelectedErrorMsg = '';
					$mcListSelectedValue = '';

					

					foreach($this->mcNewsletterLists['list'] as $listId => $listName ){
						//Add the subscribe new members checkbox.

						$form['fields']['ma_mlm_mc_list_enabled_' . $listId] = array(
							'label' => '<span style="font-weight: bold;">Enable: ' . $listName . '</span>',
							'type' => 'checkbox',
							'value' => isset($this->options['list'][$listId]['enabled']) ? $this->options['list'][$listId]['enabled'] : 0,
							'error' => '',
							'tags' => '" NAME="ma_mlm_mc_list_enabled_' . $listId . '"',
						);

						$form['fields']['ma_mlm_mc_list_regcheckbox_' . $listId] = array(
							'label' => 'Show checkbox asking user to subscribe while registering. Without a checkbox, all members will be subscribed during registration.',
							'type' => 'checkbox',
							'value' => isset($this->options['list'][$listId]['regcheckbox']) ? $this->options['list'][$listId]['regcheckbox'] : 0,
							'error' => '',
							'tags' => 'NAME="ma_mlm_mc_list_regcheckbox_' . $listId . '"',
						);
						
						$form['fields']['ma_mlm_mc_list_regtext_' . $listId] = array(
							'label' => 'Text to show next to checkbox while registering.',
							'type' => 'text',
							'value' => isset($this->options['list'][$listId]['regtext']) ? $this->options['list'][$listId]['regtext'] : 'Subscribe to the mailing list.',
							'error' => '',
							'tags' => 'NAME="ma_mlm_mc_list_regtext_' . $listId . '"',
						);
						
						$form['fields']['ma_mlm_mc_list_regprecheck_' . $listId] = array(
							'label' => 'Precheck the registration checkbox.',
							'type' => 'checkbox',
							'value' => isset($this->options['list'][$listId]['regprecheck']) ? $this->options['list'][$listId]['regprecheck'] : 0,
							'error' => '',
							'tags' => 'NAME="ma_mlm_mc_list_regprecheck_' . $listId . '"',
						);


						$form['fields']['ma_mlm_mc_list_afterconf_' . $listId] = array(
							'label' => 'Only subscribe after member confirms their email when registering.',
							'type' => 'checkbox',
							'value' => isset($this->options['list'][$listId]['afterconf']) ? $this->options['list'][$listId]['afterconf'] : 0,
							'error' => '',
							'tags' => 'NAME="ma_mlm_mc_list_afterconf_' . $listId . '"',
						);

						$form['fields']['ma_mlm_mc_list_confsend_' . $listId] = array(
							'label' => '<div style="padding-bottom: 30px;">Send a confirmation request when adding someone to this list.</div>',
							'type' => 'checkbox',
							'value' => isset($this->options['list'][$listId]['confsend']) ? $this->options['list'][$listId]['confsend'] : 0,
							'error' => '',
							'tags' => 'NAME="ma_mlm_mc_list_confsend_' . $listId . '"',
						);
						
					}


				}

			}else{
				//This is not meant for use with external users, it's only meant to be used with native membership system.
				//This is mainly due to not being able to predict the layout of other membership database tables.
				if (QA_FINAL_EXTERNAL_USERS) {
					$form['ok'] = 'This plug-in is not meant to be used with WordPress or other external member list. Please use a plug-in for that platform instead.';
				}else{
					$form=array(
						'ok' => 'Settings are only shown when requested so the admin page doesn\'t slow down from the Mail Chimp API calls',
						'buttons' => array(
							array(
								'label' => 'Show settings',
								'tags' => 'NAME="ma_mlm_show_settings"',
							),
						),
					);

				}

			}
			
            return $form;
        }

		
		//Returns a list of Mail Chimp newsletter lists.
		function mcLists() {
			//make sure the api key is set first
			if (isset($this->options['mc_api_key']) && $this->options['mc_api_key'] != '') {

				//Add the Mail Chimp API lib
				require_once $this->directory . '/mcapi/MCAPI.class.php';

				//Create the object with the stored API key
				$api = new MCAPI($this->options['mc_api_key']);

				//request the list of mailing lists
				$retval = $api->lists();

				//check the response from MC
				if ($api->errorCode){
					$response['success'] = false;
					$response['error_message'] = "Unable to load lists! Code=".$api->errorCode . ". Msg=" . $api->errorMessage;
				} else {
					//We got the list object but is there anything in it?
					if ($retval['total'] == 0) {
						$response['success'] = false;
						$response['error_message'] = "Connected to MailChimp.com but you have no lists.";
					}else{
						$response['success'] = true;
						foreach ($retval['data'] as $list) {
							$response['list'][$list['id']] = $list['name'];
						}
					}
				}

			}else{
				//The API key is missing.
				$response['success'] = false;
				$response['error_message'] = 'Mail Chimp API key is missing';
			}
			
			return $response;

		}
		
   
    };
    

/*
    Omit PHP closing tag to help avoid accidental output
*/