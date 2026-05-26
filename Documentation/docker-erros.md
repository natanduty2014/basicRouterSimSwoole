# Desativar autocrlf no Git para Builds Docker

Ao trabalhar com construção de imagens Docker, pode ser necessário desativar a configuração `autocrlf` no Git para evitar problemas ao encontrar o entrypoint. O `autocrlf` converte automaticamente as quebras de linha dos arquivos entre sistemas operacionais.

## 1. Verificar Configurações Atuais

Antes de fazer alterações, é bom verificar as configurações atuais do Git:

```bash
git config --get core.autocrlf
```
### O retorno precisa ser `false` caso contrário, siga para o próximo passo.
```bash
git config --global core.autocrlf false
```
### Verifique novamente as configurações do Git:
```bash
git config --get core.autocrlf
```