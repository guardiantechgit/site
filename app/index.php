<?php
// index.php - Página oficial de download do app GuardianTech Rastreamento

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Links oficiais
$play_web     = 'https://play.google.com/store/apps/details?id=br.com.getrak.guardiantech';
$play_app     = 'market://details?id=br.com.getrak.guardiantech';
$appstore_web = 'https://apps.apple.com/us/app/guardiantech-rastreamento/id6747404230';
$appstore_app = 'itms-apps://itunes.apple.com/app/id6747404230';

// IDs (para intent:// e itms-apps://)
$android_pkg  = 'br.com.getrak.guardiantech';
$ios_app_id   = '6747404230';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="author" content="GuardianTech">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <meta name="description" content="Baixe agora o aplicativo GuardianTech Rastreamento, solução para rastreamento veicular e monitoramento em tempo real. iOS e Android.">
  <meta name="keywords" content="GuardianTech, download, aplicativo, rastreamento veicular, rastreador, GuardianTech, iOS, Android">
  <meta name="robots" content="index, follow">
  <meta name="language" content="Portuguese">
  <meta name="revisit-after" content="30 days">
  <meta name="apple-mobile-web-app-title" content="GuardianTech App">
  <link rel="canonical" href="https://guardiantech.site/app" />

  <!-- favicon icons -->
  <link rel="icon" type="image/png" href="/favicon/favicon-96x96.png" sizes="96x96" />
  <link rel="icon" type="image/svg+xml" href="/favicon/favicon.svg" />
  <link rel="shortcut icon" href="/favicon/favicon.ico" />
  <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png" />
  <link rel="manifest" href="/favicon/site.webmanifest" />

  <!-- Open Graph -->
  <meta property="og:title" content="GuardianTech Rastreamento - Download do Aplicativo Oficial" />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="https://guardiantech.site/app/" />
  <meta property="og:image" content="https://guardiantech.site/images/og-app.jpg" />
  <meta property="og:image:alt" content="GuardianTech - App de Rastreamento Veicular" />
  <meta property="og:description" content="Baixe o GuardianTech Rastreamento, o app oficial para rastrear seus veículos. Disponível na App Store e Google Play." />
  <meta property="og:site_name" content="GuardianTech" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:image:type" content="image/jpeg" />

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="GuardianTech Rastreamento - App Oficial" />
  <meta name="twitter:description" content="Download do app GuardianTech Rastreamento, solução para rastreamento e monitoramento. iOS e Android." />
  <meta name="twitter:image" content="https://guardiantech.site/images/og-index.jpg" />
  <meta name="twitter:image:alt" content="App GuardianTech Rastreamento" />

  <title>GuardianTech Rastreamento - Download</title>

  <link rel="stylesheet" href="/app/style_guardiantechapp.css?v=1.3">
  <script defer src="/app/script_guardiantechapp.js?v=1.3"></script>

  <noscript>
    <!-- Fallback simples caso JS esteja desabilitado -->
    <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($play_web, ENT_QUOTES, 'UTF-8'); ?>">
  </noscript>
</head>
<body
  data-play-web="<?php echo htmlspecialchars($play_web, ENT_QUOTES, 'UTF-8'); ?>"
  data-play-app="<?php echo htmlspecialchars($play_app, ENT_QUOTES, 'UTF-8'); ?>"
  data-appstore-web="<?php echo htmlspecialchars($appstore_web, ENT_QUOTES, 'UTF-8'); ?>"
  data-appstore-app="<?php echo htmlspecialchars($appstore_app, ENT_QUOTES, 'UTF-8'); ?>"
  data-android-package="<?php echo htmlspecialchars($android_pkg, ENT_QUOTES, 'UTF-8'); ?>"
  data-ios-app-id="<?php echo htmlspecialchars($ios_app_id, ENT_QUOTES, 'UTF-8'); ?>"
>
  <div class="wrap">
    <!-- Card DESKTOP -->
    <div class="card" id="desktop-msg" hidden>
      <img class="logo" src="https://guardiantech.site/images/guardiantech-app.png" alt="GuardianTrack - Download nas lojas oficiais">
      <p>O aplicativo está disponível para <strong>App Store (iOS)</strong> e <strong>Google Play (Android)</strong>, porém identificamos que você está em um <strong>computador</strong>.</p>
      <p class="hint">Se quiser acessar o aplicativo nas lojas oficiais, utilize os botões abaixo.</p>
      <div class="btns">
        <a class="btn" href="<?php echo htmlspecialchars($appstore_web, ENT_QUOTES, 'UTF-8'); ?>" rel="noopener" target="_blank">App Store (iOS)</a>
        <a class="btn" href="<?php echo htmlspecialchars($play_web, ENT_QUOTES, 'UTF-8'); ?>" rel="noopener" target="_blank">Google Play (Android)</a>
      </div>
    </div>

    <!-- Card MOBILE (com contagem regressiva e botão único) -->
    <div class="card" id="mobile-msg" hidden>
      <img class="logo" src="https://guardiantech.site/images/guardiantrack-loja.png" alt="GuardianTech - Download nas lojas oficiais">

      <p class="lead" id="lead-text"></p>

      <p class="hint">
        Redirecionando em <span class="count" id="countdown">3</span>…
      </p>

      <p class="hint" id="fallback-hint">
        Caso não seja redirecionado, use o botão abaixo.
      </p>
      <div class="btns">
        <a class="btn" id="btn-ios" href="<?php echo htmlspecialchars($appstore_web, ENT_QUOTES, 'UTF-8'); ?>" rel="noopener">App Store</a>
        <a class="btn" id="btn-android" href="<?php echo htmlspecialchars($play_web, ENT_QUOTES, 'UTF-8'); ?>" rel="noopener">Google Play</a>
      </div>
    </div>

    <!-- Linha cinza de SO/Navegador (renderizada pelo JS só se detectar ambos) -->
    <div class="sys-hint" id="sys-hint" hidden></div>
  </div>
</body>
</html>
