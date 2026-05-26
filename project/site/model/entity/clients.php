<?php

namespace App\model\entity;

use Hyperf\DbConnection\Model\Model;
use Hyperf\DbConnection\Db;
use Functions\image\image;
use Functions\jwt\jwt;


class clients extends Model
{
    protected ?string $table = 'tb_clientes';
    protected string $primaryKey = 'cli_id';
    public bool $timestamps = false; // Legacy table does not use standard Laravel timestamps

    protected array $fillable = [
        'cli_nome',
        'cli_email',
        'cli_cpf',
        'cli_nascimento',
        'cli_telefone1',
        'cli_telefone2',
        'cli_senha',
        'cli_facebook',
        'cli_ativo',
        'cli_excluido',
        // 'cli_avatar' // Optional if table has it
    ];

    public static function create($data): array
    {
        try {
            Db::beginTransaction();

            $src = './public/uploads/midias/';

            // unicidade cpf
            if (!empty($data['cli_cpf'])) {
                $cpf = self::query()
                    ->where('cli_cpf', $data['cli_cpf'])
                    ->where('cli_excluido', 0)
                    ->first();

                if ($cpf) {
                    Db::rollBack();
                    return ['status' => 400, 'message' => 'CPF já cadastrado', 'data' => null];
                }
            }

            // unicidade e-mail
            if (!empty($data['cli_email'])) {
                $email = self::query()
                    ->where('cli_email', $data['cli_email'])
                    ->where('cli_excluido', 0)
                    ->first();

                if ($email) {
                    Db::rollBack();
                    return ['status' => 400, 'message' => 'E-mail já cadastrado', 'data' => null];
                }
            }

            // upload do avatar (opcional)
            // $imagePath = '';
            // if (!empty($data['cli_avatar'])) {
            //     // Assuming image::upload handles base64 or file upload
            //     $file = image::upload($data['cli_avatar'], $src);
            //     $imagePath = '/public/uploads/midias/' . $file;
            // }

            $hashedPassword = md5(trim($data['cli_senha'] ?? ''));

            $create = new self();
            // $create->cli_avatar        = $imagePath;
            $create->cli_email         = trim($data['cli_email'] ?? '');
            $create->cli_nome          = trim($data['cli_nome'] ?? '');
            $create->cli_telefone1     = $data['cli_telefone1'] ?? '';
            $create->cli_telefone2     = $data['cli_telefone2'] ?? '';
            $create->cli_cpf           = $data['cli_cpf'] ?? '';
            $create->cli_nascimento    = $data['cli_nascimento'] ?? null;
            $create->cli_senha         = $hashedPassword;
            $create->cli_ativo         = $data['cli_ativo'] ?? 1;
            $create->cli_excluido      = 0;
            // Facebook ID logic if needed
            if (!empty($data['cli_facebook'])) {
                $create->cli_facebook = $data['cli_facebook'];
            }

            $create->save();

            Db::commit();

            return ['status' => 201, 'message' => 'Cliente criado com sucesso', 'data' => ['cli_id' => $create->cli_id]];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function edit($data, $id): array
    {
        try {
            Db::beginTransaction();

            $src = './public/uploads/midias/';

            $client = self::query()
                ->where('cli_id', $id)
                ->where('cli_excluido', 0)
                ->first();

            if (!$client) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Cliente não encontrado'];
            }

            // trocar avatar (remove o antigo se existir)
            // if (!empty($data['cli_avatar'])) {
            //     if (!empty($client->cli_avatar)) {
            //         $oldName = str_replace('/public/uploads/midias/', '', $client->cli_avatar);
            //         $oldPath = $src . $oldName;
            //         if (is_file($oldPath)) {
            //             @unlink($oldPath);
            //         }
            //     }
            //     $newFile = image::upload($data['cli_avatar'], $src);
            //     $client->cli_avatar = '/public/uploads/midias/' . $newFile;
            // }

            // trocar senha
            if (!empty($data['cli_senha'])) {
                $client->cli_senha = md5($data['cli_senha']);
            }

            // atualizar campos básicos
            $client->cli_email         = $data['cli_email']         ?? $client->cli_email;
            $client->cli_telefone1     = $data['cli_telefone1']     ?? $client->cli_telefone1;
            $client->cli_telefone2     = $data['cli_telefone2']     ?? $client->cli_telefone2;
            $client->cli_nome          = $data['cli_nome']          ?? $client->cli_nome;
            $client->cli_nascimento    = $data['cli_nascimento']    ?? $client->cli_nascimento;

            $client->save();

            Db::commit();

            return ['status' => 200, 'message' => 'Cliente atualizado com sucesso', 'data' => $client];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function listAll($page = 1): array
    {
        try {
            $p = self::query()
                ->where('cli_excluido', 0)
                ->orderByDesc('cli_id')
                ->paginate(10, ['*'], 'page', (int)$page)
                ->toArray();

            if (empty($p['data'])) {
                return ['status' => 404, 'message' => 'Nenhum cliente encontrado'];
            }

            return [
                'pagination' => [
                    'current_page'  => $p['current_page'],
                    'first_page_url' => 1,
                    'from'          => $p['from'],
                    'last_page'     => $p['last_page'],
                    'last_page_url' => $p['last_page'],
                    'next_page_url' => $p['next_page_url'] ? (int)explode('=', $p['next_page_url'])[1] : null,
                    'per_page'      => $p['per_page'],
                    'prev_page_url' => $p['prev_page_url'] ? (int)explode('=', $p['prev_page_url'])[1] : null,
                    'to'            => $p['to'],
                    'total'         => $p['total'],
                ],
                'status' => 200,
                'data'   => $p['data'],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function getById($id): array
    {
        try {
            $client = self::query()
                ->where('cli_id', $id)
                ->where('cli_excluido', 0)
                ->first();

            if (!$client) {
                return ['status' => 404, 'message' => 'Cliente não encontrado'];
            }

            return ['status' => 200, 'data' => $client];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function deleted($id): array
    {
        try {
            Db::beginTransaction();

            $client = self::query()
                ->where('cli_id', $id)
                ->where('cli_excluido', 0)
                ->first();

            if (!$client) {
                Db::rollBack();
                return ['status' => 404, 'message' => 'Cliente não encontrado'];
            }

            $client->cli_excluido = 1;
            $client->cli_ativo = 0;
            $client->save();

            Db::commit();

            return ['status' => 200, 'message' => 'Cliente excluído com sucesso'];
        } catch (\Throwable $e) {
            Db::rollBack();
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function login($data)
    {
        try {
            $email = trim($data['cli_email'] ?? '');
            $senha = trim($data['cli_senha'] ?? '');

            $client = self::query()
                ->where('cli_email', $email)
                ->where('cli_excluido', 0)
                ->first();

            // Refacil says "E-mail ou senha não conferem"
            if (!$client) {
                return ['status' => 404, 'message' => 'E-mail ou senha não conferem.'];
            }

            // Verify MD5 hash
            if (md5($senha) !== $client->cli_senha) {
                return ['status' => 404, 'message' => 'E-mail ou senha não conferem.'];
            }

            if ((int)$client->cli_ativo === 0) {
                // Inactive account, possibly needs SMS verification
                return [
                    'status' => 403,
                    'message' => 'Conta inativa. Verifique se confirmou seu cadastro via SMS.',
                    'error_code' => 'INACTIVE_ACCOUNT',
                    'data' => ['cli_id' => $client->cli_id]
                ];
            }

            $token = jwt::generator([
                'cli_id'        => $client->cli_id,
                'cli_nome'      => $client->cli_nome,
                'cli_email'     => $client->cli_email,
                'cli_avatar'    => $client->cli_avatar ?? '',
            ]);

            return [
                'status' => 200,
                'message' => 'Login realizado com sucesso',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'cli_id' => $client->cli_id,
                        'cli_nome' => $client->cli_nome,
                        'cli_email' => $client->cli_email,
                        'cli_avatar' => $client->cli_avatar
                    ]
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function recoverPassword($email)
    {
        try {
            $client = self::query()
                ->where('cli_email', $email)
                ->where('cli_excluido', 0)
                ->first();

            if (!$client) {
                return ['status' => 404, 'message' => 'E-mail não encontrado.'];
            }

            $phone = preg_replace('/[^0-9]/', '', $client->cli_telefone1);
            if (strlen($phone) >= 10 && strlen($phone) <= 11) {
                $phone = '55' . $phone;
            }

            // Gera OTP de 6 dígitos e salva no Redis com 10 min de expiração
            $otp = rand(100000, 999999);
            \Functions\db\redis::saveEx('sms_recover_' . $client->cli_id, $otp, 600);

            // Envia SMS
            \Functions\api\ZenviaClient::enviarSMS($phone, "Seu código de recuperação Refacil: $otp");

            // Mascara o telefone para exibir ao usuário
            $phoneMasked = '****' . substr($client->cli_telefone1, -4);

            return [
                'status' => 200,
                'message' => 'Código SMS enviado para ' . $phoneMasked,
                'data' => [
                    'cli_id' => $client->cli_id,
                    'phone_masked' => $phoneMasked,
                ]
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function confirmRecoverPassword($cliId, $code, $newPassword)
    {
        try {
            $savedCode = \Functions\db\redis::get('sms_recover_' . $cliId);

            if ($savedCode === null || $savedCode === false) {
                return ['status' => 410, 'message' => 'Código expirado. Solicite um novo.', 'error_code' => 'CODE_EXPIRED'];
            }

            if ($savedCode != $code) {
                return ['status' => 400, 'message' => 'Código inválido.', 'error_code' => 'CODE_INVALID'];
            }

            $client = self::find($cliId);
            if (!$client) {
                return ['status' => 404, 'message' => 'Cliente não encontrado.'];
            }

            $client->cli_senha = md5(trim($newPassword));
            $client->save();

            \Functions\db\redis::delete('sms_recover_' . $cliId);

            return ['status' => 200, 'message' => 'Senha alterada com sucesso!'];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public static function resendSMS($cli_id): array
    {
        try {
            $client = self::query()
                ->where('cli_id', $cli_id)
                ->where('cli_excluido', 0)
                ->first();

            if (!$client) {
                return ['status' => 404, 'message' => 'Cliente não encontrado'];
            }

            if ((int)$client->cli_ativo === 1) {
                return ['status' => 200, 'message' => 'Cliente já está ativo.'];
            }

            $phone = preg_replace('/[^0-9]/', '', $client->cli_telefone1);
            if (strlen($phone) >= 10 && strlen($phone) <= 11) {
                $phone = '55' . $phone;
            }

            $otp = rand(100000, 999999);
            \Functions\db\redis::saveEx('sms_verif_' . $client->cli_id, $otp, 600);
            \Functions\api\ZenviaClient::enviarSMS($phone, "Seu código de verificação Refacil: $otp");

            return ['status' => 200, 'message' => 'Código SMS reenviado com sucesso.'];
        } catch (\Throwable $e) {
            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }
}
