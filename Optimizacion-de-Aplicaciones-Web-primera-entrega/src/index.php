<?php
include 'db.php';

$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'pub_date';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT * FROM news WHERE title LIKE '%$search_query%' ORDER BY $order_by DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lector RSS</title>
    <link rel="stylesheet" href="css/style.css">
    
</head>
<body>
    <h1>Lector de Feeds RSS</h1>

    <form method="GET">
        <input type="text" name="search" placeholder="Buscar noticias..." value="<?= htmlspecialchars($search_query) ?>">
        <button type="submit">Buscar</button>
    </form>

    <table border="1">
        <thead>
            <tr>
                <th><a href="?order_by=pub_date">Fecha</a></th>
                <th><a href="?order_by=title">Título</a></th>
                <th><a href="?order_by=category">Categoría</a></th>
                <th>Descripción</th>
                <th>Enlace</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()) : ?>
                <tr>
                    <td><?= $row['pub_date'] ?></td>
                    <td><?= $row['title'] ?></td>
                    <td><?= $row['category'] ?></td>
                    <td><?= substr($row['description'], 0, 100) ?>...</td>
                    <td><a href="<?= $row['link'] ?>" target="_blank">Leer más</a></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <a href="add_feed.php">Agregar Feed</a> | 
    <a href="update_feeds.php">Actualizar Noticias</a> |
    <a href="index.php">Volver</a> 

    <!-- Al final del body -->
<footer>
    <a href="add_feed.php">Agregar Feed</a> | 
    <a href="update_feeds.php">Actualizar Noticias</a> |
    <a href="index.php">Volver</a> 
</footer>

</body>
</html>
