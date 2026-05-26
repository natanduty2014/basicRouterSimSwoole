<?php
namespace Functions\api\payments;

use DateTime;

class orendapayGetBilling
{
    static public function getBilling(array $data): array
    {   
        $cpf_cliente = $data['ass_cpf'];
        $data_vencimento_inicial = $data['pai_date']; // Formato: YYYY-MM-DD
        $data_vencimento_final = date('Y-m-d', strtotime($data['pai_date'] . ' +1 year'));
        $seu_codigo = $data['pai_code'];

        $url = "https://www.orendapay.com.br/api/v1/cobrancas/status?DOC={$cpf_cliente}&data_vencimento_inicial={$data_vencimento_inicial}&data_vencimento_final={$data_vencimento_final}";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "x-ID: 1239",
            "x-Token: 12I175258e546673S723524s6780C77e7942j72j1149e96C8276s59R6428S15s4110e12s7269I",
            "Content-Type: application/json"
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_code == 200) {
            $dataReq = json_decode($response, true);
            $cobrancas = $dataReq['cobrancas'] ?? [];

            // Filtrar cobranças por "seu_codigo" e "data_vencimento_inicial"
            $cobrancas_recorrencia = array_filter($cobrancas, function ($cobranca) use ($seu_codigo, $data_vencimento_inicial) {
                // Converter data_vencimento da API para formato americano (YYYY-MM-DD) para comparar
                $data_vencimento_api = DateTime::createFromFormat('d/m/Y', $cobranca['data_vencimento'])->format('Y-m-d');
                return $cobranca['seu_codigo'] === $seu_codigo && $data_vencimento_api >= $data_vencimento_inicial;
            });

            if(!$cobrancas_recorrencia) {
                return array(
                    'status' => 200,
                    'message' => 'no_billing_found',
                    'data' => $cobrancas_recorrencia
                );
            }

            return array(
                'status' => 200,
                'message' => 'success',
                'seu_codigo' => $seu_codigo,
                'type_payment' => $data['type_payment'],
                'pai_price' => $data['pai_price'],
                'data' => $cobrancas_recorrencia
            );
        } else {
            return array(
                'status' => 400,
                'message' => 'error',
                'data' => $response
            );
        }
    }
}
