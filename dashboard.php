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

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Errore di connessione: ' . $e->getMessage());
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

// Calcola statistiche
$totaleProdotti = 0;
$totaleQuantita = 0;
$prodottiCritici = 0;

foreach ($prodotti_per_padre as $padre => $figli) {
    foreach ($figli as $prod) {
        $totaleProdotti++;
        $totaleQuantita += $prod['quantita'];
        if ($prod['quantita'] < $prod['minimo']) {
            $prodottiCritici++;
        }
    }
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
    <title>Dashboard - Gestione Prodotti</title>
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

        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            animation: fadeIn 0.7s;
        }
        
        .stat-card h4 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
        }
        
        .stat-critico {
            color: #ff6b6b;
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
            cursor: pointer;
            transition: all 0.3s;
        }

        .prodotto-allarme-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
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
            cursor: pointer;
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

        /* Modal Movimenti */
        .modal-movimenti {
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

        .modal-movimenti.active {
            display: flex;
        }

        .modal-movimenti-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
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

        .modal-movimenti-header {
            padding: 25px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
        }

        .modal-movimenti-header h3 {
            color: #1a1a1a;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
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

        .modal-movimenti-body {
            padding: 25px;
        }

        .product-info-header {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .product-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .product-info-label {
            font-weight: 600;
            color: #666;
        }

        .product-info-value {
            font-weight: 600;
            color: #1a1a1a;
        }

        .movimenti-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .movimenti-table thead {
            background: #f9f9f9;
        }

        .movimenti-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .movimenti-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }

        .movimenti-table tbody tr {
            transition: all 0.3s;
        }

        .movimenti-table tbody tr:hover {
            background: #f9f9f9;
        }

        .row-entrata {
            background: #e8f5e9 !important;
            border-left: 4px solid #4caf50;
        }

        .row-entrata:hover {
            background: #c8e6c9 !important;
        }

        .row-uscita {
            background: #ffebee !important;
            border-left: 4px solid #ff6b6b;
        }

        .row-uscita:hover {
            background: #ffcdd2 !important;
        }

        .badge-movimento {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 12px;
        }

        .badge-entrata {
            background: #4caf50;
            color: white;
        }

        .badge-uscita {
            background: #ff6b6b;
            color: white;
        }

        .no-movimenti {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .no-movimenti-icon {
            font-size: 60px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .btn-vedi-altro {
            display: block;
            width: 100%;
            padding: 15px;
            background: #1a1a1a;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-vedi-altro:hover {
            background: #000;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .loading i {
            font-size: 48px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

            .modal-movimenti-content {
                width: 95%;
                max-height: 95vh;
            }

            .movimenti-table {
                font-size: 11px;
            }

            .movimenti-table th,
            .movimenti-table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">GP</div>
            <div class="sidebar-title">Gestione Prodotti</div>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active">
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
            <a href="entrateUscite.php" class="menu-item">
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
            <h1>Dashboard</h1>
        </div>
        <div class="user-info">
            <span>Benvenuto, <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <a href="./logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header">
            <h2>Dashboard Magazzino</h2>
            <p>Panoramica generale dei prodotti organizzati per gruppo</p>
        </div>

        <!-- Statistiche -->
       <!-- <div class="stats">
            <div class="stat-card">
                <h4>Prodotti Totali</h4>
                <div class="number"><?php echo $totaleProdotti; ?></div>
            </div>
            <div class="stat-card">
                <h4>Quantità Totale</h4>
                <div class="number"><?php echo $totaleQuantita; ?></div>
            </div>
            <div class="stat-card">
                <h4>Prodotti Critici</h4>
                <div class="number stat-critico"><?php echo $prodottiCritici; ?></div>
            </div>
            <div class="stat-card">
                <h4>Gruppi Attivi</h4>
                <div class="number"><?php echo count($prodotti_per_padre); ?></div>
            </div>
        </div>-->

        <!-- Alert prodotti con scorte basse -->
        <?php if (count($prodottiAllarme) > 0): ?>
            <div class="alert-box">
                <div class="alert-header">
                    <span class="alert-icon">⚠️</span>
                    <div class="alert-title">Attenzione: <?php echo count($prodottiAllarme); ?> prodott<?php echo count($prodottiAllarme) > 1 ? 'i' : 'o'; ?> con scorte basse!</div>
                </div>
                <div class="prodotti-allarme">
                    <?php foreach ($prodottiAllarme as $prod): ?>
                        <div class="prodotto-allarme-card" onclick="openMovimentiModal(<?php echo $prod['id']; ?>)">
                            <div class="prodotto-allarme-nome"><?php echo htmlspecialchars($prod['nome']); ?></div>
                            <div class="prodotto-allarme-quantita">Gruppo: <strong><?php echo htmlspecialchars($prod['padre']); ?></strong></div>
                            <div class="prodotto-allarme-quantita">Quantità: <strong><?php echo $prod['quantita']; ?></strong> / Allarme: <strong><?php echo $prod['minimo']; ?></strong></div>
                            <div style="margin-top: 8px; font-size: 12px; color: #1a1a1a; font-weight: 600;">
                                <i class="fas fa-hand-pointer"></i> Clicca per vedere i movimenti
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

            <?php if (!empty($prodotti_per_padre)): ?>
                <?php foreach ($prodotti_per_padre as $padre => $figli): ?>
                    <div class="padre-group">
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
                                         onclick="openMovimentiModal(<?php echo $prod['id']; ?>)">
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

<!-- Modal Movimenti -->
<div class="modal-movimenti" id="modalMovimenti">
    <div class="modal-movimenti-content">
        <div class="modal-movimenti-header">
            <h3><i class="fas fa-history"></i> Ultimi Movimenti</h3>
            <button class="modal-close" onclick="closeMovimentiModal()">×</button>
        </div>
        <div class="modal-movimenti-body" id="modalMovimentiBody">
            <div class="loading">
                <i class="fas fa-spinner"></i>
                <p>Caricamento movimenti...</p>
            </div>
        </div>
    </div>
</div>

<script>
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

    // Modal Movimenti
    const modalMovimenti = document.getElementById('modalMovimenti');
    const modalMovimentiBody = document.getElementById('modalMovimentiBody');

    function openMovimentiModal(prodottoId) {
        modalMovimenti.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Reset del contenuto
        modalMovimentiBody.innerHTML = `
            <div class="loading">
                <i class="fas fa-spinner"></i>
                <p>Caricamento movimenti...</p>
            </div>
        `;

        // Carica i dati via AJAX
        fetch('get_movimenti.php?id=' + prodottoId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayMovimenti(data.prodotto, data.movimenti);
                } else {
                    modalMovimentiBody.innerHTML = `
                        <div class="no-movimenti">
                            <div class="no-movimenti-icon">❌</div>
                            <h3>Errore</h3>
                            <p>${data.error || 'Impossibile caricare i movimenti'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                modalMovimentiBody.innerHTML = `
                    <div class="no-movimenti">
                        <div class="no-movimenti-icon">❌</div>
                        <h3>Errore di connessione</h3>
                        <p>Impossibile caricare i movimenti</p>
                    </div>
                `;
            });
    }

    function displayMovimenti(prodotto, movimenti) {
        let html = `
            <div class="product-info-header">
                <h4 style="color: #1a1a1a; margin-bottom: 15px; font-size: 18px;">
                    <i class="fas fa-box"></i> ${prodotto.nome}
                </h4>
                <div class="product-info-row">
                    <span class="product-info-label">Gruppo:</span>
                    <span class="product-info-value">${prodotto.padre}</span>
                </div>
                <div class="product-info-row">
                    <span class="product-info-label">Quantità Attuale:</span>
                    <span class="product-info-value">${prodotto.quantita} pz</span>
                </div>
                <div class="product-info-row">
                    <span class="product-info-label">Soglia Allarme:</span>
                    <span class="product-info-value">${prodotto.minimo} pz</span>
                </div>
                <div class="product-info-row">
                    <span class="product-info-label">Fornitore:</span>
                    <span class="product-info-value">${prodotto.fornitore}</span>
                </div>
            </div>
        `;

        if (movimenti.length > 0) {
            html += `
                <table class="movimenti-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Quantità</th>
                            <th>Utente</th>
                            <th>Descrizione</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            movimenti.forEach(mov => {
                const isUscita = mov.movimento.toString().startsWith('-');
                const movimentoNum = Math.abs(parseInt(mov.movimento));
                const rowClass = isUscita ? 'row-uscita' : 'row-entrata';
                const badgeClass = isUscita ? 'badge-uscita' : 'badge-entrata';
                const tipo = isUscita ? 'USCITA' : 'ENTRATA';
                const icon = isUscita ? '↓' : '↑';
                const sign = isUscita ? '-' : '+';

                html += `
                    <tr class="${rowClass}">
                        <td>${mov.dataMovimento}</td>
                        <td>
                            <span class="badge-movimento ${badgeClass}">
                                ${icon} ${tipo}
                            </span>
                        </td>
                        <td><strong>${sign}${movimentoNum}</strong></td>
                        <td>${mov.idUtente}</td>
                        <td>${mov.descrizione || '-'}</td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
                <a href="storicoEntrateUscite.php?prodotto_filtro=${encodeURIComponent(prodotto.nome)}" class="btn-vedi-altro">
                    <i class="fas fa-list"></i> Vedi tutti i movimenti
                </a>
            `;
        } else {
            html += `
                <div class="no-movimenti">
                    <div class="no-movimenti-icon">📭</div>
                    <h3>Nessun movimento registrato</h3>
                    <p>Non ci sono ancora movimenti per questo prodotto</p>
                </div>
                <a href="entrateUscite.php" class="btn-vedi-altro">
                    <i class="fas fa-plus"></i> Registra un movimento
                </a>
            `;
        }

        modalMovimentiBody.innerHTML = html;
    }

    function closeMovimentiModal() {
        modalMovimenti.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Chiudi modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (modalMovimenti.classList.contains('active')) {
                closeMovimentiModal();
            }
        }
    });

    // Click fuori dal modal
    modalMovimenti.addEventListener('click', function(e) {
        if (e.target === modalMovimenti) {
            closeMovimentiModal();
        }
    });
</script>
</body>
</html>
