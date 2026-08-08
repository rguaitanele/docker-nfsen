# Docker NfSen

Container do NfSen 1.3.11-blz.1 com nfdump 1.7.8, PHP 8.2 e Apache sobre Debian 12.

## Executar

```bash
docker compose up -d --build
```

A interface fica em `http://localhost:8080/nfsen/`. Os dados são persistidos em
`./data` e os coletores UDP usam as portas 4445 a 4453.

## Configurar sources

Configure os coletores pela variável `NFSEN_SOURCES`, no mesmo formato da imagem
legada:

```text
nome,porta,cor,tipo[,optarg]:nome,porta,cor,tipo[,optarg]
```

O quinto campo é opcional e permite configurar os argumentos do coletor,
incluindo a rate do fluxo:

```bash
NFSEN_SOURCES="ptt,4445,#ff0000,netflow,-s -16000:cdn,4448,#CC6600,netflow,-s -1000"
```

Entradas antigas com apenas quatro campos continuam válidas. A configuração é
regenerada em toda inicialização, portanto basta alterar a variável e recriar o
container.

Para arquivar um source sem perder o acesso ao histórico, mantenha o nome antigo
e troque sua porta por `0`. O NfSen continuará exibindo esse source, mas não
iniciará um coletor para ele. A porta liberada pode ser usada pelo nome novo:

```bash
NFSEN_SOURCES="oi,0,#0000ff,netflow:mhnet,4446,#0000ff,netflow,-s -16000"
```

Mais de um source arquivado pode usar a porta `0`; portas UDP entre `1` e
`65535` precisam continuar exclusivas.

Como alternativa, é possível fornecer uma lista JSON pela variável
`NFSEN_SOURCES_JSON` ou montar um arquivo privado e indicar seu caminho em
`NFSEN_SOURCES_FILE`. O arquivo local `sources.json` é ignorado pelo Git e não é
incluído na imagem.

Não edite `nfsen.conf` dentro do container: essa cópia é gerada automaticamente.

Para usar um diretório de dados fora do projeto, copie `.env.example` para
`.env` e ajuste `NFSEN_DATA_DIR`.

## Publicação das imagens

O pipeline publica automaticamente:

- branch `dev`: `$CI_REGISTRY_IMAGE:dev` e
  `rguaitanele/docker-nfsen:dev`;
- branch `main`: `$CI_REGISTRY_IMAGE:latest` e
  `rguaitanele/docker-nfsen:latest`;
- tags Git: `<tag>` e `latest` no GitLab Registry e no Docker Hub.

As variáveis de autenticação do Container Registry são fornecidas pelo próprio
GitLab CI. A instância precisa ter o Container Registry habilitado; o pipeline
interrompe com uma mensagem explícita quando `CI_REGISTRY` não estiver definido,
evitando que o Docker tente autenticar acidentalmente no Docker Hub.

Para publicar no Docker Hub, configure `DOCKERHUB_USERNAME` e `DOCKERHUB_TOKEN`
como variáveis do GitLab disponíveis para as branches e tags executadas pelo
pipeline. Mantenha o token mascarado para não expor seu conteúdo nos logs.

## Atualização segura

As versões são fixadas pelos argumentos `NFDUMP_VERSION`, `NFSEN_REPOSITORY` e
`NFSEN_VERSION` no Compose. A versão usada vem do fork `rguaitanele/nfsen`, que
incorpora as correções de compatibilidade com PHP 8. O NfSen 1.3.11 requer
nfdump 1.6.20 ou posterior e é compatível com a série 1.7.x. Antes de trocar de
1.6.x para 1.7.x, faça backup de `./data`, pois o formato dos arquivos mudou e
perfis históricos antigos têm limitações.
