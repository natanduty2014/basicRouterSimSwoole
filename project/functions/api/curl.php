<?php

namespace Functions\api;

class curl{
    static public function get($url){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/json"
            ),
        ));

        $responseCurl = curl_exec($curl);
        $err = curl_error($curl);
        if($err)
            $responseCurl = $err;
        else
            $responseCurl = $responseCurl;
        curl_close($curl);
        return $responseCurl;
    }
}