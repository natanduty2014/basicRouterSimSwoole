<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\cognitoLogin as cognitoLoginModel;
use Functions\slim\getParsedBody as getParsedBody;

class cognitoLogin
{
    /**
     * Realiza login no Cognito e retorna Tokens
     */
    public function login(Request $request, Response $response): Response
    {
        try {
            // Obter dados do body

            $result = cognitoLoginModel::login();

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 200);
        } catch (\Throwable $e) {
            $error = ['status' => 500, 'message' => $e->getMessage()];
            $response->getBody()->write(json_encode($error));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
