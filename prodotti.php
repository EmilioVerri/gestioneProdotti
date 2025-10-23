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

// Gestione eliminazione prodotto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['elimina_prodotto'])) {
    $id = intval($_POST['id']);
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("DELETE FROM prodotti WHERE id = ?");
        $stmt->execute([$id]);
        
        $successo = 'Prodotto eliminato con successo!';
    } catch (PDOException $e) {
        $errore = 'Errore durante l\'eliminazione: ' . $e->getMessage();
    }
}

// Gestione modifica prodotto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifica_prodotto'])) {
    $id = intval($_POST['id']);
    $nome = trim($_POST['nome']);
    $descrizione = trim($_POST['descrizione']);
    $quantita = intval($_POST['quantita']);
    $fornitore = trim($_POST['fornitore']);
    
    if (!empty($nome) && !empty($descrizione) && $quantita >= 0 && !empty($fornitore)) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("UPDATE prodotti SET nome = ?, descrizione = ?, quantita = ?, fornitore = ? WHERE id = ?");
            $stmt->execute([$nome, $descrizione, $quantita, $fornitore, $id]);
            
            $successo = 'Prodotto modificato con successo!';
        } catch (PDOException $e) {
            $errore = 'Errore durante la modifica: ' . $e->getMessage();
        }
    } else {
        $errore = 'Compila tutti i campi correttamente';
    }
}

// Recupera tutti i prodotti
$prodotti = [];
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT * FROM prodotti ORDER BY id DESC");
    $prodotti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errore = 'Errore nel recupero dei prodotti: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prodotti - Gestione Prodotti</title>
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
        
        .menu-toggle:active {
            transform: scale(0.95);
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
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
        }
        
        .prodotti-count {
            background: #1a1a1a;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
        }
        
        .search-container {
            position: relative;
        }
        
        .search-box {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f9f9f9;
        }
        
        .search-box:focus {
            outline: none;
            border-color: #1a1a1a;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #666;
            pointer-events: none;
        }
        
        .clear-search {
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
        
        .clear-search.active {
            display: flex;
        }
        
        .clear-search:hover {
            background: #000;
            transform: translateY(-50%) scale(1.1);
        }
        
        .search-results-info {
            margin-top: 15px;
            color: #666;
            font-size: 14px;
            text-align: center;
            display: none;
        }
        
        .search-results-info.active {
            display: block;
        }
        
        .prodotti-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .prodotto-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            animation: slideUp 0.5s ease;
            position: relative;
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
        
        .prodotto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }
        
        .prodotto-card.hidden {
            display: none;
        }
        
        .prodotto-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .prodotto-nome {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            flex: 1;
            word-break: break-word;
        }
        
        .prodotto-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-modifica, .btn-elimina {
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-modifica {
            background: #1a1a1a;
            color: white;
        }
        
        .btn-modifica:hover {
            background: #000;
            transform: rotate(90deg) scale(1.1);
        }
        
        .btn-elimina {
            background: #ff4444;
            color: white;
        }
        
        .btn-elimina:hover {
            background: #cc0000;
            transform: scale(1.1);
        }
        
        .prodotto-info {
            margin-top: 15px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            min-width: 100px;
        }
        
        .info-value {
            color: #1a1a1a;
            flex: 1;
        }
        
        .prodotto-descrizione {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .badge-quantita {
            display: inline-block;
            background: #e0e0e0;
            color: #1a1a1a;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
        }
        
        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fadeIn 0.3s;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 25px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            color: #1a1a1a;
            font-size: 22px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #999;
            cursor: pointer;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .modal-close:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        .modal-body {
            padding: 25px;
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
        
        input[type="text"],
        input[type="number"],
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
            min-height: 100px;
        }
        
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #1a1a1a;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #1a1a1a;
            color: white;
        }
        
        .btn-primary:hover {
            background: #000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #1a1a1a;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
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
        
        /* Confirm Dialog */
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
        }
        
        .confirm-dialog.active {
            display: flex;
        }
        
        .confirm-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            animation: slideDown 0.3s ease;
        }
        
        .confirm-icon {
            font-size: 50px;
            margin-bottom: 20px;
        }
        
        .confirm-title {
            font-size: 22px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        
        .confirm-message {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .confirm-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-title {
            font-size: 24px;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        
        .empty-message {
            color: #666;
            font-size: 16px;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: none;
        }
        
        .no-results.active {
            display: block;
        }
        
        .no-results-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .no-results-title {
            font-size: 24px;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        
        .no-results-message {
            color: #666;
            font-size: 16px;
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
            
            .header-top {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .search-container {
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
    
    <!-- Confirm Dialog Modifica -->
    <div class="confirm-dialog" id="confirmDialog">
        <div class="confirm-content">
            <div class="confirm-icon">⚠️</div>
            <div class="confirm-title">Conferma Modifica</div>
            <div class="confirm-message">Sei sicuro di voler modificare questo prodotto? Questa azione aggiornerà i dati nel database.</div>
            <div class="confirm-buttons">
                <button class="btn btn-secondary" onclick="closeConfirm()">Annulla</button>
                <button class="btn btn-primary" onclick="confirmModifica()">Conferma</button>
            </div>
        </div>
    </div>
    
    <!-- Confirm Dialog Eliminazione -->
    <div class="confirm-dialog" id="confirmDeleteDialog">
        <div class="confirm-content">
            <div class="confirm-icon">🗑️</div>
            <div class="confirm-title">Conferma Eliminazione</div>
            <div class="confirm-message" id="deleteMessage">Sei sicuro di voler eliminare questo prodotto? Questa azione è irreversibile!</div>
            <div class="confirm-buttons">
                <button class="btn btn-secondary" onclick="closeDeleteConfirm()">Annulla</button>
                <button class="btn btn-primary" style="background: #ff4444;" onclick="confirmDelete()">Elimina</button>
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
            <h1>Gestione Prodotti</h1>
        </div>
        <div class="user-info">
            <span>Benvenuto, <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <a href=".\logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header">
            <div class="header-top">
                <h2>Elenco Prodotti</h2>
                <div class="prodotti-count" id="totalCount"><?php echo count($prodotti); ?> Prodotti</div>
            </div>
            
            <div class="search-container">
                <input type="text" 
                       class="search-box" 
                       id="searchInput" 
                       placeholder="Cerca prodotti per nome, descrizione o fornitore..."
                       autocomplete="off">
                <button class="clear-search" id="clearSearch" onclick="clearSearch()">×</button>
                <span class="search-icon">🔍</span>
            </div>
            
            <div class="search-results-info" id="searchResultsInfo"></div>
        </div>
        
        <?php if (empty($prodotti)): ?>
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <div class="empty-title">Nessun prodotto trovato</div>
                <div class="empty-message">Inizia ad aggiungere prodotti dalla pagina Controllo</div>
            </div>
        <?php else: ?>
            <div class="no-results" id="noResults">
                <div class="no-results-icon">🔍</div>
                <div class="no-results-title">Nessun risultato</div>
                <div class="no-results-message">Nessun prodotto corrisponde alla tua ricerca</div>
            </div>
            
            <div class="prodotti-grid" id="prodottiGrid">
                <?php foreach ($prodotti as $index => $prodotto): ?>
                    <div class="prodotto-card" 
                         style="animation-delay: <?php echo $index * 0.1; ?>s;"
                         data-nome="<?php echo strtolower(htmlspecialchars($prodotto['nome'])); ?>"
                         data-descrizione="<?php echo strtolower(htmlspecialchars($prodotto['descrizione'])); ?>"
                         data-fornitore="<?php echo strtolower(htmlspecialchars($prodotto['fornitore'])); ?>">
                        <div class="prodotto-header">
                            <div class="prodotto-nome"><?php echo htmlspecialchars($prodotto['nome']); ?></div>
                            <div class="prodotto-actions">
                                <button class="btn-modifica" onclick="openModal(<?php echo htmlspecialchars(json_encode($prodotto)); ?>)" title="Modifica prodotto">
                                    ✏️
                                </button>
                                <button class="btn-elimina" onclick="showDeleteConfirm(<?php echo $prodotto['id']; ?>, '<?php echo addslashes(htmlspecialchars($prodotto['nome'])); ?>')" title="Elimina prodotto">
                                    🗑️
                                </button>
                            </div>
                        </div>
                        
                        <div class="prodotto-info">
                            <div class="info-row">
                                <span class="info-label">Quantità:</span>
                                <span class="info-value">
                                    <span class="badge-quantita"><?php echo $prodotto['quantita']; ?> pz</span>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Fornitore:</span>
                                <span class="info-value"><?php echo htmlspecialchars($prodotto['fornitore']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Allarme:</span>
                                <span class="info-value"><?php echo htmlspecialchars($prodotto['allarme']); ?></span>
                            </div>
                        </div>
                        
                        <div class="prodotto-descrizione">
                            <?php echo htmlspecialchars($prodotto['descrizione']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal Modifica -->
    <div class="modal" id="modalModifica">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Modifica Prodotto</h3>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <form id="formModifica" onsubmit="return showConfirm(event);">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    
                    <div class="form-group">
                        <label for="edit_nome">Nome Prodotto *</label>
                        <input type="text" id="edit_nome" name="nome" required>
                    </div>
                    
                    <div class="form-group">
                        <input type="number" id="edit_quantita" name="quantita" min="0" style="display:none">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_fornitore">Fornitore *</label>
                        <input type="text" id="edit_fornitore" name="fornitore" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_descrizione">Descrizione *</label>
                        <textarea id="edit_descrizione" name="descrizione" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva Modifiche</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Popup Functions
        function showPopup(type, title, message) {
            const popup = document.getElementById('popupNotification');
            const icon = document.getElementById('popupIcon');
            const titleEl = document.getElementById('popupTitle');
            const messageEl = document.getElementById('popupMessage');
            
            popup.className = 'popup-notification';
            
            if (type === 'success') {
                popup.classList.add('success');
                icon.textContent = '✅';
            }
            
            titleEl.textContent = title;
            messageEl.textContent = message;
            
            setTimeout(() => {
                popup.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                closePopup();
            }, 4000);
        }
        
        function closePopup() {
            document.getElementById('popupNotification').classList.remove('show');
        }
        
        <?php if ($successo): ?>
            showPopup('success', 'Successo!', '<?php echo addslashes($successo); ?>');
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
        
        // Modal Functions
        const modal = document.getElementById('modalModifica');
        
        function openModal(prodotto) {
            document.getElementById('edit_id').value = prodotto.id;
            document.getElementById('edit_nome').value = prodotto.nome;
            document.getElementById('edit_quantita').value = prodotto.quantita;
            document.getElementById('edit_fornitore').value = prodotto.fornitore;
            document.getElementById('edit_descrizione').value = prodotto.descrizione;
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Confirm Dialog Functions
        const confirmDialog = document.getElementById('confirmDialog');
        const confirmDeleteDialog = document.getElementById('confirmDeleteDialog');
        let pendingForm = null;
        let pendingDeleteId = null;
        
        function showConfirm(event) {
            event.preventDefault();
            pendingForm = event.target;
            confirmDialog.classList.add('active');
            return false;
        }
        
        function closeConfirm() {
            confirmDialog.classList.remove('active');
            pendingForm = null;
        }
        
        function confirmModifica() {
            if (pendingForm) {
                const formData = new FormData(pendingForm);
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                for (let [key, value] of formData.entries()) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
                
                const modifyInput = document.createElement('input');
                modifyInput.type = 'hidden';
                modifyInput.name = 'modifica_prodotto';
                modifyInput.value = '1';
                form.appendChild(modifyInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Delete Functions
        function showDeleteConfirm(id, nome) {
            pendingDeleteId = id;
            const message = document.getElementById('deleteMessage');
            message.textContent = 'Sei sicuro di voler eliminare "' + nome + '"? Questa azione è irreversibile!';
            confirmDeleteDialog.classList.add('active');
        }
        
        function closeDeleteConfirm() {
            confirmDeleteDialog.classList.remove('active');
            pendingDeleteId = null;
        }
        
        function confirmDelete() {
            if (pendingDeleteId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = pendingDeleteId;
                form.appendChild(idInput);
                
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'elimina_prodotto';
                deleteInput.value = '1';
                form.appendChild(deleteInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (confirmDeleteDialog.classList.contains('active')) {
                    closeDeleteConfirm();
                } else if (confirmDialog.classList.contains('active')) {
                    closeConfirm();
                } else if (modal.classList.contains('active')) {
                    closeModal();
                }
            }
        });
        
        // Click outside modal to close
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        confirmDialog.addEventListener('click', function(e) {
            if (e.target === confirmDialog) {
                closeConfirm();
            }
        });
        
        confirmDeleteDialog.addEventListener('click', function(e) {
            if (e.target === confirmDeleteDialog) {
                closeDeleteConfirm();
            }
        });
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearch');
        const searchResultsInfo = document.getElementById('searchResultsInfo');
        const totalCount = document.getElementById('totalCount');
        const noResults = document.getElementById('noResults');
        const prodottiGrid = document.getElementById('prodottiGrid');
        const prodottiCards = document.querySelectorAll('.prodotto-card');
        const totalProdotti = prodottiCards.length;
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                if (searchTerm.length > 0) {
                    clearSearchBtn.classList.add('active');
                } else {
                    clearSearchBtn.classList.remove('active');
                }
                
                filterProducts(searchTerm);
            });
            
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        }
        
        function filterProducts(searchTerm) {
            let visibleCount = 0;
            
            prodottiCards.forEach(card => {
                const nome = card.getAttribute('data-nome') || '';
                const descrizione = card.getAttribute('data-descrizione') || '';
                const fornitore = card.getAttribute('data-fornitore') || '';
                
                const matches = nome.includes(searchTerm) || 
                               descrizione.includes(searchTerm) || 
                               fornitore.includes(searchTerm);
                
                if (searchTerm === '' || matches) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });
            
            updateSearchResults(searchTerm, visibleCount);
        }
        
        function updateSearchResults(searchTerm, visibleCount) {
            if (searchTerm === '') {
                searchResultsInfo.classList.remove('active');
                totalCount.textContent = totalProdotti + ' Prodotti';
                noResults.classList.remove('active');
                if (prodottiGrid) prodottiGrid.style.display = 'grid';
            } else {
                searchResultsInfo.classList.add('active');
                totalCount.textContent = visibleCount + ' di ' + totalProdotti;
                
                if (visibleCount === 0) {
                    searchResultsInfo.textContent = 'Nessun prodotto trovato per "' + searchTerm + '"';
                    noResults.classList.add('active');
                    if (prodottiGrid) prodottiGrid.style.display = 'none';
                } else {
                    searchResultsInfo.textContent = 'Trovati ' + visibleCount + ' prodotti';
                    noResults.classList.remove('active');
                    if (prodottiGrid) prodottiGrid.style.display = 'grid';
                }
            }
        }
        
        function clearSearch() {
            searchInput.value = '';
            clearSearchBtn.classList.remove('active');
            filterProducts('');
            searchInput.focus();
        }
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            if (searchTerm.length > 0) {
                prodottiCards.forEach(card => {
                    if (!card.classList.contains('hidden')) {
                        card.style.transition = 'all 0.3s';
                    }
                });
            }
        });
    </script>
</body>
</html>