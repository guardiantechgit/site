<?php
// ios.php
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Link web oficial
$web = 'https://apps.apple.com/us/app/guardiantrack-rastreamento/id6747404230';

// Tenta abrir direto no app da App Store (iOS)
$scheme = 'itms-apps://itunes.apple.com/app/id6747404230';

// Usar HTML+JS para tentar o esquema nativo e cair no web
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>App Store • GuardianTrack</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <noscript><meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($web, ENT_QUOTES, 'UTF-8'); ?>"></noscript>
</head>
<body>
<script>
  (function(){
    var scheme = <?php echo json_encode($scheme); ?>;
    var web    = <?php echo json_encode($web); ?>;
    var start = Date.now();
    try { window.location.href = scheme; } catch(e) {}
    setTimeout(function(){ if (Date.now() - start < 1600) window.location.href = web; }, 700);
  })();
</script>
<p>Redirecionando para a App Store… <a href="<?php echo htmlspecialchars($web, ENT_QUOTES, 'UTF-8'); ?>">clique aqui se não for automático</a>.</p>
</body>
</html>
