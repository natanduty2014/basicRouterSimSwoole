<?php

require_once '../../vendor/autoload.php';

$swagger = array(
    "openapi" => "3.0.0",
    "info" => array(
        "title" => "Best Time 2",
        "description" => "API",
        "version" => "1.0.1"
    ),
    "servers" => array(
        array(
            "url" => "http://localhost:3380",
            "description" => "Produção"
        ),
        array(
            "url" => "http://localhost:9502",
            "description" => "Local"
        )
    ),
    "tags" => array(),
    "paths" => array(),
    "components" => array(
        "schemas" => new stdClass(),
        "securitySchemes" => array(
            "bearerAuth" => array(
                "type" => "http",
                "scheme" => "bearer",
                "bearerFormat" => "JWT"
            )
        ),
        "parameters" => new stdClass(),
        "responses" => array(
            "200" => array(
                "description" => "Success"
            ),
            "400" => array(
                "description" => "Bad request"
            ),
            "401" => array(
                "description" => "Unauthorized"
            ),
            "500" => array(
                "description" => "Internal Server Error"
            )
        ),
        "requestBodies" => new stdClass(),
    )
);

$routes = array();
$fileContent = file_get_contents('../../site/helpers/routerSlim.php');

// Ignorar linhas comentadas
$fileContent = preg_replace('/\/\/.*|\/\*[\s\S]*?\*\//', '', $fileContent);

// Ajustar as expressões regulares para capturar rotas aninhadas
preg_match_all('/\$app->group\(\'(.*?)\',\s*function\s*\(RouteCollectorProxy\s*\$group\)/', $fileContent, $groupMatches, PREG_SET_ORDER);

preg_match_all('/\$group->(get|post|put|delete)\(\'(.*?)\',\s*(.*?)::class\s*\.\s*\'(.*?)\'\)(.*?);/s', $fileContent, $groupRouterMatches, PREG_SET_ORDER);

foreach ($groupMatches as $match) {
    $tags = array(
        "name" => $match[1],
        "description" => "Grupo de rotas para " . $match[1],
    );
    array_push($swagger['tags'], $tags);
    $routes[$match[1]] = [];
}

function getClassProperties($className) {
    if (!class_exists($className)) {
        return [];
    }

    $reflectionClass = new ReflectionClass($className);
    $properties = $reflectionClass->getProperties();
    $schema = array();

    foreach ($properties as $property) {
        $type = $property->getType();
        $schemaType = 'string'; // Valor padrão
        $format = null;
        $example = null;

        if ($type !== null) {
            $typeName = $type->getName();
            switch ($typeName) {
                case 'int':
                case 'integer':
                    $schemaType = 'integer';
                    $example = 0;
                    break;
                case 'float':
                case 'double':
                    $schemaType = 'number';
                    $example = 0.0;
                    break;
                case 'bool':
                case 'boolean':
                    $schemaType = 'boolean';
                    $example = true;
                    break;
                case 'array':
                    $schemaType = 'array';
                    break;
                case 'date':
                    $schemaType = 'string';
                    $format = 'date';
                    $example = "1997-08-00";
                    break;
                case 'binary':
                case 'base64':
                    $schemaType = 'string';
                    $format = 'byte';
                    $example = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUA";
                    break;
            }
        }

        $propertySchema = array();
        $propertySchema["type"] = $schemaType;

        if ($format) {
            $propertySchema["format"] = $format;
        }
        if ($example) {
            $propertySchema["example"] = $example;
        }

        $schema[$property->getName()] = $propertySchema;
    }

    return $schema;
}

function getDataPropertySchema($className, $propertyName) {
    if (!class_exists($className)) {
        return [];
    }

    $reflectionClass = new ReflectionClass($className);

    if (!$reflectionClass->hasProperty($propertyName)) {
        echo "\033[33mAlerta: Propriedade '$propertyName' não encontrada na classe $className.\033[0m\n";
        return [];
    }

    $property = $reflectionClass->getProperty($propertyName);
    $property->setAccessible(true);
    $defaultValue = $property->getValue(new $className);
    $limitedFields = $reflectionClass->hasProperty('limited') ? $reflectionClass->getProperty('limited')->getValue(new $className) : [];

    $itemsSchema = array();
    if (is_array($defaultValue)) {
        foreach ($defaultValue as $key => $value) {
            $itemSchema = array("type" => getTypeSchema($value));

            if (is_string($value) && strpos($value, 'base64') !== false) {
                $itemSchema['format'] = 'byte';
                $itemSchema['example'] = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUA";
            }
            if (is_string($value) && strpos($value, 'date') !== false) {
                $itemSchema['format'] = 'date';
                $itemSchema['example'] = "1997-08-00";
            }

            if (array_key_exists($key . '_max', $limitedFields)) {
                $itemSchema['maxLength'] = $limitedFields[$key . '_max'];
            }
            if (array_key_exists($key . '_min', $limitedFields)) {
                $itemSchema['minLength'] = $limitedFields[$key . '_min'];
            }

            if (is_array($value)) {
                $subItemsSchema = array();
                foreach ($value as $subKey => $subValue) {
                    $subItemsSchema[$subKey] = array("type" => getTypeSchema($subValue));
                    if (is_string($subValue) && strpos($subValue, 'base64') !== false) {
                        $subItemsSchema[$subKey]['format'] = 'byte';
                        $subItemsSchema[$subKey]['example'] = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUA";
                    }
                    if (is_string($subValue) && strpos($subValue, 'date') !== false) {
                        $subItemsSchema[$subKey]['format'] = 'date';
                        $subItemsSchema[$subKey]['example'] = "1997-08-00";
                    }
                    if (array_key_exists($subKey . '_max', $limitedFields)) {
                        $subItemsSchema[$subKey]['maxLength'] = $limitedFields[$subKey . '_max'];
                    }
                    if (array_key_exists($subKey . '_min', $limitedFields)) {
                        $subItemsSchema[$subKey]['minLength'] = $limitedFields[$subKey . '_min'];
                    }
                }
                $itemSchema["type"] = "object";
                $itemSchema["properties"] = $subItemsSchema;
            }

            $itemsSchema[$key] = $itemSchema;
        }
    }

    $result = array("type" => "object", "properties" => $itemsSchema);
    return $result;
}

function getTypeSchema($value) {
    switch ($value) {
        case 'int':
        case 'integer':
            return 'integer';
        case 'float':
        case 'double':
            return 'number';
        case 'bool':
        case 'boolean':
            return 'boolean';
        case 'date':
            return 'string';
        case 'binary':
        case 'base64':
            return 'string';
        default:
            return 'string';
    }
}

$operationIds = [];

//$groupMatches
//$groupRouterMatches ()
foreach ($groupRouterMatches as $match) {
    $method = $match[1];
    $path = $match[2];
    $controllerClass = 'App\\controllers\\entity\\' . $match[3]; // Usar namespace completo
    $controllerMethod = $match[4];
    $requiresAuth = strpos($match[5], 'authorization::class') !== false; // Verifica se a rota requer autorização
    $groupPath = "/v1/api/" . $match[3] . '/';
    $groupPath2 = "/v1/api/" . $match[3] . '/' . $path;

 

    
  
    

    foreach ($groupMatches as $match2) {
        // Divide a string do caminho base em partes
        $base_path_array = explode("/", trim($match2[1], "/"))[2];
    
        // Verifica se "newsCategories" contém "categories" de forma insensível a maisculas e minsculas
        if (stripos($match[3], $base_path_array) !== false && strtolower($base_path_array) === $base_path_array) {
           if($match[3] != $base_path_array){
            $groupPath = "/v1/api/" . $base_path_array . '/';
            $groupPath2 = "/v1/api/" . $base_path_array . '/' . $path;
           }
        }
    }
    
    


   
  

    if (!isset($swagger['paths'][$groupPath2])) {
        $swagger['paths'][$groupPath2] = [];
    }

    $operationId = str_replace(':', '',$controllerMethod);
    $operationCount = 1;
    while (in_array($operationId, $operationIds)) {
        $operationId = str_replace(':', '',$controllerMethod) . " ->" . $operationCount++;
    }
    $operationIds[] = $operationId;

    $swaggerPathItem = array(
        "tags" => array($groupPath),
        "operationId" => $operationId,
        "responses" => array(
            ($method === 'post' ? "201" : "200") => array(
                "description" => "Operação bem-sucedida"
            )
        )
    );

    // Definir parâmetros de caminho faltantes
    preg_match_all('/{(\w+)}/', $path, $pathParameters);
    foreach ($pathParameters[1] as $param) {
        $swaggerPathItem['parameters'][] = array(
            "name" => $param,
            "in" => "path",
            "required" => true,
            "schema" => array(
                "type" => "string"
            ),
            "description" => "Parâmetro de caminho " . $param
        );
    }

    if ($method === 'post' || $method === 'put') {
        $dataSchema = getDataPropertySchema($controllerClass, 'data');
        $paginationSchema = getDataPropertySchema($controllerClass, 'pagination');
        if (!empty($dataSchema) || !empty($paginationSchema)) {
            $schemaName = (new ReflectionClass($controllerClass))->getShortName();
            if (!isset($swagger['components']['schemas']->$schemaName)) {
                $swagger['components']['schemas']->$schemaName = new stdClass();
            }
            $swagger['components']['schemas']->$schemaName = array(
                "type" => "object",
                "properties" => array(
                    "data" => $dataSchema,
                    "pagination" => $paginationSchema,
                )
            );

            $swaggerPathItem['requestBody'] = array(
                "required" => true,
                "content" => array(
                    "application/json" => array(
                        "schema" => array(
                            "type" => "object",
                            "properties" => array(
                                "data" => $dataSchema
                            )
                        )
                    )
                )
            );
        }
    }

    if ($requiresAuth) {
        $swaggerPathItem['security'] = array(
            array(
                "bearerAuth" => array()
            )
        );
    }

    $swagger['paths'][$groupPath2][strtolower($method)] = $swaggerPathItem;
}

file_put_contents('../../site/swagger.json', json_encode($swagger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

print_r("\033[32mArquivo swagger.json gerado com sucesso!\033[0m \n");

?>
