<?php

namespace App\middleware;

use Slim\Psr7\Response as SlimResponse;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ResponseInterface as ResponseSlim;
use Psr\Http\Message\ServerRequestInterface as RequestSlim;
use Psr\Http\Server\RequestHandlerInterface as RequestHandlerSlim;
use App\helpers\unitScope as unitScopeHelper;

/**
 * Middleware que protege endpoints com {uni_id} na URL.
 *
 * Lê o argumento da rota, chama unitScopeHelper::assertAccess e:
 *   - Admin do Contratante (user_is_admin=true) passa direto
 *   - Demais usuários: precisam ter o uni_id em sua lista user_unidades
 *
 * Uso:
 *   $app->group('/v1/api/unidades/', function (...) { ... })
 *       ->add(new unitScope('uni_id'))
 *       ->add(new permission('unidade'))
 *       ->add(authorization::class . ':authorization');
 *
 * `paramName` é opcional (default 'uni_id') para uso em rotas com nomes diferentes.
 */
class unitScope
{
    private string $paramName;

    public function __construct(string $paramName = 'uni_id')
    {
        $this->paramName = $paramName;
    }

    public function __invoke(RequestSlim $request, RequestHandlerSlim $handler): ResponseSlim
    {
        try {
            $route = RouteContext::fromRequest($request)->getRoute();
            $args = $route ? $route->getArguments() : [];
            $uniId = isset($args[$this->paramName]) ? (int)$args[$this->paramName] : 0;

            if ($uniId <= 0) {
                // Rota dentro do grupo que não usa {uni_id}, deixa passar
                // (caso futuro de endpoint colateral no mesmo grupo).
                return $handler->handle($request);
            }

            unitScopeHelper::assertAccess($request, $uniId);
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $code = $e->getCode();
            // Se for um dos códigos HTTP esperados (401, 403, 400), retorna-o; senão 500
            $httpCode = in_array($code, [400, 401, 403], true) ? $code : 500;
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'error' => 'unit_scope_denied',
                'message' => $e->getMessage(),
            ]));
            return $response
                ->withStatus($httpCode)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}
