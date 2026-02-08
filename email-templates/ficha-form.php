<?php
// A verificação inicial foi alterada para `username` pois seu formulário de cadastro não possui um campo de e-mail.
if ( ! empty( $_POST['username'] ) ) {

    // Habilita / Desabilita SMTP
     $enable_smtp = 'yes'; // yes OU no. Note que o padrão foi alterado para 'yes' para usar sua configuração SMTP.

     // Endereço de e-mail do destinatário
     $receiver_email = 'contato@guardiantech.site';

     // Nome do destinatário para o e-mail SMTP
     $receiver_name  = 'GuardianTech';

     // Assunto do e-mail
     $subject = "Novo Cadastro - Ficha de Cliente"; // Assunto atualizado

     // Como o formulário não coleta o e-mail do cliente, o remetente será fixo.
     $from_email = 'no-reply@guardiantech.site'; // Use um e-mail válido do seu domínio.
     $from_name = 'Formulário de Cadastro GuardianTech';
     
     // (O código de validação do reCaptcha foi omitido por brevidade — mantenha o seu aqui)

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {

        // Captura IP e navegador enviados pelo JS (assumindo que você ainda os envia)
        $usuario_ip         = ! empty( $_POST['usuario_ip'] ) ? $_POST['usuario_ip'] : 'Não disponível';
        $usuario_navegador  = ! empty( $_POST['usuario_navegador'] ) ? $_POST['usuario_navegador'] : 'Não disponível';

        $submits  = $_POST;

        // Mapeamento dos campos do formulário para rótulos amigáveis no e-mail
        $field_labels = [
            // Dados pessoais e de contato
            'tipo_cliente' => 'Tipo de Cliente',
            'cpf_cnpj' => 'CPF/CNPJ',
            'rg_ie' => 'RG/IE',
            'data_nascimento' => 'Data de Nascimento',
            'celular_principal' => 'Celular Principal',
            'username' => 'Nome de Usuário',
            
            // Pessoa de contato (emergência)
            'contato_nome' => 'Nome (Contato Emergência)',
            'contato_relacao' => 'Parentesco / Cargo (Contato Emergência)',
            'contato_celular' => 'Celular (Contato Emergência)',
            'contato_fixo' => 'Telefone Fixo (Contato Emergência)',

            // Dados de endereço
            'cep' => 'CEP (Endereço Principal)',
            'rua' => 'Rua (Endereço Principal)',
            'numero' => 'Número (Endereço Principal)',
            'complemento' => 'Complemento (Endereço Principal)',
            'bairro' => 'Bairro (Endereço Principal)',
            'cidade_estado' => 'Cidade - UF (Endereço Principal)',
            'observacoes_endereco' => 'Observações (Endereço Principal)',
            
            // Dados do veículo
            'veiculo1_tipo' => 'Tipo do Veículo 1',
            'veiculo1_combustivel' => 'Combustível do Veículo 1',
            'veiculo1_placa' => 'Placa do Veículo 1',
            'veiculo1_marca' => 'Marca/Montadora do Veículo 1',
            'veiculo1_modelo' => 'Modelo do Veículo 1',

            // Endereço de instalação
            'cepveiculo1' => 'CEP (Instalação Veículo 1)',
            'ruaveiculo1' => 'Rua (Instalação Veículo 1)',
            'numeroveiculo1' => 'Número (Instalação Veículo 1)',
            'complementoveiculo1' => 'Complemento (Instalação Veículo 1)',
            'bairroveiculo1' => 'Bairro (Instalação Veículo 1)',
            'cidade_estadoveiculo1' => 'Cidade - UF (Instalação Veículo 1)',
            'observacoes_enderecoveiculo1' => 'Observações (Instalação Veículo 1)',
            
            // Informações de pagamento
            'pag_instalacao' => 'Pagamento da Instalação',
            'dia_mensalidade' => 'Dia de Vencimento da Mensalidade',
            'pag_mensalidade' => 'Pagamento da Mensalidade',
            
            // Termos de Uso
            'termosUso' => 'Aceite dos Termos de Uso'
        ];

        // Coleta e sanitiza todos os campos com base no mapeamento
        $fields = array();
        foreach ( $field_labels as $key => $label ) {
            if ( isset( $submits[$key] ) ) {
                $value = $submits[$key];

                // Tratamento especial para o checkbox
                if ( $key === 'termosUso' ) {
                    $value = ( $value === 'on' ) ? 'Sim' : 'Não';
                }

                if ( is_array( $value ) ) {
                    $value = implode( ', ', $value );
                }
                $fields[ $label ] = nl2br( filter_var( $value, FILTER_SANITIZE_SPECIAL_CHARS ) );
            }
        }

        // Adiciona IP e navegador ao conjunto de campos
        $fields['IP do Usuário']        = nl2br( filter_var( $usuario_ip, FILTER_SANITIZE_SPECIAL_CHARS ) );
        $fields['Navegador do Usuário'] = nl2br( filter_var( $usuario_navegador, FILTER_SANITIZE_SPECIAL_CHARS ) );

        // Constrói as linhas da tabela no e-mail
        $response = array();
        foreach ( $fields as $label => $value ) {
            $response[] = '
                <tr>
                    <td align="right" valign="top" style="
                        border-top:1px solid #dfdfdf;
                        font-family:Arial, Helvetica, sans-serif;
                        font-size:14px;
                        color:#000;
                        padding:7px 5px 7px 0;
                    "><strong>' . $label . ':</strong></td>
                    <td align="left" valign="top" style="
                        border-top:1px solid #dfdfdf;
                        font-family:Arial, Helvetica, sans-serif;
                        font-size:13px;
                        color:#000;
                        padding:7px 0 7px 5px;
                    ">' . $value . '</td>
                </tr>';
        }

        // Junta tudo em um e-mail HTML completo
        $message = '
            <html>
            <head>
                <title>' . $subject . '</title>
            </head>
            <body>
                <table width="600" border="0" align="center" cellpadding="0" cellspacing="0" style="border:1px solid #dfdfdf;">
                    <tr style="background-color: #f8f8f8;">
                        <td colspan="2" align="center" valign="top">
                            <h2 style="margin:10px; font-family:Arial, Helvetica, sans-serif; font-size:18px; font-weight:bold; color:#333;">' . $subject . '</h2>
                        </td>
                    </tr>
                    ' . implode( "\n", $response ) . '
                    <tr>
                        <td colspan="2" align="center" valign="top" style="padding:15px; font-family:Arial, Helvetica, sans-serif; font-size:12px; color:#999;">
                            Este email foi gerado automaticamente pelo formulário de cadastro do site.
                        </td>
                    </tr>
                </table>
            </body>
            </html>';

        if ( $enable_smtp === 'no' ) {

            // Envio simples via mail()
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= 'From: ' . $from_name . ' <' . $from_email . ">\r\n";

            if ( mail( $receiver_email, $subject, $message, $headers ) ) {
                // sucesso
                echo '{ "alert": "alert-success", "message": "Sua ficha de cadastro foi enviada com sucesso!" }';
            } else {
                // falha
                echo '{ "alert": "alert-danger", "message": "Erro: Sua ficha de cadastro não pôde ser enviada!" }';
         .   }

        } else {

            // Envio via SMTP (PHPMailer)
            require 'phpmailer/Exception.php';
            require 'phpmailer/PHPMailer.php';
            require 'phpmailer/SMTP.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer();
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';       // seu host SMTP
            $mail->SMTPAuth   = true;
            $mail->Username   = 'contato@guardiantech.site';// seu usuário SMTP
            $mail->Password   = 'Gu4rdMail!@';              // sua senha SMTP
            $mail->SMTPSecure = 'tls';                      // tls ou ssl conforme seu provedor
            $mail->Port       = 587;                        // porta (587 para TLS)
            $mail->CharSet    = 'UTF-8';

            // remetente
            $mail->setFrom( $from_email, $from_name );

            // destinatário
            $mail->addAddress( $receiver_email, $receiver_name );

            $mail->Subject = $subject;
            $mail->isHTML( true );
            $mail->Body    = $message;

            if ( $mail->send() ) {
                echo '{ "alert": "alert-success", "message": "Sua ficha de cadastro foi enviada com sucesso!" }';
            } else {
                echo '{ "alert": "alert-danger", "message": "Erro: Sua ficha de cadastro não pôde ser enviada! Erro: ' . $mail->ErrorInfo . '" }';
            }
        }

    }

} else {
    // Caso o nome de usuário não tenha sido preenchido
    echo '{ "alert": "alert-danger", "message": "Por favor, preencha o nome de usuário para enviar o formulário!" }';
}
?>