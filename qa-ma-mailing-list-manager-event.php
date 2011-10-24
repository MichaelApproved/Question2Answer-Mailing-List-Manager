<?php

/*
	(c) 2011, Michael Khalili

	http://www.michaelapproved.com/

    File: qa-plugin/ma-mailing-list-manager/qa-ma-mailing-list-manager-event.php
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
			$addSubscriber = ( ($event == 'u_confirmed') && qa_opt('ma_mlm_mc_subscribe_new_members') && qa_opt('ma_mlm_mc_confirmed_only') ||
					( ($event == 'u_register') && qa_opt('ma_mlm_mc_subscribe_new_members') && !qa_opt('ma_mlm_mc_confirmed_only') ) );
			
			

			//Should we add the subscriber? Make sure there's also an API key and list selected before we try to.
			if ($addSubscriber 
					&& qa_opt('ma_mlm_mc_api_key') != ''
					&& qa_opt('ma_mlm_mc_lists') != '') {
				
				//Add the Mail Chimp API lib
				require_once $this->directory . '/mcapi/MCAPI.class.php';
				
				//Create the object with the stored API key
				$api = new MCAPI(qa_opt('ma_mlm_mc_api_key'));
				
				//pull the members email address
				require_once QA_INCLUDE_DIR.'qa-db-users.php';
				require_once QA_INCLUDE_DIR.'qa-db-selects.php';

				$userinfo=qa_db_select_with_pending(qa_db_user_account_selectspec($userid, true));
				
				//listSubscribe($id, $email_address, $merge_vars=NULL, $email_type='html', $double_optin=true, $update_existing=false, $replace_interests=true, $send_welcome=false)
				$retval = $api->listSubscribe( qa_opt('ma_mlm_mc_lists') , $userinfo['email'], NULL, 'html', qa_opt('ma_mlm_mc_send_confirmation_email'), true, false, false);

				if ($api->errorCode){
					echo "Unable to load listSubscribe()!\n";
					echo "\tCode=".$api->errorCode."\n";
					echo "\tMsg=".$api->errorMessage."\n";
				} else {
					echo "Subscribed - look for the confirmation email!\n";
				}
			}
			
		}
	
	};
	

/*
	Omit PHP closing tag to help avoid accidental output
*/