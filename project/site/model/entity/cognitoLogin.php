<?php

namespace App\model\entity;

use Hyperf\DbConnection\Model\Model;
use App\model\entity\clients;
use Functions\db\redis;

class cognitoLogin
{

    public static function login()
    {
        try {
            // Cognito Authentication
            $clientId = defined('COGNITO_CLIENT_ID') ? COGNITO_CLIENT_ID : null;
            $region   = defined('COGNITO_REGION') ? COGNITO_REGION : 'us-east-1';

            if (!$clientId) {
                // Tenta pegar do ambiente se não definido
                $clientId = getenv('COGNITO_CLIENT_ID');
            }

            if (!$clientId) {
                return ['status' => 500, 'message' => 'Erro interno: Cognito Client ID não configurado.'];
            }

            $url = "https://cognito-idp.$region.amazonaws.com/";
            $authData = [
                'AuthFlow' => 'USER_PASSWORD_AUTH',
                'ClientId' => $clientId,
                'AuthParameters' => [
                    'USERNAME' => defined('COGNITO_AUTH_USER') ? constant('COGNITO_AUTH_USER') : getenv('COGNITO_AUTH_USER'),
                    'PASSWORD' => defined('COGNITO_AUTH_PASS') ? constant('COGNITO_AUTH_PASS') : getenv('COGNITO_AUTH_PASS')
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-amz-json-1.1',
                'X-Amz-Target: AWSCognitoIdentityProviderService.InitiateAuth'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($authData));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $json = json_decode($response, true);

            if ($httpCode !== 200) {
                $msg = 'E-mail ou senha não conferem.';
                if (isset($json['message']) && strpos($json['message'], 'User is not confirmed') !== false) {
                    $msg = 'Usuário não confirmado no Cognito.';
                }
                return ['status' => 401, 'message' => $msg, 'details' => $json['message'] ?? ''];
            }

            if (!isset($json['AuthenticationResult'])) {
                return ['status' => 500, 'message' => 'Resposta inválida do provedor de autenticação.'];
            }

            $authResult = $json['AuthenticationResult'];
            $idToken = $authResult['IdToken'];
            $refreshToken = $authResult['RefreshToken'] ?? null;
            $expiresIn = $authResult['ExpiresIn'] ?? 3600;

            // Extrair Payload do IdToken para pegar identificador (sub ou email)
            $tokenParts = explode('.', $idToken);
            $payload = isset($tokenParts[1]) ? json_decode(base64_decode($tokenParts[1]), true) : [];

            // Tenta usar 'sub' (UUID do cognito) ou o email como identificador para o Redis
            // Se o sistema espera um ID numérico (cli_id), isso pode quebrar se não houver um cli_id no token.
            // Vou usar o email como fallback de chave unique se não existir um custom:cli_id
            $redisKeySuffix = 7; // Força ID 7 conforme solicitado

            // $redisKeySuffix = $payload['sub'] ?? (defined('COGNITO_AUTH_USER') ? constant('COGNITO_AUTH_USER') : 'unknown_user');

            // // Se houver um custom attribute para o ID, use-o
            // if (isset($payload['custom:cli_id'])) {
            //     $redisKeySuffix = $payload['custom:cli_id'];
            // }

            // Save tokens to Redis
            if (class_exists('Functions\db\redis')) {
                // Usando prefixo genérico 'cognito_token_' para não colidir com 'client_token_' se o sufixo não for ID
                redis::saveEx('cognito_token_' . $redisKeySuffix, $idToken, $expiresIn);
                if ($refreshToken) {
                    redis::saveEx('cognito_refresh_token_' . $redisKeySuffix, $refreshToken, 30 * 24 * 60 * 60); // 30 days
                }
            }

            return [
                'status' => 200,
                'message' => 'Login realizado com sucesso',
                'data' => [
                    'token' => $idToken,
                    'refreshToken' => $refreshToken
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
