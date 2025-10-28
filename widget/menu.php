<?php
$host = 'localhost';
$dbname = 'gestioneprodotti';
$db_username = 'root';
$db_password = '';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);


$stmt = $pdo->prepare("SELECT privilegi FROM login WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$privilegi = $stmt->fetchColumn();


?>



<div class="sidebar-menu">
    
     <a href="dashboard.php" class="menu-item">
        <span class="menu-icon">🏠</span>
        <span class="menu-text">DashBoard</span>
    </a><?php
    if($privilegi=="admin"){?>
    <a href="pagina_controllo.php" class="menu-item">
        <span class="menu-icon">⚙️</span>
        <span class="menu-text">Pagina di Controllo</span>
    </a>
    <?php } ?>
    <a href="prodotti.php" class="menu-item">
        <span class="menu-icon">📦</span>
        <span class="menu-text">Modifica prodotti</span>
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