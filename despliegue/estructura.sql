
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `ausencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ausencias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empleado_id` int(10) unsigned NOT NULL,
  `tipo` enum('vacaciones','incapacidad','permiso','licencia','falta') NOT NULL DEFAULT 'permiso',
  `desde` date NOT NULL,
  `hasta` date NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `estado` enum('solicitada','aprobada','rechazada') NOT NULL DEFAULT 'solicitada',
  `aprobada_por` int(10) unsigned DEFAULT NULL,
  `aprobada_en` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empleado_id` (`empleado_id`),
  CONSTRAINT `ausencias_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bloqueos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bloqueos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unidad_id` int(10) unsigned NOT NULL,
  `conexion_id` int(10) unsigned DEFAULT NULL,
  `canal` varchar(30) NOT NULL DEFAULT 'booking',
  `uid` varchar(200) DEFAULT NULL,
  `resumen` varchar(200) DEFAULT NULL,
  `fecha_entrada` date NOT NULL,
  `fecha_salida` date NOT NULL,
  `origen` enum('ical','manual') NOT NULL DEFAULT 'ical',
  `notas` varchar(300) DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bloqueos_conexion_id_foreign` (`conexion_id`),
  KEY `unidad_id_fecha_entrada_fecha_salida` (`unidad_id`,`fecha_entrada`,`fecha_salida`),
  CONSTRAINT `bloqueos_conexion_id_foreign` FOREIGN KEY (`conexion_id`) REFERENCES `canal_conexiones` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `bloqueos_unidad_id_foreign` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bono_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bono_movimientos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bono_id` int(10) unsigned NOT NULL,
  `tipo` enum('emision','consumo','devolucion','anulacion') NOT NULL DEFAULT 'consumo',
  `valor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saldo_despues` decimal(12,2) NOT NULL DEFAULT 0.00,
  `concepto` varchar(150) DEFAULT NULL,
  `reserva_id` int(10) unsigned DEFAULT NULL,
  `comanda_id` int(10) unsigned DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bono_movimientos_bono_id_foreign` (`bono_id`),
  KEY `bono_movimientos_reserva_id_foreign` (`reserva_id`),
  KEY `bono_movimientos_comanda_id_foreign` (`comanda_id`),
  CONSTRAINT `bono_movimientos_bono_id_foreign` FOREIGN KEY (`bono_id`) REFERENCES `bonos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bono_movimientos_comanda_id_foreign` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `bono_movimientos_reserva_id_foreign` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bonos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bonos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) NOT NULL,
  `importe_inicial` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saldo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `comprador_nombre` varchar(150) DEFAULT NULL,
  `comprador_email` varchar(150) DEFAULT NULL,
  `comprador_telefono` varchar(30) DEFAULT NULL,
  `beneficiario` varchar(150) DEFAULT NULL,
  `mensaje` varchar(300) DEFAULT NULL,
  `caduca` date DEFAULT NULL,
  `forma_pago` enum('efectivo','tarjeta','transferencia','wompi','otro') NOT NULL DEFAULT 'efectivo',
  `estado` enum('activo','anulado') NOT NULL DEFAULT 'activo',
  `notas` varchar(300) DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `caja_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `caja_movimientos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `turno_id` int(10) unsigned NOT NULL,
  `tipo` enum('ingreso','egreso') NOT NULL,
  `concepto` varchar(150) NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `folio_movimiento_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `turno_id` (`turno_id`),
  CONSTRAINT `caja_movimientos_turno_id_foreign` FOREIGN KEY (`turno_id`) REFERENCES `caja_turnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `caja_turnos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `caja_turnos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `base_inicial` decimal(12,2) NOT NULL DEFAULT 0.00,
  `apertura` datetime NOT NULL,
  `cierre` datetime DEFAULT NULL,
  `efectivo_contado` decimal(12,2) DEFAULT NULL,
  `diferencia` decimal(12,2) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `canal_conexiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `canal_conexiones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unidad_id` int(10) unsigned NOT NULL,
  `canal` varchar(30) NOT NULL DEFAULT 'booking',
  `nombre` varchar(120) DEFAULT NULL,
  `url_importar` varchar(500) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `ultima_sync` datetime DEFAULT NULL,
  `ultimo_error` varchar(300) DEFAULT NULL,
  `eventos` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unidad_id_canal` (`unidad_id`,`canal`),
  CONSTRAINT `canal_conexiones_unidad_id_foreign` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `carta_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `carta_categorias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `color` varchar(20) DEFAULT '#4f8a68',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `carta_productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `carta_productos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `categoria_id` int(10) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `precio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `destino` enum('cocina','barra','directo') DEFAULT 'cocina',
  `apto_vegano` tinyint(1) DEFAULT 0,
  `apto_vegetariano` tinyint(1) DEFAULT 0,
  `sin_gluten` tinyint(1) DEFAULT 0,
  `sin_lactosa` tinyint(1) DEFAULT 0,
  `picante` tinyint(1) DEFAULT 0,
  `alergenos` varchar(255) DEFAULT NULL,
  `divisible` tinyint(1) DEFAULT 0,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `carta_productos_categoria_id_foreign` (`categoria_id`),
  CONSTRAINT `carta_productos_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `carta_categorias` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comanda_linea_modificadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comanda_linea_modificadores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `linea_id` int(10) unsigned NOT NULL,
  `modificador_id` int(10) unsigned DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio_extra` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comanda_linea_modificadores_modificador_id_foreign` (`modificador_id`),
  KEY `linea_id` (`linea_id`),
  CONSTRAINT `comanda_linea_modificadores_linea_id_foreign` FOREIGN KEY (`linea_id`) REFERENCES `comanda_lineas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `comanda_linea_modificadores_modificador_id_foreign` FOREIGN KEY (`modificador_id`) REFERENCES `modificadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comanda_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comanda_lineas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `comanda_id` int(10) unsigned NOT NULL,
  `producto_id` int(10) unsigned DEFAULT NULL,
  `nombre_producto` varchar(120) NOT NULL,
  `composicion` varchar(255) DEFAULT NULL,
  `destino` enum('cocina','barra','directo') DEFAULT 'cocina',
  `precio_unitario` decimal(12,2) NOT NULL,
  `cantidad` int(10) unsigned NOT NULL DEFAULT 1,
  `enviado_cocina` tinyint(1) DEFAULT 0,
  `recibido` tinyint(1) DEFAULT 0,
  `recibido_en` datetime DEFAULT NULL,
  `entregado` tinyint(1) NOT NULL DEFAULT 0,
  `servido` tinyint(1) DEFAULT 0,
  `listo_en` datetime DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL,
  `empleado_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comanda_lineas_producto_id_foreign` (`producto_id`),
  KEY `comanda_id` (`comanda_id`),
  KEY `comanda_lineas_empleado_id_foreign` (`empleado_id`),
  CONSTRAINT `comanda_lineas_comanda_id_foreign` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `comanda_lineas_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE SET NULL,
  CONSTRAINT `comanda_lineas_producto_id_foreign` FOREIGN KEY (`producto_id`) REFERENCES `carta_productos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comanda_pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comanda_pagos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `comanda_id` int(10) unsigned NOT NULL,
  `forma_pago` enum('efectivo','tarjeta','transferencia','wompi','habitacion','bono') NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `recibido` decimal(12,2) DEFAULT NULL,
  `cambio` decimal(12,2) DEFAULT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `empleado_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comanda_id` (`comanda_id`),
  KEY `comanda_pagos_empleado_id_foreign` (`empleado_id`),
  CONSTRAINT `comanda_pagos_comanda_id_foreign` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `comanda_pagos_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comandas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comandas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL,
  `mesa` varchar(60) DEFAULT NULL,
  `mesa_id` int(10) unsigned DEFAULT NULL,
  `comensales` int(10) unsigned DEFAULT 1,
  `reserva_id` int(10) unsigned DEFAULT NULL,
  `cliente_nombre` varchar(150) DEFAULT NULL,
  `cliente_documento` varchar(50) DEFAULT NULL,
  `cliente_telefono` varchar(30) DEFAULT NULL,
  `estado` enum('abierta','cobrada','anulada') NOT NULL DEFAULT 'abierta',
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(12,2) DEFAULT 0.00,
  `cupon_id` int(10) unsigned DEFAULT NULL,
  `motivo_descuento` varchar(150) DEFAULT NULL,
  `propina` decimal(12,2) DEFAULT 0.00,
  `liquidacion_id` int(10) unsigned DEFAULT NULL,
  `forma_pago` enum('efectivo','tarjeta','transferencia','wompi','habitacion','bono') DEFAULT NULL,
  `recibido` decimal(12,2) DEFAULT NULL,
  `cambio` decimal(12,2) DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `empleado_id` int(10) unsigned DEFAULT NULL,
  `autorizo_id` int(10) unsigned DEFAULT NULL,
  `cerrada_en` datetime DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `comandas_reserva_id_foreign` (`reserva_id`),
  KEY `comandas_empleado_id_foreign` (`empleado_id`),
  CONSTRAINT `comandas_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE SET NULL,
  CONSTRAINT `comandas_reserva_id_foreign` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comandero_borradores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comandero_borradores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empleado_id` int(10) unsigned NOT NULL,
  `clave` varchar(60) NOT NULL,
  `mesa_id` int(10) unsigned DEFAULT NULL,
  `comanda_id` int(10) unsigned DEFAULT NULL,
  `destino` varchar(120) DEFAULT NULL,
  `resumen` text DEFAULT NULL,
  `unidades` smallint(5) unsigned NOT NULL DEFAULT 0,
  `importe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empleado_id_clave` (`empleado_id`,`clave`),
  KEY `mesa_id` (`mesa_id`),
  CONSTRAINT `comandero_borradores_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comandero_envios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comandero_envios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(40) NOT NULL,
  `comanda_id` int(10) unsigned NOT NULL,
  `empleado_id` int(10) unsigned DEFAULT NULL,
  `lineas` smallint(5) unsigned NOT NULL DEFAULT 0,
  `a_cocina` tinyint(1) NOT NULL DEFAULT 0,
  `demora_seg` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `comandero_envios_empleado_id_foreign` (`empleado_id`),
  KEY `comanda_id` (`comanda_id`),
  CONSTRAINT `comandero_envios_comanda_id_foreign` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comandero_envios_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(80) NOT NULL,
  `valor` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `correos_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `correos_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) NOT NULL,
  `destinatario` varchar(200) NOT NULL,
  `asunto` varchar(255) NOT NULL,
  `estado` enum('enviado','fallido','sin_configurar') NOT NULL DEFAULT 'enviado',
  `error` text DEFAULT NULL,
  `reserva_id` int(10) unsigned DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reserva_id` (`reserva_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cupon_usos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cupon_usos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cupon_id` int(10) unsigned NOT NULL,
  `reserva_id` int(10) unsigned DEFAULT NULL,
  `comanda_id` int(10) unsigned DEFAULT NULL,
  `huesped_id` int(10) unsigned DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `base` decimal(12,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(12,2) NOT NULL DEFAULT 0.00,
  `canal` enum('web','recepcion','tpv') NOT NULL DEFAULT 'recepcion',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cupon_usos_cupon_id_foreign` (`cupon_id`),
  KEY `cupon_usos_reserva_id_foreign` (`reserva_id`),
  KEY `cupon_usos_comanda_id_foreign` (`comanda_id`),
  CONSTRAINT `cupon_usos_comanda_id_foreign` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `cupon_usos_cupon_id_foreign` FOREIGN KEY (`cupon_id`) REFERENCES `cupones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `cupon_usos_reserva_id_foreign` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cupones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cupones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `tipo` enum('porcentaje','valor') NOT NULL DEFAULT 'porcentaje',
  `valor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ambito` enum('alojamiento','restaurante','todo') NOT NULL DEFAULT 'alojamiento',
  `desde` date DEFAULT NULL,
  `hasta` date DEFAULT NULL,
  `importe_minimo` decimal(12,2) DEFAULT NULL,
  `descuento_maximo` decimal(12,2) DEFAULT NULL,
  `limite_usos` int(10) unsigned DEFAULT NULL,
  `limite_por_huesped` int(10) unsigned DEFAULT NULL,
  `usos` int(10) unsigned NOT NULL DEFAULT 0,
  `en_web` tinyint(1) NOT NULL DEFAULT 1,
  `en_recepcion` tinyint(1) NOT NULL DEFAULT 1,
  `en_tpv` tinyint(1) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empleado_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empleado_documentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empleado_id` int(10) unsigned NOT NULL,
  `tipo` varchar(40) NOT NULL DEFAULT 'otro',
  `archivo` varchar(255) NOT NULL,
  `nombre_original` varchar(255) DEFAULT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `tamano` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empleado_id` (`empleado_id`),
  CONSTRAINT `empleado_documentos_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empleados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empleados` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(150) NOT NULL,
  `tipo_documento` varchar(20) NOT NULL DEFAULT 'CC',
  `num_documento` varchar(50) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `ciudad` varchar(120) DEFAULT NULL,
  `cargo` varchar(100) NOT NULL,
  `area` enum('recepcion','limpieza','cocina','mantenimiento','administracion','otro') NOT NULL DEFAULT 'otro',
  `tipo_contrato` enum('indefinido','fijo','obra','prestacion','aprendiz') NOT NULL DEFAULT 'indefinido',
  `fecha_ingreso` date DEFAULT NULL,
  `fecha_salida` date DEFAULT NULL,
  `salario` decimal(12,2) NOT NULL DEFAULT 0.00,
  `jornada` varchar(60) DEFAULT NULL,
  `eps` varchar(120) DEFAULT NULL,
  `arl` varchar(120) DEFAULT NULL,
  `fondo_pension` varchar(120) DEFAULT NULL,
  `caja_compensacion` varchar(120) DEFAULT NULL,
  `banco` varchar(120) DEFAULT NULL,
  `cuenta_bancaria` varchar(60) DEFAULT NULL,
  `emergencia_nombre` varchar(150) DEFAULT NULL,
  `emergencia_telefono` varchar(30) DEFAULT NULL,
  `emergencia_parentesco` varchar(60) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `pin_hash` varchar(255) DEFAULT NULL,
  `pin_actualizado` datetime DEFAULT NULL,
  `ficha_movil` tinyint(1) DEFAULT 1,
  `tarjeta_uid` varchar(64) DEFAULT NULL,
  `rol_tpv` enum('ninguno','camarero','encargado') DEFAULT 'ninguno',
  `foto` varchar(200) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipo_documento_num_documento` (`tipo_documento`,`num_documento`),
  UNIQUE KEY `empleados_tarjeta` (`tarjeta_uid`),
  KEY `empleados_usuario_id_foreign` (`usuario_id`),
  CONSTRAINT `empleados_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `experiencia_reservas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `experiencia_reservas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `experiencia_id` int(10) unsigned NOT NULL,
  `reserva_id` int(10) unsigned DEFAULT NULL,
  `huesped_id` int(10) unsigned DEFAULT NULL,
  `cliente_nombre` varchar(150) DEFAULT NULL,
  `cliente_telefono` varchar(30) DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time DEFAULT NULL,
  `adultos` int(10) unsigned NOT NULL DEFAULT 1,
  `ninos` int(10) unsigned NOT NULL DEFAULT 0,
  `precio_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  `precio_nino` decimal(12,2) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('solicitada','confirmada','realizada','cancelada','no_show') NOT NULL DEFAULT 'solicitada',
  `empleado_id` int(10) unsigned DEFAULT NULL,
  `folio_movimiento_id` int(10) unsigned DEFAULT NULL,
  `cobrado_aparte` tinyint(1) NOT NULL DEFAULT 0,
  `notas` varchar(300) DEFAULT NULL,
  `motivo` varchar(200) DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `experiencia_reservas_experiencia_id_foreign` (`experiencia_id`),
  KEY `experiencia_reservas_reserva_id_foreign` (`reserva_id`),
  KEY `experiencia_reservas_huesped_id_foreign` (`huesped_id`),
  KEY `fecha_hora` (`fecha`,`hora`),
  CONSTRAINT `experiencia_reservas_experiencia_id_foreign` FOREIGN KEY (`experiencia_id`) REFERENCES `experiencias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `experiencia_reservas_huesped_id_foreign` FOREIGN KEY (`huesped_id`) REFERENCES `huespedes` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `experiencia_reservas_reserva_id_foreign` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `experiencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `experiencias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `incluye` varchar(300) DEFAULT NULL,
  `no_incluye` varchar(300) DEFAULT NULL,
  `categoria` varchar(40) NOT NULL DEFAULT 'Naturaleza',
  `tipo_precio` enum('persona','grupo') NOT NULL DEFAULT 'persona',
  `precio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `precio_nino` decimal(12,2) DEFAULT NULL,
  `coste` decimal(12,2) NOT NULL DEFAULT 0.00,
  `duracion_min` int(10) unsigned NOT NULL DEFAULT 60,
  `capacidad` int(10) unsigned NOT NULL DEFAULT 8,
  `minimo` int(10) unsigned NOT NULL DEFAULT 1,
  `edad_minima` int(10) unsigned DEFAULT NULL,
  `horarios` varchar(120) DEFAULT NULL,
  `dias` varchar(20) NOT NULL DEFAULT '1,2,3,4,5,6,7',
  `aviso_horas` int(10) unsigned NOT NULL DEFAULT 0,
  `punto_encuentro` varchar(150) DEFAULT NULL,
  `proveedor` varchar(120) DEFAULT NULL,
  `notas_internas` varchar(300) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `publicada` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `factura_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factura_lineas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `factura_id` int(10) unsigned NOT NULL,
  `codigo` varchar(60) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL DEFAULT 1.000,
  `precio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `factura_id` (`factura_id`),
  CONSTRAINT `factura_lineas_factura_id_foreign` FOREIGN KEY (`factura_id`) REFERENCES `facturas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `facturas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `origen` enum('reserva','comanda','manual') NOT NULL DEFAULT 'reserva',
  `reserva_id` int(10) unsigned DEFAULT NULL,
  `comanda_id` int(10) unsigned DEFAULT NULL,
  `cliente_nombre` varchar(200) NOT NULL,
  `cliente_documento` varchar(50) NOT NULL,
  `cliente_email` varchar(150) DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `impuestos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','emitida','error','anulada') NOT NULL DEFAULT 'pendiente',
  `siigo_id` varchar(64) DEFAULT NULL,
  `numero` varchar(60) DEFAULT NULL,
  `cufe` varchar(200) DEFAULT NULL,
  `url_publica` varchar(500) DEFAULT NULL,
  `respuesta` text DEFAULT NULL,
  `error` text DEFAULT NULL,
  `emitida_en` datetime DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reserva_id` (`reserva_id`),
  KEY `comanda_id` (`comanda_id`),
  CONSTRAINT `facturas_comanda_id_foreign` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `facturas_reserva_id_foreign` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fichajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fichajes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empleado_id` int(10) unsigned NOT NULL,
  `tipo` enum('entrada','salida','pausa_inicio','pausa_fin') NOT NULL DEFAULT 'entrada',
  `marcado_en` datetime NOT NULL,
  `origen` enum('terminal','movil','manual') NOT NULL DEFAULT 'terminal',
  `foto` varchar(200) DEFAULT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `precision_m` int(10) unsigned DEFAULT NULL,
  `distancia_m` int(10) unsigned DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `observacion` varchar(200) DEFAULT NULL,
  `editado_por` int(10) unsigned DEFAULT NULL,
  `editado_en` datetime DEFAULT NULL,
  `anulado` tinyint(1) NOT NULL DEFAULT 0,
  `motivo` varchar(200) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empleado_id_marcado_en` (`empleado_id`,`marcado_en`),
  CONSTRAINT `fichajes_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `folio_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `folio_movimientos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reserva_id` int(10) unsigned NOT NULL,
  `tipo` enum('cargo','pago','descuento') NOT NULL,
  `concepto` varchar(150) NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `metodo` enum('efectivo','tarjeta','transferencia','wompi','bono','otro') DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reserva_id` (`reserva_id`),
  CONSTRAINT `folio_movimientos_reserva_id_foreign` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `huespedes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `huespedes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(150) NOT NULL,
  `tipo_documento` enum('CC','CE','PASAPORTE','TI','OTRO') NOT NULL DEFAULT 'CC',
  `num_documento` varchar(50) NOT NULL,
  `nacionalidad` varchar(80) NOT NULL DEFAULT 'Colombia',
  `telefono` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipo_documento_num_documento` (`tipo_documento`,`num_documento`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `insumos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `insumos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `unidad` varchar(20) NOT NULL DEFAULT 'g',
  `es_preparacion` tinyint(1) DEFAULT 0,
  `rendimiento` decimal(12,3) DEFAULT NULL,
  `costo_unitario` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `proveedor` varchar(120) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventario_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventario_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `grupo` varchar(40) NOT NULL DEFAULT 'Dormitorio',
  `valor_reposicion` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cantidad_estandar` int(10) unsigned NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventario_revision_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventario_revision_lineas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revision_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `esperada` int(10) unsigned NOT NULL DEFAULT 0,
  `encontrada` int(10) unsigned NOT NULL DEFAULT 0,
  `estado` enum('ok','falta','danado') NOT NULL DEFAULT 'ok',
  `observacion` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventario_revision_lineas_revision_id_foreign` (`revision_id`),
  KEY `inventario_revision_lineas_item_id_foreign` (`item_id`),
  CONSTRAINT `inventario_revision_lineas_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventario_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `inventario_revision_lineas_revision_id_foreign` FOREIGN KEY (`revision_id`) REFERENCES `inventario_revisiones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventario_revisiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventario_revisiones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unidad_id` int(10) unsigned NOT NULL,
  `reserva_id` int(10) unsigned DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `estado` enum('ok','incidencias') NOT NULL DEFAULT 'ok',
  `faltantes` int(10) unsigned NOT NULL DEFAULT 0,
  `danados` int(10) unsigned NOT NULL DEFAULT 0,
  `notas` varchar(300) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventario_revisiones_unidad_id_foreign` (`unidad_id`),
  KEY `inventario_revisiones_reserva_id_foreign` (`reserva_id`),
  CONSTRAINT `inventario_revisiones_reserva_id_foreign` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `inventario_revisiones_unidad_id_foreign` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `limpiezas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `limpiezas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unidad_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `inicio` datetime NOT NULL,
  `fin` datetime DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unidad_id` (`unidad_id`),
  CONSTRAINT `limpiezas_unidad_id_foreign` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mantenimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mantenimientos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unidad_id` int(10) unsigned DEFAULT NULL,
  `ubicacion` varchar(120) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `prioridad` enum('baja','media','alta','urgente') NOT NULL DEFAULT 'media',
  `estado` enum('abierta','en_proceso','resuelta') NOT NULL DEFAULT 'abierta',
  `reporto_id` int(10) unsigned NOT NULL,
  `resolvio_id` int(10) unsigned DEFAULT NULL,
  `resuelta_en` datetime DEFAULT NULL,
  `solucion` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unidad_id` (`unidad_id`),
  CONSTRAINT `mantenimientos_unidad_id_foreign` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo_unidad_id` int(10) unsigned DEFAULT NULL,
  `unidad_id` int(10) unsigned DEFAULT NULL,
  `experiencia_id` int(10) unsigned DEFAULT NULL,
  `publico` tinyint(1) DEFAULT 1,
  `tipo` enum('foto','video') NOT NULL DEFAULT 'foto',
  `archivo` varchar(200) DEFAULT NULL,
  `miniatura` varchar(200) DEFAULT NULL,
  `url` varchar(300) DEFAULT NULL,
  `titulo` varchar(150) DEFAULT NULL,
  `alt` varchar(200) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `portada` tinyint(1) NOT NULL DEFAULT 0,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tipo_unidad_id_orden` (`tipo_unidad_id`,`orden`),
  KEY `unidad_id_orden` (`unidad_id`,`orden`),
  KEY `medios_experiencia_id_foreign` (`experiencia_id`),
  CONSTRAINT `medios_experiencia_id_foreign` FOREIGN KEY (`experiencia_id`) REFERENCES `experiencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medios_tipo_unidad_id_foreign` FOREIGN KEY (`tipo_unidad_id`) REFERENCES `tipos_unidad` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `medios_unidad_id_foreign` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mesas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mesas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `zona` varchar(60) NOT NULL DEFAULT 'Salón',
  `capacidad` int(10) unsigned NOT NULL DEFAULT 4,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modificador_grupos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modificador_grupos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('unico','multiple') NOT NULL DEFAULT 'multiple',
  `obligatorio` tinyint(1) NOT NULL DEFAULT 0,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modificadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modificadores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `grupo_id` int(10) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio_extra` decimal(12,2) NOT NULL DEFAULT 0.00,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `modificadores_grupo_id_foreign` (`grupo_id`),
  CONSTRAINT `modificadores_grupo_id_foreign` FOREIGN KEY (`grupo_id`) REFERENCES `modificador_grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `preparacion_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `preparacion_lineas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `preparacion_id` int(10) unsigned NOT NULL,
  `insumo_id` int(10) unsigned NOT NULL,
  `cantidad` decimal(12,3) NOT NULL DEFAULT 0.000,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `preparacion_lineas_insumo_id_foreign` (`insumo_id`),
  KEY `preparacion_id` (`preparacion_id`),
  CONSTRAINT `preparacion_lineas_insumo_id_foreign` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `preparacion_lineas_preparacion_id_foreign` FOREIGN KEY (`preparacion_id`) REFERENCES `insumos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `producto_modificador_grupos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `producto_modificador_grupos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `producto_id` int(10) unsigned NOT NULL,
  `grupo_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `producto_id_grupo_id` (`producto_id`,`grupo_id`),
  KEY `producto_modificador_grupos_grupo_id_foreign` (`grupo_id`),
  CONSTRAINT `producto_modificador_grupos_grupo_id_foreign` FOREIGN KEY (`grupo_id`) REFERENCES `modificador_grupos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `producto_modificador_grupos_producto_id_foreign` FOREIGN KEY (`producto_id`) REFERENCES `carta_productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `propina_liquidacion_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `propina_liquidacion_lineas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `liquidacion_id` int(10) unsigned NOT NULL,
  `empleado_id` int(10) unsigned NOT NULL,
  `generado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `comandas` int(10) unsigned NOT NULL DEFAULT 0,
  `importe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `entregado` tinyint(1) NOT NULL DEFAULT 0,
  `entregado_en` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `liquidacion_id_empleado_id` (`liquidacion_id`,`empleado_id`),
  KEY `propina_liquidacion_lineas_empleado_id_foreign` (`empleado_id`),
  CONSTRAINT `propina_liquidacion_lineas_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `propina_liquidacion_lineas_liquidacion_id_foreign` FOREIGN KEY (`liquidacion_id`) REFERENCES `propina_liquidaciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `propina_liquidaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `propina_liquidaciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `desde` date NOT NULL,
  `hasta` date NOT NULL,
  `criterio` enum('ventas','partes_iguales','manual') NOT NULL DEFAULT 'ventas',
  `recaudado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `repartido` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('borrador','cerrada') NOT NULL DEFAULT 'borrador',
  `notas` varchar(300) DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `cerrada_en` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `desde_hasta` (`desde`,`hasta`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `receta_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receta_lineas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `producto_id` int(10) unsigned NOT NULL,
  `insumo_id` int(10) unsigned NOT NULL,
  `cantidad` decimal(12,3) NOT NULL DEFAULT 0.000,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `receta_lineas_insumo_id_foreign` (`insumo_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `receta_lineas_insumo_id_foreign` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `receta_lineas_producto_id_foreign` FOREIGN KEY (`producto_id`) REFERENCES `carta_productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `registro_acompanantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registro_acompanantes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `registro_id` int(10) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(150) NOT NULL,
  `tipo_documento` varchar(20) NOT NULL DEFAULT 'CC',
  `num_documento` varchar(50) DEFAULT NULL,
  `nacionalidad` varchar(80) NOT NULL DEFAULT 'Colombia',
  `fecha_nacimiento` date DEFAULT NULL,
  `es_menor` tinyint(1) NOT NULL DEFAULT 0,
  `parentesco` varchar(60) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `registro_id` (`registro_id`),
  CONSTRAINT `registro_acompanantes_registro_id_foreign` FOREIGN KEY (`registro_id`) REFERENCES `registros` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `registro_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registro_documentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `registro_id` int(10) unsigned NOT NULL,
  `acompanante_id` int(10) unsigned DEFAULT NULL,
  `tipo` varchar(40) NOT NULL DEFAULT 'documento',
  `archivo` varchar(255) NOT NULL,
  `nombre_original` varchar(255) DEFAULT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `tamano` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `registro_documentos_acompanante_id_foreign` (`acompanante_id`),
  KEY `registro_id` (`registro_id`),
  CONSTRAINT `registro_documentos_acompanante_id_foreign` FOREIGN KEY (`acompanante_id`) REFERENCES `registro_acompanantes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `registro_documentos_registro_id_foreign` FOREIGN KEY (`registro_id`) REFERENCES `registros` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `registros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registros` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reserva_id` int(10) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `estado` enum('pendiente','enviado','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `expira_en` datetime DEFAULT NULL,
  `motivo_viaje` varchar(60) DEFAULT NULL,
  `pais_residencia` varchar(80) DEFAULT NULL,
  `ciudad_residencia` varchar(120) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `ocupacion` varchar(120) DEFAULT NULL,
  `placa_vehiculo` varchar(20) DEFAULT NULL,
  `hora_llegada` varchar(10) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `acepta_datos` tinyint(1) NOT NULL DEFAULT 0,
  `acepta_reglamento` tinyint(1) NOT NULL DEFAULT 0,
  `acepta_escnna` tinyint(1) NOT NULL DEFAULT 0,
  `version_politica` varchar(20) DEFAULT NULL,
  `acepta_marketing` tinyint(1) NOT NULL DEFAULT 0,
  `firma_archivo` varchar(255) DEFAULT NULL,
  `firmado_en` datetime DEFAULT NULL,
  `firma_ip` varchar(45) DEFAULT NULL,
  `firma_dispositivo` varchar(255) DEFAULT NULL,
  `enviado_en` datetime DEFAULT NULL,
  `revisado_por` int(10) unsigned DEFAULT NULL,
  `revisado_en` datetime DEFAULT NULL,
  `motivo_rechazo` varchar(255) DEFAULT NULL,
  `hay_menores` tinyint(1) NOT NULL DEFAULT 0,
  `hay_extranjeros` tinyint(1) NOT NULL DEFAULT 0,
  `reportado_sire` tinyint(1) NOT NULL DEFAULT 0,
  `reportado_tra` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `reserva_id` (`reserva_id`),
  CONSTRAINT `registros_reserva_id_foreign` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reglas_precio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reglas_precio` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `tipo` enum('dia_semana','ocupacion','anticipacion','duracion') NOT NULL DEFAULT 'dia_semana',
  `dias` varchar(20) DEFAULT NULL,
  `valor_desde` int(11) DEFAULT NULL,
  `valor_hasta` int(11) DEFAULT NULL,
  `tipo_ajuste` enum('porcentaje','valor') NOT NULL DEFAULT 'porcentaje',
  `ajuste` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tipo_unidad_id` int(10) unsigned DEFAULT NULL,
  `prioridad` int(11) NOT NULL DEFAULT 0,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reglas_precio_tipo_unidad_id_foreign` (`tipo_unidad_id`),
  CONSTRAINT `reglas_precio_tipo_unidad_id_foreign` FOREIGN KEY (`tipo_unidad_id`) REFERENCES `tipos_unidad` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reservas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `huesped_id` int(10) unsigned NOT NULL,
  `unidad_id` int(10) unsigned NOT NULL,
  `fecha_entrada` date NOT NULL,
  `fecha_salida` date NOT NULL,
  `adultos` int(10) unsigned NOT NULL DEFAULT 1,
  `ninos` int(10) unsigned NOT NULL DEFAULT 0,
  `estado` enum('pendiente','confirmada','checkin','checkout','cancelada') NOT NULL DEFAULT 'pendiente',
  `canal` varchar(30) DEFAULT 'directa',
  `comision` decimal(12,2) DEFAULT 0.00,
  `referencia_externa` varchar(120) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `desglose_precio` text DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `reservas_huesped_id_foreign` (`huesped_id`),
  KEY `reservas_unidad_id_foreign` (`unidad_id`),
  CONSTRAINT `reservas_huesped_id_foreign` FOREIGN KEY (`huesped_id`) REFERENCES `huespedes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `reservas_unidad_id_foreign` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `servicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servicios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `grupo` varchar(40) NOT NULL DEFAULT 'Comodidades',
  `icono` varchar(40) NOT NULL DEFAULT 'bi-check-circle',
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tarifas_temporada`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tarifas_temporada` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo_unidad_id` int(10) unsigned NOT NULL,
  `temporada_id` int(10) unsigned NOT NULL,
  `precio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipo_unidad_id_temporada_id` (`tipo_unidad_id`,`temporada_id`),
  KEY `tarifas_temporada_temporada_id_foreign` (`temporada_id`),
  CONSTRAINT `tarifas_temporada_temporada_id_foreign` FOREIGN KEY (`temporada_id`) REFERENCES `temporadas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tarifas_temporada_tipo_unidad_id_foreign` FOREIGN KEY (`tipo_unidad_id`) REFERENCES `tipos_unidad` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `temporadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temporadas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `desde` date NOT NULL,
  `hasta` date NOT NULL,
  `tipo_ajuste` enum('porcentaje','valor','fijo') NOT NULL DEFAULT 'porcentaje',
  `ajuste` decimal(12,2) NOT NULL DEFAULT 0.00,
  `prioridad` int(11) NOT NULL DEFAULT 0,
  `color` varchar(20) NOT NULL DEFAULT '#b9873f',
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `origen` enum('manual','agente') DEFAULT 'manual',
  `clave` varchar(60) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `temporadas_clave` (`clave`),
  KEY `desde_hasta` (`desde`,`hasta`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tipo_servicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipo_servicios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo_unidad_id` int(10) unsigned NOT NULL,
  `servicio_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipo_unidad_id_servicio_id` (`tipo_unidad_id`,`servicio_id`),
  KEY `tipo_servicios_servicio_id_foreign` (`servicio_id`),
  CONSTRAINT `tipo_servicios_servicio_id_foreign` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tipo_servicios_tipo_unidad_id_foreign` FOREIGN KEY (`tipo_unidad_id`) REFERENCES `tipos_unidad` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tipos_unidad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_unidad` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `capacidad` int(10) unsigned NOT NULL DEFAULT 2,
  `tarifa_base` decimal(12,2) NOT NULL DEFAULT 0.00,
  `precio_minimo` decimal(12,2) DEFAULT NULL,
  `precio_maximo` decimal(12,2) DEFAULT NULL,
  `personas_incluidas` int(10) unsigned DEFAULT 2,
  `suplemento_adulto` decimal(12,2) DEFAULT 0.00,
  `suplemento_nino` decimal(12,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `traducciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `traducciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tabla` varchar(40) NOT NULL,
  `registro_id` int(10) unsigned NOT NULL,
  `campo` varchar(40) NOT NULL,
  `idioma` varchar(5) NOT NULL,
  `texto` text DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tabla_registro_id_campo_idioma` (`tabla`,`registro_id`,`campo`,`idioma`),
  KEY `tabla_idioma` (`tabla`,`idioma`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `turnos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `turnos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empleado_id` int(10) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `puesto` varchar(80) DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `turnos_empleado_id_foreign` (`empleado_id`),
  KEY `fecha_empleado_id` (`fecha`,`empleado_id`),
  CONSTRAINT `turnos_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unidad_inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `unidad_inventario` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unidad_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `cantidad` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unidad_id_item_id` (`unidad_id`,`item_id`),
  KEY `unidad_inventario_item_id_foreign` (`item_id`),
  CONSTRAINT `unidad_inventario_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventario_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `unidad_inventario_unidad_id_foreign` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unidad_servicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `unidad_servicios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unidad_id` int(10) unsigned NOT NULL,
  `servicio_id` int(10) unsigned NOT NULL,
  `estado` enum('si','no') NOT NULL DEFAULT 'si',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unidad_id_servicio_id` (`unidad_id`,`servicio_id`),
  KEY `unidad_servicios_servicio_id_foreign` (`servicio_id`),
  CONSTRAINT `unidad_servicios_servicio_id_foreign` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `unidad_servicios_unidad_id_foreign` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `unidades` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo_id` int(10) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(300) DEFAULT NULL,
  `ubicacion` varchar(120) DEFAULT NULL,
  `orden` int(11) DEFAULT 0,
  `token_ical` varchar(64) DEFAULT NULL,
  `estado` enum('disponible','ocupada','limpieza','bloqueada') NOT NULL DEFAULT 'disponible',
  `notas` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unidades_tipo_id_foreign` (`tipo_id`),
  CONSTRAINT `unidades_tipo_id_foreign` FOREIGN KEY (`tipo_id`) REFERENCES `tipos_unidad` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `clave_hash` varchar(255) NOT NULL,
  `rol` enum('gerencia','recepcion','limpieza') NOT NULL DEFAULT 'recepcion',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

