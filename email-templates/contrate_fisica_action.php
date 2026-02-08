<?php
/**
 * contrate_fisica_action.php
 * ─────────────────────────────────────────────────────────────
 * Recebe o POST do formulário de contratação (Pessoa Física)
 * e envia tudo por e-mail usando PHPMailer (SMTP).
 *
 * REQUISITOS:
 *   composer require phpmailer/phpmailer
 *   (ou baixe manualmente em https://github.com/PHPMailer/PHPMailer)
 *
 * INSTALAÇÃO:
 *   1. Coloque este arquivo em: email-templates/contrate_fisica_action.php
 *   2. Instale o PHPMailer via Composer na raiz do seu site:
 *        cd /caminho/do/site && composer require phpmailer/phpmailer
 *   3. Ajuste o caminho do autoload abaixo se necessário.
 *   4. Configure as constantes SMTP abaixo com os dados do seu servidor.
 * ─────────────────────────────────────────────────────────────
 */

// ══════════════════════════════════════════════════════════════
// CONFIGURAÇÃO SMTP — AJUSTE CONFORME SEU PROVEDOR DE E-MAIL
// ══════════════════════════════════════════════════════════════
define('SMTP_HOST',       'mail.guardiantech.site');   // servidor SMTP (ajuste se for outro)
define('SMTP_PORT',       465);                         // 465 (SSL) ou 587 (TLS)
define('SMTP_SECURE',     'ssl');                        // 'ssl' ou 'tls'
define('SMTP_USER',       'contato@guardiantech.site');
define('SMTP_PASS',       'Gu4rdMail!@');
define('MAIL_FROM',       'contato@guardiantech.site');
define('MAIL_FROM_NAME',  'GuardianTech - Contratação');
define('MAIL_TO',         'contato@guardiantech.site');
define('MAIL_SUBJECT',    'Nova contratação — Pessoa Física');

// Tamanho máximo por arquivo (8 MB) e total (16 MB)
define('MAX_FILE_SIZE',   8 * 1024 * 1024);
define('MAX_TOTAL_SIZE',  16 * 1024 * 1024);

// ══════════════════════════════════════════════════════════════
// AUTOLOAD DO PHPMAILER (Composer)
// ══════════════════════════════════════════════════════════════
// Ajuste o caminho relativo conforme sua estrutura de pastas.
// Se o composer.json está na raiz e este arquivo em email-templates/:
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',   // email-templates/ → raiz
    __DIR__ . '/vendor/autoload.php',      // mesmo diretório
    __DIR__ . '/../../vendor/autoload.php', // dois níveis acima
];

$loaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    http_response_code(500);
    die(json_encode([
        'ok'      => false,
        'message' => 'Erro interno: PHPMailer não encontrado. Execute "composer require phpmailer/phpmailer" na raiz do site.'
    ]));
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ══════════════════════════════════════════════════════════════
// VERIFICAÇÕES BÁSICAS
// ══════════════════════════════════════════════════════════════
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['ok' => false, 'message' => 'Método não permitido.']));
}

// Honeypot antispam
if (!empty($_POST['website'])) {
    die(json_encode(['ok' => true, 'message' => 'Enviado com sucesso.'])); // silencioso
}

// ══════════════════════════════════════════════════════════════
// COLETA DOS CAMPOS DO FORMULÁRIO
// ══════════════════════════════════════════════════════════════
function post(string $key, string $default = ''): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

// Dados pessoais
$fullName         = post('full_name');
$email            = post('email');
$cpf              = post('cpf');
$rg               = post('rg');
$birthDate        = post('birth_date');
$phonePrimary     = post('phone_primary');
$phoneSecondary   = post('phone_secondary');
$platformUsername  = post('platform_username');

// Endereço de cadastro
$addressCep          = post('address_cep');
$addressUf           = post('address_uf');
$addressCity         = post('address_city');
$addressNeighborhood = post('address_neighborhood');
$addressStreet       = post('address_street');
$addressNumber       = post('address_number');
$addressComplement   = post('address_complement');
$addressNote         = post('address_note');

// Contato de emergência
$emergencyName         = post('emergency_contact_name');
$emergencyPhone        = post('emergency_contact_phone');
$emergencyRelationship = post('emergency_contact_relationship');

// Dados do veículo
$vehicleType          = post('vehicle_type');
$vehicleFuel          = post('vehicle_fuel');
$vehicleColor         = post('vehicle_color');
$vehiclePlate         = post('vehicle_plate');
$vehicleBrand         = post('vehicle_brand');
$vehicleModel         = post('vehicle_model');
$vehicleYearModel     = post('vehicle_year_model');
$vehicleMaxDays       = post('vehicle_max_days_no_movement');
$remoteBlocking       = post('remote_blocking');

// Endereço da instalação
$installChoice       = post('installation_address_choice');
$installCep          = post('install_cep');
$installUf           = post('install_uf');
$installCity         = post('install_city');
$installNeighborhood = post('install_neighborhood');
$installStreet       = post('install_street');
$installNumber       = post('install_number');
$installComplement   = post('install_complement');
$installNote         = post('install_note');

// Instalação e pagamentos
$installPeriod     = isset($_POST['installation_period']) && is_array($_POST['installation_period'])
                     ? implode(', ', array_map('trim', $_POST['installation_period']))
                     : post('installation_period');
$installPayment    = post('installation_payment_method');
$monthlyPayment    = post('monthly_payment_method');
$monthlyDueDay     = post('monthly_due_day');

// Cupom
$couponValid       = post('coupon_valid');
$couponCode        = post('coupon_code_applied');
$installationCoupon = post('installation_coupon');

// Resumo calculado
$calculatedPlan    = post('calculated_plan');
$calculatedMonthly = post('calculated_monthly');
$calculatedInstall = post('calculated_install');

// Termos
$termsAccepted     = post('terms_accepted');

// Dados coletados (transparência)
$collectedIp          = post('collected_ip');
$collectedUserAgent   = post('collected_user_agent_raw');
$collectedBrowser     = post('collected_browser_friendly');
$collectedOs          = post('collected_os_friendly');
$collectedServerDT    = post('collected_server_datetime');
$collectedEpoch       = post('collected_server_epoch_ms');
$collectedGeo         = post('collected_geolocation');

// ══════════════════════════════════════════════════════════════
// MONTA O CORPO DO E-MAIL (HTML)
// ══════════════════════════════════════════════════════════════
$h = function(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };

$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333;line-height:1.6;margin:0;padding:20px;background:#f5f5f5;">
<div style="max-width:700px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">

<!-- Header -->
<div style="background:#33322f;padding:25px 30px;text-align:center;">
  <h1 style="margin:0;color:#AF985A;font-size:22px;">Nova Contratação — Pessoa Física</h1>
  <p style="margin:5px 0 0;color:#ccc;font-size:13px;">Recebido em ' . $h(date('d/m/Y H:i:s')) . '</p>
</div>

<div style="padding:25px 30px;">

<!-- DADOS PESSOAIS -->
<h2 style="color:#AF985A;font-size:16px;border-bottom:2px solid #AF985A;padding-bottom:5px;">Dados Pessoais</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
  <tr><td style="padding:6px 10px;font-weight:bold;width:220px;vertical-align:top;">Nome completo:</td><td style="padding:6px 10px;">' . $h($fullName) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">E-mail:</td><td style="padding:6px 10px;">' . $h($email) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">CPF:</td><td style="padding:6px 10px;">' . $h($cpf) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">RG:</td><td style="padding:6px 10px;">' . $h($rg) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Data de nascimento:</td><td style="padding:6px 10px;">' . $h($birthDate) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Celular principal:</td><td style="padding:6px 10px;">' . $h($phonePrimary) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Telefone secundário:</td><td style="padding:6px 10px;">' . $h($phoneSecondary) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Usuário plataforma:</td><td style="padding:6px 10px;">' . $h($platformUsername) . '</td></tr>
</table>

<!-- ENDEREÇO DE CADASTRO -->
<h2 style="color:#AF985A;font-size:16px;border-bottom:2px solid #AF985A;padding-bottom:5px;">Endereço de Cadastro</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
  <tr><td style="padding:6px 10px;font-weight:bold;width:220px;">CEP:</td><td style="padding:6px 10px;">' . $h($addressCep) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">UF:</td><td style="padding:6px 10px;">' . $h($addressUf) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Cidade:</td><td style="padding:6px 10px;">' . $h($addressCity) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Bairro:</td><td style="padding:6px 10px;">' . $h($addressNeighborhood) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Rua/Avenida:</td><td style="padding:6px 10px;">' . $h($addressStreet) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Número:</td><td style="padding:6px 10px;">' . $h($addressNumber) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Complemento:</td><td style="padding:6px 10px;">' . $h($addressComplement) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Observação:</td><td style="padding:6px 10px;">' . $h($addressNote) . '</td></tr>
</table>

<!-- CONTATO DE EMERGÊNCIA -->
<h2 style="color:#AF985A;font-size:16px;border-bottom:2px solid #AF985A;padding-bottom:5px;">Contato de Emergência</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
  <tr><td style="padding:6px 10px;font-weight:bold;width:220px;">Nome:</td><td style="padding:6px 10px;">' . $h($emergencyName) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Telefone:</td><td style="padding:6px 10px;">' . $h($emergencyPhone) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Relação/parentesco:</td><td style="padding:6px 10px;">' . $h($emergencyRelationship) . '</td></tr>
</table>

<!-- DADOS DO VEÍCULO -->
<h2 style="color:#AF985A;font-size:16px;border-bottom:2px solid #AF985A;padding-bottom:5px;">Dados do Veículo</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
  <tr><td style="padding:6px 10px;font-weight:bold;width:220px;">Tipo:</td><td style="padding:6px 10px;">' . $h($vehicleType) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Combustível:</td><td style="padding:6px 10px;">' . $h($vehicleFuel) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Cor:</td><td style="padding:6px 10px;">' . $h($vehicleColor) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Placa:</td><td style="padding:6px 10px;">' . $h($vehiclePlate) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Marca:</td><td style="padding:6px 10px;">' . $h($vehicleBrand) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Modelo:</td><td style="padding:6px 10px;">' . $h($vehicleModel) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Ano modelo:</td><td style="padding:6px 10px;">' . $h($vehicleYearModel) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Máx. sem uso:</td><td style="padding:6px 10px;">' . $h($vehicleMaxDays) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Bloqueio remoto:</td><td style="padding:6px 10px;">' . $h($remoteBlocking) . '</td></tr>
</table>

<!-- ENDEREÇO DA INSTALAÇÃO -->
<h2 style="color:#AF985A;font-size:16px;border-bottom:2px solid #AF985A;padding-bottom:5px;">Endereço da Instalação</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
  <tr><td style="padding:6px 10px;font-weight:bold;width:220px;">Opção:</td><td style="padding:6px 10px;">' . $h($installChoice === 'same' ? 'Mesmo endereço de cadastro' : 'Outro endereço') . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">CEP:</td><td style="padding:6px 10px;">' . $h($installCep) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">UF:</td><td style="padding:6px 10px;">' . $h($installUf) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Cidade:</td><td style="padding:6px 10px;">' . $h($installCity) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Bairro:</td><td style="padding:6px 10px;">' . $h($installNeighborhood) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Rua/Avenida:</td><td style="padding:6px 10px;">' . $h($installStreet) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Número:</td><td style="padding:6px 10px;">' . $h($installNumber) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Complemento:</td><td style="padding:6px 10px;">' . $h($installComplement) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Observação:</td><td style="padding:6px 10px;">' . $h($installNote) . '</td></tr>
</table>

<!-- INSTALAÇÃO E PAGAMENTOS -->
<h2 style="color:#AF985A;font-size:16px;border-bottom:2px solid #AF985A;padding-bottom:5px;">Instalação e Pagamentos</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
  <tr><td style="padding:6px 10px;font-weight:bold;width:220px;">Período preferido:</td><td style="padding:6px 10px;">' . $h($installPeriod) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Pgto. instalação:</td><td style="padding:6px 10px;">' . $h($installPayment) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Pgto. mensalidade:</td><td style="padding:6px 10px;">' . $h($monthlyPayment) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Dia vencimento:</td><td style="padding:6px 10px;">' . $h($monthlyDueDay) . '</td></tr>
</table>

<!-- RESUMO -->
<h2 style="color:#AF985A;font-size:16px;border-bottom:2px solid #AF985A;padding-bottom:5px;">Resumo do Pedido</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
  <tr><td style="padding:6px 10px;font-weight:bold;width:220px;">Plano:</td><td style="padding:6px 10px;">' . $h($calculatedPlan) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Mensalidade:</td><td style="padding:6px 10px;">' . $h($calculatedMonthly) . '</td></tr>
  <tr><td style="padding:6px 10px;font-weight:bold;">Instalação:</td><td style="padding:6px 10px;">' . $h($calculatedInstall) . '</td></tr>
  <tr style="background:#f9f9f9;"><td style="padding:6px 10px;font-weight:bold;">Cupom aplicado:</td><td style="padding:6px 10px;">' . ($couponValid === '1' ? $h($couponCode) : 'Nenhum') . '</td></tr>
</table>

<!-- DADOS COLETADOS -->
<h2 style="color:#999;font-size:14px;border-bottom:1px solid #ddd;padding-bottom:5px;">Dados de Transparência</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:12px;color:#888;">
  <tr><td style="padding:4px 10px;font-weight:bold;width:220px;">IP:</td><td style="padding:4px 10px;">' . $h($collectedIp) . '</td></tr>
  <tr><td style="padding:4px 10px;font-weight:bold;">User-Agent:</td><td style="padding:4px 10px;">' . $h($collectedUserAgent) . '</td></tr>
  <tr><td style="padding:4px 10px;font-weight:bold;">Navegador:</td><td style="padding:4px 10px;">' . $h($collectedBrowser) . '</td></tr>
  <tr><td style="padding:4px 10px;font-weight:bold;">SO:</td><td style="padding:4px 10px;">' . $h($collectedOs) . '</td></tr>
  <tr><td style="padding:4px 10px;font-weight:bold;">Data/hora servidor:</td><td style="padding:4px 10px;">' . $h($collectedServerDT) . '</td></tr>
  <tr><td style="padding:4px 10px;font-weight:bold;">Geolocalização:</td><td style="padding:4px 10px;">' . ($collectedGeo ? $h($collectedGeo) : 'Não disponível') . '</td></tr>
  <tr><td style="padding:4px 10px;font-weight:bold;">Termos aceitos:</td><td style="padding:4px 10px;">' . ($termsAccepted ? 'Sim' : 'Não') . '</td></tr>
</table>

<p style="font-size:12px;color:#999;text-align:center;margin-top:30px;">
  Documentos anexados (se houver) estão nos anexos deste e-mail.
</p>

</div>
</div>
</body>
</html>';

// ══════════════════════════════════════════════════════════════
// ENVIO COM PHPMAILER
// ══════════════════════════════════════════════════════════════
$mail = new PHPMailer(true);

try {
    // Configuração SMTP
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // Remetente e destinatário
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_TO);

    // Reply-To com o e-mail do cliente
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($email, $fullName);
    }

    // Assunto
    $mail->Subject = MAIL_SUBJECT . ' — ' . ($fullName ?: 'Sem nome');

    // Corpo HTML
    $mail->isHTML(true);
    $mail->Body    = $html;
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</tr>', '</td>'], ["\n", "\n", "\n", "\n", " | "], $html));

    // ── Anexos (document_file_1 e document_file_2) ──
    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif',
        'application/pdf',
    ];

    $totalSize = 0;

    foreach (['document_file_1', 'document_file_2'] as $fileKey) {
        if (
            isset($_FILES[$fileKey]) &&
            $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK &&
            $_FILES[$fileKey]['size'] > 0
        ) {
            $tmpName  = $_FILES[$fileKey]['tmp_name'];
            $fileName = $_FILES[$fileKey]['name'];
            $fileSize = $_FILES[$fileKey]['size'];
            $fileMime = $_FILES[$fileKey]['type'];

            // Validar tipo MIME real
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($tmpName);

            if (!in_array($realMime, $allowedMimes, true)) {
                echo json_encode([
                    'ok'      => false,
                    'message' => "Tipo de arquivo não permitido: {$fileName} ({$realMime})"
                ]);
                exit;
            }

            if ($fileSize > MAX_FILE_SIZE) {
                echo json_encode([
                    'ok'      => false,
                    'message' => "Arquivo {$fileName} excede o limite de 8 MB."
                ]);
                exit;
            }

            $totalSize += $fileSize;

            if ($totalSize > MAX_TOTAL_SIZE) {
                echo json_encode([
                    'ok'      => false,
                    'message' => 'O total dos arquivos excede 16 MB.'
                ]);
                exit;
            }

            $mail->addAttachment($tmpName, $fileName, 'base64', $realMime);
        }
    }

    // Enviar
    $mail->send();

    echo json_encode([
        'ok'      => true,
        'message' => 'Solicitação enviada com sucesso! Entraremos em contato em breve.'
    ]);

} catch (Exception $e) {
    error_log('PHPMailer Error: ' . $mail->ErrorInfo);

    echo json_encode([
        'ok'      => false,
        'message' => 'Erro ao enviar e-mail. Tente novamente ou entre em contato diretamente.'
    ]);
}
