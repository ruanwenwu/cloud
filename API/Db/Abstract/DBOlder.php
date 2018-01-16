<?php

abstract class API_Db_Abstract_DBOlder
{
    public function get_var($sql)
    {
        return $this->getOne($sql);    
    }

    public function get_results($sql, $fetchStyle = 'A')
    {
        $fetchStyle = ($fetchStyle == 'A') ? (PDO::FETCH_ASSOC) : (PDO::FETCH_OBJ);
        return $this->getAll($sql, $fetchStyle);
    }

    public function get_row($sql, $fetchStyle = 'A')
    {
        $fetchStyle = ($fetchStyle == 'A') ? (PDO::FETCH_ASSOC) : (PDO::FETCH_OBJ); 
        return $this->getRow($sql, $fetchStyle);
    }
}

