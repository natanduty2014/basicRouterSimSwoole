<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\clients as clientsModel;
use OpenApi\Attributes as OA;
use Functions\slim\getParsedBody as getParsedBody;

#[OA\Schema(
    schema: 'Client',
    description: 'Modelo de Cliente',
    required: ['cli_email', 'cli_nome', 'cli_cpf', 'cli_nascimento', 'cli_telefone1', 'cli_senha'],
    properties: [
        new OA\Property(property: 'cli_id', type: 'integer'),
        new OA\Property(property: 'cli_avatar', type: 'string', description: 'Imagem em base64', example: 'data:image/gif;base64,...'),
        new OA\Property(property: 'cli_email', type: 'string', format: 'email'),
        new OA\Property(property: 'cli_nome', type: 'string'),
        new OA\Property(property: 'cli_cpf', type: 'string'),
        new OA\Property(property: 'cli_nascimento', type: 'string', format: 'date'),
        new OA\Property(property: 'cli_telefone1', type: 'string'),
        new OA\Property(property: 'cli_telefone2', type: 'string'),
        new OA\Property(property: 'cli_senha', type: 'string', description: 'Senha do cliente', format: 'password')
    ]
)]


#[OA\Schema(
    schema: 'LoginClient',
    description: 'login do cliente',
    required: ['cli_email', 'cli_senha'],
    properties: [
        new OA\Property(property: 'cli_email', type: 'string', format: 'email'),
        new OA\Property(property: 'cli_senha', type: 'string', description: 'Senha do cliente', format: 'password')
    ]
)]
#[OA\Tag(name: 'Clients', description: 'Operações relacionadas a clientes')]
class clients
{
    #[OA\Post(
        path: '/clients/',
        operationId: 'createClient',
        summary: 'Criar novo cliente (Cadastro)',
        tags: ['Clients']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    ref: '#/components/schemas/Client'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Cliente criado, aguardando confirmação SMS',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 201),
                new OA\Property(property: 'message', type: 'string', example: 'Cliente criado, verifique o SMS enviado'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Client'),
                        new OA\Property(property: 'cli_id', type: 'integer')
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Erro de validação',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Mensagem de erro')
            ]
        )
    )]
    public function create(Request $request, Response $response): Response
    {
        try {
            $parsed = $request->getParsedBody();
            if (empty($parsed)) {
                $raw = (string)$request->getBody();
                $parsed = json_decode($raw, true) ?? [];
            }
            $data = $parsed['data'] ?? $parsed;

            if (empty($data['cli_email']) || empty($data['cli_senha'])) {
                throw new \Exception("E-mail e senha são obrigatórios.");
            }

            // Force inactive until verified
            $data['cli_ativo'] = 0;

            $result = clientsModel::create($data);

            if ($result['status'] === 201) {
                $cliId = $result['data']['cli_id'];
                $phone = preg_replace('/[^0-9]/', '', $data['cli_telefone1'] ?? '');

                // Ensure 55 for Brazil if not present
                if (strlen($phone) >= 10 && strlen($phone) <= 11) {
                    $phone = '55' . $phone;
                }

                // Generate 6 digit OTP
                $otp = rand(100000, 999999);

                // Save to Redis with 10 min expiration
                \Functions\db\redis::saveEx('sms_verif_' . $cliId, $otp, 600);

                // Send SMS
                \Functions\api\ZenviaClient::enviarSMS($phone, "Seu código de verificação Refacil: $otp");

                $result['message'] = 'Cliente criado. Código SMS enviado para verificação.';
            }

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Post(
        path: '/clients/confirm-sms',
        operationId: 'confirmSMS',
        summary: 'Confirmar código SMS',
        tags: ['Clients']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'cli_id', type: 'integer'),
                new OA\Property(property: 'code', type: 'string')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Telefone verificado com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Telefone verificado com sucesso')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Código inválido ou expirado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Código inválido ou expirado')
            ]
        )
    )]
    public function confirmSMS(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            if (empty($body)) {
                $raw = (string) $request->getBody();
                $body = json_decode($raw, true) ?? [];
            }
            $cliId = $body['cli_id'] ?? null;
            $code = $body['code'] ?? null;

            if (!$cliId || !$code) {
                throw new \Exception('ID do cliente e código são obrigatórios');
            }

            $savedCode = \Functions\db\redis::get('sms_verif_' . $cliId);

            // Código expirou (Redis TTL de 10min passou)
            if ($savedCode === null || $savedCode === false) {
                $response->getBody()->write(json_encode([
                    'status' => 410,
                    'error' => 'Código expirado. Solicite um novo código SMS.',
                    'error_code' => 'CODE_EXPIRED'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(410);
            }

            // Código incorreto
            if ($savedCode != $code) {
                $response->getBody()->write(json_encode([
                    'status' => 400,
                    'error' => 'Código inválido. Verifique e tente novamente.',
                    'error_code' => 'CODE_INVALID'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Código correto — ativar conta
            $client = clientsModel::find($cliId);
            if (!$client) {
                throw new \Exception('Cliente não encontrado');
            }

            $client->cli_ativo = 1;
            $client->save();
            \Functions\db\redis::delete('sms_verif_' . $cliId);

            $response->getBody()->write(json_encode([
                'status' => 200,
                'message' => 'Telefone verificado com sucesso. Conta ativada.'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Put(
        path: '/clients/{id}',
        operationId: 'updateClient',
        summary: 'Atualizar cliente',
        tags: ['Clients']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    ref: '#/components/schemas/Client'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Cliente atualizado com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Cliente atualizado com sucesso'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Client')
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Erro de validação',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Mensagem de erro')
            ]
        )
    )]
    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            $parsedBody = new getParsedBody();
            $parsed = $request->getParsedBody();
            if (empty($parsed)) {
                $raw = (string)$request->getBody();
                $parsed = json_decode($raw, true) ?? [];
            }
            $data = $parsed['data'] ?? $parsed;

            if (empty($data['cli_email'])) {
                throw new \Exception("E-mail é obrigatório.");
            }
            $data = $parsedBody->filter($data)
                ->jsonToArray($data)
                ->objectData('data')
                ->isValidDate('cli_nascimento')
                ->validInputEmpty('cli_email')
                ->validInputEmpty('cli_telefone1')
                ->validInputEmpty('cli_nome')
                ->getData();

            // Se uma senha foi fornecida, valida o comprimento mínimo
            if (isset($data['cli_senha']) && !empty($data['cli_senha'])) {
                $parsedBody->strlenMin('cli_senha', 6);
            }

            $result = clientsModel::edit($data, $args['id']);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Get(
        path: '/clients/{page}',
        operationId: 'listClients',
        summary: 'Listar todos os clientes',
        tags: ['Clients']
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', minimum: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Lista de clientes',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Client')
                        ),
                        new OA\Property(property: 'total', type: 'integer'),
                        new OA\Property(property: 'page', type: 'integer'),
                        new OA\Property(property: 'totalPages', type: 'integer')
                    ]
                )
            ]
        )
    )]
    public function listAll(Request $request, Response $response, array $args): Response
    {
        $page = $args['page'] ?? 1;
        $result = clientsModel::listAll($page);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Get(
        path: '/clients/search/{id}',
        operationId: 'getClientById',
        summary: 'Buscar cliente por ID',
        tags: ['Clients']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Cliente encontrado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Client')
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Cliente não encontrado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Cliente não encontrado')
            ]
        )
    )]
    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = clientsModel::getById($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Delete(
        path: '/clients/{id}',
        operationId: 'deleteClient',
        summary: 'Excluir cliente',
        tags: ['Clients']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Cliente excluído com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Cliente excluído com sucesso')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Cliente não encontrado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Cliente não encontrado')
            ]
        )
    )]
    static public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = clientsModel::deleted($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Post(
        path: '/clients/login',
        operationId: 'loginClient',
        summary: 'Login de cliente',
        tags: ['Clients']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    ref: '#/components/schemas/LoginClient'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Login realizado com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Login realizado com sucesso'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'token', type: 'string')
                    ]
                )
            ]
        )
    )]
    static public function login(Request $request, Response $response): Response
    {
        try {
            $parsed = $request->getParsedBody();
            if (empty($parsed)) {
                $raw = (string)$request->getBody();
                $parsed = json_decode($raw, true) ?? [];
            }
            $data = $parsed['data'] ?? $parsed;

            if (empty($data['cli_email']) || empty($data['cli_senha'])) {
                throw new \Exception("E-mail e senha são obrigatórios.");
            }

            $result = clientsModel::login($data);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Post(
        path: '/clients/recover-password',
        operationId: 'recoverPassword',
        summary: 'Recuperar senha',
        tags: ['Clients']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Nova senha enviada',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Nova senha enviada para seu e-mail.')
            ]
        )
    )]
    public function recoverPassword(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            if (empty($body)) {
                $raw = (string) $request->getBody();
                $body = json_decode($raw, true) ?? [];
            }
            $email = $body['email'] ?? null;

            if (!$email) {
                throw new \Exception('Email é obrigatório');
            }

            $result = clientsModel::recoverPassword($email);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Post(
        path: '/clients/resend-sms',
        operationId: 'resendSMS',
        summary: 'Reenviar SMS de verificação',
        tags: ['Clients']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'cli_id', type: 'integer')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'SMS reenviado com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Código SMS reenviado com sucesso.')
            ]
        )
    )]
    public function resendSMS(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            if (empty($body)) {
                $raw = (string) $request->getBody();
                $body = json_decode($raw, true) ?? [];
            }
            $cliId = $body['cli_id'] ?? null;

            if (!$cliId) {
                throw new \Exception('ID do cliente é obrigatório');
            }

            $result = clientsModel::resendSMS($cliId);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function confirmRecoverPassword(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            if (empty($body)) {
                $raw = (string) $request->getBody();
                $body = json_decode($raw, true) ?? [];
            }

            $cliId = $body['cli_id'] ?? null;
            $code = $body['code'] ?? null;
            $newPassword = $body['new_password'] ?? null;

            if (!$cliId || !$code || !$newPassword) {
                throw new \Exception('ID do cliente, código e nova senha são obrigatórios');
            }

            if (strlen($newPassword) < 6) {
                throw new \Exception('A senha deve ter no mínimo 6 caracteres');
            }

            $result = clientsModel::confirmRecoverPassword($cliId, $code, $newPassword);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}
