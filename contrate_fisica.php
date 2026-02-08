<?php
	/* ===== Dados coletados (server-side) ===== */
	date_default_timezone_set('America/Sao_Paulo');
	
	function gt_client_ip(): string {
	    // Cloudflare
	    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
	        return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
	    }
	    // Proxy / LB (best-effort)
	    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
	        $candidate = trim($parts[0]);
	        if (filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
	    }
	    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
	    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'Indisponível';
	}
	
	$gt_ip = gt_client_ip();
	$gt_server_dt = date('d/m/Y H:i:s');
	$gt_server_epoch_ms = (int) round(microtime(true) * 1000);
	?>
<?php
	ob_start();
	ini_set('display_errors', '0');
	
	/*
	  Estrutura de cupom (preparada para evoluir):
	  - code
	  - install_discount_enabled (bool)
	  - install_discount_mode ('percent'|'value')
	  - install_discount_value (numero)
	  - monthly_discount_enabled (bool)
	  - monthly_discount_mode ('percent'|'value')
	  - monthly_discount_value (numero)
	*/
	$GT_COUPONS = [
	  [
	    'code' => 'WESLAO',
	    'install_discount_enabled' => true,
	    'install_discount_mode' => 'percent',
	    'install_discount_value' => 10,
	    'monthly_discount_enabled' => false,
	    'monthly_discount_mode' => 'percent',
	    'monthly_discount_value' => 0,
	  ],
	  [
	    'code' => 'TRIPZERO19',
	    'install_discount_enabled' => true,
	    'install_discount_mode' => 'percent',
	    'install_discount_value' => 10,
	    'monthly_discount_enabled' => false,
	    'monthly_discount_mode' => 'percent',
	    'monthly_discount_value' => 0,
	  ],
	  [
	    'code' => 'DEMATEI',
	    'install_discount_enabled' => true,
	    'install_discount_mode' => 'percent',
	    'install_discount_value' => 10,
	    'monthly_discount_enabled' => false,
	    'monthly_discount_mode' => 'percent',
	    'monthly_discount_value' => 0,
	  ],
	  [
	    'code' => 'GARAGE62',
	    'install_discount_enabled' => true,
	    'install_discount_mode' => 'percent',
	    'install_discount_value' => 10,
	    'monthly_discount_enabled' => false,
	    'monthly_discount_mode' => 'percent',
	    'monthly_discount_value' => 0,
	  ],
	  [
	    'code' => 'MARCELINHO',
	    'install_discount_enabled' => true,
	    'install_discount_mode' => 'percent',
	    'install_discount_value' => 10,
	    'monthly_discount_enabled' => false,
	    'monthly_discount_mode' => 'percent',
	    'monthly_discount_value' => 0,
	  ],
	  [
	    'code' => 'NEICRAVEIRO',
	    'install_discount_enabled' => true,
	    'install_discount_mode' => 'percent',
	    'install_discount_value' => 10,
	    'monthly_discount_enabled' => false,
	    'monthly_discount_mode' => 'percent',
	    'monthly_discount_value' => 0,
	  ],
	  [
	    'code' => 'EDSON',
	    'install_discount_enabled' => true,
	    'install_discount_mode' => 'percent',
	    'install_discount_value' => 10,
	    'monthly_discount_enabled' => false,
	    'monthly_discount_mode' => 'percent',
	    'monthly_discount_value' => 0,
	  ],
	];
	
	function gt_brl($value) {
	  return number_format((float)$value, 2, ',', '.');
	}
	
	function gt_find_coupon($rawCode, $coupons) {
	  $code = strtoupper(trim((string)$rawCode));
	  if ($code === '') return null;
	
	  foreach ($coupons as $c) {
	    if (isset($c['code']) && strtoupper((string)$c['code']) === $code) return $c;
	  }
	  return null;
	}
	
	function gt_apply_discount($amount, $enabled, $mode, $value, &$discountLabel = null) {
	  $amount = (float)$amount;
	  if (!$enabled) {
	    $discountLabel = null;
	    return $amount;
	  }
	
	  $mode = (string)$mode;
	  $value = (float)$value;
	
	  $final = $amount;
	
	  if ($mode === 'percent') {
	    $final = $amount * (1 - ($value / 100.0));
	    $discountLabel = rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') . '%';
	  } elseif ($mode === 'value') {
	    $final = $amount - $value;
	    $discountLabel = 'R$ ' . gt_brl($value);
	  } else {
	    $discountLabel = null;
	    return $amount;
	  }
	
	  if ($final < 0) $final = 0;
	  return $final;
	}
	
	function gt_compute_quote($vehicleType, $remoteBlocking, $coupon) {
	  $heavyTypes = ['caminhao', 'trator_maquina', 'embarcacao', 'aeronave'];
	
	  $vt = (string)$vehicleType;
	  $rb = (string)$remoteBlocking;
	
	// Se for veículo leve e o usuário ainda não escolheu, assume "nao" para atualizar automaticamente
	if (!in_array($vt, $heavyTypes, true) && $rb === '') {
	 $rb = 'sim';
	}
	
	
	  $plan = '—';
	  $monthlyLabel = '—';
	  $installLabel = '—';
	
	  $installAmountBase = null;
	  $installPrefix = '';
	  $couponLine = null;
	
	  if ($vt === '') {
	    return [
	      'plan' => $plan,
	      'monthly_label' => $monthlyLabel,
	      'install_label' => $installLabel,
	      'coupon_line' => null,
	    ];
	  }
	
	  if (in_array($vt, $heavyTypes, true)) {
	    $plan = 'GuardianHeavy';
	    $monthlyLabel = 'R$ ' . gt_brl(68.90);
	    $installAmountBase = 150.00;
	    $installPrefix = 'A partir de ';
	  } else {
	    if ($rb === 'sim') {
	      $plan = 'GuardianSecure';
	      $monthlyLabel = 'R$ ' . gt_brl(64.90);
	    } elseif ($rb === 'nao') {
	      $plan = 'GuardianEssential';
	      $monthlyLabel = 'R$ ' . gt_brl(58.90);
	    } else {
	      $plan = '—';
	      $monthlyLabel = '—';
	    }
	
	    $installAmountBase = 120.00;
	    $installPrefix = '';
	  }
	
	  // Desconto (por enquanto só instalação; preparado para evoluir)
	  $installFinal = $installAmountBase;
	
	  if ($coupon) {
	    $discountLabel = null;
	
	    $installFinal = gt_apply_discount(
	      $installAmountBase,
	      !empty($coupon['install_discount_enabled']),
	      $coupon['install_discount_mode'] ?? '',
	      $coupon['install_discount_value'] ?? 0,
	      $discountLabel
	    );
	
	    // Importante: aqui NÃO escrevemos "Cupom ... aplicado", porque o label no HTML já é "Cupom:"
	    if (!empty($coupon['install_discount_enabled']) && $discountLabel) {
	      $couponLine = '<strong>' . htmlspecialchars($coupon['code']) . '</strong> — desconto na instalação de <strong>' . htmlspecialchars($discountLabel) . '</strong>.';
	    }
	  }
	
	  $installLabel = $installPrefix . 'R$ ' . gt_brl($installFinal);
	
	  return [
	    'plan' => $plan,
	    'monthly_label' => $monthlyLabel,
	    'install_label' => $installLabel,
	    'coupon_line' => $couponLine,
	  ];
	}
	
	/* =========================================================
	   ENDPOINT AJAX (MESMA PAGINA)
	========================================================= */
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gt_ajax']) && $_POST['gt_ajax'] === '1') {
	  if (ob_get_length()) ob_clean();
	  header('Content-Type: application/json; charset=utf-8');
	
	  $action = isset($_POST['gt_action']) ? (string)$_POST['gt_action'] : '';
	
	  if ($action === 'validate_coupon') {
	    $raw = $_POST['coupon'] ?? '';
	    $code = strtoupper(trim((string)$raw));
	
	    if ($code === '') {
	      echo json_encode([
	        'ok' => true,
	        'coupon' => [
	          'valid' => false,
	          'type' => 'warning',
	          'message' => 'Digite um cupom para validar.',
	        ]
	      ]);
	      exit;
	    }
	
	    $coupon = gt_find_coupon($code, $GT_COUPONS);
	
	    if (!$coupon) {
	      echo json_encode([
	        'ok' => true,
	        'coupon' => [
	          'valid' => false,
	          'type' => 'danger',
	          'message' => 'Cupom <strong>' . htmlspecialchars($code) . '</strong> inválido.',
	        ]
	      ]);
	      exit;
	    }
	
	    echo json_encode([
	      'ok' => true,
	      'coupon' => [
	        'valid' => true,
	        'type' => 'success',
	        'message' => 'Cupom <strong>' . htmlspecialchars($coupon['code']) . '</strong> válido.',
	        'data' => [
	          'code' => (string)$coupon['code'],
	          'install_discount_enabled' => (bool)$coupon['install_discount_enabled'],
	          'install_discount_mode' => (string)$coupon['install_discount_mode'],
	          'install_discount_value' => (float)$coupon['install_discount_value'],
	          'monthly_discount_enabled' => (bool)$coupon['monthly_discount_enabled'],
	          'monthly_discount_mode' => (string)$coupon['monthly_discount_mode'],
	          'monthly_discount_value' => (float)$coupon['monthly_discount_value'],
	        ],
	      ]
	    ]);
	    exit;
	  }
	
	  if ($action === 'quote') {
	    $vehicleType = $_POST['vehicle_type'] ?? '';
	    $remoteBlocking = $_POST['remote_blocking'] ?? '';
	
	    $couponCode = $_POST['coupon_code_applied'] ?? '';
	    $couponValid = ($_POST['coupon_valid'] ?? '0') === '1';
	
	    $coupon = null;
	    if ($couponValid && trim((string)$couponCode) !== '') {
	      $coupon = gt_find_coupon($couponCode, $GT_COUPONS);
	    }
	
	    $quote = gt_compute_quote($vehicleType, $remoteBlocking, $coupon);
	
	    echo json_encode([
	      'ok' => true,
	      'quote' => $quote,
	    ]);
	    exit;
	  }
	
	  echo json_encode(['ok' => false, 'message' => 'Ação inválida.']);
	  exit;
	}
	?>
<!doctype html>
<html class="no-js" lang="pt-br">
	<head>
		<title>GuardianTech - Contratar - Pessoa Física</title>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="author" content="GuardianTech">
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<meta name="description" content="Especializada em rastreamento veicular e segurança eletrônica, a GuardianTech protege seu patrimônio com tecnologia de ponta para carros, motos, caminhões, cargas, barcos e tratores. Soluções completas para condomínios e empresas.">
		<meta name="keywords" content="rastreadores, bragança paulista, socorro, serra negra, atibaia, zona rural, tratores, caminhão, carga, rastreamento veicular, rastreador de carro, rastreador de moto, rastreador de caminhão, rastreador de barco, rastreamento de cargas, rastreador agrícola, GuardianTech">
		<meta name="robots" content="index, follow">
		<meta name="language" content="Portuguese">
		<meta name="revisit-after" content="30 days">
		<meta name="apple-mobile-web-app-title" content="GuardianTech">
		<link rel="canonical" href="https://guardiantech.site" />
		<!-- favicon icons -->
		<link rel="icon" type="image/png" href="favicon/favicon-96x96.png" sizes="96x96" />
		<link rel="icon" type="image/svg+xml" href="favicon/favicon.svg" />
		<link rel="shortcut icon" href="favicon/favicon.ico" />
		<link rel="apple-touch-icon" sizes="180x180" href="favicon/apple-touch-icon.png" />
		<!-- Open Graph Meta Tags -->
		<meta property="og:title" content="GuardianTech - Contratar (PF)" />
		<meta property="og:type" content="website" />
		<meta property="og:url" content="https://guardiantech.site" />
		<meta property="og:image" content="https://guardiantech.site/images/og-formfisica.jpg" />
		<meta property="og:image:alt" content="GuardianTech - Rastreamento e Segurança" />
		<meta property="og:description" content="Formulário de contratação (Pessoa Física)" />
		<meta property="og:site_name" content="GuardianTech" />
		<meta property="og:image:width" content="1200" />
		<meta property="og:image:height" content="630" />
		<meta property="og:image:type" content="image/jpeg" />
		<!-- Twitter Card Meta Tags -->
		<meta name="twitter:card" content="summary_large_image" />
		<meta name="twitter:title" content="GuardianTech - Contratar (PF)" />
		<meta name="twitter:description" content="Formulário de contratação (Pessoa Física)" />
		<meta name="twitter:image" content="https://guardiantech.site/images/og-formfisica.jpg" />
		<meta name="twitter:image:alt" content="Imagem de rastreamento de veículos e segurança eletrônica" />
		<!-- google fonts preconnect -->
		<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<!-- style sheets and font icons -->
		<link rel="stylesheet" href="css/vendors.min.css" />
		<link rel="stylesheet" href="css/icon.min.css" />
		<link rel="stylesheet" href="css/style.css" />
		<link rel="stylesheet" href="css/responsive.css" />
		<link rel="stylesheet" href="css/custom.css" />
		<!-- reCAPTCHA v3 (site key pública) -->
		<script src="https://www.google.com/recaptcha/api.js?render=6Lfq-swrAAAAAB-oEqZQ_QKwLGw9xLDJTjTaAhGY"></script>
	</head>
	<body data-mobile-nav-style="classic">
		<div id="gt-top" tabindex="-1" aria-hidden="true" style="position:absolute; top:0; left:0; width:1px; height:1px; overflow:hidden;"></div>
		<div id="gt-page-loader" role="status" aria-label="Carregando">
			<div class="gt-spinner"></div>
			<div class="gt-text">Carregando…</div>
		</div>
		<!-- start header -->
		<?php include 'includes/header.php'; ?>
		<!-- end header --> 
		<section class="page-title-big-typography bg-dark-gray ipad-top-space-margin" data-parallax-background-ratio="0.5" style="background-image: url(images/title-formfisica.jpg)">
			<div class="opacity-extra-medium bg-dark-slate-blue"></div>
			<div class="container">
				<div class="row align-items-center justify-content-center extra-small-screen">
					<div class="col-12 position-relative text-center page-title-extra-large">
						<h1 class="m-auto text-white text-shadow-double-large fw-500 ls-minus-3px xs-ls-minus-2px" data-anime='{ "translateY": [15, 0], "opacity": [0,1], "duration": 600, "delay": 0, "staggervalue": 300, "easing": "easeOutQuad" }'>Contrate agora</h1>
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
		<!-- start section: formulário de contratação (Rastreamento) -->
		<section>
			<div class="container" data-anime='{"el":"childs","translateY":[50,0],"opacity":[0,1],"duration":800,"delay":200,"staggervalue":150,"easing":"easeOutQuad"}'>
				<div class="row justify-content-center">
					<div class="col-xl-10">
						<div class="row align-items-center mb-40px">
							<div class="col-lg-9">
								<h4 class="alt-font text-dark-gray fw-700 mb-5px">Formulário de contratação <br>(Pessoa física)</h4>
								<p class="mb-0">Preencha os dados abaixo para iniciarmos seu cadastro e agendamento de instalação.</p>
							</div>
							<div class="col-lg-3 text-lg-end mt-20px mt-lg-0">
								<i class="bi bi-send icon-large text-base-color opacity-75"></i>
							</div>
						</div>
						<!-- start form -->
						<form action="email-templates/contrate_fisica_action.php"
							method="post"
							class="row contact-form-style-02 gt-contract-form"
							novalidate enctype="multipart/form-data">
							<!-- Alert geral do formulário -->
							<div class="col-12 mb-20px">
								<div class="alert-box-style-05 d-none" id="formAlertWrap" tabindex="-1"></div>
							</div>
							<!-- Honeypot antispam (opcional) -->
							<div class="d-none">
								<label>Não preencher: <input type="text" name="website" autocomplete="off"></label>
							</div>
							<!-- =========================
								DADOS PESSOAIS
								========================== -->
							<div class="col-12 mb-10px">
								<h6 class="alt-font text-dark-gray fw-700 mb-0">Dados pessoais</h6>
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="full_name">Digite seu nome completo*</label>
								<input id="full_name" class="form-control required" type="text" name="full_name" placeholder="Nome completo" autocomplete="name" required />
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="email">Digite seu e-mail principal*</label>
								<input id="email" class="form-control required" type="email" name="email" placeholder="email@exemplo.com" autocomplete="email" required />
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="cpf">Digite seu CPF*</label>
								<input id="cpf" class="form-control required" type="text" name="cpf" placeholder="000.000.000-00" inputmode="numeric" maxlength="14" required />
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="rg">Digite seu RG*</label>
								<input id="rg" class="form-control required" type="text" name="rg" placeholder="00.000.000-0" inputmode="numeric" maxlength="12" required />
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="birth_date">Data de nascimento*</label>
								<input id="birth_date" class="form-control required" type="date" name="birth_date" required />
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="phone_primary">Digite seu celular principal*</label>
								<input id="phone_primary" class="form-control required" type="tel" name="phone_primary" placeholder="(11) 90000-0000" inputmode="tel" maxlength="15" required />
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="phone_secondary">Digite um telefone secundário*</label>
								<input id="phone_secondary" class="form-control required" type="tel" name="phone_secondary" placeholder="(11) 90000-0000" inputmode="tel" maxlength="15" required />
							</div>
							<div class="col-md-12 mb-30px">
								<label class="form-label mb-10px" for="platform_username">Digite o nome de usuário desejado na plataforma*</label>
								<input id="platform_username"
									class="form-control required text-lowercase"
									type="text"
									name="platform_username"
									placeholder="Ex.: joaosilva"
									autocomplete="off"
									maxlength="30"
									required />
								<small class="d-block mt-5px">Este será o seu nome de usuário (login) para acessar o aplicativo/plataforma.</small>
								<small class="d-block">Não aceita espaços nem caracteres especiais. Máximo de 30 caracteres.</small>
							</div>

							<div class="col-md-12 mb-30px">
								<label class="form-label mb-10px">Anexe seu documento (RG, CPF ou CNH)*</label>

								<div class="border border-color-extra-medium-gray border-radius-4px p-20px">
									<input type="hidden" name="MAX_FILE_SIZE" value="5242880">

									<div class="row">
										<!-- Frente (ou arquivo único) -->
										<div class="col-md-6 mb-20px">
											<div class="d-flex flex-wrap align-items-center">
												<button type="button" class="btn btn-base-color btn-medium btn-rounded btn-box-shadow" id="document_file_1_btn">
													Selecionar frente (ou arquivo único)
												</button>
</div>

											<div class="mt-10px">
												<small class="d-block" id="document_file_1_status">Nenhum arquivo selecionado.</small>
											</div>

											<div class="gt-doc-preview-wrap position-relative mt-15px" id="document_file_1_preview_wrap" style="display:none;">
    <button type="button" class="btn btn-dark btn-sm position-absolute top-0 end-0 m-2 gt-doc-remove-btn" id="document_file_1_remove" style="display:none; z-index: 2;" aria-label="Remover documento frente">
        Remover
    </button>
    <div id="document_file_1_preview"></div>
</div>

											<input id="document_file_1"
												class="form-control required visually-hidden"
												type="file"
												name="document_file_1"
												accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf"
												required />
										</div>

										<!-- Verso (opcional) -->
										<div class="col-md-6 mb-5px">
											<div class="d-flex flex-wrap align-items-center">
												<button type="button" class="btn btn-base-color btn-medium btn-rounded btn-box-shadow" id="document_file_2_btn">
													Selecionar verso (opcional)
												</button>
</div>

											<div class="mt-10px">
												<small class="d-block" id="document_file_2_status">Nenhum arquivo selecionado.</small>
											</div>

											<div class="gt-doc-preview-wrap position-relative mt-15px" id="document_file_2_preview_wrap" style="display:none;">
    <button type="button" class="btn btn-dark btn-sm position-absolute top-0 end-0 m-2 gt-doc-remove-btn" id="document_file_2_remove" style="display:none; z-index: 2;" aria-label="Remover documento verso">
        Remover
    </button>
    <div id="document_file_2_preview"></div>
</div>

											<input id="document_file_2"
												class="form-control visually-hidden"
												type="file"
												name="document_file_2"
												accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf" />
										</div>
									</div>

									<small class="d-block mt-10px">
										Você pode enviar a frente e o verso em <strong>um único arquivo</strong> (somente no campo da frente), ou enviar <strong>2 arquivos</strong> (frente e verso).
									</small>
									<small class="d-block">
										Formatos aceitos: JPG, JPEG, PNG, WEBP, HEIC/HEIF ou PDF. Tamanho máximo: 8 MB por arquivo (até 2 arquivos) e 16 MB no total.
									</small>
								</div>
							</div>

							<style>
								/* Input file escondido para UX consistente (mantém acessibilidade e compatibilidade) */
								.visually-hidden{
									position:absolute !important;
									left:-9999px !important;
									width:1px !important;
									height:1px !important;
									overflow:hidden !important;
									opacity:0 !important;
									pointer-events:none !important;
								}
								/* Prévia: mantém estética sem depender de CSS adicional */
								.gt-doc-preview-img{
									max-width:100%;
									height:auto;
									display:block;
									border-radius:4px;
								}
								.gt-doc-preview-box{
									border:1px solid rgba(0,0,0,0.12);
									border-radius:4px;
									padding:10px;
								}
							</style>

<div class="col-12"><span class="d-block mt-10px mb-25px w-100 h-1px bg-very-light-gray"></span></div>
							<!-- =========================
								ENDEREÇO DE CADASTRO
								(AJUSTE MÍNIMO: UF/Cidade viram inputs readonly + adiciona Bairro + IDs para autopreenchimento)
								========================== -->
							<div class="col-12 mb-10px">
								<h6 class="alt-font text-dark-gray fw-700 mb-0">Endereço de cadastro</h6>
							</div>
							<div class="col-md-3 mb-30px">
								<label class="form-label mb-10px" for="address_cep">CEP*</label>
								<!-- + data-cep-scope="address" (usado pelo JS para saber quais campos preencher) -->
								<input id="address_cep" class="form-control required" type="text" name="address_cep"
									placeholder="00000-000" inputmode="numeric" maxlength="9" required
									data-cep-scope="address" />
							</div>
							<!-- UF vira input readonly -->
							<div class="col-md-3 mb-30px">
								<label class="form-label mb-10px" for="address_uf">UF*</label>
								<input id="address_uf" class="form-control required" type="text" name="address_uf"
									placeholder="Digite o CEP" readonly required />
							</div>
							<!-- Cidade vira input readonly -->
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="address_city">Cidade*</label>
								<input id="address_city" class="form-control required" type="text" name="address_city"Fcupom
									placeholder="Digite o CEP" readonly required />
							</div>
							<!-- Bairro (novo; preenche se disponível) -->
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="address_neighborhood">Bairro*</label>
								<input id="address_neighborhood" class="form-control required" type="text" name="address_neighborhood"
									placeholder="Bairro" required />
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="address_street">Rua/Avenida*</label>
								<input id="address_street" class="form-control required" type="text" name="address_street"
									placeholder="Rua/Avenida" required />
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="address_number">Número*</label>
								<input id="address_number" class="form-control required" type="text" name="address_number"
									placeholder="Número" inputmode="numeric" required />
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="address_complement">Complemento</label>
								<input id="address_complement" class="form-control" type="text" name="address_complement"
									placeholder="Complemento" required />
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="address_note">Observação (do endereço)</label>
								<input id="address_note" class="form-control" type="text" name="address_note"
									placeholder="Observação" required />
							</div>
							<!-- =========================
								CONTATO DE EMERGÊNCIA
								========================== -->
							<div class="col-12 mb-10px">
								<h6 class="alt-font text-dark-gray fw-700 mb-0">Contato de emergência</h6>
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="emergency_contact_name">Nome completo do contato de emergência*</label>
								<input id="emergency_contact_name" class="form-control required" type="text" name="emergency_contact_name" placeholder="Nome completo" required />
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="emergency_contact_phone">Telefone do contato de emergência*</label>
								<input id="emergency_contact_phone" class="form-control required" type="tel" name="emergency_contact_phone" placeholder="(11) 90000-0000" inputmode="tel" maxlength="15" required />
							</div>
							<div class="col-md-12 mb-30px">
								<label class="form-label mb-10px" for="emergency_contact_relationship">Relação/parentesco com o contato de emergência*</label>
								<input id="emergency_contact_relationship" class="form-control required" type="text" name="emergency_contact_relationship" placeholder="Ex.: Pai, Mãe, Cônjuge..." required />
							</div>
							<div class="col-12"><span class="d-block mt-10px mb-25px w-100 h-1px bg-very-light-gray"></span></div>
							<!-- =========================
								DADOS DO VEÍCULO
								========================== -->
							<div class="col-12 mb-10px">
								<h6 class="alt-font text-dark-gray fw-700 mb-0">Dados do veículo</h6>
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="vehicle_type">Tipo de veículo*</label>
								<select id="vehicle_type" class="form-control required" name="vehicle_type" required>
									<option value="" selected disabled>Selecione</option>
									<option value="moto">Moto</option>
									<option value="carro">Carro</option>
									<option value="pickup">Pickup</option>
									<option value="caminhonete">Caminhonete</option>
									<option value="van">Van</option>
									<option value="caminhao">Caminhão</option>
									<option value="trator_maquina">Trator / Máquina agrícola</option>
									<option value="trator_maquina">Outras máquinas</option>
									<option value="embarcacao">Embarcação</option>
									<option value="aeronave">Aeronave / Helicóptero</option>
									<option value="outro">Outro</option>
								</select>
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="vehicle_fuel">Combustível*</label>
								<select id="vehicle_fuel" class="form-control required" name="vehicle_fuel" required>
									<option value="" selected disabled>Selecione</option>
									<option value="Gasolina">Gasolina</option>
									<option value="Etanol">Etanol</option>
									<option value="Flex">Flex</option>
									<option value="Diesel">Diesel</option>
									<option value="GNV">GNV</option>
									<option value="Elétrico">Elétrico</option>
									<option value="Híbrido">Híbrido</option>
									<option value="Outro">Outro</option>
								</select>
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="vehicle_color">Cor*</label>
								<select id="vehicle_color" class="form-control required" name="vehicle_color" required>
									<option value="" selected disabled>Selecione</option>
									<option value="Amarelo">Amarelo</option>
									<option value="Azul">Azul</option>
									<option value="Bege">Bege</option>
									<option value="Branco">Branco</option>
									<option value="Cinza">Cinza</option>
									<option value="Dourado">Dourado</option>
									<option value="Grená">Grená</option>
									<option value="Laranja">Laranja</option>
									<option value="Marrom">Marrom</option>
									<option value="Prata">Prata</option>
									<option value="Preto">Preto</option>
									<option value="Rosa">Rosa</option>
									<option value="Roxo">Roxo</option>
									<option value="Verde">Verde</option>
									<option value="Vermelha">Vermelha</option>
									<option value="Fantasia">Fantasia</option>
								</select>
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="vehicle_plate">Placa* (com hífen)</label>
								<input id="vehicle_plate" class="form-control required text-uppercase" type="text" name="vehicle_plate" placeholder="ABC-1234 / ABC-1D23" maxlength="8" required />
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="vehicle_brand">Marca*</label>
								<input id="vehicle_brand" class="form-control required" type="text" name="vehicle_brand" placeholder="Ex.: Toyota, Honda..." required />
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="vehicle_model">Modelo*</label>
								<input id="vehicle_model" class="form-control required" type="text" name="vehicle_model" placeholder="Ex.: Corolla, CG 150..." required />
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="vehicle_year_model">Ano modelo*</label>
								<input id="vehicle_year_model" class="form-control required" type="number" name="vehicle_year_model" placeholder="Ex.: 2021" min="1950" max="2100" required />
							</div>
							<div class="col-md-8 mb-30px">
								<label class="form-label mb-10px" for="vehicle_max_days_no_movement">Tempo máximo sem uso*</label>
								<select id="vehicle_max_days_no_movement" class="form-control required" name="vehicle_max_days_no_movement" required>
									<option value="" selected disabled>Selecione</option>
									<option value="nenhum_dia">Nenhum dia</option>
									<option value="De 1 a 3 dias">De 1 a 3 dias</option>
									<option value="De 3 a 5 dias5">De 3 a 5 dias</option>
									<option value="De 5 a 10 dias">De 5 a 10 dias</option>
									<option value="Mais de 10 dias">Mais de 10 dias</option>
								</select>
								<small class="d-block mt-5px">Tempo máximo que o você deixa o veículo sem dar partida.</small>
							</div>
							<div class="col-12 mb-30px">
								<label class="form-label mb-10px" for="remote_blocking">Deseja poder bloquear seu veículo remotamente?*</label>
								<select id="remote_blocking" class="form-control required" name="remote_blocking" required>
									<option value="" selected disabled>Selecione</option>
									<option value="sim">Sim</option>
									<option value="nao">Não</option>
								</select>
							</div>
							<div class="col-12"><span class="d-block mt-10px mb-25px w-100 h-1px bg-very-light-gray"></span></div>
							<!-- =========================
								ENDEREÇO DA INSTALAÇÃO
								(AJUSTE MÍNIMO: CEP ganha data-cep-scope="install" + UF/Cidade viram inputs readonly + adiciona Bairro)
								========================== -->
							<div class="col-12 mb-10px">
								<h6 class="alt-font text-dark-gray fw-700 mb-0">Endereço da instalação</h6>
							</div>
							<div class="col-12 mb-20px mt-5px">
								<div class="row g-2">
									<div class="col-md-6">
										<label class="gt-choice">
										<input type="radio" name="installation_address_choice" value="same" checked>
										<span class="gt-choice-text">Mesmo endereço de cadastro</span>
										</label>
									</div>
									<div class="col-md-6">
										<label class="gt-choice">
										<input type="radio" name="installation_address_choice" value="other">
										<span class="gt-choice-text">Outro endereço</span>
										</label>
									</div>
								</div>
							</div>
							<!-- ... seu bloco de "Mesmo endereço / Outro endereço" mantém igual ... -->
							<div class="col-md-3 mb-30px">
								<label class="form-label mb-10px" for="install_cep">CEP (instalação)*</label>
								<!-- + data-cep-scope="install" -->
								<input id="install_cep" class="form-control required" type="text" name="install_cep"
									placeholder="00000-000" inputmode="numeric" maxlength="9" required
									data-cep-scope="install" />
							</div>
							<!-- UF vira input readonly -->
							<div class="col-md-3 mb-30px">
								<label class="form-label mb-10px" for="install_uf">UF (instalação)*</label>
								<input id="install_uf" class="form-control required" type="text" name="install_uf"
									placeholder="Digite o CEP" readonly required />
							</div>
							<!-- Cidade vira input readonly -->
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="install_city">Cidade (instalação)*</label>
								<input id="install_city" class="form-control required" type="text" name="install_city"
									placeholder="Digite o CEP" readonly required />
							</div>
							<!-- Bairro (novo; preenche se disponível) -->
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="install_neighborhood">Bairro (instalação)*</label>
								<input id="install_neighborhood" class="form-control required" type="text" name="install_neighborhood"
									placeholder="Bairro" required />
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="install_street">Rua/Avenida (instalação)*</label>
								<input id="install_street" class="form-control required" type="text" name="install_street"
									placeholder="Rua/Avenida" required />
							</div>
							<div class="col-md-4 mb-30px">
								<label class="form-label mb-10px" for="install_number">Número (instalação)*</label>
								<input id="install_number" class="form-control required" type="text" name="install_number"
									placeholder="Número" inputmode="numeric" required />
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="install_complement">Complemento (instalação)</label>
								<input id="install_complement" class="form-control" type="text" name="install_complement"
									placeholder="Complemento" required />
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="install_note">Observação (instalação)</label>
								<input id="install_note" class="form-control" type="text" name="install_note"
									placeholder="Observação" required />
							</div>
							<div class="col-12"><span class="d-block mt-10px mb-25px w-100 h-1px bg-very-light-gray"></span></div>
							<!-- =========================
								INSTALAÇÃO E PAGAMENTOS
								========================== -->
							<div class="col-12 mb-10px">
								<h6 class="alt-font text-dark-gray fw-700 mb-0">Instalação e pagamentos</h6>
							</div>
							<div class="col-md-12 mb-30px">
								<label class="form-label mb-15px">Qual melhor período para instalação?*</label>
								<div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-2">
									<div class="col">
										<label class="gt-choice">
										<input type="checkbox" name="installation_period[]" value="manha">
										<span class="gt-choice-text">Manhã</span>
										</label>
									</div>
									<div class="col">
										<label class="gt-choice">
										<input type="checkbox" name="installation_period[]" value="tarde">
										<span class="gt-choice-text">Tarde</span>
										</label>
									</div>
									<div class="col">
										<label class="gt-choice">
										<input type="checkbox" name="installation_period[]" value="noite">
										<span class="gt-choice-text">Noite</span>
										</label>
									</div>
									<div class="col">
										<label class="gt-choice">
										<input type="checkbox" name="installation_period[]" value="Qualquer horário" id="installation_anytime">
										<span class="gt-choice-text">Qualquer horário</span>
										</label>
									</div>
								</div>
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="installation_payment_method">Forma de pagamento da instalação*</label>
								<select id="installation_payment_method" class="form-control required" name="installation_payment_method" required>
									<option value="" selected disabled>Selecione</option>
									<option value="Cartão de crédito">Cartão de crédito</option>
									<option value="PIX">PIX</option>
									<option value="Boleto">Boleto</option>
								</select>
							</div>
							<div class="col-md-6 mb-30px">
								<label class="form-label mb-10px" for="monthly_payment_method">Forma de pagamento da mensalidade*</label>
								<select id="monthly_payment_method" class="form-control required" name="monthly_payment_method" required>
									<option value="" selected disabled>Selecione</option>
									<option value="PIX">PIX</option>
									<option value="Boleto">Boleto</option>
								</select>
							</div>
<div class="col-12 col-lg-6 mb-35px">
    <label class="form-label">Dia de vencimento da mensalidade*</label>
    <select id="monthly_due_day" name="monthly_due_day" class="form-control" required>
        <option value=""  selected disabled>Selecione</option>
        <option value="10">Dia 10</option>
        <option value="15">Dia 15</option>
        <option value="20">Dia 20</option>
    </select>
</div>



<!-- CUPOM (opcional / abre somente se marcar) -->
<div class="col-12 mb-20px">
<label class="form-label">Cupom de desconto/indicação (opcional):</label>
    <!-- Mesmo estilo dos checkboxes "Manhã/Tarde/Noite" -->
    <label class="gt-choice w-100">
        <input type="checkbox" id="hasCouponToggle">
        <span class="gt-choice-text fs-15">Marque aqui caso tenha um cupom de desconto/indicação</span>
    </label>
            <small class="d-block text-medium-gray mt-10px">
            Se você não tem cupom, deixe desmarcado e continue o formulário normalmente.
        </small>

    <!-- Área que abre/fecha -->
    <div id="couponFieldsWrap" class="d-none mt-15px">


        <div class="d-flex flex-column flex-sm-row align-items-stretch" style="gap: 12px;">
            <input
                id="installation_coupon"
                class="form-control"
                type="text"
                name="installation_coupon"
                placeholder="Digite aqui e clique em APLICAR"
                autocomplete="off"
            />

            <button
                id="couponValidateBtn"
                class="btn btn-base-color btn-medium btn-rounded btn-box-shadow"
                type="button"
                style="white-space: nowrap;"
            >
                Aplicar
            </button>
            
        </div>

        <div class="alert-box-style-05 mt-15px d-none" id="couponAlertWrap" tabindex="-1"></div>


    </div>
</div>
							<div class="col-12"><span class="d-block mt-10px mb-25px w-100 h-1px bg-very-light-gray"></span></div>
							<!-- Resumo do pedido -->
							<div class="col-12 mb-30px">
								<div class="border border-color-extra-medium-gray border-radius-4px p-20px bg-very-light-gray">
									<span class="alt-font text-dark-gray fw-700 d-block">Resumo do pedido</span>
									<small class="d-block">(Atualiza automaticamente conforme suas escolhas.)</small>
									<div class="mt-15px">
										<p class="mb-5px text-dark-gray"><strong>Plano:</strong> <span id="summaryPlan">—</span></p>
										<p class="mb-5px text-dark-gray"><strong>Mensalidade:</strong> <span id="summaryMonthly">—</span></p>
										<p class="mb-0 text-dark-gray"><strong>Instalação:</strong> <span id="summaryInstall">—</span></p>
										<p class="mb-0 text-dark-gray d-none mt-10px" id="summaryCouponLine">
											<strong>Cupom:</strong> <span id="summaryCouponText">—</span>
										</p>
									</div>
									<div class="mt-15px">
										<small class="d-block">
										Motos, carros, pickups, vans e caminhonetes (inclusive elétricas e híbridas):<br>
										<strong>R$ 58,90/mês</strong> – rastreamento sem bloqueio (GuardianEssential)<br>
										<strong>R$ 64,90/mês</strong> – com bloqueio via app (GuardianSecure)<br>
										Instalação (ambos): <strong>R$ 120,00</strong>
										</small>
										<small class="d-block mt-10px">
										Caminhões, tratores, máquinas agrícolas, embarcações e aeronaves:<br>
										<strong>R$ 68,90/mês</strong> – rastreamento com ou sem bloqueio (GuardianHeavy)<br>
										Instalação: <strong>a partir de R$ 150,00</strong>
										</small>
									</div>
									<!-- Hidden (cupom aplicado / quote) -->
									<input type="hidden" name="coupon_valid" id="coupon_valid" value="0">
									<input type="hidden" name="coupon_code_applied" id="coupon_code_applied" value="">
									<input type="hidden" name="calculated_plan" id="calculated_plan" value="">
									<input type="hidden" name="calculated_monthly" id="calculated_monthly" value="">
									<input type="hidden" name="calculated_install" id="calculated_install" value="">
								</div>
							</div>
							<div class="col-12"><span class="d-block mt-10px mb-25px w-100 h-1px bg-very-light-gray"></span></div>
							<!-- =========================
								TERMOS
								========================== -->
							<div class="col-12 mb-35px">
								<label class="form-label mb-10px"><strong>CONTRATO DE PRESTAÇÃO DE SERVIÇO*</strong></label>
								<div class="border border-color-extra-medium-gray border-radius-4px p-20px mb-15px gt-contract-scroll">
									<?php echo nl2br(file_get_contents(__DIR__ . '/includes/contrato_fisica.php')); ?>
								</div>
								<label class="gt-choice gt-terms-choice">
								<input class="terms-condition required" type="checkbox" value="1" id="termsAccepted" name="terms_accepted" required>
								<span class="gt-choice-text">
								Li integralmente e concordo com o&nbsp;
								<span class="gt-terms-title">CONTRATO DE PRESTAÇÃO DE SERVIÇO</span>
								</span>
								</label>
								<!-- Cole este bloco logo abaixo do checkbox de termos -->
								<div class="mt-15px">
									<small class="d-block text-base-color opacity-50" style="font-size: 12px; line-height: 1.3;">
										<strong>Dados coletados:</strong>
										IP: <span id="gtCollectedIp"><?php echo htmlspecialchars($gt_ip); ?></span> —
										Navegador/SO: <span id="gtCollectedAgent">Carregando...</span> —
										Data/Hora:
										<span id="gtCollectedServerDT" data-server-epoch-ms="<?php echo (int)$gt_server_epoch_ms; ?>">
										<?php echo htmlspecialchars($gt_server_dt); ?>
										</span>
										<!-- Localização só aparece quando autorizado -->
										<span id="gtCollectedGeoWrap" class="d-none"> — Localização: <span id="gtCollectedGeo"></span></span>
									</small>
								</div>
								<!-- Hidden fields (enviados junto no POST) -->
								<input type="hidden" name="collected_ip" id="collected_ip" value="<?php echo htmlspecialchars($gt_ip); ?>">
								<input type="hidden" name="collected_user_agent_raw" id="collected_user_agent_raw" value="">
								<input type="hidden" name="collected_browser_friendly" id="collected_browser_friendly" value="">
								<input type="hidden" name="collected_os_friendly" id="collected_os_friendly" value="">
								<input type="hidden" name="collected_server_datetime" id="collected_server_datetime" value="<?php echo htmlspecialchars($gt_server_dt); ?>">
								<input type="hidden" name="collected_server_epoch_ms" id="collected_server_epoch_ms" value="<?php echo (int)$gt_server_epoch_ms; ?>">
								<input type="hidden" name="collected_geolocation" id="collected_geolocation" value="">
								<!-- Hidden fields (enviados junto no POST) -->
								<input type="hidden" name="collected_ip" id="collected_ip" value="<?php echo htmlspecialchars($gt_ip); ?>">
								<input type="hidden" name="collected_user_agent_raw" id="collected_user_agent_raw" value="">
								<input type="hidden" name="collected_browser_friendly" id="collected_browser_friendly" value="">
								<input type="hidden" name="collected_os_friendly" id="collected_os_friendly" value="">
								<input type="hidden" name="collected_server_datetime" id="collected_server_datetime" value="<?php echo htmlspecialchars($gt_server_dt); ?>">
								<input type="hidden" name="collected_server_epoch_ms" id="collected_server_epoch_ms" value="<?php echo (int)$gt_server_epoch_ms; ?>">
								<input type="hidden" name="collected_geolocation" id="collected_geolocation" value="">
								<!-- Hidden fields (enviados junto no POST) -->
								<input type="hidden" name="collected_ip" id="collected_ip" value="<?php echo htmlspecialchars($gt_ip); ?>">
								<input type="hidden" name="collected_user_agent_raw" id="collected_user_agent_raw" value="">
								<input type="hidden" name="collected_browser_friendly" id="collected_browser_friendly" value="">
								<input type="hidden" name="collected_os_friendly" id="collected_os_friendly" value="">
								<input type="hidden" name="collected_server_datetime" id="collected_server_datetime" value="<?php echo htmlspecialchars($gt_server_dt); ?>">
								<input type="hidden" name="collected_server_epoch_ms" id="collected_server_epoch_ms" value="<?php echo (int)$gt_server_epoch_ms; ?>">
								<input type="hidden" name="collected_geolocation" id="collected_geolocation" value="">
							</div>
							<div class="col-xl-7 col-md-7 last-paragraph-no-margin">
								<p class="text-center text-md-start fs-15 lh-26 mb-0">
									Ao enviar este formulário, você confirma a contratação e autoriza contato para fins de agendamento e procedimentos operacionais. Seus dados serão tratados conforme a <a href="https://www.gov.br/esporte/pt-br/acesso-a-informacao/lgpd" target="_blank">LGPD.</a>
								</p>
							</div>
							<div class="col-xl-5 col-md-5 text-center text-md-end sm-mt-20px">
								<input type="hidden" name="redirect" value="">
								<button class="btn btn-base-color btn-medium btn-rounded btn-box-shadow submit" type="submit" id="submitRequestBtn">
								Enviar solicitação
								</button>
							</div>
							<div class="col-12">
								<div class="form-results mt-20px d-none"></div>
							</div>
						</form>
						<!-- end form -->
					</div>
				</div>
			</div>
		</section>
		<!-- end section -->

		<!-- start footer -->
		<?php include 'includes/footer.php'; ?>
		<!-- end footer -->
		<!-- start scroll progress -->
		<div class="scroll-progress d-none d-xxl-block">
			<a href="#" class="scroll-top" aria-label="scroll">
			<span class="scroll-text">Rolagem do site</span><span class="scroll-line"><span class="scroll-point"></span></span>
			</a>
		</div>
		<!-- end scroll progress -->
		<!-- javascript libraries -->
		<script type="text/javascript" src="js/jquery.js"></script>
		<script type="text/javascript" src="js/vendors.min.js"></script>
		<script type="text/javascript" src="js/main.js"></script>
		<script type="text/javascript" src="js/gt-contratacao-rastreamento.js?v=1.0.0"></script>

	</body>
</html>