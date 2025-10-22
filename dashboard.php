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

// Carica tutti i prodotti
try {
    $stmt = $pdo->query("SELECT * FROM prodotti ORDER BY quantita ASC, nome ASC");
    $prodotti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $prodotti = [];
}

// Filtra prodotti in esaurimento (quantità < 5)
$prodottiEsaurimento = array_filter($prodotti, function($p) {
    return $p['quantita'] < 5;
});

// Calcola statistiche
$totaleProdotti = count($prodotti);
$totaleQuantita = array_sum(array_column($prodotti, 'quantita'));
$prodottiCritici = count($prodottiEsaurimento);
$prodottiOk = $totaleProdotti - $prodottiCritici;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestione Prodotti</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .welcome-card {
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
        
        .welcome-card h2 {
            color: #1a1a1a;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .welcome-card p {
            color: #666;
            font-size: 14px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            animation: fadeIn 0.7s;
        }
        
        .stat-card h4 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        
        .card {
            background: white;
            padding: 30px;
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
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-icon {
            font-size: 32px;
        }
        
        .card-header h3 {
            color: #1a1a1a;
            font-size: 20px;
        }
        
        /* Tabella prodotti */
        .prodotti-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .prodotti-table thead {
            background: #f9f9f9;
        }
        
        .prodotti-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
        }
        
        .prodotti-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .prodotti-table tbody tr {
            transition: background 0.3s;
        }
        
        .prodotti-table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .row-critico {
            background: #ffebee !important;
        }
        
        .row-critico:hover {
            background: #ffcdd2 !important;
        }
        
        .row-ok {
            background: #e8f5e9 !important;
        }
        
        .row-ok:hover {
            background: #c8e6c9 !important;
        }
        
        .badge-qty {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .badge-critico {
            background: #ff6b6b;
            color: white;
        }
        
        .badge-ok {
            background: #4caf50;
            color: white;
        }
        
        .prodotto-nome {
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .no-prodotti {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .no-prodotti-icon {
            font-size: 60px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        /* Grafico */
        .chart-container {
            position: relative;
            height: 350px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chart-full-width {
            grid-column: 1 / -1;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-full-width {
                grid-column: 1;
            }
        }
        
        @media (max-width: 768px) {
            .navbar h1 {
                font-size: 18px;
            }
            
            .user-info span {
                display: none;
            }
            
            .stats {
                grid-template-columns: 1fr;
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
            <h1>Dashboard</h1>
        </div>
        <div class="user-info">
            <span>Benvenuto, <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <a href=".\logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="welcome-card">
            <h2>Dashboard</h2>
            <p>Panoramica generale del magazzino e stato prodotti</p>
        </div>
        
        <div class="stats">
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
                <div class="number" style="color: #ff6b6b;"><?php echo $prodottiCritici; ?></div>
            </div>
            <div class="stat-card">
                <h4>Prodotti OK</h4>
                <div class="number" style="color: #4caf50;"><?php echo $prodottiOk; ?></div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <!-- Prodotti in esaurimento -->
            <div class="card">
                <div class="card-header">
                    <span class="card-icon">⚠️</span>
                    <h3>Prodotti in Esaurimento</h3>
                </div>
                
                <?php if (count($prodottiEsaurimento) > 0): ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="prodotti-table">
                            <thead>
                                <tr>
                                    <th>Prodotto</th>
                                    <th style="text-align: center;">Quantità</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prodottiEsaurimento as $prod): ?>
                                    <tr class="row-critico">
                                        <td class="prodotto-nome"><?php echo htmlspecialchars($prod['nome']); ?></td>
                                        <td style="text-align: center;">
                                            <span class="badge-qty badge-critico">
                                                <?php echo $prod['quantita']; ?> pz
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-prodotti">
                        <div class="no-prodotti-icon">✅</div>
                        <p><strong>Ottimo!</strong><br>Nessun prodotto in esaurimento</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tutti i prodotti con quantità OK -->
            <div class="card">
                <div class="card-header">
                    <span class="card-icon">✅</span>
                    <h3>Prodotti Disponibili</h3>
                </div>
                
                <?php 
                $prodottiOkArray = array_filter($prodotti, function($p) {
                    return $p['quantita'] >= 5;
                });
                ?>
                
                <?php if (count($prodottiOkArray) > 0): ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="prodotti-table">
                            <thead>
                                <tr>
                                    <th>Prodotto</th>
                                    <th style="text-align: center;">Quantità</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prodottiOkArray as $prod): ?>
                                    <tr class="row-ok">
                                        <td class="prodotto-nome"><?php echo htmlspecialchars($prod['nome']); ?></td>
                                        <td style="text-align: center;">
                                            <span class="badge-qty badge-ok">
                                                <?php echo $prod['quantita']; ?> pz
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-prodotti">
                        <div class="no-prodotti-icon">📦</div>
                        <p>Nessun prodotto con scorte sufficienti</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Grafico a torta -->
            <div class="card chart-full-width">
                <div class="card-header">
                    <span class="card-icon">📊</span>
                    <h3>Distribuzione Quantità Prodotti</h3>
                </div>
                <div class="chart-container">
                    <canvas id="pieChart"></canvas>
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
        
        // Menu items
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Dati per il grafico
        const prodotti = <?php echo json_encode($prodotti); ?>;
        
        // Prepara dati per grafico a torta
        const labels = [];
        const quantities = [];
        const backgroundColors = [];
        
        prodotti.forEach(prod => {
            labels.push(prod.nome);
            quantities.push(parseInt(prod.quantita));
            
            // Colore rosso se < 5, verde altrimenti
            if (parseInt(prod.quantita) < 5) {
                backgroundColors.push('#ff6b6b');
            } else {
                backgroundColors.push('#4caf50');
            }
        });
        
        // Crea grafico a torta
        const ctx = document.getElementById('pieChart').getContext('2d');
        const pieChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: quantities,
                    backgroundColor: backgroundColors,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            },
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        const color = data.datasets[0].backgroundColor[i];
                                        return {
                                            text: label + ': ' + value + ' pz',
                                            fillStyle: color,
                                            hidden: false,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value + ' pz (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>