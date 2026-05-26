<?php

namespace App\middleware;

use Slim\Psr7\Response as SlimResponse;
use Psr\Http\Message\ResponseInterface as ResponseSlim;
use Psr\Http\Message\ServerRequestInterface as RequestSlim;
use Psr\Http\Server\RequestHandlerInterface as RequestHandlerSlim;
use Functions\jwt\jwtCms;

/**
 * Middleware de permissão por rota+ação.
 *
 * Uso:
 *   $app->group('/v1/api/unidades/', function (...) { ... })
 *       ->add(new permission('unidade', 'edit'))
 *       ->add(authorization::class . ':authorization');
 *
 * Para grupos cujas ações variam por verbo HTTP, use `null` como action e
 * o middleware deriva da requisição:
 *   GET → read, POST → insert, PUT/PATCH → edit, DELETE → remove
 *
 * Wildcard: usuário cujo JWT contém uma permissão com `route.value === '*'`
 * passa por qualquer rota (Administrador do Contratante).
 */
class permission
{
    private string $route;
    private ?string $action;

    public function __construct(string $route, ?string $action = null)
    {
        $this->route = $route;
        $this->action = $action;
    }

    public function __invoke(RequestSlim $request, RequestHandlerSlim $handler): ResponseSlim
    {
        try {
            $auth = $request->getHeader('Authorization')[0] ?? null;
            if (!$auth) {
                return self::deny(401, 'missing_token');
            }
            $token = jwtCms::decodetoken($auth);
            if (!is_object($token)) {
                return self::deny(401, 'invalid_token');
            }

            $userPerms = isset($token->user_permissions) ? (array) $token->user_permissions : [];
            $requiredAction = $this->action ?? self::actionFromMethod($request->getMethod());

            foreach ($userPerms as $perm) {
                $routeValue = is_object($perm) ? ($perm->route->value ?? null) : ($perm['route']['value'] ?? null);
                $actions    = is_object($perm) ? ((array)($perm->actions ?? [])) : ($perm['actions'] ?? []);

                $matchesRoute = $routeValue === '*' || $routeValue === $this->route;
                if (!$matchesRoute) continue;

                if (in_array($requiredAction, $actions, true)) {
                    return $handler->handle($request);
                }
            }

            return self::deny(403, 'not_permission', [
                'required_route' => $this->route,
                'required_action' => $requiredAction,
            ]);
        } catch (\Throwable $e) {
            return self::deny(500, 'permission_check_error', ['details' => $e->getMessage()]);
        }
    }

    // Compatibilidade com chamadas antigas que talvez ainda existam:
    // `permission::class . ':authorization'` — sem parâmetros.
    public static function authorization(RequestSlim $request, RequestHandlerSlim $handler): ResponseSlim
    {
        $self = new self('*', null);
        return $self->__invoke($request, $handler);
    }

    private static function actionFromMethod(string $method): string
    {
        switch (strtoupper($method)) {
            case 'POST':   return 'insert';
            case 'PUT':
            case 'PATCH':  return 'edit';
            case 'DELETE': return 'remove';
            case 'GET':
            default:       return 'read';
        }
    }

    private static function deny(int $code, string $message, array $extra = []): ResponseSlim
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode(array_merge(['error' => $message], $extra)));
        return $response
            ->withStatus($code)
            ->withHeader('Content-Type', 'application/json');
    }
}
