<?php

namespace Functions\api;

use Functions\db\mysqlSite as mysql;

class pagination{
    static private int $limit = 10;
    static private int $page = 1;
    static private int $offset;
    static private int $total;
    /**
     * @param array $data
     * @return array
     */
    static private function countSql(array $data, string|null $type = null): int{
        if($type == 'between'){
            $sql = "SELECT COUNT(*) AS total FROM {$data['table']} WHERE {$data['where']} AND {$data['between']}";
            $result = mysql::select($sql);
            self::$total = (int)$result['data'][0]['total'];
            return $result['data'][0]['total'];
        }
        $sql = "SELECT COUNT(*) AS total FROM {$data['table']} WHERE {$data['where']}";
        $result = mysql::select($sql);
        self::$total = (int)$result['data'][0]['total'];
        return $result['data'][0]['total'];
    }

    /**
     * @param array $data
     * @return array
     */

     /*exemple call function
        $data = [
            'select' => 'use_id, use_name, use_email, use_register',
            'table' => 'tb_users',
            'where' => 'use_deleted = 1'
        ];
     */
    static public function paginationSql(array $data, int|null|array $pag = null): array{
        self::countSql($data);
        self::$page = $pag ?? 1;
        self::$offset = (self::$page - 1) * self::$limit;
        $sql = "SELECT {$data['select']} FROM {$data['table']} WHERE {$data['where']} LIMIT " . self::$offset . "," . self::$limit;
        $result = mysql::select($sql);
        $result['total'] = self::$total;
        $result['limit'] = self::$limit;
        $result['page'] = self::$page;
        if($result['http'] == 404){
           return array(
               'http' => 404,
               'message' => 'Nenhum registro encontrado'
           );
        }
        return $result;
    }

    /**
     * @param array $data
     * @return array
     */

     /*exemple call function
        $endDate = date('Y-m-d', strtotime($endDate . '+1 days'));
        $data = [
            'select' => 'use_id, use_name, use_email, use_register',
            'table' => 'tb_users',
            'where' => 'use_deleted = 1',
            'between' => "use_date BETWEEN '2021-01-01' AND '2021-01-31'"
        ];
     */
     static public function paginationSqlBetween(array $data, int|null|array $pag = null): array{
        self::countSql($data, 'between');
        self::$page = $pag ?? 1;
        self::$offset = (self::$page - 1) * self::$limit;
        $sql = "SELECT {$data['select']} FROM {$data['table']} WHERE {$data['where']} AND {$data['between']} LIMIT " . self::$offset . "," . self::$limit;
        $result = mysql::select($sql);
        $result['total'] = self::$total;
        $result['limit'] = self::$limit;
        $result['page'] = self::$page;
        if($result['http'] == 404){
           return array(
               'http' => 404,
               'message' => 'Nenhum registro encontrado'
           );
        }
        return $result;

     }

}