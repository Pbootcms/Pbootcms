<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @license This is not a freeware, use is subject to license terms
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年8月30日
 *  数据库PDO驱动
 */
namespace core\database;

use core\basic\Config;

class Pdo implements Builder
{

    protected static $pdo;

    protected $master;

    protected $slave;

    protected $commit;

    private function __construct()
    {}

    // 获取单一实例，使用单一实例数据库连接类
    public static function getInstance()
    {
        if (! self::$pdo) {
            self::$pdo = new self();
        }
        return self::$pdo;
    }

    // 连接数据库，接受数据库连接参数，返回数据库连接对象
    public function conn($cfg)
    {
        if (! extension_loaded('PDO')) {
            die('未检测到您服务器环境的PDO数据库扩展，请检查php.ini中是否已经开启对应的数据库扩展！');
        }
        $charset = Config::get('database.charset') ?: 'utf8';
        switch (Config::get('database.type')) {
            case 'pdo_mysql':
                $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['dbname'] . ';charset=' . $charset;
                try {
                    $conn = new \PDO($dsn, $cfg['user'], $cfg['passwd']);
                } catch (\PDOException $e) {
                    error('PDO方式连接MySQL数据库错误：' . iconv('gbk', 'utf-8', $e->getMessage()));
                }
                break;
            case 'pdo_sqlite':
                $dsn = 'sqlite:' . ROOT_PATH . $cfg['dbname'];
                try {
                    $conn = new \PDO($dsn);
                } catch (\PDOException $e) {
                    error('PDO方式连接Sqlite数据库错误：' . iconv('gbk', 'utf-8', $e->getMessage()));
                }
                break;
            case 'pdo_pgsql':
                $dsn = 'pgsql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['dbname'];
                try {
                    $conn = new \PDO($dsn, $cfg['user'], $cfg['passwd']);
                } catch (\PDOException $e) {
                    error('PDO方式连接Pgsql数据库错误：' . iconv('gbk', 'utf-8', $e->getMessage()));
                }
                break;
            default:
                $dsn = Config::get('database.dsn');
                try {
                    $conn = new \PDO($dsn, $cfg['user'], $cfg['passwd']);
                } catch (\PDOException $e) {
                    error('PDO方式连接数据库错误：' . iconv('gbk', 'utf-8', $e->getMessage()));
                }
                break;
        }
        return $conn;
    }

    // 关闭自动提交，开启事务模式
    public function closeCommit()
    {
        $this->master->beginTransaction();
        $this->commit = true;
    }

    // 提交事务
    public function commit()
    {
        $this->master->commit();
        $this->commit = false;
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
                }
                $result = $this->master->exec($sql);
                if ($result === false) {
                    if ($this->commit) { // 如果是事务模式，发生错误，则回滚
                        $this->master->rollBack();
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
                    if (Config::get('database.type') == 'pdo_mysql') {
                        $this->slave->exec("SET sql_mode='NO_ENGINE_SUBSTITUTION'"); // MySql写入规避严格模式
                    }
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
        if ($result->fetch()) {
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
        if (! ! $row = $result->fetch(\PDO::FETCH_NUM)) {
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
            return $result->columnCount();
        } else {
            return false;
        }
    }

    /**
     * 获取表信息,接受数据库表名，返回表字段信息数组
     *
     * @param $table 表名            
     */
    public function tableFields($table)
    {
        $rows = array();
        switch (Config::get('database.type')) {
            case 'pdo_mysql':
                $sql = "describe $table";
                $result = $this->query($sql, 'slave');
                while (! ! $row = $result->fetchObject()) {
                    $rows[] = $row->Field;
                }
                break;
            case 'pdo_sqlite':
                $sql = "pragma table_info($table)";
                $result = $this->query($sql, 'slave');
                while (! ! $row = $result->fetchObject()) {
                    $rows[] = $row->name;
                }
                break;
            case 'pdo_pgsql':
                $sql = "SELECT column_name FROM information_schema.columns WHERE table_name ='$table'";
                $result = $this->query($sql, 'slave');
                while (! ! $row = $result->fetchObject()) {
                    $rows[] = $row->column_name;
                }
                break;
            default:
                return array();
        }
        return $rows;
    }

    /**
     * 查询一条数据模型，接受完整SQL语句，有数据返回对象数组，否则空数组
     * @$type 可以是MYSQLI_ASSOC(FETCH_ASSOC) ,MYSQLI_NUM(FETCH_NUM) ,MYSQLI_BOTH(FETCH_BOTH),不设置则返回对象模式
     */
    public function one($sql, $type = null)
    {
        $result = $this->query($sql, 'slave');
        $row = array();
        if ($type) {
            $type ++; // 与mysqli统一返回类型设置
            $row = $result->fetch($type);
        } else {
            $row = $result->fetchObject();
        }
        return $row;
    }

    /**
     * 查询多条数据模型，接受完整SQL语句，有数据返回二维对象数组，否则空数组
     * @$type 可以是MYSQLI_ASSOC(FETCH_ASSOC) ,MYSQLI_NUM(FETCH_NUM) ,MYSQLI_BOTH(FETCH_BOTH),不设置则返回对象模式
     */
    public function all($sql, $type = null)
    {
        $result = $this->query($sql, 'slave');
        $rows = array();
        if ($type) {
            $type ++; // 与mysqli统一返回类型设置
            $rows = $result->fetchAll($type);
        } else {
            while (! ! $row = $result->fetchObject()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    // 数据增、删、改模型，接受完整SQL语句，返回影响的行数的int数据
    public function amd($sql)
    {
        $result = $this->query($sql, 'master');
        if ($result > 0) {
            return $result;
        } else {
            return 0;
        }
    }

    // 最近一次插入数据的自增字段值，返回int数据
    public function insertId()
    {
        return $this->master->lastInsertId();
    }

    // 执行多条SQL模型，成功返回true,否则false
    public function multi($sql)
    {
        $sqls = explode(';', $sql);
        foreach ($sqls as $key => $value) {
            $result = $this->query($value, 'master');
        }
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    // 显示执行错误
    protected function error($sql, $conn)
    {
        $err = $this->$conn->errorInfo();
        error('执行SQL发生错误！错误：' . $err[2] . '，语句：' . $sql);
    }
}