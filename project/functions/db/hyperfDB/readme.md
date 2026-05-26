# 🚀 Guia de Implementação - HyperfDB com Swoole

## 📋 Sumário
1. [Introdução](#introdução)
2. [Configuração do Servidor](#servidor)
3. [Criando Models](#models)
4. [Boas Práticas](#boas-praticas)

## 📘 Introdução {#introdução}

Este guia fornece instruções detalhadas sobre como implementar e utilizar o Swoole em conjunto com HyperfDB para criar aplicações PHP de alta performance. O Swoole permite criar servidores HTTP assíncronos, enquanto o HyperfDB oferece uma camada de abstração poderosa para operações com banco de dados.

## ⚙️ Configuração do Servidor {#servidor}

### Requisitos Iniciais
- PHP >= 7.4
- Extensão Swoole instalada
- Composer
- HyperfDB configurado

### Dependências do HyperfDB
```json
{
    "require": {
        "hyperf/db": "^3.1",
        "hyperf/pool": "^3.1",
        "hyperf/contract": "^3.1",
        "hyperf/di": "^3.1",
        "hyperf/db-connection": "^3.1"
    }
}
```

### Estrutura do Servidor

```php
use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap/db.php';

$server = new Server("127.0.0.1", 9501);

$server->on("request", function (Request $request, Response $response) {
    Swoole\Coroutine\run(function () use ($response) {
        initHyperfDb(); // -> colocar isso para ficar global e assim o HyperDB reconhecer o bootstrap
        
        $result = \App\Model\ProductsCategories::listAll();
        
        $response->header("Content-Type", "application/json");
        $response->end(json_encode($result));
    });
});

$server->start();
```

### 🔧 Configurações do Servidor
1. **Autoload**: Configuração do composer para carregamento automático
2. **Database**: Arquivo de configuração do banco em `bootstrap/db.php`
3. **Coroutines**: Implementação de operações assíncronas
4. **Headers**: Configuração correta para respostas HTTP

### Inicialização do Servidor
```bash
php server.php
```

## 💡 Criando Models {#models}

### Estrutura Básica de um Model

```php
namespace App\Model;

use Hyperf\DbConnection\Db;

class ProductsCategories
{
    protected static string $table = 'tb_products_categories';

    public static function listAll(): array
    {
        try {
            return Db::table(self::$table)
                ->where('prc_deleted', 0)
                ->orderBy('prc_title', 'asc')
                ->limit(10)
                ->get();
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    // Exemplo de paginação
    public static function listPaginated(int $page = 1, int $perPage = 10): array
    {
        return Db::table(self::$table)
            ->where('deleted', 0)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();
    }
}
```

### 🔑 Características dos Models
- **Namespace Organizado**: Utilize `App\Model` para manter o código organizado
- **Tratamento de Exceções**: Implementação robusta de try/catch
- **Métodos Estáticos**: Facilita o acesso às funcionalidades
- **Convenções de Nomenclatura**: Seguindo os padrões PSR

## ✨ Boas Práticas {#boas-praticas}

1. **Configuração do Servidor**
   - Use variáveis de ambiente para configurações sensíveis
   - Implemente logs para monitoramento
   - Configure timeouts adequadamente

2. **Desenvolvimento de Models**
   - Siga as PSRs para padronização
   - Implemente tratamento de exceções
   - Use tipos de retorno explícitos
   - Documente métodos complexos

3. **Performance**
   - Utilize corrotinas para operações assíncronas
   - Implemente cache quando necessário
   - Otimize consultas ao banco de dados

## 📄 Paginação (Hyperf Paginator)

- Para que `Model::query()->paginate()` e `Builder::paginate()` funcionem, o container precisa prover:
    - `Hyperf\Contract\LengthAwarePaginatorInterface => Hyperf\Paginator\LengthAwarePaginator`
    - `Hyperf\Contract\PaginatorInterface => Hyperf\Paginator\Paginator`
- Isso já é configurado em `functions/db/hyperfDB/initHyperfDb.php` usando `define(interface, classe)`.
- Importante: o container não deve reutilizar instâncias quando `make()` recebe parâmetros (itens, total, etc.). O `SimpleContainer` já trata isso e não cacheia objetos criados com parâmetros.
- Em ambiente Swoole/`indexpro.php`, o `initHyperfDb()` é chamado no `WorkerStart`, garantindo que o `ApplicationContext` esteja pronto para paginação.

## 🛠️ Ferramentas Recomendadas

- VS Code ou PHPStorm com suporte a PHP
- Composer para gerenciamento de dependências
- Postman ou Insomnia para testes de API
- Git para controle de versão

## 📚 Recursos Adicionais

- [Documentação Oficial do Hyperf](https://hyperf.wiki)
- [Documentação do Swoole](https://www.swoole.co.uk)
- [PSR Standards](https://www.php-fig.org/psr/)

---

📅 Última atualização: 22 de Abril de 2025

