<?php

namespace Functions\db;


class postgres extends \PDO
{
    //criar uma conexção com banco de dados 
    private static function conn()
    {
        $conn = new \PDO("pgsql:host=" . PG_HOST . ";dbname=" . PG_DB, PG_USER, PG_PASS);
        return $conn;
    }

    //puxar mais de um dados do banco de dados
    public static function select($rawQuery, $params = array()): array
    {
        try {
            $stmt = self::queryy($rawQuery, $params);
            return $stmt;
            $stmt = null;
        } catch (\Throwable $e) {
            return $e;
        }
    }

    //puxar mais de um dados do banco de dados
    public static function queryy($rawQuery, $params = array()): array
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
                    "http"   => 404,
                    "info"   => "nem um dados encontrado"
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

            return $apiresponse;
        }
    }


    //inserir dados de forma dinamica com multiplos dados
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
            $stmt = null;
            return true;
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

    //deletar dados de forma dinamica com multiplos dados
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
