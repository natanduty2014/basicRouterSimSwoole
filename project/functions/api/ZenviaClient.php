<?php

namespace Functions\api;

use Functions\api\TotalVoiceAPI;


//exemple: ZenviaClient::enviarSMS('5584999864979', 'Teste de SMS')

class ZenviaClient
{
    static public function enviarSMS($telefone, $sms, $respostaUsuario = false)
    {
        $api = new TotalVoiceAPI("54baadb05b836ed90fa7fbf462bea9c4");
        //    $api->debugOn();
        $api->returnAssoc();
        return $api->enviaSMS($telefone, $sms, $respostaUsuario);
    }
}
