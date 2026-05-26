# Git Flow Tutorial

Este guia mostra como iniciar o git-flow no repo e usar feature, release e hotfix.

## 1) Iniciar o git-flow

Execute:

```
git flow init
```

Sugestao de respostas (defaults):
- Branch de producao: main
- Branch de desenvolvimento: develop
- Prefixos: feature/, release/, hotfix/, support/, version tag (v)

Se o repo so tem a main, o git-flow vai criar a develop automaticamente.

## 2) Feature

Criar e comecar uma feature:

```
git flow feature start minha-feature
```

Trabalhe e faça commits normalmente. Para finalizar:

```
git flow feature finish minha-feature
```

Isso mescla a feature em develop e remove a branch local da feature.

## 3) Release

Criar release a partir de develop:

```
git flow release start 1.0.0
```

Depois de ajustes finais, finalizar:

```
git flow release finish 1.0.0
```

Isso mescla a release em main e develop e cria a tag.

## 4) Hotfix

Criar hotfix a partir de main:

```
git flow hotfix start 1.0.1
```

Faca as correcoes e commits. Finalizar hotfix:

```
git flow hotfix finish 1.0.1
```

Isso mescla o hotfix em main e develop e cria a tag.

## 5) Enviar para o remoto

Depois de terminar release ou hotfix:

```
git push origin main develop --tags
```

## Dicas

- Use nomes claros para feature: feature/ajuste-login
- Para ver branches do git-flow:
  - feature: `git flow feature list`
  - release: `git flow release list`
  - hotfix: `git flow hotfix list`
