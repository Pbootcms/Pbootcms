<?php
/**
 * @ copyright (C)2016-2099 Hnaoyun Inc.
 * @ license This is not a freeware, use is subject to license terms
 * @ author XingMeng
 * @ email hnxsh@foxmail.com
 * @ date 2017年4月14日
 * 公共处理函数
 */
use core\basic\Config;

// 获取字符串型自动编码
function get_auto_code($string, $start = '1')
{
    if (! $string)
        return $start;
    if (is_numeric($string)) { // 如果纯数字则直接加1
        return sprintf('%0' . strlen($string) . 's', $string + 1);
    } else { // 非纯数字则先分拆
        $reg = '/^([a-zA-Z-_]+)([0-9]+)$/';
        $str = preg_replace($reg, '$1', $string); // 字母部分
        $num = preg_replace($reg, '$2', $string); // 数字部分
        return $str . sprintf('%0' . (strlen($string) - strlen($str)) . 's', $num + 1);
    }
}

// 获取指定分类列表
function get_type($tcode)
{
    $type_model = model('admin.system.Type');
    if (! ! $result = $type_model->getItem($tcode)) {
        return $result;
    } else {
        return array();
    }
}

// 解析无限菜单为HTML
function make_tree_html($tree, $name, $url, $sonName = 'son')
{
    $tree_html = '';
    foreach ($tree as $value) {
        if (is_array($value)) {
            if ($value[$sonName] == '') {
                $tree_html .= "<li><a href='" . url($value[$url]) . "'>{$value[$name]}</a></li>";
            } else {
                $tree_html .= "<li><a href='" . url($value[$url]) . "'>{$value[$name]}</a>";
                $tree_html .= make_tree_html($value[$sonName], $name, $url, $sonName);
                $tree_html = $tree_html . "</li>";
            }
        } else {
            $menu_url = '';
            if (! $value->pcode) { // 顶级菜单指向第一个子菜单
                if (isset($value->son[0])) {
                    $menu_url = url($value->son[0]->url);
                }
            } else {
                $menu_url = url($value->$url);
            }
            if ($value->$sonName == '') {
                $tree_html .= "<li><a href='$menu_url'>{$value->$name}</a></li>";
            } else {
                $tree_html .= "<li><a href='$menu_url'>{$value->$name}</a>";
                $tree_html .= make_tree_html($value->$sonName, $name, $url, $sonName);
                $tree_html = $tree_html . "</li>";
            }
        }
    }
    return $tree_html ? '<ul>' . $tree_html . '</ul>' : $tree_html;
}

// 生成区域选择
function make_area_Select($tree, $selectid = null)
{
    $list_html = '';
    global $blank;
    foreach ($tree as $values) {
        // 默认选择项
        if ($selectid == $values->acode) {
            $select = "selected='selected'";
        } else {
            $select = '';
        }
        
        // 禁用父栏目选择功能
        if ($values->son) {
            $disabled = "disabled='disabled'";
        } else {
            $disabled = '';
        }
        $list_html .= "<option value='{$values->acode}' $select $disabled>{$blank}{$values->acode} {$values->name}";
        
        // 子菜单处理
        if ($values->son) {
            $blank .= '　　';
            $list_html .= make_area_Select($values->son, $selectid);
        }
    }
    // 循环完后回归位置
    $blank = substr($blank, 0, - 6);
    return $list_html;
}

// 检测指定的方法是否拥有权限
function check_level($btnAction, $isPath = false)
{
    $user_level = session('levels');
    if ($isPath) {
        if (in_array($btnAction, $user_level)) {
            return true;
        }
    } else {
        if (in_array('/' . M . '/' . C . '/' . $btnAction, $user_level) || session('id') == 1) {
            return true;
        }
    }
}

// 获取返回按钮
function get_btn_back($btnName = '返 回')
{
    if (! ! $backurl = get('backurl')) {
        $url = base64_decode($backurl);
    } elseif (isset($_SERVER["HTTP_REFERER"])) {
        $url = $_SERVER["HTTP_REFERER"];
    } else {
        $url = url('/' . M . '/' . C . '/index');
    }
    
    $btn_html = "<a href='" . $url . "' class='layui-btn layui-btn-primary'>$btnName</a>";
    return $btn_html;
}

// 获取新增按钮
function get_btn_add($btnName = '新 增')
{
    $user_level = session('levels');
    if (! in_array('/' . M . '/' . C . '/add', $user_level) && session('id') != 1)
        return;
    $btn_html = "<a href='" . url("/" . M . '/' . C . "/add") . "?backurl=" . base64_encode(URL) . "' class='layui-btn layui-btn-primary'>$btnName</a>";
    return $btn_html;
}

// 获取更多按钮
function get_btn_more($idValue, $id = 'id', $btnName = '详情')
{
    $btn_html = "<a href='" . url("/" . M . '/' . C . "/index/$id/$idValue") . "' class='layui-btn layui-btn-xs layui-btn-primary' title='$btnName'>$btnName</a>";
    return $btn_html;
}

// 获取删除按钮
function get_btn_del($idValue, $id = 'id', $btnName = '删除')
{
    $user_level = session('levels');
    if (! in_array('/' . M . '/' . C . '/del', $user_level) && session('id') != 1)
        return;
    $btn_html = "<a href='" . url('/' . M . '/' . C . "/del/$id/$idValue") . "' onclick='return confirm(\"您确定要删除么？\")' class='layui-btn layui-btn-xs layui-btn-danger' title='$btnName'>$btnName</a>";
    return $btn_html;
}

// 获取修改按钮
function get_btn_mod($idValue, $id = 'id', $btnName = '修改')
{
    $user_level = session('levels');
    if (! in_array('/' . M . '/' . C . '/mod', $user_level) && session('id') != 1)
        return;
    $btn_html = "<a href='" . url("/" . M . '/' . C . "/mod/$id/$idValue") . "?backurl=" . base64_encode(URL) . "'  class='layui-btn layui-btn-xs'>$btnName</a>";
    return $btn_html;
}

// 获取其它按钮
function get_btn($btnName, $theme, $btnAction, $idValue, $id = 'id')
{
    $user_level = session('levels');
    if (! in_array('/' . M . '/' . C . '/' . $btnAction, $user_level) && session('id') != 1)
        return;
    $btn_html = "<a href='" . url("/" . M . '/' . C . "/$btnAction/$id/$idValue") . "?backurl=" . base64_encode(URL) . "'  class='layui-btn layui-btn-xs $theme'>$btnName</a>";
    return $btn_html;
}

// 缓存基础信息
function cache_config($refresh = false)
{
    // 判断是否已经设置语言
    $lg = isset($_COOKIE['lg']) ? cookie('lg') : '';
    $path = RUN_PATH . '/config/' . md5('config' . $lg) . '.php';
    if (! file_exists($path) || $refresh) {
        
        $model = model('admin.system.Config');
        
        // 系统配置
        $data = $model->getConfig();
        
        // 获取区域信息
        $area = $model->getArea();
        if (! isset($area[0])) {
            error('系统没有任何可用区域，请核对后再试！');
        }
        $data['lgs'] = $area;
        
        // 获取系统设置的主题
        if (! preg_match('/^[\w]+$/', $lg)) { // 过滤非法参数
            unset($_COOKIE['lg']);
            $lg = '';
        }
        if (! ! $theme = $model->getTheme($lg ?: $area[0]['acode'])) {
            $data['theme'] = $theme;
        } else {
            $data['theme'] = 'default';
        }
        Config::set(md5('config' . $lg), $data, false); // 缓存语言配置并注入
    } else {
        Config::assign($path);
    }
}



