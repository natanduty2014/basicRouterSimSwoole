# Documentação das Rotas da API

A seguir estão as rotas da API documentadas para fácil referência.

## Rota: `/v1/api/login/`

### Login

- **Método:** `POST`
- **Descrição:** Realiza a autenticação do usuário.
- **Dados a serem Enviados:**
  - `username`: Nome de usuário
  - `password`: Senha

### Logout

- **Método:** `GET`
- **Descrição:** Realiza o logout do usuário autenticado.
- **Token:** Deve incluir o token de autenticação no cabeçalho da solicitação.

## Rota: `/v1/api/user/`

### Listar Todos os Usuários

- **Método:** `GET`
- **Descrição:** Obtém a lista de todos os usuários no sistema.
- **Token: Bearer${token}** Deve incluir o token de autenticação no cabeçalho da solicitação.

### Editar Usuário por ID

- **Método:** `PUT`
- **Descrição:** Edita as informações de um usuário existente.
- **Dados da rotas:**
  ```text
    /v1/api/user/:id
    ```
- **Dados a serem Enviados (JSON):**
  ```json
  {
    "user_name": "Nome do Usuário",
    "user_email": "teste@teste.com",
    "user_role": "ID do Grupo de Usuários",
    "user_slug": "Slug do Usuário",
    "user_avatar": "URL da Imagem do Usuário",
    "user_password": "Senha do Usuário"

  }
  ```
- **Token:** Bearer${token} Deve incluir o token de autenticação no cabeçalho da solicitação.
