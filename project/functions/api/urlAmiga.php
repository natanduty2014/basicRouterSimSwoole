<?php

namespace Functions\api;

class urlAmiga
{
    static public function url($str)
    {
        //precisa atualizar essa função UTF8 PHP 8.2
        $str = strtolower(utf8_decode($str));
        $i = 1;
        $str = strtr($str, utf8_decode('àáâãäåæçèéêëìíîïñòóôõöøùúûýýÿ'), 'aaaaaaaceeeeiiiinoooooouuuyyy');
        $str = preg_replace("/([^a-z0-9])/", '-', utf8_decode($str));
        while ($i > 0) {
            $str = str_replace('--', '-', $str, $i);
        }
        if (substr($str, -1) == '-') {
            $str = substr($str, 0, -1);
        }
        return $str;
    }
}
