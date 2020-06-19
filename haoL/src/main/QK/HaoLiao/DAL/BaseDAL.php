<?php
/**
 * User: WangHui
 * Date: 2018/5/23
 * Time: 15:35
 */

namespace QK\HaoLiao\DAL;

use QK\HaoLiao\Common\StringHandler;
use QK\WSF\DAL\DAL;
use QK\WSF\Settings\AppSetting;

class BaseDAL extends DAL {
    private $_readDB;
    private $_writeDB;

    public function __construct(AppSetting $appSetting){
        parent::__construct($appSetting);
    }


    protected function getDB($sql){
        //检查是否为读库
        $pos = strpos(strtolower($sql), "select");
        if (!is_bool($pos)) {
            //读库
            return $this->_readDB = $this->getMysqlDBBySettingPath("mdbs:read");
        } else {
            //写库
            return $this->_writeDB = $this->getMysqlDBBySettingPath("mdbs:write");
        }
    }

    public function insertData($data, $table){
        $insertString = StringHandler::newInstance()->getDBInsertString($data);
        $insertKeySql = $insertString['insert'];
        $insertValueSql = $insertString['value'];
        $sql = 'INSERT INTO `'.$table.'` ('.$insertKeySql.') VALUES ('.$insertValueSql.')';
        return $this->getDB($sql)->executeNoResult($sql);

    }

    public function getInsertId(){
        $idSql = "SELECT LAST_INSERT_ID()";
        $id = $this->_writeDB->executeValue($idSql);
        return $id;
    }

    public function updateData($id, $data, $table){
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = 'UPDATE `'.$table.'` SET '.$updateString.'WHERE id='.$id;
        return $this->getDB($sql)->executeNoResult($sql);
    }

    public function updateByCondition($condition, $data, $table){
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $condition = $this->parseCondition($condition);
        $sql = 'UPDATE `'.$table.'` SET '.$updateString.' WHERE 1 ' . $condition;
        return $this->getDB($sql)->executeNoResult($sql);
    }

    protected function setMappingFields(){
        // TODO: Implement setMappingFields() method.
    }

    /**
     * 获取列表接口
     */
    public function select($table, $condition = array(), $fields = array(), $offset = 0, $limit = 20, $orderBy = array()) {
      $select_fields = " * ";
      if (!empty($fields)) {
        $select_fields = implode(',', $fields);
      }
      $sql = "SELECT $select_fields FROM `$table` WHERE  1 = 1";
      $sql .= $this->parseCondition($condition);

      if (!empty($orderBy)) {
        $ordersArr = array();
        foreach($orderBy as $orderKey => $orderVal) {
		$ordersArr[] = "`$orderKey` $orderVal";
        }
        $orderStr = implode(',', $ordersArr);
        $sql .= " ORDER BY $orderStr";
      }

      if ($limit) {
        $sql .= " limit $limit";
      }
      if (!empty($offset)) {
        $sql .= " offset $offset";
      }
      return $this->getDB($sql)->executeRows($sql);
    }

    public function total($condition = array()) {
      $select_fields = " * ";
      if (!empty($fields)) {
        $select_fields = implode(',', $fields);
      }
      $sql = "SELECT $select_fields FROM `$this->_table` WHERE  1 = 1";
      $sql .= $this->parseCondition($condition);

      return $this->getDB($sql)->executeRows($sql);
    }

    public function counts($table, $condition) {
      $sql = "select count(*) as count from " . $table . " where 1 = 1";
      $sql .= $this->parseCondition($condition);
      return $this->getDB($sql)->executeRows($sql);
    }

    public function get($table, $condition = array(), $fields = array()) {
      $select_fields = " * ";
      if (!empty($fields)) {
        $select_fields = implode(',', $fields);
      }
      $sql = "SELECT $select_fields FROM `$table` WHERE  1 = 1";
      $sql .= $this->parseCondition($condition);

      return $this->getDB($sql)->executeRow($sql);
    }

    public function create($table, $data){
      $fields = implode(', ', array_keys($data));
      $sqlPlaceHolder = implode(', ', array_pad(array(), count($data), '?'));
      $values = array();
      foreach(array_values($data) as $key => $val) {
        $values[$key+1] = $val;
      }
      $sql = "INSERT INTO `$table` ($fields) VALUES ($sqlPlaceHolder)";
      return $this->getDB($sql)->executeNoResult($sql, $values);
    }

    public function update($table, $data, $condition) {
      $fields = array_keys($data);
      $sql = "UPDATE `$table` SET ";
      foreach($fields as $field) {
        // eg: 'balance=balance+1'
        if(preg_match('/=/', $field)) {
          $sql .= $field.",";
        } else {
          $sql .= $field."=?,";
        }
      }
      $sql = trim($sql, ",");

      $sql .= " WHERE 1 = 1 ";
      $sql .= $this->parseCondition($condition);

      $values = array();
      foreach(array_values($data) as $key => $val) {
        $values[$key+1] = $val;
      }
      return $this->getDB($sql)->executeNoResult($sql, $values);
    }

    protected function parseCondition($condition) {
      $sql = "";
      if (!empty($condition)) {
        foreach($condition as $key => $val) {
          if ($key == '-') {
            if (is_array($val)) {
              foreach($val as $sql_stat) {
                $sql .= "AND  $sql_stat ";
              }
            }
          } else {
            if (is_array($val)) {
              if (count($val) == 2) {
                $sql .= " AND $key $val[0] $val[1]";
              } else {
                $sql .= " AND $key  = $val[0]";
              }
            } else {
              $sql .= " AND $key = '$val'";
            }
          }
        }
      }
      return $sql;
    }

    public function beginTrans() {
        return $this->getDB('')->getPDO()->beginTransaction();
    }

    public function commit() {
        return $this->getDB('')->getPDO()->commit();
    }

    public function rollBack() {
        return $this->getDB('')->getPDO()->rollBack();
    }

    /**
     * @param $table 主表表名
     * @param $condition  查询条件数组
     * @param $fields 查询字段，如['age', 'user1.*', 'user1.id', 'user2.name']
     * @param $offset 
     * @param $limit
     * @param $orderBy
     * @param $join ['LEFT JOIN/RIGHT JOIN/INNER JOIN/FULL JOIN', ['join_tbl_name', 'main_tbl_field', 'join_tbl_relation_field']]
     *
     */
    public function getAll($table, $condition = array(), $fields = array(), $offset = 0, $limit = 20, $orderBy = array(), $join = []) {
      $select_fields = " * ";
      if (!empty($fields)) {
        $fields_str = "";
        foreach($fields as $field_name) {
          if (strpos($field_name, '.') === false) {
            $fields_str .= "`$field_name`,";
          } else {
            list($tbl_text, $field_text) = explode('.', $field_name);
            $fields_str .= ($field_text != '*') ? "`$tbl_text`.`$field_text`," : "`$tbl_text`.$field_text,";
          }
        }
        $select_fields = rtrim($fields_str, ",");
      }
      $sql = "SELECT $select_fields FROM `$table` ";
      if (!empty($join)) {
        if (count($join) == 2 && count($join[1]) == 3) {
          $join_type = $join[0];
          list($relation_tbl, $main_tbl_key, $relation_tbl_key) = $join[1];
          $sql .= " $join_type `$relation_tbl` ON `$table`.`$main_tbl_key` = `$relation_tbl`.`$relation_tbl_key`";
        }
      }
      $sql .= " WHERE 1";
      $sql .= $this->conditionSql($condition);

      if (!empty($orderBy)) {
        $ordersArr = array();
        foreach($orderBy as $orderKey => $orderVal) {
          if (strpos($orderKey, '.') !== false) {
            $orderKey = explode('.', $orderKey);
            $orderKey = "`$orderKey[0]`.`$orderKey[1]`";
          } else {
            $orderKey = "`$orderKey`";
          }
          $ordersArr[] = "$orderKey $orderVal";
        }
        $orderStr = implode(',', $ordersArr);
        $sql .= " ORDER BY $orderStr";
      }

      if ($limit) {
        $sql .= " limit $limit";
      }
      if (!empty($offset)) {
        $sql .= " offset $offset";
      }
      return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * sql查询条件的解析
     * [
     *    JOIN查询时，field_key为'tbl.field_name'形式
     *    'field_key' => 'string_value',        普通的K-V键值对
     *    'field_key' => [operator, val],       K为字段名,V[0]为特殊运算符号,V[1]为对应的值
     *    'field_key' => [[operator1, val1], [operator2, val2]]   K为字段名,如：'age' => [['<=', 24], ['>=', 12]]解析为age <= 24 AND age >=12
     *    '-'   => [statement1, statement2]     -符号代表它的值对应的是多个表达式,如'-' => ['age between 18 and 20', 'name like \'tony\'']
     * ]
     *
     */
    protected function conditionSql($condition) {
      $sql = "";
      if (!empty($condition)) {
        foreach($condition as $key => $val) {
          if ($key == '-') {
            if (is_array($val)) {
              foreach($val as $sql_stat) {
                $sql .= "AND  $sql_stat ";
              }
            }
          } else {
            //key的处理，加反引号
            if (strpos($key, '.') === false) {
              $key = "`$key`";
            } else {
              list($tbl_key, $field_key) = explode('.', $key);
              $key = "`$tbl_key`.`$field_key`";
            }
            //value处理
            if (is_array($val)) {
              if (count($val) == 2) {
                $str_operator_arr = ['=', '>=', '<=', '>', '<', '!=', '<>'];
                
                if (is_array($val[0]) && count($val[0]) == 2 && is_array($val[1]) && count($val[1]) == 2) {
                  //当一个字段有两个运算处理时
                  $operator1 = $val[0];
                  $operator2 = $val[1];
                  $sql .= is_string($operator1[1]) && in_array($operator1[0], $str_operator_arr) 
                    ? " AND $key $operator1[0] '$operator1[1]'"
                    : " AND $key $operator1[0] $operator1[1]";
                  $sql .= is_string($operator2[1]) && in_array($operator2[0], $str_operator_arr)
                    ? " AND $key $operator2[0] '$operator2[1]'"
                    : " AND $key $operator2[0] $operator2[1]";
                } else {
                  //当运算符为in，not in等等时，对字符串不加引号处理
                  $sql .= is_string($val[1]) && in_array($val[0], $str_operator_arr) ? " AND $key $val[0] '$val[1]'" : " AND $key $val[0] $val[1]";
                }
              } else {
                $sql .= is_string($val[0]) ? " AND $key  = '$val[0]'" : " AND $key  = $val[0]";
              }
            } else {
              if (is_string($val)) {
                $sql .= " AND $key = '$val'";
              } else {
                $sql .= " AND $key = $val";
              }
            }
          }
        }
      }
      return $sql;
    }

}
