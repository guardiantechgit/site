<?php
// Captura de metadados do cliente

// 1. Definir fuso horário
date_default_timezone_set('America/Sao_Paulo');

// 2. Data e hora do envio
$timestamp = date('Y-m-d H:i:s');

// 3. Captura do IP real (inclui X-Forwarded-For se aplicável)
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($ips[0]);
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}

// 4. Cabeçalhos e User-Agent
$userAgent      = $_SERVER['HTTP_USER_AGENT']      ?? '';
$referer        = $_SERVER['HTTP_REFERER']         ?? '';
$acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$host           = $_SERVER['HTTP_HOST']            ?? '';
$remotePort     = $_SERVER['REMOTE_PORT']          ?? '';
$serverProtocol = $_SERVER['SERVER_PROTOCOL']      ?? '';

// 5. Navegador e plataforma (requer browscap em php.ini)
$browserInfo = get_browser(null, true);
$browser     = $browserInfo['browser']  ?? '';
$version     = $browserInfo['version']  ?? '';
$platform    = $browserInfo['platform'] ?? '';

// 6. Geolocalização aproximada via IP (ip-api.com)
$geoJson = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,lat,lon,isp");
$geoData = $geoJson ? json_decode($geoJson, true) : [];
if (!empty($geoData['status']) && $geoData['status'] === 'success') {
    $geo = [
        'country'   => $geoData['country'],
        'region'    => $geoData['regionName'],
        'city'      => $geoData['city'],
        'latitude'  => $geoData['lat'],
        'longitude' => $geoData['lon'],
        'isp'       => $geoData['isp'],
    ];
} else {
    $geo = [
        'country'   => null,
        'region'    => null,
        'city'      => null,
        'latitude'  => null,
        'longitude' => null,
        'isp'       => null,
    ];
}

// 7. Captura dos campos do formulário
$formData = [];
foreach ($_POST as $field => $value) {
    $formData[$field] = trim($value);
}

// 8. Montagem dos arrays finais
$meta = [
    'timestamp'       => $timestamp,
    'ip'              => $ip,
    'user_agent'      => $userAgent,
    'browser'         => "$browser $version",
    'platform'        => $platform,
    'referer'         => $referer,
    'accept_language' => $acceptLanguage,
    'host'            => $host,
    'remote_port'     => $remotePort,
    'protocol'        => $serverProtocol,
] + $geo;

// Agora você tem dois arrays prontos para uso:
//  - $formData contém todos os campos enviados pelo formulário.
//  - $meta     contém todos os metadados capturados do cliente.
?>


<!doctype html>
<html class="no-js" lang="pt-br">
   <head>
      <title>GuardianTech - Rastreamento - Ficha de Cadastro</title>
      <meta charset="utf-8">
      <meta http-equiv="X-UA-Compatible" content="IE=edge" />
      <meta name="author" content="GuardianTech">
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <meta name="description" content="Especializada em rastreamento veicular e segurança eletrônica, a GuardianTech protege seu patrimônio com tecnologia de ponta para carros, motos, caminhões, cargas, barcos e tratores. Soluções completas para condomínios e empresas.">
      <meta name="keywords" content="rastreadores, bragança paulista, socorro, serra negra, atibaia, zona rural, tratores, caminhão, carga, rastreamento veicular, rastreador de carro, rastreador de moto, rastreador de caminhão, rastreador de barco, rastreamento de cargas, rastreador agrícola, GuardianTech, segurança eletrônica, CFTV, controle de acesso, automação residencial, redes cabeadas, redes Wi-Fi, vigilância, segurança patrimonial, tecnologia para condomínios e empresas">
      <meta name="robots" content="index, follow">
      <meta name="language" content="Portuguese">
      <meta name="revisit-after" content="30 days">
      <meta name="apple-mobile-web-app-title" content="GuardianTech">
      <link rel="canonical" href="https://guardiantech.site" />
      <!-- favicon icons -->
      <link rel="icon" type="image/png" href="../favicon/favicon-96x96.png" sizes="96x96" />
      <link rel="icon" type="image/svg+xml" href="../favicon/favicon.svg" />
      <link rel="shortcut icon" href="../favicon/favicon.ico" />
      <link rel="apple-touch-icon" sizes="180x180" href="../favicon/apple-touch-icon.png" />
      <link rel="manifest" href="../favicon/site.webmanifest" />
      <!-- Open Graph Meta Tags -->
      <meta property="og:title" content="GuardianTech - Ficha de Cadastro" />
      <meta property="og:type" content="website" />
      <meta property="og:url" content="https://guardiantech.site" />
      <meta property="og:image" content="https://guardiantech.site/images/og-index.jpg" />
      <meta property="og:image:alt" content="GuardianTech - Rastreamento e Segurança" />
      <meta property="og:description" content="Solicite o serviço de rastreamento" />
      <meta property="og:site_name" content="GuardianTech" />
      <meta property="og:image:width" content="1200" />
      <meta property="og:image:height" content="630" />
      <meta property="og:image:type" content="image/jpeg" />
      <!-- Twitter Card Meta Tags -->
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content="GuardianTech - Contato" />
      <meta name="twitter:description" content="Rastreie e proteja seus veículos, cargas e máquinas com a GuardianTech. Soluções em segurança, automação e tecnologia para condomínios e empresas." />
      <meta name="twitter:image" content="https://guardiantech.site/images/og-contato.jpg" />
      <meta name="twitter:image:alt" content="Imagem de rastreamento de veículos e segurança eletrônica" />
      <!-- google fonts preconnect -->
      <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <!-- style sheets and font icons -->
      <link rel="stylesheet" href="../css/vendors.min.css" />
      <link rel="stylesheet" href="../css/icon.min.css" />
      <link rel="stylesheet" href="../css/style.css" />
      <link rel="stylesheet" href="../css/responsive.css" />
      <link rel="stylesheet" href="../css/custom.css" />
   </head>
   
   <body data-mobile-nav-style="classic">
      <!-- start header -->
      <?php include '../includes/header.php'; ?>
      <!-- end header --> 
      <section class="page-title-big-typography bg-dark-gray ipad-top-space-margin" data-parallax-background-ratio="0.5" style="background-image: url(../images/title-contato.jpg)">
         <div class="opacity-extra-medium bg-dark-slate-blue"></div>
         <div class="container">
            <div class="row align-items-center justify-content-center extra-small-screen">
               <div class="col-12 position-relative text-center page-title-extra-large">
                  <h1 class="m-auto text-white text-shadow-double-large fw-500 ls-minus-3px xs-ls-minus-2px" data-anime='{ "translateY": [15, 0], "opacity": [0,1], "duration": 600, "delay": 0, "staggervalue": 300, "easing": "easeOutQuad" }'>Cadastro para rastreamento</h1>
               </div>
               <div class="down-section text-center" data-anime='{ "translateY": [-15, 0], "opacity": [0,1], "duration": 600, "delay": 0, "staggervalue": 300, "easing": "easeOutQuad" }'>
                  <a href="#down-section" aria-label="scroll down" class="section-link">
                     <div class="d-flex justify-content-center align-items-center mx-auto rounded-circle fs-30 text-white">
                        <i class="feather icon-feather-chevron-down"></i>
                     </div>
                  </a>
               </div>
            </div>
         </div>
      </section>
      <!-- end page title -->
      <!-- start section -->
      <section class="ps-12 pe-12 xl-ps-10 xl-pe-10 lg-ps-3 lg-pe-3 half-section p-2" id="down-section">
         <div class="container-fluid">
            <div class="row justify-content-center mb-3">
               <div class="col-xl-12 col-lg-12 col-sm-12 text-center pt-20px" data-anime='{ "el": "childs", "translateY": [30, 0], "opacity": [0,1], "duration": 600, "delay": 0, "staggervalue": 300, "easing": "easeOutQuad" }'>
                  <h5 class="alt-font text-dark-gray fw-600 ls-minus-2px">Preencha com atenção os campos abaixo</h5>
               </div>
            </div>
            <div class="container text-dark-gray">
               <div class="row justify-content-center">
                  <div class="col-lg-10 col-md-12">
<form id="cadastroForm" action="https://guardiantech.site/email-templates/ficha-form.php" method="post" class="row row-cols-1 row-cols-md-2">
  <div class="col-md-12 col-lg-12 col-sm-12">
    <h5 class="text-center mb-4">Dados pessoais e de contato</h5>
  </div>
  <!-- DADOS PESSOAIS E DE CONTATO -->
  <div class="col-md-6 mb-3">
    <label for="tipo_cliente" class="form-label">Tipo de Cliente*</label>
    <select id="tipo_cliente" name="tipo_cliente" class="form-select required" required>
      <option value="">Selecione...</option>
      <option value="pf">Pessoa Física</option>
      <option value="pj">Pessoa Jurídica</option>
    </select>
  </div>
  <div class="col-md-6 mb-3">
    <label for="cpf_cnpj" class="form-label">CPF/CNPJ*</label>
    <input
      id="cpf_cnpj"
      type="text"
      name="cpf_cnpj"
      class="form-control required"
      placeholder="Digite CPF ou CNPJ"
      maxlength="14"
      pattern="\d{11,14}"
      title="Apenas números (11 a 14 dígitos)"
      required
      oninput="this.value = this.value.replace(/\D/g,'');"
    />
  </div>
  <div class="col-md-6 mb-3">
    <label for="rg_ie" class="form-label">RG/IE*</label>
    <input
      id="rg_ie"
      type="text"
      name="rg_ie"
      class="form-control"
      placeholder="Digite RG ou IE"
      maxlength="10"
      pattern="\d{1,10}"
      title="Apenas números (até 10 dígitos)"
      required
      oninput="this.value = this.value.replace(/\D/g,'');"
    />
  </div>
  <div class="col-md-6 mb-3">
    <label for="data_nascimento" class="form-label">Data de Nascimento*</label>
    <input
      id="data_nascimento"
      type="date"
      name="data_nascimento"
      class="form-control"
      required
    />
  </div>
  <div class="col-md-6 mb-3">
    <label for="celular_principal" class="form-label">Celular Principal*</label>
    <input
      id="celular_principal"
      type="tel"
      name="celular_principal"
      class="form-control required"
      placeholder="(00) 00000-0000"
      title="Apenas números (10 ou 11 dígitos)"
      required
      oninput="this.value = this.value.replace(/\D/g,'');"
    />
  </div>
  <div class="col-md-6 mb-3">
    <label for="username" class="form-label">Nome de Usuário*</label>
    <input
      id="username"
      type="text"
      name="username"
      class="form-control required"
      placeholder="Somente letras e números, minúsculo sem espaços"
      pattern="[a-z0-9]+"
      title="Somente letras minúsculas e números, sem espaços"
      oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9]/g, '');"
      required
    />
  </div>

  <div class="col-md-12 col-lg-12 col-sm-12">
    <h5 class="text-center">Pessoa de contato (emergência)</h5>
  </div>
  <div class="col-md-12 col-lg-12 col-sm-12">
    <small class="d-block mb-3 text-center text-muted">
      Pessoa de confiança para contato em caso de emergência
    </small>
  </div>
  <div class="col-md-6 mb-3">
    <label for="contato_nome" class="form-label">Nome completo*</label>
    <input
      id="contato_nome"
      type="text"
      name="contato_nome"
      class="form-control"
      placeholder="Nome completo"
      required
    />
  </div>
  <div class="col-md-6 mb-3">
    <label for="contato_relacao" class="form-label">Parentesco / Cargo*</label>
    <input
      id="contato_relacao"
      type="text"
      name="contato_relacao"
      class="form-control"
      placeholder="Parentesco / Cargo"
      required
    />
  </div>
  <div class="col-md-6 mb-3">
    <label for="contato_celular" class="form-label">Celular*</label>
    <input
      id="contato_celular"
      type="tel"
      name="contato_celular"
      class="form-control"
      placeholder="Celular"
      title="Apenas números (10 ou 11 dígitos)"
      required
      oninput="this.value = this.value.replace(/\D/g,'');"
    />
  </div>
  <div class="col-md-6 mb-6">
    <label for="contato_fixo" class="form-label">Telefone fixo</label>
    <input
      id="contato_fixo"
      type="tel"
      name="contato_fixo"
      class="form-control"
      placeholder="Telefone fixo"
      oninput="this.value = this.value.replace(/\D/g,'');"
    />
  </div>

  <!-- DADOS DE ENDEREÇO -->
  <div class="col-md-12 col-lg-12 col-sm-12">
    <h5 class="text-center">Dados de endereço</h5>
  </div>
  <div class="col-md-4 mb-3">
    <label for="cep" class="form-label">CEP*</label>
    <input
      id="cep"
      type="text"
      name="cep"
      class="form-control"
      placeholder="CEP"
      maxlength="10"
      pattern="\d{2}\.\d{3}-\d{3}"
      title="Formato 11.111-111"
      required
      oninput="maskCEP(this)"
    />
  </div>
  <div class="col-md-8 mb-3">
    <label for="rua" class="form-label">Rua*</label>
    <input
      id="rua"
      type="text"
      name="rua"
      class="form-control"
      placeholder="Rua"
      required
    />
  </div>
  <div class="col-md-2 mb-3">
    <label for="numero" class="form-label">Número*</label>
    <input
      id="numero"
      type="text"
      name="numero"
      class="form-control"
      placeholder="Número"
      required
      oninput="this.value = this.value.replace(/\D/g,'').slice(0,5);"
      pattern="\d{1,5}"
      title="Apenas números (até 5 dígitos)"
    />
  </div>
  <div class="col-md-4 mb-3">
    <label for="complemento" class="form-label">Complemento</label>
    <input
      id="complemento"
      type="text"
      name="complemento"
      class="form-control"
      placeholder="Complemento"
    />
  </div>
  <div class="col-md-6 mb-3">
    <label for="bairro" class="form-label">Bairro*</label>
    <input
      id="bairro"
      type="text"
      name="bairro"
      class="form-control"
      placeholder="Bairro"
      required
    />
  </div>
  <div class="col-md-12 mb-3">
    <label for="cidade_estado" class="form-label">Cidade - UF*</label>
    <select id="cidade_estado" name="cidade_estado" class="form-select required" required>
      <option value="">Selecione a Cidade - UF</option>
      <option value="Bragança Paulista - SP">Bragança Paulista - SP</option>
      <option value="Socorro - SP">Socorro - SP</option>
      <option value="Atibaia - SP">Atibaia - SP</option>
      <option value="Pinhalzinho - SP">Pinhalzinho - SP</option>
      <option value="Pedra Bela - SP">Pedra Bela - SP</option>
      <option value="Águas de Lindóia - SP">Águas de Lindóia - SP</option>
      <option value="Monte Alegre do Sul - SP">Monte Alegre do Sul - SP</option>
      <option value="Lindóia - SP">Lindóia - SP</option>
      <option value="Piracaia - SP">Piracaia - SP</option>
      <option value="Jarinu - SP">Jarinu - SP</option>
      <option value="Itatiba - SP">Itatiba - SP</option>
      <option value="Morungaba - SP">Morungaba - SP</option>
      <option value="Vargem - SP">Vargem - SP</option>
      <option value="Joanópolis - SP">Joanópolis - SP</option>
      <option value="Extrema - MG">Extrema - MG</option>
      <option value="Bueno Brandão - MG">Bueno Brandão - MG</option>
      <option value="Monte Verde - MG">Monte Verde - MG</option>
      <option value="Ouro Fino - MG">Ouro Fino - MG</option>
    </select>
  </div>
  <div class="col-md-12 mb-6">
    <label for="observacoes_endereco" class="form-label">Observações</label>
    <textarea
      id="observacoes_endereco"
      name="observacoes_endereco"
      rows="2"
      class="form-control"
      placeholder="Observações"
    ></textarea>
  </div>

  <!-- DADOS DO VEÍCULO -->
  <div class="col-md-12 col-lg-12 col-sm-12">
    <h5 class="text-center mb-4">Dados do veículo</h5>
  </div>
  <!-- Veículo 1 -->
  <div class="col-md-4 mb-3">
    <label for="veiculo1_tipo" class="form-label">Tipo*</label>
    <select id="veiculo1_tipo" name="veiculo1_tipo" class="form-select" required>
      <option value="">Tipo</option>
      <option value="moto">Moto</option>
      <option value="carro">Carro</option>
      <option value="caminhonete">Caminhonete</option>
    </select>
  </div>
  <div class="col-md-4 mb-3">
    <label for="veiculo1_combustivel" class="form-label">Combustível*</label>
    <select id="veiculo1_combustivel" name="veiculo1_combustivel" class="form-select" required>
      <option value="">Combustível</option>
      <option>Gasolina</option>
      <option>Álcool</option>
      <option>Flex</option>
      <option>Diesel</option>
      <option>Híbrido</option>
      <option>Elétrico</option>
    </select>
  </div>
  <div class="col-md-4 mb-3">
    <label for="veiculo1_placa" class="form-label">Placa*</label>
    <input
      id="veiculo1_placa"
      type="text"
      name="veiculo1_placa"
      class="form-control"
      placeholder="Placa"
      maxlength="8"
      pattern="^[A-Za-z]{3}-\d{4}$|^[A-Za-z]{3}-\d[A-Za-z]\d{2}$"
      title="AAA-1111 ou AAA-1A11"
      required
      oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g,''); maskPlaca(this)"
    />
  </div>
  <div class="col-md-6 mb-3">
    <label for="veiculo1_marca" class="form-label">Marca/Montadora*</label>
    <input
      id="veiculo1_marca"
      type="text"
      name="veiculo1_marca"
      class="form-control"
      placeholder="Marca/Montadora"
      required
    />
  </div>
  <div class="col-md-6 mb-3">
    <label for="veiculo1_modelo" class="form-label">Modelo*</label>
    <input
      id="veiculo1_modelo"
      type="text"
      name="veiculo1_modelo"
      class="form-control"
      placeholder="Modelo"
      required
    />
  </div>

  <!-- ENDEREÇO DA INSTALAÇÃO -->
  <div class="col-md-12 col-lg-12 col-sm-12">
    <h6 class="text-center">Endereço da instalação deste veículo</h6>
  </div>
  <div class="col-md-12 mb-4 mt-1 text-center">
    <button
      type="button"
      id="copyAddressBtn"
      class="btn btn-medium btn-rounded btn-transparent-dark-gray d-table d-lg-inline-block lg-mb-12px md-mx-auto"
    >
      <i class="feather icon-feather-copy me-1"></i>
      Copiar endereço acima
    </button>
  </div>
  <div class="col-md-4 mb-3">
    <label for="cepveiculo1" class="form-label">CEP*</label>
    <input
      id="cepveiculo1"
      type="text"
      name="cepveiculo1"
      class="form-control"
      placeholder="CEP"
      maxlength="10"
      pattern="\d{2}\.\d{3}-\d{3}"
      title="Formato 11.111-111"
      required
      oninput="maskCEP(this)"
    />
  </div>
  <div class="col-md-8 mb-3">
    <label for="ruaveiculo1" class="form-label">Rua*</label>
    <input
      id="ruaveiculo1"
      type="text"
      name="ruaveiculo1"
      class="form-control"
      placeholder="Rua"
      required
    />
  </div>
  <div class="col-md-2 mb-3">
    <label for="numeroveiculo1" class="form-label">Número*</label>
    <input
      id="numeroveiculo1"
      type="text"
      name="numeroveiculo1"
      class="form-control"
      placeholder="Número"
      required
      oninput="this.value = this.value.replace(/\D/g,'').slice(0,5);"
      pattern="\d{1,5}"
      title="Apenas números (até 5 dígitos)"
    />
  </div>
  <div class="col-md-4 mb-3">
    <label for="complementoveiculo1" class="form-label">Complemento</label>
    <input
      id="complementoveiculo1"
      type="text"
      name="complementoveiculo1"
      class="form-control"
      placeholder="Complemento"
    />
  </div>
  <div class="col-md-6 mb-3">
    <label for="bairroveiculo1" class="form-label">Bairro*</label>
    <input
      id="bairroveiculo1"
      type="text"
      name="bairroveiculo1"
      class="form-control"
      placeholder="Bairro"
      required
    />
  </div>
  <div class="col-md-12 mb-3">
    <label for="cidade_estadoveiculo1" class="form-label">Cidade - UF*</label>
    <select id="cidade_estadoveiculo1" name="cidade_estadoveiculo1" class="form-select required" required>
      <option value="">Selecione a Cidade - UF</option>
      <option value="Bragança Paulista - SP">Bragança Paulista - SP</option>
      <option value="Socorro - SP">Socorro - SP</option>
      <option value="Atibaia - SP">Atibaia - SP</option>
      <option value="Pinhalzinho - SP">Pinhalzinho - SP</option>
      <option value="Pedra Bela - SP">Pedra Bela - SP</option>
      <option value="Águas de Lindóia - SP">Águas de Lindóia - SP</option>
      <option value="Monte Alegre do Sul - SP">Monte Alegre do Sul - SP</option>
      <option value="Lindóia - SP">Lindóia - SP</option>
      <option value="Piracaia - SP">Piracaia - SP</option>
      <option value="Jarinu - SP">Jarinu - SP</option>
      <option value="Itatiba - SP">Itatiba - SP</option>
      <option value="Morungaba - SP">Morungaba - SP</option>
      <option value="Vargem - SP">Vargem - SP</option>
      <option value="Joanópolis - SP">Joanópolis - SP</option>
      <option value="Extrema - MG">Extrema - MG</option>
      <option value="Bueno Brandão - MG">Bueno Brandão - MG</option>
      <option value="Monte Verde - MG">Monte Verde - MG</option>
      <option value="Ouro Fino - MG">Ouro Fino - MG</option>
    </select>
  </div>
  <div class="col-md-12 mb-6">
    <label for="observacoes_enderecoveiculo1" class="form-label">Observações</label>
    <textarea
      id="observacoes_enderecoveiculo1"
      name="observacoes_enderecoveiculo1"
      rows="2"
      class="form-control"
      placeholder="Observações"
    ></textarea>
  </div>

  <!-- INFORMAÇÕES DE PAGAMENTO -->
  <div class="col-md-12 col-lg-12 col-sm-12">
    <h5 class="text-center mb-4">Informações de Pagamento</h5>
  </div>
  <div class="col-md-5 mb-3">
    <label for="pag_instalacao" class="form-label">Forma de pagamento da Instalação*</label>
    <select id="pag_instalacao" name="pag_instalacao" class="form-select" required>
      <option value="">Selecione...</option>
      <option value="pix">PIX</option>
      <option value="cartao">Cartão</option>
      <option value="boleto">Boleto (instalação após compensação)</option>
    </select>
  </div>
  <div class="col-md-4 mb-3">
    <label for="dia_mensalidade" class="form-label">Dia de vencimento da mensalidade*</label>
    <select id="dia_mensalidade" name="dia_mensalidade" class="form-select" required>
      <option value="">Selecione...</option>
      <option>10</option>
      <option>15</option>
      <option>20</option>
    </select>
  </div>
  <div class="col-md-3 mb-3">
    <label for="pag_mensalidade" class="form-label">Forma de Pagamento*</label>
    <select id="pag_mensalidade" name="pag_mensalidade" class="form-select" required>
      <option value="pix">PIX</option>
      <option value="boleto">Boleto</option>
    </select>
  </div>

  <!-- Termos de Uso -->
  <div class="col-md-12 mb-3">
    <label for="termosUso" class="form-label">Li e aceito os termos e cláusulas de contratação*</label>
    <textarea id="termos" name="termos" class="form-control" readonly>Texto do contrato.</textarea>
    <input
      id="termosUso"
      type="checkbox"
      name="termosUso"
      class="form-check-input required"
      required
    />
  </div>

  <!-- Botão Enviar -->
  <div class="col-md-12 text-center mt-4">
    <button id="submitBtn" type="submit" class="btn btn-large btn-base-color btn-rounded">
      Enviar Ficha
    </button>
  </div>
</form>


               </div>
            </div>
         </div>
      </section>
      <!-- end section --> 
      <!-- start footer -->
      <?php include '../includes/footer.php'; ?>
      <!-- end footer -->
      <!-- start scroll progress -->
      <div class="scroll-progress d-none d-xxl-block">
         <a href="#" class="scroll-top" aria-label="scroll">
         <span class="scroll-text">Rolagem do site</span><span class="scroll-line"><span class="scroll-point"></span></span>
         </a>
      </div>
      <!-- end scroll progress -->
      <!-- javascript libraries -->
<script>
// Máscara de CEP no formato 11.111-111
function maskCEP(input) {
  let v = input.value.replace(/\D/g, '').slice(0, 8);
  if (v.length > 5) {
    v = v.replace(/^(\d{2})(\d{3})(\d{0,3}).*/, '$1.$2-$3');
  } else if (v.length > 2) {
    v = v.replace(/^(\d{2})(\d{0,3})/, '$1.$2');
  }
  input.value = v;
}

// Máscara de placa (AAA-1111 ou AAA-1A11)
function maskPlaca(input) {
  let v = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
  if (v.length > 3 && v[3] !== '-') {
    v = v.slice(0, 3) + '-' + v.slice(3);
  }
  input.value = v.slice(0, 8);
}

// Validação de termos e alerta no submit
document.getElementById('cadastroForm').addEventListener('submit', function(e) {
  const termosCheckbox = document.getElementById('termosUso');
  if (!termosCheckbox.checked) {
    e.preventDefault();
    alert('Confirme que leu e aceitou os termos e cláusulas de contratação');
  }
});
</script>

      <script>
         document.getElementById('copyAddressBtn').addEventListener('click', function(){
           // campos endereço principal
           const cep       = document.querySelector('input[name="cep"]').value;
           const rua       = document.querySelector('input[name="rua"]').value;
           const numero    = document.querySelector('input[name="numero"]').value;
           const comp      = document.querySelector('input[name="complemento"]').value;
           const bairro    = document.querySelector('input[name="bairro"]').value;
           const cidadeUF  = document.querySelector('select[name="cidade_estado"]').value;
           const obs       = document.querySelector('textarea[name="observacoes_endereco"]').value;
         
           // aplica nos campos do veículo
           document.querySelector('input[name="cepveiculo1"]').value             = cep;
           document.querySelector('input[name="ruaveiculo1"]').value            = rua;
           document.querySelector('input[name="numeroveiculo1"]').value         = numero;
           document.querySelector('input[name="complementoveiculo1"]').value    = comp;
           document.querySelector('input[name="bairroveiculo1"]').value         = bairro;
           document.querySelector('select[name="cidade_estadoveiculo1"]').value = cidadeUF;
           // copia observações
           document.querySelector('textarea[name="observacoes_enderecoveiculo1"]').value = obs;
         });
      </script>
      <script type="text/javascript" src="../js/jquery.js"></script>
      <script type="text/javascript" src="../js/vendors.min.js"></script>
      <script type="text/javascript" src="../js/main.js"></script>
   </body>
</html>