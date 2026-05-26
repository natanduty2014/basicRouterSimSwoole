<?php

namespace App\model\entity;

use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;
use Functions\cryptography\passwordHash;
use Functions\jwt\jwtCms;
use Functions\image\image;
use Functions\api\email;
use Functions\db\redis;
use App\model\entity\usersRelUnidades;

class user extends Model
{
    protected ?string $table = 'tb_users';
    protected string $primaryKey = 'use_id';
    public bool $timestamps = true;
    const CREATED_AT = 'use_register';
    const UPDATED_AT = 'use_update';

    public static function login($data): array
    {
        try {
            $payload = is_array($data) ? $data : (json_decode($data, true)['data'] ?? []);
            $payload = $payload['data'] ?? $payload; // Suporte para payload aninhado em "data"
            if (empty($payload['user_email']) || empty($payload['user_password'])) {
                return ['status' => 401, 'message' => 'Unauthorized - Dados incompletos', 'debug' => $payload];
            }
            // Busca usuário + grupo + contratante
            $row = self::query()
                ->where('tb_users.use_email', $payload['user_email'])
                ->where('tb_users.use_deleted', 0)
                ->join('tb_users_groups', 'tb_users.use_usg_id', '=', 'tb_users_groups.usg_id')
                ->select('tb_users.*', 'tb_users_groups.usg_permissions', 'tb_users_groups.usg_title')
                ->first();
            if (! $row) {
                // Debug: Verificar se o usuário existe mas falhou nos joins
                $check = self::query()->where('use_email', $payload['user_email'])->first();
                $reason = 'User completely not found';
                if ($check) {
                    $reason = 'User exists but Group (' . $check->use_usg_id . ') or Contractor (' . $check->use_con_id . ') missing/invalid/deleted';
                }
                return ['status' => 404, 'message' => 'User not found: ' . $reason, 'debug_email' => $payload['user_email']];
            }

            // Verificar se a senha no banco está em formato MD5 (senha antiga)
            // MD5 tem exatamente 32 caracteres hexadecimais
            $isMD5Password = preg_match('/^[a-f0-9]{32}$/i', $row->use_password);

            if ($isMD5Password) {
                // Senha está no formato antigo MD5, precisa redefinir
                return [
                    'status' => 426, // 426 Upgrade Required
                    'message' => 'password_reset_required',
                    'error_code' => 'MD5_PASSWORD_DETECTED',
                    'data' => [
                        'email' => $payload['user_email'],
                        'requires_password_reset' => true
                    ]
                ];
            }

            // Você já usa password_hash na criação — aqui tanto faz usar password_verify nativo
            if (! password_verify($payload['user_password'], $row->use_password)) {
                return ['status' => 404, 'message' => 'Login incorreto (senha inválida).'];
            }

            $permissions = [];
            if (! empty($row->usg_permissions)) {
                $decoded = json_decode($row->usg_permissions, true);
                $permissions = $decoded['permissions'] ?? [];
            }

            // Resolve escopo de unidades. Admin do Contratante = acesso a todas
            // (não persistido na pivot por convenção); demais users → tb_users_rel_unidades.
            $isAdmin = ($row->usg_title ?? '') === 'Administrador do Contratante';
            $unidades = $isAdmin
                ? []
                : usersRelUnidades::listUniIdsByUser((int)$row->use_id);

            $tokenData = $row->toArray();
            $tokenData['is_admin'] = $isAdmin;
            $tokenData['unidades'] = $unidades;

            return [
                'status' => 200,
                'message' => 'success',
                'token' => jwtCms::generator($tokenData, $permissions),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'message' => 'Server Error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }
    }

    public static function edit($data, $id): array
    {
        Db::beginTransaction();
        try {
            $u = self::query()->find($id);
            if (! $u || (int)$u->use_deleted === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Usuário não encontrado'];
            }

            // avatar
            $imagePath = null;
            if (isset($data['user_avatar']) && $data['user_avatar'] !== $u->use_avatar) {
                $src = './public/uploads/midias/';
                $uploaded = image::upload($data['user_avatar'], $src);
                $imagePath = '/public/uploads/midias/' . $uploaded;
            }

            // senha
            $newHash = null;
            if (isset($data['user_password']) && $data['user_password'] !== '') {
                $newHash = passwordHash::passwordHash($data['user_password']);
            }

            $u->use_name  = $data['user_name']  ?? $u->use_name;
            $u->use_email = $data['user_email'] ?? $u->use_email;
            $u->use_password = $newHash ?? $u->use_password;
            $u->use_avatar   = $imagePath ?? $u->use_avatar;
            $u->use_usg_id   = isset($data['user_role']) ? (int)$data['user_role'] : $u->use_usg_id;

            $u->save();

            // Sincroniza unidades sempre que o cliente enviou o campo, ou sempre
            // que o role mudou para um não-admin (precisamos limpar a pivot do
            // antigo admin que não tinha unidades).
            $isAdminRole = (int)$u->use_usg_id === 3;
            if (isset($data['user_unidades']) || $isAdminRole) {
                $unidades = $isAdminRole
                    ? []
                    : (isset($data['user_unidades']) && is_array($data['user_unidades'])
                        ? $data['user_unidades']
                        : []);
                usersRelUnidades::syncForUser((int)$u->use_id, $unidades);
            }

            Db::commit();
            return ['status' => 200, 'message' => 'Editado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao editar'];
        }
    }

    public static function activeDisable($id): array
    {
        Db::beginTransaction();
        try {
            $u = self::query()->find($id);
            if (! $u || (int)$u->use_deleted === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Usuário não encontrado'];
            }
            $u->use_actived = (int) (! (int)$u->use_actived);
            $u->save();
            Db::commit();
            return ['status' => 200, 'message' => 'Editado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao editar'];
        }
    }

    public static function deleted($id): array
    {
        Db::beginTransaction();
        try {
            $u = self::query()->find($id);
            if (! $u || (int)$u->use_deleted === 1) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Usuário não encontrado'];
            }
            $u->use_deleted = 1;
            $u->save();
            Db::commit();
            return ['status' => 200, 'message' => 'Deletado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 404, 'message' => 'Erro ao deletar'];
        }
    }

    public static function listAll($pag, $ass_con_id): array
    {
        $page = (int)($pag ?? 1);

        try {
            $p = self::query()
                ->where('tb_users.use_deleted', 0)
                ->where('tb_users.use_con_id', $ass_con_id)
                ->join('tb_users_groups', 'tb_users_groups.usg_id', '=', 'tb_users.use_usg_id')
                ->orderBy('tb_users.use_id', 'asc')
                ->select(
                    'tb_users.use_id as user_id',
                    'tb_users.use_email as user_email',
                    'tb_users.use_name as user_name',
                    'tb_users.use_avatar as user_avatar',
                    'tb_users.use_usg_id as user_usg_id',
                    'tb_users_groups.usg_title as user_role'
                )
                ->paginate(10, ['*'], 'page', $page);

            $rows = $p->items();
            if (count($rows) === 0) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            $data = array_map(fn($r) => is_array($r) ? $r : (method_exists($r, 'toArray') ? $r->toArray() : (array)$r), $rows);

            // Anexa unidades de cada usuário (uma única query para todos)
            $useIds = array_map(fn($u) => (int)($u['user_id'] ?? 0), $data);
            $unitsByUser = [];
            if (!empty($useIds)) {
                $unitRows = Db::table('tb_users_rel_unidades as uru')
                    ->leftJoin('tb_unidades as u', 'u.uni_id', '=', 'uru.uru_uni_id')
                    ->whereIn('uru.uru_use_id', $useIds)
                    ->where('uru.uru_excluido', 0)
                    ->where('uru.uru_ativo', 1)
                    ->select('uru.uru_use_id', 'u.uni_id', 'u.uni_titulo')
                    ->get();
                foreach ($unitRows as $ur) {
                    $unitsByUser[(int)$ur->uru_use_id][] = [
                        'uni_id' => (int)$ur->uni_id,
                        'uni_titulo' => $ur->uni_titulo,
                    ];
                }
            }
            foreach ($data as &$u) {
                $u['user_unidades'] = $unitsByUser[(int)($u['user_id'] ?? 0)] ?? [];
            }
            unset($u);

            return [
                'pagination' => [
                    'current_page' => $p->currentPage(),
                    'first_page_url' => 1,
                    'from' => $p->firstItem(),
                    'last_page' => $p->lastPage(),
                    'last_page_url' => $p->lastPage(),
                    'next_page_url' => $p->nextPageUrl() ? $p->currentPage() + 1 : null,
                    'per_page' => $p->perPage(),
                    'prev_page_url' => $p->previousPageUrl() ? $p->currentPage() - 1 : null,
                    'to' => $p->lastItem(),
                    'total' => $p->total(),
                ],
                'status' => 200,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function search($id): array
    {
        try {
            $row = self::query()
                ->where('tb_users.use_id', $id)
                ->where('tb_users.use_deleted', 0)
                ->join('tb_users_groups', 'tb_users.use_usg_id', '=', 'tb_users_groups.usg_id')
                ->select('tb_users.*', 'tb_users_groups.usg_id', 'tb_users_groups.usg_title')
                ->first();

            if (! $row) {
                return ['status' => 404, 'message' => 'Not found'];
            }

            $mapped = [
                'use_id'        => $row->use_id,
                'user_id'       => $row->use_id,
                'user_email'    => $row->use_email,
                'user_name'     => $row->use_name,
                'user_avatar'   => $row->use_avatar,
                'user_role'     => $row->usg_id,
                'user_role_title' => $row->usg_title,
                'user_unidades' => usersRelUnidades::listUniIdsByUser((int)$row->use_id),
            ];

            return ['status' => 200, 'data' => $mapped];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function create($data, $con_id): array
    {
        Db::beginTransaction();
        try {
            // email único
            if (self::query()->where('use_email', $data['user_email'])->where('use_deleted', 0)->exists()) {
                Db::rollBack();
                return ['status' => 400, 'message' => 'email_already_exists'];
            }
            // senha min 8
            if (strlen($data['user_password'] ?? '') < 8) {
                Db::rollBack();
                return ['status' => 400, 'message' => 'password_invalid'];
            }

            // avatar
            if (empty($data['user_avatar'])) {
                $imagePath = '/public/uploads/profile.png';
            } else {
                $src = './public/uploads/midias/';
                $uploaded = image::upload($data['user_avatar'], $src);
                $imagePath = '/public/uploads/midias/' . $uploaded;
            }

            $u = new self();
            $u->use_con_id = $con_id;
            $u->use_name   = $data['user_name'];
            $u->use_email  = $data['user_email'];
            $u->use_avatar = $imagePath;
            $u->use_usg_id = (int) $data['user_role'];
            $u->use_password = passwordHash::passwordHash($data['user_password']);
            $u->save();

            // Sincroniza unidades acessíveis (pivot). Admin do Contratante não usa
            // a pivot (acessa todas via convenção do JWT/middleware).
            $roleId = (int) $data['user_role'];
            $isAdminRole = $roleId === 3; // 3 = Administrador do Contratante (seed)
            if (!$isAdminRole) {
                $unidades = isset($data['user_unidades']) && is_array($data['user_unidades'])
                    ? $data['user_unidades']
                    : [];
                usersRelUnidades::syncForUser((int)$u->use_id, $unidades);
            }

            Db::commit();
            return ['status' => 201, 'message' => 'Cadastrado com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 400, 'message' => 'Erro ao cadastrar'];
        }
    }

    public static function forgetPassword($data): array
    {
        $payload = is_array($data) ? $data : (json_decode($data, true)['data'] ?? []);
        $emailAddr = $payload['user_email'] ?? '';
        $phoneNumber = $payload['user_phone'] ?? '';

        // Validar email
        if (! filter_var($emailAddr, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 400, 'message' => 'email_invalid'];
        }

        // Validar telefone (formato brasileiro básico) - OBRIGATÓRIO
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 11) {
            return ['status' => 400, 'message' => 'phone_invalid'];
        }

        // Verificar se usuário existe
        $user = self::query()
            ->where('use_email', $emailAddr)
            ->where('use_deleted', 0)
            ->first();

        if (! $user) {
            return ['status' => 404, 'message' => 'email_not_found'];
        }

        // Gerar código de 6 dígitos
        $code = random_int(100000, 999999);

        // Enviar SMS via Zenvia
        try {
            $message = "Seu código de recuperação Refácil é: {$code}. Válido por 10 minutos.";
            \Functions\api\ZenviaClient::enviarSMS($cleanPhone, $message, false);
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => 'sms_send_error', 'details' => $e->getMessage()];
        }

        // Salvar no Redis com 10 minutos de expiração (600 segundos)
        $redisData = [
            'code'   => $code,
            'email'  => $emailAddr,
            'phone'  => $cleanPhone,
            'date'   => date('Y-m-d H:i:s'),
            'status' => 'pending',
        ];

        redis::saveEx('forgetPassword_' . $emailAddr, json_encode($redisData), 600);

        return ['status' => 200, 'message' => 'sms_sent_success'];
    }

    public static function forgetPasswordCode($data): array
    {
        $payload = is_array($data) ? $data : (json_decode($data, true)['data'] ?? []);
        $emailAddr = $payload['user_email'] ?? '';
        $code      = $payload['code'] ?? '';

        if (! filter_var($emailAddr, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 400, 'message' => 'email_invalid'];
        }

        $stored = redis::get('forgetPassword_' . $emailAddr);
        if ($stored === false) {
            return ['status' => 404, 'message' => 'code_not_found'];
        }

        $obj = json_decode($stored, true);
        // Expira em 10 minutos (600 segundos)
        if ((time() - strtotime($obj['date'])) > 600) {
            redis::delete('forgetPassword_' . $emailAddr);
            return ['status' => 400, 'message' => 'code_expired'];
        }

        if ((string)$obj['code'] !== (string)$code) {
            return ['status' => 400, 'message' => 'code_invalid'];
        }

        $obj['status'] = 'used';
        $obj['date']   = date('Y-m-d H:i:s');
        redis::saveEx('forgetPassword_' . $emailAddr, json_encode($obj), 600);

        return ['status' => 200, 'message' => 'code_validated'];
    }

    public static function generationPassword($data): array
    {
        $payload = is_array($data) ? $data : (json_decode($data, true)['data'] ?? []);
        $emailAddr = $payload['user_email'] ?? '';
        $newPass   = $payload['user_password'] ?? '';

        if (! filter_var($emailAddr, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 400, 'message' => 'email_invalid'];
        }
        if (strlen($newPass) < 8) {
            return ['status' => 400, 'message' => 'password_invalid'];
        }

        $stored = redis::get('forgetPassword_' . $emailAddr);
        if ($stored === false) {
            return ['status' => 404, 'message' => 'code_not_found'];
        }

        $obj = json_decode($stored, true);
        // Expira em 10 minutos (600 segundos)
        if ((time() - strtotime($obj['date'])) > 600) {
            redis::delete('forgetPassword_' . $emailAddr);
            return ['status' => 400, 'message' => 'code_expired'];
        }

        if (($obj['status'] ?? '') !== 'used') {
            return ['status' => 400, 'message' => 'code_not_validated'];
        }

        // Iniciar transação
        Db::beginTransaction();
        try {
            // Atualiza senha
            $u = self::query()->where('use_email', $emailAddr)->first();
            if (! $u) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'User not found'];
            }

            $u->use_password = passwordHash::passwordHash($newPass);
            $u->save();

            // Commit da transação
            Db::commit();

            // Invalidar o token/código após sucesso
            redis::delete('forgetPassword_' . $emailAddr);

            return ['status' => 200, 'message' => 'password_updated'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => 'error_updating_password', 'details' => $e->getMessage()];
        }
    }
}
