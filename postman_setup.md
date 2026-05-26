# Postman Setup - Refacil Serving API

> ⚠️ **ATENÇÃO**: Este arquivo contém credenciais sensíveis. NÃO compartilhe ou commite no git.

## 1. Configuração do Ambiente

### Variáveis de Ambiente (Environment)

Crie um novo Environment no Postman com as seguintes variáveis:

| Variable | Initial Value | Current Value |
|----------|---------------|---------------|
| `base_url` | `https://m95ji3ctbi.execute-api.us-east-1.amazonaws.com` | (mesmo) |
| `cognito_client_id` | `7f7c7b17ki09f8f256u9pu1dfv` | (mesmo) |
| `cognito_user_pool_id` | `us-east-1_ENj1Vx8n0` | (mesmo) |
| `username` | `emanuelbetcel@gmail.com` | (mesmo) |
| `password` | `RfSecure2026!Prod` | (mesmo) |
| `access_token` | (deixe vazio) | (preenchido automaticamente) |

---

## 2. Obter Token de Acesso

### Opção A: Configurar OAuth 2.0 no Postman

1. Na aba **Authorization** do request, selecione **OAuth 2.0**
2. Configure:
   - **Grant Type**: Password Credentials
   - **Access Token URL**: `https://cognito-idp.us-east-1.amazonaws.com/`
   - **Client ID**: `7f7c7b17ki09f8f256u9pu1dfv`
   - **Username**: `emanuelbetcel@gmail.com`
   - **Password**: `RfSecure2026!Prod`

### Opção B: Request Manual para Obter Token

Crie um request POST para obter o token:

**URL**: `https://cognito-idp.us-east-1.amazonaws.com/`

**Headers**:
```
Content-Type: application/x-amz-json-1.1
X-Amz-Target: AWSCognitoIdentityProviderService.InitiateAuth
```

**Body** (raw JSON):
```json
{
    "AuthFlow": "USER_PASSWORD_AUTH",
    "ClientId": "7f7c7b17ki09f8f256u9pu1dfv",
    "AuthParameters": {
        "USERNAME": "emanuelbetcel@gmail.com",
        "PASSWORD": "RfSecure2026!Prod"
    }
}
```

**Response**: Copie o valor de `AuthenticationResult.IdToken`

### Opção C: Renovar Token (Refresh Token)

Se o token expirar, use o `RefreshToken` recebido no login para gerar um novo sem enviar a senha novamente:

**URL**: `https://cognito-idp.us-east-1.amazonaws.com/`
**Headers**: Mesmos acima (`Content-Type`, `X-Amz-Target`)

**Body**:
```json
{
   "AuthFlow": "REFRESH_TOKEN_AUTH",
   "ClientId": "7f7c7b17ki09f8f256u9pu1dfv",
   "AuthParameters": {
       "REFRESH_TOKEN": "eyJjdHkiOiJK..." 
   }
}
```

---

## 3. Endpoints Disponíveis

### POST /dashboard

Retorna métricas e KPIs do dashboard. Permite filtrar por cliente e período personalizado.

**URL**: `{{base_url}}/dashboard`

**Method**: `POST`

**Headers**:
```
Authorization: Bearer {{access_token}}
Content-Type: application/json
```

**Body** (raw JSON):
```json
{
    "data": {
        "client_id": 1,              // Opcional: ID do cliente para filtrar
        "data_inicio": "2026-01-01", // Opcional: Início do período customizado
        "data_fim": "2026-01-31"     // Opcional: Fim do período customizado
    }
}
```

**Exemplo de Response**:
```json
{
  "hoje": {
    "periodo": { "inicio": "2026-01-29", "fim": "2026-01-29" },
    "kpis": {
      "receitaTotal": 0.0,
      "numeroPedidos": 0,
      "ticketMedio": 0.0,
      "novosClientes": 0
    },
    "vendasEvolucao": []
  },
  "ultimos7Dias": { ... },
  "esteMes": { ... },
  "customizado": {       // Aparece apenas se data_inicio e data_fim forem enviados
    "periodo": { "inicio": "2026-01-01", "fim": "2026-01-31" },
    "kpis": { ... },
    "vendasEvolucao": [ ... ]
  }
}
```

---

### 4. Script Inteligente para Auto-Refresh (Pre-request)

Cole este script na aba **Pre-request Script** da Collection "Refacil API".
Ele gerencia tudo sozinho: verifica se o token expirou e usa o Refresh Token (ou Login) automaticamente.

```javascript
// Configuração
const COGNITO_URL = "https://cognito-idp.us-east-1.amazonaws.com/";
const CLIENT_ID = pm.environment.get("cognito_client_id");

function parseJwt(token) {
    if (!token) return null;
    try {
        const base64Url = token.split('.')[1];
        const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
        const jsonPayload = decodeURIComponent(atob(base64).split('').map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)).join(''));
        return JSON.parse(jsonPayload);
    } catch (e) {
        return null;
    }
}

function loginUserPassword() {
    const username = pm.environment.get("username");
    const password = pm.environment.get("password");
    console.log("Token expirado ou ausente. Tentando LOGIN completo...");

    const authRequest = {
        url: COGNITO_URL,
        method: "POST",
        header: {
            "Content-Type": "application/x-amz-json-1.1",
            "X-Amz-Target": "AWSCognitoIdentityProviderService.InitiateAuth"
        },
        body: {
            mode: "raw",
            raw: JSON.stringify({
                AuthFlow: "USER_PASSWORD_AUTH",
                ClientId: CLIENT_ID,
                AuthParameters: { USERNAME: username, PASSWORD: password }
            })
        }
    };

    pm.sendRequest(authRequest, (err, res) => {
        if (err || res.code !== 200) {
            console.error("Erro no login:", res.text());
            return;
        }
        const data = res.json();
        pm.environment.set("access_token", data.AuthenticationResult.IdToken);
        if (data.AuthenticationResult.RefreshToken) {
            pm.environment.set("refresh_token", data.AuthenticationResult.RefreshToken);
        }
        console.log("Login realizado com sucesso!");
    });
}

function refreshToken() {
    const refreshToken = pm.environment.get("refresh_token");
    if (!refreshToken) {
        loginUserPassword(); // Se não tem refresh, faz login
        return;
    }

    console.log("Token expirado. Tentando REFRESH TOKEN...");
    const refreshRequest = {
        url: COGNITO_URL,
        method: "POST",
        header: {
            "Content-Type": "application/x-amz-json-1.1",
            "X-Amz-Target": "AWSCognitoIdentityProviderService.InitiateAuth"
        },
        body: {
            mode: "raw",
            raw: JSON.stringify({
                AuthFlow: "REFRESH_TOKEN_AUTH",
                ClientId: CLIENT_ID,
                AuthParameters: { REFRESH_TOKEN: refreshToken }
            })
        }
    };

    pm.sendRequest(refreshRequest, (err, res) => {
        if (err || res.code !== 200) {
            console.warn("Falha no refresh token. Tentando login completo...");
            loginUserPassword(); // Se falhar o refresh, faz login
            return;
        }
        const data = res.json();
        pm.environment.set("access_token", data.AuthenticationResult.IdToken);
        console.log("Token renovado com sucesso via Refresh Token!");
    });
}

// Lógica Principal
const accessToken = pm.environment.get("access_token");
const jwt = parseJwt(accessToken);
const now = Math.floor(Date.now() / 1000);

// Se não tem token ou se expira em menos de 2 minutos
if (!jwt || !jwt.exp || (jwt.exp - now) < 120) {
    refreshToken();
} else {
    console.log("Token válido por mais " + (jwt.exp - now) + " segundos.");
}
```

---

## 5. cURL de Referência

```bash
# Obter token
TOKEN=$(aws cognito-idp initiate-auth \
  --client-id 7f7c7b17ki09f8f256u9pu1dfv \
  --auth-flow USER_PASSWORD_AUTH \
  --auth-parameters 'USERNAME=emanuelbetcel@gmail.com,PASSWORD=RfSecure2026!Prod' \
  --region us-east-1 \
  --profile tf_refacil \
  --query 'AuthenticationResult.IdToken' \
  --output text)

# Chamar API
curl -X GET "https://m95ji3ctbi.execute-api.us-east-1.amazonaws.com/dashboard" \
  -H "Authorization: Bearer $TOKEN"
```


---

## 6. Filtrando por Data (Parâmetros)

É possível filtrar o dashboard por um período personalizado passando parâmetros na URL.

**Parâmetros (Query Params)**:

| Chave | Formato | Descrição |
|-------|---------|-----------|
| `data_inicio` | `YYYY-MM-DD` | Data inicial do período |
| `data_fim` | `YYYY-MM-DD` | Data final do período |

**No Postman**:
1. Selecione o request `GET /dashboard`
2. Vá na aba **Params**
3. Adicione as chaves acima com os valores desejados (ex: `2026-01-01`)

**Exemplo de URL**:
`{{base_url}}/dashboard?data_inicio=2026-01-01&data_fim=2026-01-31`

**Resposta**:
O JSON de resposta incluirá uma nova chave `customizado` contendo os dados do período solicitado.

---


## Credenciais Resumo

| Campo | Valor |
|-------|-------|
| **API URL** | `https://m95ji3ctbi.execute-api.us-east-1.amazonaws.com` |
| **User Pool ID** | `us-east-1_ENj1Vx8n0` |
| **Client ID** | `7f7c7b17ki09f8f256u9pu1dfv` |
| **Email** | `emanuelbetcel@gmail.com` |
| **Senha** | `RfSecure2026!Prod` |
| **Account ID** | `7` |

---

*Criado em: 22/01/2026*

## token
{
    "AuthenticationResult": {
        "AccessToken": "eyJraWQiOiJMOU9UbGN6eFBPRDc4ZHBHaU00bzcxWlJzcFFDQkMxNUcwT252SmoyNVowPSIsImFsZyI6IlJTMjU2In0.eyJzdWIiOiJiNDE4NDQ4OC0zMGIxLTcwNTAtNzNhOC1iMjM0ZmUxYjc3YTMiLCJpc3MiOiJodHRwczpcL1wvY29nbml0by1pZHAudXMtZWFzdC0xLmFtYXpvbmF3cy5jb21cL3VzLWVhc3QtMV9FTmoxVng4bjAiLCJjbGllbnRfaWQiOiI3ZjdjN2IxN2tpMDlmOGYyNTZ1OXB1MWRmdiIsIm9yaWdpbl9qdGkiOiI0OTBkNzMxZi0wODM0LTQ2OGItOTU5Yy0yYjU1NTVjYzUxNGMiLCJldmVudF9pZCI6ImNmNzhjOWU5LTViZjItNGY5Ny04Mjk2LTU2ZGZkZmEwMDIyZiIsInRva2VuX3VzZSI6ImFjY2VzcyIsInNjb3BlIjoiYXdzLmNvZ25pdG8uc2lnbmluLnVzZXIuYWRtaW4iLCJhdXRoX3RpbWUiOjE3Njk3MDQ3NzYsImV4cCI6MTc2OTcwODM3NiwiaWF0IjoxNzY5NzA0Nzc2LCJqdGkiOiJiY2U2MzU5MC01NTJkLTQzMTgtYTczYy1hMjY0MWJlYjQwNGQiLCJ1c2VybmFtZSI6ImVtYW51ZWxiZXRjZWxAZ21haWwuY29tIn0.MBG-G5VSZBm6WEqQEAnOUZmKqKh4kF16BblvPNnwsQeMAmiDKrJeuz98lXKNNp4Zebb8Kvy0T-pEBQt0IxjHmDpX2FwV6zJmijhi_OKKVQ-8_huQt4ABMZKfOQzlXrIoaO_rPOt2Gk3izCUb4d_lpAg-FscqTbytQYl9EyZ7ptponehUVzBnqyyvBwQXN-TF3qgOEVzrISk6Ul5XGU5br3J9AckJndk8Ex3jEgaCTu26qYKNpKz819RCCvgQA6Edg7Tn1MCHRcyj50xQuev_5C2QdDUaKGa97Ch1XbFTT-40Qsd4ErFJyIggMoS4gDSGYTme1QGP1aQIFC4f_sWaIA",
        "ExpiresIn": 3600,
        "IdToken": "eyJraWQiOiJXcXI2M05jUVdFZTIrM085cHNubHNkTFc4SDhpTnhyTGU4OVNOYzJIU3I4PSIsImFsZyI6IlJTMjU2In0.eyJzdWIiOiJiNDE4NDQ4OC0zMGIxLTcwNTAtNzNhOC1iMjM0ZmUxYjc3YTMiLCJpc3MiOiJodHRwczpcL1wvY29nbml0by1pZHAudXMtZWFzdC0xLmFtYXpvbmF3cy5jb21cL3VzLWVhc3QtMV9FTmoxVng4bjAiLCJjb2duaXRvOnVzZXJuYW1lIjoiZW1hbnVlbGJldGNlbEBnbWFpbC5jb20iLCJvcmlnaW5fanRpIjoiNDkwZDczMWYtMDgzNC00NjhiLTk1OWMtMmI1NTU1Y2M1MTRjIiwiYXVkIjoiN2Y3YzdiMTdraTA5ZjhmMjU2dTlwdTFkZnYiLCJldmVudF9pZCI6ImNmNzhjOWU5LTViZjItNGY5Ny04Mjk2LTU2ZGZkZmEwMDIyZiIsInRva2VuX3VzZSI6ImlkIiwiYXV0aF90aW1lIjoxNzY5NzA0Nzc2LCJjdXN0b206YWNjb3VudF9pZCI6IjciLCJleHAiOjE3Njk3MDgzNzYsImlhdCI6MTc2OTcwNDc3NiwianRpIjoiOTA5N2NjOGMtNWY1NS00NDUwLTg0ZjctYTMwZjZkMWYwODY5IiwiZW1haWwiOiJlbWFudWVsYmV0Y2VsQGdtYWlsLmNvbSJ9.HmUu9S3daqzE642MYHbxV6i-hCxbJTipecsd_Rm_Vz3t53Dd3Mfme4jMmLRxBrgHQhZwQniKEi1Y9ma_V4VmzYEZmZJgUAQ-zOl6k8OhCH3WNMal91QB3P5G07XVye196_DAgUpE1GolnJ3TUzvd0x3BW_2R5Bcvc6sosQ70yAFrMwVDlMrxT8NcICyFEPuX-rcM4HnSDjwEpVq6fg_H7ilJ2zictm9wF5pw8O_DtqAL0RI6u-kkUquC8aFC_enY9r7SmFLupW8s8KEZx7azbWOftdiv66YENAWo0ppft7Guo2LK8tDycc1G97ittkCOhzMEy4a9XB6JZyVAuNWgxw",
        "RefreshToken": "eyJjdHkiOiJKV1QiLCJlbmMiOiJBMjU2R0NNIiwiYWxnIjoiUlNBLU9BRVAifQ.u-SC0-j26XEGWgBADliqhkwkRqc0cy8Q6kwG1IuqDafnnG_CaZouDPnJk9WvBf66wYvAEMwdXTWwnhbyqZ3UA40ilxJFFeu3fWUl5dMXw2QKO45k8rnD6rUhK8Q-9q70BOMwFGpP-4zY_objSKcEtvchwDZ1ixIuc53utrb5DGEdqZfrZ9_m1vdZEWZRfggTlIqh_J8kE6PpygtU2bI8-30qmR7ejaIgYCoXDhRx95LCjq1yJsggkavaFZsh4aZhUFwy8TAkGxNWj3lUIARBw53JB0dliIHt64TvYLVPwgNxrlzR5hJOCc_sFM1RdRjA47JNebb8vFDbsg75N7g4gw.FPcAonrwcP9YhbRA.Z1o7Xhek66GNMmdnTs1zfW4yC2bPIxjCXycdsCdV0D2GjvKz5wYpiRffO1xzGAGe2clp4W9LxilWvi5pk-sMCBdlzM6I448bbCKLih4yb6lIrLhUEgeNMig-Ike3rBgnOAQSzEh6TtrOecdvHQfeo7nV5ISOZmGdT4mTTOQLImWdmRtNqrwbY5ge-kmMIEPG7qqu657LeE8Tl6duLXwSHDi23nhev8A27gMiYsciv2p8aYKNiPdv0jXmlOtXOB5ODlXi8kJSjWPBpkoLQtPLtAuEIayyQNZgCHIvJEYo7vchdX4jPZ8CCAuFOY7f0MeGKqZOgVD3j4eGgTKGJkmmWE9DGTOUuuEqZMKtaSr81FuF3CD2_JezNKnWv45OL42Lx-O4sqOGwZiE2HaVNBt0NLnBDaf1un7SWqElu5REHKHVblWwY8opSsvrNWcXICUXzPC0mMy6gexNguVDlIoFF7aDxuoBhnarUSTVML1erzPrCJuWuobAzL0hq0LgdomK3EC_EQ_1C3MHOrSA4hRBSWO4aiWPGmLQuSOaPLoXaNhHC6ih651NogaFVr7QsvzD8Pgw0Q-nY-AbZVstTkWKtuiokDUetry2YxMk_NV7DhozW3TaqVXrWQIkvFQn0GR166PMlsB2v1l0VIlbKHwB8-lHf4rGl_vSGUHisyCdqmWR-rf0APGmGTaKQH39VXJ69N2bGF37dwpskKz8BNOAekSLvUd3SySmxJJ0XpnqgW8WmECslPhUDjN2FN9hqczLRQLjSRTN-Z8CHdGJGkSuINeycC0XercFfbcH1bjJoE0VY597j0zcoSchr4PWtxxVUTDJNDmrM27BNnm29i4ISvBnwx-6NJbhJ0YE7BTehzLO6e0yvkdeftqgZSpemZ3NBgFpwlwn5AkzHZJS8N2ye8UO210s6XM_ty4IQYbNMsjmdZ9F5g8YQBIpKSiNXe9UnrL3aGScbG_O9MJYHol2vbgIgL3IPxjRwvKeIvII-4OqnjmbXJ5hh00Siz6FlQ1OtJ_TwLtArEOA5z8ZKxfAT5d6y_LN99BjQStclR9Ns3Orc0eH4GjszI7ESGr6c1_1odguF81RfWWlJrur1e2xOWZxBVpz1Y-GmMM8euGBoOJyMR9sIOwBGhuC9CTRVSbsinsVYCw5FlfCJQ1YuDn6uPtLcW8OZ6nBqcuHpo-fbgo2pastpqtUpmEyb66TRebPGh25cvhChNxg0yUz5yxDI1qA0FCYhf8JPA13g_5e5nibYJDctFP8D4QEahuPHyV4Wi6n2OVryeVbcOxWYe0peE9CRzxiks_xwDs.wZuSbnkLMn1qYlyqvv7NQA",
        "TokenType": "Bearer"
    },
    "ChallengeParameters": {}
}
