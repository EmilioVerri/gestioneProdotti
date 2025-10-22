<?php
session_start();

// Verifica se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Gestione logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
$privilegi = $_SESSION['privilegi'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestione Prodotti</title>
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
            max-width: 1200px;
            margin: 100px auto 40px;
            padding: 0 20px;
        }
        
        .welcome-card {
            background: white;
            padding: 40px;
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
            font-size: 32px;
        }
        
        .welcome-card p {
            color: #666;
            font-size: 16px;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            cursor: pointer;
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
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }
        
        .card h3 {
            color: #1a1a1a;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .card p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .card-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar h1 {
                font-size: 18px;
            }
            
            .user-info span {
                display: none;
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
            <div class="menu-item active">
                <span class="menu-icon">🏠</span>
                <span class="menu-text">Dashboard</span>
            </div>
            <div class="menu-item" onclick="alert('Funzionalità in sviluppo')">
                <span class="menu-icon">📦</span>
                <span class="menu-text">Prodotti</span>
            </div>
            <div class="menu-item" onclick="alert('Funzionalità in sviluppo')">
                <span class="menu-icon">🏷️</span>
                <span class="menu-text">Categorie</span>
            </div>
            <div class="menu-item" onclick="alert('Funzionalità in sviluppo')">
                <span class="menu-icon">📊</span>
                <span class="menu-text">Report</span>
            </div>
            <div class="menu-item" onclick="alert('Funzionalità in sviluppo')">
                <span class="menu-icon">📈</span>
                <span class="menu-text">Statistiche</span>
            </div>
           <!-- <div class="menu-item" onclick="alert('Funzionalità in sviluppo')">
                <span class="menu-icon">👥</span>
                <span class="menu-text">Utenti</span>
            </div>
            <div class="menu-item" onclick="alert('Funzionalità in sviluppo')">
                <span class="menu-icon">⚙️</span>
                <span class="menu-text">Impostazioni</span>
            </div>-->
        </div>
        <!--
        <div class="sidebar-footer">
            v1.0.0 - © 2025
        </div>-->
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
            <a href="?logout=1" class="btn-logout">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="welcome-card">
            <h2>Dashboard</h2>
            <p>Benvenuto nel sistema di gestione prodotti. Da qui puoi gestire tutti gli aspetti del tuo inventario.</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h4>Prodotti Totali</h4>
                <div class="number" id="totaleProdotti">0</div>
            </div>
            <div class="stat-card">
                <h4>Categorie</h4>
                <div class="number" id="categorie">0</div>
            </div>
            <div class="stat-card">
                <h4>Valore Inventario</h4>
                <div class="number" id="valore">€ 0</div>
            </div>
        </div>
        
        <div class="cards-grid">
            <div class="card" onclick="alert('Funzionalità in sviluppo')">
                <div class="card-icon">📦</div>
                <h3>Gestione Prodotti</h3>
                <p>Aggiungi, modifica ed elimina prodotti dal tuo inventario</p>
            </div>
            
            <div class="card" onclick="alert('Funzionalità in sviluppo')">
                <div class="card-icon">📊</div>
                <h3>Report</h3>
                <p>Visualizza statistiche e report dettagliati</p>
            </div>
            
            <div class="card" onclick="alert('Funzionalità in sviluppo')">
                <div class="card-icon">🏷️</div>
                <h3>Categorie</h3>
                <p>Gestisci le categorie dei prodotti</p>
            </div>
            
            <div class="card" onclick="alert('Funzionalità in sviluppo')">
                <div class="card-icon">⚙️</div>
                <h3>Impostazioni</h3>
                <p>Configura le impostazioni del sistema</p>
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
        
        // Chiudi sidebar quando clicchi su un menu item
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                menuItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                // Chiudi la sidebar dopo aver cliccato un item
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Animazione contatori
        function animateCounter(element, end, duration) {
            let start = 0;
            const increment = end / (duration / 16);
            
            const timer = setInterval(() => {
                start += increment;
                if (start >= end) {
                    element.textContent = end;
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(start);
                }
            }, 16);
        }
        
        // Simula dati
        window.addEventListener('load', () => {
            setTimeout(() => {
                animateCounter(document.getElementById('totaleProdotti'), 127, 1500);
                animateCounter(document.getElementById('categorie'), 8, 1200);
            }, 300);
            
            // Anima il valore monetario
            setTimeout(() => {
                let valore = 0;
                const valoreFinale = 15420;
                const valoreElement = document.getElementById('valore');
                
                const timer = setInterval(() => {
                    valore += 150;
                    if (valore >= valoreFinale) {
                        valoreElement.textContent = '€ ' + valoreFinale.toLocaleString('it-IT');
                        clearInterval(timer);
                    } else {
                        valoreElement.textContent = '€ ' + valore.toLocaleString('it-IT');
                    }
                }, 16);
            }, 300);
        });
        
        // Aggiungi effetto parallasse leggero alle card
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.animationDelay = (index * 0.1) + 's';
        });
        
        // Effetto hover con movimento del mouse
        cards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 20;
                const rotateY = (centerX - x) / 20;
                
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-5px)`;
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0)';
            });
        });
    </script>
</body>
</html>