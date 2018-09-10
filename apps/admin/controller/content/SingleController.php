<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @license This is not a freeware, use is subject to license terms
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date  2017年12月15日
 *  单页内容控制器
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\SingleModel;

class SingleController extends Controller
{

    private $model;

    private $blank;

    public function __construct()
    {
        $this->model = new SingleModel();
    }

    // 单页内容列表
    public function index()
    {
        if ((! ! $id = get('id', 'int')) && $result = $this->model->getSingle($id)) {
            $this->assign('more', true);
            $this->assign('content', $result);
        } else {
            $this->assign('list', true);
            if (! ! ($field = get('field', 'var')) && ! ! ($keyword = get('keyword', 'vars'))) {
                $result = $this->model->findSingle($field, $keyword);
            } else {
                $result = $this->model->getList();
            }
            $this->assign('contents', $result);
        }
        $this->display('content/single.html');
    }

    // 单页内容删除
    public function del()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        if ($this->model->delSingle($id)) {
            $this->log('删除单页内容' . $id . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除单页内容' . $id . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 单页内容修改
    public function mod()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 单独修改状态
        if (($field = get('field', 'var')) && ! is_null($value = get('value', 'var'))) {
            if ($this->model->modSingle($id, "$field='$value',update_user='" . session('username') . "'")) {
                location(- 1);
            } else {
                alert_back('修改失败！');
            }
        }
        
        // 修改操作
        if ($_POST) {
            
            // 获取数据
            $title = post('title');
            $content = post('content');
            $author = post('author');
            $source = post('source');
            $ico = post('ico');
            $pics = post('pics');
            
            $titlecolor = post('titlecolor');
            $subtitle = post('subtitle');
            $outlink = post('outlink');
            $date = post('date');
            $enclosure = post('enclosure');
            $keywords = post('keywords');
            $description = post('description');
            $status = post('status', 'int');
            
            if (! $title) {
                alert_back('单页内容标题不能为空！');
            }
            
            // 自动提起前一百个字符为描述
            if (! $description && isset($_POST['content'])) {
                $description = mb_substr(strip_tags($_POST['content']), 0, 150, 'utf-8');
            }
            
            // 缩放缩略图
            if ($ico) {
                resize_img(ROOT_PATH . $ico, '', $this->config('ico.max_width'), $this->config('ico.max_height'));
            }
            
            // 构建数据
            $data = array(
                'title' => $title,
                'content' => $content,
                'author' => $author,
                'source' => $source,
                'ico' => $ico,
                'pics' => $pics,
                'titlecolor' => $titlecolor,
                'subtitle' => $subtitle,
                'outlink' => $outlink,
                'date' => $date,
                'enclosure' => $enclosure,
                'keywords' => $keywords,
                'description' => $description,
                'status' => $status,
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->modSingle($id, $data)) {
                
                // 扩展内容修改
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'ext_') === 0) {
                        $temp = post($key);
                        if (is_array($temp)) {
                            $data2[$key] = implode(',', $temp);
                        } else {
                            $data2[$key] = str_replace("\r\n", '<br>', $temp);
                        }
                    }
                }
                if (isset($data2)) {
                    if ($this->model->findContentExt($id)) {
                        $this->model->modContentExt($id, $data2);
                    } else {
                        $data2['contentid'] = $id;
                        $this->model->addContentExt($data2);
                    }
                }
                
                $this->log('修改单页内容' . $id . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', base64_decode($backurl));
                } else {
                    success('修改成功！', url('/admin/Single/index'));
                }
            } else {
                location(- 1);
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getSingle($id)) {
                error('编辑的内容已经不存在！', - 1);
            }
            $this->assign('content', $result);
            
            // 扩展字段
            if (! $mcode = get('mcode', 'var')) {
                error('传递的模型编码参数有误，请核对后重试！');
            }
            $this->assign('extfield', model('admin.content.ExtField')->getModelField($mcode));
            
            $this->display('content/single.html');
        }
    }
}