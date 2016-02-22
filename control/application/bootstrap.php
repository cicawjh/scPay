<?php
	require_once 'Zend/Session.php';
	require_once 'Zend/Session/Namespace.php';
	Zend_Session::start();
	$scnsp = new Zend_Session_Namespace("Default");
	$scnsp->setExpirationSeconds(86400);

	//读取控制文件
	$config = new Zend_Config_Ini('../application/config.ini.php', 'general');
	$register = Zend_Registry::getInstance();
	$register->set('config', $config);

	$scnsp->mail_parameter = array(
								'server'=>$config->smtp->params->server,
								'username'=>$config->smtp->params->username,
								'password'=>$config->smtp->params->password, 
								'port'=>$config->smtp->params->port,
								'ssl'=>$config->smtp->params->ssl,
								'sendby'=>$config->smtp->params->sendby,
								'sendname'=>$config->smtp->params->sendname,
							);
	
	$db_parameter = array(
			'host'		=>	$config->db->params->host,
			'username'	=>	$config->db->params->username,
			'password'	=>	$config->db->params->password,
			'dbname'	=>	$config->db->params->dbname
		);
	
	//添加适配器
	// DATABASE ADAPTER - Setup the database adapter
	// Zend_Db implements a factory interface that allows developers to pass in an
	// adapter name and some parameters that will create an appropriate database
	// adapter object.  In this instance, we will be using the values found in the
	// "database" section of the configuration obj.
	$dbAdapter = Zend_Db::factory($config->db->adapter, $db_parameter);
	

	// DATABASE TABLE SETUP - Setup the Database Table Adapter
	// Since our application will be utilizing the Zend_Db_Table component, we need 
	// to give it a default adapter that all table objects will be able to utilize 
	// when sending queries to the db.
	Zend_Db_Table::setDefaultAdapter($dbAdapter);

	// REGISTRY - setup the application registry
	// An application registry allows the application to store application 
	// necessary objects into a safe and consistent (non global) place for future 
	// retrieval.  This allows the application to ensure that regardless of what 
	// happends in the global scope, the registry will contain the objects it 
	// needs.
	
	//$registry->dbAdapter = $dbAdapter;
	$dbAdapter->query("SET NAMES utf8;");
	Zend_Registry::set('db', $dbAdapter);
	Zend_Registry::set('scnsp', $scnsp);
	
	//add table name
	require('../application/db_tables.php');
	
	//setup layout
	Zend_Layout::startMvc('../application/layouts/scripts');