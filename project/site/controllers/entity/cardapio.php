<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\cardapio as cardapioModel;
use OpenApi\Attributes as OA;
use Functions\slim\getParsedBody as getParsedBody;
use Functions\jwt\jwt;

#[OA\Schema(
    schema: 'Cardapio',
    description: 'Modelo de Cardápio',
    required: ['car_titulo', 'car_hora_abertura', 'car_hora_fechamento', 'car_dia'],
    properties: [
        new OA\Property(property: 'car_id', type: 'integer'),
        new OA\Property(property: 'car_con_id', type: 'integer', nullable: true),
        new OA\Property(property: 'car_titulo', type: 'string', example: 'Cardápio do Almoço'),
        new OA\Property(property: 'car_hora_abertura', type: 'string', format: 'time', example: '11:00:00'),
        new OA\Property(property: 'car_hora_fechamento', type: 'string', format: 'time', example: '15:00:00'),
        new OA\Property(property: 'car_dia', type: 'string', example: 'segunda,terca,quarta'),
        new OA\Property(property: 'car_ativo', type: 'integer', example: 1),
    ]
)]
#[OA\Tag(name: 'Cardapio', description: 'Operações relacionadas a cardápios')]
class cardapio
{
    #[OA\Post(
        path: '/cardapio/',
        operationId: 'createCardapio',
        summary: 'Criar novo cardápio',
        tags: ['Cardapio']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Cardapio')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Cardápio criado com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 201),
                new OA\Property(property: 'message', type: 'string', example: 'Cadastrado com sucesso')
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
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;

            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $data['car_con_id'] = $tokenData->user_con_id;

            $result = cardapioModel::create($data);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Put(
        path: '/cardapio/{id}',
        operationId: 'updateCardapio',
        summary: 'Atualizar cardápio',
        tags: ['Cardapio']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Cardapio')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Cardápio atualizado com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Editado com sucesso')
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Erro de validação',
        content: new OA\JsonContent(
            properties: [new OA\Property(property: 'error', type: 'string')]
        )
    )]
    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;

            $id = $args['id'];
            $result = cardapioModel::edit($data, $id);

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
        path: '/cardapio/{page}',
        operationId: 'listCardapios',
        summary: 'Listar todos os cardápios',
        tags: ['Cardapio']
    )]
    #[OA\Parameter(name: 'page', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'con_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Lista de cardápios com paginação',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Cardapio')
                )
            ]
        )
    )]
    public function listAll(Request $request, Response $response, array $args): Response
    {
        $page = $args['page'] ?? 1;
        $conId = $request->getQueryParams()['con_id'] ?? null;
        $result = cardapioModel::listAll($page, $conId);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Get(
        path: '/cardapio/search/{id}',
        operationId: 'getCardapioById',
        summary: 'Buscar cardápio por ID',
        tags: ['Cardapio']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Cardápio encontrado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'data', ref: '#/components/schemas/Cardapio')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Cardápio não encontrado',
        content: new OA\JsonContent(
            properties: [new OA\Property(property: 'message', type: 'string', example: 'Not found')]
        )
    )]
    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = cardapioModel::search($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Get(
        path: '/cardapio/contratante/{con_id}',
        operationId: 'listCardapiosByContratante',
        summary: 'Listar cardápios de um contratante',
        tags: ['Cardapio']
    )]
    #[OA\Parameter(name: 'con_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Lista de cardápios do contratante',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Cardapio')
                )
            ]
        )
    )]
    public function listByContratante(Request $request, Response $response, array $args): Response
    {
        $conId = $args['con_id'];
        $result = cardapioModel::listByContratante($conId);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Patch(
        path: '/cardapio/{id}/status',
        operationId: 'activeDisableCardapio',
        summary: 'Ativar ou Desativar cardápio',
        tags: ['Cardapio']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Status do cardápio alterado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Editado com sucesso')
            ]
        )
    )]
    public function activeDisable(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = cardapioModel::activeDisable($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Delete(
        path: '/cardapio/{id}',
        operationId: 'deleteCardapio',
        summary: 'Excluir cardápio',
        tags: ['Cardapio']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Cardápio excluído com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Deletado com sucesso')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Cardápio não encontrado',
        content: new OA\JsonContent(
            properties: [new OA\Property(property: 'message', type: 'string', example: 'Cardápio não encontrado')]
        )
    )]
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = cardapioModel::deleted($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }
}
