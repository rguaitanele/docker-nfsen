# Docker NfSen

Imagem Docker do NfSen 1.3.11-blz.1 com nfdump 1.7.8, PHP 8.2 e Apache,
baseada no Debian 12.

Código-fonte: [github.com/rguaitanele/docker-nfsen](https://github.com/rguaitanele/docker-nfsen)

Os sources são configurados por variável de ambiente e a configuração do NfSen
é regenerada sempre que o container inicia. Não é necessário editar o
`nfsen.conf` dentro do container nem manter um `sources.json` no projeto.

## Executar

Crie um `docker.sh`:

```bash
#!/bin/bash
set -e

IMAGE='rguaitanele/docker-nfsen:latest'

NFSEN_SOURCES='borda_01,4445,#ff0000,netflow,-s -16000:'\
'borda_02,4446,#00BFFF,netflow,-s -16000:'\
'concentrador_01,4447,#009933,netflow,-s -1000:'\
'concentrador_02,4448,#CC6600,netflow,-s -1000'

docker run -d \
  --hostname nfsen \
  --name nfsen \
  --restart always \
  --stop-timeout 30 \
  -e TZ='America/Sao_Paulo' \
  -e "NFSEN_SOURCES=$NFSEN_SOURCES" \
  -p 8080:80 \
  -p 4445-4448:4445-4448/udp \
  -v /home/docker/nfsen/data:/data \
  -v /etc/localtime:/etc/localtime:ro \
  "$IMAGE"
```

Execute:

```bash
chmod +x docker.sh
./docker.sh
```

A interface ficará disponível em `http://IP_DO_SERVIDOR:8080/nfsen/`.

O diretório do host montado em `/data` contém os flows, perfis, gráficos e
demais dados persistentes. Ele pode ser substituído pelo caminho usado na sua
instalação, inclusive por um diretório de dados legado.

## Configurar sources

A variável `NFSEN_SOURCES` utiliza o formato:

```text
nome,porta,cor,tipo[,optarg]:nome,porta,cor,tipo[,optarg]
```

Cada source é separado por `:`. Os campos são:

- `nome`: identificador permanente do source, com até 19 letras, números ou
  underscores;
- `porta`: porta UDP na qual o equipamento envia os flows;
- `cor`: cor hexadecimal usada nos gráficos;
- `tipo`: `netflow` ou `sflow`;
- `optarg`: argumentos opcionais enviados ao coletor.

O quinto campo é opcional. Para informar a sampling rate:

```text
borda_01,4445,#ff0000,netflow,-s -16000
```

As rates acima são apenas exemplos e precisam corresponder à configuração de
sampling de cada equipamento exportador.

Sem o quinto campo, o source é criado sem `optarg` e o coletor utiliza seu
comportamento padrão.

Ao adicionar uma porta fora do intervalo publicado no exemplo, ajuste também o
mapeamento do `docker run`. Por exemplo, para receber na porta `4454`:

```bash
-p 4445-4454:4445-4454/udp
```

## Arquivar ou renomear um source

O nome do source identifica seus dados históricos. Para substituir ou renomear
um equipamento sem perder o histórico, preserve o nome antigo com a porta `0` e
adicione o nome novo usando a porta liberada:

```text
borda_antiga,0,#0000ff,netflow:borda_02,4446,#00BFFF,netflow,-s -16000
```

A porta `0` mantém o source disponível para consulta, mas não inicia um coletor.
Vários sources arquivados podem usar a porta `0`; portas UDP ativas precisam ser
exclusivas.

Depois de adicionar ou remover sources, recrie o container. A configuração e o
perfil `live` serão atualizados automaticamente. Perfis personalizados podem
precisar receber o novo source pela interface do NfSen.

## Atualizar o container

Faça backup do diretório persistente antes de atualizações importantes. Para
trocar a imagem sem apagar os dados:

```bash
docker pull rguaitanele/docker-nfsen:latest
docker stop nfsen
docker rm nfsen
./docker.sh
```

O `docker rm` remove somente o container. Os dados permanecem no diretório do
host montado em `/data`.

Para testar a versão de desenvolvimento, altere a imagem para:

```text
rguaitanele/docker-nfsen:dev
```

## Tags

- `dev`: versão gerada pela branch de desenvolvimento;
- `latest`: versão estável mais recente;
- tags de versão: versões fixas indicadas para produção e rollback.

## Build local

Para gerar a imagem diretamente a partir do repositório:

```bash
docker build -t docker-nfsen:local .
```

As versões podem ser alteradas com argumentos de build:

```bash
docker build \
  --build-arg NFDUMP_VERSION=1.7.8 \
  --build-arg NFSEN_REPOSITORY=rguaitanele/nfsen \
  --build-arg NFSEN_VERSION=1.3.11-blz.1 \
  -t docker-nfsen:local .
```

O fork `rguaitanele/nfsen` contém as correções de compatibilidade utilizadas por
esta imagem.

## Publicação

O GitLab CI publica automaticamente a mesma imagem no GitLab Container Registry
e no Docker Hub:

- branch `dev`: tag `dev`;
- branch `master`: tag `latest`;
- tag Git: a tag informada e `latest`.

Para a publicação no Docker Hub, configure `DOCKERHUB_USERNAME` e
`DOCKERHUB_TOKEN` como variáveis mascaradas no GitLab CI.
