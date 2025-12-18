<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];
$privilegi = $_SESSION['privilegi'];

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

// GESTIONE INSERIMENTO PRODOTTI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiungi_prodotto'])) {
    $padre_nome = trim($_POST['padre_nome']);


            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO padre (nome) VALUES (?)");
            $stmt->execute([$padre_nome]);
            $pdo->commit();

    
    if (!empty($padre_nome)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO prodotti (nome, descrizione, quantita, allarme, fornitore, padre, minimo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            //$stmt->execute([$padre_nome, 'Gruppo padre', 0, 'nessuno', '', $padre_nome, 0]);
            
            $prodotti_da_inserire = [];
            
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'nome_') === 0) {
                    $index = str_replace('nome_', '', $key);
                    $nome = trim($value);
                    $descrizione = trim($_POST['descrizione_' . $index] ?? '');
                    $quantita = intval($_POST['quantita_' . $index] ?? 0);
                    $fornitore = trim($_POST['fornitore_' . $index] ?? '');
                    $minimo = intval($_POST['minimo_' . $index] ?? 0);
                    
                    if (!empty($nome)) {
                        $prodotti_da_inserire[] = [
                            'nome' => $nome,
                            'descrizione' => $descrizione,
                            'quantita' => $quantita,
                            'fornitore' => $fornitore,
                            'minimo' => $minimo,
                            'padre' => $padre_nome
                        ];
                    }
                }
            }
            
            if (count($prodotti_da_inserire) > 0) {
                $stmt = $pdo->prepare("INSERT INTO prodotti (nome, descrizione, quantita, allarme, fornitore, padre, minimo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($prodotti_da_inserire as $prodotto) {
                    $stmt->execute([
                        $prodotto['nome'],
                        $prodotto['descrizione'],
                        $prodotto['quantita'],
                        'nessuno',
                        $prodotto['fornitore'],
                        $prodotto['padre'],
                        $prodotto['minimo']
                    ]);
                }
                
                $pdo->commit();
                $successo = json_encode(['tipo' => 'successo', 'messaggio' => 'Gruppo padre e ' . count($prodotti_da_inserire) . ' componenti aggiunti!']);
            } else {
                $pdo->commit();
                $successo = json_encode(['tipo' => 'successo', 'messaggio' => 'Gruppo padre creato!']);
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errore = json_encode(['tipo' => 'errore', 'messaggio' => 'Errore durante l\'inserimento: ' . $e->getMessage()]);
        }
    }
}

// GESTIONE ELIMINAZIONE SINGOLO PRODOTTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['elimina_prodotto'])) {
    $id = intval($_POST['id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM prodotti WHERE id = ?");
        $stmt->execute([$id]);
        $successo = json_encode(['tipo' => 'successo', 'messaggio' => 'Prodotto eliminato!']);
    } catch (PDOException $e) {
        $errore = json_encode(['tipo' => 'errore', 'messaggio' => 'Errore: ' . $e->getMessage()]);
    }
}

// GESTIONE ELIMINAZIONE GRUPPO PADRE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['elimina_padre'])) {
    $padre_nome = trim($_POST['padre_nome']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM prodotti WHERE padre = ?");
        $stmt->execute([$padre_nome]);
        $stmt = $pdo->prepare("DELETE FROM padre WHERE nome = ?");
        $stmt->execute([$padre_nome]);
        $successo = json_encode(['tipo' => 'successo', 'messaggio' => 'Gruppo eliminato!']);
    } catch (PDOException $e) {
        $errore = json_encode(['tipo' => 'errore', 'messaggio' => 'Errore: ' . $e->getMessage()]);
    }
}

// GESTIONE MODIFICA GRUPPO PADRE COMPLETO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifica_gruppo_padre'])) {
    $vecchio_padre = trim($_POST['vecchio_padre']);
    $nuovo_padre = trim($_POST['nuovo_padre']);
    
    try {
        $pdo->beginTransaction();
        
        // Elimina componenti selezionati
        if (isset($_POST['elimina_componenti']) && is_array($_POST['elimina_componenti'])) {
            foreach ($_POST['elimina_componenti'] as $id) {
                $stmt = $pdo->prepare("DELETE FROM prodotti WHERE id = ?");
                $stmt->execute([intval($id)]);
            }
        }
        
        // Modifica componenti esistenti
        if (isset($_POST['componenti_ids']) && is_array($_POST['componenti_ids'])) {
            foreach ($_POST['componenti_ids'] as $id) {
                $nome = trim($_POST['comp_nome_' . $id] ?? '');
                $descrizione = trim($_POST['comp_descrizione_' . $id] ?? '');
                $quantita = intval($_POST['comp_quantita_' . $id] ?? 0);
                $fornitore = trim($_POST['comp_fornitore_' . $id] ?? '');
                $minimo = intval($_POST['comp_minimo_' . $id] ?? 0);
                
                if (!empty($nome)) {
                    $stmt = $pdo->prepare("UPDATE prodotti SET nome = ?, descrizione = ?, quantita = ?, fornitore = ?, minimo = ?, padre = ? WHERE id = ?");
                    $stmt->execute([$nome, $descrizione, $quantita, $fornitore, $minimo, $nuovo_padre, intval($id)]);

                    $stmt = $pdo->prepare("UPDATE padre SET nome = ? WHERE nome = ?");
                    $stmt->execute([ $nuovo_padre, $vecchio_padre]);
                }
            }
        }
        
        // Aggiungi nuovi componenti
        if (isset($_POST['nuovi_componenti']) && is_array($_POST['nuovi_componenti'])) {
            foreach ($_POST['nuovi_componenti'] as $index) {
                $nome = trim($_POST['nuovo_nome_' . $index] ?? '');
                $descrizione = trim($_POST['nuovo_descrizione_' . $index] ?? '');
                $quantita = intval($_POST['nuovo_quantita_' . $index] ?? 0);
                $fornitore = trim($_POST['nuovo_fornitore_' . $index] ?? '');
                $minimo = intval($_POST['nuovo_minimo_' . $index] ?? 0);
                
                if (!empty($nome)) {
                    $stmt = $pdo->prepare("INSERT INTO prodotti (nome, descrizione, quantita, allarme, fornitore, padre, minimo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nome, $descrizione, $quantita, 'nessuno', $fornitore, $nuovo_padre, $minimo]);
                }
            }
        }
        
        // Aggiorna nome padre
        $stmt = $pdo->prepare("UPDATE prodotti SET nome = ?, padre = ? WHERE padre = ? AND nome = ?");
        $stmt->execute([$nuovo_padre, $nuovo_padre, $vecchio_padre, $vecchio_padre]);
        
        $pdo->commit();
        $successo = json_encode(['tipo' => 'successo', 'messaggio' => 'Gruppo modificato con successo!']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errore = json_encode(['tipo' => 'errore', 'messaggio' => 'Errore: ' . $e->getMessage()]);
    }
}

// GESTIONE MODIFICA SINGOLO PRODOTTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifica_prodotto'])) {
    $id = intval($_POST['id']);
    $nome = trim($_POST['nome']);
    $descrizione = trim($_POST['descrizione']);
    $quantita = intval($_POST['quantita']);
    $fornitore = trim($_POST['fornitore']);
    $minimo = intval($_POST['minimo']);
    
    if (!empty($nome)) {
        try {
            $stmt = $pdo->prepare("UPDATE prodotti SET nome = ?, descrizione = ?, quantita = ?, fornitore = ?, minimo = ? WHERE id = ?");
            $stmt->execute([$nome, $descrizione, $quantita, $fornitore, $minimo, $id]);
            $successo = json_encode(['tipo' => 'successo', 'messaggio' => 'Prodotto modificato!']);
        } catch (PDOException $e) {
            $errore = json_encode(['tipo' => 'errore', 'messaggio' => 'Errore: ' . $e->getMessage()]);
        }
    }
}

// RECUPERA PRODOTTI
$prodotti = [];
try {
    $stmt = $pdo->query("SELECT * FROM prodotti ORDER BY padre ASC, id DESC");
    $prodotti = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errore = json_encode(['tipo' => 'errore', 'messaggio' => 'Errore nel recupero: ' . $e->getMessage()]);
}

$prodotti_per_padre = [];
foreach ($prodotti as $prodotto) {
    $padre = $prodotto['padre'] ?: 'Senza Padre';
    if (!isset($prodotti_per_padre[$padre])) {
        $prodotti_per_padre[$padre] = [];
    }
    $prodotti_per_padre[$padre][] = $prodotto;
}

// COMPONENTI PREDEFINITI
$componenti_predefiniti = [
    'Telaio', 'Telaio Restauro', 'Anta', 'Anta Maniglia Passante',
    'Scambio Battuta', 'Traverso Telaio da mm. 104', 'Traverso Anta da mm. 70',
    'FV Vetro 45 MM', 'FV Fix'
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Prodotti</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f6fa; /* bianco/grigio chiaro */
            min-height: 100vh;
        }
        
        /* NAVBAR */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(26, 26, 26, 0.95);
            backdrop-filter: blur(10px);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            z-index: 998;
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
        
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .user-info {
    display: flex;
    align-items: center;
    gap: 20px;
}
        .navbar-logo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffffffff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            color: #1a1a1a;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.5);
        }
        
        .navbar h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .btn-logout {
            background: white;
            color: #1a1a1a;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.3);
        }
        
        /* CONTAINER */
        .container {
            max-width: 1600px;
            margin: 100px auto 40px;
            padding: 0 20px;
        }
        
        /* SEZIONI */
        .insert-section, .prodotti-section {
            background: white;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            cursor: pointer;
        }
        
        .section-header h3 {
            color: #1a1a1a;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .toggle-icon {
            font-size: 28px;
            transition: transform 0.3s;
            color: #667eea;
        }
        
        .toggle-icon.collapsed {
            transform: rotate(-90deg);
        }
        
        .form-content {
            display: none;
        }
        
        .form-content.expanded {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        /* FORM */
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            color: #1a1a1a;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        /* PADRE SECTION */
        .padre-section {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 2px solid #667eea30;
        }
        
        /* FIGLI CONTAINER */
        .figli-container {
            border: 2px dashed #667eea40;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            background: #f8f9ff;
        }
        
        .figli-header {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 25px;
        }
        
        .figlio-item {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .figlio-item:hover {
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
            border-color: #667eea;
        }
        
        .figlio-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .figlio-numero {
            font-weight: 700;
            color: #667eea;
            font-size: 18px;
        }
        
        .btn-remove-figlio {
            background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.3s;
        }
        
        .btn-remove-figlio:hover {
            transform: rotate(90deg) scale(1.1);
        }
        
        .figlio-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 15px;
        }
        
        /* BUTTONS */
        .btn-add-figlio {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn-add-figlio:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        
        button[type="submit"] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 45px;
            border: none;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        button[type="submit"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
        }
        
        /* PRODOTTI GRUPPO */
        .padre-group {
            margin-bottom: 30px;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            border-radius: 16px;
            border-left: 5px solid #667eea;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .padre-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            padding: 20px;
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .padre-header:hover {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.15);
        }
        
        .padre-title {
            font-size: 26px;
            font-weight: 700;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .padre-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 18px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .padre-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit-padre,
        .btn-delete-padre {
            border: none;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.3s;
        }
        
        .btn-edit-padre {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-edit-padre:hover {
            transform: scale(1.1);
        }
        
        .btn-delete-padre {
            background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%);
            color: white;
        }
        
        .btn-delete-padre:hover {
            transform: scale(1.1);
        }
        
        .figli-list {
            display: none;
            padding-top: 15px;
        }
        
        .figli-list.expanded {
            display: block;
        }
        
        .prodotti-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .prodotto-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        
        .prodotto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
        }
        
        .prodotto-nome {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            min-width: 90px;
        }
        
        /* MODAL */
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
            backdrop-filter: blur(5px);
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .modal-header {
            padding: 25px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border-radius: 16px 16px 0 0;
        }
        
        .modal-header h3 {
            color: #1a1a1a;
            font-size: 22px;
            font-weight: 700;
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
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 2px solid #f0f0f0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        /* ALERT MODERNO */
        .modern-alert {
            position: fixed;
            top: -200px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 25px 35px;
            border-radius: 16px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
            z-index: 99999;
            display: flex;
            align-items: center;
            gap: 20px;
            min-width: 400px;
            max-width: 600px;
            transition: top 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .modern-alert.show {
            top: 100px;
        }
        
        .alert-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }
        
        .modern-alert.successo .alert-icon {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }
        
        .modern-alert.errore .alert-icon {
            background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%);
            color: white;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: 700;
            font-size: 18px;
            color: #1a1a1a;
            margin-bottom: 5px;
        }
        
        .alert-message {
            font-size: 14px;
            color: #666;
        }
        
        .alert-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #999;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .alert-close:hover {
            color: #333;
            transform: rotate(90deg);
        }
        
        @media (max-width: 768px) {
            .figlio-grid {
                grid-template-columns: 1fr;
            }
            
            .prodotti-grid {
                grid-template-columns: 1fr;
            }
            
            .modern-alert {
                min-width: 90%;
            }
        }
    </style>
</head>
<body>
    <!-- ALERT MODERNO -->
    <div class="modern-alert" id="modernAlert">
        <div class="alert-icon" id="alertIcon"></div>
        <div class="alert-content">
            <div class="alert-title" id="alertTitle"></div>
            <div class="alert-message" id="alertMessage"></div>
        </div>
        <button class="alert-close" onclick="closeAlert()">×</button>
    </div>
    
  <!-- Overlay -->
<div class="overlay" id="overlay"></div>

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
        <a href="prodotti.php" class="menu-item active">
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
        <h1>Gestione Prodotti</h1>
    </div>
    <div class="user-info">
        <span>Benvenuto, <strong><?php echo htmlspecialchars($username); ?></strong></span>
        <a href="./logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
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

</script>


    
    <!-- CONTAINER -->
    <div class="container">
        <!-- INSERIMENTO -->
        <div class="insert-section">
            <div class="section-header" onclick="toggleFormSection()">
           <h3><i class="fas fa-plus-circle"></i> Inserimento Nuovo Gruppo Prodotti</h3>
                <span class="toggle-icon collapsed" id="formToggleIcon">▼</span>
            </div>
            
            <div class="form-content" id="formContent">
                <form method="POST" action="" id="formProdotto">
                    <div class="padre-section">
                        <div class="form-group">
                            <label for="padre_nome"><i class="fas fa-folder"></i> Nome Gruppo Padre *</label>
                            <input type="text" id="padre_nome" name="padre_nome" required placeholder="Es: Infisso Standard">
                        </div>
                    </div>
                    
                    <div class="figli-container">
                        <div class="figli-header">
                            <i class="fas fa-cubes"></i> Componenti del Gruppo
                        </div>
                        
                        <div id="figliContainer">
                            <?php foreach ($componenti_predefiniti as $i => $comp): ?>
                            <div class="figlio-item" data-index="<?php echo $i; ?>">
                                <div class="figlio-header-row">
                                    <span class="figlio-numero">Componente #<?php echo $i + 1; ?></span>
                                    <button type="button" class="btn-remove-figlio" onclick="removeFiglio(<?php echo $i; ?>)">×</button>
                                </div>
                                <div class="figlio-grid">
                                    <div class="form-group">
                                        <label>Nome Prodotto *</label>
                                        <input type="text" name="nome_<?php echo $i; ?>" value="<?php echo htmlspecialchars($comp); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Quantità *</label>
                                        <input type="number" name="quantita_<?php echo $i; ?>" min="0" value="0" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Allarme *</label>
                                        <input type="number" name="minimo_<?php echo $i; ?>" min="0" value="0" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Fornitore</label>
                                        <input type="text" name="fornitore_<?php echo $i; ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Descrizione</label>
                                    <textarea name="descrizione_<?php echo $i; ?>"></textarea>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="btn-add-figlio" onclick="addFiglio()">
                            <i class="fas fa-plus"></i> Aggiungi Componente
                        </button>
                    </div>
                    
                    <button type="submit" name="aggiungi_prodotto">
                        <i class="fas fa-save"></i> Salva Gruppo Completo
                    </button>
                </form>
            </div>
        </div>
        
        <!-- ELENCO PRODOTTI -->
        <div class="prodotti-section">
            <h2 style="font-size: 28px; margin-bottom: 25px; color: #1a1a1a; font-weight: 700;">
                <i class="fas fa-box"></i> Elenco Gruppi Prodotti
            </h2>
            
            <?php if (!empty($prodotti_per_padre)): ?>
                <?php foreach ($prodotti_per_padre as $padre => $figli): ?>
                    <div class="padre-group">
                        <div class="padre-header" onclick="toggleFigliList(this)">
                            <div class="padre-title">
                                <i class="fas fa-folder-open"></i>
                                <?php echo htmlspecialchars($padre); ?>
                                <span class="padre-badge"><?php echo count($figli); ?> componenti</span>
                            </div>
                            <div class="padre-actions" onclick="event.stopPropagation()">
                                <button class="btn-edit-padre" onclick="openEditPadreModal('<?php echo htmlspecialchars($padre, ENT_QUOTES); ?>', <?php echo htmlspecialchars(json_encode($figli)); ?>)" title="Modifica gruppo">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-delete-padre" onclick="confirmDeletePadre('<?php echo htmlspecialchars($padre, ENT_QUOTES); ?>', <?php echo count($figli) - 1; ?>)" title="Elimina gruppo">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="figli-list">
                            <div class="prodotti-grid">
                                <?php foreach ($figli as $prodotto): ?>
                                    <?php if ($prodotto['nome'] === $prodotto['padre']) continue; ?>
                                    <div class="prodotto-card">
                                        <div class="prodotto-nome"><?php echo htmlspecialchars($prodotto['nome']); ?></div>
                                        <div class="info-row">
                                            <span class="info-label">Quantità:</span>
                                            <span><?php echo $prodotto['quantita']; ?> pz</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Allarme:</span>
                                            <span><?php echo $prodotto['minimo']; ?> pz</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Fornitore:</span>
                                            <span><?php echo htmlspecialchars($prodotto['fornitore']); ?></span>
                                        </div>
                                        <?php if (!empty($prodotto['descrizione'])): ?>
                                            <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #f0f0f0; color: #666; font-size: 13px;">
                                                <?php echo htmlspecialchars($prodotto['descrizione']); ?>
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
                    <h3 style="color: #1a1a1a; margin-bottom: 10px;">Nessun gruppo trovato</h3>
                    <p style="color: #666;">Aggiungi il tuo primo gruppo di prodotti utilizzando il form sopra</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MODAL MODIFICA GRUPPO PADRE -->
    <div class="modal" id="modalEditPadre">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Modifica Gruppo Padre</h3>
                <button class="modal-close" onclick="closeEditPadreModal()">×</button>
            </div>
            <form method="POST" action="" id="formEditPadre">
                <div class="modal-body">
                    <input type="hidden" id="vecchio_padre" name="vecchio_padre">
                    <input type="hidden" name="componenti_ids[]" id="componentiIdsContainer">
                    
                    <div class="form-group">
                        <label for="nuovo_padre"><i class="fas fa-folder"></i> Nome Gruppo *</label>
                        <input type="text" id="nuovo_padre" name="nuovo_padre" required>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <h4 style="font-size: 18px; font-weight: 700; margin-bottom: 20px; color: #1a1a1a;">
                            <i class="fas fa-cubes"></i> Componenti
                        </h4>
                        <div id="componentiEditContainer"></div>
                        
                        <button type="button" class="btn-add-figlio" onclick="addNuovoComponente()">
                            <i class="fas fa-plus"></i> Aggiungi Nuovo Componente
                        </button>
                    </div>
                    
                    <div id="nuoviComponentiContainer"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeEditPadreModal()" style="background: #e0e0e0; color: #1a1a1a; padding: 12px 25px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        Annulla
                    </button>
                    <button type="submit" name="modifica_gruppo_padre">
                        <i class="fas fa-save"></i> Salva Modifiche
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let figlioCounter = <?php echo count($componenti_predefiniti); ?>;
        let nuovoComponenteCounter = 0;
        
        // GESTIONE ALERT
        function showAlert(tipo, messaggio) {
            const alert = document.getElementById('modernAlert');
            const icon = document.getElementById('alertIcon');
            const title = document.getElementById('alertTitle');
            const message = document.getElementById('alertMessage');
            
            alert.className = 'modern-alert ' + tipo;
            
            if (tipo === 'successo') {
                icon.innerHTML = '<i class="fas fa-check-circle"></i>';
                title.textContent = 'Operazione Completata!';
            } else {
                icon.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
                title.textContent = 'Errore!';
            }
            
            message.textContent = messaggio;
            
            setTimeout(() => alert.classList.add('show'), 100);
            setTimeout(() => closeAlert(), 5000);
        }
        
        function closeAlert() {
            document.getElementById('modernAlert').classList.remove('show');
        }
        
        <?php if ($successo): ?>
            const successData = <?php echo $successo; ?>;
            showAlert(successData.tipo, successData.messaggio);
        <?php endif; ?>
        
        <?php if ($errore): ?>
            const errorData = <?php echo $errore; ?>;
            showAlert(errorData.tipo, errorData.messaggio);
        <?php endif; ?>
        
        // TOGGLE FORM SECTION
        function toggleFormSection() {
            const content = document.getElementById('formContent');
            const icon = document.getElementById('formToggleIcon');
            
            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                icon.classList.add('collapsed');
            } else {
                content.classList.add('expanded');
                icon.classList.remove('collapsed');
            }
        }
        
        // AGGIUNGI FIGLIO
        function addFiglio() {
            const container = document.getElementById('figliContainer');
            const newIndex = figlioCounter;
            
            const newFiglio = document.createElement('div');
            newFiglio.className = 'figlio-item';
            newFiglio.setAttribute('data-index', newIndex);
            newFiglio.innerHTML = `
                <div class="figlio-header-row">
                    <span class="figlio-numero">Componente #${newIndex + 1}</span>
                    <button type="button" class="btn-remove-figlio" onclick="removeFiglio(${newIndex})">×</button>
                </div>
                <div class="figlio-grid">
                    <div class="form-group">
                        <label>Nome Prodotto *</label>
                        <input type="text" name="nome_${newIndex}" required>
                    </div>
                    <div class="form-group">
                        <label>Quantità *</label>
                        <input type="number" name="quantita_${newIndex}" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label>Allarme *</label>
                        <input type="number" name="minimo_${newIndex}" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label>Fornitore</label>
                        <input type="text" name="fornitore_${newIndex}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Descrizione</label>
                    <textarea name="descrizione_${newIndex}"></textarea>
                </div>
            `;
            
            container.appendChild(newFiglio);
            figlioCounter++;
            newFiglio.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // RIMUOVI FIGLIO
        function removeFiglio(index) {
            const figlio = document.querySelector(`.figlio-item[data-index="${index}"]`);
            if (figlio) {
                figlio.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => figlio.remove(), 300);
            }
        }
        
        // TOGGLE FIGLI LIST
        function toggleFigliList(header) {
            const padreGroup = header.closest('.padre-group');
            const figliList = padreGroup.querySelector('.figli-list');
            
            if (figliList.classList.contains('expanded')) {
                figliList.classList.remove('expanded');
            } else {
                figliList.classList.add('expanded');
            }
        }
        
        // MODAL MODIFICA PADRE
        function openEditPadreModal(padreName, figli) {
            document.getElementById('vecchio_padre').value = padreName;
            document.getElementById('nuovo_padre').value = padreName;
            
            const container = document.getElementById('componentiEditContainer');
            const idsContainer = document.getElementById('componentiIdsContainer');
            container.innerHTML = '';
            idsContainer.innerHTML = '';
            
            figli.forEach((prod, index) => {
                if (prod.nome === prod.padre) return;
                
                const div = document.createElement('div');
                div.className = 'figlio-item';
                div.style.marginBottom = '20px';
                div.innerHTML = `
                    <input type="hidden" name="componenti_ids[]" value="${prod.id}">
                    <div class="figlio-header-row">
                        <span class="figlio-numero">Componente #${index}</span>
                        <label style="display: flex; align-items: center; gap: 8px; color: #ff4444; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="elimina_componenti[]" value="${prod.id}" style="width: 20px; height: 20px;">
                            Elimina
                        </label>
                    </div>
                    <div class="figlio-grid">
                        <div class="form-group">
                            <label>Nome Prodotto *</label>
                            <input type="text" name="comp_nome_${prod.id}" value="${prod.nome}" required>
                        </div>
                        <div class="form-group">
                            <label>Quantità *</label>
                            <input type="number" name="comp_quantita_${prod.id}" value="${prod.quantita}" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Allarme *</label>
                            <input type="number" name="comp_minimo_${prod.id}" value="${prod.minimo || 0}" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Fornitore</label>
                            <input type="text" name="comp_fornitore_${prod.id}" value="${prod.fornitore}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descrizione</label>
                        <textarea name="comp_descrizione_${prod.id}">${prod.descrizione}</textarea>
                    </div>
                `;
                container.appendChild(div);
            });
            
            document.getElementById('nuoviComponentiContainer').innerHTML = '';
            nuovoComponenteCounter = 0;
            
            document.getElementById('modalEditPadre').classList.add('active');
        }
        
        function closeEditPadreModal() {
            document.getElementById('modalEditPadre').classList.remove('active');
        }
        
        // AGGIUNGI NUOVO COMPONENTE IN MODALE
        function addNuovoComponente() {
            const container = document.getElementById('nuoviComponentiContainer');
            const index = nuovoComponenteCounter;
            
            const div = document.createElement('div');
            div.className = 'figlio-item';
            div.style.marginTop = '20px';
            div.innerHTML = `
                <input type="hidden" name="nuovi_componenti[]" value="${index}">
                <div class="figlio-header-row">
                    <span class="figlio-numero" style="color: #4CAF50;">Nuovo Componente #${index + 1}</span>
                    <button type="button" class="btn-remove-figlio" onclick="this.closest('.figlio-item').remove()">×</button>
                </div>
                <div class="figlio-grid">
                    <div class="form-group">
                        <label>Nome Prodotto *</label>
                        <input type="text" name="nuovo_nome_${index}" required>
                    </div>
                    <div class="form-group">
                        <label>Quantità *</label>
                        <input type="number" name="nuovo_quantita_${index}" value="0" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Allarme *</label>
                        <input type="number" name="nuovo_minimo_${index}" value="0" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Fornitore</label>
                        <input type="text" name="nuovo_fornitore_${index}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Descrizione</label>
                    <textarea name="nuovo_descrizione_${index}"></textarea>
                </div>
            `;
            
            container.appendChild(div);
            nuovoComponenteCounter++;
            div.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // CONFERMA ELIMINAZIONE PADRE
        function confirmDeletePadre(padreName, numComponenti) {
            if (confirm(`⚠️ ATTENZIONE!\n\nStai per eliminare il gruppo "${padreName}" e tutti i suoi ${numComponenti} componenti.\n\nQuesta azione è IRREVERSIBILE!\n\nVuoi continuare?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const padreInput = document.createElement('input');
                padreInput.type = 'hidden';
                padreInput.name = 'padre_nome';
                padreInput.value = padreName;
                form.appendChild(padreInput);
                
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'elimina_padre';
                deleteInput.value = '1';
                form.appendChild(deleteInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // CHIUDI MODAL CON ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeEditPadreModal();
            }
        });
        
        // CHIUDI MODAL CLICCANDO FUORI
        document.getElementById('modalEditPadre').addEventListener('click', (e) => {
            if (e.target.id === 'modalEditPadre') {
                closeEditPadreModal();
            }
        });
        
        // ANIMAZIONE FADEOUT
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; transform: scale(1); }
                to { opacity: 0; transform: scale(0.8); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>