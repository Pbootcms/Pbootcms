<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @license This is not a freeware, use is subject to license terms
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年2月14日
 *  标签解析引擎模型
 */
namespace app\home\model;

use core\basic\Model;

class ParserModel extends Model
{

    // 存储分类及子编码
    protected $scodes = array();

    // 存储分类查询数据
    protected $sorts;

    // 存储栏目位置
    protected $position = array();

    // 上一篇
    protected $pre;

    // 下一篇
    protected $next;

    // 站点配置信息
    public function getSite()
    {
        return parent::table('ay_site')->where("acode='" . session('lg') . "'")->find();
    }

    // 公司信息
    public function getCompany()
    {
        return parent::table('ay_company')->where("acode='" . session('lg') . "'")->find();
    }

    // 自定义标签
    public function getLabel()
    {
        return parent::table('ay_label')->decode()->column('value', 'name');
    }

    // 单个分类信息
    public function getSort($scode)
    {
        $field = array(
            'a.id',
            'a.pcode',
            'c.name AS parentname',
            'a.scode',
            'a.name',
            'a.subname',
            'b.type',
            'a.filename',
            'a.outlink',
            'a.listtpl',
            'a.contenttpl',
            'a.ico',
            'a.pic',
            'a.keywords',
            'a.description',
            'a.sorting'
        );
        $join = array(
            array(
                'ay_model b',
                'a.mcode=b.mcode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.pcode=c.scode',
                'LEFT'
            )
        );
        return parent::table('ay_content_sort a')->field($field)
            ->where("a.acode='" . session('lg') . "'")
            ->where("a.scode='$scode' OR a.filename='$scode'")
            ->join($join)
            ->find();
    }

    // 多个分类信息
    public function getMultSort($scodes)
    {
        $field = array(
            'a.id',
            'a.pcode',
            'c.name AS parentname',
            'a.scode',
            'a.name',
            'a.subname',
            'b.type',
            'a.filename',
            'a.outlink',
            'a.listtpl',
            'a.contenttpl',
            'a.ico',
            'a.pic',
            'a.keywords',
            'a.description',
            'a.sorting'
        );
        $join = array(
            array(
                'ay_model b',
                'a.mcode=b.mcode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.pcode=c.scode',
                'LEFT'
            )
        );
        return parent::table('ay_content_sort a')->field($field)
            ->where("a.acode='" . session('lg') . "'")
            ->in('a.scode', $scodes)
            ->join($join)
            ->select();
    }

    // 分类栏目列表关系树
    public function getSortsTree()
    {
        $fields = array(
            'a.id',
            'a.pcode',
            'a.scode',
            'a.name',
            'a.subname',
            'b.type',
            'a.filename',
            'a.outlink',
            'a.listtpl',
            'a.contenttpl',
            'a.ico',
            'a.pic',
            'a.keywords',
            'a.description',
            'a.sorting'
        );
        $join = array(
            'ay_model b',
            'a.mcode=b.mcode',
            'LEFT'
        );
        $result = parent::table('ay_content_sort a')->where("a.acode='" . session('lg') . "'")
            ->where('a.status=1')
            ->join($join)
            ->order('a.pcode,a.sorting,a.id')
            ->column($fields, 'scode');
        
        foreach ($result as $key => $value) {
            if ($value['pcode']) {
                $result[$value['pcode']]['son'][] = $value; // 记录到关系树
            } else {
                $data['top'][] = $value; // 记录顶级菜单
            }
        }
        $data['tree'] = $result;
        return $data;
    }

    // 获取分类名称
    public function getSortName($scode)
    {
        $result = $this->getSortList();
        return $result[$scode]['name'];
    }

    // 分类顶级编码
    public function getSortTopScode($scode)
    {
        $result = $this->getSortList();
        return $this->getTopParent($scode, $result);
    }

    // 获取位置
    public function getPosition($scode)
    {
        $result = $this->getSortList();
        $this->position = array(); // 重置
        $this->getTopParent($scode, $result);
        return array_reverse($this->position);
    }

    // 分类顶级编码
    private function getTopParent($scode, $sorts)
    {
        if (! $scode || ! $sorts) {
            return;
        }
        $this->position[] = $sorts[$scode];
        if ($sorts[$scode]['pcode']) {
            return $this->getTopParent($sorts[$scode]['pcode'], $sorts);
        } else {
            return $sorts[$scode]['scode'];
        }
    }

    // 分类子类集
    private function getSubScodes($scode)
    {
        if (! $scode) {
            return;
        }
        $this->scodes[] = $scode;
        $subs = parent::table('ay_content_sort')->where("pcode='$scode'")->column('scode');
        if ($subs) {
            foreach ($subs as $value) {
                $this->getSubScodes($value);
            }
        }
        return $this->scodes;
    }

    // 获取栏目清单
    private function getSortList()
    {
        if (! isset($this->sorts)) {
            $fields = array(
                'a.id',
                'a.pcode',
                'a.scode',
                'a.name',
                'a.filename',
                'a.outlink',
                'b.type'
            );
            $join = array(
                'ay_model b',
                'a.mcode=b.mcode',
                'LEFT'
            );
            $this->sorts = parent::table('ay_content_sort a')->where("a.acode='" . session('lg') . "'")
                ->join($join)
                ->column($fields, 'scode');
        }
        return $this->sorts;
    }

    // 获取筛选字段数据
    public function getSelect($field)
    {
        return parent::table('ay_extfield')->where("name='$field'")->value('value');
    }

    // 列表内容
    public function getList($scode, $num, $order, $filter = array(), $where = array(), $fuzzy = true)
    {
        $fields = array(
            'a.*',
            'b.name as sortname',
            'b.filename as sortfilename',
            'c.name as subsortname',
            'c.filename as subfilename',
            'd.type',
            'e.*'
        );
        $join = array(
            array(
                'ay_content_sort b',
                'a.scode=b.scode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.subscode=c.scode',
                'LEFT'
            ),
            array(
                'ay_model d',
                'b.mcode=d.mcode',
                'LEFT'
            ),
            array(
                'ay_content_ext e',
                'a.id=e.contentid',
                'LEFT'
            )
        );
        
        $where1 = '';
        if ($scode) {
            // 获取所有子类分类编码
            $this->scodes = array(); // 先清空
            $arr = explode(',', $scode);
            foreach ($arr as $value) {
                $scodes = $this->getSubScodes(trim($value));
            }
            // 拼接条件
            $where1 = array(
                "a.scode in (" . implode_quot(',', $scodes) . ")",
                "a.subscode='$scode'"
            );
        }
        
        $where2 = array(
            "a.acode='" . session('lg') . "'",
            'a.status=1',
            'd.type=2'
        );
        
        // 筛选条件支持模糊匹配
        return parent::table('ay_content a')->field($fields)
            ->where($where1, 'OR')
            ->where($where2)
            ->where($where, 'AND', 'AND', $fuzzy)
            ->where($filter, 'OR', 'AND')
            ->join($join)
            ->order($order)
            ->page(1, $num)
            ->decode()
            ->select();
    }

    // 指定列表内容，不分页
    public function getSpecifyList($scode, $num, $order, $filter = array(), $where = array(), $fuzzy = true)
    {
        $fields = array(
            'a.*',
            'b.name as sortname',
            'b.filename as sortfilename',
            'c.name as subsortname',
            'c.filename as subfilename',
            'd.type',
            'e.*'
        );
        $join = array(
            array(
                'ay_content_sort b',
                'a.scode=b.scode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.subscode=c.scode',
                'LEFT'
            ),
            array(
                'ay_model d',
                'b.mcode=d.mcode',
                'LEFT'
            ),
            array(
                'ay_content_ext e',
                'a.id=e.contentid',
                'LEFT'
            )
        );
        $this->scodes = array(); // 先清空
                                 
        // 获取多分类子类
        $arr = explode(',', $scode);
        foreach ($arr as $value) {
            $scodes = $this->getSubScodes(trim($value));
        }
        
        // 拼接条件
        $where1 = array(
            "a.scode in (" . implode_quot(',', $scodes) . ")",
            "a.subscode='$scode'"
        );
        $where2 = array(
            "a.acode='" . session('lg') . "'",
            'a.status=1',
            'd.type=2'
        );
        
        return parent::table('ay_content a')->field($fields)
            ->where($where1, 'OR')
            ->where($where2)
            ->where($where, 'AND', 'AND', $fuzzy)
            ->where($filter, 'OR', 'AND')
            ->join($join)
            ->order($order)
            ->limit($num)
            ->decode()
            ->select();
    }

    // 内容详情
    public function getContent($id)
    {
        $field = array(
            'a.*',
            'b.name as sortname',
            'b.filename as sortfilename',
            'c.name as subsortname',
            'c.filename as subfilename',
            'd.type',
            'e.*'
        );
        $join = array(
            array(
                'ay_content_sort b',
                'a.scode=b.scode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.subscode=c.scode',
                'LEFT'
            ),
            array(
                'ay_model d',
                'b.mcode=d.mcode',
                'LEFT'
            ),
            array(
                'ay_content_ext e',
                'a.id=e.contentid',
                'LEFT'
            )
        );
        $result = parent::table('ay_content a')->field($field)
            ->where("a.id='$id' OR a.filename='$id'")
            ->where("a.acode='" . session('lg') . "'")
            ->where('a.status=1')
            ->join($join)
            ->decode()
            ->find();
        return $result;
    }

    // 单篇详情
    public function getAbout($scode)
    {
        $field = array(
            'a.*',
            'b.name as sortname',
            'b.filename as sortfilename',
            'c.name as subsortname',
            'c.filename as subfilename',
            'd.type',
            'e.*'
        );
        $join = array(
            array(
                'ay_content_sort b',
                'a.scode=b.scode',
                'LEFT'
            ),
            array(
                'ay_content_sort c',
                'a.subscode=c.scode',
                'LEFT'
            ),
            array(
                'ay_model d',
                'b.mcode=d.mcode',
                'LEFT'
            ),
            array(
                'ay_content_ext e',
                'a.id=e.contentid',
                'LEFT'
            )
        );
        $result = parent::table('ay_content a')->field($field)
            ->where("a.scode='$scode' OR b.filename='$scode'")
            ->where("a.acode='" . session('lg') . "'")
            ->where('a.status=1')
            ->join($join)
            ->decode()
            ->order('id DESC')
            ->find();
        return $result;
    }

    // 指定内容多图
    public function getContentPics($id)
    {
        $result = parent::table('ay_content')->where("id='$id'")
            ->where("acode='" . session('lg') . "'")
            ->where('status=1')
            ->value('pics');
        return $result;
    }

    // 指定内容多选调用
    public function getContentCheckbox($id, $field)
    {
        $result = parent::table('ay_content_ext')->where("contentid='$id'")->value($field);
        return $result;
    }

    // 上一篇内容
    public function getContentPre($scode, $id)
    {
        if (! $this->pre) {
            $this->scodes = array();
            $scodes = $this->getSubScodes($scode);
            $this->pre = parent::table('ay_content')->field('id,title,filename')
                ->where("id<$id")
                ->in('scode', $scodes)
                ->where("acode='" . session('lg') . "'")
                ->where('status=1')
                ->order('id DESC')
                ->find();
        }
        return $this->pre;
    }

    // 下一篇内容
    public function getContentNext($scode, $id)
    {
        if (! $this->next) {
            $this->scodes = array();
            $scodes = $this->getSubScodes($scode);
            $this->next = parent::table('ay_content')->field('id,title,filename')
                ->where("id>$id")
                ->in('scode', $scodes)
                ->where("acode='" . session('lg') . "'")
                ->where('status=1')
                ->order('id ASC')
                ->find();
        }
        return $this->next;
    }

    // 幻灯片
    public function getSlides($gid, $num)
    {
        $result = parent::table('ay_slide')->where("gid='$gid'")
            ->where("acode='" . session('lg') . "'")
            ->order('sorting ASC,id ASC')
            ->limit($num)
            ->select();
        return $result;
    }

    // 友情链接
    public function getLinks($gid, $num)
    {
        $result = parent::table('ay_link')->where("gid='$gid'")
            ->where("acode='" . session('lg') . "'")
            ->order('sorting ASC,id ASC')
            ->limit($num)
            ->select();
        return $result;
    }

    // 获取留言
    public function getMessage($num)
    {
        return parent::table('ay_message')->where("status=1")
            ->where("acode='" . session('lg') . "'")
            ->order('id DESC')
            ->decode(false)
            ->page(1, $num)
            ->select();
    }

    // 新增留言
    public function addMessage($data)
    {
        return parent::table('ay_message')->autoTime()->insert($data);
    }

    // 获取表单字段
    public function getFormField($fcode)
    {
        $field = array(
            'a.table_name',
            'b.name',
            'b.required',
            'b.description'
        );
        
        $join = array(
            'ay_form_field b',
            'a.fcode=b.fcode',
            'LEFT'
        );
        
        return parent::table('ay_form a')->field($field)
            ->where("a.fcode='$fcode'")
            ->join($join)
            ->order('b.sorting ASC,b.id ASC')
            ->select();
    }

    // 获取表单表名称
    public function getFormTable($fcode)
    {
        return parent::table('ay_form')->where("fcode='$fcode'")->value('table_name');
    }

    // 获取表单数据
    public function getForm($table, $num)
    {
        return parent::table($table)->order('id DESC')
            ->decode(false)
            ->page(1, $num)
            ->select();
    }

    // 新增表单数据
    public function addForm($table, $data)
    {
        return parent::table($table)->insert($data);
    }
}