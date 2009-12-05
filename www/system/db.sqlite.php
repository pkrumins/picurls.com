<?php
/*
** Copyright (C) 2007 Peteris Krumins (peter@catonmat.net)
** http://www.catonmat.net  -  good coders code, great reuse
** 
** This program is free software: you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation, either version 3 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program.  If not, see <http://www.gnu.org/licenses/>.
**
** ------------------------------------------------------------------
**
** This code is part of http://picurls.com - buzziest pics website.
** Read how it was designed at:
** http://catonmat.net/blog/making-of-picurls-popurls-for-pictures-part-one/
**
*/ 

error_reporting(E_ALL);

class SQLite {
    var $db;
    var $sqlite_error;
    var $error;
    
    function SQLite($db_path) {
        $this->db = @sqlite_open($db_path, 0666, $this->sqlite_error);
        if (!$this->isError()) {
            sqlite_busy_timeout($this->db, 30000); // 30 seconds before SQLITE_BUSY
        }
    }
    
    function getError() {
        if ($this->sqlite_error) return $this->sqlite_error;
        if ($this->error)        return $this->error;
        
        $last_error = sqlite_last_error($this->db);
        return sqlite_error_string($last_error);
    }
    
    function isError() {
        if ($this->sqlite_error)  return TRUE;
        if (!empty($this->error)) return TRUE;
        
        $last_error = sqlite_last_error($this->db);
        if ($last_error == 0) return FALSE;   
        
        return $last_error;
    }    
    
    function getLastInsertId() {
        return sqlite_last_insert_rowid($this->db);
    }
    
    function escape($str) {
        return sqlite_escape_string($str);
    }
    
    function query($query) {
        return @sqlite_query($this->db, $query);
    }
    
    function fetchRowAssoc($res) {
        return sqlite_fetch_array($res, SQLITE_ASSOC);
    }

    function fetchRowQueryAssoc($query) {
        $res = $this->query($query);
        if (!$res) return $res;
        return $this->fetchRowAssoc($res);
    }
    
    function fetchRowSingle($res) {
        return sqlite_fetch_single($res);
    }

    function fetchRowQuerySingle($query) {
        $res = $this->query($query);
        if (!$res) return $res;
        return $this->fetchRowSingle($res);
    }

    function fetchAllAssoc($res, $field = NULL) {
        $retArray = Array();
        if (!$res) return $retArray;
        while ($row = $this->fetchRowAssoc($res)) {
            if ($field) {
                $retArray[$row[$field]] = $row;
            }
            else {
                array_push($retArray, $row);
            }
        }
        return $retArray;
    }

    function fetchAllQueryAssoc($query, $field = NULL) {
        $res = $this->query($query);
        if (!$res) return $res;
        return $this->fetchAllAssoc($res, $field);
    }

    function beginTransaction() {
        $this->query("BEGIN TRANSACTION ON CONFLICT ROLLBACK");
    }

    function endTransaction() {
        $this->query("COMMIT TRANSACTION");
    }
}

?>
