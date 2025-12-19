<?php
session_start();

// Verifica se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit;
}

// Configurazione database
$host = 'localhost';
$dbname = 'gestioneprodotti';
$db_username = 'root';
$db_password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Errore di connessione']);
    exit;
}

$prodottoId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($prodottoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID prodotto non valido']);
    exit;
}

try {
    // Ottieni informazioni prodotto
    $stmt = $pdo->prepare("SELECT * FROM prodotti WHERE id = ?");
    $stmt->execute([$prodottoId]);
    $prodotto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prodotto) {
        echo json_encode(['success' => false, 'error' => 'Prodotto non trovato']);
        exit;
    }

    // Ottieni ultimi 5 movimenti
    $stmt = $pdo->prepare("SELECT * FROM storicomovimenti WHERE idProdotto = ? ORDER BY id DESC LIMIT 5");
    $stmt->execute([$prodotto['nome']]);
    $movimenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'prodotto' => $prodotto,
        'movimenti' => $movimenti
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Errore durante il recupero dei dati']);
}
