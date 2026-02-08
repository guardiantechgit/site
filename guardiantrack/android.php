<?php
// android.php
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$play_web = 'https://play.google.com/store/apps/details?id=br.com.getrak.guardiantech';
$play_app = 'market://details?id=br.com.getrak.guardiantech';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Google Play • GuardianTrack</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <noscript><meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($play_web, ENT_QUOTES, 'UTF-8'); ?>"></noscript>
</head>
<body>
<script>
  (function(){
    var scheme = <?php echo json_encode($play_app); ?>;
    var web    = <?php echo json_encode($play_web); ?>;
    try { window.location.href = scheme; } catch(e) { window.location.href = web; }
    setTimeout(function(){ window.location.href = web; }, 800);
  })();
</script>
<p>Redirecionando para a Google Play… <a href="<?php echo htmlspecialchars($play_web, ENT_QUOTES, 'UTF-8'); ?>">clique aqui se não for automático</a>.</p>
</body>
</html>
