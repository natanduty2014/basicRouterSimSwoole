<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\dataExtract;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DataExportResponse',
    description: 'Resposta da extração de dados',
    properties: [
        new OA\Property(property: 'status', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Dados extraídos com sucesso'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(
            property: 'pagination',
            properties: [
                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                new OA\Property(property: 'first_page_url', type: 'integer', example: 1),
                new OA\Property(property: 'from', type: 'integer', example: 1),
                new OA\Property(property: 'last_page', type: 'integer', example: 3),
                new OA\Property(property: 'last_page_url', type: 'integer', example: 3),
                new OA\Property(property: 'next_page_url', type: 'integer', example: 2, nullable: true),
                new OA\Property(property: 'per_page', type: 'integer', example: 50),
                new OA\Property(property: 'prev_page_url', type: 'integer', example: null, nullable: true),
                new OA\Property(property: 'to', type: 'integer', example: 50),
                new OA\Property(property: 'total', type: 'integer', example: 150)
            ]
        ),
        new OA\Property(property: 'total_records', type: 'integer', example: 150),
        new OA\Property(property: 'is_incremental', type: 'boolean', example: true),
        new OA\Property(property: 'modified_column', type: 'string', example: 'updated_at', nullable: true)
    ]
)]

#[OA\Schema(
    schema: 'TableInfoResponse',
    description: 'Informações sobre a estrutura da tabela',
    properties: [
        new OA\Property(property: 'status', type: 'integer', example: 200),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(property: 'table_name', type: 'string', example: 'tb_clientes'),
                new OA\Property(
                    property: 'columns',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'COLUMN_NAME', type: 'string', example: 'cli_id'),
                            new OA\Property(property: 'DATA_TYPE', type: 'string', example: 'int'),
                            new OA\Property(property: 'IS_NULLABLE', type: 'string', example: 'NO'),
                            new OA\Property(property: 'COLUMN_KEY', type: 'string', example: 'PRI'),
                            new OA\Property(property: 'EXTRA', type: 'string', example: 'auto_increment')
                        ]
                    )
                ),
                new OA\Property(property: 'detected_modified_column', type: 'string', example: 'updated_at', nullable: true),
                new OA\Property(property: 'supports_incremental', type: 'boolean', example: true)
            ]
        )
    ]
)]

#[OA\Tag(name: 'Data Export', description: 'Operações de extração de dados para Engenharia de Dados')]
class dataExport
{
    #[OA\Get(
        path: '/api/v1/dados/{table}/{page}/{per_page}',
        operationId: 'extractTableData',
        summary: 'Extrair dados de uma tabela (carga completa ou incremental)',
        description: 'Endpoint para extração de dados de tabelas do banco. Suporta carga completa (todos os registros) ou carga incremental (apenas registros modificados após um timestamp específico). Use /api/v1/dados/{table} para página 1 com 50 registros, /api/v1/dados/{table}/{page} para especificar página, ou /api/v1/dados/{table}/{page}/{per_page} para controle total.',
        security: [['bearerAuth' => []]],
        tags: ['Data Export']
    )]
    #[OA\Parameter(
        name: 'table',
        in: 'path',
        required: true,
        description: 'Nome da tabela do banco de dados',
        schema: new OA\Schema(type: 'string', example: 'tb_clientes')
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'path',
        required: false,
        description: 'Número da página (padrão: 1)',
        schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
    )]
    #[OA\Parameter(
        name: 'per_page',
        in: 'path',
        required: false,
        description: 'Registros por página (padrão: 50, máximo: 1000)',
        schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 1000, example: 50)
    )]
    #[OA\Parameter(
        name: 'modified_since',
        in: 'query',
        required: false,
        description: 'Timestamp ISO 8601 para carga incremental. Se omitido, retorna todos os registros (carga completa). Formato: YYYY-MM-DDTHH:MM:SSZ',
        schema: new OA\Schema(type: 'string', format: 'date-time', example: '2023-12-01T16:56:37Z')
    )]
    #[OA\Response(
        response: 200,
        description: 'Dados extraídos com sucesso',
        content: new OA\JsonContent(ref: '#/components/schemas/DataExportResponse')
    )]
    #[OA\Response(
        response: 400,
        description: 'Erro de validação (nome de tabela inválido, timestamp inválido ou tabela sem coluna de modificação)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 400),
                new OA\Property(property: 'message', type: 'string', example: 'Parâmetro modified_since inválido. Use formato ISO 8601'),
                new OA\Property(property: 'error', type: 'string', example: 'INVALID_MODIFIED_SINCE')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Não autorizado - Token JWT inválido ou ausente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 401),
                new OA\Property(property: 'message', type: 'string', example: 'Unauthorized Token inválido')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Tabela não encontrada',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 404),
                new OA\Property(property: 'message', type: 'string', example: 'Tabela não encontrada'),
                new OA\Property(property: 'error', type: 'string', example: 'TABLE_NOT_FOUND')
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Erro interno do servidor',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 500),
                new OA\Property(property: 'message', type: 'string', example: 'Erro ao extrair dados'),
                new OA\Property(property: 'error', type: 'string', example: 'INTERNAL_ERROR')
            ]
        )
    )]
    public static function get(Request $request, Response $response, array $args): Response
    {
        try {
            // Obter o nome da tabela da rota
            $table = $args['table'];
            
            // Obter page e per_page da URL (args) ou usar padrões
            $page = isset($args['page']) ? (int)$args['page'] : 1;
            $perPage = isset($args['per_page']) ? (int)$args['per_page'] : 1000;

            // Obter modified_since da query string
            $queryParams = $request->getQueryParams();
            $modifiedSince = $queryParams['modified_since'] ?? null;

            // Chamar o model para extrair os dados
            $result = dataExtract::extractData($table, $modifiedSince, $page, $perPage);

            // Retornar a resposta
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $error = [
                'status' => 500,
                'message' => 'Erro inesperado',
                'error' => 'INTERNAL_ERROR',
                'details' => $e->getMessage()
            ];
            $response->getBody()->write(json_encode($error));
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    #[OA\Get(
        path: '/api/v1/dados/{table}/info',
        operationId: 'getTableInfo',
        summary: 'Obter informações sobre a estrutura da tabela',
        description: 'Retorna a estrutura completa da tabela, incluindo colunas, tipos de dados e a coluna de modificação detectada.',
        security: [['bearerAuth' => []]],
        tags: ['Data Export']
    )]
    #[OA\Parameter(
        name: 'table',
        in: 'path',
        required: true,
        description: 'Nome da tabela do banco de dados',
        schema: new OA\Schema(type: 'string', example: 'tb_clientes')
    )]
    #[OA\Response(
        response: 200,
        description: 'Informações da tabela obtidas com sucesso',
        content: new OA\JsonContent(ref: '#/components/schemas/TableInfoResponse')
    )]
    #[OA\Response(
        response: 401,
        description: 'Não autorizado - Token JWT inválido ou ausente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 401),
                new OA\Property(property: 'message', type: 'string', example: 'Unauthorized Token inválido')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Tabela não encontrada',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'integer', example: 404),
                new OA\Property(property: 'message', type: 'string', example: 'Tabela não encontrada')
            ]
        )
    )]
    public static function getInfo(Request $request, Response $response, array $args): Response
    {
        try {
            // Obter o nome da tabela da rota
            $table = $args['table'] ?? '';

            // Chamar o model para obter informações da tabela
            $result = dataExtract::getTableInfo($table);

            // Retornar a resposta
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $error = [
                'status' => 500,
                'message' => 'Erro ao obter informações da tabela',
                'details' => $e->getMessage()
            ];
            $response->getBody()->write(json_encode($error));
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}
