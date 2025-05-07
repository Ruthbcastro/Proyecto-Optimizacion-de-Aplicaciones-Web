<?php
include 'db.php';
require_once 'simplepie/autoloader.php';

function validateFeed($url) {
    try {
        // Verificar si la URL es válida
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return "URL inválida";
        }

        // Verificar si SimplePie está disponible
        if (!class_exists('SimplePie')) {
            return "Error: La librería SimplePie no está disponible";
        }

        // Intentar obtener el contenido del feed
        $feed = new SimplePie();
        $feed->set_feed_url($url);
        $feed->enable_cache(false);
        $feed->set_timeout(30);
        $feed->force_feed(true);
        
        // Desactivar el ordenamiento por fecha para evitar error
        $feed->enable_order_by_date(false);
        
        $feed->init();

        // Verificar si hay error en el feed
        if ($feed->error()) {
            return "Error al leer el feed: " . $feed->error();
        }

        // Intentar obtener el primer item sin usar get_items()
        $first_item = $feed->get_item(0);
        if (!$first_item) {
            return "El feed parece estar vacío";
        }

        return true;
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $url = trim($_POST['url']);
    
    // Verificar si la URL termina en una de las extensiones válidas
    if (!preg_match('#\.(xml|rss)$#i', $url)) {
        $message = "La URL debe terminar en .xml o .rss";
        $messageType = 'error';
    } else {
        $validation = validateFeed($url);
        
        if ($validation === true) {
            // Verificar si el feed ya existe
            $checkSql = "SELECT id FROM feeds WHERE url = ?";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param("s", $url);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = "Este feed ya está registrado.";
                $messageType = 'warning';
            } else {
                $sql = "INSERT INTO feeds (url) VALUES (?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $url);
                
                if ($stmt->execute()) {
                    $message = "Feed agregado con éxito.";
                    $messageType = 'success';
                } else {
                    $message = "Error al agregar el feed: " . $conn->error;
                    $messageType = 'error';
                }
            }
        } else {
            $message = $validation;
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Feed</title>
    <style>
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
        .warning {
            background-color: #fcf8e3;
            color: #8a6d3b;
            border: 1px solid #faebcc;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .help-text {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        input[type="url"] {
            padding: 8px;
            width: 100%;
            max-width: 500px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h2>Agregar Nuevo Feed</h2>
    
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="url">URL del feed XML:</label><br>
            <input type="url" id="url" name="url" required 
                   placeholder="https://ejemplo.com/sitemap_index.xml"
                   pattern="https?://.+"
                   title="Debe ser una URL válida comenzando con http:// o https://">
            <div class="help-text">
                Formatos aceptados:
                <ul>
                    <li>sitemap_index.xml (ejemplo: https://www.xataka.com.mx/sitemap_index.xml)</li>
                    <li>index.xml</li>
                    <li>rss</li>
                </ul>
            </div>
        </div>
        <div class="form-group">
            <button type="submit">Agregar Feed</button>
        </div>
    </form>
    
    <div style="margin-top: 20px;">
        <h3>Feeds actuales:</h3>
        <?php
        $sql = "SELECT * FROM feeds ORDER BY id DESC";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            echo "<ul>";
            while ($row = $result->fetch_assoc()) {
                echo "<li>" . htmlspecialchars($row['url']) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No hay feeds registrados.</p>";
        }
        ?>
    </div>
    
    <p><a href="index.php">Volver al inicio</a></p>
</body>
</html>