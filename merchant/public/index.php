<?php
	error_reporting(0); //E_WARNING|E_PARSE|E_STRICT|E_ERROR
	ini_set('display_errors', false);
	date_default_timezone_set('America/Los_Angeles');
	header("'Content-type: text/html;charset=utf-8");
	
	$rootDir = dirname(dirname(__FILE__));
	$_SERVER['TMPDIR'] = 'tmp/';
	define('APPLICATION_PATH',$rootDir.'/application/');
	set_include_path($rootDir . '/application/models'. PATH_SEPARATOR.$rootDir . '/library'. PATH_SEPARATOR . get_include_path());
	
	//automatic get baseUrl,add by jason 3.05.2009
	$baseUrl=str_replace(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']),'',str_replace('\\','/',$rootDir));
	$baseUrl=($baseUrl&&$baseUrl{0}=='/'?'':'/').$baseUrl;

	require_once 'Zend/Loader/Autoloader.php';
	Zend_Loader_Autoloader::getInstance()->setFallbackAutoloader(true);
	
	try {
		require '../application/bootstrap.php';
	} catch (Exception $exception) {
		echo '<html><body><center>' . 'An exception occured while bootstrapping the application.';
		if (defined('APPLICATION_ENVIRONMENT') && APPLICATION_ENVIRONMENT != 'production') {
			echo '<br /><br />' . $exception->getMessage() . '<br />' . '<div align="left">Stack Trace:' . '<pre>' . $exception->getTraceAsString() . '</pre></div>';
		}
		echo '</center></body></html>';
		exit(1);
	}	

	// setup controller
	$frontController = Zend_Controller_Front::getInstance(); 
	//$frontController->throwExceptions(true); 
	$frontController->setControllerDirectory('../application/controllers')
	                ->setBaseUrl($baseUrl); 
	
	$frontController->dispatch();
