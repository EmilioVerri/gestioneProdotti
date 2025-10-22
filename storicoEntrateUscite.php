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
$dataInizio = isset($_GET['data_inizio']) ? $_GET['data_inizio'] : '';
$dataFine = isset($_GET['data_fine']) ? $_GET['data_fine'] : '';
$tipoFiltro = isset($_GET['tipo_filtro']) ? $_GET['tipo_filtro'] : 'tutti';
$prodottoFiltro = isset($_GET['prodotto_filtro']) ? $_GET['prodotto_filtro'] : '';
$paginaCorrente = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$righePerPagina = 5;
$offset = ($paginaCorrente - 1) * $righePerPagina;

// Costruisci query con filtri
$query = "SELECT * FROM storicomovimenti WHERE 1=1";
$params = [];

// Filtro per data
if ($dataInizio && $dataFine) {
    // Converti le date dal formato yyyy-mm-dd al formato dd/mm/yyyy per il confronto
    $query .= " AND STR_TO_DATE(dataMovimento, '%d/%m/%Y %H:%i') BETWEEN STR_TO_DATE(?, '%Y-%m-%d 00:00') AND STR_TO_DATE(?, '%Y-%m-%d 23:59')";
    $params[] = $dataInizio;
    $params[] = $dataFine;
}

// Filtro per tipo movimento (campo movimento è VARCHAR con "-" per negativi)
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

// Conta totale righe per paginazione (prima dei LIMIT/OFFSET)
$queryCount = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$stmtCount = $pdo->prepare($queryCount);
$stmtCount->execute($params);
$totaleRighe = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalePagine = ceil($totaleRighe / $righePerPagina);

// Aggiungi ordinamento cronologico decrescente (più recenti prima) e paginazione
// Se non ci sono dati in dataMovimento, ordina per id
$query .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $righePerPagina;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $movimenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $movimenti = [];
}

// Carica lista prodotti per il filtro
try {
    $stmtProdotti = $pdo->query("SELECT DISTINCT nome FROM prodotti ORDER BY nome ASC");
    $prodotti = $stmtProdotti->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $prodotti = [];
}

// Calcola statistiche per i movimenti filtrati
$totaleEntrate = 0;
$totaleUscite = 0;
foreach ($movimenti as $mov) {
    $movimentoValue = $mov['movimento'];
    // Se inizia con "-" è un'uscita, altrimenti è un'entrata
    if (strpos($movimentoValue, '-') === 0) {
        $totaleUscite += abs(intval($movimentoValue));
    } else {
        $totaleEntrate += abs(intval($movimentoValue));
    }
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .movimenti-table {
            width: 100%;
            border-collapse: collapse;
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
            background: #f9f9f9;
            transform: scale(1.01);
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
        
        .pagination-btn:hover {
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
         <a href=".\logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header">
            <h2>Storico Movimenti Magazzino</h2>
            <p>Visualizza e filtra tutti i movimenti di entrata e uscita</p>
        </div>
        
        <!-- Statistiche -->
        <div class="stats">
            <div class="stat-card">
                <h4>Totale Movimenti</h4>
                <div class="number"><?php echo $totaleRighe; ?></div>
            </div>
            <div class="stat-card">
                <h4>Entrate (pagina corrente)</h4>
                <div class="number stat-entrate">+<?php echo $totaleEntrate; ?></div>
            </div>
            <div class="stat-card">
                <h4>Uscite (pagina corrente)</h4>
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
                        <label for="data_inizio">Data Inizio</label>
                        <input type="date" id="data_inizio" name="data_inizio" value="<?php echo htmlspecialchars($dataInizio); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="data_fine">Data Fine</label>
                        <input type="date" id="data_fine" name="data_fine" value="<?php echo htmlspecialchars($dataFine); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="tipo_filtro">Tipo Movimento</label>
                        <select id="tipo_filtro" name="tipo_filtro">
                            <option value="tutti" <?php echo $tipoFiltro === 'tutti' ? 'selected' : ''; ?>>Tutti</option>
                            <option value="entrate" <?php echo $tipoFiltro === 'entrate' ? 'selected' : ''; ?>>Solo Entrate</option>
                            <option value="uscite" <?php echo $tipoFiltro === 'uscite' ? 'selected' : ''; ?>>Solo Uscite</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="prodotto_filtro">Prodotto</label>
                        <input type="text" id="prodotto_filtro" name="prodotto_filtro" placeholder="Nome prodotto..." value="<?php echo htmlspecialchars($prodottoFiltro); ?>">
                    </div>
                </div>
                
                <div class="filter-buttons">
                    <a href="storico_movimenti.php" class="btn-filter btn-reset">Resetta Filtri</a>
                    <button type="submit" class="btn-filter btn-apply">Applica Filtri</button>
                </div>
            </form>
        </div>
        
        <!-- Tabella Movimenti -->
        <div class="table-card">
            <div class="table-header">
                <span class="table-icon">📋</span>
                <h3>Elenco Movimenti</h3>
            </div>
            
            <?php if (count($movimenti) > 0): ?>
                <table class="movimenti-table">
                    <thead>
                        <tr>
                            <th>Data e Ora</th>
                            <th>Prodotto</th>
                            <th>Movimento</th>
                            <th>Utente</th>
                            <th>N. Bolla</th>
                            <th>Descrizione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimenti as $mov): ?>
                            <?php 
                            // Controlla se il movimento inizia con "-" per determinare se è uscita
                            $isUscita = (strpos($mov['movimento'], '-') === 0);
                            $movimentoValue = abs(intval($mov['movimento']));
                            ?>
                            <tr class="<?php echo $isUscita ? 'row-uscita' : 'row-entrata'; ?>">
                                <td><?php echo htmlspecialchars($mov['dataMovimento']); ?></td>
                                <td class="prodotto-nome"><?php echo htmlspecialchars($mov['idProdotto']); ?></td>
                                <td>
                                    <?php if ($isUscita): ?>
                                        <span class="badge-movimento badge-uscita">
                                            <span>📤</span>
                                            <span>-<?php echo $movimentoValue; ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-movimento badge-entrata">
                                            <span>📥</span>
                                            <span>+<?php echo $movimentoValue; ?></span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($mov['idUtente']); ?></td>
                                <td><?php echo htmlspecialchars($mov['bollaNumero'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($mov['descrizione'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Paginazione -->
                <?php if ($totalePagine > 1): ?>
                    <div class="pagination">
                        <?php if ($paginaCorrente > 1): ?>
                            <a href="?pagina=<?php echo $paginaCorrente - 1; ?><?php echo $dataInizio ? '&data_inizio=' . $dataInizio : ''; ?><?php echo $dataFine ? '&data_fine=' . $dataFine : ''; ?><?php echo $tipoFiltro !== 'tutti' ? '&tipo_filtro=' . $tipoFiltro : ''; ?><?php echo $prodottoFiltro ? '&prodotto_filtro=' . urlencode($prodottoFiltro) : ''; ?>" class="pagination-btn">
                                ← Precedente
                            </a>
                        <?php else: ?>
                            <button class="pagination-btn" disabled>← Precedente</button>
                        <?php endif; ?>
                        
                        <span class="pagination-info">
                            Pagina <?php echo $paginaCorrente; ?> di <?php echo $totalePagine; ?>
                        </span>
                        
                        <?php if ($paginaCorrente < $totalePagine): ?>
                            <a href="?pagina=<?php echo $paginaCorrente + 1; ?><?php echo $dataInizio ? '&data_inizio=' . $dataInizio : ''; ?><?php echo $dataFine ? '&data_fine=' . $dataFine : ''; ?><?php echo $tipoFiltro !== 'tutti' ? '&tipo_filtro=' . $tipoFiltro : ''; ?><?php echo $prodottoFiltro ? '&prodotto_filtro=' . urlencode($prodottoFiltro) : ''; ?>" class="pagination-btn">
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
                    <p>Prova a modificare i filtri di ricerca</p>
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
        
        // Menu items
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Dati movimenti per export PDF
        const movimentiData = <?php echo json_encode($movimenti); ?>;
        
        // Funzione esporta PDF
        async function esportaPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Titolo
            doc.setFontSize(18);
            doc.setFont(undefined, 'bold');
            doc.text('Storico Movimenti Magazzino', 14, 20);
            
            // Info filtri
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            let yPos = 30;
            
            <?php if ($dataInizio && $dataFine): ?>
            doc.text('Periodo: <?php echo $dataInizio; ?> - <?php echo $dataFine; ?>', 14, yPos);
            yPos += 6;
            <?php endif; ?>
            
            <?php if ($tipoFiltro !== 'tutti'): ?>
            doc.text('Tipo: <?php echo ucfirst($tipoFiltro); ?>', 14, yPos);
            yPos += 6;
            <?php endif; ?>
            
            <?php if ($prodottoFiltro): ?>
            doc.text('Prodotto: <?php echo htmlspecialchars($prodottoFiltro); ?>', 14, yPos);
            yPos += 6;
            <?php endif; ?>
            
            doc.text('Data generazione: ' + new Date().toLocaleString('it-IT'), 14, yPos);
            yPos += 10;
            
            // Prepara dati per la tabella
            const tableData = movimentiData.map(mov => {
                // Controlla se inizia con "-" per determinare il tipo
                const isUscita = mov.movimento.toString().startsWith('-');
                const movimentoNum = Math.abs(parseInt(mov.movimento));
                const movimento = isUscita ? '-' + movimentoNum : '+' + movimentoNum;
                const tipo = isUscita ? 'USCITA' : 'ENTRATA';
                return [
                    mov.dataMovimento,
                    mov.idProdotto,
                    tipo,
                    movimento,
                    mov.idUtente,
                    mov.bollaNumero || '-',
                    mov.descrizione || '-'
                ];
            });
            
            // Aggiungi tabella
            doc.autoTable({
                startY: yPos,
                head: [['Data/Ora', 'Prodotto', 'Tipo', 'Qta', 'Utente', 'Bolla', 'Descrizione']],
                body: tableData,
                styles: {
                    fontSize: 8,
                    cellPadding: 3
                },
                headStyles: {
                    fillColor: [26, 26, 26],
                    textColor: [255, 255, 255],
                    fontStyle: 'bold'
                },
                alternateRowStyles: {
                    fillColor: [245, 245, 245]
                },
                columnStyles: {
                    0: { cellWidth: 28 },
                    1: { cellWidth: 35 },
                    2: { cellWidth: 20 },
                    3: { cellWidth: 15 },
                    4: { cellWidth: 25 },
                    5: { cellWidth: 20 },
                    6: { cellWidth: 45 }
                },
                didParseCell: function(data) {
                    // Colora le righe in base al tipo
                    if (data.section === 'body' && data.column.index === 2) {
                        if (data.cell.raw === 'ENTRATA') {
                            data.cell.styles.fillColor = [232, 245, 233];
                            data.cell.styles.textColor = [76, 175, 80];
                            data.cell.styles.fontStyle = 'bold';
                        } else if (data.cell.raw === 'USCITA') {
                            data.cell.styles.fillColor = [255, 235, 238];
                            data.cell.styles.textColor = [255, 107, 107];
                            data.cell.styles.fontStyle = 'bold';
                        }
                    }
                }
            });
            
            // Footer con totali
            const finalY = doc.lastAutoTable.finalY + 10;
            doc.setFontSize(10);
            doc.setFont(undefined, 'bold');
            doc.text('Totale Entrate: +<?php echo $totaleEntrate; ?>', 14, finalY);
            doc.text('Totale Uscite: -<?php echo $totaleUscite; ?>', 14, finalY + 6);
            doc.text('Totale Movimenti: <?php echo count($movimenti); ?>', 14, finalY + 12);
            
            // Salva PDF
            const dataOggi = new Date().toISOString().split('T')[0];
            doc.save('storico_movimenti_' + dataOggi + '.pdf');
        }
        
        // Validazione date
        const dataInizio = document.getElementById('data_inizio');
        const dataFine = document.getElementById('data_fine');
        
        dataInizio.addEventListener('change', function() {
            if (dataFine.value && this.value > dataFine.value) {
                alert('La data di inizio non può essere successiva alla data di fine');
                this.value = '';
            }
        });
        
        dataFine.addEventListener('change', function() {
            if (dataInizio.value && this.value < dataInizio.value) {
                alert('La data di fine non può essere precedente alla data di inizio');
                this.value = '';
            }
        });
    </script>
</body>
</html>