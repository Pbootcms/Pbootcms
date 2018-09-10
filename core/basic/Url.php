<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @license This is not a freeware, use is subject to license terms
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年11月6日
 *  生成指定模块下控制器方法的跳转路径
 */
namespace core\basic;

class Url
{

    // 存储已经生成过的地址信息
    private static $urls = array();

    // 接收控制器方法完整访问路径，如：/home/Index/index /模块/控制器/方法/.. 路径，生成可访问地址
    public static function get($path, $addExt = true)
    {
        if (strpos($path, 'http') === 0) {
            return $path;
        }
        if (! $path)
            return;
        
        if (! isset(self::$urls[$path])) {
            // 未正常生成的，则无法跳转，如：未绑定的模块无法跳转！
            $cut_str = '';
            $host = '';
            if ($addExt) {
                $url_ext = Config::get('url_suffix'); // 地址后缀
            } else {
                $url_ext = '';
            }
            $path = trim_slash($path); // 去除两端斜线
                                       
            // 路由处理
            if (! ! $routes = Config::get('url_route')) {
                foreach ($routes as $key => $value) {
                    $value = trim_slash($value); // 去除两端斜线
                    if (strpos($path, $value . '/') === 0) {
                        $path = str_replace($value . '/', $key . '/', $path);
                        $route = true;
                        break;
                    } elseif ($path == $value) {
                        $path = $key;
                        $route = true;
                        break;
                    }
                }
            }
            
            // 域名绑定处理匹配
            if (! ! $domains = Config::get('app_domain_blind')) {
                foreach ($domains as $key => $value) {
                    $value = trim_slash($value); // 去除两端斜线
                    if (strpos($path, $value . '/') === 0) {
                        $cut_str = $value; // 需要截掉的地址字符
                        $server_name = $_SERVER['SERVER_NAME'];
                        if ($server_name != $key) { // 绑定的域名与当前域名不一致时，添加主机地址
                            $host = is_https() ? 'https://' . $key : 'http://' . $key;
                        }
                        break;
                    }
                }
            }
            
            // 入口文件绑定匹配
            if (defined('URL_BLIND')) {
                $url_blind = trim_slash(URL_BLIND);
                // 已经匹配过域名绑定
                if ($cut_str) {
                    // 地址中域名绑定不包含入口绑定且入口绑定中包含域名绑定
                    if (strpos($cut_str, $url_blind) === false && strpos($url_blind, $cut_str) === 0) {
                        $cut_str = $url_blind;
                    }
                } else {
                    $cut_str = $url_blind;
                }
            }
            
            // 执行URL简化
            if ($cut_str) {
                $path = substr($path, strlen($cut_str) + 1);
            }
            
            // 保存处理过的地址
            if ($path) {
                self::$urls[$path] = $host . self::getPrePath() . '/' . $path . $url_ext;
            } else {
                self::$urls[$path] = $host . self::getPrePath();
            }
        }
        return self::$urls[$path];
    }

    // 获取地址前缀
    private static function getPrePath()
    {
        if (! isset(self::$urls['prepath'])) {
            $indexfile = $_SERVER["SCRIPT_NAME"];
            if (Config::get('url_type') == 1) { // 普通PATHINFO
                $pre_path = $indexfile;
            } elseif (Config::get('url_type') == 2) { // PATHINFO重写模式
                if (strrpos($indexfile, 'index.php') === false) {
                    $pre_path = $indexfile;
                } else {
                    $pre_path = SITE_DIR;
                }
            } else { // 兼容模式
                $pre_path = $indexfile . '?s=';
            }
            self::$urls['prepath'] = $pre_path;
        }
        return self::$urls['prepath'];
    }
}