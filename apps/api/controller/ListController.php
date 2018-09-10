<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @license This is not a freeware, use is subject to license terms
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年4月20日
 *  内容列表接口控制器
 */
namespace app\api\controller;

use core\basic\Controller;
use app\api\model\CmsModel;

class ListController extends Controller
{

    protected $model;

    public function __construct()
    {
        $this->model = new CmsModel();
    }

    public function index()
    {
        // 获取参数
        $acode = get('acode', 'var') ?: $this->config('lgs.0.acode');
        $scode = get('scode', 'var') ?: - 1;
        $num = get('num', 'int') ?: $this->config('pagesize');
        $order = get('order', 'var') ?: 'date';
        switch ($order) {
            case 'date':
            case 'istop':
            case 'isrecommend':
            case 'isheadline':
            case 'visits':
            case 'likes':
            case 'oppose':
                $order = $order . ' DESC';
                break;
            default:
                $order = $order . ' ASC';
        }
        $order .= ",sorting ASC,id DESC";
        
        // 读取数据
        $data = $this->model->getList($acode, $scode, $num, $order);
        
        foreach ($data as $key => $value) {
            if ($value->outlink) {
                $data[$key]->link = $data->outlink;
            } else {
                $data[$key]->link = url('/api/list/index/scode/' . $data[$key]->id, false);
            }
            $data[$key]->likeslink = url('/home/Do/likes/id/' . $data[$key]->id, false);
            $data[$key]->opposelink = url('/home/Do/oppose/id/' . $data[$key]->id, false);
            $data[$key]->content = str_replace('/static/upload/', get_http_url() . '/static/upload/', $data[$key]->content);
        }
        
        // 输出数据
        if (get('page') <= PAGECOUNT) {
            json(1, $data);
        } else {
            return json(0, '已经到底了！');
        }
    }
}