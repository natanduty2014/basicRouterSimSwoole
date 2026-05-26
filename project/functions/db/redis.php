<?php

namespace Functions\db;

class redis
{
    static private function conn()
    {
        try {
            //create redis instance
            $redis = new \Redis();
            //connect with server and port
            $redis->connect('redis', 6379);
            $redis->auth(['pass' => 'user']);
            // $redis->auth(['user' => 'phpredis', 'pass' => 'phpredis']);
            return $redis;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    /**
     * @param $key
     * @param $value
     * @return bool|string
     */
    static public function save($key, $value)
    {
        $redis = self::conn();
        $redis->set($key, $value);
        return self::get($key);
    }

    /**
     * @param $key
     * @param $value
     * @param $expire
     * @return bool
     */
    static public function saveEx($key, $value, $expire)
    {
        $redis = self::conn();
        return $redis->setex($key, $expire, $value);
    }

    /**
     * @param $key
     * @return bool|string
     */
    static public function get($key)
    {

        $redis = self::conn();
        $value = $redis->get($key);
        return $value;
    }

    /**
     * @param $key
     * @return bool|string
     */
    static public function exists($key)
    {

        $redis = self::conn();
        $value = $redis->exists($key);
        return $value;
    }

    /**
     * @param $key
     * @return bool|string
     */
    static public function delete($key)
    {

        $redis = self::conn();
        $value = $redis->del($key);
        return $value;
    }

    /**
     * @param $key
     * @param $value
     * @return bool|string
     */
    static public function update($key, $value)
    {

        $redis = self::conn();
        $redis->set($key, $value);
        return $value;
    }
}
