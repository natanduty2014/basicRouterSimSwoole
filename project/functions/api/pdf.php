<?php

namespace Functions\api;

use Mpdf\Mpdf;

class pdf
{
    static public function generationPag(string $html, null | string | int | array $name): string
    {

        $mpdf = new Mpdf();
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S');

    }
}
