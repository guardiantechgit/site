<?php
/**
 * Autoload simples para PHPMailer (sem Composer).
 * Coloque este arquivo em: email-templates/phpmailer/autoload.php
 */
spl_autoload_register(function ($class) {
    // Namespace base do PHPMailer
    $prefix = 'PHPMailer\\PHPMailer\\';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return; // não é PHPMailer, ignora
    }

    $relativeClass = substr($class, $len);
    $file = __DIR__ . '/src/' . $relativeClass . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
