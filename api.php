<?php
/**
 * LSF�ڲ�API����ļ�
 * ��ΰ��
 * 2012-6-26
 */
if(function_exists("__autoload")){
    die("[LSF_API]__autoload��ͻ�����޸�Ϊspl_autoload_register��ʽ��");
}

define('IN_LSF_API', true);

//API��Ŀ¼
defined('LSF_API_BASE') || define('LSF_API_BASE', dirname(__FILE__) );
defined('LSF_API_ROOT') || define('LSF_API_ROOT', LSF_API_BASE . '/API');
defined('LSF_API_UTF8') || define('LSF_API_UTF8', false);
defined('LSF_API_DEBUG') || define('LSF_API_DEBUG', false);

//���������ļ�
//require_once(LSF_API_BASE . '/config.php');

spl_autoload_register(array('LSF_Api', 'autoload'));

//��ܻ������cookie����Ϣ�������ȱ���
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
    public static $_globalVars = array(); #ȫ�ֱ���������LSF��ܻὫ$_COOKIE unset�������������Ҫ��ǰ��$_COOKIE��������
    public static $_nowMethod  = false; #��ǰִ�еķ���
    /**
     * �Զ�����
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
        // �Ը��Ե������ռ�������
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
     * ʹ��namespace����ʵ��ÿ��ʵ���������ռ�ӳ��
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

