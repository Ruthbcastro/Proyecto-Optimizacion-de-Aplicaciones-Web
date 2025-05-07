<?php
include 'db.php';
require_once 'simplepie/autoloader.php';

// Configuración de error reporting
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);

// Función para limpiar la descripción
function cleanDescription($description) {
    // Eliminar todas las etiquetas HTML, manteniendo solo el texto
    $text = strip_tags($description);
    
    // Convertir entidades HTML a sus caracteres correspondientes
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Eliminar múltiples espacios, tabs y saltos de línea
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Eliminar caracteres especiales y emojis
    $text = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}]/u', '', $text);
    
    // Eliminar caracteres de control
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    
    // Recortar espacios al inicio y final
    $text = trim($text);
    
    // Limitar la longitud si es necesario (por ejemplo, a 1000 caracteres)
    if (strlen($text) > 1000) {
        $text = substr($text, 0, 997) . '...';
    }
    
    return $text;
}

// Obtener todos los feeds
$sql = "SELECT * FROM feeds";
$result = $conn->query($sql);

// Contador de feeds procesados
$processed = 0;
$errors = [];

while ($row = $result->fetch_assoc()) {
    try {
        // Crear nueva instancia de SimplePie para cada feed
        $feed = new SimplePie();
        $feed->set_feed_url($row['url']);
        $feed->enable_cache(false);
        $feed->set_timeout(30);
        $feed->force_feed(true);
        
        // Desactivar el ordenamiento por fecha para compatibilidad con SimplePie 1.5
        $feed->enable_order_by_date(false);
        
        $feed->init();
        
        // Verificar si hay error en el feed
        if ($feed->error()) {
            $errors[] = "Error en feed {$row['url']}: " . $feed->error();
            continue;
        }
        
        // Procesar items uno por uno
        $item_count = $feed->get_item_quantity();
        
        for ($i = 0; $i < $item_count; $i++) {
            try {
                $item = $feed->get_item($i);
                if (!$item) continue;
                
                // Obtener y validar título
                $title = $item->get_title();
                if (empty($title)) {
                    $title = "Sin título";
                }
                $title = $conn->real_escape_string($title);
                
                // Obtener y validar enlace
                $link = $item->get_link();
                if (empty($link)) {
                    continue; // Saltar items sin enlace
                }
                $link = $conn->real_escape_string($link);
                
                // Obtener y limpiar descripción
                $description = $item->get_description();
                if (empty($description)) {
                    $description = "Sin descripción";
                } else {
                    $description = cleanDescription($description);
                }
                $description = $conn->real_escape_string($description);
                
                
                $pub_date = date('Y-m-d H:i:s'); // Fecha por defecto
                
                // Intentar obtener la fecha del feed de varias maneras
                $date_methods = ['get_local_date', 'get_gmdate', 'get_date'];
                foreach ($date_methods as $method) {
                    if (method_exists($item, $method)) {
                        try {
                            $raw_date = @$item->$method();
                            if ($raw_date) {
                                // Intentar varios formatos comunes de fecha
                                foreach ([
                                    'D, d M Y H:i:s O',
                                    'Y-m-d H:i:s',
                                    'Y-m-d\TH:i:sP',
                                    'Y-m-d\TH:i:s.uP',
                                    'Y-m-d'
                                ] as $format) {
                                    $parsed_date = @DateTime::createFromFormat($format, $raw_date);
                                    if ($parsed_date) {
                                        $pub_date = $parsed_date->format('Y-m-d H:i:s');
                                        break 2;
                                    }
                                }
                                
                                // Si los formatos anteriores fallan, intentar con strtotime
                                $timestamp = @strtotime($raw_date);
                                if ($timestamp !== false) {
                                    $pub_date = date('Y-m-d H:i:s', $timestamp);
                                    break;
                                }
                            }
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                }
                
                // Manejar categorías
                $category = 'Sin categoría';
                $cats = $item->get_categories();
                if (!empty($cats)) {
                    $category_names = [];
                    foreach ($cats as $cat) {
                        if ($cat && method_exists($cat, 'get_label')) {
                            $label = trim($cat->get_label());
                            if (!empty($label)) {
                                $category_names[] = $conn->real_escape_string($label);
                            }
                        }
                    }
                    if (!empty($category_names)) {
                        $category = implode(', ', array_unique($category_names));
                    }
                }
                
                // Verificar si la noticia ya existe
                $check = $conn->prepare("SELECT id FROM news WHERE link = ?");
                $check->bind_param("s", $link);
                $check->execute();
                $check_result = $check->get_result();
                
                if ($check_result->num_rows == 0) {
                    $insert = $conn->prepare("INSERT INTO news (title, link, description, pub_date, category, feed_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $insert->bind_param("sssssi", $title, $link, $description, $pub_date, $category, $row['id']);
                    
                    if ($insert->execute()) {
                        $processed++;
                    }
                    $insert->close();
                }
                $check->close();
                
            } catch (Exception $e) {
                $errors[] = "Error procesando item del feed {$row['url']}: " . $e->getMessage();
                continue;
            }
        }
        
    } catch (Exception $e) {
        $errors[] = "Error general en feed {$row['url']}: " . $e->getMessage();
        continue;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Actualización de Feeds</title>
    <style>
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>Resultado de la actualización</h2>
    
    <?php if ($processed > 0): ?>
        <p class="success">Se procesaron exitosamente <?php echo $processed; ?> noticias nuevas.</p>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <h3>Errores encontrados:</h3>
        <ul class="error">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <p><a href="index.php">Volver al inicio</a></p>
</body>
</html>