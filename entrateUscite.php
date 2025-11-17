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
    $quantitaMovimento = intval($_POST['quantita_movimento']);
    $descrizioneMovimento = trim($_POST['descrizione_movimento']);
    $numeroBolla = trim($_POST['numero_bolla']);
    $numeroDato = trim($_POST['numero_dato']);
    
    if ($idProdotto > 0 && $quantitaMovimento > 0) {
        try {
            // Ottieni il prodotto corrente
            $stmt = $pdo->prepare("SELECT id, nome, quantita FROM prodotti WHERE id = ?");
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
                    
                    // Aggiorna campo allarme se quantità < 5
                    $allarme = ($nuovaQuantita < 5) ? 'attivo' : 'nessuno';
                    $stmt = $pdo->prepare("UPDATE prodotti SET allarme = ? WHERE id = ?");
                    $stmt->execute([$allarme, $idProdotto]);
                    
                    // Inserisci il movimento nello storico
                    $dataMovimento = date('d/m/Y H:i');
                    $stmt = $pdo->prepare("INSERT INTO storicomovimenti (idProdotto, movimento, idUtente, descrizione, dataMovimento, bollaNumero, datoNumero) VALUES (?, ?, ?, ?, ?, ?,?)");
                    $stmt->execute([
                        $prodotto['nome'],
                        $movimento,
                        $username,
                        $descrizioneMovimento,
                        $dataMovimento,
                        $numeroBolla,
                        $numeroDato
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

// Carica tutti i prodotti - prima quelli con quantità < 5, poi gli altri
try {
    $stmt = $pdo->query("SELECT * FROM prodotti ORDER BY CASE WHEN quantita < 5 THEN 0 ELSE 1 END, nome ASC");
    $prodotti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $prodotti = [];
}

// Conta prodotti con allarme
$prodottiAllarme = array_filter($prodotti, function($p) {
    return $p['allarme'] === 'attivo' || $p['quantita'] < 5;
});
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrate/Uscite - Gestione Prodotti</title>
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
        
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
            border-top: 1px solid #333;
            color: #999;
            font-size: 12px;
            text-align: center;
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
        
        /* Form Section */
        .section-card {
            background: white;
            padding: 35px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            animation: slideUp 0.5s ease;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #1a1a1a;
            font-weight: 500;
            font-size: 14px;
        }
        
        select,
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
        
        select:focus,
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #1a1a1a;
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .tipo-movimento-group {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .radio-option {
            flex: 1;
            position: relative;
        }
        
        .radio-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .radio-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            background: #f9f9f9;
            font-weight: 600;
        }
        
        .radio-option input[type="radio"]:checked + .radio-label {
            border-color: #1a1a1a;
            background: #1a1a1a;
            color: white;
            transform: scale(1.05);
        }
        
        .radio-label:hover {
            border-color: #666;
        }
        
        .radio-icon {
            font-size: 24px;
        }
        
        button[type="submit"] {
            background: #1a1a1a;
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        button[type="submit"]:hover {
            background: #000;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        
        .messaggio {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            animation: fadeIn 0.5s;
            font-size: 14px;
        }
        
        .errore {
            background: #ffe6e6;
            color: #cc0000;
            border: 1px solid #ff9999;
        }
        
        .successo {
            background: #e6ffe6;
            color: #006600;
            border: 1px solid #99ff99;
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
        
        .prodotto-info {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }
        
        .prodotto-info.show {
            display: block;
            animation: fadeIn 0.3s;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            color: #1a1a1a;
        }
        
        /* Search List */
        .search-container-list {
            position: relative;
            margin-bottom: 25px;
        }
        
        .search-box-list {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f9f9f9;
        }
        
        .search-box-list:focus {
            outline: none;
            border-color: #1a1a1a;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .search-icon-list {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #666;
            pointer-events: none;
        }
        
        .clear-search-list {
            position: absolute;
            right: 45px;
            top: 50%;
            transform: translateY(-50%);
            background: #1a1a1a;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            display: none;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .clear-search-list.active {
            display: flex;
        }
        
        .clear-search-list:hover {
            background: #000;
            transform: translateY(-50%) scale(1.1);
        }
        
        .search-results-info-list {
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
            text-align: center;
            display: none;
        }
        
        .search-results-info-list.active {
            display: block;
        }
        
        /* Table */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .prodotti-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .prodotti-table thead {
            background: #1a1a1a;
            color: white;
        }
        
        .prodotti-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .prodotti-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .prodotto-row {
            transition: all 0.3s;
        }
        
        .prodotto-row.hidden {
            display: none;
        }
        
        .prodotto-row:hover {
            background: #f9f9f9;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .row-alert {
            background: #ffebee !important;
            border-left: 4px solid #ff6b6b;
        }
        
        .row-alert:hover {
            background: #ffcdd2 !important;
        }
        
        .row-ok {
            background: #e8f5e9 !important;
            border-left: 4px solid #4caf50;
        }
        
        .row-ok:hover {
            background: #c8e6c9 !important;
        }
        
        .td-nome {
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .td-descrizione {
            color: #666;
            font-size: 14px;
            max-width: 300px;
        }
        
        .td-quantita {
            text-align: center;
        }
        
        .badge-qty {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            background: #e0e0e0;
            color: #1a1a1a;
            font-weight: 600;
            font-size: 13px;
        }
        
        .badge-qty-low {
            background: #ff6b6b;
            color: white;
        }
        
        .badge-alert {
            display: inline-block;
            margin-left: 10px;
            padding: 4px 10px;
            border-radius: 12px;
            background: #ff6b6b;
            color: white;
            font-size: 11px;
            font-weight: 600;
        }
        
        .td-azioni {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
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
        
        .no-results-list {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            display: none;
        }
        
        .no-results-list.active {
            display: block;
        }
        
        .no-results-icon-list {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .no-results-title-list {
            font-size: 24px;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        
        .no-results-message-list {
            color: #666;
            font-size: 16px;
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
        
        .modal-quick-footer {
            padding: 20px 25px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
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
            z-index: 10001;
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
            0%, 100% {
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
        
        @media (max-width: 768px) {
            .navbar h1 {
                font-size: 18px;
            }
            
            .user-info span {
                display: none;
            }
            
            .form-grid {
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
        
        <?php include './widget/menu.php'; ?>
        
      
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
            <a href=".\logout.php" class="btn-logout">Logout</a>
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
                <div class="prodotto-allarme-card">
                    <div class="prodotto-allarme-nome"><?php echo htmlspecialchars($prod['nome']); ?></div>
                    <div class="prodotto-allarme-quantita">Quantità: <strong><?php echo $prod['quantita']; ?></strong> unità</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Form registrazione movimento -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-icon">📝</span>
                <h3>Registra Movimento</h3>
            </div>
            
            <?php if ($errore): ?>
                <div class="messaggio errore"><?php echo htmlspecialchars($errore); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="formMovimento">
                <!-- Selezione tipo movimento -->
                <div class="tipo-movimento-group">
                    <div class="radio-option">
                        <input type="radio" id="entrata" name="tipo_movimento" value="entrata" checked>
                        <label for="entrata" class="radio-label">
                            <span class="radio-icon">📥</span>
                            <span>Entrata</span>
                        </label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="uscita" name="tipo_movimento" value="uscita">
                        <label for="uscita" class="radio-label">
                            <span class="radio-icon">📤</span>
                            <span>Uscita</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-grid">
                    <!-- Selezione prodotto -->
                    <div class="form-group">
                        <label for="prodotto">Seleziona Prodotto *</label>
                        <select id="prodotto" name="prodotto" required>
                            <option value="">-- Seleziona un prodotto --</option>
                            <?php foreach ($prodotti as $prod): ?>
                                <option value="<?php echo $prod['id']; ?>" 
                                        data-nome="<?php echo htmlspecialchars($prod['nome']); ?>"
                                        data-descrizione="<?php echo htmlspecialchars($prod['descrizione']); ?>"
                                        data-quantita="<?php echo $prod['quantita']; ?>"
                                        data-fornitore="<?php echo htmlspecialchars($prod['fornitore']); ?>">
                                    <?php echo htmlspecialchars($prod['nome']); ?> (Disponibile: <?php echo $prod['quantita']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Quantità movimento -->
                    <div class="form-group">
                        <label for="quantita_movimento">Quantità *</label>
                        <input type="number" id="quantita_movimento" name="quantita_movimento" min="1" value="1" required>
                    </div>
                    
                    <!-- Numero bolla -->
                    <div class="form-group">
                        <label for="numero_bolla">NUMERO ISTA TAGLIO</label>
                        <input type="text" id="numero_bolla" name="numero_bolla" placeholder="Opzionale">
                    </div>

                                        <!-- Numero dato -->
                    <div class="form-group">
                        <label for="numero_dato">NUMERO OFFERTA</label>
                        <input type="text" id="numero_dato" name="numero_dato" placeholder="Opzionale">
                    </div>
                    
                    <!-- Descrizione movimento -->
                    <div class="form-group full-width">
                        <label for="descrizione_movimento">Descrizione Movimento</label>
                        <textarea id="descrizione_movimento" name="descrizione_movimento" placeholder="Note aggiuntive (opzionale)"></textarea>
                    </div>
                </div>
                
                <!-- Info prodotto selezionato -->
                <div class="prodotto-info" id="prodottoInfo">
                    <div class="info-row">
                        <span class="info-label">Fornitore:</span>
                        <span class="info-value" id="infoProdottoFornitore">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Descrizione:</span>
                        <span class="info-value" id="infoProdottoDescrizione">-</span>
                    </div>
                </div>
                
                <button type="submit" name="registra_movimento">Registra Movimento</button>
            </form>
        </div>
        
        <!-- Lista Prodotti -->
        <div class="section-card" style="margin-top: 30px;">
            <div class="section-header">
                <span class="section-icon">📋</span>
                <h3>Lista Prodotti</h3>
            </div>
            
            <!-- Barra di ricerca -->
            <div class="search-container-list">
                <input type="text" 
                       class="search-box-list" 
                       id="searchInputList" 
                       placeholder="Cerca prodotti per nome, descrizione o fornitore..."
                       autocomplete="off">
                <button class="clear-search-list" id="clearSearchList" onclick="clearSearchList()">×</button>
                <span class="search-icon-list">🔍</span>
            </div>
            
            <div class="search-results-info-list" id="searchResultsInfoList"></div>
            
            <!-- Tabella prodotti -->
            <div class="table-container">
                <table class="prodotti-table" id="prodottiTable">
                    <thead>
                        <tr>
                            <th>Prodotto</th>
                            <th>Descrizione</th>
                            <th>Quantità</th>
                            <th>Fornitore</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="prodottiTableBody">
                        <?php foreach ($prodotti as $prod): ?>
                            <tr class="prodotto-row <?php echo ($prod['quantita'] < 5) ? 'row-alert' : 'row-ok'; ?>"
                                data-nome="<?php echo strtolower(htmlspecialchars($prod['nome'])); ?>"
                                data-descrizione="<?php echo strtolower(htmlspecialchars($prod['descrizione'])); ?>"
                                data-fornitore="<?php echo strtolower(htmlspecialchars($prod['fornitore'])); ?>">
                                <td class="td-nome">
                                    <strong><?php echo htmlspecialchars($prod['nome']); ?></strong>
                                    <?php if ($prod['quantita'] < 5): ?>
                                        <span class="badge-alert">⚠️ Scorte basse</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-descrizione"><?php echo htmlspecialchars($prod['descrizione']); ?></td>
                                <td class="td-quantita">
                                    <span class="badge-qty <?php echo ($prod['quantita'] < 5) ? 'badge-qty-low' : ''; ?>">
                                        <?php echo $prod['quantita']; ?> pz
                                    </span>
                                </td>
                                <td class="td-fornitore"><?php echo htmlspecialchars($prod['fornitore']); ?></td>
                                <td class="td-azioni">
                                    <button class="btn-action btn-entrata" 
                                            onclick='openQuickMovement(<?php echo json_encode($prod); ?>, "entrata")'>
                                        📥 Entrata
                                    </button>
                                    <button class="btn-action btn-uscita" 
                                            onclick='openQuickMovement(<?php echo json_encode($prod); ?>, "uscita")'>
                                        📤 Uscita
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="no-results-list" id="noResultsList">
                <div class="no-results-icon-list">🔍</div>
                <div class="no-results-title-list">Nessun risultato</div>
                <div class="no-results-message-list">Nessun prodotto corrisponde alla tua ricerca</div>
            </div>
        </div>
        
        <!-- Modal Quick Movement -->
        <div class="modal-quick" id="modalQuick">
            <div class="modal-quick-content">
                <div class="modal-quick-header">
                    <h3 id="modalQuickTitle">Movimento Rapido</h3>
                    <button class="modal-close" onclick="closeQuickModal()">×</button>
                </div>
                <form id="formQuick" onsubmit="return handleQuickSubmit(event);">
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
                            <input type="number" id="quick_quantita_movimento" name="quantita_movimento" min="1" value="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="quick_numero_bolla">NUMERO ISTA TAGLIO</label>
                            <input type="text" id="quick_numero_bolla" name="numero_bolla" placeholder="Opzionale">
                        </div>
                               <div class="form-group">
                            <label for="quick_numero_dato">NUMERO OFFERTA</label>
                            <input type="text" id="quick_numero_dato" name="numero_dato" placeholder="Opzionale">
                        </div>

                        
                        
                        <div class="form-group">
                            <label for="quick_descrizione_movimento">Descrizione</label>
                            <textarea id="quick_descrizione_movimento" name="descrizione_movimento" placeholder="Note aggiuntive (opzionale)"></textarea>
                        </div>
                    </div>
                    <div class="modal-quick-footer">
                        <button type="button" class="btn btn-cancel" onclick="closeQuickModal()">Annulla</button>
                        <button type="submit" class="btn btn-confirm" id="btnQuickSubmit">Conferma</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Funzioni Popup
        function showPopup(type, title, message) {
            const popup = document.getElementById('popupNotification');
            const icon = document.getElementById('popupIcon');
            const titleEl = document.getElementById('popupTitle');
            const messageEl = document.getElementById('popupMessage');
            
            popup.className = 'popup-notification';
            
            if (type === 'success') {
                popup.classList.add('success');
                icon.textContent = '✅';
            } else {
                popup.classList.add('error');
                icon.textContent = '❌';
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
        
        // Mostra popup se c'è un messaggio di successo
        <?php if ($successo): ?>
            showPopup('success', 'Movimento Registrato!', '<?php echo addslashes($successo); ?>');
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
        
        // Gestione selezione prodotto
        const prodottoSelect = document.getElementById('prodotto');
        const prodottoInfo = document.getElementById('prodottoInfo');
        const infoProdottoFornitore = document.getElementById('infoProdottoFornitore');
        const infoProdottoDescrizione = document.getElementById('infoProdottoDescrizione');
        const quantitaInput = document.getElementById('quantita_movimento');
        
        prodottoSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption.value) {
                const fornitore = selectedOption.dataset.fornitore;
                const descrizione = selectedOption.dataset.descrizione;
                
                infoProdottoFornitore.textContent = fornitore;
                infoProdottoDescrizione.textContent = descrizione;
                
                prodottoInfo.classList.add('show');
                
                // Imposta il massimo per le uscite
                updateQuantitaMax();
            } else {
                prodottoInfo.classList.remove('show');
            }
        });
        
        // Aggiorna il massimo della quantità in base al tipo di movimento
        const radioEntrata = document.getElementById('entrata');
        const radioUscita = document.getElementById('uscita');
        
        function updateQuantitaMax() {
            const selectedOption = prodottoSelect.options[prodottoSelect.selectedIndex];
            
            if (selectedOption.value && radioUscita.checked) {
                const quantitaDisponibile = parseInt(selectedOption.dataset.quantita);
                quantitaInput.max = quantitaDisponibile;
                
                if (parseInt(quantitaInput.value) > quantitaDisponibile) {
                    quantitaInput.value = quantitaDisponibile;
                }
            } else {
                quantitaInput.removeAttribute('max');
            }
        }
        
        radioEntrata.addEventListener('change', updateQuantitaMax);
        radioUscita.addEventListener('change', updateQuantitaMax);
        
        // Custom Confirm Dialog
        const confirmDialog = document.getElementById('confirmDialog');
        let pendingForm = false;
        
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
                confirmIcon.textContent = '📥';
                confirmTitle.textContent = 'Conferma Entrata';
                confirmMessage.textContent = 'Stai per registrare un\'entrata di prodotto nel magazzino.';
            } else {
                confirmIconWrapper.classList.add('uscita');
                confirmIcon.textContent = '📤';
                confirmTitle.textContent = 'Conferma Uscita';
                confirmMessage.textContent = 'Stai per registrare un\'uscita di prodotto dal magazzino.';
            }
            
            // Calcola nuova quantità
            const nuovaQuantita = tipoMovimento === 'entrata' 
                ? parseInt(quantitaAttuale) + parseInt(quantita)
                : parseInt(quantitaAttuale) - parseInt(quantita);
            
            // Crea i dettagli
            let detailsHTML = `
                <div class="confirm-detail-row">
                    <span class="confirm-detail-label">Prodotto:</span>
                    <span class="confirm-detail-value">${nomeProdotto}</span>
                </div>
                <div class="confirm-detail-row">
                    <span class="confirm-detail-label">Quantità movimento:</span>
                    <span class="confirm-detail-value ${tipoMovimento === 'entrata' ? 'highlight' : 'warning'}">
                        ${tipoMovimento === 'entrata' ? '+' : '-'}${quantita} unità
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
                        <span class="confirm-detail-label">Numero Bolla:</span>
                        <span class="confirm-detail-value">${numeroBolla}</span>
                    </div>
                `;
            }

                        if (numeroDato && numeroDato.trim() !== '') {
                detailsHTML += `
                    <div class="confirm-detail-row">
                        <span class="confirm-detail-label">Numero Dato:</span>
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
            
            // Resetta solo se non stiamo confermando
            if (!pendingForm) {
                currentQuickProduct = null;
                currentQuickType = null;
            }
        }
        
        function confirmSubmit() {
            if (pendingForm) {
                // Crea un form nascosto e invialo
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                // Copia tutti i valori dal form originale
                const originalForm = document.getElementById('formMovimento');
                const formData = new FormData(originalForm);
                
                for (let [key, value] of formData.entries()) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Gestione click fuori dal dialog
        confirmDialog.addEventListener('click', function(e) {
            if (e.target === confirmDialog) {
                closeConfirm();
            }
        });
        
        // Validazione form con conferma custom
        document.getElementById('formMovimento').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const prodotto = prodottoSelect.value;
            const quantita = parseInt(quantitaInput.value);
            const tipoMovimento = document.querySelector('input[name="tipo_movimento"]:checked').value;
            const numeroBolla = document.getElementById('numero_bolla').value;
            const numeroDato = document.getElementById('numero_dato').value;
            
            if (!prodotto) {
                alert('Seleziona un prodotto!');
                return;
            }
            
            if (quantita <= 0) {
                alert('Inserisci una quantità valida!');
                return;
            }
            
            const selectedOption = prodottoSelect.options[prodottoSelect.selectedIndex];
            const quantitaDisponibile = parseInt(selectedOption.dataset.quantita);
            const nomeProdotto = selectedOption.dataset.nome;
            
            if (tipoMovimento === 'uscita' && quantita > quantitaDisponibile) {
                alert('Quantità non disponibile! Disponibili: ' + quantitaDisponibile + ' unità');
                return;
            }
            
            // Mostra conferma custom
            pendingForm = 'main';
            showConfirm(tipoMovimento, nomeProdotto, quantita, quantitaDisponibile, numeroBolla);
        });
        
        // Close dialog con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && confirmDialog.classList.contains('active')) {
                closeConfirm();
            }
        });
        
        // Reset form dopo successo
        <?php if ($successo): ?>
            setTimeout(() => {
                document.getElementById('formMovimento').reset();
                prodottoInfo.classList.remove('show');
                document.getElementById('entrata').checked = true;
            }, 100);
        <?php endif; ?>
        
        // Animazione input
        const inputs = document.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateX(3px)';
                this.parentElement.style.transition = 'transform 0.2s';
            });
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateX(0)';
            });
        });
        
        // Quick Movement Functions
        let currentQuickProduct = null;
        let currentQuickType = null;
        const modalQuick = document.getElementById('modalQuick');
        
        function openQuickMovement(prodotto, tipo) {
            currentQuickProduct = prodotto;
            currentQuickType = tipo;
            
            console.log('Opening quick modal for:', prodotto, 'Type:', tipo); // Debug
            
            // Configura il modal
            const quickIcon = document.getElementById('quickIcon');
            const quickIconWrapper = document.getElementById('quickIconWrapper');
            const modalTitle = document.getElementById('modalQuickTitle');
            const productName = document.getElementById('quickProductName');
            const productQty = document.getElementById('quickProductQty');
            const btnSubmit = document.getElementById('btnQuickSubmit');
            const quantitaInput = document.getElementById('quick_quantita_movimento');
            
            quickIconWrapper.className = 'quick-icon-wrapper';
            
            if (tipo === 'entrata') {
                quickIconWrapper.classList.add('entrata');
                quickIcon.textContent = '📥';
                modalTitle.textContent = 'Entrata Rapida';
                btnSubmit.style.background = '#4caf50';
                quantitaInput.removeAttribute('max');
            } else {
                quickIconWrapper.classList.add('uscita');
                quickIcon.textContent = '📤';
                modalTitle.textContent = 'Uscita Rapida';
                btnSubmit.style.background = '#ff9800';
                quantitaInput.max = prodotto.quantita;
            }
            
            productName.textContent = prodotto.nome;
            productQty.textContent = `Quantità disponibile: ${prodotto.quantita} pz`;
            
            // Reset form e imposta valori
            const formQuick = document.getElementById('formQuick');
            formQuick.reset();
            
            // IMPORTANTE: Imposta i valori hidden DOPO il reset
            document.getElementById('quick_prodotto_id').value = prodotto.id;
            document.getElementById('quick_tipo_movimento').value = tipo;
            document.getElementById('quick_quantita_movimento').value = 1;
            
            console.log('Form quick values set:', {
                id: prodotto.id,
                tipo: tipo,
                quantita: 1
            }); // Debug
            
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
        
        function handleQuickSubmit(event) {
            event.preventDefault();
            
            console.log('handleQuickSubmit called'); // Debug
            console.log('currentQuickProduct:', currentQuickProduct); // Debug
            console.log('currentQuickType:', currentQuickType); // Debug
            
            const quantita = parseInt(document.getElementById('quick_quantita_movimento').value);
            
            if (quantita <= 0) {
                alert('Inserisci una quantità valida!');
                return false;
            }
            
            if (currentQuickType === 'uscita' && quantita > currentQuickProduct.quantita) {
                alert('Quantità non disponibile! Disponibili: ' + currentQuickProduct.quantita + ' unità');
                return false;
            }
            
            // Verifica che i campi hidden abbiano i valori corretti
            const prodottoId = document.getElementById('quick_prodotto_id').value;
            const tipoMov = document.getElementById('quick_tipo_movimento').value;
            
            console.log('Form values before confirm:', {
                prodotto: prodottoId,
                tipo_movimento: tipoMov,
                quantita: quantita
            }); // Debug
            
            // Salva i dati prima di chiudere il modal
            const numeroBolla = document.getElementById('quick_numero_bolla').value;
            const numeroDato = document.getElementById('quick_numero_dato').value;
            const tipoMovimento = currentQuickType;
            const nomeProdotto = currentQuickProduct.nome;
            const quantitaAttuale = currentQuickProduct.quantita;
            
            // Chiudi modal
            modalQuick.classList.remove('active');
            document.body.style.overflow = '';
            
            // NON resettare currentQuickProduct e currentQuickType qui
            // Imposta il flag per il form quick
            pendingForm = 'quick';
            showConfirm(tipoMovimento, nomeProdotto, quantita, quantitaAttuale, numeroBolla);
            
            return false;
        }
        
        // Modifica confirmSubmit originale per salvare riferimento
        const originalConfirmSubmitFunc = window.confirmSubmit;
        
      // Modifica confirmSubmit per gestire correttamente entrambi i form
window.confirmSubmit = function() {
    if (pendingForm) {
        let formToSubmit;
        
        console.log('Pending form type:', pendingForm); // Debug
        
        // Controlla quale form stiamo usando
        if (pendingForm === 'quick') {
            formToSubmit = document.getElementById('formQuick');
            console.log('Using quick form'); // Debug
            
            // Verifica che i campi hidden esistano e abbiano valori
            const prodottoId = document.getElementById('quick_prodotto_id');
            const tipoMov = document.getElementById('quick_tipo_movimento');
            const quantitaMov = document.getElementById('quick_quantita_movimento');
            
            console.log('Hidden fields check:', {
                prodotto_exists: !!prodottoId,
                prodotto_value: prodottoId ? prodottoId.value : 'NULL',
                tipo_exists: !!tipoMov,
                tipo_value: tipoMov ? tipoMov.value : 'NULL',
                quantita_exists: !!quantitaMov,
                quantita_value: quantitaMov ? quantitaMov.value : 'NULL'
            });
            
        } else {
            formToSubmit = document.getElementById('formMovimento');
            console.log('Using main form'); // Debug
            
            // Assicurati che il campo registra_movimento esista nel form principale
            let submitInput = formToSubmit.querySelector('input[name="registra_movimento"]');
            if (!submitInput) {
                submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'registra_movimento';
                submitInput.value = '1';
                formToSubmit.appendChild(submitInput);
            }
        }
        
        // Debug: mostra tutti i campi del form
        const formData = new FormData(formToSubmit);
        console.log('Form fields to be submitted:');
        for (let [key, value] of formData.entries()) {
            console.log('  ' + key + ': ' + value);
        }
        
        // Verifica che registra_movimento sia presente
        if (!formData.has('registra_movimento')) {
            console.error('ERRORE: Campo registra_movimento mancante!');
            alert('Errore: campo registra_movimento mancante. Controlla la console.');
            return;
        }
        
        // Verifica che prodotto sia presente
        if (!formData.has('prodotto') || !formData.get('prodotto')) {
            console.error('ERRORE: Campo prodotto mancante o vuoto!');
            alert('Errore: campo prodotto mancante. Controlla la console.');
            return;
        }
        
        console.log('All checks passed, submitting form...'); // Debug
        
        // Chiudi il dialog PRIMA di resettare le variabili
        confirmDialog.classList.remove('active');
        document.body.style.overflow = '';
        
        // Resetta le variabili
        pendingForm = false;
        currentQuickProduct = null;
        currentQuickType = null;
        
        // IMPORTANTE: Cambia method e action del form per assicurarti che funzioni
        formToSubmit.method = 'POST';
        formToSubmit.action = '';
        
        // Invia il form
        console.log('Calling form.submit()...'); // Debug
        formToSubmit.submit();
    } else {
        console.log('pendingForm is false, not submitting'); // Debug
    }
};
        // Close quick modal on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (modalQuick.classList.contains('active')) {
                    closeQuickModal();
                }
            }
        });
        
        // Click outside quick modal
        modalQuick.addEventListener('click', function(e) {
            if (e.target === modalQuick) {
                closeQuickModal();
            }
        });
        
        // Search functionality for list
        const searchInputList = document.getElementById('searchInputList');
        const clearSearchBtnList = document.getElementById('clearSearchList');
        const searchResultsInfoList = document.getElementById('searchResultsInfoList');
        const noResultsList = document.getElementById('noResultsList');
        const tableContainer = document.querySelector('.table-container');
        const prodottiRows = document.querySelectorAll('.prodotto-row');
        const totalProdottiList = prodottiRows.length;
        
        if (searchInputList) {
            searchInputList.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                if (searchTerm.length > 0) {
                    clearSearchBtnList.classList.add('active');
                } else {
                    clearSearchBtnList.classList.remove('active');
                }
                
                filterProductsList(searchTerm);
            });
        }
        
        function filterProductsList(searchTerm) {
            let visibleCount = 0;
            
            prodottiRows.forEach(row => {
                const nome = row.getAttribute('data-nome') || '';
                const descrizione = row.getAttribute('data-descrizione') || '';
                const fornitore = row.getAttribute('data-fornitore') || '';
                
                const matches = nome.includes(searchTerm) || 
                               descrizione.includes(searchTerm) || 
                               fornitore.includes(searchTerm);
                
                if (searchTerm === '' || matches) {
                    row.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.classList.add('hidden');
                }
            });
            
            updateSearchResultsList(searchTerm, visibleCount);
        }
        
        function updateSearchResultsList(searchTerm, visibleCount) {
            if (searchTerm === '') {
                searchResultsInfoList.classList.remove('active');
                noResultsList.classList.remove('active');
                tableContainer.style.display = 'block';
            } else {
                searchResultsInfoList.classList.add('active');
                
                if (visibleCount === 0) {
                    searchResultsInfoList.textContent = 'Nessun prodotto trovato per "' + searchTerm + '"';
                    noResultsList.classList.add('active');
                    tableContainer.style.display = 'none';
                } else {
                    searchResultsInfoList.textContent = 'Trovati ' + visibleCount + ' prodotti';
                    noResultsList.classList.remove('active');
                    tableContainer.style.display = 'block';
                }
            }
        }
        
        function clearSearchList() {
            searchInputList.value = '';
            clearSearchBtnList.classList.remove('active');
            filterProductsList('');
            searchInputList.focus();
        }
    </script>
</body>
</html>