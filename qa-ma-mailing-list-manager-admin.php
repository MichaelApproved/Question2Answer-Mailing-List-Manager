<?php

/*
	(c) 2011, Michael Khalili

	http://www.michaelapproved.com/

    File: qa-plugin/ma-mailing-list-manager/qa-ma-mailing-list-manager-admin.php
    Version: 1.0
    Date: 2011-10-20
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
            if ($option == 'ma_mlm_mc_api_key') {
                return '';
            }elseif ($option == 'ma_mlm_mc_lists') {
				return '';
			}elseif ($option == 'ma_mlm_mc_subscribe_new_members') {
				return false;
			}elseif ($option == 'ma_mlm_mc_send_confirmation_email') {
				return false;
			}elseif ($option == 'ma_mlm_mc_confirmed_only') {
				return true;
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
			
            //has the save button been pressed?
            if (qa_clicked('ma_mlm_save_button')) {
                
                //save the MC api key
                qa_opt('ma_mlm_mc_api_key', qa_post_text('ma_mlm_mc_api_key'));
               
				//save the MC list to use.
				qa_opt('ma_mlm_mc_lists', qa_post_text('ma_mlm_mc_lists'));

				//save the option to only add members who have confirmed.
				qa_opt('ma_mlm_mc_confirmed_only', (int)qa_post_text('ma_mlm_mc_confirmed_only'));

				//save the option to send out a confirmation email.
				qa_opt('ma_mlm_mc_send_confirmation_email', (int)qa_post_text('ma_mlm_mc_send_confirmation_email'));
				
				//save the value of whether we should subscribe new members.
				qa_opt('ma_mlm_mc_subscribe_new_members', (int)qa_post_text('ma_mlm_mc_subscribe_new_members'));

				
                //mark form as saved
                $saved=true;
				
				//set the message to display
				$okDisplay = 'Options saved';
				
				//show the settings
				$showSettings = true;
            }
			
			//Is the user trying to import all existing members?
			if (qa_clicked('ma_mlm_mc_subscribe_existing_members') && 
								qa_post_text('ma_mlm_mc_subscribe_existing_members_confirm')) {
				
				//Execute the batch process
				$batchSubscribeResult = $this->mcBatchSubscribe();

				if ($batchSubscribeResult['success']) {
					$okDisplay = 'Import success! Added: ' . $batchSubscribeResult['stats']['added'] . 
							'. Updated: ' . $batchSubscribeResult['stats']['updated'] . 
							'. Errored: ' . $batchSubscribeResult['stats']['errored'] . '.';
					
					//display the import report as a textarea
					$importReportTextarea = array(
                        'label' => 'Import report',
                        'type' => 'textarea',
                        'rows' => '10',
                        'value' => $batchSubscribeResult['import_report']
                    );
							
				}else{
					$okDisplay = 'Import failed! ' . $batchSubscribeResult['error_message'];
				}
			}
			
			//Show the settings if the "Show Settings" or "Subscribe Existing Members" buttons are clicked
			if (qa_clicked('ma_mlm_show_settings') || qa_clicked('ma_mlm_mc_subscribe_existing_members')) {
				//show the settings
				$showSettings = true;
			}
			
			
			if ($showSettings) {
				//run the code that gets the Mail Chimp lists
				$this->mcNewsletterLists = $this->mcLists();

				//build the form.
				//'ok' displays a message above the form. Used here to display a success message if the form has been saved.
				//files contains an array of field options.
				$form=array(
					'ok' => $okDisplay,

					//lets ask for the MailChimp API information
					'fields' => array(
						'mc_api_key' => array(
							'label' => 'MailChimp.com API Key',
							'type' => 'textbox',
							'value' => qa_opt('ma_mlm_mc_api_key'),
							'tags' => 'NAME="ma_mlm_mc_api_key"',
							'error' => array_key_exists('error_message', $this->mcNewsletterLists) ? $this->mcNewsletterLists['error_message'] : ''
						),
					),

					'buttons' => array(
						array(
							'label' => 'Save Changes',
							'tags' => 'NAME="ma_mlm_save_button"',
						),
					),
				);

				//Was there an import report textarea created? If so, show it.
				if (is_array($importReportTextarea)) {
					$form['fields']['mc_import_report'] = $importReportTextarea;
				}

				//Were we able to pull up the newsletter list?
				if ($this->mcNewsletterLists['success']) {
					$mcListSelectedMsg = '';
					$mcListSelectedErrorMsg = '';
					$mcListSelectedValue = '';

					//make sure there's been a list selected previously
					if (qa_opt('ma_mlm_mc_lists') != '') {

						//does this list still exist?
						if (array_key_exists(qa_opt('ma_mlm_mc_lists'), $this->mcNewsletterLists['list'])) {
							$mcListSelectedValue = $this->mcNewsletterLists['list'][qa_opt('ma_mlm_mc_lists')];
							$mcListSelectedMsg = "Members will be added to: $mcListSelectedValue";
						}else{
							$mcListSelectedErrorMsg = 'Previously saved list does not exist anymore (list id: ' . qa_opt('ma_mlm_mc_lists') . ')';
						}
					}

					//display a dropdown of the lists
					$form['fields']['mc_lists'] = array(
						'label' => 'Lists',
						'type' => 'select',
						'value' => $mcListSelectedValue,
						'options' => $this->mcNewsletterLists['list'],
						'note' => $mcListSelectedMsg,
						'error' => $mcListSelectedErrorMsg,
						'tags' => 'NAME="ma_mlm_mc_lists"',
					);

					//Add the subscribe new members checkbox.
					$form['fields']['mc_subscribe_new_members'] = array(
						'label' => 'Automatically subscribe new members.',
						'type' => 'checkbox',
						'value' => qa_opt('ma_mlm_mc_subscribe_new_members'),
						'error' => ($mcListSelectedValue == '') ? 'You must select a list first' : '',
						'tags' => 'NAME="ma_mlm_mc_subscribe_new_members"',
					);

					//Add the option to send a confirmation email when adding someone to the list.
					$form['fields']['mc_confirmed_only'] = array(
						'label' => 'Only include members who have confirmed their email.',
						'type' => 'checkbox',
						'value' => qa_opt('ma_mlm_mc_confirmed_only'),
						'tags' => 'NAME="ma_mlm_mc_confirmed_only"',
					);

					//Add the option to send a confirmation email when adding someone to the list.
					$form['fields']['mc_send_confirmation_email'] = array(
						'label' => 'Send out a confirmation email when adding someone.',
						'type' => 'checkbox',
						'value' => qa_opt('ma_mlm_mc_send_confirmation_email'),
						'tags' => 'NAME="ma_mlm_mc_send_confirmation_email"',
					);
					
					


					//Add the add all existing members confirmation checkbox
					$form['fields']['mc_subscribe_existing_members_confirm'] = array(
						'label' => 'Check this box to confirm you\'d like to add all existing members to your Mail Chimp list.',
						'type' => 'checkbox',
						'value' => false,
						'error' => (qa_clicked('ma_mlm_mc_subscribe_existing_members') && 
									qa_post_text('ma_mlm_mc_subscribe_existing_members_confirm') == false) ? 'You must check the box above to confirm.' : '',
						'tags' => 'NAME="ma_mlm_mc_subscribe_existing_members_confirm"',
					);

					//Add the button to import all existing members
					$form['buttons']['mc_subscribe_existing_members'] = array(
						'label' => 'Subscribe all existing members',
						'tags' => 'NAME="ma_mlm_mc_subscribe_existing_members"',
					);


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
			if (qa_opt('ma_mlm_mc_api_key') != '') {

				//Add the Mail Chimp API lib
				require_once $this->directory . '/mcapi/MCAPI.class.php';

				//Create the object with the stored API key
				$api = new MCAPI(qa_opt('ma_mlm_mc_api_key'));

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
		
		//One time, import all existing members into the MC list
		function mcBatchSubscribe() {
			//make sure the api key is set first
			if (qa_opt('ma_mlm_mc_api_key') != '') {
				//Add the Mail Chimp API lib
				require_once $this->directory . '/mcapi/MCAPI.class.php';
				
				//Create the object with the stored API key
				$api = new MCAPI(qa_opt('ma_mlm_mc_api_key'));

				//Build the SQL needed to select all member's email addresses

				if (qa_opt('ma_mlm_mc_confirmed_only')) {
					$users = qa_db_query_sub('SELECT email FROM ^users WHERE flags&#', QA_USER_FLAGS_EMAIL_CONFIRMED);
				}else{
					$users = qa_db_query_sub('SELECT email FROM ^users');
				}

				//prime the value so it wont error out if nothing was returned.
				$batch = null;
				
				while ( ($email=qa_db_read_one_value($users,true)) !== null ) {
					$batch[] = array('EMAIL'=>$email);
				}
												
				if (count($batch) == 0) {
					//Nothing to send MC so kick back an error.
					$response['success'] = false;
					$response['error_message'] .= "There are no confirmed members.";
				}else{
					$optin = qa_opt('ma_mlm_mc_send_confirmation_email'); //Should we send the email to opt in?
					$up_exist = true; // yes, update currently subscribed users
					$replace_int = false; // no, add interest, don't replace

					//send the batch to MC
					$vals = $api->listBatchSubscribe(qa_opt('ma_mlm_mc_lists'),$batch,$optin, $up_exist, $replace_int);

					//How'd the process go?
					if ($api->errorCode){
						//Not good. Put together the error code and kick it back.
						$response['success'] = false;
						$response['error_message'] = "Batch Subscribe failed! Code: " . $api->errorCode . ". Msg: " . $api->errorMessage . ".";
					} else {
						$response['success'] = true;

						$response['stats']['added'] = array_key_exists('add_count', $vals) ? $vals['add_count'] : 0;
						$response['stats']['updated'] = array_key_exists('update_count', $vals) ? $vals['update_count'] : 0;
						$response['stats']['errored'] = array_key_exists('error_count', $vals) ? $vals['error_count'] : 0;

						$report = '';
						$report .= "Added:   " . $response['stats']['added'] . "\n";
						$report .= "Updated: " . $response['stats']['updated'] . "\n";
						$report .= "Errors:  " . $response['stats']['errored'] . "\n";

						foreach($vals['errors'] as $val){
							$report .= $val['email_address'] . "\tfailed\t" . $val['code'] . "\t" . $val['message'] . "\n";
						}

						$response['import_report'] = $report;
					}
				}
				
				return $response;
			}
		}
    
    };
    

/*
    Omit PHP closing tag to help avoid accidental output
*/