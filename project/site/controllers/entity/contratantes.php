<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\contratantes as contratantesModel;
use OpenApi\Attributes as OA;
use Functions\slim\getParsedBody as getParsedBody;

#[OA\Schema(
    schema: 'Contratante',
    description: 'Modelo de Contratante',
    required: ['con_titulo', 'con_razaosocial', 'con_cnpj', 'con_email', 'con_telefone'],
    properties: [
        new OA\Property(property: 'con_id', type: 'integer'),
        new OA\Property(property: 'con_titulo', type: 'string'),
        new OA\Property(property: 'con_title', type: 'string'),
        new OA\Property(property: 'con_descricao', type: 'string'),
        new OA\Property(property: 'con_razaosocial', type: 'string'),
        new OA\Property(property: 'con_cnpj', type: 'string'),
        new OA\Property(property: 'con_email', type: 'string', format: 'email'),
        new OA\Property(property: 'con_telefone', type: 'string'),
        new OA\Property(property: 'con_preco', type: 'number', format: 'float'),
        new OA\Property(property: 'con_googleanalytics', type: 'string'),
        new OA\Property(property: 'con_theme', type: 'string'),
        new OA\Property(property: 'con_javascript', type: 'string'),
        new OA\Property(property: 'con_pwa_name', type: 'string'),
        new OA\Property(property: 'con_pwa_color', type: 'string'),
        new OA\Property(property: 'con_promocao_aberta', type: 'integer', example: 1)
    ]
)]
#[OA\Tag(name: 'Contratantes', description: 'Operações relacionadas a contratantes')]
class contratantes
{
    #[OA\Post(
        path: '/contratantes/',
        operationId: 'createContratante',
        summary: 'Criar novo contratante',
        tags: ['Contratantes']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    ref: '#/components/schemas/Contratante'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Contratante criado com sucesso',
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
            $data = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)
                ->jsonToArray($_POST)
                ->objectData('data')
                ->validInputEmpty('con_titulo')
                ->validInputEmpty('con_razaosocial')
                ->validInputEmpty('con_cnpj')
                ->validInputEmpty('con_email')
                ->validInputEmpty('con_telefone')
                ->getData();

            $result = contratantesModel::create($data);

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
        path: '/contratantes/{id}',
        operationId: 'updateContratante',
        summary: 'Atualizar contratante',
        tags: ['Contratantes']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    ref: '#/components/schemas/Contratante'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Contratante atualizado com sucesso',
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
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Mensagem de erro')
            ]
        )
    )]
    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)
                ->jsonToArray($_POST)
                ->objectData('data')
                ->getData();

            $id = $args['id'];
            $result = contratantesModel::edit($data, $id);

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
        path: '/contratantes/{page}',
        operationId: 'listContratantes',
        summary: 'Listar todos os contratantes',
        tags: ['Contratantes']
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', minimum: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Lista de contratantes com paginação',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(
                    property: 'pagination',
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer')
                    ]
                ),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Contratante')
                )
            ]
        )
    )]
    public function listAll(Request $request, Response $response, array $args): Response
    {
        $page = $args['page'] ?? 1;
        $result = contratantesModel::listAll($page);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Get(
        path: '/contratantes/search/{id}',
        operationId: 'getContratanteById',
        summary: 'Buscar contratante por ID',
        tags: ['Contratantes']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Contratante encontrado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'data', ref: '#/components/schemas/Contratante')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Contratante não encontrado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 404),
                new OA\Property(property: 'message', type: 'string', example: 'Not found')
            ]
        )
    )]
    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = contratantesModel::search($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Get(
        path: '/contratantes/search-url/{url}/{cep_client}',
        operationId: 'searchContratanteUrl',
        summary: 'Buscar contratante por URL da loja e calcular distância pelo CEP',
        tags: ['Contratantes']
    )]
    #[OA\Parameter(
        name: 'url',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: 'www.carolidouces.com.br')
    )]
    #[OA\Parameter(
        name: 'cep_client',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', example: '59094100')
    )]
    #[OA\Response(
        response: 200,
        description: 'Contratante encontrado por URL',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'data', ref: '#/components/schemas/Contratante')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'URL não encontrada',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 404),
                new OA\Property(property: 'message', type: 'string', example: 'Not found')
            ]
        )
    )]
    public function searchUrl(Request $request, Response $response, array $args): Response
    {
        try {
            $url = $args['url'] ?? null;
            $cepClient = $args['cep_client'] ?? null;

            if (!$url) {
                throw new \Exception('URL é obrigatória para a busca');
            }

            $result = contratantesModel::search_url($url, $cepClient);
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
        path: '/contratantes/search-by-query/{url}',
        operationId: 'searchContratanteByQuery',
        summary: 'Buscar contratante por URL da loja usando CEP ou texto de endereço',
        tags: ['Contratantes']
    )]
    public function searchByQuery(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)
                ->jsonToArray($_POST)
                ->getData();
            $url = $args['url'] ?? null;
            $query = $data['q'] ?? '';
            $clientLat = isset($data['lat']) && $data['lat'] !== '' ? (float)$data['lat'] : null;
            $clientLng = isset($data['lng']) && $data['lng'] !== '' ? (float)$data['lng'] : null;

            if (!$url) {
                throw new \Exception('URL é obrigatória para a busca');
            }

            if (empty($query)) {
                throw new \Exception('Informe um CEP ou endereço para buscar');
            }

            $result = contratantesModel::search_by_query($url, $query, $clientLat, $clientLng);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Patch(
        path: '/contratantes/{id}/status',
        operationId: 'activeDisableContratante',
        summary: 'Ativar ou Desativar contratante',
        tags: ['Contratantes']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Status do contratante alterado',
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
        $result = contratantesModel::activeDisable($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    #[OA\Delete(
        path: '/contratantes/{id}',
        operationId: 'deleteContratante',
        summary: 'Excluir contratante',
        tags: ['Contratantes']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Contratante excluído com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Deletado com sucesso')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Contratante não encontrado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 404),
                new OA\Property(property: 'message', type: 'string', example: 'Contratante não encontrado')
            ]
        )
    )]
    static public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = contratantesModel::deleted($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }
}
