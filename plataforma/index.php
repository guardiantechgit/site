<?php
/**
 * GuardianTech — Desktop site; Mobile redirect
 *
 * Estratégia:
 *  - Decisão no servidor com Client Hints (Sec-CH-UA-*) quando presentes.
 *  - Fallback para User-Agent clássico.
 *  - Cobertura para iPadOS que reporta "Macintosh" no UA.
 *  - Redirecionamento 302 antes de qualquer saída.
 *  - Cabeçalhos Accept-CH e Vary para consistência e cache correto.
 */

declare(strict_types=1);

// ———————————————————————————————————————————————————————————————————————————
// Config
// ———————————————————————————————————————————————————————————————————————————
const MOBILE_REDIRECT = 'https://guardiantech.site/guardiantrack';

// Solicita Client Hints para próximas navegações (navegadores compatíveis).
header('Accept-CH: Sec-CH-UA, Sec-CH-UA-Mobile, Sec-CH-UA-Platform');

// Evita servir resposta cacheada incorreta em proxies/CDNs.
header('Vary: User-Agent, Sec-CH-UA, Sec-CH-UA-Mobile, Sec-CH-UA-Platform');

// ———————————————————————————————————————————————————————————————————————————
// Detect: Client Hints + UA clássico
// ———————————————————————————————————————————————————————————————————————————
$chMobile   = $_SERVER['HTTP_SEC_CH_UA_MOBILE']   ?? '';              // Chromium: "?1" em mobile
$chPlatform = strtolower(trim($_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '')); // "Android","iOS","Windows","macOS","Linux", etc.
$ua         = $_SERVER['HTTP_USER_AGENT']         ?? '';

// Heurística via Client Hints
$isMobileByCH = ($chMobile === '?1') || in_array($chPlatform, ['android','ios','ipad','ipad os','ipados'], true);

// iPadOS moderno: alguns Safari reportam "Macintosh" + traços de mobile no UA
$isIpadLikeUA = (strpos($ua, 'iPad') !== false) || (strpos($ua, 'Macintosh') !== false && strpos($ua, 'Mobile') !== false);

// Padrões clássicos de UA que caracterizam mobile
$isMobileByUA = (bool) preg_match('/Android|iPhone|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i', $ua);

// Decisão final
$isMobile = $isMobileByCH || $isIpadLikeUA || $isMobileByUA;

// Redireciona mobile antes de emitir qualquer byte
if ($isMobile) {
    header('Location: ' . MOBILE_REDIRECT, true, 302);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <script>
      // Comentário de engenharia: manter leve, sem dependências, com branchs explícitas.
      (function () {
        try {
          var ua = navigator.userAgent || navigator.vendor || window.opera;
          var isMobileUA = /Android|iPhone|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i.test(ua);
          var isIpadOS = /Macintosh/.test(ua) && navigator.maxTouchPoints > 1;
          var isMobileCH = (navigator.userAgentData && navigator.userAgentData.mobile) === true;
          if (isMobileUA || isIpadOS || isMobileCH) {
            window.location.replace('<?php echo MOBILE_REDIRECT; ?>');
          }
        } catch (_) {
          // Silencioso por design: não bloquear renderização desktop.
        }
      })();
    </script>
  
	<title>GuardianTech - Plataforma Web</title>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="author" content="GuardianTech">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Plataforma Web do GuardianTrack - App de Rastreamento.">
	<meta name="keywords" content="rastreadores, bragança paulista, socorro, serra negra, atibaia, zona rural, tratores, caminhão, carga, rastreamento veicular, rastreador de carro, rastreador de moto, rastreador de caminhão, rastreador de barco, rastreamento de cargas, rastreador agrícola, GuardianTech, segurança eletrônica, CFTV, controle de acesso, automação residencial, redes cabeadas, redes Wi-Fi, vigilância, segurança patrimonial, tecnologia para condomínios e empresas">
	<meta name="robots" content="index, follow">
	<meta name="language" content="Portuguese">
	<meta name="revisit-after" content="30 days">
	<meta name="apple-mobile-web-app-title" content="GuardianTech">
	<link rel="canonical" href="https://guardiantech.site" />
	
    <link rel="manifest" href="manifest.json">

	<!-- favicon icons -->
	<link rel="icon" type="image/png" href="../favicon/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="../favicon/favicon.svg" />
	<link rel="shortcut icon" href="../favicon/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="../favicon/apple-touch-icon.png" />
	<link rel="manifest" href="../favicon/site.webmanifest" />

	<!-- Open Graph Meta Tags -->
	<meta property="og:title" content="GuardianTech - Plataforma Web" />
	<meta property="og:type" content="website" />
	<meta property="og:url" content="https://guardiantech.site" />
	<meta property="og:image" content="https://guardiantech.site/images/og-app.jpg" />
	<meta property="og:image:alt" content="GuardianTech - Rastreamento e Segurança" />
	<meta property="og:description" content="Acesse a Plataforma Web de Rastreamento." />
	<meta property="og:site_name" content="GuardianTech" />
	<meta property="og:image:width" content="1200" />
	<meta property="og:image:height" content="630" />
	<meta property="og:image:type" content="image/jpeg" />

	<!-- Twitter Card Meta Tags -->
	<meta name="twitter:card" content="summary_large_image" />
	<meta name="twitter:title" content="GuardianTech - Plataforma Web" />
	<meta name="twitter:description" content="Acesse a Plataforma Web de Rastreamento." />
	<meta name="twitter:image" content="https://guardiantech.site/images/og-app.jpg" />
	<meta name="twitter:image:alt" content="Imagem de rastreamento de veículos e segurança eletrônica" />

	<!-- google fonts preconnect -->
	<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="style.css">

</head>
<body>
    <img src="image/logoGtBk.png" alt="Logo Fundo" class="body-logo-bg">
        <script>
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });
            document.addEventListener('keydown', function(e) {
                if (
                    e.key === 'F12' ||
                    (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J')) ||
                    (e.ctrlKey && (e.key === 'U' || e.key === 'S' || e.key === 'C'))
                ) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
            document.addEventListener('selectstart', function(e) {
                e.preventDefault();
            });
        </script>
    <div class="login-container">
        <form class="login-form" method="POST" action="https://sis.getrak.com/guardiantech">
            <img src="image/guardianTechLogoLogin.png" alt="Logo GuardianTech" class="login-title-logo" style="height: 180px; margin-bottom: 18px; display: block; margin-left: auto; margin-right: auto;">
            <!-- <h2 class="login-title">Plataforma Web</h2>-->
            <div class="input-group">
                <label for="login">Usuário</label>
                <input type="text" name="login" id="login" title="Usuário" placeholder="Digite seu usuário" autocomplete="username" required>
            </div>
            <div class="input-group">
                <label for="senha">Senha</label>
                <input type="password" name="senha" id="senha" title="Senha" placeholder="Digite sua senha" autocomplete="current-password" required>
            </div>
            <div style="display: none; justify-content: flex-end; margin-bottom: 12px;">
                <a href="https://sis.getrak.com/guardiantech/acesso/recuperacao" style="color: #B99955; font-size: 0.98rem; text-decoration: none;">Esqueceu a senha?</a>
            </div>
            <input type="submit" value="Entrar">
        </form>
    </div>
    <footer class="footer">
                <span>&copy; GuardianTech Segurança e Tecnologia 2025 | Todos os direitos reservados.</span>
        <script>
            let deferredPrompt;
            const installBtn = document.getElementById('installBtn');
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                installBtn.style.display = 'block';
            });
            installBtn.addEventListener('click', async () => {
                installBtn.style.display = 'none';
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    await deferredPrompt.userChoice;
                    deferredPrompt = null;
                }
            });
        </script>
    </footer>
</body>
</html>