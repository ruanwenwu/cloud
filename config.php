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
defined('LSF_API_LOGLEVEL') || define('LSF_API_LOGLEVEL', E_ALL ^ E_NOTICE);//定义错误级别
defined('LSF_API_LOG') || define('LSF_API_LOG', LSF_API_ROOT . "/Log");

//引入配置文件
require_once(LSF_API_BASE . '/config.php');


spl_autoload_register(array('LSF_Api', 'autoload'));
if(!LSF_API_ISFW ){ //如果不是LSF框架，配置自动加载,模拟一下框架的相关文件
    // 将LSF_Api的自动加载包含进来
    foreach (array('Db','LSF') as $nv) {
        LSF_Api::setNameSpace(LSF_API_ROOT . '/' . $nv);
    }
    
}

//if(LSF_API_ISFW ){#LSF框架
    #框架会将$_COOKIE unset掉，所以这个需要提前将$_COOKIE保存起来
    LSF_Api::$_globalVars['_COOKIE'] = $_COOKIE;
//}

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
/**
 * json的相关处理
 */
function api_json_encode($value){
  array_walk_recursive($value, "api_json_convert_encoding_g2u");
  return json_encode($value);
}
function api_json_decode($value, $assoc = true){
  $value = json_decode($value,$assoc);
  array_walk_recursive($value, "api_json_convert_encoding_u2g");
  return $value;
}
function api_json_convert_encoding_g2u(&$value, &$key){
  $value = mb_convert_encoding($value, "UTF-8", "GBK");
}
function api_json_convert_encoding_u2g(&$value, &$key){
  $value = mb_convert_encoding($value, "GBK", "UTF-8");
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
        if(LSF_API_ISFW && in_array($namespace, array('Db','LSF')) ){ #框架的自动加载加载
            return ;
        }
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
                throw new LSF_Exception('Class or interface does not exist in loaded file');
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

    /**
     * 执行API的方法
     */
    public static function run($method,$param=false){
        if(!$method)return false;
        
        $method = str_replace(".", "_", $method);
        $class = "API_Item_" . substr($method, 0,strrpos($method,"_"));
        $func  = substr($method, strrpos($method,"_")+1);
        self::$_nowMethod = $method;//通过私有云run调用
        $data  = call_user_func_array(array($class, $func), array($param));
        
        #返回结果的处理
        #格式如id,name,age  *(id,name)  id,row(tm,name)  colorArr(*(name))
        if($data && is_array($data) && isset($param['rtnCols']) && $param['rtnCols']){
            $level1Arr = array();#第一维
            $level2Arr = array();#第二维
            $level3Arr = array();#第三维
            #下面正则的作用：因为不过多维都是用逗号分割字段，id,row(tm(se,fr),name) 不好区分，
            #第三维用# 第二维用+ id,row(tm(se#fr)+name)
            $rtnCols   = preg_replace_callback("#\(.*\(.*(,).*\).*\)#isU", create_function( '$matches', 'return str_replace(",","#",$matches[0]);' ),$param['rtnCols'] );
            $rtnCols   = preg_replace_callback("#\(.*(,).*\)#isU", create_function( '$matches', 'return str_replace(",","+",$matches[0]);' ), $rtnCols);
            $tmpArr    = explode(",", $rtnCols);
            $l1Star    = $l2Star = false; #第一维是否是*
            foreach($tmpArr as $v){
                $v = trim($v);
                if($lp  = strpos($v, '(')){
                    $v2 = substr($v,$lp+1,-1);
                    $v  = substr($v,0,$lp);
                    $l1Star = ($v == '*');

                    #第三维的处理
                    if($lp2  = strpos($v2, '(')){

                        $v3     = substr($v2,$lp2+1,-1);
                        $v2     = substr($v2,0,$lp2);
                        $l2Star = ($v2 == '*');
                        $level3Arr[$v2] = explode("#", $v3);
                    }
                    $level2Arr[$v]=explode("+", $v2);
                }
                $level1Arr[] = $v;
            }
            $newData = array();
            #对返回数据进行字段过滤处理
            foreach($data as $kk => $vv){
                #符合第一维的限制
                if($l1Star || in_array($kk, $level1Arr)){#如果是*，或是指定的key
                    #如果第二维有限制
                    if((isset($level2Arr[$kk]) || isset($level2Arr['*']) ) && is_array($vv)){
                        $newVv = array();
                        foreach ($vv as $kk2=>$vv2){
                            #echo $kk;
                            #print_r($level2Arr[$kk]);

                            if((isset($level2Arr[$kk]) && (in_array($kk2, $level2Arr[$kk]) || in_array('*', $level2Arr[$kk])))
                                || (isset($level2Arr['*']) && in_array($kk2, $level2Arr['*']))){
                                
                                #如果第三维有限制 如 color(*(name))
                                if((isset($level3Arr[$kk2]) || isset($level3Arr['*']) ) && is_array($vv2)){
                                    $newVv2 = array();
                                    foreach ($vv2 as $kk3=>$vv3){
                                        if((isset($level3Arr[$kk2]) && in_array($kk3, $level3Arr[$kk2]))
                                           || (isset($level3Arr['*']) && in_array($kk3, $level3Arr['*']))){
                                            $newVv2[$kk3] = $vv3;
                                        }
                                    }

                                    $vv2 = $newVv2;
                                }

                                $newVv[$kk2] = $vv2;
                            }
                        }
                        $vv = $newVv;
                    }
                    $newData[$kk] = $vv;
                }
            }
            $data = $newData;
        }//<<返回数据处理完毕
        
        if(LSF_API_UTF8 && $data && !defined("LSF_API_UTF8_FORBID")){
            $data = self::toUTF8($data);
           # array_walk_recursive($data, "api_json_convert_encoding_g2u");
            return $data;

        }
        return $data;
    }


    /**
     * UTF8的转换
     */
    private static function toUTF8($input){
		if(is_string($input)){
			return mb_convert_encoding($input, 'UTF-8', 'GBK');
		}elseif(is_array($input)){
			$output = array();
			foreach ($input as $k=>$v){
                $k          = self::toUTF8($k);
				$output[$k] = self::toUTF8($v);
			}
			return $output;
		}else{
            return $input;
        }
	}
}

#配置日志日志记录级别 
if(defined('LSF_API_LOGLEVEL') && !defined('LSF_API_STOPLOG')){
    //set_error_handler(array('API_Item_Service_Log', 'logHandler'),LSF_API_LOGLEVEL);
    //register_shutdown_function(array('API_Item_Service_Log', 'shutdownHandler'));
    //set_exception_handler(array('API_Item_Service_Log', 'exceptionHandler'));
}

function P($name,$die=1){
    echo '<pre>';
    var_dump($name);
    if($die){
        die;
    }
}