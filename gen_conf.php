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

$output = $argv[1] ?? null;
if ($output === null) {
    fail('informe o caminho do arquivo de saída');
}

$templatePath = getenv('NFSEN_CONFIG_TEMPLATE') ?: '/etc/nfsen/nfsen.conf.template';
$json = getenv('NFSEN_SOURCES_JSON');
if ($json === false || trim($json) === '') {
    $sourcesPath = getenv('NFSEN_SOURCES_FILE') ?: '/etc/nfsen/sources.json';
    $json = @file_get_contents($sourcesPath);
    if ($json === false) {
        fail("não foi possível ler {$sourcesPath}");
    }
}

try {
    $sources = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $error) {
    fail('JSON inválido: '.$error->getMessage());
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
        'options' => ['min_range' => 1, 'max_range' => 65535],
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
    if (isset($seenPorts[$port])) {
        fail("porta {$port} repetida em {$seenPorts[$port]} e {$name}");
    }
    $seenPorts[$port] = $name;

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
