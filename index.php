<?php
$titulo = "Hotel Carlos";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <header>
        <h1><?php echo $titulo; ?></h1>
        <p>Bienvenido a nuestro hotel</p>
    </header>

    <main>
        <section>
            <h2>Proyecto en construcción</h2>
            <p>Sitio web del Hotel Carlos.</p>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Hotel Carlos</p>
    </footer>
</body>
</html>
