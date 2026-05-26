<?php

namespace App\model\entity;

use Functions\db\redis;

class reports
{
    private static string $apiBase = 'https://m95ji3ctbi.execute-api.us-east-1.amazonaws.com';

    /**
     * Executa requisição GET na API externa com token Cognito do Redis.
     * Em caso de 401, renova via cognitoLogin e tenta novamente.
     */
    private static function get(string $path, array $queryParams = []): array
    {
        $token = null;
        if (class_exists('Functions\db\redis')) {
            $token = redis::get('cognito_token_7');
        }

        $url = self::$apiBase . $path;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $result = self::doRequest($url, $token);

        // Token expirado: renova e tenta novamente
        if ($result['httpCode'] === 401) {
            $loginResult = \App\model\entity\cognitoLogin::login();
            $newToken = $loginResult['data']['token'] ?? null;
            if ($newToken) {
                $result = self::doRequest($url, $newToken);
            }
        }

        if ($result['httpCode'] !== 200) {
            $msg = $result['data']['message'] ?? 'Erro ao consultar API externa.';
            return [
                'status'  => $result['httpCode'],
                'message' => $msg,
                'details' => $result['data'],
            ];
        }

        return [
            'status' => 200,
            'data'   => $result['data'],
        ];
    }

    private static function doRequest(string $url, ?string $token): array
    {
        $headers = ['Content-Type: application/json'];
        if ($token) {
            $headers[] = 'Authorization: ' . $token;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['httpCode' => 500, 'data' => ['message' => $error]];
        }
        curl_close($ch);

        $data = json_decode($response, true) ?? [];
        return ['httpCode' => $httpCode, 'data' => $data];
    }

    private static function dateParams(?string $dataInicio, ?string $dataFim, ?int $idCliente = null): array
    {
        $params = [];
        if ($dataInicio) $params['data_inicio'] = $dataInicio;
        if ($dataFim)    $params['data_fim']    = $dataFim;
        if ($idCliente)  $params['id_cliente']  = $idCliente;
        return $params;
    }

    // -------------------------------------------------------------------------

    public static function getPedidosNaoFinalizados(?string $dataInicio = null, ?string $dataFim = null): array
    {
        return self::get('/dashboard/pedidos-nao-finalizados', self::dateParams($dataInicio, $dataFim));
    }

    public static function getClientes(?string $dataInicio = null, ?string $dataFim = null): array
    {
        return self::get('/dashboard/clientes', self::dateParams($dataInicio, $dataFim));
    }

    public static function getItensPorPeriodo(?string $dataInicio = null, ?string $dataFim = null): array
    {
        return self::get('/dashboard/itens-por-periodo', self::dateParams($dataInicio, $dataFim));
    }

    public static function getItensPorCliente(?string $dataInicio = null, ?string $dataFim = null, ?int $idCliente = null): array
    {
        return self::get('/dashboard/itens-por-cliente', self::dateParams($dataInicio, $dataFim, $idCliente));
    }

    public static function getPedidosDiaHora(?string $dataInicio = null, ?string $dataFim = null): array
    {
        return self::get('/dashboard/pedidos-dia-hora', self::dateParams($dataInicio, $dataFim));
    }

    public static function getPedidosBairro(?string $dataInicio = null, ?string $dataFim = null): array
    {
        return self::get('/dashboard/pedidos-bairro', self::dateParams($dataInicio, $dataFim));
    }

    public static function getResumoPedidos(?string $dataInicio = null, ?string $dataFim = null): array
    {
        return self::get('/dashboard/resumo-pedidos', self::dateParams($dataInicio, $dataFim));
    }

    // -------------------------------------------------------------------------

}

