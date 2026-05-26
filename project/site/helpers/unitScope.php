<?php

namespace App\helpers;

use Functions\jwt\jwtCms;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Helpers para verificação de escopo de unidade no backend.
 *
 * Convenção: usuário com `user_is_admin = true` no JWT (Administrador do
 * Contratante) acessa todas as unidades do contratante e ignora a pivot.
 * Demais usuários só podem acessar unidades listadas em `user_unidades`.
 */
class unitScope
{
    /**
     * Lança ForbiddenException (resposta 403) se o usuário do token não pode
     * acessar a unidade alvo. Admin do contratante passa direto.
     */
    public static function assertAccess(Request $request, int $uniId): void
    {
        if ($uniId <= 0) {
            throw new \RuntimeException('uni_id inválido para verificação de escopo', 400);
        }
        $auth = $request->getHeader('Authorization')[0] ?? null;
        if (!$auth) {
            throw new \RuntimeException('Sem token de autenticação', 401);
        }
        $token = jwtCms::decodetoken($auth);
        if (!is_object($token)) {
            throw new \RuntimeException('Token inválido', 401);
        }
        if (!empty($token->user_is_admin)) {
            return; // admin do contratante = acesso total
        }
        $unidades = isset($token->user_unidades) ? (array)$token->user_unidades : [];
        $unidadesInt = array_map('intval', $unidades);
        if (!in_array($uniId, $unidadesInt, true)) {
            throw new \RuntimeException('Sem acesso a esta unidade', 403);
        }
    }

    /**
     * Retorna as unidades que o usuário do token pode acessar.
     * Para admin: retorna `null` (= sem restrição; quem chama deve filtrar
     * somente por `con_id` e ignorar a lista de unidades).
     */
    public static function userUnidades(Request $request): ?array
    {
        $auth = $request->getHeader('Authorization')[0] ?? null;
        if (!$auth) return [];
        $token = jwtCms::decodetoken($auth);
        if (!is_object($token)) return [];
        if (!empty($token->user_is_admin)) return null;
        $unidades = isset($token->user_unidades) ? (array)$token->user_unidades : [];
        return array_map('intval', $unidades);
    }

    /**
     * Atalho: o usuário é admin do contratante?
     */
    public static function isAdmin(Request $request): bool
    {
        $auth = $request->getHeader('Authorization')[0] ?? null;
        if (!$auth) return false;
        $token = jwtCms::decodetoken($auth);
        if (!is_object($token)) return false;
        return !empty($token->user_is_admin);
    }
}
