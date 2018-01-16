<?php

class API_Db_Lsf extends API_Db_Abstract_Pdo
{
	protected $servers   = array(
		'engner' => 'mysql',
	    'master' => array(
	     'host' => '101.200.168.135',
	     'database' => 'lsf',

	    ),
	    'slave' => array(
	       'host' => '101.200.168.135',
	       'database' => 'lsf',
	    ),
	    'charset'  => 'utf8',
	    'username'=> 'ruanwenwu',
	    'password'=> 'hiv1605'
	);
}
