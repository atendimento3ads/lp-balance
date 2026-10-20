<?php
declare(strict_types=1);

const LEAD_DESTINATION = 'thyago@balanceconsultoria.com.br';
const ALLOWED_HOST = 'gestao.balanceconsultoria.com.br';
const CONSENT_NOTICE_VERSION = '1.0';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

function wantsJson(): bool
{
    return strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function respond(int $status, bool $success, string $message): void
{
    http_response_code($status);
    if (wantsJson()) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: /?' . ($success ? 'enviado=1' : 'erro=1') . '#contato', true, 303);
    exit;
}

function cleanText(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '');
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength, 'UTF-8')
        : substr($value, 0, $maxLength);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Método não permitido.');
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 20000) {
    respond(413, false, 'Solicitação muito grande.');
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    if (!is_string($originHost) || strtolower($originHost) !== ALLOWED_HOST) {
        respond(403, false, 'Origem não autorizada.');
    }
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, true, 'Mensagem recebida.');
}

$name = cleanText((string) ($_POST['nome'] ?? ''), 100);
$company = cleanText((string) ($_POST['empresa'] ?? ''), 120);
$role = cleanText((string) ($_POST['cargo'] ?? ''), 80);
$phone = cleanText((string) ($_POST['telefone'] ?? ''), 24);
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$challenge = cleanText((string) ($_POST['desafio'] ?? ''), 80);
$contactConsent = ($_POST['consentimento_contato'] ?? '') === '1';

$allowedChallenges = [
    'Controle financeiro',
    'Fluxo de caixa',
    'Custos e despesas',
    'Lucro e rentabilidade',
    'Indicadores financeiros',
    'Planejamento',
    'Outro',
];
$phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';

if ($name === '' || $company === '' || $role === '' || $email === false
    || !in_array($challenge, $allowedChallenges, true)
    || !preg_match('/^\d{10,11}$/', $phoneDigits)
    || !$contactConsent) {
    respond(422, false, 'Revise os campos obrigatórios.');
}

$submittedAt = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i:s T');
$subject = 'Novo contato — Governança Financeira';
$safeName = preg_replace('/[\r\n]+/', ' ', $name) ?? 'Contato do site';
$body = implode("\r\n", [
    'Novo contato recebido pela landing page de Governança Financeira.',
    '',
    'Nome: ' . $name,
    'Empresa: ' . $company,
    'Cargo: ' . $role,
    'Telefone / WhatsApp: ' . $phone,
    'E-mail: ' . $email,
    'Principal desafio: ' . $challenge,
    '',
    'Autorização para contato: sim',
    'Versão técnica do aviso: ' . CONSENT_NOTICE_VERSION,
    'Recebido em: ' . $submittedAt,
]);

$headers = [
    'From: Balance Consultoria <no-reply@balanceconsultoria.com.br>',
    'Reply-To: ' . $safeName . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$sent = mail(
    LEAD_DESTINATION,
    '=?UTF-8?B?' . base64_encode($subject) . '?=',
    $body,
    implode("\r\n", $headers)
);

if (!$sent) {
    respond(500, false, 'Não foi possível enviar agora.');
}

respond(200, true, 'Mensagem enviada.');
