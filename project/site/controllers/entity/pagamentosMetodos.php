<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\pagamentosMetodos as pagamentosMetodosModel;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'PagamentosMetodos', description: 'Metodos de pagamento por unidade')]
class pagamentosMetodos
{
    #[OA\Get(
        path: '/public/pagamentos-metodos/{uni_id}/{frete_tipo}',
        operationId: 'listPagamentosMetodosByUnidade',
        summary: 'Lista metodos de pagamento disponiveis por unidade e tipo de frete',
        tags: ['PagamentosMetodos']
    )]
    public function listByUnidade(Request $request, Response $response, array $args): Response
    {
        try {
            $uniId = (int)($args['uni_id'] ?? 0);
            $freteTipo = (int)($args['frete_tipo'] ?? 3);

            $result = pagamentosMetodosModel::listByUnidade($uniId, $freteTipo);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}
