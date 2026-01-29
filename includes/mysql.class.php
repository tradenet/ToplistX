<?php
// Copyright 2011 JMB Software, Inc.
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//    http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

class DB
{
    private mysqli $handle;
    private string $hostname;
    private string $username;
    private string $password;
    private bool $connected;
    private string $database;

    public function __construct(string $hostname, string $username, string $password, string $database)
    {
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->connected = false;
    }

    public function Connect(): void
    {
        if (!$this->connected) {
            $this->handle = new mysqli($this->hostname, $this->username, $this->password, $this->database);
            
            if ($this->handle->connect_error) {
                trigger_error('Database connection failed: ' . $this->handle->connect_error, E_USER_ERROR);
            }
            
            $this->connected = true;
        }
    }

    public function IsConnected(): bool
    {
        return $this->connected;
    }

    public function Disconnect(): void
    {
        if ($this->connected) {
            $this->handle->close();
            $this->connected = false;
        }
    }

    public function SelectDB(string $database): void
    {
        $this->database = $database;
        
        if (!$this->handle->select_db($this->database)) {
            trigger_error('Database selection failed: ' . $this->handle->error, E_USER_ERROR);
        }
    }

    public function Row(string $query, array $binds = []): ?array
    {
        $stmt = $this->ExecuteQuery($query, $binds);
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }

    public function Count(string $query, array $binds = []): int
    {
        $stmt = $this->ExecuteQuery($query, $binds);
        $result = $stmt->get_result();
        $row = $result->fetch_row();
        $stmt->close();
        return (int)($row[0] ?? 0);
    }

    public function Query(string $query, array $binds = []): mysqli_result
    {
        $stmt = $this->ExecuteQuery($query, $binds);
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }

    public function QueryWithPagination(string $query, array $binds = [], int $page = 1, int $per_page = 10, bool $nolimit = false): array
    {
        global $C;
        
        $result = [];
        
        // Get total number of results
        // Build a count query more carefully to handle complex queries with JOINs
        $count_query = preg_replace('~ ORDER BY [^;]*$~is', '', $query);
        
        // Replace the SELECT clause with COUNT(*) - use a more robust pattern
        // Match from start of string, "SELECT", anything (greedy), up to last occurrence of "FROM"
        $count_query = preg_replace('~^SELECT\s+.*\s+FROM~is', 'SELECT COUNT(*) FROM', $count_query);
        
        // Final fallback: if the count query is empty or doesn't have SELECT, wrap it as a subquery
        if (empty(trim($count_query)) || !preg_match('~SELECT~i', $count_query)) {
            $count_query = "SELECT COUNT(*) FROM ($query) AS counter_table";
        }
        
        if (stristr($count_query, 'GROUP BY')) {
            $temp_result = $this->Query($count_query, $binds);
            $result['total'] = $temp_result->num_rows;
        } else {
            $result['total'] = $this->Count($count_query, $binds);
        }
        
        // Calculate pagination
        $result['pages'] = (int)ceil($result['total'] / $per_page);
        $result['page'] = min(max($page, 1), $result['pages']);
        $result['limit'] = max(($result['page'] - 1) * $per_page, 0);
        $result['start'] = max(($result['page'] - 1) * $per_page + 1, 0);
        $result['end'] = min($result['start'] - 1 + $per_page, $result['total']);
        $result['prev'] = ($result['page'] > 1);
        $result['next'] = ($result['end'] < $result['total']);
        
        if ($result['next']) {
            $result['next_page'] = $result['page'] + 1;
        }
        
        if ($result['prev']) {
            $result['prev_page'] = $result['page'] - 1;
        }
        
        if ($result['total'] > 0) {
            $limit_query = $nolimit ? $query : $query . " LIMIT {$result['limit']},{$per_page}";
            $result['result'] = $this->Query($limit_query, $binds);
        } else {
            $result['result'] = false;
        }
        
        // Format
        $result['fpages'] = number_format($result['pages'], 0, $C['dec_point'], $C['thousands_sep']);
        $result['start'] = number_format($result['start'], 0, $C['dec_point'], $C['thousands_sep']);
        $result['end'] = number_format($result['end'], 0, $C['dec_point'], $C['thousands_sep']);
        $result['ftotal'] = number_format($result['total'], 0, $C['dec_point'], $C['thousands_sep']);
        
        return $result;
    }

    public function &FetchAll(string $query, array $binds = [], ?string $key = null): array
    {
        $all = [];
        $result = $this->Query($query, $binds);
        
        while ($row = $result->fetch_assoc()) {
            if ($key) {
                $all[$row[$key]] = $row;
            } else {
                $all[] = $row;
            }
        }
        
        $result->free();
        return $all;
    }

    public function Update(string $query, array $binds = []): int
    {
        $stmt = $this->ExecuteQuery($query, $binds);
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    public function NextRow(mysqli_result $result): ?array
    {
        return $result->fetch_assoc();
    }

    public function Free(mysqli_result $result): void
    {
        $result->free();
    }

    public function InsertID(): int
    {
        return (int)$this->handle->insert_id;
    }

    public function NumRows(mysqli_result $result): int
    {
        return $result->num_rows;
    }

    public function FetchArray(mysqli_result $result): ?array
    {
        return $result->fetch_array();
    }

    public function Seek(mysqli_result $result, int $where): void
    {
        $result->data_seek($where);
    }

    public function BindList(int $count): string
    {
        if ($count <= 0) {
            return "''";
        }
        return implode(',', array_fill(0, $count, '?'));
    }

    public function Escape(string $string): string
    {
        return $this->handle->real_escape_string($string);
    }

    public function GetTables(): array
    {
        $tables = [];
        $result = $this->Query('SHOW TABLES');
        
        while ($row = $result->fetch_assoc()) {
            $key = array_key_first($row);
            $table = $row[$key];
            $tables[$table] = $table;
        }
        
        $result->free();
        return $tables;
    }

    public function GetColumns(string $table, bool $as_hash = false, bool $with_backticks = false): array
    {
        $columns = [];
        $result = $this->Query('DESCRIBE ' . $this->handle->real_escape_string($table));
        
        while ($column = $result->fetch_assoc()) {
            $field_name = $column['Field'];
            
            if ($as_hash) {
                $columns[$field_name] = $with_backticks ? "`{$field_name}`" : $field_name;
            } else {
                $columns[] = $with_backticks ? "`{$field_name}`" : $field_name;
            }
        }
        
        $result->free();
        return $columns;
    }

    private function ExecuteQuery(string $query, array $binds = []): mysqli_stmt
    {
        if (empty($binds)) {
            $stmt = $this->handle->prepare($query);
            if (!$stmt) {
                trigger_error('Query preparation failed: ' . $this->handle->error . "<br />$query", E_USER_ERROR);
            }
            $stmt->execute();
            return $stmt;
        }

        // Process binds - separate ? and # placeholders
        $types = '';
        $params = [];
        $index = 0;
        $pieces = preg_split('/(\?|#)/', $query, -1, PREG_SPLIT_DELIM_CAPTURE);
        $new_query = '';

        foreach ($pieces as $piece) {
            if ($piece === '?') {
                $new_query .= '?';
                $value = $binds[$index] ?? null;
                
                if ($value === null) {
                    $types .= 's';
                    $params[] = null;
                } elseif (is_int($value)) {
                    $types .= 'i';
                    $params[] = $value;
                } elseif (is_float($value)) {
                    $types .= 'd';
                    $params[] = $value;
                } else {
                    $types .= 's';
                    $params[] = (string)$value;
                }
                $index++;
            } elseif ($piece === '#') {
                $identifier = str_replace('`', '', (string)($binds[$index] ?? ''));
                $new_query .= '`' . $this->handle->real_escape_string($identifier) . '`';
                $index++;
            } else {
                $new_query .= $piece;
            }
        }

        $stmt = $this->handle->prepare($new_query);
        if (!$stmt) {
            trigger_error('Query preparation failed: ' . $this->handle->error . "<br />$new_query", E_USER_ERROR);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            trigger_error('Query execution failed: ' . $stmt->error . "<br />$new_query", E_USER_ERROR);
        }

        return $stmt;
    }
}

?>