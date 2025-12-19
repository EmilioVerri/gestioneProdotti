<?php
session_start();

// Configurazione database
$host = 'localhost';
$dbname = 'gestioneprodotti';
$username = 'root'; // Modifica con il tuo username
$password = ''; // Modifica con la tua password

$errore = '';
$successo = '';

// Gestione del login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    if (!empty($user) && !empty($pass)) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("SELECT id, username, password, privilegi FROM login WHERE username = ?");
            $stmt->execute([$user]);
            $utente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($utente && password_verify($pass, $utente['password'])) {
                $_SESSION['user_id'] = $utente['id'];
                $_SESSION['username'] = $utente['username'];
                $_SESSION['privilegi'] = $utente['privilegi'];
                header('Location: dashboard.php');
                exit;
            } else {
                $errore = 'Username o password non validi';
            }
        } catch (PDOException $e) {
            $errore = 'Errore di connessione al database';
        }
    } else {
        $errore = 'Compila tutti i campi';
    }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestione Prodotti</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h2 {
            color: #1a1a1a;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #1a1a1a;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f9f9f9;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #1a1a1a;
            background: white;
            transform: translateY(-2px);
        }

        button {
            width: 100%;
            padding: 14px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        button:hover {
            background: #000;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        .messaggio {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
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

        .link-registrazione {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .link-registrazione a {
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .link-registrazione a:hover {
            color: #000;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h2>Login</h2>

        <?php if ($errore): ?>
            <div class="messaggio errore"><?php echo htmlspecialchars($errore); ?></div>
        <?php endif; ?>

        <?php if ($successo): ?>
            <div class="messaggio successo"><?php echo htmlspecialchars($successo); ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" name="login">Accedi</button>
        </form>


    </div>

    <script>
        // Animazione input al focus
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateX(5px)';
            });
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateX(0)';
            });
        });

        // Validazione form
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (username === '' || password === '') {
                e.preventDefault();
                alert('Compila tutti i campi!');
            }
        });
    </script>
</body>

</html>