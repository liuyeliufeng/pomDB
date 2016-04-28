<?php

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
        foreach($this->options as $name => $value) {
            $this->mysql->options($name, $value);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function _arrayQuote($columns) {
        foreach($columns as $column) {
            $arrColumn = is_int($column) ? $column : "'$column'";
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
            $conditionClause .= ' order by '. is_array($conditions['order']) ?
                implode(',', $conditions['order']) : $conditions['order'];
        }
        if (isset($conditions['limit'])) {
            $conditionClause .= ' limit '. is_array($conditions['limit']) ?
                implode(',', $conditions['limit']) : '0,'. $conditions['limit'];
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
                        } else {
                            $wheres[] = "$filed $operator $value";
                        }
                        break;

                    case 'string':
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
        $arrFields = array();

        if (!is_array($fields) || empty($fields)) {
            return $fieldsClause;
        }
        foreach ($fields as $field) {
            if (!strpos($field, '(')) {
                $arrFields[] = $field;
            } else {
                preg_match('/^(\S+)\((\S+)\)$/', $field, $matches);
                $arrFields[] = $matches[1]. ' as '. $matches[2];
            }
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


    public function query($sql) {
        if (!$this->is_connected) {
            print_r('connect to server fail');
            return false;
        }
        $ret = $this->mysql->query($sql);
        if ($ret !== false) {
            return $ret;
        }
        return true;
    }


    /**
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
        }

        $table = "$tableName $tableAlias";

        $strSql = "select $fieldClause from $table $joinClause $conditionClause";
        return $this->query($strSql);
    }

    public function insert($table, $data) {

    }

    public function update($table, $fields, $conditions) {

    }

    public function delete($table, $conditions) {

    }

    public function close() {
        if (!$this->is_connected) {
            return ;
        }
        $this->is_connected = false;
        $this->mysql->close();
    }
}