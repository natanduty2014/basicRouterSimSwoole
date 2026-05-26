<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class testRedis
{
    /**
     * Testa a conexão com Redis e força refresh do token Cognito
     */
    public function testTokenRefresh(Request $request, Response $response): Response
    {
        $debugInfo = [];

        try {
            // 1. Testa constantes
            $debugInfo['constants_check'] = [
                'COGNITO_CLIENT_ID' => defined('COGNITO_CLIENT_ID') ? COGNITO_CLIENT_ID : 'AUSENTE',
                'COGNITO_REGION' => defined('COGNITO_REGION') ? COGNITO_REGION : 'AUSENTE',
                'COGNITO_AUTH_USER' => defined('COGNITO_AUTH_USER') ? 'PRESENTE' : 'AUSENTE',
                'COGNITO_AUTH_PASS' => defined('COGNITO_AUTH_PASS') ? 'PRESENTE' : 'AUSENTE',
            ];

            // 2. Testa Redis básico
            $redis = new \Redis();
            $redis->connect('redis', 6379);
            $redis->auth(['pass' => 'user']);

            $testResult = [
                'redis_conectado' => true,
                'redis_test' => $redis->set('test', time()) ? 'OK' : 'FAIL',
            ];

            // 3. (Removido) Tenta forçar refresh do token Cognito via AthenaQuery
            $refreshSuccess = false;
            $refreshError = "AthenaQuery removido";

            // 4. Verifica se salvou no Redis
            $jwtToken = \Functions\db\redis::get('cognito_token');
            $refreshTokenRedis = \Functions\db\redis::get('cognito_refresh_token');

            $result = [
                'status' => 200,
                'message' => 'Teste de Redis e Token',
                'data' => [
                    'constants' => $debugInfo['constants_check'],
                    'redis' => $testResult,
                    'refresh_executado' => $refreshSuccess,
                    'refresh_error' => $refreshError,
                    'jwt_salvo' => $jwtToken ? 'SIM (' . substr($jwtToken, 0, 30) . '...)' : 'NAO',
                    'refresh_token_salvo' => $refreshTokenRedis ? 'SIM (' . substr($refreshTokenRedis, 0, 30) . '...)' : 'NAO',
                    'todas_keys_redis' => $redis->keys('*')
                ]
            ];

            $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $error = [
                'status' => 500,
                'message' => 'Erro no teste: ' . $e->getMessage(),
                'debug' => $debugInfo,
                'trace' => explode("\n", $e->getTraceAsString())
            ];
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
