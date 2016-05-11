<?php

/**
 * @brief
 * PomDB is a light/high-security/powerful/gentle ORM arch for PHPer.
 * It only support mysql for now.
 * In future more kinds of database engine will also be supported.
 *
 * @author xiashanshan
 * Class PomDB
 */
define('SUCCESS', '0');
define('QUERY_ERROR', '1');

class PomDB {

    // mysql
    private $mysql;
    // flag of if connected
    private $is_connected = false;
    // data type, current data type is mysql
    private $database_type;
    // database server ip
    private $host;
    // port
    private $port;
    // username for server
    private $username;
    // password for server
    private $password;
    // database
    private $database_name;
    // charset connected with server
    private $charset;
    // mysql options
    private $options;
    // last execute sql
    private $lastSql;


    public function __construct($database, $options = null)
    {
        $this->mysql = mysqli_init();
        $this->database_type = $database['database_type'];
        $this->host = $database['host'];
        $this->port = $database['port'];
        $this->username = $database['username'];
        $this->password = $database['password'];
        $this->database_name = $database['database_name'];
        $this->charset = $database['charset'];
        $this->options = $options;
        $this->_connect();
    }

    private function _connect() {
        $this->is_connected = $this->mysql->real_connect(
            $this->host, $this->username, $this->password, $this->database_name, $this->port, null, 0);
        // set options
        if ($this->is_connected) {
            $this->_setOptions();
        }
        // set charset
        if ($this->charset) {
            $this->mysql->set_charset($this->charset);
        }
        return $this->is_connected;
    }

    private function _setOptions() {
        if (empty($this->options)) {
            return false;
        }
        foreach($this->options as $name => $value) {
            $this->mysql->options($name, $value);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * implode array into sql string
     * @param $columns
     * @return string
     */
    private function _arrayQuote($columns) {
        if (!is_array($columns)) {
            return false;
        }

        foreach($columns as $column) {
            if (is_array($column) || empty($column)) {
                continue;
            } else if(is_string($column)) {
                $column = $this->mysql->real_escape_string($column);
            }
            $arrColumn[] = is_string($column) ? "'$column'" : $column;
        }
        return implode(',', $arrColumn);
    }


    /**
     * generate expressions of conditions
     * @param $conditions
     * @return string
     */
    private function _conditionClause($conditions) {

        $conditionClause = '';
        if (!is_array($conditions) || empty($conditions)) {
            return $conditionClause;
        }
        if (isset($conditions['where'])) {
            $conditionClause .= ' where '. $this->_dataClause($conditions['where']);
        }
        if (isset($conditions['order'])) {
            $tempClause = is_array($conditions['order']) ?
                implode(',', $conditions['order']) : $conditions['order'];
            $conditionClause .= ' order by '. $tempClause;
        }
        if (isset($conditions['limit'])) {
            $tempClause = is_array($conditions['limit']) ?
                implode(',', $conditions['limit']) : '0,'. $conditions['limit'];
            $conditionClause .= ' limit '. $tempClause;
        }
        if (isset($conditions['group'])) {
            $conditionClause .= ' group by '. $conditions['group'];
        }
        if (isset($conditions['having'])) {
            $conditionClause .= ' having '. $conditions['having'];
        }
        return $conditionClause;
    }

    /**
     * generate expressions of where
     * @param $data
     * @param string $connector
     * @return string
     */
    private function _dataClause($data, $connector = '') {

        $whereClause = '';

        if (!is_array($data) || empty($data)) {
            return $whereClause;
        }

        $wheres = array();

        foreach($data as $key => $value) {

            $type = gettype($value);

            if ($type == 'array' && preg_match('/^(and|or)$/', $key, $matches)) {

                $wheres[] = '(' . $this->_dataClause($value, ' ' . $matches[1]). ' ' . ')';

            } else {
                if (!strpos($key, '|')) {
                    $filed = $key;
                } else {
                    preg_match('/^(\S+)\|(\S+)$/', $key, $matches);
                    $filed = $matches[1];
                    $operator = $matches[2];
                }

                switch($type){
                    case 'NULL':
                        if (!isset($operator)) {
                            $wheres[] = "$filed is null";
                        } else if ($operator == '!=') {
                            $wheres[] = "$filed is not null";
                        }
                        break;

                    case 'integer':
                    case 'double':
                        if (!isset($operator)) {
                            $wheres[] = "$filed = $value";
                        } else if ($operator == '!') {
                            $wheres[] = "$filed != $value";
                        }else {
                            $wheres[] = "$filed $operator $value";
                        }
                        break;

                    case 'string':
                        $value = $this->mysql->real_escape_string($value);
                        if (!isset($operator)) {
                            $wheres[] = "$filed = '$value'";
                        } else if ($operator == '!') {
                            $wheres[] = "$filed != '$value'";
                        } else if ($operator == '~') {
                            $wheres[] = "$filed like '$value'";
                        } else if ($operator == '!~') {
                            $wheres[] = "$filed not like '$value'";
                        }
                        break;

                    case 'array':

                        if (!isset($operator)) {
                            $wheres[] = "$filed in (". $this->_arrayQuote($value). ")";
                        } else if ($operator == '!') {
                            $wheres[] = "$filed not in (". $this->_arrayQuote($value). ")";
                        } else if ($operator == '><') {
                            $wheres[] = "$filed between $value[0] and $value[1]";
                        } else if ($operator == '<>') {
                            $wheres[] = "$filed not between $value[0] and $value[1]";
                        }
                        break;

                    default:
                        break;
                }
            }
        }
        $whereClause = implode(' '. $connector. ' ', $wheres);
        return $whereClause;
    }

    /**
     * generate expressions of fields
     * @param $fields
     * @return string
     */
    private function _fieldsClause($fields) {

        $fieldsClause = '*';
        if (!is_array($fields) || empty($fields)) {
            return $fieldsClause;
        }
        $arrFields = array();
        // if the fields is assoc array
        if (count(array_filter(array_keys($fields, 'is_string'))) > 0) {
            foreach ($fields as $field => $alias) {
                $arrFields[] = $field. ' as '. $alias;
            }
        } else {
            $arrFields = $fields;
        }

        return implode(',', $arrFields);
    }

    /**
     * generate expressions of joins
     * @param $joins
     * @return string
     */
    private function _joinsClause($joins) {

        $joinsClause = '';
        $arrJoin = array();

        if (!is_array($joins) || empty($joins)) {
            return $joinsClause;
        }
        foreach ($joins as $key => $value) {

            $tableAlias = '';

            if (!strpos($key, '(')) {

                preg_match('/^(\S+)\|(\S+)$/', $key, $matches);
                $joinType = $matches[1]. ' join';
                $joinTable = $matches[2];

            } else {

                preg_match('/^(\S+)\|(\S+)\((\S+)\)$/', $key, $matches);

                $joinType = $matches[1]. ' join';
                $joinTable = $matches[2];
                $tableAlias = $matches[3];
            }

            $arrJoinOn = array();
            foreach ($value as $onKey => $onValue) {
                $arrJoinOn[] = $onKey. '='. $onValue;
            }

            $joinOns = implode(' and ', $arrJoinOn);
            $arrJoin[] = "$joinType $joinTable $tableAlias on $joinOns";

            unset($tableAlias);
        }
        $joinsClause = implode(' ', $arrJoin);
        return $joinsClause;
    }

    /**
     * mysql exec sql
     * @param $sql
     * @return bool|mysqli_result
     */
    public function query($sql) {
        if (!$this->is_connected) {
            print_r('connect to server fail');
            return false;
        }
        $this->lastSql = $sql;
        $ret = $this->mysql->query($sql);
        $arrRet = array();
        if (is_bool($ret) || $ret == null) {
            if ($ret == true) {
                return $this->mysql->affected_rows;
            } else {
                print_r($this->mysql->error);
                return false;
            }
        } else {
            while($row = $ret->fetch_assoc())
            {
                $arrRet[] = $row;
            }
            $ret->free();
        }
        return $arrRet;
    }


    /**
     * interface: select fields from table
     * @param $table
     * @param null $fields
     * @param null $joins
     * @param null $conditions
     * @return bool|mysqli_result
     */
    public function select($table, $fields = null, $joins = null, $conditions = null) {

        $fieldClause = $this->_fieldsClause($fields);
        $joinClause = $this->_joinsClause($joins);
        $conditionClause = $this->_conditionClause($conditions);

        if (strpos($table, '(')) {
            preg_match('/^(\S+)\((\S+)\)$/', $table, $matches);
            $tableName = $matches[1];
            $tableAlias = $matches[2];
            $table = "$tableName $tableAlias";
        }

        $strSql = "select $fieldClause from $table $joinClause $conditionClause";
        return $this->query($strSql);
    }

    /**
     * interface: insert into table data
     * @param $table
     * @param $fields
     * @param $data
     * @return bool|mysqli_result
     */
    public function insert($table, $fields, $data) {
        if (!is_array($fields) || !is_array($data)) {
            return false;
        }
        $strField = implode(',', $fields);

        $arrData = array();
        // if data is multi-dimension array
        if (count($data) == count($data, 1)) {
            $strValue = $this->_arrayQuote($data);
            $arrData[] = "($strValue)";
        } else {
            foreach($data as $key => $value) {
                $strValue = $this->_arrayQuote($value);
                $arrData[] = "($strValue)";
            }
        }
        $strData = implode(',', $arrData);
        $strSql = "insert into $table($strField) values $strData";
        return $this->query($strSql);
    }

    /**
     * interface: update table
     * @param $table
     * @param $fields
     * @param $conditions
     * @return bool|mysqli_result
     */
    public function update($table, $fields, $conditions) {
        if (!is_array($fields)) {
            return false;
        }
        $arrField = array();
        foreach($fields as $field => $value) {
            $type = gettype($value);
            $strField = '';
            switch($type) {
                case 'NULL':
                    $strField = "$field=null";
                    break;
                case 'string':
                    $value = $this->mysql->real_escape_string($value);
                    $strField = "$field='$value'";
                    break;
                default:
                    $strField = "$field=$value";
                    break;
            }
            $arrField[] = $strField;
        }
        $fieldClaus = implode(',', $arrField);
        $conditionClause = $this->_conditionClause($conditions);
        $strSql = "update $table set $fieldClaus $conditionClause";
        return $this->query($strSql);
    }

    /**
     * interface: delete columns from table
     * @param $table
     * @param null $conditions
     * @return bool|mysqli_result
     */
    public function delete($table, $conditions = null) {
        $conditionClause = '';
        if (is_array($conditions)) {
            $conditionClause = $this->_conditionClause($conditions);
        }
        $strSql = "delete from $table ";
        if (!empty($conditionClause)) {
            $strSql .= $conditionClause;
        }
        return $this->query($strSql);
    }

    /**
     * @return mixed
     */
    public function getLastSql() {
        return $this->lastSql;
    }
    /**
     * close the connection to mysql
     */
    public function close() {
        if (!$this->is_connected) {
            return ;
        }
        $this->is_connected = false;
        $this->mysql->close();
    }


    /**
     * begin_transaction
     */
    public function beginTransaction() {
        if (!$this->is_connected) {
            return ;
        }
        $this->mysql->begin_transaction();
    }
    /**
     * commit transaction
     */
    public function commit() {
        if (!$this->is_connected) {
            return ;
        }
        $this->mysql->commit();
    }
    /**
     * transaction rollback
     */
    public function rollback() {
        if (!$this->is_connected) {
            return ;
        }
        $this->mysql->rollback();
    }
}