<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\unidades as unidadesModel;
use App\model\entity\pagamentosMetodos as pagamentosMetodosModel;
use App\model\entity\horarios as horariosModel;
use App\model\entity\horariosEntregas as horariosEntregasModel;
use App\model\entity\fretes as fretesModel;
use Functions\jwt\jwt;

class unidades
{
    public function listByContratante(Request $request, Response $response): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = $tokenData->user_con_id;

            $result = unidadesModel::listAll(1, $conId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function create(Request $request, Response $response): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = (int)$tokenData->user_con_id;

            $body = $request->getParsedBody();
            $data = is_array($body) ? ($body['data'] ?? $body) : [];

            if (! is_array($data) || empty($data['uni_titulo'])) {
                $payload = ['status' => 400, 'message' => 'uni_titulo é obrigatório'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (! empty($data['uni_slug'])) {
                $slug = trim((string)$data['uni_slug']);
                $exists = unidadesModel::query()
                    ->where('uni_slug', $slug)
                    ->where('uni_con_id', $conId)
                    ->where('uni_excluido', 0)
                    ->exists();
                if ($exists) {
                    $payload = ['status' => 409, 'message' => 'Já existe outra unidade com este slug'];
                    $response->getBody()->write(json_encode($payload));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                }
                $data['uni_slug'] = $slug;
            }

            $result = unidadesModel::create($data, $conId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = (int)$tokenData->user_con_id;
            $id = (int)($args['uni_id'] ?? 0);

            if ($id <= 0) {
                $payload = ['status' => 400, 'message' => 'uni_id inválido'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $owned = unidadesModel::query()
                ->where('uni_id', $id)
                ->where('uni_con_id', $conId)
                ->where('uni_excluido', 0)
                ->exists();
            if (! $owned) {
                $payload = ['status' => 404, 'message' => 'Unidade não encontrada'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $result = unidadesModel::deleted($id);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = (int)$tokenData->user_con_id;
            $id = (int)($args['uni_id'] ?? 0);

            if ($id <= 0) {
                $payload = ['status' => 400, 'message' => 'uni_id inválido'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $result = unidadesModel::search($id, $conId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function editConfiguracoes(Request $request, Response $response, array $args): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = (int)$tokenData->user_con_id;
            $id = (int)($args['uni_id'] ?? 0);

            if ($id <= 0) {
                $payload = ['status' => 400, 'message' => 'uni_id inválido'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $body = $request->getParsedBody();
            $data = is_array($body) ? ($body['data'] ?? $body) : [];

            if (! is_array($data) || count($data) === 0) {
                $payload = ['status' => 400, 'message' => 'Nenhum campo enviado'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $result = unidadesModel::editConfiguracoes($data, $id, $conId);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function getRecebimento(Request $request, Response $response, array $args): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = (int)$tokenData->user_con_id;
            $id = (int)($args['uni_id'] ?? 0);

            $owned = unidadesModel::query()
                ->where('uni_id', $id)
                ->where('uni_con_id', $conId)
                ->where('uni_excluido', 0)
                ->exists();
            if (! $owned) {
                $payload = ['status' => 404, 'message' => 'Unidade não encontrada'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $result = pagamentosMetodosModel::listForCms($id);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function editRecebimento(Request $request, Response $response, array $args): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = (int)$tokenData->user_con_id;
            $id = (int)($args['uni_id'] ?? 0);

            $owned = unidadesModel::query()
                ->where('uni_id', $id)
                ->where('uni_con_id', $conId)
                ->where('uni_excluido', 0)
                ->exists();
            if (! $owned) {
                $payload = ['status' => 404, 'message' => 'Unidade não encontrada'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $body = $request->getParsedBody();
            $data = is_array($body) ? ($body['data'] ?? $body) : [];
            $methods = is_array($data) ? ($data['metodos'] ?? $data) : [];

            if (! is_array($methods)) {
                $payload = ['status' => 400, 'message' => 'Payload inválido: metodos deve ser array'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $result = pagamentosMetodosModel::saveForUnidade($id, $methods);

            if (($result['status'] ?? 0) === 200) {
                $fresh = pagamentosMetodosModel::listForCms($id);
                $result['data'] = $fresh['data'] ?? [];
            }

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function getHorarios(Request $request, Response $response, array $args): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = (int)$tokenData->user_con_id;
            $id = (int)($args['uni_id'] ?? 0);

            $owned = unidadesModel::query()
                ->where('uni_id', $id)
                ->where('uni_con_id', $conId)
                ->where('uni_excluido', 0)
                ->exists();
            if (! $owned) {
                $payload = ['status' => 404, 'message' => 'Unidade não encontrada'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $atendimento = horariosModel::listByUnidade($id);
            $entrega = horariosEntregasModel::listByUnidade($id);

            $payload = [
                'status' => 200,
                'data' => [
                    'atendimento' => $atendimento['data'] ?? [],
                    'entrega' => $entrega['data'] ?? [],
                ],
            ];

            $response->getBody()->write(json_encode($payload));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function editHorarios(Request $request, Response $response, array $args): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = (int)$tokenData->user_con_id;
            $id = (int)($args['uni_id'] ?? 0);

            $owned = unidadesModel::query()
                ->where('uni_id', $id)
                ->where('uni_con_id', $conId)
                ->where('uni_excluido', 0)
                ->exists();
            if (! $owned) {
                $payload = ['status' => 404, 'message' => 'Unidade não encontrada'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $body = $request->getParsedBody();
            $data = is_array($body) ? ($body['data'] ?? $body) : [];
            $atendimento = is_array($data['atendimento'] ?? null) ? $data['atendimento'] : [];
            $entrega = is_array($data['entrega'] ?? null) ? $data['entrega'] : [];

            $resAtend = horariosModel::saveForUnidade($id, $atendimento);
            if (($resAtend['status'] ?? 0) !== 200) {
                $response->getBody()->write(json_encode($resAtend));
                return $response->withHeader('Content-Type', 'application/json')->withStatus($resAtend['status'] ?? 500);
            }

            $resEntrega = horariosEntregasModel::saveForUnidade($id, $entrega);
            if (($resEntrega['status'] ?? 0) !== 200) {
                $response->getBody()->write(json_encode($resEntrega));
                return $response->withHeader('Content-Type', 'application/json')->withStatus($resEntrega['status'] ?? 500);
            }

            $freshAtend = horariosModel::listByUnidade($id);
            $freshEntrega = horariosEntregasModel::listByUnidade($id);

            $payload = [
                'status' => 200,
                'message' => 'Horários atualizados',
                'data' => [
                    'atendimento' => $freshAtend['data'] ?? [],
                    'entrega' => $freshEntrega['data'] ?? [],
                ],
            ];

            $response->getBody()->write(json_encode($payload));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function getFretePublic(Request $request, Response $response, array $args): Response
    {
        try {
            $slug = (string)($args['slug'] ?? '');
            $cepClient = preg_replace('/\D+/', '', (string)($args['cep_client'] ?? '')) ?? '';
            $params = $request->getQueryParams();
            $lat = isset($params['lat']) && $params['lat'] !== '' ? (float)$params['lat'] : null;
            $lng = isset($params['lng']) && $params['lng'] !== '' ? (float)$params['lng'] : null;

            $result = unidadesModel::getFretePublic($slug, $cepClient, $lat, $lng);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function getFretes(Request $request, Response $response, array $args): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = (int)$tokenData->user_con_id;
            $id = (int)($args['uni_id'] ?? 0);

            $owned = unidadesModel::query()
                ->where('uni_id', $id)
                ->where('uni_con_id', $conId)
                ->where('uni_excluido', 0)
                ->exists();
            if (! $owned) {
                $payload = ['status' => 404, 'message' => 'Unidade não encontrada'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $result = fretesModel::listByUnidade($id);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function editFretes(Request $request, Response $response, array $args): Response
    {
        try {
            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $conId = (int)$tokenData->user_con_id;
            $id = (int)($args['uni_id'] ?? 0);

            $owned = unidadesModel::query()
                ->where('uni_id', $id)
                ->where('uni_con_id', $conId)
                ->where('uni_excluido', 0)
                ->exists();
            if (! $owned) {
                $payload = ['status' => 404, 'message' => 'Unidade não encontrada'];
                $response->getBody()->write(json_encode($payload));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $body = $request->getParsedBody();
            $data = is_array($body) ? ($body['data'] ?? $body) : [];
            $regras = is_array($data['regras'] ?? null) ? $data['regras'] : [];

            $resSave = fretesModel::saveForUnidade($id, $regras);
            if (($resSave['status'] ?? 0) !== 200) {
                $response->getBody()->write(json_encode($resSave));
                return $response->withHeader('Content-Type', 'application/json')->withStatus($resSave['status'] ?? 500);
            }

            $fresh = fretesModel::listByUnidade($id);
            $response->getBody()->write(json_encode($fresh));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }

    public function getBySlugPublic(Request $request, Response $response, array $args): Response
    {
        try {
            $slug = (string)($args['slug'] ?? '');

            $result = unidadesModel::getBySlugPublic($slug);

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
