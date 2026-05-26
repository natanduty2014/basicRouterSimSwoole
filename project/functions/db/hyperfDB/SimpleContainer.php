<?php
namespace Functions\db\hyperfDB;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Hyperf\Contract\ContainerInterface as HyperfContainerInterface;

class SimpleContainer implements PsrContainerInterface, HyperfContainerInterface
{
    private array $instances = [];
    private array $definitions = [];

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        // Se houver uma definição registrada, resolva-a
        if (isset($this->definitions[$id])) {
            $def = $this->definitions[$id];
            if (is_callable($def)) {
                $this->instances[$id] = $def($this);
            } elseif (is_string($def) && class_exists($def)) {
                $this->instances[$id] = new $def();
            } else {
                $this->instances[$id] = $def;
            }
            return $this->instances[$id];
        }
        // PSR-11 recomenda lançar NotFoundException, mas manteremos null
        // para compatibilidade interna onde `has()` é checado previamente.
        return null;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->definitions[$id]);
    }

    public function set(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    // Hyperf\Contract\ContainerInterface adicionais
    public function make(string $name, array $parameters = []): mixed
    {
        // Quando parâmetros são fornecidos, não reutiliza instâncias singleton
        // para permitir criação de objetos com estado (ex.: Paginators)
        if (empty($parameters) && isset($this->instances[$name])) {
            return $this->instances[$name];
        }
        if (isset($this->definitions[$name])) {
            $def = $this->definitions[$name];
            if (is_callable($def)) {
                return $def($this, $parameters);
            }
            if (is_string($def) && class_exists($def)) {
                $ref = new \ReflectionClass($def);
                $obj = $ref->newInstanceArgs($parameters);
                // Só cacheia se não houver parâmetros (objeto sem estado)
                if (empty($parameters)) {
                    $this->instances[$name] = $obj;
                }
                return $obj;
            }
            return $def;
        }
        if (class_exists($name)) {
            $ref = new \ReflectionClass($name);
            $obj = $ref->newInstanceArgs($parameters);
            if (empty($parameters)) {
                $this->instances[$name] = $obj;
            }
            return $obj;
        }
        return null;
    }

    public function unbind(string $name): void
    {
        unset($this->instances[$name], $this->definitions[$name]);
    }

    public function define(string $name, mixed $concrete): void
    {
        $this->definitions[$name] = $concrete;
    }
}
