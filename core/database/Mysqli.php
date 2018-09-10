<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @license This is not a freeware, use is subject to license terms
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年11月6日
 *  数据库mysqli驱动
 */
namespace core\database;

use core\basic\Config;

class Mysqli implements Builder
{

    protected static $mysqli;

    protected $master;

    protected $slave;

    protected $commit;

    private function __construct()
    {}

    // 获取单一实例，使用单一实例数据库连接类
    public static function getInstance()
    {
        if (! self::$mysqli) {
            self::$mysqli = new self();
        }
        return self::$mysqli;
    }

    // 连接数据库，接受数据库连接参数，返回数据库连接对象
    public function conn($cfg)
    {
        if (! extension_loaded('mysqli')) {
            die('未检测到您服务器环境的mysqli数据库扩展，请检查php.ini中是否已经开启该扩展！');
        }
        // 优化>php5.3版本 在win2008以上服务器连接
        if ($cfg['host'] == 'localhost') {
            $cfg['host'] = '127.0.0.1';
        }
        $conn = @new \Mysqli($cfg['host'], $cfg['user'], $cfg['passwd'], $cfg['dbname'], $cfg['dbport']);
        if (mysqli_connect_errno()) {
            error("连接数据库服务器失败：" . iconv('gbk', 'utf-8', mysqli_connect_error()));
        }
        $charset = Config::get('database.charset') ?: 'utf8';
        $conn->set_charset($charset); // 设置编码
        return $conn;
    }

    // 关闭自动提交，开启事务模式
    public function closeCommit()
    {
        $this->master->autocommit(false);
        $this->commit = true;
    }

    // 提交事务
    public function commit()
    {
        $this->master->commit(); // 提交事务
        $this->master->autocommit(true); // 提交后恢复自动提交
        $this->commit = false; // 关闭事务模式
    }

    // 执行SQL语句,接受完整SQL语句，返回结果集对象
    public function query($sql, $type = 'master')
    {
        $time_s = microtime(true);
        switch ($type) {
            case 'master':
                if (! $this->master) {
                    $cfg = Config::get('database');
                    $this->master = $this->conn($cfg);
                    $this->master->query("SET sql_mode='NO_ENGINE_SUBSTITUTION'"); // 写入规避严格模式
                }
                $result = $this->master->query($sql);
                if (! $result) {
                    if ($this->commit) { // 如果是事务模式，发生错误，则回滚
                        $this->master->rollback();
                    }
                    $this->error($sql, 'master');
                }
                break;
            case 'slave':
                if (! $this->slave) {
                    // 未设置从服务器时直接读取主数据库配置
                    if (! $cfg = Config::get('database.slave')) {
                        $cfg = Config::get('database');
                    } else {
                        // 随机选择从数据库
                        if (is_multi_array($cfg)) {
                            $count = count($cfg);
                            $cfg = $cfg['slave' . mt_rand(1, $count)];
                        }
                    }
                    $this->slave = $this->conn($cfg);
                }
                $result = $this->slave->query($sql) or $this->error($sql, 'slave');
                break;
        }
        return $result;
    }

    // 数据是否存在模型，接受完整SQL语句，返回boolean数据
    public function isExist($sql)
    {
        $result = $this->query($sql, 'slave');
        if ($result->num_rows) {
            $result->free();
            return true;
        } else {
            return false;
        }
    }

    // 获取记录总量模型，接受数据库表名，返回int数据
    public function rows($table)
    {
        $sql = "SELECT count(*) FROM $table";
        $result = $this->query($sql, 'slave');
        if (! ! $row = $result->fetch_array(2)) {
            $result->free();
            return $row[0];
        } else {
            return 0;
        }
    }

    // 读取字段数量模型，接受数据库表名，返回int数据
    public function fields($table)
    {
        $sql = "SELECT * FROM $table LIMIT 1";
        $result = $this->query($sql, 'slave');
        if ($result) {
            return $result->field_count;
        } else {
            return 0;
        }
    }

    /**
     * 获取表信息,接受数据库表名，返回表字段信息数组
     *
     * @param $table 表名            
     */
    public function tableFields($table)
    {
        $sql = "describe $table";
        $result = $this->query($sql, 'slave');
        $rows = array();
        if ($this->slave->affected_rows) {
            while (! ! $row = $result->fetch_object()) {
                $rows[] = $row->Field;
            }
            $result->free();
        }
        return $rows;
    }

    /**
     * 查询一条数据模型，接受完整SQL语句，有数据返回对象数组，否则空数组
     *
     * @$type 可以是MYSQLI_ASSOC ,MYSQLI_NUM ,MYSQLI_BOTH,不设置则返回对象数组
     */
    public function one($sql, $type = null)
    {
        $result = $this->query($sql, 'slave');
        $row = array();
        if ($this->slave->affected_rows) {
            if ($type) {
                $row = $result->fetch_array($type);
            } else {
                $row = $result->fetch_object();
            }
            $result->free();
        }
        return $row;
    }

    /**
     * 查询多条数据模型，接受完整SQL语句，有数据返回二维对象数组，否则空数组
     * @$type 可以是MYSQLI_ASSOC ,MYSQLI_NUM ,MYSQLI_BOTH,不设置则返回对象模式
     */
    public function all($sql, $type = null)
    {
        $result = $this->query($sql, 'slave');
        $rows = array();
        if ($this->slave->affected_rows) {
            if ($type) {
                while (! ! $array = $result->fetch_array($type)) { // 关联数组或数字数组或同时
                    $rows[] = $array;
                }
            } else {
                while (! ! $objects = $result->fetch_object()) {
                    $rows[] = $objects;
                }
            }
            $result->free();
        }
        return $rows;
    }

    // 数据增、删、改模型，接受完整SQL语句，返回影响的行数的int数据
    public function amd($sql)
    {
        $result = $this->query($sql, 'master');
        $num = $this->master->affected_rows;
        if ($num > 0) {
            return $num;
        } else {
            return 0;
        }
    }

    // 最近一次插入数据的自增字段值，返回int数据
    public function insertId()
    {
        return $this->master->insert_id;
    }

    // 执行多条SQL模型，成功返回true,否则false
    public function multi($sql)
    {
        $result = $this->master->multi_query($sql) or $this->error($sql);
        if ($result) {
            $result->free();
            return true;
        } else {
            return false;
        }
    }

    // 显示执行错误
    protected function error($sql, $conn)
    {
        error('执行SQL发生错误！错误：' . mysqli_error($this->$conn) . '，语句：' . $sql);
    }
}

