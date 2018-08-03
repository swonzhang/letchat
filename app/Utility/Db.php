<?php

namespace App\Utility;

use Illuminate\Database\Capsule\Manager as Capsule;//如果你不喜欢这个名称，as DB;就好 


class Db
{

	protected static $instance;



	public static function getInstance()
	{
		if (!isset(self::$instance)) {
			self::$instance = self::connect();
		}
		return self::$instance;
	}

	public static function connect(){

		$database = [ 
		     'driver'    => 'mysql',
		     'host'      => 'http://192.168.3.11',
		     'database'  => 'chat',
		     'username'  => 'root',
		     'password'  => '',
		     'charset'   => 'utf8',
		     'collation' => 'utf8_general_ci',
		     'prefix'    => ''
		 ];

	     // 初始化数据库
	    $capsule = new Capsule;
	    // 创建链接
	    $capsule->addConnection($database);
	    // 设置全局静态可访问
	    $capsule->setAsGlobal(); 
	    // 启动Eloquent
	    $capsule->bootEloquent();

	    return $capsule;

	}
}