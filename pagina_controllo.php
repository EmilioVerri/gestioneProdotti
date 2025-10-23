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

$errore_reg = '';
$successo_reg = '';
$errore_prod = '';
$successo_prod = '';
$errore_gestione = '';
$successo_gestione = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Errore di connessione: ' . $e->getMessage());
}

// Gestione CAMBIO PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambia_password'])) {
    $user_id = intval($_POST['user_id']);
    $nuova_pass = $_POST['nuova_password'];
    $conferma_nuova = $_POST['conferma_nuova_password'];
    
    if (!empty($nuova_pass) && !empty($conferma_nuova)) {
        if ($nuova_pass === $conferma_nuova) {
            if (strlen($nuova_pass) >= 6) {
                try {
                    $password_hash = password_hash($nuova_pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE login SET password = ? WHERE id = ?");
                    $stmt->execute([$password_hash, $user_id]);
                    $successo_gestione = 'Password modificata con successo!';
                } catch (PDOException $e) {
                    $errore_gestione = 'Errore durante la modifica: ' . $e->getMessage();
                }
            } else {
                $errore_gestione = 'La password deve essere lunga almeno 6 caratteri';
            }
        } else {
            $errore_gestione = 'Le password non coincidono';
        }
    } else {
        $errore_gestione = 'Compila tutti i campi per cambiare la password';
    }
}

// Gestione CANCELLAZIONE UTENTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['elimina_utente'])) {
    $user_id = intval($_POST['user_id']);
    
    // Impedisci di cancellare se stesso
    if ($user_id === $_SESSION['user_id']) {
        $errore_gestione = 'Non puoi eliminare il tuo stesso account!';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM login WHERE id = ?");
            $stmt->execute([$user_id]);
            $successo_gestione = 'Utente eliminato con successo!';
        } catch (PDOException $e) {
            $errore_gestione = 'Errore durante l\'eliminazione: ' . $e->getMessage();
        }
    }
}

// Gestione della registrazione utenti
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrati'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    $conferma_pass = $_POST['conferma_password'];
    
    if (!empty($user) && !empty($pass) && !empty($conferma_pass)) {
        if ($pass === $conferma_pass) {
            if (strlen($pass) >= 6) {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM login WHERE username = ?");
                    $stmt->execute([$user]);
                    
                    if ($stmt->fetch()) {
                        $errore_reg = 'Username già esistente';
                    } else {
                        $password_hash = password_hash($pass, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO login (username, password, privilegi) VALUES (?, ?, ?)");
                        $stmt->execute([$user, $password_hash, 'base']);
                        $successo_reg = 'Utente registrato con successo!';
                    }
                } catch (PDOException $e) {
                    $errore_reg = 'Errore durante la registrazione: ' . $e->getMessage();
                }
            } else {
                $errore_reg = 'La password deve essere lunga almeno 6 caratteri';
            }
        } else {
            $errore_reg = 'Le password non coincidono';
        }
    } else {
        $errore_reg = 'Compila tutti i campi';
    }
}

// Gestione inserimento prodotti
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiungi_prodotto'])) {
    $nome = trim($_POST['nome']);
    $descrizione = trim($_POST['descrizione']);
    $quantita = intval($_POST['quantita']);
    $fornitore = trim($_POST['fornitore']);
    
    if (!empty($nome) && !empty($descrizione) && $quantita >= 0 && !empty($fornitore)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO prodotti (nome, descrizione, quantita, allarme, fornitore) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $descrizione, $quantita, 'nessuno', $fornitore]);
            
            $successo_prod = 'Prodotto aggiunto con successo!';
        } catch (PDOException $e) {
            $errore_prod = 'Errore durante l\'inserimento: ' . $e->getMessage();
        }
    } else {
        $errore_prod = 'Compila tutti i campi correttamente';
    }
}

// Carica tutti gli utenti
try {
    $stmt = $pdo->query("SELECT id, username, privilegi FROM login ORDER BY username ASC");
    $utenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $utenti = [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagina Controllo - Gestione Prodotti</title>
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
        
        .sections-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
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
        }
        
        .form-group {
            margin-bottom: 20px;
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
        
        input[type="text"],
        input[type="password"],
        input[type="number"],
        textarea,
        select {
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
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #1a1a1a;
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .password-strength {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
            border-radius: 2px;
        }
        
        .strength-weak { width: 33%; background: #ff4444; }
        .strength-medium { width: 66%; background: #ffaa00; }
        .strength-strong { width: 100%; background: #00cc00; }
        
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
            margin-top: 10px;
        }
        
        button[type="submit"]:hover {
            background: #000;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        
        button[type="submit"]:active {
            transform: translateY(0);
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
        
        .requisiti {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Tabella Utenti */
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .users-table thead {
            background: #f9f9f9;
        }
        
        .users-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .users-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .users-table tbody tr {
            transition: all 0.3s;
        }
        
        .users-table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-admin {
            background: #ff6b6b;
            color: white;
        }
        
        .badge-base {
            background: #4caf50;
            color: white;
        }
        
        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 5px;
        }
        
        .btn-edit {
            background: #2196F3;
            color: white;
        }
        
        .btn-edit:hover {
            background: #1976D2;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: #ff6b6b;
            color: white;
        }
        
        .btn-delete:hover {
            background: #ff5252;
            transform: translateY(-2px);
        }
        
        .btn-delete:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .modal-header h3 {
            color: #1a1a1a;
            font-size: 20px;
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
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-cancel {
            flex: 1;
            background: #e0e0e0;
            color: #1a1a1a;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background: #d0d0d0;
        }
        
        .btn-confirm {
            flex: 1;
            background: #1a1a1a;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-confirm:hover {
            background: #000;
        }
        
        .btn-confirm.delete {
            background: #ff6b6b;
        }
        
        .btn-confirm.delete:hover {
            background: #ff5252;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .users-table {
                font-size: 13px;
            }
            
            .users-table th,
            .users-table td {
                padding: 10px;
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
    
    <!-- Modal Cambio Password -->
    <div class="modal" id="modalPassword">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🔑 Cambia Password</h3>
                <button class="modal-close" onclick="closeModal('modalPassword')">×</button>
            </div>
            <form method="POST" id="formCambioPassword">
                <input type="hidden" name="user_id" id="change_user_id">
                <p style="margin-bottom: 20px; color: #666;">
                    Modifica password per: <strong id="change_username"></strong>
                </p>
                <div class="form-group">
                    <label for="nuova_password">Nuova Password</label>
                    <input type="password" id="nuova_password" name="nuova_password" required minlength="6">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBarModal"></div>
                    </div>
                    <div class="requisiti">Minimo 6 caratteri</div>
                </div>
                <div class="form-group">
                    <label for="conferma_nuova_password">Conferma Nuova Password</label>
                    <input type="password" id="conferma_nuova_password" name="conferma_nuova_password" required minlength="6">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalPassword')">Annulla</button>
                    <button type="submit" class="btn-confirm" name="cambia_password">Cambia Password</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Conferma Eliminazione -->
    <div class="modal" id="modalDelete">
        <div class="modal-content">
            <div class="modal-header">
                <h3>⚠️ Conferma Eliminazione</h3>
                <button class="modal-close" onclick="closeModal('modalDelete')">×</button>
            </div>
            <p style="margin-bottom: 20px; color: #666; font-size: 15px;">
                Sei sicuro di voler eliminare l'utente <strong id="delete_username"></strong>?<br>
                <span style="color: #ff6b6b; font-size: 13px;">Questa azione è irreversibile!</span>
            </p>
            <form method="POST" id="formEliminaUtente">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalDelete')">Annulla</button>
                    <button type="submit" class="btn-confirm delete" name="elimina_utente">Elimina Utente</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">GP</div>
            <div class="sidebar-title">Gestione Prodotti</div>
        </div>
        
       <?php include './widget/menu.php'; ?>
        
        <div class="sidebar-footer">
            v1.0.0 - © 2025
        </div>
    </div>
    
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-left">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <div class="navbar-logo">GP</div>
            <h1>Pagina Controllo</h1>
        </div>
        <div class="user-info">
            <span>Benvenuto, <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <a href="./logout.php" class="btn-logout">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header">
            <h2>Pannello di Controllo</h2>
            <p>Gestisci utenti e prodotti del sistema da questa pagina</p>
        </div>
        
        <div class="sections-container">
            <!-- Sezione Gestione Utenti Esistenti -->
            <div class="section-card">
                <div class="section-header">
                    <span class="section-icon">👥</span>
                    <h3>Gestione Utenti Esistenti</h3>
                </div>
                
                <?php if ($errore_gestione): ?>
                    <div class="messaggio errore"><?php echo htmlspecialchars($errore_gestione); ?></div>
                <?php endif; ?>
                
                <?php if (count($utenti) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Privilegi</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($utenti as $utente): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($utente['id']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($utente['username']); ?></strong>
                                            <?php if ($utente['id'] === $_SESSION['user_id']): ?>
                                                <span style="color: #2196F3; font-size: 12px;"> (Tu)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $utente['privilegi'] === 'admin' ? 'badge-admin' : 'badge-base'; ?>">
                                                <?php echo ucfirst(htmlspecialchars($utente['privilegi'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn-action btn-edit" 
                                                    onclick="openPasswordModal(<?php echo $utente['id']; ?>, '<?php echo htmlspecialchars($utente['username'], ENT_QUOTES); ?>')">
                                                🔑 Cambia Password
                                            </button>
                                            <button class="btn-action btn-delete" 
                                                    onclick="openDeleteModal(<?php echo $utente['id']; ?>, '<?php echo htmlspecialchars($utente['username'], ENT_QUOTES); ?>')"
                                                    <?php echo $utente['id'] === $_SESSION['user_id'] ? 'disabled title="Non puoi eliminare te stesso"' : ''; ?>>
                                                🗑️ Elimina
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 40px; color: #999;">Nessun utente trovato</p>
                <?php endif; ?>
            </div>
            
            <!-- Sezione Inserimento Prodotti -->
            <div class="section-card">
                <div class="section-header">
                    <span class="section-icon">📦</span>
                    <h3>Inserimento Nuovo Prodotto</h3>
                </div>
                
                <?php if ($errore_prod): ?>
                    <div class="messaggio errore"><?php echo htmlspecialchars($errore_prod); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" id="formProdotto">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nome">Nome Prodotto *</label>
                            <input type="text" id="nome" name="nome" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantita">Quantità *</label>
                            <input type="number" id="quantita" name="quantita" min="0" value="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="fornitore">Fornitore *</label>
                            <input type="text" id="fornitore" name="fornitore" required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="descrizione">Descrizione *</label>
                            <textarea id="descrizione" name="descrizione" required></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="aggiungi_prodotto">Aggiungi Prodotto</button>
                </form>
            </div>
            
            <!-- Sezione Registrazione Utenti -->
            <div class="section-card">
                <div class="section-header">
                    <span class="section-icon">👤</span>
                    <h3>Registrazione Nuovi Utenti</h3>
                </div>
                
                <?php if ($errore_reg): ?>
                    <div class="messaggio errore"><?php echo htmlspecialchars($errore_reg); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" id="formRegistrazione">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required minlength="3">
                            <div class="requisiti">Minimo 3 caratteri</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required minlength="6">
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="requisiti">Minimo 6 caratteri</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="conferma_password">Conferma Password</label>
                            <input type="password" id="conferma_password" name="conferma_password" required minlength="6">
                        </div>
                    </div>
                    
                    <button type="submit" name="registrati">Registra Utente</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Funzioni Modal
        function openPasswordModal(userId, username) {
            document.getElementById('change_user_id').value = userId;
            document.getElementById('change_username').textContent = username;
            document.getElementById('modalPassword').classList.add('show');
            document.getElementById('nuova_password').value = '';
            document.getElementById('conferma_nuova_password').value = '';
            document.getElementById('strengthBarModal').className = 'password-strength-bar';
        }
        
        function openDeleteModal(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_username').textContent = username;
            document.getElementById('modalDelete').classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        // Chiudi modal cliccando fuori
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
        
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
            }, 4000);
        }
        
        function closePopup() {
            const popup = document.getElementById('popupNotification');
            popup.classList.remove('show');
        }
        
        // Show popup on page load
        <?php if ($successo_prod): ?>
            showPopup('success', 'Prodotto Aggiunto!', '<?php echo addslashes($successo_prod); ?>');
        <?php endif; ?>
        
        <?php if ($successo_reg): ?>
            showPopup('success', 'Utente Registrato!', '<?php echo addslashes($successo_reg); ?>');
        <?php endif; ?>
        
        <?php if ($successo_gestione): ?>
            showPopup('success', 'Operazione Completata!', '<?php echo addslashes($successo_gestione); ?>');
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
        
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Password strength per form registrazione
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        const confermaInput = document.getElementById('conferma_password');
        
        passwordInput.addEventListener('input', function() {
            const pass = this.value;
            const strength = calcolaForza(pass);
            
            strengthBar.className = 'password-strength-bar';
            
            if (strength === 1) {
                strengthBar.classList.add('strength-weak');
            } else if (strength === 2) {
                strengthBar.classList.add('strength-medium');
            } else if (strength === 3) {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // Password strength per modal cambio password
        const nuovaPasswordInput = document.getElementById('nuova_password');
        const strengthBarModal = document.getElementById('strengthBarModal');
        const confermaNuovaInput = document.getElementById('conferma_nuova_password');
        
        nuovaPasswordInput.addEventListener('input', function() {
            const pass = this.value;
            const strength = calcolaForza(pass);
            
            strengthBarModal.className = 'password-strength-bar';
            
            if (strength === 1) {
                strengthBarModal.classList.add('strength-weak');
            } else if (strength === 2) {
                strengthBarModal.classList.add('strength-medium');
            } else if (strength === 3) {
                strengthBarModal.classList.add('strength-strong');
            }
        });
        
        function calcolaForza(pass) {
            if (pass.length === 0) return 0;
            if (pass.length < 6) return 1;
            
            let forza = 1;
            if (pass.length >= 8) forza++;
            if (/[A-Z]/.test(pass) && /[a-z]/.test(pass) && /[0-9]/.test(pass)) forza++;
            
            return forza;
        }
        
        // Verifica corrispondenza password (form registrazione)
        confermaInput.addEventListener('input', function() {
            if (passwordInput.value !== this.value) {
                this.style.borderColor = '#ff4444';
            } else {
                this.style.borderColor = '#00cc00';
            }
        });
        
        // Verifica corrispondenza password (modal)
        confermaNuovaInput.addEventListener('input', function() {
            if (nuovaPasswordInput.value !== this.value) {
                this.style.borderColor = '#ff4444';
            } else {
                this.style.borderColor = '#00cc00';
            }
        });
        
        // Validazione form registrazione
        document.getElementById('formRegistrazione').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = passwordInput.value;
            const conferma = confermaInput.value;
            
            if (username.length < 3) {
                e.preventDefault();
                alert('Username deve essere di almeno 3 caratteri!');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('La password deve essere di almeno 6 caratteri!');
                return;
            }
            
            if (password !== conferma) {
                e.preventDefault();
                alert('Le password non coincidono!');
                return;
            }
        });
        
        // Validazione form cambio password
        document.getElementById('formCambioPassword').addEventListener('submit', function(e) {
            const nuovaPass = nuovaPasswordInput.value;
            const confermaNuova = confermaNuovaInput.value;
            
            if (nuovaPass.length < 6) {
                e.preventDefault();
                alert('La password deve essere di almeno 6 caratteri!');
                return;
            }
            
            if (nuovaPass !== confermaNuova) {
                e.preventDefault();
                alert('Le password non coincidono!');
                return;
            }
        });
        
        // Validazione form prodotto
        document.getElementById('formProdotto').addEventListener('submit', function(e) {
            const nome = document.getElementById('nome').value.trim();
            const descrizione = document.getElementById('descrizione').value.trim();
            const quantita = document.getElementById('quantita').value;
            const fornitore = document.getElementById('fornitore').value.trim();
            
            if (!nome || !descrizione || !fornitore) {
                e.preventDefault();
                alert('Compila tutti i campi obbligatori!');
                return;
            }
            
            if (quantita < 0) {
                e.preventDefault();
                alert('La quantità non può essere negativa!');
                return;
            }
        });
        
        // Animazione input
        const inputs = document.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateX(3px)';
                this.parentElement.style.transition = 'transform 0.2s';
            });
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateX(0)';
            });
        });
        
        // Reset form dopo successo
        <?php if ($successo_prod): ?>
            setTimeout(() => {
                document.getElementById('formProdotto').reset();
            }, 100);
        <?php endif; ?>
        
        <?php if ($successo_reg): ?>
            setTimeout(() => {
                document.getElementById('formRegistrazione').reset();
                strengthBar.className = 'password-strength-bar';
            }, 100);
        <?php endif; ?>
        
        // Chiudi modal dopo successo
        <?php if ($successo_gestione): ?>
            closeModal('modalPassword');
            closeModal('modalDelete');
        <?php endif; ?>
    </script>
</body>
</html>