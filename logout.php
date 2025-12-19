<?php
session_start(); // Avvia la sessione se non è già attiva

// Elimina tutte le variabili di sessione
$_SESSION = array();

// Se è impostato un cookie di sessione, lo invalida
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Distrugge la sessione
session_destroy();

// Reindirizza alla pagina principale
header("Location: index.php");
exit;
