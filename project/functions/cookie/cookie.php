<?php

namespace Functions\cookie;

class cookie{
    static public function set($data){
        $data = (object) $data;
        $cookie_name = $data->name;
        $cookie_value = $data->value;
        $cookie_expire = $data->expire;
        $cookie_path = $data->path;
        $cookie_domain = $data->domain;
        $cookie_secure = $data->secure;
        $cookie_httponly = $data->httponly;
        $cookie_samesite = $data->samesite;
        setcookie($cookie_name, $cookie_value, $cookie_expire, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly, $cookie_samesite);
    }
    static public function get($data){
        $data = (object) $data;
        $cookie_name = $data->name;
        if(isset($_COOKIE[$cookie_name])){
            return $_COOKIE[$cookie_name];
        }else{
            return false;
        }
    }
    static public function delete($data){
        $data = (object) $data;
        $cookie_name = $data->name;
        $cookie_path = $data->path;
        $cookie_domain = $data->domain;
        $cookie_secure = $data->secure;
        $cookie_httponly = $data->httponly;
        $cookie_samesite = $data->samesite;
        setcookie($cookie_name, '', time() - 3600, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly, $cookie_samesite);
    }
}