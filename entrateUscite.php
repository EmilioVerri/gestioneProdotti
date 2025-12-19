<?php
session_start();

// Verifica se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Gestione logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];
$privilegi = $_SESSION['privilegi'];

// Configurazione database
$host = 'localhost';
$dbname = 'gestioneprodotti';
$db_username = 'root';
$db_password = '';

$errore = '';
$successo = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Errore di connessione: ' . $e->getMessage());
}

// Gestione movimento prodotto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registra_movimento'])) {
    $idProdotto = intval($_POST['prodotto']);
    $tipoMovimento = $_POST['tipo_movimento']; // 'entrata' o 'uscita'

    // Converti in float e arrotonda a 1 decimale
    $quantitaMovimento = round(floatval($_POST['quantita_movimento']), 1);

    $descrizioneMovimento = trim($_POST['descrizione_movimento']);
    $numeroBolla = trim($_POST['numero_bolla']);
    $numeroDato = trim($_POST['numero_dato']);

    if ($idProdotto > 0 && $quantitaMovimento > 0) {
        try {
            // Ottieni il prodotto corrente
            $stmt = $pdo->prepare("SELECT id, nome, quantita, padre FROM prodotti WHERE id = ?");
            $stmt->execute([$idProdotto]);
            $prodotto = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($prodotto) {
                // Calcola la nuova quantità
                $movimento = ($tipoMovimento === 'entrata') ? $quantitaMovimento : -$quantitaMovimento;
                $nuovaQuantita = $prodotto['quantita'] + $movimento;

                // Verifica che la quantità non diventi negativa
                if ($nuovaQuantita < 0) {
                    $errore = 'La quantità non può diventare negativa!';
                } else {
                    // Aggiorna la quantità del prodotto
                    $stmt = $pdo->prepare("UPDATE prodotti SET quantita = ? WHERE id = ?");
                    $stmt->execute([$nuovaQuantita, $idProdotto]);

                    // Ottieni il minimo del prodotto
                    $stmt = $pdo->prepare("SELECT minimo FROM prodotti WHERE id = ?");
                    $stmt->execute([$idProdotto]);
                    $minimo = $stmt->fetchColumn();

                    // Aggiorna campo allarme se quantità < minimo
                    $allarme = ($nuovaQuantita < $minimo) ? 'attivo' : 'nessuno';
                    $stmt = $pdo->prepare("UPDATE prodotti SET allarme = ? WHERE id = ?");
                    $stmt->execute([$allarme, $idProdotto]);

                    // Inserisci il movimento nello storico
                    $dataMovimento = date('d/m/Y H:i');
                    $stmt = $pdo->prepare("INSERT INTO storicomovimenti (idProdotto, movimento, idUtente, descrizione, dataMovimento, bollaNumero, datoNumero, idPadre) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $prodotto['nome'],
                        $movimento,
                        $username,
                        $descrizioneMovimento,
                        $dataMovimento,
                        $numeroBolla,
                        $numeroDato,
                        $prodotto['padre']
                    ]);

                    $tipoMsg = ($tipoMovimento === 'entrata') ? 'Entrata' : 'Uscita';
                    $successo = "$tipoMsg registrata con successo! Nuova quantità: $nuovaQuantita";
                }
            } else {
                $errore = 'Prodotto non trovato';
            }
        } catch (PDOException $e) {
            $errore = 'Errore durante la registrazione: ' . $e->getMessage();
        }
    } else {
        $errore = 'Seleziona un prodotto e inserisci una quantità valida';
    }
}

// Carica tutti i gruppi padre
try {
    $stmt = $pdo->query("SELECT DISTINCT padre FROM prodotti WHERE padre IS NOT NULL AND padre != '' ORDER BY padre ASC");
    $gruppiPadre = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $gruppiPadre = [];
}

// Carica tutti i prodotti raggruppati per padre
$prodotti_per_padre = [];
try {
    $stmt = $pdo->query("SELECT * FROM prodotti WHERE padre IS NOT NULL AND padre != '' ORDER BY padre ASC, nome ASC");
    $prodotti = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($prodotti as $prodotto) {
        // Escludi il prodotto padre stesso
        if ($prodotto['nome'] === $prodotto['padre']) continue;

        $padre = $prodotto['padre'];
        if (!isset($prodotti_per_padre[$padre])) {
            $prodotti_per_padre[$padre] = [];
        }
        $prodotti_per_padre[$padre][] = $prodotto;
    }
} catch (PDOException $e) {
    $prodotti_per_padre = [];
}

// Conta prodotti con allarme
$prodottiAllarme = [];
foreach ($prodotti_per_padre as $padre => $figli) {
    foreach ($figli as $p) {
        if ($p['allarme'] === 'attivo' || $p['quantita'] < $p['minimo']) {
            $prodottiAllarme[] = $p;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrate/Uscite - Gestione Prodotti</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: -280px;
            top: 0;
            width: 280px;
            height: 100vh;
            background: #1a1a1a;
            transition: left 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 30px 20px;
            border-bottom: 1px solid #333;
            text-align: center;
        }

        .sidebar-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffffff 0%, #e0e0e0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 36px;
            font-weight: bold;
            color: #1a1a1a;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .sidebar-title {
            color: white;
            font-size: 18px;
            font-weight: 600;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            padding: 15px 25px;
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            text-decoration: none;
        }

        .menu-item:hover {
            background: #2d2d2d;
            border-left-color: white;
            transform: translateX(5px);
        }

        .menu-item.active {
            background: #2d2d2d;
            border-left-color: white;
        }

        .menu-icon {
            font-size: 24px;
            width: 30px;
            text-align: center;
        }

        .menu-text {
            font-size: 15px;
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            z-index: 999;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #1a1a1a;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 998;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px 10px;
            transition: all 0.3s;
            border-radius: 5px;
        }

        .menu-toggle:hover {
            background: #2d2d2d;
            transform: scale(1.05);
        }

        .navbar-logo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffffff 0%, #e0e0e0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            color: #1a1a1a;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .navbar h1 {
            font-size: 24px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn-logout {
            background: white;
            color: #1a1a1a;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-logout:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        .container {
            max-width: 1400px;
            margin: 100px auto 40px;
            padding: 0 20px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-header h2 {
            color: #1a1a1a;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 14px;
        }

        /* Alert Box */
        .alert-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-left: 5px solid #ff6b6b;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .alert-icon {
            font-size: 32px;
        }

        .alert-title {
            color: #1a1a1a;
            font-size: 20px;
            font-weight: 600;
        }

        .prodotti-allarme {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .prodotto-allarme-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ff6b6b;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .prodotto-allarme-nome {
            font-weight: 600;
            color: #d32f2f;
            margin-bottom: 5px;
        }

        .prodotto-allarme-quantita {
            font-size: 14px;
            color: #666;
        }

        /* Search Bar */
        .search-container {
            position: relative;
        }

        .search-box {
            width: 100%;
            padding: 14px 50px 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f9f9f9;
            font-family: inherit;
        }

        .search-box:focus {
            outline: none;
            border-color: #1a1a1a;
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #666;
            pointer-events: none;
        }

        .clear-search {
            position: absolute;
            right: 50px;
            top: 50%;
            transform: translateY(-50%);
            background: #1a1a1a;
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: none;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .clear-search.active {
            display: flex;
        }

        .clear-search:hover {
            background: #000;
            transform: translateY(-50%) scale(1.1);
        }

        .search-results-info {
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
            text-align: center;
            display: none;
            padding: 10px;
            background: #f0f2f8;
            border-radius: 8px;
        }

        .search-results-info.active {
            display: block;
        }

        .highlight {
            background-color: #ffeb3b;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 600;
        }

        /* Section Card */
        .section-card {
            background: white;
            padding: 35px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            animation: slideUp 0.5s ease;
            margin-bottom: 30px;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-icon {
            font-size: 32px;
        }

        .section-header h3 {
            color: #1a1a1a;
            font-size: 22px;
        }

        /* Padre Groups */
        .padre-group {
            margin-bottom: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .padre-group:hover {
            border-color: #1a1a1a;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .padre-header {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            padding: 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .padre-header:hover {
            background: linear-gradient(135deg, #f0f2f8 0%, #f8f9ff 100%);
        }

        .padre-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .padre-badge {
            background: #1a1a1a;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }

        .padre-toggle {
            font-size: 24px;
            transition: transform 0.3s;
            color: #1a1a1a;
        }

        .padre-toggle.expanded {
            transform: rotate(180deg);
        }

        .padre-content {
            display: none;
            padding: 20px;
            background: #fafafa;
        }

        .padre-content.expanded {
            display: block;
        }

        .prodotti-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }

        .prodotto-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s;
        }

        .prodotto-card:hover {
            border-color: #1a1a1a;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .prodotto-card.allarme {
            border-color: #ff6b6b;
            background: #fff5f5;
        }

        .prodotto-nome {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .badge-allarme {
            background: #ff6b6b;
            color: white;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
        }

        .info-value {
            color: #1a1a1a;
        }

        .prodotto-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .btn-action {
            flex: 1;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-entrata {
            background: #4caf50;
            color: white;
        }

        .btn-entrata:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
        }

        .btn-uscita {
            background: #ff9800;
            color: white;
        }

        .btn-uscita:hover {
            background: #fb8c00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4);
        }

        /* Modal Quick */
        .modal-quick {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10002;
            animation: fadeIn 0.3s;
        }

        .modal-quick.active {
            display: flex;
        }

        .modal-quick-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            animation: scaleIn 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-quick-header {
            padding: 25px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-quick-header h3 {
            color: #1a1a1a;
            font-size: 22px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 32px;
            color: #999;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-close:hover {
            color: #333;
            transform: rotate(90deg);
        }

        .modal-quick-body {
            padding: 25px;
        }

        .quick-product-info {
            text-align: center;
            margin-bottom: 25px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
        }

        .quick-icon-wrapper {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quick-icon-wrapper.entrata {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        }

        .quick-icon-wrapper.uscita {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
        }

        .quick-icon {
            font-size: 40px;
        }

        .quick-product-name {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .quick-product-qty {
            font-size: 16px;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #1a1a1a;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="number"],
        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f9f9f9;
            font-family: inherit;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #1a1a1a;
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .modal-quick-footer {
            padding: 20px 25px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 130px;
        }

        .btn-cancel {
            background: #e0e0e0;
            color: #1a1a1a;
        }

        .btn-cancel:hover {
            background: #d0d0d0;
            transform: translateY(-2px);
        }

        .btn-confirm {
            background: #1a1a1a;
            color: white;
        }

        .btn-confirm:hover {
            background: #000;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }

        /* Custom Confirm Dialog */
        .confirm-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10003;
            animation: fadeIn 0.3s;
        }

        .confirm-dialog.active {
            display: flex;
        }

        .confirm-content {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            animation: scaleIn 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }

        .confirm-icon-wrapper {
            width: 90px;
            height: 90px;
            margin: 0 auto 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }

        .confirm-icon-wrapper.entrata {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        }

        .confirm-icon-wrapper.uscita {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .confirm-icon {
            font-size: 50px;
        }

        .confirm-title {
            font-size: 26px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 15px;
        }

        .confirm-message {
            color: #666;
            margin-bottom: 10px;
            line-height: 1.6;
            font-size: 16px;
        }

        .confirm-details {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }

        .confirm-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .confirm-detail-row:last-child {
            border-bottom: none;
        }

        .confirm-detail-label {
            font-weight: 600;
            color: #666;
        }

        .confirm-detail-value {
            color: #1a1a1a;
            font-weight: 600;
        }

        .confirm-detail-value.highlight {
            color: #00cc00;
            font-size: 18px;
        }

        .confirm-detail-value.warning {
            color: #ff6b6b;
            font-size: 18px;
        }

        .confirm-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }

        /* Popup notification */
        .popup-notification {
            position: fixed;
            top: -100px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 300px;
            transition: top 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .popup-notification.show {
            top: 90px;
        }

        .popup-notification.success {
            border-left: 5px solid #00cc00;
        }

        .popup-notification.error {
            border-left: 5px solid #ff4444;
        }

        .popup-icon {
            font-size: 32px;
        }

        .popup-content {
            flex: 1;
        }

        .popup-title {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .popup-message {
            font-size: 14px;
            color: #666;
        }

        .popup-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .popup-close:hover {
            background: #f0f0f0;
            color: #333;
        }

        @media (max-width: 768px) {
            .navbar h1 {
                font-size: 18px;
            }

            .user-info span {
                display: none;
            }

            .prodotti-grid {
                grid-template-columns: 1fr;
            }

            .prodotti-allarme {
                grid-template-columns: 1fr;
            }

            .confirm-content {
                padding: 30px 20px;
            }

            .confirm-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Popup Notification -->
    <div class="popup-notification" id="popupNotification">
        <span class="popup-icon" id="popupIcon"></span>
        <div class="popup-content">
            <div class="popup-title" id="popupTitle"></div>
            <div class="popup-message" id="popupMessage"></div>
        </div>
        <button class="popup-close" onclick="closePopup()">×</button>
    </div>

    <!-- Custom Confirm Dialog -->
    <div class="confirm-dialog" id="confirmDialog">
        <div class="confirm-content">
            <div class="confirm-icon-wrapper" id="confirmIconWrapper">
                <span class="confirm-icon" id="confirmIcon"></span>
            </div>
            <div class="confirm-title" id="confirmTitle"></div>
            <div class="confirm-message" id="confirmMessage"></div>
            <div class="confirm-details" id="confirmDetails"></div>
            <div class="confirm-buttons">
                <button class="btn btn-cancel" onclick="closeConfirm()">Annulla</button>
                <button class="btn btn-confirm" onclick="confirmSubmit()">Conferma</button>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">GP</div>
            <div class="sidebar-title">Gestione Prodotti</div>
        </div>

        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <span class="menu-icon">🏠</span>
                <span class="menu-text">Dashboard</span>
            </a>
            <a href="pagina_controllo.php" class="menu-item">
                <span class="menu-icon">⚙️</span>
                <span class="menu-text">Pagina di Controllo</span>
            </a>
            <a href="prodotti.php" class="menu-item">
                <span class="menu-icon">📦</span>
                <span class="menu-text">Modifica Prodotti</span>
            </a>
            <a href="entrateUscite.php" class="menu-item active">
                <span class="menu-icon">🏷️</span>
                <span class="menu-text">Registra Entrate/Uscite</span>
            </a>
            <a href="storicoEntrateUscite.php" class="menu-item">
                <span class="menu-icon">📈</span>
                <span class="menu-text">Storico Entrate/Uscite</span>
            </a>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-left">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div class="navbar-logo">GP</div>
            <h1>Entrate/Uscite Prodotti</h1>
        </div>
        <div class="user-info">
            <span>Benvenuto, <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <a href="./logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>Gestione Movimenti Magazzino</h2>
            <p>Registra entrate e uscite dei prodotti dal magazzino</p>
        </div>

        <!-- Alert prodotti con scorte basse -->
        <?php if (count($prodottiAllarme) > 0): ?>
            <div class="alert-box">
                <div class="alert-header">
                    <span class="alert-icon">⚠️</span>
                    <div class="alert-title">Attenzione: <?php echo count($prodottiAllarme); ?> prodott<?php echo count($prodottiAllarme) > 1 ? 'i' : 'o'; ?> con scorte basse!</div>
                </div>
                <div class="prodotti-allarme">
                    <?php foreach ($prodottiAllarme as $prod): ?>
                        <div class="prodotto-allarme-card" style="cursor: pointer;"
                            onclick="scrollToAndExpandPadre('<?php echo htmlspecialchars($prod['padre']); ?>', '<?php echo htmlspecialchars($prod['nome']); ?>')">
                            <div class="prodotto-allarme-nome"><?php echo htmlspecialchars($prod['nome']); ?></div>
                            <div class="prodotto-allarme-quantita">Gruppo: <strong><?php echo htmlspecialchars($prod['padre']); ?></strong></div>
                            <div class="prodotto-allarme-quantita">Quantità: <strong><?php echo $prod['quantita']; ?></strong> / Allarme: <strong><?php echo $prod['minimo']; ?></strong></div>
                            <div style="margin-top: 8px; font-size: 12px; color: #1a1a1a; font-weight: 600;">
                                <i class="fas fa-hand-pointer"></i> Clicca per visualizzare
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Lista Prodotti per Padre -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-icon">📋</span>
                <h3>Prodotti per Gruppo</h3>
            </div>

            <!-- AGGIUNGI QUESTA BARRA DI RICERCA QUI -->
            <div class="search-container" style="margin-bottom: 25px;">
                <input type="text"
                    class="search-box"
                    id="searchInput"
                    placeholder="Cerca prodotti o gruppi..."
                    autocomplete="off">
                <button class="clear-search" id="clearSearch" onclick="clearSearch()">×</button>
                <span class="search-icon">🔍</span>
            </div>
            <div class="search-results-info" id="searchResultsInfo"></div>
            <!-- FINE BARRA DI RICERCA -->

            <?php if (!empty($prodotti_per_padre)): ?>


                <?php foreach ($prodotti_per_padre as $padre => $figli): ?>
                    <div class="padre-group"
                        data-padre-name="<?php echo strtolower(htmlspecialchars($padre)); ?>"
                        id="padre-<?php echo htmlspecialchars($padre); ?>">
                        <div class="padre-header" onclick="togglePadreContent(this)">
                            <div class="padre-title">
                                <i class="fas fa-folder"></i>
                                <?php echo htmlspecialchars($padre); ?>
                                <span class="padre-badge"><?php echo count($figli); ?> prodotti</span>
                            </div>
                            <span class="padre-toggle">▼</span>
                        </div>

                        <div class="padre-content">
                            <div class="prodotti-grid">
                                <?php foreach ($figli as $prod): ?>
                                    <div class="prodotto-card <?php echo ($prod['quantita'] < $prod['minimo']) ? 'allarme' : ''; ?>"
                                        data-product-name="<?php echo strtolower(htmlspecialchars($prod['nome'])); ?>"
                                        data-product-description="<?php echo strtolower(htmlspecialchars($prod['descrizione'])); ?>"
                                        data-product-fornitore="<?php echo strtolower(htmlspecialchars($prod['fornitore'])); ?>"
                                        id="product-<?php echo $prod['id']; ?>">
                                        <div class="prodotto-nome">
                                            <?php echo htmlspecialchars($prod['nome']); ?>
                                            <?php if ($prod['quantita'] < $prod['minimo']): ?>
                                                <span class="badge-allarme">⚠️ BASSO</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="info-row">
                                            <span class="info-label">Quantità:</span>
                                            <span class="info-value"><strong><?php echo $prod['quantita']; ?></strong> pz</span>
                                        </div>

                                        <div class="info-row">
                                            <span class="info-label">Allarme:</span>
                                            <span class="info-value"><?php echo $prod['minimo']; ?> pz</span>
                                        </div>

                                        <div class="info-row">
                                            <span class="info-label">Fornitore:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($prod['fornitore']); ?></span>
                                        </div>

                                        <?php if (!empty($prod['descrizione'])): ?>
                                            <div class="info-row">
                                                <span class="info-label">Descrizione:</span>
                                                <span class="info-value"><?php echo htmlspecialchars($prod['descrizione']); ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="prodotto-actions">
                                            <button class="btn-action btn-entrata"
                                                onclick='openQuickMovement(<?php echo json_encode($prod); ?>, "entrata")'>
                                                <i class="fas fa-arrow-down"></i> Entrata
                                            </button>
                                            <button class="btn-action btn-uscita"
                                                onclick='openQuickMovement(<?php echo json_encode($prod); ?>, "uscita")'>
                                                <i class="fas fa-arrow-up"></i> Uscita
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-box-open" style="font-size: 80px; color: #ddd; margin-bottom: 20px;"></i>
                    <h3 style="color: #1a1a1a; margin-bottom: 10px;">Nessun prodotto trovato</h3>
                    <p style="color: #666;">Aggiungi prodotti dalla sezione "Modifica Prodotti"</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Quick Movement -->
    <div class="modal-quick" id="modalQuick">
        <div class="modal-quick-content">
            <div class="modal-quick-header">
                <h3 id="modalQuickTitle">Movimento Rapido</h3>
                <button class="modal-close" onclick="closeQuickModal()">×</button>
            </div>
            <form id="formQuick" method="POST" action="">
                <input type="hidden" id="quick_prodotto_id" name="prodotto">
                <input type="hidden" id="quick_tipo_movimento" name="tipo_movimento">
                <input type="hidden" name="registra_movimento" value="1">

                <div class="modal-quick-body">
                    <div class="quick-product-info">
                        <div class="quick-icon-wrapper" id="quickIconWrapper">
                            <span class="quick-icon" id="quickIcon"></span>
                        </div>
                        <div class="quick-product-name" id="quickProductName"></div>
                        <div class="quick-product-qty" id="quickProductQty"></div>
                    </div>

                    <div class="form-group">
                        <label for="quick_quantita_movimento">Quantità *</label>
                        <input type="number"
                            id="quick_quantita_movimento"
                            name="quantita_movimento"
                            min="0.1"
                            step="0.1"
                            value="1"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="quick_numero_bolla">Numero Ista Taglio</label>
                        <input type="text" id="quick_numero_bolla" name="numero_bolla" placeholder="Opzionale">
                    </div>

                    <div class="form-group">
                        <label for="quick_numero_dato">Numero Offerta</label>
                        <input type="text" id="quick_numero_dato" name="numero_dato" placeholder="Opzionale">
                    </div>

                    <div class="form-group">
                        <label for="quick_descrizione_movimento">Descrizione</label>
                        <textarea id="quick_descrizione_movimento" name="descrizione_movimento" placeholder="Note aggiuntive (opzionale)"></textarea>
                    </div>
                </div>
                <div class="modal-quick-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeQuickModal()">Annulla</button>
                    <button type="button" class="btn btn-confirm" onclick="handleQuickSubmit()">Conferma</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variabili globali
        let currentQuickProduct = null;
        let currentQuickType = null;
        let pendingForm = false;

        // Funzioni Popup
        function showPopup(type, title, message) {
            const popup = document.getElementById('popupNotification');
            const icon = document.getElementById('popupIcon');
            const titleEl = document.getElementById('popupTitle');
            const messageEl = document.getElementById('popupMessage');

            popup.className = 'popup-notification';

            if (type === 'success') {
                popup.classList.add('success');
                icon.innerHTML = '<i class="fas fa-check-circle" style="color: #00cc00;"></i>';
            } else {
                popup.classList.add('error');
                icon.innerHTML = '<i class="fas fa-times-circle" style="color: #ff4444;"></i>';
            }

            titleEl.textContent = title;
            messageEl.textContent = message;

            setTimeout(() => {
                popup.classList.add('show');
            }, 100);

            setTimeout(() => {
                closePopup();
            }, 5000);
        }

        function closePopup() {
            const popup = document.getElementById('popupNotification');
            popup.classList.remove('show');
        }

        // Mostra popup se c'è un messaggio
        <?php if ($successo): ?>
            showPopup('success', 'Movimento Registrato!', '<?php echo addslashes($successo); ?>');
        <?php endif; ?>

        <?php if ($errore): ?>
            showPopup('error', 'Errore', '<?php echo addslashes($errore); ?>');
        <?php endif; ?>

        // Toggle Sidebar
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function toggleSidebar() {
            const isActive = sidebar.classList.contains('active');

            if (isActive) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });

        overlay.addEventListener('click', toggleSidebar);

        // Menu items
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });

        // Toggle Padre Content
        function togglePadreContent(header) {
            const content = header.nextElementSibling;
            const toggle = header.querySelector('.padre-toggle');

            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                toggle.classList.remove('expanded');
            } else {
                content.classList.add('expanded');
                toggle.classList.add('expanded');
            }
        }

        // Quick Movement Functions
        const modalQuick = document.getElementById('modalQuick');

        function openQuickMovement(prodotto, tipo) {
            currentQuickProduct = prodotto;
            currentQuickType = tipo;

            const quickIcon = document.getElementById('quickIcon');
            const quickIconWrapper = document.getElementById('quickIconWrapper');
            const modalTitle = document.getElementById('modalQuickTitle');
            const productName = document.getElementById('quickProductName');
            const productQty = document.getElementById('quickProductQty');
            const quantitaInput = document.getElementById('quick_quantita_movimento');

            quickIconWrapper.className = 'quick-icon-wrapper';

            if (tipo === 'entrata') {
                quickIconWrapper.classList.add('entrata');
                quickIcon.innerHTML = '<i class="fas fa-arrow-down" style="color: #4caf50;"></i>';
                modalTitle.textContent = 'Entrata Rapida';
                quantitaInput.removeAttribute('max');
            } else {
                quickIconWrapper.classList.add('uscita');
                quickIcon.innerHTML = '<i class="fas fa-arrow-up" style="color: #ff9800;"></i>';
                modalTitle.textContent = 'Uscita Rapida';
                quantitaInput.max = prodotto.quantita;
            }

            productName.textContent = prodotto.nome;
            productQty.textContent = `Quantità disponibile: ${prodotto.quantita} pz | Allarme: ${prodotto.minimo} pz`;

            // Reset form e imposta valori
            const formQuick = document.getElementById('formQuick');
            formQuick.reset();

            document.getElementById('quick_prodotto_id').value = prodotto.id;
            document.getElementById('quick_tipo_movimento').value = tipo;
            document.getElementById('quick_quantita_movimento').value = 1;

            // Mostra modal
            modalQuick.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeQuickModal() {
            modalQuick.classList.remove('active');
            document.body.style.overflow = '';
            currentQuickProduct = null;
            currentQuickType = null;
        }

        function handleQuickSubmit() {
            // Converti in float e arrotonda a 1 decimale
            const quantita = Math.round(parseFloat(document.getElementById('quick_quantita_movimento').value) * 10) / 10;

            if (isNaN(quantita) || quantita <= 0) {
                alert('Inserisci una quantità valida!');
                return;
            }

            if (currentQuickType === 'uscita' && quantita > currentQuickProduct.quantita) {
                alert('Quantità non disponibile! Disponibili: ' + currentQuickProduct.quantita + ' unità');
                return;
            }

            const numeroBolla = document.getElementById('quick_numero_bolla').value;
            const numeroDato = document.getElementById('quick_numero_dato').value;

            // Chiudi modal
            modalQuick.classList.remove('active');
            document.body.style.overflow = '';

            // Mostra conferma
            pendingForm = 'quick';
            showConfirm(currentQuickType, currentQuickProduct.nome, quantita, currentQuickProduct.quantita, numeroBolla, numeroDato);
        }

        // Custom Confirm Dialog
        const confirmDialog = document.getElementById('confirmDialog');

        function showConfirm(tipoMovimento, nomeProdotto, quantita, quantitaAttuale, numeroBolla, numeroDato) {
            const confirmIcon = document.getElementById('confirmIcon');
            const confirmIconWrapper = document.getElementById('confirmIconWrapper');
            const confirmTitle = document.getElementById('confirmTitle');
            const confirmMessage = document.getElementById('confirmMessage');
            const confirmDetails = document.getElementById('confirmDetails');

            // Reset classes
            confirmIconWrapper.className = 'confirm-icon-wrapper';

            // Configura in base al tipo
            if (tipoMovimento === 'entrata') {
                confirmIconWrapper.classList.add('entrata');
                confirmIcon.innerHTML = '<i class="fas fa-arrow-down" style="color: #4caf50;"></i>';
                confirmTitle.textContent = 'Conferma Entrata';
                confirmMessage.textContent = 'Stai per registrare un\'entrata di prodotto nel magazzino.';
            } else {
                confirmIconWrapper.classList.add('uscita');
                confirmIcon.innerHTML = '<i class="fas fa-arrow-up" style="color: #ff9800;"></i>';
                confirmTitle.textContent = 'Conferma Uscita';
                confirmMessage.textContent = 'Stai per registrare un\'uscita di prodotto dal magazzino.';
            }

            // Calcola nuova quantità
            const nuovaQuantita = tipoMovimento === 'entrata' ?
                parseInt(quantitaAttuale) + parseInt(quantita) :
                parseInt(quantitaAttuale) - parseInt(quantita);

            // Crea i dettagli
            // Crea i dettagli
            let detailsHTML = `
    <div class="confirm-detail-row">
        <span class="confirm-detail-label">Prodotto:</span>
        <span class="confirm-detail-value">${nomeProdotto}</span>
    </div>
    <div class="confirm-detail-row">
        <span class="confirm-detail-label">Quantità movimento:</span>
        <span class="confirm-detail-value ${tipoMovimento === 'entrata' ? 'highlight' : 'warning'}">
            ${tipoMovimento === 'entrata' ? '+' : '-'}${parseFloat(quantita).toFixed(1)} unità
        </span>
    </div>
                <div class="confirm-detail-row">
                    <span class="confirm-detail-label">Quantità attuale:</span>
                    <span class="confirm-detail-value">${quantitaAttuale} unità</span>
                </div>
                <div class="confirm-detail-row">
                    <span class="confirm-detail-label">Nuova quantità:</span>
                    <span class="confirm-detail-value" style="font-size: 20px; color: #1a1a1a;">${nuovaQuantita} unità</span>
                </div>
            `;

            if (numeroBolla && numeroBolla.trim() !== '') {
                detailsHTML += `
                    <div class="confirm-detail-row">
                        <span class="confirm-detail-label">Numero Ista Taglio:</span>
                        <span class="confirm-detail-value">${numeroBolla}</span>
                    </div>
                `;
            }

            if (numeroDato && numeroDato.trim() !== '') {
                detailsHTML += `
                    <div class="confirm-detail-row">
                        <span class="confirm-detail-label">Numero Offerta:</span>
                        <span class="confirm-detail-value">${numeroDato}</span>
                    </div>
                `;
            }

            confirmDetails.innerHTML = detailsHTML;

            // Mostra il dialog
            confirmDialog.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeConfirm() {
            confirmDialog.classList.remove('active');
            document.body.style.overflow = '';

            if (!pendingForm) {
                currentQuickProduct = null;
                currentQuickType = null;
            }
        }

        function confirmSubmit() {
            if (pendingForm === 'quick') {
                const formQuick = document.getElementById('formQuick');

                // Chiudi il dialog
                confirmDialog.classList.remove('active');
                document.body.style.overflow = '';

                // Reset variabili
                pendingForm = false;
                currentQuickProduct = null;
                currentQuickType = null;

                // Invia il form
                formQuick.submit();
            }
        }

        // Gestione click fuori dal dialog
        confirmDialog.addEventListener('click', function(e) {
            if (e.target === confirmDialog) {
                closeConfirm();
            }
        });

        // Close modal on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (modalQuick.classList.contains('active')) {
                    closeQuickModal();
                }
                if (confirmDialog.classList.contains('active')) {
                    closeConfirm();
                }
            }
        });

        // Click outside quick modal
        modalQuick.addEventListener('click', function(e) {
            if (e.target === modalQuick) {
                closeQuickModal();
            }
        });








        // Funzione di ricerca
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearch');
        const searchResultsInfo = document.getElementById('searchResultsInfo');
        const padreGroups = document.querySelectorAll('.padre-group');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();

            if (searchTerm.length > 0) {
                clearSearchBtn.classList.add('active');
            } else {
                clearSearchBtn.classList.remove('active');
            }

            performSearch(searchTerm);
        });

        function performSearch(searchTerm) {
            let visibleGroups = 0;
            let totalMatches = 0;

            padreGroups.forEach(group => {
                const padreName = group.getAttribute('data-padre-name');
                const products = group.querySelectorAll('.prodotto-card');
                let hasVisibleProducts = false;
                let groupMatches = 0;

                // Verifica se il nome del padre corrisponde
                const padreMatches = padreName.includes(searchTerm);

                products.forEach(product => {
                    const productName = product.getAttribute('data-product-name');
                    const productDescription = product.getAttribute('data-product-description') || '';
                    const productFornitore = product.getAttribute('data-product-fornitore') || '';

                    const matches = searchTerm === '' ||
                        productName.includes(searchTerm) ||
                        productDescription.includes(searchTerm) ||
                        productFornitore.includes(searchTerm) ||
                        padreMatches;

                    if (matches) {
                        product.style.display = 'block';
                        hasVisibleProducts = true;
                        groupMatches++;

                        // Evidenzia il termine cercato
                        if (searchTerm !== '') {
                            highlightText(product, searchTerm);
                        } else {
                            removeHighlight(product);
                        }
                    } else {
                        product.style.display = 'none';
                    }
                });

                if (searchTerm === '') {
                    group.style.display = 'block';
                    const content = group.querySelector('.padre-content');
                    content.classList.remove('expanded');
                    const toggle = group.querySelector('.padre-toggle');
                    toggle.classList.remove('expanded');
                } else if (hasVisibleProducts || padreMatches) {
                    group.style.display = 'block';
                    visibleGroups++;
                    totalMatches += groupMatches;

                    // Espandi automaticamente il gruppo se ha corrispondenze
                    const content = group.querySelector('.padre-content');
                    content.classList.add('expanded');
                    const toggle = group.querySelector('.padre-toggle');
                    toggle.classList.add('expanded');
                } else {
                    group.style.display = 'none';
                }
            });

            updateSearchInfo(searchTerm, visibleGroups, totalMatches);
        }

        function updateSearchInfo(searchTerm, visibleGroups, totalMatches) {
            if (searchTerm === '') {
                searchResultsInfo.classList.remove('active');
            } else {
                searchResultsInfo.classList.add('active');
                if (totalMatches === 0) {
                    searchResultsInfo.innerHTML = `<i class="fas fa-search"></i> Nessun risultato trovato per "<strong>${searchTerm}</strong>"`;
                    searchResultsInfo.style.background = '#ffe6e6';
                    searchResultsInfo.style.color = '#cc0000';
                } else {
                    searchResultsInfo.innerHTML = `<i class="fas fa-check-circle"></i> Trovati <strong>${totalMatches}</strong> prodotti in <strong>${visibleGroups}</strong> gruppi`;
                    searchResultsInfo.style.background = '#e8f5e9';
                    searchResultsInfo.style.color = '#2e7d32';
                }
            }
        }

        function highlightText(element, searchTerm) {
            // Rimuovi eventuali highlight precedenti
            removeHighlight(element);

            const productName = element.querySelector('.prodotto-nome');
            if (productName) {
                const text = productName.textContent;
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                if (regex.test(text)) {
                    const highlighted = text.replace(regex, '<span class="highlight">$1</span>');
                    productName.innerHTML = highlighted;
                }
            }
        }

        function removeHighlight(element) {
            const productName = element.querySelector('.prodotto-nome');
            if (productName) {
                const text = productName.textContent;
                productName.textContent = text;
            }
        }

        function clearSearch() {
            searchInput.value = '';
            clearSearchBtn.classList.remove('active');
            performSearch('');
            searchInput.focus();
        }

        // Funzione per scrollare e espandere un gruppo padre specifico
        function scrollToAndExpandPadre(padreName, productName) {
            // Trova il gruppo padre
            const padreGroup = document.getElementById('padre-' + padreName);

            if (padreGroup) {
                // Espandi il gruppo
                const content = padreGroup.querySelector('.padre-content');
                const toggle = padreGroup.querySelector('.padre-toggle');

                if (!content.classList.contains('expanded')) {
                    content.classList.add('expanded');
                    toggle.classList.add('expanded');
                }

                // Scroll al gruppo con un offset per la navbar
                setTimeout(() => {
                    const offset = 100;
                    const elementPosition = padreGroup.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - offset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });

                    // Evidenzia temporaneamente il gruppo
                    padreGroup.style.boxShadow = '0 0 0 4px #ff6b6b';
                    setTimeout(() => {
                        padreGroup.style.boxShadow = '';
                    }, 2000);

                    // Cerca e evidenzia il prodotto specifico
                    setTimeout(() => {
                        const products = padreGroup.querySelectorAll('.prodotto-card');
                        products.forEach(product => {
                            const cardName = product.querySelector('.prodotto-nome');
                            if (cardName && cardName.textContent.includes(productName)) {
                                product.style.boxShadow = '0 0 0 3px #ff6b6b';
                                product.style.transform = 'scale(1.02)';
                                setTimeout(() => {
                                    product.style.boxShadow = '';
                                    product.style.transform = '';
                                }, 2000);
                            }
                        });
                    }, 500);
                }, 100);
            }
        }
    </script>
</body>

</html>