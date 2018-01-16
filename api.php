<?php
/**
 * LSF内部API入口文件
 * 仲伟涛
 * 2012-6-26
 */
if(function_exists("__autoload")){
    die("[LSF_API]__autoload冲突，请修改为spl_autoload_register形式！");
}

define('IN_LSF_API', true);

//API根目录
defined('LSF_API_BASE') || define('LSF_API_BASE', dirname(__FILE__) );
defined('LSF_API_ROOT') || define('LSF_API_ROOT', LSF_API_BASE . '/API');
defined('LSF_API_UTF8') || define('LSF_API_UTF8', false);
defined('LSF_API_DEBUG') || define('LSF_API_DEBUG', false);

//引入配置文件
//require_once(LSF_API_BASE . '/config.php');

spl_autoload_register(array('LSF_Api', 'autoload'));

//框架会清除掉cookie等信息，所以先保存
LSF_Api::$_globalVars['_COOKIE'] = $_COOKIE;

if (!function_exists('get_called_class')){
    function get_called_class()    {
        $bt = debug_backtrace();
        $lines = file($bt[1]['file']);
        preg_match('/([a-zA-Z0-9\_]+)::'.$bt[1]['function'].'/',
               $lines[$bt[1]['line']-1],
               $matches);
        return $matches[1];
    }
}


class LSF_Api
{
    private static $_namespace = array();
    public static $_globalVars = array(); #全局变量，比如LSF框架会将$_COOKIE unset掉，所以这个需要提前将$_COOKIE保存起来
    public static $_nowMethod  = false; #当前执行的方法
    /**
     * 自动加载
     */
    public static function autoload($name)
    {
        if (trim($name) == '') {
            new exception('No class or interface named for loading');
        }

        if (class_exists($name, false) || interface_exists($name, false)) {
            return;
        }

        $namespace = substr($name, 0, strpos($name, '_'));

        $file = '';
        if ($namespace == 'API') {
            $file = LSF_API_BASE . '/' . str_replace('_', DIRECTORY_SEPARATOR, $name) . '.php';
        }
        // 对个性的命名空间做处理
        elseif (isset(self::$_namespace[$namespace])){
            $file = self::$_namespace[$namespace] . '/' . str_replace('_', DIRECTORY_SEPARATOR, $name) . '.php';
        }
        if($file){
            include $file;

            if (! class_exists($name, false) && ! interface_exists($name, false)) {
                throw new exception('Class or interface does not exist in loaded file');
            }
        }
    }
    
    /**
     * 使用namespace方法实现每个实例的命名空间映射
     */
    public static function setNameSpace($path)
    {
        if (empty($path)) {
            new exception('No class or interface named for loading');
        }
        $namespace = substr(strrchr($path, '/'), 1);
        $namespacePath = substr($path, 0, strlen($path) - strlen($namespace) - 1);
        if (!isset(self::$_namespace[$namespace]) || self::$_namespace[$namespace] != $namespacePath) {
            self::$_namespace[$namespace] = $namespacePath;
        } else {
            throw new exception('Class or interface does not exist in loaded file');
        }
    }

    
}

