<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @license This is not a freeware, use is subject to license terms
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年2月14日
 *  详细内容页控制器
 */
namespace app\home\controller;

use core\basic\Controller;
use app\home\model\ParserModel;

class ContentController extends Controller
{

    protected $parser;

    protected $model;

    public function __construct()
    {
        $this->parser = new ParserController();
        $this->model = new ParserModel();
    }

    // 内容详情
    public function index()
    {
        if (! ! $id = get('id', 'vars')) {
            // 读取数据
            if (! $data = $this->model->getContent($id)) {
                error('您访问的内容不存在，请核对后重试！');
            }
            // 读取模板
            if (! ! $sort = $this->model->getSort($data->scode)) {
                if ($sort->contenttpl) {
                    $content = parent::parser($sort->contenttpl); // 框架标签解析
                    $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
                    $content = $this->parser->parserPositionLabel($content, $sort->scode); // CMS当前位置标签解析
                    $content = $this->parser->parserSortLabel($content, $sort); // CMS分类信息标签解析
                    $content = $this->parser->parserCurrentContentLabel($content, $sort, $data); // CMS内容标签解析
                    $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
                } else {
                    error('请到后台设置分类栏目内容页模板！');
                }
            } else {
                error('您访问内容的分类已经不存在，请核对后再试！');
            }
        } else {
            error('您访问的地址有误，必须传递内容id参数！');
        }
        $this->cache($content, true);
    }

    // 空拦截
    public function _empty()
    {
        error('您访问的地址有误，请核对后重试！');
    }
}