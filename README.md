# Docker NfSen

Container do NfSen 1.3.11-blz.1 com nfdump 1.7.8, PHP 8.2 e Apache sobre Debian 12.

## Executar

```bash
docker compose up -d --build
```

A interface fica em `http://localhost:8080/nfsen/`. Os dados são persistidos em
`./data` e os coletores UDP usam as portas 4445 a 4453.

## Configurar sources

Edite `sources.json`. O arquivo é montado como somente leitura e convertido em
`/usr/local/nfsen/etc/nfsen.conf` em toda inicialização. Portanto, a configuração
continua correta depois de recriar ou reiniciar o container.

Depois de editar o arquivo:

```bash
docker compose restart nfsen
```

Também é possível fornecer o JSON diretamente pela variável
`NFSEN_SOURCES_JSON` ou montar outro arquivo e indicar seu caminho em
`NFSEN_SOURCES_FILE`.

Não edite `nfsen.conf` dentro do container: essa cópia é gerada automaticamente.

Para usar um diretório de dados fora do projeto, copie `.env.example` para
`.env` e ajuste `NFSEN_DATA_DIR`.

## Imagens no GitLab

O pipeline publica automaticamente:

- pushes na branch `dev`: `$CI_REGISTRY_IMAGE:dev`;
- tags Git: `$CI_REGISTRY_IMAGE:<tag>` e `$CI_REGISTRY_IMAGE:latest`.

As variáveis de autenticação do Container Registry são fornecidas pelo próprio
GitLab CI. A instância precisa ter o Container Registry habilitado; o pipeline
interrompe com uma mensagem explícita quando `CI_REGISTRY` não estiver definido,
evitando que o Docker tente autenticar acidentalmente no Docker Hub.

## Atualização segura

As versões são fixadas pelos argumentos `NFDUMP_VERSION`, `NFSEN_REPOSITORY` e
`NFSEN_VERSION` no Compose. A versão usada vem do fork `rguaitanele/nfsen`, que
incorpora as correções de compatibilidade com PHP 8. O NfSen 1.3.11 requer
nfdump 1.6.20 ou posterior e é compatível com a série 1.7.x. Antes de trocar de
1.6.x para 1.7.x, faça backup de `./data`, pois o formato dos arquivos mudou e
perfis históricos antigos têm limitações.
