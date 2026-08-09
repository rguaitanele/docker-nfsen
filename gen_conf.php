#!/usr/local/bin/php
<?php
declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, "gen-nfsen-conf: {$message}\n");
    exit(1);
}

function perlQuote(string $value): string
{
    return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
}

/**
 * Formato legado: nome,porta,cor,tipo[,optarg]:nome,porta,cor,tipo[,optarg]
 */
function parseLegacySources(string $value): array
{
    $sources = [];
    foreach (explode(':', $value) as $index => $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            fail("source vazio na posição #{$index} de NFSEN_SOURCES");
        }

        $fields = array_map('trim', explode(',', $entry, 5));
        if (count($fields) < 4) {
            fail("source #{$index} inválido em NFSEN_SOURCES: esperado nome,porta,cor,tipo[,optarg]");
        }

        $source = [
            'name' => $fields[0],
            'port' => $fields[1],
            'color' => $fields[2],
            'type' => $fields[3],
        ];
        if (isset($fields[4]) && $fields[4] !== '') {
            $source['optarg'] = $fields[4];
        }
        $sources[] = $source;
    }

    return $sources;
}

function parseJsonSources(string $json): array
{
    try {
        $sources = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        fail('JSON inválido: '.$error->getMessage());
    }

    if (!is_array($sources)) {
        fail('o JSON de sources precisa ser uma lista');
    }

    return $sources;
}

$output = $argv[1] ?? null;
if ($output === null) {
    fail('informe o caminho do arquivo de saída');
}

$templatePath = getenv('NFSEN_CONFIG_TEMPLATE') ?: '/etc/nfsen/nfsen.conf.template';
$json = getenv('NFSEN_SOURCES_JSON');
$legacy = getenv('NFSEN_SOURCES');
if ($json !== false && trim($json) !== '') {
    $sources = parseJsonSources($json);
} elseif ($legacy !== false && trim($legacy) !== '') {
    $sources = parseLegacySources($legacy);
} else {
    $sourcesPath = getenv('NFSEN_SOURCES_FILE') ?: '/etc/nfsen/sources.json';
    $json = @file_get_contents($sourcesPath);
    if ($json === false) {
        fail("nenhum source configurado: defina NFSEN_SOURCES, NFSEN_SOURCES_JSON ou monte {$sourcesPath}");
    }
    $sources = parseJsonSources($json);
}

if (!is_array($sources) || $sources === []) {
    fail('a lista de sources não pode ficar vazia');
}

$seenPorts = [];
$lines = ["%sources = ("];
foreach ($sources as $index => $source) {
    if (!is_array($source)) {
        fail("source #{$index} precisa ser um objeto");
    }

    $name = (string) ($source['name'] ?? '');
    $port = filter_var($source['port'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 65535],
    ]);
    $color = (string) ($source['color'] ?? '');
    $type = (string) ($source['type'] ?? 'netflow');
    $optarg = trim((string) ($source['optarg'] ?? ''));

    if (!preg_match('/^[A-Za-z0-9_]{1,19}$/', $name)) {
        fail("nome inválido no source #{$index}: use de 1 a 19 letras, números ou underscore");
    }
    if ($port === false) {
        fail("porta inválida no source {$name}");
    }
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        fail("cor inválida no source {$name}");
    }
    if (!in_array($type, ['netflow', 'sflow'], true)) {
        fail("type inválido no source {$name}");
    }
    if (preg_match('/[\r\n]/', $optarg)) {
        fail("optarg inválido no source {$name}");
    }
    // O NfSen usa a porta 0 para manter sources históricos sem iniciar coletor.
    // Portanto, ela pode aparecer mais de uma vez; portas UDP reais são únicas.
    if ($port !== 0 && isset($seenPorts[$port])) {
        fail("porta {$port} repetida em {$seenPorts[$port]} e {$name}");
    }
    if ($port !== 0) {
        $seenPorts[$port] = $name;
    }

    $fields = [
        "'port' => '".perlQuote((string) $port)."'",
        "'col' => '".perlQuote($color)."'",
        "'type' => '".perlQuote($type)."'",
    ];
    if ($optarg !== '') {
        $fields[] = "'optarg' => ' ".perlQuote($optarg)." '";
    }
    $lines[] = "    '".perlQuote($name)."' => { ".implode(', ', $fields)." },";
}
$lines[] = ');';
$sourceBlock = implode("\n", $lines);

$template = @file_get_contents($templatePath);
if ($template === false) {
    fail("não foi possível ler o template {$templatePath}");
}

$count = 0;
$config = preg_replace('/%sources\s*=\s*\(.*?^\);/ms', $sourceBlock, $template, 1, $count);
if ($config === null || $count !== 1) {
    fail('bloco %sources não encontrado no template');
}
if (@file_put_contents($output, $config) === false) {
    fail("não foi possível gravar {$output}");
}

fwrite(STDOUT, "Configuração gerada com ".count($sources)." sources em {$output}\n");
