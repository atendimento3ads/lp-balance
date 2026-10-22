<?php
declare(strict_types=1);

const LEAD_DESTINATION = 'thyago@balanceconsultoria.com.br';
const MAIL_SENDER = 'naoresponder@balanceconsultoria.com.br';
const ALLOWED_HOST = 'gestao.balanceconsultoria.com.br';
const CONSENT_NOTICE_VERSION = '1.0';
const LEADS_STORAGE_DIRECTORY = '/home1/balanc29/balance-leads-private';

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

function csvSafeValue(string $value): string
{
    return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
}

function saveLead(array $lead): bool
{
    $directory = LEADS_STORAGE_DIRECTORY;
    if (is_link($directory)) {
        return false;
    }
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        return false;
    }
    @chmod($directory, 0700);

    $path = $directory . '/leads.csv';
    if (is_link($path)) {
        return false;
    }

    $previousUmask = umask(0077);
    $file = @fopen($path, 'c+');
    umask($previousUmask);
    if ($file === false) {
        return false;
    }

    try {
        if (!flock($file, LOCK_EX)) {
            return false;
        }

        @chmod($path, 0600);
        if (fseek($file, 0, SEEK_END) !== 0) {
            return false;
        }

        if (ftell($file) === 0) {
            if (fwrite($file, "\xEF\xBB\xBF") === false
                || fputcsv($file, [
                    'ID', 'Recebido em', 'Nome', 'Empresa', 'Cargo', 'Telefone / WhatsApp',
                    'E-mail', 'Principal desafio', 'Autorização para contato', 'Versão do aviso',
                ], ';', '"', '\\') === false) {
                return false;
            }
        }

        $row = array_map('csvSafeValue', $lead);
        if (fputcsv($file, $row, ';', '"', '\\') === false || !fflush($file)) {
            return false;
        }
        if (function_exists('fsync') && !fsync($file)) {
            return false;
        }

        return true;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
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
$submissionId = bin2hex(random_bytes(8));
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
    'Protocolo: ' . $submissionId,
]);

$stored = saveLead([
    $submissionId,
    $submittedAt,
    $name,
    $company,
    $role,
    $phone,
    (string) $email,
    $challenge,
    'sim',
    CONSENT_NOTICE_VERSION,
]);

$headers = [
    'From: Balance Consultoria <' . MAIL_SENDER . '>',
    'Sender: ' . MAIL_SENDER,
    'Reply-To: ' . $safeName . ' <' . $email . '>',
    'Auto-Submitted: auto-generated',
    'X-Auto-Response-Suppress: All',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$sent = mail(
    LEAD_DESTINATION,
    '=?UTF-8?B?' . base64_encode($subject) . '?=',
    $body,
    implode("\r\n", $headers),
    '-f' . MAIL_SENDER
);

if (!$stored && !$sent) {
    respond(500, false, 'Não foi possível registrar nem enviar sua solicitação. Tente novamente.');
}

if (!$stored) {
    error_log('[lead-form] CSV backup failed for submission ' . $submissionId);
}
if (!$sent) {
    error_log('[lead-form] Email delivery failed for submission ' . $submissionId);
}

if ($stored) {
    respond(200, true, 'Solicitação registrada com sucesso. A equipe da Balance entrará em contato.');
}

respond(200, true, 'Solicitação enviada à equipe da Balance, mas o backup local não pôde ser gravado.');
