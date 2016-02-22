<?php
	/*
		v 2.0.0.1 2006/11/01  

		http://www.shoppingrun.com

		Copyright (c) 2003-2006 chengdu XieRui Infomation Tech. Ltd,.

	*/
 define('MYSITE_IS_DEBUG','false');
 if (MYSITE_IS_DEBUG == 'false') 
 { 
	 ini_set('display_errors',false); 
	 ini_set('display_startup_errors',false);
	 error_reporting(0);
 }
 else 
 {
	 ini_set('display_errors',true); 
	 error_reporting(E_ALL & ~E_NOTICE);
 }

 date_default_timezone_set('America/Los_Angeles');

 if (file_exists("config/configure.php")) {
	require("config/configure.php");
 } else {
	exit();
 }

 if (isset($_SERVER['HTTPS'])) {
     $request_type = (getenv('HTTPS') == 'on') ? 'SSL' : 'NONSSL';
 } elseif (isset($_SERVER['SCRIPT_URI'])) {
     $request_type = (substr(strtolower(getenv('SCRIPT_URI')), 0, 5) == 'https') ? 'SSL' : 'NONSSL';
 } else {
     $request_type = 'NONSSL';
 }

 require("db_tables.php");
 require("functions/database.php");
 gf_db_connect()or die('Unable to connect to database server!');
/*
 $configuration_query = gf_db_query('select configuration_key as cfgKey, configuration_value as cfgValue from ' . TABLE_CONFIGURATIONS);

 while ($configuration = gf_db_fetch_array($configuration_query)) {
     define($configuration['cfgKey'], $configuration['cfgValue']);
 }
*/
 require("functions/general.php");
 require("classes/cc_validation.php");
 require("classes/ipvalidation.php");
 //require("functions/sessions.php");
 //gf_session_start();
 ?>