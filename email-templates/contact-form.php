<?php
/**
 * email-templates/contact-form.php
 * Versão compatível com seu front + reCAPTCHA v3 + honeypot + tempo mínimo.
 * Mantém o mesmo padrão de resposta JSON esperado pelo seu JS.
 */

if ( ! empty( $_POST['email'] ) ) {

    // ===== CONFIG =====
    $enable_smtp    = 'no'; // 'yes' para SMTP (PHPMailer), 'no' para mail()
    $receiver_email = 'contato@guardiantech.site';
    $receiver_name  = 'GuardianTech';
    $subject        = "Formulário de Contato do Site";

    // reCAPTCHA v3
    $recaptcha_secret       = '6Lfq-swrAAAAAFBIjXCf-I7l3wsKcfSmxNgD14sK';
    $recaptcha_min_score    = 0.3;    // use 0.5 quando tudo estiver 100%
    $enforce_action         = false;  // true para checar ação estritamente
    $expected_action        = 'contact';

    // Funções util
    $json_ok = function($msg){
        echo '{ "alert": "alert-success", "message": "'.addslashes($msg).'" }';
        exit;
    };
    $json_fail = function($msg){
        echo '{ "alert": "alert-danger", "message": "'.addslashes($msg).'" }';
        exit;
    };

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {

        // -------------------------
        // Anti-spam (opcionais, só atuam se os campos vierem do form)
        // -------------------------

        // Honeypot
        if ( isset($_POST['website']) && $_POST['website'] !== '' ) {
            $json_fail('Erro: verificação anti-spam falhou.');
        }

        // Tempo mínimo de preenchimento: 2s
        if ( isset($_POST['form_started_at']) && ctype_digit((string)$_POST['form_started_at']) ) {
            $elapsed = (int)(microtime(true)*1000) - (int)$_POST['form_started_at'];
            if ( $elapsed < 2000 ) {
                $json_fail('Envio muito rápido. Aguarde alguns segundos e tente novamente.');
            }
        }

        // -------------------------
        // reCAPTCHA v3 server-side
        // -------------------------
        $token = isset($_POST['g-recaptcha-response']) ? trim($_POST['g-recaptcha-response']) : '';
        if ( $token === '' ) {
            $json_fail('Erro: verificação de segurança obrigatória.');
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        // Valida no Google (cURL preferencial, fallback em file_get_contents)
        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $postfields = http_build_query([
            'secret'   => $recaptcha_secret,
            'response' => $token,
            'remoteip' => $ip
        ]);

        $verify_response = null;
        if ( function_exists('curl_init') ) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $verify_url,
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => $postfields,
                CURLOPT_TIMEOUT => 10,
            ]);
            $verify_response = curl_exec($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => $postfields,
                    'timeout' => 10
                ]
            ]);
            $verify_response = @file_get_contents($verify_url, false, $context);
        }

        if ( ! $verify_response ) {
            $json_fail('Não foi possível validar o reCAPTCHA. Tente novamente.');
        }

        $rc = json_decode($verify_response, true);
        if ( ! is_array($rc) || empty($rc['success']) ) {
            $json_fail('Verificação de segurança falhou.');
        }

        // Score leniente para não travar envios legítimos durante ajustes
        if ( isset($rc['score']) && (float)$rc['score'] < $recaptcha_min_score ) {
            $json_fail('Verificação de segurança reprovada.');
        }

        // Checagem de ação (opcional)
        if ( $enforce_action && isset($rc['action']) && $rc['action'] !== $expected_action ) {
            $json_fail('Ação do reCAPTCHA inválida.');
        }

        // -------------------------
        // Captura IP e navegador enviados pelo JS
        // -------------------------
        $usuario_ip        = ! empty( $_POST['usuario_ip'] ) ? $_POST['usuario_ip'] : ( $ip ?: 'Não disponível' );
        $usuario_navegador = ! empty( $_POST['usuario_navegador'] ) ? $_POST['usuario_navegador'] : ( $_SERVER['HTTP_USER_AGENT'] ?? 'Não disponível' );

        $prefix   = ! empty( $_POST['prefix'] ) ? $_POST['prefix'] : '';
        $submits  = $_POST;

        // -------------------------
        // Sanitização/validação básica
        // -------------------------
        $safe = function($v){
            // remove CR/LF (evita header injection), trim e sanitiza
            $v = trim((string)$v);
            $v = str_ireplace(["\r","\n","%0a","%0d"], '', $v);
            return filter_var( $v, FILTER_SANITIZE_SPECIAL_CHARS );
        };

        $email_raw = isset($_POST['email']) ? trim($_POST['email']) : '';
        if ( ! filter_var($email_raw, FILTER_VALIDATE_EMAIL) ) {
            $json_fail('Informe um e-mail válido.');
        }

        // -------------------------
        // Montagem de campos (exclui campos de sistema)
        // -------------------------
        $system_keys = [
            'g-recaptcha-response','website','form_started_at','redirect',
            'usuario_ip','usuario_navegador','prefix'
        ];

        $fields = array();
        foreach ( $submits as $key => $value ) {
            if ( in_array($key, $system_keys, true) ) {
                continue; // pula os de sistema
            }
            if ( $value === '' || $value === null ) {
                continue;
            }

            $label = str_replace( $prefix, '', $key );
            $label = function_exists( 'mb_convert_case' )
                   ? mb_convert_case( $label, MB_CASE_TITLE, "UTF-8" )
                   : ucwords( $label );

            if ( is_array( $value ) ) {
                $value = implode( ', ', $value );
            }

            $fields[ $label ] = nl2br( $safe( $value ) );
        }

        // Adiciona IP e navegador
        $fields['IP do Usuário']        = nl2br( $safe( $usuario_ip ) );
        $fields['Navegador do Usuário'] = nl2br( $safe( $usuario_navegador ) );

        // -------------------------
        // Constrói as linhas da tabela (prioriza telefone/Telefone)
        // -------------------------
        $response = array();

        // Prioriza "Phone" (caso algum template antigo) e "Telefone"
        $priorPhones = [];
        if ( isset( $fields['Phone'] ) )   { $priorPhones['Phone']   = $fields['Phone'];   unset($fields['Phone']); }
        if ( isset( $fields['Telefone'] ) ){ $priorPhones['Telefone']= $fields['Telefone'];unset($fields['Telefone']); }

        foreach ($priorPhones as $k => $phone) {
            $response[] = '
                <tr>
                    <td align="left" valign="top" style="
                        border-top:1px solid #dfdfdf;
                        font-family:Arial, Helvetica, sans-serif;
                        font-size:14px;
                        color:#000;
                        padding:7px 5px 7px 0;
                    ">Telefone:</td>
                    <td align="left" valign="top" style="
                        border-top:1px solid #dfdfdf;
                        font-family:Arial, Helvetica, sans-serif;
                        font-size:14px;
                        color:#000;
                        padding:7px 0 7px 5px;
                    ">' . $phone . '</td>
                </tr>';
        }

        // Demais campos
        foreach ( $fields as $label => $value ) {
            $response[] = '
                <tr>
                    <td align="right" valign="top" style="
                        border-top:1px solid #dfdfdf;
                        font-family:Arial, Helvetica, sans-serif;
                        font-size:14px;
                        color:#000;
                        padding:7px 5px 7px 0;
                    ">' . $label . ':</td>
                    <td align="left" valign="top" style="
                        border-top:1px solid #dfdfdf;
                        font-family:Arial, Helvetica, sans-serif;
                        font-size:13px;
                        color:#000;
                        padding:7px 0 7px 5px;
                    ">' . $value . '</td>
                </tr>';
        }

        // E-mail HTML (idêntico ao seu layout)
        $message = '
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Formulário de Contato</title>
            </head>
            <body>
                <table width="50%" border="0" align="center" cellpadding="0" cellspacing="0">
                    <tr style="background-color: #ffffff;">
                        <td colspan="2" align="center" valign="top">
                            <p style="margin:10px; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:bold;">GuardianTech</p> 
                        </td>
                    </tr>
                    ' . implode( "\n", $response ) . '
                </table>
            </body>
            </html>';

        // -------------------------
        // Cabeçalhos / remetente
        // -------------------------
        // Garante compatibilidade se "Name" não existir (usa "Nome" ou fallback)
        $from_name_hdr  = '';
        if     ( isset($fields['Name']) ) { $from_name_hdr = strip_tags($fields['Name']); }
        elseif ( isset($fields['Nome']) ) { $from_name_hdr = strip_tags($fields['Nome']); }
        else                              { $from_name_hdr = 'Contato do Site'; }

        $from_email_hdr = $email_raw; // mantém seu comportamento (From = e-mail do usuário)

        if ( $enable_smtp === 'no' ) {

            // -------------------------
            // mail()
            // -------------------------
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            // Mantém seu formato original (From = usuário), pois você disse que "envia perfeitamente"
            $headers .= 'From: ' . $from_name_hdr . ' <' . $from_email_hdr . ">\r\n";
            // Também coloca Reply-To por segurança
            $headers .= 'Reply-To: ' . $from_email_hdr . "\r\n";

            if ( mail( $receiver_email, $subject, $message, $headers ) ) {
                echo '{ "alert": "alert-success", "message": "Sua mensagem foi enviada com sucesso!" }';
            } else {
                echo '{ "alert": "alert-danger", "message": "Erro: sua mensagem não pôde ser enviada!" }';
            }

        } else {

            // -------------------------
            // SMTP via PHPMailer
            // -------------------------
            require 'phpmailer/Exception.php';
            require 'phpmailer/PHPMailer.php';
            require 'phpmailer/SMTP.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer();
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'contato@guardiantech.site';
            $mail->Password   = 'Gu4rdMail!@';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            // Mantém seu comportamento original (From = usuário)
            $mail->setFrom( $from_email_hdr, $from_name_hdr );
            $mail->addReplyTo( $from_email_hdr, $from_name_hdr );

            // destinatário
            $mail->addAddress( $receiver_email, $receiver_name );

            $mail->Subject = $subject;
            $mail->isHTML( true );
            $mail->Body    = $message;

            if ( $mail->send() ) {
                echo '{ "alert": "alert-success", "message": "Sua mensagem foi enviada com sucesso!" }';
            } else {
                echo '{ "alert": "alert-danger", "message": "Erro: sua mensagem não pôde ser enviada!" }';
            }
        }

    }

} else {
    // Empty email
    echo '{ "alert": "alert-danger", "message": "Por favor, adicione um email!" }';
}
?>
