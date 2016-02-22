<?php
	/**
		Copyright (c) 2003 etiusa.com
	*/
	
	define('HTTP_SERVER', 'http://mic.payment.com');
	
	define('HTTPS_SERVER', 'https://mic.payment.com');
	
	define('ENABLE_SSL', 'false');
	
	define('DIR_RP_CATALOG', '/');
	
	define('DB_SERVER', 'localhost');
	
	define('DB_SERVER_USERNAME', 'root');
	
	define('DB_SERVER_PASSWORD', 'root');
	
	define('DB_DATABASE', 'scpay');
	
	define('DB_TABLE_PREFIX', '');
	
	define('DIR_AP_CATALOG', dirname(dirname(__FILE__)).'/');
	
	// define our database connection
	define('USE_PCONNECT', 'false'); 	// use persistent connections?
	define('STORE_SESSIONS', 'mysql'); 	// leave empty '' for default handler or set to 'mysql'
?>