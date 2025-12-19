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

// Gestione filtri
$tipoFiltro = isset($_GET['tipo_filtro']) ? $_GET['tipo_filtro'] : 'tutti';
$prodottoFiltro = isset($_GET['prodotto_filtro']) ? $_GET['prodotto_filtro'] : '';
$dataInizio = isset($_GET['data_inizio']) ? $_GET['data_inizio'] : '';
$dataFine = isset($_GET['data_fine']) ? $_GET['data_fine'] : '';
$padreFiltro = isset($_GET['padre_filtro']) ? $_GET['padre_filtro'] : '';
$bollaFiltro = isset($_GET['bolla_filtro']) ? $_GET['bolla_filtro'] : '';
$datoFiltro = isset($_GET['dato_filtro']) ? $_GET['dato_filtro'] : '';
$descrizioneFiltro = isset($_GET['descrizione_filtro']) ? $_GET['descrizione_filtro'] : '';
$paginaCorrente = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$righePerPagina = 50;
$offset = ($paginaCorrente - 1) * $righePerPagina;

// Costruisci query con filtri - ordina sempre per ID decrescente
$query = "SELECT * FROM storicomovimenti WHERE 1=1";
$params = [];

// Filtro per tipo movimento
if ($tipoFiltro === 'entrate') {
    $query .= " AND movimento NOT LIKE '-%'";
} elseif ($tipoFiltro === 'uscite') {
    $query .= " AND movimento LIKE '-%'";
}

// Filtro per prodotto
if ($prodottoFiltro) {
    $query .= " AND idProdotto LIKE ?";
    $params[] = '%' . $prodottoFiltro . '%';
}

// Filtro per data inizio
if ($dataInizio) {
    // Converti la data dal formato gg/mm/aaaa hh:mm al formato aaaa-mm-gg per il confronto
    $query .= " AND STR_TO_DATE(SUBSTRING(dataMovimento, 1, 10), '%d/%m/%Y') >= ?";
    $params[] = $dataInizio;
}

// Filtro per data fine
if ($dataFine) {
    // Converti la data dal formato gg/mm/aaaa hh:mm al formato aaaa-mm-gg per il confronto
    $query .= " AND STR_TO_DATE(SUBSTRING(dataMovimento, 1, 10), '%d/%m/%Y') <= ?";
    $params[] = $dataFine;
}
// Filtro per idPadre
if ($padreFiltro) {
    $query .= " AND idPadre LIKE ?";
    $params[] = '%' . $padreFiltro . '%';
}
// Filtro per Numero Lista Taglio (bollaNumero)
if ($bollaFiltro) {
    $query .= " AND bollaNumero LIKE ?";
    $params[] = '%' . $bollaFiltro . '%';
}

// Filtro per Numero Offerta (datoNumero)
if ($datoFiltro) {
    $query .= " AND datoNumero LIKE ?";
    $params[] = '%' . $datoFiltro . '%';
}

// Filtro per Descrizione
if ($descrizioneFiltro) {
    $query .= " AND descrizione LIKE ?";
    $params[] = '%' . $descrizioneFiltro . '%';
}
// Conta totale righe per paginazione
$queryCount = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$stmtCount = $pdo->prepare($queryCount);
$stmtCount->execute($params);
$totaleRighe = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalePagine = ceil($totaleRighe / $righePerPagina);

// Query principale con paginazione - ORDINA PER ID DECRESCENTE
$query .= " ORDER BY id DESC LIMIT $righePerPagina OFFSET $offset";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $movimenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Errore query: ' . $e->getMessage());
}

// Carica lista prodotti per il filtro
try {
    $stmtProdotti = $pdo->query("SELECT DISTINCT nome FROM prodotti ORDER BY nome ASC");
    $prodotti = $stmtProdotti->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $prodotti = [];
}

// Calcola statistiche TOTALI
$queryStats = str_replace(" ORDER BY id DESC LIMIT $righePerPagina OFFSET $offset", "", $query);
try {
    $stmtStats = $pdo->prepare($queryStats);
    $stmtStats->execute($params);
    $movimentiStats = $stmtStats->fetchAll(PDO::FETCH_ASSOC);
    
    $totaleEntrate = 0;
    $totaleUscite = 0;
    foreach ($movimentiStats as $mov) {
        $movimentoValue = str_replace('+', '', $mov['movimento']);
        $movimentoValue = intval($movimentoValue);
        
        if (strpos($mov['movimento'], '-') === 0) {
            $totaleUscite += abs($movimentoValue);
        } else {
            $totaleEntrate += abs($movimentoValue);
        }
    }
} catch (PDOException $e) {
    $totaleEntrate = 0;
    $totaleUscite = 0;
}

// Funzione helper per costruire URL con parametri
function buildUrlParams($pagina = null) {
    global $tipoFiltro, $prodottoFiltro, $dataInizio, $dataFine, $padreFiltro, $bollaFiltro, $datoFiltro, $descrizioneFiltro;
    $params = [];
    
    if ($pagina !== null) {
        $params[] = 'pagina=' . $pagina;
    }
    if ($tipoFiltro !== 'tutti') {
        $params[] = 'tipo_filtro=' . urlencode($tipoFiltro);
    }
    if ($prodottoFiltro) {
        $params[] = 'prodotto_filtro=' . urlencode($prodottoFiltro);
    }
    if ($dataInizio) {
        $params[] = 'data_inizio=' . urlencode($dataInizio);
    }
    if ($dataFine) {
        $params[] = 'data_fine=' . urlencode($dataFine);
    }
    if ($padreFiltro) {
        $params[] = 'padre_filtro=' . urlencode($padreFiltro); // NUOVO
    }
    if ($bollaFiltro) {
        $params[] = 'bolla_filtro=' . urlencode($bollaFiltro);
    }
    if ($datoFiltro) {
        $params[] = 'dato_filtro=' . urlencode($datoFiltro);
    }
    if ($descrizioneFiltro) {
        $params[] = 'descrizione_filtro=' . urlencode($descrizioneFiltro);
    }
    
    return !empty($params) ? '?' . implode('&', $params) : '';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storico Movimenti - Gestione Prodotti</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
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
        
        .stat-entrate {
            color: #4caf50;
        }
        
        .stat-uscite {
            color: #ff6b6b;
        }
        
        /* Filters Card */
        .filters-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            animation: slideUp 0.5s;
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
        
        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .filters-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .filter-icon {
            font-size: 28px;
        }
        
        .filters-title h3 {
            color: #1a1a1a;
            font-size: 20px;
        }
        
        .btn-export-pdf {
            background: #ff6b6b;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-export-pdf:hover {
            background: #ff5252;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 8px;
            color: #1a1a1a;
            font-weight: 500;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f9f9f9;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #1a1a1a;
            background: white;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-filter {
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-apply {
            background: #1a1a1a;
            color: white;
        }
        
        .btn-apply:hover {
            background: #000;
            transform: translateY(-2px);
        }
        
        .btn-reset {
            background: #e0e0e0;
            color: #1a1a1a;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-reset:hover {
            background: #d0d0d0;
        }
        
        /* Table Card */
        .table-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            animation: slideUp 0.6s;
        }
        
        .table-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .table-icon {
            font-size: 28px;
        }
        
        .table-header h3 {
            color: #1a1a1a;
            font-size: 20px;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        .movimenti-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .movimenti-table thead {
            background: #f9f9f9;
        }
        
        .movimenti-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
        }
        
        .movimenti-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .movimenti-table tbody tr {
            transition: all 0.3s;
        }
        
        .movimenti-table tbody tr:hover {
            transform: scale(1.002);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
            font-size: 13px;
        }
        
        .badge-entrata {
            background: #4caf50;
            color: white;
        }
        
        .badge-uscita {
            background: #ff6b6b;
            color: white;
        }
        
        .prodotto-nome {
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .no-data {
            text-align: center;
            padding: 60px;
            color: #999;
        }
        
        .no-data-icon {
            font-size: 60px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding: 20px 0;
            flex-wrap: wrap;
        }
        
        .pagination-btn {
            padding: 8px 16px;
            border: 2px solid #e0e0e0;
            background: white;
            color: #1a1a1a;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }
        
        .pagination-btn.active {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-info {
            color: #666;
            font-size: 14px;
            padding: 0 10px;
        }
        
        .debug-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .debug-box h3 {
            color: #856404;
            margin-bottom: 15px;
        }
        
        .debug-box pre {
            background: white;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        @media (max-width: 768px) {
            .navbar h1 {
                font-size: 18px;
            }
            
            .user-info span {
                display: none;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                flex-direction: column;
            }
            
            .btn-filter {
                width: 100%;
            }
            
            .filters-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .btn-export-pdf {
                width: 100%;
                justify-content: center;
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
        
        <?php include './widget/menu.php'; ?>
    </div>
    
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-left">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div class="navbar-logo">GP</div>
            <h1>Storico Movimenti</h1>
        </div>
        <div class="user-info">
            <span>Benvenuto, <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <a href="./logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <!-- DEBUG MODE -->
        <?php if (isset($_GET['debug'])): ?>
        <div class="debug-box">
            <h3>🔍 DEBUG - Informazioni Database</h3>
            <pre><?php
                echo "Totale record: " . $pdo->query("SELECT COUNT(*) FROM storicomovimenti")->fetchColumn() . "\n\n";
                echo "Ultimi 10 record:\n";
                $debugStmt = $pdo->query("SELECT * FROM storicomovimenti ORDER BY id DESC LIMIT 10");
                print_r($debugStmt->fetchAll(PDO::FETCH_ASSOC));
                echo "\n\nQuery utilizzata:\n" . $query;
                echo "\n\nParametri:\n";
                print_r($params);
            ?></pre>
            <p style="margin-top: 15px;"><a href="?" style="color: #856404; font-weight: bold;">← Torna alla vista normale</a></p>
        </div>
        <?php endif; ?>
        
        <div class="page-header">
            <h2>Storico Movimenti Magazzino</h2>
            <p>Visualizza tutti i movimenti ordinati dal più recente (50 righe per pagina)
            </p>
        </div>
        
        <!-- Statistiche -->
        <div class="stats">
            <div class="stat-card">
                <h4>Totale Movimenti</h4>
                <div class="number"><?php echo $totaleRighe; ?></div>
            </div>
            <div class="stat-card">
                <h4>Totale Entrate</h4>
                <div class="number stat-entrate">+<?php echo $totaleEntrate; ?></div>
            </div>
            <div class="stat-card">
                <h4>Totale Uscite</h4>
                <div class="number stat-uscite">-<?php echo $totaleUscite; ?></div>
            </div>
        </div>
        
        <!-- Filtri -->
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <span class="filter-icon">🔍</span>
                    <h3>Filtri di Ricerca</h3>
                </div>
                <button class="btn-export-pdf" onclick="esportaPDF()">
                    <span>📄</span>
                    <span>Esporta PDF</span>
                </button>
            </div>
            
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="tipo_filtro">Tipo Movimento</label>
                        <select id="tipo_filtro" name="tipo_filtro">
                            <option value="tutti" <?php echo $tipoFiltro === 'tutti' ? 'selected' : ''; ?>>Tutti i Movimenti</option>
                            <option value="entrate" <?php echo $tipoFiltro === 'entrate' ? 'selected' : ''; ?>>Solo Entrate (Verde)</option>
                            <option value="uscite" <?php echo $tipoFiltro === 'uscite' ? 'selected' : ''; ?>>Solo Uscite (Rosso)</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="prodotto_filtro">Cerca Prodotto</label>
                        <input type="text" id="prodotto_filtro" name="prodotto_filtro" placeholder="Nome prodotto..." value="<?php echo htmlspecialchars($prodottoFiltro); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="data_inizio">📅 Data Inizio</label>
                        <input type="date" id="data_inizio" name="data_inizio" value="<?php echo htmlspecialchars($dataInizio); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="data_fine">📅 Data Fine</label>
                        <input type="date" id="data_fine" name="data_fine" value="<?php echo htmlspecialchars($dataFine); ?>">
                    </div>

                    <div class="filter-group">
    <label for="padre_filtro">🔗 ID Padre</label>
    <input type="text" id="padre_filtro" name="padre_filtro" placeholder="Cerca per ID Padre..." value="<?php echo htmlspecialchars($padreFiltro); ?>">
</div>
<div class="filter-group">
    <label for="bolla_filtro">📋 Numero Lista Taglio</label>
    <input type="text" id="bolla_filtro" name="bolla_filtro" placeholder="Cerca Numero Lista Taglio..." value="<?php echo htmlspecialchars($bollaFiltro); ?>">
</div>

<div class="filter-group">
    <label for="dato_filtro">📄 Numero Offerta</label>
    <input type="text" id="dato_filtro" name="dato_filtro" placeholder="Cerca Numero Offerta..." value="<?php echo htmlspecialchars($datoFiltro); ?>">
</div>

<div class="filter-group">
    <label for="descrizione_filtro">📝 Descrizione</label>
    <input type="text" id="descrizione_filtro" name="descrizione_filtro" placeholder="Cerca nella Descrizione..." value="<?php echo htmlspecialchars($descrizioneFiltro); ?>">
</div>
                </div>
                
                <div class="filter-buttons">
                    <a href="storicoEntrateUscite.php" class="btn-filter btn-reset">Resetta Filtri</a>
                    <button type="submit" class="btn-filter btn-apply">Applica Filtri</button>
                </div>
            </form>
        </div>
        
        <!-- Tabella Movimenti -->
        <div class="table-card">
            <div class="table-header">
                <span class="table-icon">📋</span>
                <h3>Elenco Movimenti (Pagina <?php echo $paginaCorrente; ?> di <?php echo $totalePagine; ?>)</h3>
            </div>
            
            <?php if (count($movimenti) > 0): ?>
                <div class="table-wrapper">
                    <table class="movimenti-table">
                        <thead>
                            <tr>
                                <!--<th>ID</th>-->
                                <th>Data e Ora</th>
                                <th>Prodotto</th>
                                <th>Movimento</th>
                                <th>Utente</th>
                                <th>NUMERO LISTA TAGLIO</th>
                                <th>NUMERO OFFERTA</th>
                                <th>Descrizione</th>
                                <th>Padre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimenti as $mov): ?>
                                <?php 
                                $isUscita = (strpos($mov['movimento'], '-') === 0);
                                $movimentoValue = abs(intval($mov['movimento']));
                                ?>
                                <tr class="<?php echo $isUscita ? 'row-uscita' : 'row-entrata'; ?>">
                                   <!-- <td><?php //echo htmlspecialchars($mov['id']); ?></td>-->
                                    <td><?php echo htmlspecialchars($mov['dataMovimento']); ?></td>
                                    <td class="prodotto-nome"><?php echo htmlspecialchars($mov['idProdotto']); ?></td>
                                    <td>
                                        <span class="badge-movimento <?php echo $isUscita ? 'badge-uscita' : 'badge-entrata'; ?>">
                                            <?php echo $isUscita ? '↓' : '↑'; ?>
                                            <?php echo $isUscita ? '-' : '+'; ?><?php echo $movimentoValue; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($mov['idUtente']); ?></td>
                                    <td><?php echo htmlspecialchars($mov['bollaNumero'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($mov['datoNumero'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($mov['descrizione'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($mov['idPadre'] ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginazione -->
                <?php if ($totalePagine > 1): ?>
                    <div class="pagination">
                        <?php if ($paginaCorrente > 1): ?>
                            <a href="<?php echo buildUrlParams($paginaCorrente - 1); ?>" class="pagination-btn">
                                ← Precedente
                            </a>
                        <?php else: ?>
                            <button class="pagination-btn" disabled>← Precedente</button>
                        <?php endif; ?>
                        
                        <?php
                        $range = 2;
                        $start = max(1, $paginaCorrente - $range);
                        $end = min($totalePagine, $paginaCorrente + $range);
                        
                        if ($start > 1) {
                            ?>
                            <a href="<?php echo buildUrlParams(1); ?>" class="pagination-btn">1</a>
                            <?php if ($start > 2): ?>
                                <span class="pagination-info">...</span>
                            <?php endif;
                        }
                        
                        for ($i = $start; $i <= $end; $i++): ?>
                            <a href="<?php echo buildUrlParams($i); ?>" 
                               class="pagination-btn <?php echo $i === $paginaCorrente ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor;
                        
                        if ($end < $totalePagine) {
                            if ($end < $totalePagine - 1): ?>
                                <span class="pagination-info">...</span>
                            <?php endif; ?>
                            <a href="<?php echo buildUrlParams($totalePagine); ?>" class="pagination-btn"><?php echo $totalePagine; ?></a>
                        <?php } ?>
                        
                        <span class="pagination-info">
                            Pagina <?php echo $paginaCorrente; ?> di <?php echo $totalePagine; ?>
                        </span>
                        
                        <?php if ($paginaCorrente < $totalePagine): ?>
                            <a href="<?php echo buildUrlParams($paginaCorrente + 1); ?>" class="pagination-btn">
                                Successiva →
                            </a>
                        <?php else: ?>
                            <button class="pagination-btn" disabled>Successiva →</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">📭</div>
                    <h3>Nessun movimento trovato</h3>
                    <p>Non ci sono movimenti nel database o i filtri applicati non hanno prodotto risultati</p>
                </div>
            <?php endif; ?>
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
        
        // Dati movimenti per export PDF
        const movimentiData = <?php 
            $queryPdf = str_replace(" ORDER BY id DESC LIMIT $righePerPagina OFFSET $offset", " ORDER BY id DESC", $query);
            try {
                $stmtPdf = $pdo->prepare($queryPdf);
                $stmtPdf->execute($params);
                $movimentiPdf = $stmtPdf->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($movimentiPdf);
            } catch (PDOException $e) {
                echo '[]';
            }
        ?>;
        
        // Funzione esporta PDF
        async function esportaPDF() {
            if (movimentiData.length === 0) {
                alert('Nessun movimento da esportare!');
                return;
            }
            
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'a4'
            });
            
            doc.setFontSize(18);
            doc.setFont(undefined, 'bold');
            doc.text('Storico Movimenti Magazzino', 14, 20);
            
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            let yPos = 30;
            
            <?php if ($tipoFiltro !== 'tutti'): ?>
            doc.text('Tipo: <?php echo ucfirst($tipoFiltro); ?>', 14, yPos);
            yPos += 6;
            <?php endif; ?>
            
            <?php if ($prodottoFiltro): ?>
            doc.text('Prodotto: <?php echo htmlspecialchars($prodottoFiltro); ?>', 14, yPos);
            yPos += 6;
            <?php endif; ?>
            
            <?php if ($dataInizio): ?>
            doc.text('Data Inizio: <?php echo htmlspecialchars($dataInizio); ?>', 14, yPos);
            yPos += 6;
            <?php endif; ?>
            
            <?php if ($dataFine): ?>
            doc.text('Data Fine: <?php echo htmlspecialchars($dataFine); ?>', 14, yPos);
            yPos += 6;
            <?php endif; ?>
            <?php if ($padreFiltro): ?>
doc.text('ID Padre: <?php echo htmlspecialchars($padreFiltro); ?>', 14, yPos);
yPos += 6;
<?php endif; ?>
<?php if ($bollaFiltro): ?>
doc.text('Numero Lista Taglio: <?php echo htmlspecialchars($bollaFiltro); ?>', 14, yPos);
yPos += 6;
<?php endif; ?>

<?php if ($datoFiltro): ?>
doc.text('Numero Offerta: <?php echo htmlspecialchars($datoFiltro); ?>', 14, yPos);
yPos += 6;
<?php endif; ?>

<?php if ($descrizioneFiltro): ?>
doc.text('Descrizione: <?php echo htmlspecialchars($descrizioneFiltro); ?>', 14, yPos);
yPos += 6;
<?php endif; ?>
            
            doc.text('Data generazione: ' + new Date().toLocaleString('it-IT'), 14, yPos);
            doc.text('Totale movimenti: ' + movimentiData.length, 200, yPos);
            yPos += 10;
            
            let totaleEntrateExp = 0;
            let totaleUsciteExp = 0;
            
            const tableData = movimentiData.map(mov => {
                const isUscita = mov.movimento.toString().startsWith('-');
                const movimentoNum = Math.abs(parseInt(mov.movimento));
                const movimento = isUscita ? '-' + movimentoNum : '+' + movimentoNum;
                const tipo = isUscita ? 'USCITA' : 'ENTRATA';
                
                if (isUscita) {
                    totaleUsciteExp += movimentoNum;
                } else {
                    totaleEntrateExp += movimentoNum;
                }
                
                return [
                    mov.dataMovimento,
                    mov.idProdotto,
                    tipo,
                    movimento,
                    mov.idUtente,
                    mov.bollaNumero || '-',
                    mov.datoNumero || '-',
                    (mov.descrizione || '-').substring(0, 25),
                     mov.idPadre || '-'
                ];
            });
            
            doc.autoTable({
                startY: yPos,
               head: [['Data/Ora', 'Prodotto', 'Tipo', 'Qta', 'Utente', 'N. Lista', 'N. Offerta', 'Descrizione', 'Padre']],
                body: tableData,
                styles: {
                    fontSize: 7,
                    cellPadding: 2
                },
                headStyles: {
                    fillColor: [26, 26, 26],
                    textColor: [255, 255, 255],
                    fontStyle: 'bold',
                    halign: 'center'
                },
                alternateRowStyles: {
                    fillColor: [245, 245, 245]
                },
                columnStyles: {
    0: { cellWidth: 28, halign: 'center', fontSize: 7 },      // Data/Ora
    1: { cellWidth: 30, fontSize: 7 },                         // Prodotto
    2: { cellWidth: 20, halign: 'center', fontSize: 7 },      // Tipo
    3: { cellWidth: 15, halign: 'center', fontSize: 8 },      // Qta
    4: { cellWidth: 22, fontSize: 7 },                         // Utente
    5: { cellWidth: 22, halign: 'center', fontSize: 7 },      // N. Lista
    6: { cellWidth: 22, halign: 'center', fontSize: 7 },      // N. Offerta
    7: { cellWidth: 50, fontSize: 6 },                         // Descrizione
    8: { cellWidth: 18, halign: 'center', fontSize: 7 }       // ID Padre
},
                didParseCell: function(data) {
    if (data.section === 'body') {
        if (data.column.index === 2) { // Colonna "Tipo"
            const isEntrata = data.cell.raw === 'ENTRATA';
            const fillColor = isEntrata ? [232, 245, 233] : [255, 235, 238];
            const badgeColor = isEntrata ? [76, 175, 80] : [255, 107, 107];
            const textColor = isEntrata ? [76, 175, 80] : [255, 107, 107];
            
            // Applica colore a tutte le celle della riga
            for (let i = 0; i < 9; i++) {
                if (i === 2) { // Badge Tipo
                    data.row.cells[i].styles.fillColor = badgeColor;
                    data.row.cells[i].styles.textColor = [255, 255, 255];
                    data.row.cells[i].styles.fontStyle = 'bold';
                } else if (i === 3) { // Quantità
                    data.row.cells[i].styles.fillColor = fillColor;
                    data.row.cells[i].styles.textColor = textColor;
                    data.row.cells[i].styles.fontStyle = 'bold';
                } else {
                    data.row.cells[i].styles.fillColor = fillColor;
                }
            }
        }
    }
},
                margin: { top: yPos, left: 14, right: 14 }
            });
            
            const finalY = doc.lastAutoTable.finalY + 10;
            doc.setFontSize(11);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(76, 175, 80);
            doc.text('Totale Entrate: +' + totaleEntrateExp, 14, finalY);
            doc.setTextColor(255, 107, 107);
            doc.text('Totale Uscite: -' + totaleUsciteExp, 80, finalY);
            doc.setTextColor(0, 0, 0);
            doc.text('Totale Movimenti: ' + movimentiData.length, 145, finalY);
            
            const dataOggi = new Date().toISOString().split('T')[0];
            doc.save('storico_movimenti_' + dataOggi + '.pdf');
        }
        
        window.addEventListener('load', function() {
            document.querySelectorAll('.stat-card, .filters-card, .table-card').forEach((el, index) => {
                el.style.animationDelay = (index * 0.1) + 's';
            });
        });
    </script>
</body>
</html>