<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\cardapioRelUnidades as cardapioRelUnidadesModel;
use OpenApi\Attributes as OA;
use Functions\slim\getParsedBody as getParsedBody;

#[OA\Schema(
    schema: 'CardapioRelUnidades',
    description: 'Relação entre Cardápio e Unidade',
    required: ['cru_car_id', 'cru_uni_id'],
    properties: [
        new OA\Property(property: 'cru_id', type: 'integer'),
        new OA\Property(property: 'cru_car_id', type: 'integer', example: 1),
        new OA\Property(property: 'cru_uni_id', type: 'integer', example: 9),
        new OA\Property(property: 'cru_ativo', type: 'integer', example: 1),
    ]
)]
#[OA\Tag(name: 'CardapioRelUnidades', description: 'Relações entre cardápios e unidades')]
class cardapioRelUnidades
{
    #[OA\Post(
        path: '/cardapio-rel-unidades/',
        operationId: 'createCardapioRelUnidade',
        summary: 'Vincular unidade a um cardápio',
        tags: ['CardapioRelUnidades']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/CardapioRelUnidades')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Vínculo criado com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 201),
                new OA\Property(property: 'message', type: 'string', example: 'Cadastrado com sucesso')
            ]
        )
    )]
    #[OA\Response(
        response: 409,
        description: 'Relação já existente',
        content: new OA\JsonContent(
            properties: [new OA\Property(property: 'message', type: 'string')]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Erro de validação',
        content: new OA\JsonContent(
            properties: [new OA\Property(property: 'error', type: 'string')]
        )
    )]
    public function create(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;

            $result = cardapioRelUnidadesModel::create($data);

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
        path: '/cardapio-rel-unidades/{page}',
        operationId: 'listCardapioRelUnidades',
        summary: 'Listar relações (paginado, filtrado por unidade)',
        tags: ['CardapioRelUnidades']
    )]
    #[OA\Parameter(name: 'page', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'uni_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Lista paginada de relações',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CardapioRelUnidades')
                )
            ]
        )
    )]
    public function listAll(Request $request, Response $response, array $args): Response
    {
        $page  = $args['page'] ?? 1;
        $slug = $args['slug'] ?? null;
        $result = cardapioRelUnidadesModel::listAll($page, $slug);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Get(
        path: '/cardapio-rel-unidades/search/{id}',
        operationId: 'getCardapioRelUnidadeById',
        summary: 'Buscar relação por ID',
        tags: ['CardapioRelUnidades']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Relação encontrada',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'data', ref: '#/components/schemas/CardapioRelUnidades')
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Não encontrado')]
    public function getById(Request $request, Response $response, array $args): Response
    {
        $result = cardapioRelUnidadesModel::search($args['id']);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Get(
        path: '/cardapio-rel-unidades/cardapio/{car_id}',
        operationId: 'listRelByCardapio',
        summary: 'Listar unidades de um cardápio',
        tags: ['CardapioRelUnidades']
    )]
    #[OA\Parameter(name: 'car_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Unidades vinculadas ao cardápio',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CardapioRelUnidades'))
            ]
        )
    )]
    public function listByCardapio(Request $request, Response $response, array $args): Response
    {
        $result = cardapioRelUnidadesModel::listByCardapio($args['car_id']);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Get(
        path: '/cardapio-rel-unidades/unidade/{uni_id}',
        operationId: 'listRelByUnidade',
        summary: 'Listar cardápios de uma unidade',
        tags: ['CardapioRelUnidades']
    )]
    #[OA\Parameter(name: 'uni_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Cardápios vinculados à unidade',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CardapioRelUnidades'))
            ]
        )
    )]
    public function listByUnidade(Request $request, Response $response, array $args): Response
    {
        $result = cardapioRelUnidadesModel::listByUnidade($args['slug']);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Patch(
        path: '/cardapio-rel-unidades/{id}/status',
        operationId: 'activeDisableCardapioRelUnidade',
        summary: 'Ativar ou Desativar relação',
        tags: ['CardapioRelUnidades']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Status alterado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Editado com sucesso')
            ]
        )
    )]
    public function activeDisable(Request $request, Response $response, array $args): Response
    {
        $result = cardapioRelUnidadesModel::activeDisable($args['id']);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Delete(
        path: '/cardapio-rel-unidades/{id}',
        operationId: 'deleteCardapioRelUnidade',
        summary: 'Excluir relação cardápio x unidade',
        tags: ['CardapioRelUnidades']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Relação excluída com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Deletado com sucesso')
            ]
        )
    )]
    public function delete(Request $request, Response $response, array $args): Response
    {
        $result = cardapioRelUnidadesModel::deleted($args['id']);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }
}
