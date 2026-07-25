# Hotel Carlos — Plataforma de gestión hotelera

Sistema de gestión (PMS) para un hotel rural en Colombia: reservas, recepción,
huéspedes, restaurante, limpieza y más, según la propuesta funcional incluida en
[docs/](docs/).

## Tecnología

- **Backend:** CodeIgniter 4 (PHP 8.2)
- **Base de datos:** MariaDB/MySQL (`hotelcarlos`)
- **Frontend:** Bootstrap 5

## Desarrollo local (XAMPP)

1. Arrancar Apache y MySQL en XAMPP.
2. Crear la base de datos (solo la primera vez):
   ```sql
   CREATE DATABASE hotelcarlos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Instalar dependencias y preparar la base de datos:
   ```bash
   composer install
   php spark migrate
   php spark db:seed DatosIniciales
   ```
4. Abrir http://localhost/hotelcarlos

La configuración local está en `.env` (no se sube al repositorio; usar `env` como plantilla).
