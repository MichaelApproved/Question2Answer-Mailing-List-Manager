<?php

/*
	(c) 2012, Michael Khalili

	http://www.michaelapproved.com/

    File: qa-plugin/ma-mailing-list-manager/qa-ma-mailing-list-manager-page.php
    Version: 1.5
    Date: 2012-11-06
	Description: exports the email addresses from your list.


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

class qa_ma_mlm_page
{
	private $directory;
	private $urltoroot;

	function load_module( $directory, $urltoroot )
	{
		$this->directory = $directory;
		$this->urltoroot = $urltoroot;
	}


	function match_request( $request )
	{
		if ($request=='admin/ma-mlm-export')
			return true;

		return false;
	}

	function process_request( $request )
	{
		$level = qa_get_logged_in_level();

		if($level == null || $level < QA_USER_LEVEL_ADMIN)
			qa_fatal_error('Only admins can access this page');

		
		if (isset($_GET['confirmed'])){
			$users = qa_db_query_sub('SELECT email FROM ^users WHERE flags&#', QA_USER_FLAGS_EMAIL_CONFIRMED);
			$filename = 'email-export-confirmed.txt';
		}else{
			$users = qa_db_query_sub('SELECT email FROM ^users');
			$filename = 'email-export.txt';
		}
		
		header("Content-Disposition: attachment; filename=$filename");
		while ( ($email=qa_db_read_one_value($users,true)) !== null ) {
			echo "$email\n";
		}
		die();
		
	}



}


