<?php

namespace Functions\api;

class calcVotos{
    /**
     * @param $votos
     * @param $total
     * @return float|int
     */
    static public function porcentagem($votos, $total){
        $porcentagem = ($votos * 100) / $total;
        //arredondando
        $porcentagem = round($porcentagem, 2);
        return $porcentagem;
    }
}