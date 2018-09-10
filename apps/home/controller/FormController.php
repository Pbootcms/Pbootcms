<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @license This is not a freeware, use is subject to license terms
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年6月1日
 *  表单控制器
 */
namespace app\home\controller;

use app\home\model\ParserModel;
use core\basic\Controller;

class FormController extends Controller
{

    protected $model;

    public function __construct()
    {
        $this->model = new ParserModel();
    }

    // 表单提交
    public function add()
    {
        if ($_POST) {
            
            if (! $fcode = get('fcode', 'var')) {
                alert_back('传递的表单编码有误！');
            }
            
            if ($fcode == 1) {
                alert_back('留言提交请使用留言专用地址!');
            }
            
            // 验证码验证
            $checkcode = post('checkcode');
            if ($this->config('message_check_code')) {
                if (! $checkcode) {
                    alert_back('验证码不能为空！');
                }
                if ($checkcode != session('checkcode')) {
                    alert_back('验证码错误！');
                }
            }
            
            // 读取字段
            if (! $form = $this->model->getFormField($fcode)) {
                alert_back('接收表单不存在任何字段，请核对后重试！');
            }
            
            // 接收数据
            $mail_body = '';
            foreach ($form as $value) {
                $field_data = post($value->name);
                if (is_array($field_data)) { // 如果是多选等情况时转换
                    $field_data = implode(',', $field_data);
                }
                if ($value->required && ! $field_data) {
                    alert_back($value->description . '不能为空！');
                } else {
                    $data[$value->name] = post($value->name);
                    $mail_body .= $value->description . '：' . post($value->name) . '<br>';
                }
            }
            
            // 设置创建时间
            if ($data) {
                $data['create_time'] = get_datetime();
            }
            
            // 写入数据
            if ($this->model->addForm($value->table_name, $data)) {
                $this->log('提交表单数据成功！');
                if ($this->config('message_send_mail') && $this->config('message_send_to')) {
                    $mail_subject = "【PbootCMS】您有新的表单数据，请注意查收！";
                    $mail_body .= '<br>来自网站' . get_http_url() . '（' . date('Y-m-d H:i:s') . '）';
                    sendmail($this->config(), $this->config('message_send_to'), $mail_subject, $mail_body);
                }
                alert_location('提交成功！', '-1');
            } else {
                $this->log('提交表单数据失败！');
                alert_back('提交失败！');
            }
        } else {
            error('提交失败，请使用POST方式提交！');
        }
    }

    // 空拦截
    public function _empty()
    {
        error('您访问的地址有误，请核对后重试！');
    }
}

