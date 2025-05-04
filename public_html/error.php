<?php
$errorCode = isset($_GET['code']) ? intval($_GET['code']) : 404;
$errorMessages = [
    404 => 'Nie znaleziono strony',
    500 => 'Błąd serwera'
];
$errorMessage = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : 'Wystąpił nieznany błąd';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Błąd <?php echo $errorCode; ?> - Moja strona z Notion</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .error-container {
            text-align: center;
            padding: 50px 0;
        }
        .error-code {
            font-size: 5em;
            color: #ddd;
            margin-bottom: 10px;
        }
        .error-message {
            font-size: 1.5em;
            color: #666;
            margin-bottom: 30px;
            background: none;
            border: none;
        }
        .back-link {
            display: inline-block;
            padding: 10px 20px;
            background-color: #0066cc;
            color: white;
            border-radius: 5px;
            text-decoration: none;
        }
        .back-link:hover {
            background-color: #0055aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <div class="error-code"><?php echo $errorCode; ?></div>
            <div class="error-message"><?php echo $errorMessage; ?></div>
            <a href="/" class="back-link">Powrót do strony głównej</a>
        </div>
    </div>
</body>
</html>
