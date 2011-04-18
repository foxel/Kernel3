<?php

class FDBaseQCmysql
{
    private $pdo = null;

    public function __construct(PDO $pdo)
    {        static $charSets = Array(); 
        
        // html charset names to SQL ones converting
        $charset = strtr(strtolower(F_INTERNAL_ENCODING), Array('-' => '', 'windows' => 'cp'));
        $charset = (isset($charSets[$charset]))
            ? $charSets[$charset]
            : $charset;

        $this->pdo = $pdo;
        try { $pdo->exec('set names '.$charset); } catch (PDOException $e) {};
    }

    public function simpleSelect($table, $fields = Array(), $where = '', $other = '', $flags = 0)
    {
        $where = $this->_parseWhere($where, $flags);
        $other = $this->_parseOther($other, $flags);

        $query = 'SELECT ';
        if ($flags & FDataBase::SQL_DISTINCT)
            $query.= 'DISTINCT ';

        if (is_array($fields)) {
            if (count($fields))
            {
                foreach ($fields as $id => $fname)
                {
                    $fields[$id] = '`'.$fname.'`';
                }
                $fields = implode(', ', $fields).' ';
            }
            else
                $fields = '*';
        }

        if (empty($fields))
            $fields = '*';

        $query.= $fields.' ';

        $query.= 'FROM `'.$table.'` '.$where.' '.strval($other);

        return $query;
    }

    // complex multitable select
    // $tqueries = Array (
    //     'table1_name' => Array('fields' => '*', 'where' => '...', 'prefix' => 't1_'),
    //     'table2_name' => Array('fields' => '*', 'where' => '...', 'prefix' => 't2_', 'join' => Array('[table2_filed_name]' => '[main_table_field_name]', ...) ),
    //     ...
    //     )
    public function multitableSelect($tqueries, $other = '', $flags = 0)
    {
        $qc_fields = $qc_where = $qc_order = Array();
        $qc_tables = '';

        if (!is_array($tqueries))
            return '';

        $ti = 0;
        $inh_flags = $flags & (FDataBase::SQL_NOESCAPE);
        
        foreach ($tqueries as $table => $params)
        {
            $tl = 't'.$ti;

            if ($ti > 0)
            {
                $join_by = Array();
                if (isset($params['join']) && is_array($params['join']) && count($params['join']))
                {
                    $join_to = 0;
                    if (isset($params['join_to']) && ($join_to = (int) $params['join_to']))
                        $join_to = min(max(0, $join_to), $ti - 1);

                    foreach($params['join'] as $tfield => $mtfield)
                    {
                        $join_by[] = $tl.'.`'.$tfield.'` = t'.$join_to.'.`'.$mtfield.'`';
                    }
                }

                if (($params['flags'] | $inh_flags) & FDataBase::SQL_LEFTJOIN && count($join_by))
                    $qc_tables.= ' LEFT JOIN `'.$table.'` '.$tl;
                else
                    $qc_tables.= ' JOIN `'.$table.'` '.$tl;

                if (count($join_by))
                    $qc_tables.= ' ON ('.implode(', ', $join_by).')';
            }
            else
                $qc_tables = '`'.$table.'` '.$tl;

            if (isset($params['fields']))
            {
                $ifields = $params['fields'];
                if (is_array($ifields))
                {
                    $prefix = (isset($params['prefix']))
                        ? preg_replace('#[^A-Za-z_]#', '', $params['prefix'])
                        : false;
                    if (count($ifields))
                        foreach ($ifields as $fkey => $fname)
                        {
                            if (is_int($fkey))
                            {
                                $field = $tl.'.`'.$fname.'`';
                                if ($prefix)
                                    $field.= ' AS `'.$prefix.$fname.'`';
                            }
                            else
                                $field = $tl.'.`'.$fkey.'` AS `'.$fname.'`';

                            $qc_fields[] = $field;
                        }
                    else
                        $qc_fields[] = $tl.'.*';
                }
                elseif ($ifields == '*')
                    $qc_fields[] = $tl.'.*';
                elseif ($ifields)
                    $qc_fields[] = $tl.'.`'.$ifields.'`';
            }

            if (isset($params['order']))
            {
                $order = $params['order'];
                if (is_array($order))
                {
                    foreach($order as $fkey => $fname)
                    {
                        if (is_int($fkey))
                            $field = $tl.'.`'.$fname.'` ASC';
                        else
                        {
                            $type = strtolower($fname);
                            $field = $tl.'.`'.$fkey.'`'.(($type == 'desc' || $type == '-1') ? ' DESC' : ' ASC');
                        }
                        $qc_order[] = $field;
                    }
                }
                elseif ($order)
                    $qc_order[] = $tl.'.`'.$order.'`';
            }

            if ($where = $this->_parseWhere($params['where'], $params['flags'] | $inh_flags, $tl))
                $qc_where[] = '('.preg_replace('#^WHERE\s#i', '', $where).')';
            $ti++;
        }

        if (count($qc_fields))
            $qc_fields = implode(', ', $qc_fields);
        else
            $qc_fields = '*';

        if (count($qc_where))
            $qc_where = 'WHERE '.implode(($flags & FDataBase::SQL_WHERE_OR) ? ' OR ' : ' AND ', $qc_where);
        else
            $qc_where = '';

        if (count($qc_order))
            $qc_order = 'ORDER BY '.implode(', ', $qc_order);
        else
            $qc_order = '';

        $query ='SELECT '.$qc_fields.' FROM '.$qc_tables.' '.$qc_where.' '.$qc_order.' '.strval($other);

        return $query;
    }

    public function insert($table, Array $data, $replace = false, $flags = 0)
    {
        $query = ($replace) ? 'REPLACE INTO ' : 'INSERT INTO ';

        $query.= '`'.$table.'` ';

        if (count($data)) 
        {
            $names = $ivals = $vals = Array();
            if (!($flags & FDataBase::SQL_MULINSERT))
                $data = Array($data);
            
            $fixnames = false;
            foreach ($data as $dataset)
            {
                if (!count($dataset))
                    continue;
                    
                foreach ($dataset as $field => $val)
                {
                    if (!$fixnames)
                        $names[] = $field;
                    elseif (!in_array($field, $names))
                        continue;
                      
                    if (is_scalar($val))
                    {
                        if (is_bool($val))
                            $val = (int) $val;
                        elseif (is_string($val))
                        {
                            if (!($flags & FDataBase::SQL_NOESCAPE) && (!is_numeric($val) || $val[0] == '0'))
                                $val = $this->pdo->quote($val);
                            //$val = '"'.$val.'"';
                        }
                        else
                            $val = (string) $val;

                        $vals[$field] = $val;
                    }
                    elseif (is_null($val))
                        $vals[$field] = 'NULL';
                    else
                        $vals[$field] = '""';
                }
                $ivals[] = implode(', ', $vals);
                $fixnames = true;
            }
            
            if (count($names))
            {
                $query.='(`'.implode('`, `', $names).'`) VALUES ('.implode('), (', $ivals).')';
                return $query;
            }
        }

        return false;
    }
    
    public function update($table, Array $data, $where = '', $flags = 0)
    {
        $where = $this->_parseWhere($where, $flags);

        $query = 'UPDATE `'.$table.'` SET ';

        if (count($data)) {
            $names = $vals = Array();
            foreach ($data AS $field=>$val)
            {
                if (($flags & FDataBase::SQL_USEFUNCS) && $part = $this->_parseFieldFunc($field, $val, false))
                    $fields[] = $part;
                elseif (is_scalar($val))
                {
                    $names[] = '`'.$field.'`';

                    if (is_bool($val))
                        $val = (int) $val;
                    elseif (is_string($val))
                    {
                        if (!($flags & FDataBase::SQL_NOESCAPE) && (!is_numeric($val) || $val[0] == '0'))
                            $val = $this->pdo->quote($val);
                        //$val = '"'.$val.'"';
                    }
                    else
                        $val = (string) $val;

                    $fields[] = '`'.$field.'` = '.$val;
                }
                elseif (is_null($val))
                    $fields[] = '`'.$field.'` = NULL';
            }
            $query.= implode(', ', $fields);
            $query.= ' '.$where;

            return $query;
        }
        else
            return false;
    }
    
    public function delete($table, $where = '', $flags = 0)
    {
        $where = $this->_parseWhere($where, $flags);

        $query = 'DELETE FROM `'.$table.'` '.$where;

        return $query;
    }
    
    private function _parseWhere($where, $flags = 0, $tbl_pref = '')
    {
        if (empty($where))
            return '';

        if (is_array($where))
        {
            $parts = Array();
            foreach ($where AS $field => $val)
            {
                $field = '`'.$field.'`';
                if ($tbl_pref)
                    $field = $tbl_pref.'.'.$field;

                if (($flags & FDataBase::SQL_USEFUNCS) && ($part = $this->_parseFieldFunc($field, $val, true)))
                    $parts[] = $part;
                elseif (is_scalar($val))
                {
                    if (is_bool($val))
                        $val = (int) $val;
                    elseif (is_string($val))
                    {
                        if (!($flags & FDataBase::SQL_NOESCAPE) && (!is_numeric($val) || $val[0] == '0'))
                            $val = $this->pdo->quote($val);
                        //$val = '"'.$val.'"';
                    }
                    else
                        $val = (string) $val;

                    $parts[] = $field.' = '.$val;
                }
                elseif (is_array($val) && count($val))
                {
                    /*$val = array_unique($val);
                    sort($val);*/
                    $nvals = Array();
                    foreach ($val as $id => $sub)
                    {
                        if (is_bool($sub))
                            $sub = (int) $sub;
                        elseif (is_string($sub))
                        {
                            if (!($flags & FDataBase::SQL_NOESCAPE) && (!is_numeric($val) || $val[0] == '0'))
                                $sub = $this->pdo->quote($sub);
                            //$sub = '"'.$sub.'"';
                        }
                        elseif (is_null($sub))
                            $sub = 'NULL';

                        if (is_scalar($sub))
                            $nvals[$id] = $sub;
                    }

                    if (count($nvals))
                        $parts[] = $field.' IN ('.implode(', ', $nvals).')';
                }
                elseif (is_null($val))
                    $parts[] = $field.' IS NULL';
            }
            if (count($parts))
                return 'WHERE '.implode(($flags & FDataBase::SQL_WHERE_OR) ? ' OR ' : ' AND ', $parts);
            else
                return 'WHERE false';
        }
        elseif (empty($tbl_pref))
        {
            $where = trim(strval($where));
            if (!preg_match('#^WHERE\s#i', $where))
                $where = 'WHERE '.$where;
            return $where;
        }
        else
            return '';
    }

    // constructs simple ORDER and LIMIT
    private function _parseOther($other, $flags = 0, $tbl_pref = '')
    {
        if (empty($other))
            return '';

        if (is_array($other))
        {
            $parts = Array();

            if (isset($other['order']))
            {
                $order = $other['order'];
                if (is_array($order))
                {
                    $order_by = Array();
                    foreach($order as $fkey => $fname)
                    {
                        if (is_int($fkey))
                            $field = '`'.$fname.'` ASC';
                        else
                        {
                            $type = strtolower($fname);
                            $field = '`'.$fkey.'`'.(($type == 'desc' || $type == '-1') ? ' DESC' : ' ASC');
                        }
                        if ($tbl_pref)
                            $field = $tbl_pref.'.'.$field;
                        $order_by[] = $field;
                    }
                    if (count($order_by))
                        $parts[] = 'ORDER BY '.implode(', ', $order_by);
                }
                elseif (empty($tbl_pref))
                    $parts[] = 'ORDER BY '.$order;
            }

            if (isset($other['limit']))
            {
                $limit = $other['limit'];
                if (!is_array($limit))
                    $limit = preg_split('#\D#', $limit, -1, PREG_SPLIT_NO_EMPTY);
                $limit = array_slice($limit, 0, 2);
                $lim_count = (int) array_pop($limit);
                $lim_first = (int) array_pop($limit);

                if ($lim_count > 0)
                    $parts[] = 'LIMIT '.$lim_first.', '.$lim_count;
            }

            if (count($parts))
                return implode(' ', $parts);
            else
                return '';
        }
        elseif (empty($tbl_pref))
        {
            $other = trim(strval($other));
            return $other;
        }
        else
            return '';
    }

    private function _parseFieldFunc($field, $data, $is_compare = false)
    {
        static $set_funcs = Array(
            '++' => '%1$s = %1$s + %2$s',
            '--' => '%1$s = %1$s - %2$s',
            );

        static $cmp_funcs = Array(
            '<'  => '%1$s < %2$s',  '<=' => '%1$s <= %2$s',
            '>'  => '%1$s > %2$s',  '>=' => '%1$s >= %2$s',
            '<>' => '%1$s <> %2$s', '!=' => '%1$s != %2$s',
            'LIKE' => '%1$s LIKE %2$s', 'ISNULL' => 'ISNULL(%1$s) = %2$s',
            );

        if (!is_string($data))
            return false;

        $funcs_set = ($is_compare) ? $cmp_funcs : $set_funcs;
        $expr = explode(' ', $data, 2);
        if (count($expr) == 2)
        {

            if (isset($funcs_set[$expr[0]]))
            {
                $val = $expr[1];
                if ($val == 'NULL')
                    $val = 'NULL';
                elseif (!is_numeric($val) || $val[0] == '0')
                    $val = $this->pdo->quote($val);

                $out = sprintf($funcs_set[$expr[0]], $field, $val);
                return $out;
            }
            else
                return false;
        }
        else
            return false;
    }

}

?>
