
# Documentação docker

Configurar as portas do projeto em docker, o nome do da rede (network) e o modo de operação de rede

## Configurar o arquivo .env portas

| Nomes das variaves de ambiente               | exemplos                                                |
| -------------------- | ---------------------------------------------------------------- |
| WEB_PORT             | 9502    |
| WEB_HOTRELOAD_PORT   | 9503    |
| PHPMYADMIN_PORT      | 9504    |
| DATABASE_PORT        | 9505    |
| REDIS_PORT           | 9506    |

## Configurar o arquivo .env network

| Nomes das variaves de ambiente                | exemplos                                                |
| -------------------- | ---------------------------------------------------------------- |
| NETWORK_NAME              | exemplo |
| PROJECT_NAME              | exemplo |
| NETWORK_MODE_NAME         | bridge or host |

OBS: caso colocar em modo host só funcionará em um ambiente linux devido a
     compatibilidade da kernal do docker, e deve ser usado em produção (pequise mais para saber). modo bridge para desenvolvimento (funcionará em qualquer        
     ambiente mas perde performance)


 ## Configurar o arquivo .env cpu e memória (php/web)

| Nomes das variaves de ambiente                | exemplos                                                |
| -------------------- | ---------------------------------------------------------------- |
| WEB_CPU_QUANT              | 2 |
| WEB_MEM_QUANT              | 500MB |
| WEB_CPU_RESERV             | 0.5 |
| WEB_MEM_RESERV             | 50MB |

 ## Configurar o arquivo .env cpu e memória (mysql/database)

| Nomes das variaves de ambiente                | exemplos                                                |
| -------------------- | ---------------------------------------------------------------- |
| DATABASE_CPU_QUANT              | 2 |
| DATABASE_MEM_QUANT              | 500MB |
| DATABASE_CPU_RESERV             | 0.5 |
| DATABASE_MEM_RESERV             | 50MB |


 ## Configurar o arquivo .env cpu e memória (phpmyadmin/databaseManager)

| Nomes das variaves de ambiente                | exemplos                                                |
| -------------------- | ---------------------------------------------------------------- |
| DATABASEMANAGER_CPU_QUANT              | 2 |
| DATABASEMANAGER_MEM_QUANT              | 500MB |
| DATABASEMANAGER_CPU_RESERV             | 0.5 |
| DATABASEMANAGER_MEM_RESERV             | 50MB |


 ## Configurar o arquivo .env cpu e memória (redis/ServerCache)

| Nomes das variaves de ambiente                | exemplos                                                |
| -------------------- | ---------------------------------------------------------------- |
| SERVERCACHE_CPU_QUANT              | 2 |
| SERVERCACHE_MEM_QUANT              | 500MB |
| SERVERCACHE_CPU_RESERV             | 0.5 |
| SERVERCACHE_MEM_RESERV             | 50MB |

 ## Configurar o arquivo .env login e senha (database)

| Nomes das variaves de ambiente                | exemplos                                                |
| -------------------- | ---------------------------------------------------------------- |
| DATABASE_USER                      | db_user |
| DATABASE_PASSWORD_ROOT             | my_secret_password |
| DATABASE_PASSWORD                  | db_user_pass |
| DATABASE_DATABASE                  | app_db |
| DATABASE_TIMEZONE                  | America/Sao_Paulo |
| SERVERCACHE_LOGIN                  | null |
| SERVERCACHE_PASSWORD               | user |











# basicRouterSimSwoole
