<?php

namespace Functions\db;

use Functions\logs\log;

class mysqlPainel extends \PDO
{
    //criar uma conexção com banco de dados
    private static function conn()
    {
        $conn = new \PDO("mysql:host=" . MYSQL_HOST . ";dbname=" . MYSQL_DB, MYSQL_USER, MYSQL_PASS);
        return $conn;
    }

    //puxar mais de um dados do banco de dados
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
     * Undocumented function
     *
     * @param [type] $table
     * @return boolean
     * @throws \Throwable
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

    //puxar mais de um dados do banco de dados
    private static function queryy($rawQuery, $params = array()): array
    {
        try {
            //iniciar a conexção
            $conn = self::conn();
            //carregar o script sql via PDO
            $stmt = $conn->prepare($rawQuery);
            self::setParams($stmt, $params);
            $stmt->execute();

            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $apiresponse =  empty($results) ?
                array(
                    "status" => "error",
                    "status_detail" => "Not_Found",
                    "http"   => 404,
                    "data"   => "nem um dados encontrado"
                ) :
                array(
                    "status" => 'sucess',
                    "http"   => 200,
                    "data"  => $results
                );
            $stmt = null;
            return $apiresponse;
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
     * Undocumented function
     *
     * @param [type] $rawQuery
     * @param array $params
     * @return boolean|array
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

    //deletar dados de forma dinamica com multiplos dados
    public static function delete($rawQuery, $params = array())
    {

        try {
            //iniciar a conexção
            $conn = self::conn();
            //carregar o script sql via PDO
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
     * Undocumented function
     *
     * @param [type] $rawQuery
     * @param array $params
     * @return boolean
     */
    public static function update($rawQuery, $params = array())
    {
        try {
            //iniciar a conexção
            $conn = self::conn();
            //carregar o script sql via PDO
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
     * Undocumented function
     *
     * @param [type] $table
     * @return  boolean
     */
   static public function table($table)
    {
        return self::tableExists($table);
    }

    //puxar/inserir varios campos de uma vez
    private static function setParams($statment, $parameters = array())
    {
        foreach ($parameters as $row) {
            foreach ($row as $key => $value) {
                self::setParam($statment, $key, $value);
            }
        }
    }
    //puxar/inserir apenas um campo/dados
    private static function setParam($statment, $key, $value)
    {
        $statment->bindParam($key, $value);
    }
}