<?php

namespace App\controllers\entity;

use Functions\jwt\jwt;
use App\model\entity\user as userModel;
use Functions\cryptography\passwordHash;
use Functions\slim\getParsedBody as getParsedBody;
use Psr\Http\Message\ResponseInterface as ResponseSlim;
use Psr\Http\Message\ServerRequestInterface as RequestSlim;
use Functions\db\mysqlSite as mysql;

class user
{

    static public function login(RequestSlim $request, ResponseSlim $response, array $args): ResponseSlim
    {
        //get ip client user on cdn cloudflare
        $data = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)->getData();
        $data = userModel::login($data);
        $response->getBody()->write(\json_encode($data));
        $response = $response->withStatus($data['status'] ?? 500);
        $response = $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Max-Age', '86400');
        return $response;
    }

    static public function listAll(RequestSlim $request, ResponseSlim $response, array $args): ResponseSlim
    {
        $use_con_id = jwt::decodetoken($request->getHeader('Authorization')[0])->user_con_id;
        if ($use_con_id == null) {
            $response->getBody()->write(
                \json_encode(
                    [
                        'status' => 401,
                        'message' => 'Unauthorized'
                    ]
                )
            );
            $response = $response->withStatus(401);
            return $response->withHeader('Content-Type', 'application/json');
        }
        $id = $args['pag'] ?? 1;
        $data = userModel::listAll($id, $use_con_id);
        $response->getBody()->write(
            \json_encode($data)
        );
        $response = $response->withStatus($data['status'] ?? 500);
        return $response->withHeader('Content-Type', 'application/json');
    }

    static public function edit(RequestSlim $request, ResponseSlim $response, array $args): ResponseSlim
    {

        $data = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)
            ->jsonToArray($_POST)
            ->objectData('data')
            ->validInputEmpty('user_name')->isStringInput('user_name')
            ->validInputEmpty('user_email')->validEmail('user_email')
            ->isValidPassword('user_password', 'user_password_confirm', 8)
            ->validInputEmpty('user_role')
            ->getData();
        $data = userModel::edit($data, $args['id']);
        $response->getBody()->write(
            \json_encode($data)
        );
        $response = $response->withStatus($data['status'] ?? 500);
        return $response->withHeader('Content-Type', 'application/json');
    }

    static public function delete(RequestSlim $request, ResponseSlim $response, array $args): ResponseSlim
    {
        $data = userModel::deleted($args['id']);
        $response->getBody()->write(
            \json_encode($data)
        );
        $response = $response->withStatus($data['status'] ?? 500);
        return $response->withHeader('Content-Type', 'application/json');
    }

    static public function searchId(RequestSlim $request, ResponseSlim $response, array $args): ResponseSlim
    {
        $data = userModel::search($args['id']);
        $response->getBody()->write(
            \json_encode($data)
        );
        $response = $response->withStatus($data['status'] ?? 500);
        return $response->withHeader('Content-Type', 'application/json');
    }

    static public function create(RequestSlim $request, ResponseSlim $response, array $args): ResponseSlim
    {
        try {
            $con_id = jwt::decodetoken($request->getHeader('Authorization')[0])->user_con_id;
            $data = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)
                ->jsonToArray($_POST)
                ->objectData('data')
                ->validInputEmpty('user_name')->isStringInput('user_name')
                ->validInputEmpty('user_email')->validEmail('user_email')
                ->validInputEmpty('user_password')->isStringInput('user_password')
                ->validInputEmpty('user_role')
                ->getData();

            $data = userModel::create($data, $con_id);
            $response->getBody()->write(
                \json_encode($data)
            );
            $response = $response->withStatus($data['status'] ?? 500);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    static public function logout(RequestSlim $request, ResponseSlim $response, $args): ResponseSlim
    {
        $token = jwt::verifyToken($request->getHeader('authorization')[0] ?? null);
        if ($token !== true) {
            $response = $response->withStatus(302);
            return $response
                ->withHeader('Location', '/paineladm/login')
                ->withStatus(302);
        }
        $token = str_replace('Bearer ', '', $request->getHeader('authorization')[0] ?? null);
        $token = jwt::decodeToken($token);
        $sql = "INSERT INTO tb_jwt (jwt_token,jwt_exp,jwt_user_id) VALUES (:token,:exp,:user)";
        $param = array(
            array(
                ":token" => $request->getHeader('authorization')[0] ?? null,
                ":exp" => date('Y-m-d H:i:s', $token->exp),
                ":user" => (int)$token->id
            )
        );
        $sql = mysql::insert($sql, $param);
        \var_dump($sql);
        if (!\is_int((int)$sql)) {
            $response->getBody()->write(
                \json_encode(array("status" => "error", "message" => "Erro ao realizar logout!"))
            );
            $response = $response->withStatus(400);
            return $response;
        }
        $response->getBody()->write(
            \json_encode(array("status" => "success", "message" => "Logout realizado com sucesso!"))
        );
        $response = $response->withStatus(200);
        return  $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    static public function forgetPassword(RequestSlim $request, ResponseSlim $response): ResponseSlim
    {
        $data = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)->getData();
        $result = userModel::forgetPassword($data);
        $response->getBody()->write(\json_encode($result));
        return $response->withStatus($result['status'] ?? 500)
            ->withHeader('Content-Type', 'application/json');
    }

    static public function forgetPasswordCode(RequestSlim $request, ResponseSlim $response): ResponseSlim
    {
        $data = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)->getData();
        $result = userModel::forgetPasswordCode($data);
        $response->getBody()->write(\json_encode($result));
        return $response->withStatus($result['status'] ?? 500)
            ->withHeader('Content-Type', 'application/json');
    }

    static public function generationPassword(RequestSlim $request, ResponseSlim $response): ResponseSlim
    {
        $data = $request->getParsedBody() ?? (new getParsedBody)->filter($_POST)->getData();
        $result = userModel::generationPassword($data);
        $response->getBody()->write(\json_encode($result));
        return $response->withStatus($result['status'] ?? 500)
            ->withHeader('Content-Type', 'application/json');
    }
}
