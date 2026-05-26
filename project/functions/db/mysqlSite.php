<?php

namespace Functions\db;

use Functions\logs\log;
use Functions\db\redis;

class mysqlSite extends \PDO
{
    private static function conn()
    {
        $conn = new \PDO("mysql:host=" . MYSQL_HOST . ";dbname=" . MYSQL_DB, MYSQL_USER, MYSQL_PASS);
        return $conn;
    }
    /**
     * @param $stmt
     * @param $params
     */
    public static function select($rawQuery, $params = array())
    {
        try {
            $stmt = self::queryy($rawQuery, $params);
            return $stmt;
            $stmt = null;
        } catch (\Throwable $e) {
            return $e;
        }
    }

    /**
     * @param $stmt
     * @param $params
     */
    private static function tableExists($table)
    {
        try {
            $conn = self::conn();
            $stmt = $conn->prepare("SHOW TABLES LIKE :table");
            $stmt->bindValue(":table", $table);
            $stmt->execute();
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $stmt = null;
            if(count($results) > 0){
                return true;
            }else{
               return false;
            }
        }catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param $stmt
     * @param $params
     */
    private static function queryy($rawQuery, $params = array()): array
    {
        try {
            //iniciar a conexção
            $conn = self::conn();
            $getTableNames = explode(' ', $rawQuery)[3];
            //var_dump($getTableNames);
            /*save table name on redis
            if(redis::get($getTableNames) == true){
               return \json_decode(redis::get($getTableNames), true);
            }*/
            //carregar o script sql via PDO
            $stmt = $conn->prepare($rawQuery);
            self::setParams($stmt, $params);
            $stmt->execute();

            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if(empty($results)){
                $apiresponse = array(
                    "status" => "error",
                    "status_detail" => "Not_Found",
                    "http"   => 404,
                    "data"   => "nem um dados encontrado",
                );
                //redis::delete($getTableNames);
                $stmt = null;
                return $apiresponse;
            }
            if(!empty($results)){
                $apiresponse = array(
                    "status" => 'sucess',
                    "http"   => 200,
                    "data"  => $results,
                );
                //redis::save($getTableNames, \json_encode($apiresponse));
                $stmt = null;
                return $apiresponse;
            }
        } catch (\Throwable $e) {
            $apiresponse = array(
                "status" => 'error',
                "http"   => 500,
                "info"  => $e->getMessage()
            );
            log::logs(__METHOD__, 'database_mysql', null, 'error', $e->getMessage());
            return $apiresponse;
        }
    }


    /**
     * @param $stmt
     * @param $params
     */
    public static function insert($rawQuery, $params = array())
    {


        try {
            //iniciar a conexção
            $conn = self::conn();
            //carregar o script sql via PDO
            $stmt = $conn->prepare($rawQuery);
            //puxar a função que irar gravar o id
            self::setParams($stmt, $params);
            $stmt->execute();
            $last_id = $conn->lastInsertId();
            $stmt = null;
            return $last_id;
        } catch (\Throwable $e) {
            $apiresponse = array(
                "status" => 'error',
                "http"   => 500,
                "info"  => $e->getMessage()
            );

            return $apiresponse;
        }
    }

    /**
     * @param $stmt
     * @param $params
     */
    public static function delete($rawQuery, $params = array())
    {

        try {
            //iniciar a conexção
            $conn = self::conn();
            //carregar o script sql via PDO
            /*$getTableNames = explode(' ', $rawQuery)[2];
            if(redis::get($getTableNames) == true){
                redis::delete($getTableNames);
            }*/
            $stmt = $conn->prepare($rawQuery);
            //puxar a função que irar gravar o id
            self::setParams($stmt, $params);
            $stmt->execute();
            $stmt = null;
            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param $stmt
     * @param $params
     */
    public static function update($rawQuery, $params = array())
    {
        try {
            //iniciar a conexção
            $conn = self::conn();
            //carregar o script sql via PDO
            /* $getTableNames = explode(' ', $rawQuery)[1];
            if(redis::get($getTableNames) == true){
                redis::delete($getTableNames);
            }*/
            $stmt = $conn->prepare($rawQuery);
            //puxar a função que irar gravar o id
            self::setParams($stmt, $params);
            $stmt->execute();
            $stmt = null;
            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    //verificar se a tabela existe
   static public function table($table)
    {
        return self::tableExists($table);
    }

   /**
     * @param $stmt
     * @param $params
     */
    private static function setParams($statment, $parameters = array())
    {
        foreach ($parameters as $row) {
            foreach ($row as $key => $value) {
                self::setParam($statment, $key, $value);
            }
        }
    }
    /**
     * @param $stmt
     * @param $params
     */
    private static function setParam($statment, $key, $value)
    {
        $statment->bindParam($key, $value);
    }
}
