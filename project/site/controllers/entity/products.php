<?php

namespace App\controllers\entity;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\model\entity\produtos as produtosModel;
use App\model\entity\produtosCategorias;
use App\model\entity\produtosImagens;
use App\model\entity\produtosRelCategorias;
use App\model\entity\produtosGrades;
use Hyperf\DbConnection\Db;
use Functions\slim\getParsedBody as getParsedBody;
use Functions\jwt\jwt;

class products
{
    public function create(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;

            $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
            $data['pro_con_id'] = $tokenData->user_con_id;

            $result = produtosModel::createRecord($data);

            // Salvar imagens se enviadas
            if ($result['status'] === 201 && isset($data['pro_imagens']) && is_array($data['pro_imagens'])) {
                $proId = $result['data']->pro_id;
                foreach ($data['pro_imagens'] as $index => $imgBase64) {
                    $isCapa = isset($imgBase64['isCapa']) ? $imgBase64['isCapa'] : ($index === 0);
                    $imgData = isset($imgBase64['base64']) ? $imgBase64['base64'] : $imgBase64;
                    produtosImagens::createImage($proId, $imgData, $isCapa);
                }
            }

            // Salvar categorias se enviadas
            if ($result['status'] === 201 && isset($data['pro_categorias']) && is_array($data['pro_categorias'])) {
                $proId = $result['data']->pro_id;
                foreach ($data['pro_categorias'] as $catId) {
                    $rel = new produtosRelCategorias();
                    $rel->pra_pro_id = $proId;
                    $rel->pra_prc_id = $catId;
                    $rel->save();
                }
            }

            // Salvar grades se enviadas
            if ($result['status'] === 201 && isset($data['pro_grades']) && is_array($data['pro_grades'])) {
                $proId = $result['data']->pro_id;
                foreach ($data['pro_grades'] as $gradeId) {
                    $grade = produtosGrades::find((int)$gradeId);
                    if (!$grade) continue;
                    Db::table('tb_produtos_rel_grades')->insert([
                        'prr_pro_id'         => $proId,
                        'prr_prg_id'         => $grade->prg_id,
                        'prr_prg_descricao'  => $grade->prg_descricao ?? '',
                        'prr_prg_pgt_id'     => $grade->prg_pgt_id ?? 1,
                        'prr_prg_qtd_min'    => $grade->prg_qtd_min ?? 0,
                        'prr_prg_qtd_gratis' => $grade->prg_qtd_gratis ?? 0,
                        'prr_prg_qtd_max'    => $grade->prg_qtd_max ?? 100,
                        'prr_prg_obrigatoria'=> $grade->prg_obrigatoria ?? 1,
                        'prr_excluido'       => 0,
                    ]);
                }
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

    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;
            $id = $args['id'];

            $result = produtosModel::edit($id, $data);

            // Atualizar categorias se enviadas
            if ($result['status'] === 200 && isset($data['pro_categorias']) && is_array($data['pro_categorias'])) {
                // Remover categorias existentes
                produtosRelCategorias::query()
                    ->where('pra_pro_id', $id)
                    ->delete();

                // Inserir novas
                foreach ($data['pro_categorias'] as $catId) {
                    $rel = new produtosRelCategorias();
                    $rel->pra_pro_id = $id;
                    $rel->pra_prc_id = $catId;
                    $rel->save();
                }
            }

            // Atualizar grades se enviadas
            if ($result['status'] === 200 && isset($data['pro_grades']) && is_array($data['pro_grades'])) {
                Db::table('tb_produtos_rel_grades')
                    ->where('prr_pro_id', $id)
                    ->update(['prr_excluido' => 1]);

                foreach ($data['pro_grades'] as $gradeId) {
                    $grade = produtosGrades::find((int)$gradeId);
                    if (!$grade) continue;
                    Db::table('tb_produtos_rel_grades')
                        ->updateOrInsert(
                            ['prr_pro_id' => $id, 'prr_prg_id' => $grade->prg_id],
                            [
                                'prr_prg_descricao'  => $grade->prg_descricao ?? '',
                                'prr_prg_pgt_id'     => $grade->prg_pgt_id ?? 1,
                                'prr_prg_qtd_min'    => $grade->prg_qtd_min ?? 0,
                                'prr_prg_qtd_gratis' => $grade->prg_qtd_gratis ?? 0,
                                'prr_prg_qtd_max'    => $grade->prg_qtd_max ?? 100,
                                'prr_prg_obrigatoria'=> $grade->prg_obrigatoria ?? 1,
                                'prr_excluido'       => 0,
                            ]
                        );
                }
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

    public function listAll(Request $request, Response $response, array $args): Response
    {
        $page = $args['page'] ?? 1;
        $result = produtosModel::listAll($page);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = produtosModel::getById($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    public function search(Request $request, Response $response, array $args): Response
    {
        $query = urldecode($args['query'] ?? '');
        $page = $request->getQueryParams()['page'] ?? 1;
        $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
        $conId = $tokenData->user_con_id ?? 0;
        $result = produtosModel::searchByTitle($query, $page, 10, $conId);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    public function listCategorias(Request $request, Response $response, array $args): Response
    {
        $tokenData = jwt::decodetoken($request->getHeader('Authorization')[0]);
        $conId = $tokenData->user_con_id ?? $args['con_id'];
        $result = produtosCategorias::listByContratante($conId);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    public function activeDisable(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = produtosModel::activeDisable($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $result = produtosModel::deleteRecord($id);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    public function uploadImage(Request $request, Response $response, array $args): Response
    {
        try {
            $body = $request->getParsedBody();
            $data = $body['data'] ?? $body;
            $proId = $args['id'];
            $imgBase64 = $data['imagem'] ?? '';
            $isCapa = $data['is_capa'] ?? false;

            if (empty($imgBase64)) {
                $response->getBody()->write(json_encode(['status' => 400, 'message' => 'Imagem é obrigatória']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $result = produtosImagens::createImage($proId, $imgBase64, $isCapa);
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus($result['status'] ?? 500);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function deleteImage(Request $request, Response $response, array $args): Response
    {
        $priId = $args['pri_id'];
        $result = produtosImagens::deleteImage($priId);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($result['status'] ?? 500);
    }

    public function setCoverImage(Request $request, Response $response, array $args): Response
    {
        try {
            $priId = $args['pri_id'];

            // Buscar a imagem para obter o pro_id
            $image = produtosImagens::query()
                ->where('pri_id', $priId)
                ->where('pri_excluido', 0)
                ->first();

            if (!$image) {
                $response->getBody()->write(json_encode(['status' => 404, 'message' => 'Imagem não encontrada']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $result = produtosImagens::setCapa($priId, $image->pri_pro_id);
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
