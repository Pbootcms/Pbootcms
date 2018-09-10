<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @license This is not a freeware, use is subject to license terms
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年2月7日
 *  生成验证码
 */
use core\extend\code\Code;

// 引入验证码类
require dirname(__FILE__) . '/init.php';

// 记录验证码
session_start(); // 启动会话
                 
// 初始化验证码
$code = new Code();
$code->height = 45;
$code->width = 120;
$code->fontsize = 18;
$code->charset = '123456789';
$code->doimg();
session('checkcode', $code->getCode());
