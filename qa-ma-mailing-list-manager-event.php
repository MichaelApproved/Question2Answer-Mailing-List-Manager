<?php

/*
	(c) 2012, Michael Khalili

	http://www.michaelapproved.com/

    File: qa-plugin/ma-mailing-list-manager/qa-ma-mailing-list-manager-event.php
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

	class qa_ma_mlm_event {
		
		var $directory;
        var $urltoroot;
		
		//this runs before the module is used.
        function load_module($directory, $urltoroot)
        {
			//file system path to the plugin directory
            $this->directory=$directory;
                        
			//url path to the plugin relative to the current page request.
            $this->urltoroot=$urltoroot;
			
        }

		function process_event($event, $userid, $handle, $cookieid, $params)
		{
			//Did the user just confirm themselves and are we adding them to the mailing list when they do?
			//|| Did the user just register themselves and are we adding them to the mailing list when they do?

			//create the merge_vars for the subscriber ip.
			$merge_vars = array('OPTIN_IP' => $_SERVER['REMOTE_ADDR']);
			
			//Is this a registration event?
			if ($event == 'u_register') {
				//Congratulations, we've got a new member.
				
				//grab the serialized settings from db and unserialize them (if they exist)
				$saveSettingsSerialized = qa_opt('ma_mlm_settings');

				if ($saveSettingsSerialized == ''){
					$saveSettings = array();
				}else{
					$saveSettings = unserialize($saveSettingsSerialized);
				}

				
				//Is the Mail Chimp api key set along with settings for a list?
				if (isset($saveSettings['mc_api_key']) && 
					isset($saveSettings['list'])) {
				
					//loop through and look for an enabled list that also wants to display a checkbox on the registration page
					foreach($saveSettings['list'] as $listId => $listSettings) {

						//Is this list enabled AND ( 
						//	(asking for a checkbox on the registration page AND it's been checked?)
						//  OR is a checkbox not needed and this is an automatic subscription)
						if ($listSettings['enabled'] && 
								( ($listSettings['regcheckbox'] && (int)qa_post_text('ma_mlm_mc_list_regsubscribe_' . $listId))
								|| $listSettings['regcheckbox'] == false) ) {
							
							//We have a subscriber. Do we need to wait for them to confirm or can we subscribe them right now?
							if ($listSettings['afterconf']) {
								//Add record to the confirm table to be subscribed after the user confirms.
								//While it's possible to not have to add the subscription with regcheckbox == false and confirmation (because we can check for this later,
								//It's easier to follow the logic if we handle all cases similarly here.
								
								//serialize the current settings to store in the db. This keeps the terms of the membership the same throughout the registration process.
								$listSettingsSerialized = serialize($listSettings);

								//the listProvider is 'mc' for MailChimp. This allows the plug-in to be expanded to handle other providers without altering the table in the future.
								qa_db_query_sub(
									'INSERT INTO ^mamlmConfirm (userId, created, listProvider, listId, listSettings) 
											VALUES (#, NOW(), \'mc\', #, #)',
										$userid, $listId, $listSettingsSerialized
									);
								
								
							}else{
								//No need to wait, subscribe them now.
								
								//Add the Mail Chimp API lib and create the api object only once in the loop.
								if (isset($api) == false){
									require_once $this->directory . '/mcapi/MCAPI.class.php';

									$api = new MCAPI($saveSettings['mc_api_key']);
								}

								//pull the members email address only once in the loop.
								if (isset($regEmail) == false){
									require_once QA_INCLUDE_DIR . 'qa-db-users.php';
									require_once QA_INCLUDE_DIR . 'qa-db-selects.php';

									$userinfo=qa_db_select_with_pending(qa_db_user_account_selectspec($userid, true));
									$regEmail = $userinfo['email'];
								}


								//call the MC api and request the subscribe.
								//listSubscribe($id, $email_address, $merge_vars=NULL, $email_type='html', $double_optin=true, $update_existing=false, $replace_interests=true, $send_welcome=false)
								$retval = $api->listSubscribe( $listId , $regEmail, $merge_vars, 'html', $listSettings['confsend'], true, false, false);

								if ($api->errorCode) {
									echo "Unable to load listSubscribe()!\n";
									echo "\tCode=".$api->errorCode."\n";
									echo "\tMsg=".$api->errorMessage."\n";
									die();
								} else {
									//Success
									//echo "Subscribed - look for the confirmation email!\n";
								}			

							}
							
							
						}
						
					}	
					
					
					
				}

				
			}
			
			if ($event == 'u_confirmed') {
				//The new member confirmed.
				
				//grab the serialized settings from db and unserialize them (if they exist)
				$saveSettingsSerialized = qa_opt('ma_mlm_settings');

				if ($saveSettingsSerialized == ''){
					$saveSettings = array();
				}else{
					$saveSettings = unserialize($saveSettingsSerialized);
				}

				
				//Is the Mail Chimp api key set along with settings for a list?
				if (isset($saveSettings['mc_api_key']) && 
					isset($saveSettings['list'])) {

					//look for any records requiring subscription.
					$sqlQuery = 'Select userId, created, ListProvider, listId, listSettings
						FROM ^mamlmConfirm
						WHERE userId = #';

					//execute the SQL
					$result = qa_db_query_sub( $sqlQuery, $userid );

					//retrieve the data
					$confirmRecords = qa_db_read_all_assoc($result);

					//loop through the records and subscribe the member to the list.
					foreach ($confirmRecords as $confirmRecord) {
						//Add the Mail Chimp API lib and create the api object only once in the loop.
						if (isset($api) == false){
							require_once $this->directory . '/mcapi/MCAPI.class.php';

							$api = new MCAPI($saveSettings['mc_api_key']);
						}

						//pull the members email address only once in the loop.
						if (isset($regEmail) == false){
							require_once QA_INCLUDE_DIR . 'qa-db-users.php';
							require_once QA_INCLUDE_DIR . 'qa-db-selects.php';

							$userinfo=qa_db_select_with_pending(qa_db_user_account_selectspec($userid, true));
							$regEmail = $userinfo['email'];
						}

						//grab the specific settings for this request.
						$listSettingsSerialized = $confirmRecord['listSettings'];
						$listSettings = unserialize($listSettingsSerialized);

						//listSubscribe($id, $email_address, $merge_vars=NULL, $email_type='html', $double_optin=true, $update_existing=false, $replace_interests=true, $send_welcome=false)
						$retval = $api->listSubscribe( $confirmRecord['listId'] , $regEmail, $merge_vars, 'html', $listSettings['confsend'], true, false, false);

						if ($api->errorCode) {
							echo "Unable to load listSubscribe()!\n";
							echo "\tCode=".$api->errorCode."\n";
							echo "\tMsg=".$api->errorMessage."\n";
							die();
						} else {
							//Success
							//echo "Subscribed - look for the confirmation email!\n";
						}			
						
						//remove the record from the database.
						$sqlQuery = 'Delete FROM ^mamlmConfirm
							WHERE userId = #
								AND listId = #';

						//execute the SQL
						$result = qa_db_query_sub( $sqlQuery, $userid, $confirmRecord['listId']);

					
					}

				}
				
			}
			
		}
	
	};
	

/*
	Omit PHP closing tag to help avoid accidental output
*/