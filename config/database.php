<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'cebac';
const DB_USER = 'root';
const DB_PASS = '';

function conexion_bd(): PDO
{
    static $conexion;
    if (!$conexion instanceof PDO) {
        $server = new PDO(
            'mysql:host=' . DB_HOST . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $conexion = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        // Migración no destructiva de tablas y columnas legacy. Solo renombra cuando
        // el destino aún no existe; las filas, claves e IDs permanecen intactos.
        $existeTabla = static function (PDO $conexion, string $tabla): bool {
            $consulta = $conexion->prepare(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :tabla'
            );
            $consulta->execute(['tabla' => $tabla]);
            return (int) $consulta->fetchColumn() > 0;
        };
        $obtenerColumna = static function (PDO $conexion, string $tabla, string $columna): ?array {
            $consulta = $conexion->prepare("SHOW COLUMNS FROM `$tabla` LIKE :columna");
            $consulta->execute(['columna' => $columna]);
            $definicion = $consulta->fetch();
            return is_array($definicion) ? $definicion : null;
        };
        $existeColumna = static function (PDO $conexion, string $tabla, string $columna) use ($obtenerColumna): bool {
            return $obtenerColumna($conexion, $tabla, $columna) !== null;
        };
        $existeIndice = static function (PDO $conexion, string $tabla, string $indice): bool {
            $consulta = $conexion->prepare("SHOW INDEX FROM `$tabla` WHERE Key_name = :indice");
            $consulta->execute(['indice' => $indice]);
            return (bool) $consulta->fetch();
        };
        $tablasLegacy = [
            'users' => 'usuarios', 'social_works' => 'obras_sociales',
            'patients' => 'pacientes', 'practice_prices' => 'precios_practicas',
            'studies' => 'estudios', 'social_work_studies' => 'obras_sociales_estudios',
            'orders' => 'ordenes', 'order_studies' => 'ordenes_estudios',
            'payments' => 'pagos', 'samples' => 'muestras', 'results' => 'resultados',
            'study_parameters' => 'parametros_estudios',
            'permisos' => 'privilegios', 'roles_permisos' => 'roles_privilegios',
            'privilegios' => 'privilegios', 'roles_privilegios' => 'roles_privilegios',
        ];
        foreach ($tablasLegacy as $origen => $destino) {
            if ($origen !== $destino && $existeTabla($conexion, $origen) && !$existeTabla($conexion, $destino)) {
                $conexion->exec("RENAME TABLE `$origen` TO `$destino`");
            }
        }
        $renombrarColumnas = [
            'usuarios' => [
                'username' => 'nombre_usuario', 'full_name' => 'nombre_completo',
                'password_hash' => 'hash_contrasena', 'role' => 'rol',
                'active' => 'activo', 'created_at' => 'creado_en',
            ],
            'roles' => ['name' => 'nombre', 'description' => 'descripcion', 'active' => 'activo', 'created_at' => 'creado_en'],
            'privilegios' => ['code' => 'codigo', 'name' => 'nombre', 'description' => 'descripcion', 'created_at' => 'creado_en'],
            'roles_privilegios' => ['role_id' => 'rol_id', 'privilege_id' => 'privilegio_id'],
            'usuarios_roles' => ['user_id' => 'usuario_id', 'role_id' => 'rol_id'],
            'obras_sociales' => ['name' => 'nombre', 'tax_id' => 'identificador_fiscal', 'agreement_conditions' => 'condiciones_convenio', 'active' => 'activo', 'created_at' => 'creado_en'],
            'pacientes' => ['code' => 'codigo', 'last_name' => 'apellido', 'first_name' => 'nombre', 'birth_date' => 'fecha_nacimiento', 'address' => 'direccion', 'email' => 'correo', 'phone' => 'telefono', 'social_work_id' => 'obra_social_id', 'credential_number' => 'numero_afiliado', 'created_at' => 'creado_en', 'updated_at' => 'actualizado_en'],
            'precios_practicas' => ['practice_code' => 'codigo_practica', 'description' => 'descripcion', 'price' => 'precio', 'active' => 'activo', 'created_at' => 'creado_en', 'updated_at' => 'actualizado_en'],
            'estudios' => ['code' => 'codigo', 'name' => 'nombre', 'parameters' => 'parametros', 'practice_price_id' => 'precio_practica_id', 'instructions' => 'instrucciones', 'active' => 'activo', 'created_at' => 'creado_en', 'updated_at' => 'actualizado_en'],
            'obras_sociales_estudios' => ['social_work_id' => 'obra_social_id', 'study_id' => 'estudio_id', 'created_at' => 'creado_en'],
            'parametros_estudios' => ['study_id' => 'estudio_id', 'section_name' => 'nombre_seccion', 'name' => 'nombre', 'minimum' => 'minimo', 'maximum' => 'maximo', 'reference_text' => 'texto_referencia', 'description' => 'descripcion', 'range_min' => 'rango_min', 'range_max' => 'rango_max', 'sort_order' => 'orden', 'created_at' => 'creado_en'],
            'ordenes' => ['code' => 'codigo', 'patient_id' => 'paciente_id', 'social_work_id' => 'obra_social_id', 'order_date' => 'fecha_orden', 'doctor' => 'medico', 'status' => 'estado', 'payment_status' => 'estado_pago', 'total_due' => 'total_adeudado', 'paid_amount' => 'monto_pagado', 'created_at' => 'creado_en', 'updated_at' => 'actualizado_en'],
            'ordenes_estudios' => ['order_id' => 'orden_id', 'study_id' => 'estudio_id', 'practice_price_id' => 'precio_practica_id', 'price' => 'precio', 'status' => 'estado', 'payment_status' => 'estado_pago'],
            'pagos' => ['order_study_id' => 'orden_estudio_id', 'order_id' => 'orden_id', 'amount' => 'monto', 'method' => 'metodo', 'paid_at' => 'pagado_en', 'status' => 'estado', 'created_at' => 'creado_en'],
            'muestras' => ['code' => 'codigo', 'order_study_id' => 'orden_estudio_id', 'collected_at' => 'recolectado_en', 'status' => 'estado', 'rejection_reason' => 'motivo_rechazo', 'created_at' => 'creado_en', 'updated_at' => 'actualizado_en'],
            'resultados' => ['order_study_id' => 'orden_estudio_id', 'result_text' => 'texto_resultado', 'status' => 'estado', 'delivered_at' => 'entregado_en', 'created_at' => 'creado_en', 'updated_at' => 'actualizado_en'],
        ];
        foreach ($renombrarColumnas as $tabla => $columnas) {
            if (!$existeTabla($conexion, $tabla)) {
                continue;
            }
            foreach ($columnas as $origen => $destino) {
                $origenExiste = $conexion->prepare("SHOW COLUMNS FROM `$tabla` LIKE :columna");
                $origenExiste->execute(['columna' => $origen]);
                $destinoExiste = $conexion->prepare("SHOW COLUMNS FROM `$tabla` LIKE :columna");
                $destinoExiste->execute(['columna' => $destino]);
                $definicion = $origenExiste->fetch();
                if ($definicion && !$destinoExiste->fetch()) {
                    $tipo = $definicion['Type'];
                    $nulable = $definicion['Null'] === 'YES' ? ' NULL' : ' NOT NULL';
                    $valorPredeterminado = (string) $definicion['Default'];
                    $predeterminado = $definicion['Default'] === null
                        ? ''
                        : ' DEFAULT ' . (
                            str_starts_with(strtoupper($valorPredeterminado), 'CURRENT_TIMESTAMP')
                                ? 'CURRENT_TIMESTAMP'
                                : $conexion->quote($valorPredeterminado)
                        );
                    $extra = $definicion['Extra'] !== '' ? ' ' . $definicion['Extra'] : '';
                    $conexion->exec("ALTER TABLE `$tabla` CHANGE `$origen` `$destino` $tipo$nulable$predeterminado$extra");
                }
            }
        }
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS usuarios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre_usuario VARCHAR(50) NOT NULL UNIQUE,
                nombre_completo VARCHAR(120) NOT NULL,
                hash_contrasena VARCHAR(255) NOT NULL,
                rol ENUM('admin','operador') NOT NULL DEFAULT 'operador',
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS roles (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(50) NOT NULL UNIQUE,
                descripcion VARCHAR(150) NOT NULL DEFAULT '',
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS privilegios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(80) NOT NULL UNIQUE,
                nombre VARCHAR(100) NOT NULL,
                descripcion VARCHAR(180) NOT NULL DEFAULT '',
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS roles_privilegios (
                rol_id INT UNSIGNED NOT NULL,
                privilegio_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (rol_id, privilegio_id),
                CONSTRAINT fk_roles_privilegios_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
                CONSTRAINT fk_roles_privilegios_privilegio FOREIGN KEY (privilegio_id) REFERENCES privilegios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS usuarios_roles (
                usuario_id INT UNSIGNED NOT NULL,
                rol_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (usuario_id, rol_id),
                CONSTRAINT fk_usuarios_roles_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                CONSTRAINT fk_usuarios_roles_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $asegurarColumnaId = static function (string $tabla, string $columnaLegacy = 'codigo') use ($conexion, $existeColumna, $existeIndice): void {
            if (!$existeColumna($conexion, $tabla, 'id')) {
                $conexion->exec("ALTER TABLE `$tabla` ADD COLUMN id INT UNSIGNED NULL FIRST");
            }
            if ($columnaLegacy !== '' && $existeColumna($conexion, $tabla, $columnaLegacy)) {
                $conexion->exec(
                    "UPDATE `$tabla`
                     SET id = CAST(`$columnaLegacy` AS UNSIGNED)
                     WHERE id IS NULL AND `$columnaLegacy` REGEXP '^[0-9]+$'"
                );
            }
            $maxId = (int) $conexion->query("SELECT COALESCE(MAX(id), 0) FROM `$tabla`")->fetchColumn();
            $conexion->exec("SET @next_id := $maxId");
            $conexion->exec("UPDATE `$tabla` SET id = (@next_id := @next_id + 1) WHERE id IS NULL");
            if (!$existeIndice($conexion, $tabla, 'uq_' . $tabla . '_id')) {
                $conexion->exec("ALTER TABLE `$tabla` ADD UNIQUE KEY uq_{$tabla}_id (id)");
            }
            $conexion->exec("ALTER TABLE `$tabla` MODIFY id INT UNSIGNED NOT NULL");
            try {
                $conexion->exec("ALTER TABLE `$tabla` MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT");
            } catch (PDOException) {
                // En esquemas legacy la PK puede estar en otra columna; mantener id único y no nulo es suficiente.
            }
        };
        $asegurarColumnaId('usuarios');
        $asegurarColumnaId('roles');
        $asegurarColumnaId('privilegios');
        if (!$existeColumna($conexion, 'roles_privilegios', 'rol_id')) {
            $conexion->exec('ALTER TABLE roles_privilegios ADD COLUMN rol_id INT UNSIGNED NULL');
        }
        if (!$existeColumna($conexion, 'roles_privilegios', 'privilegio_id')) {
            $conexion->exec('ALTER TABLE roles_privilegios ADD COLUMN privilegio_id INT UNSIGNED NULL');
        }
        if ($existeColumna($conexion, 'roles_privilegios', 'rol_codigo') && $existeColumna($conexion, 'roles', 'codigo')) {
            $conexion->exec(
                'UPDATE roles_privilegios rp
                 JOIN roles r ON r.codigo = rp.rol_codigo
                 SET rp.rol_id = r.id
                 WHERE rp.rol_id IS NULL'
            );
        }
        if ($existeColumna($conexion, 'roles_privilegios', 'privilegio_codigo')) {
            if ($existeColumna($conexion, 'privilegios', 'codigo_privilegio')) {
                $conexion->exec(
                    'UPDATE roles_privilegios rp
                     JOIN privilegios p ON p.codigo_privilegio = rp.privilegio_codigo
                     SET rp.privilegio_id = p.id
                     WHERE rp.privilegio_id IS NULL'
                );
            }
            if ($existeColumna($conexion, 'privilegios', 'codigo')) {
                $conexion->exec(
                    'UPDATE roles_privilegios rp
                     JOIN privilegios p ON p.codigo = rp.privilegio_codigo
                     SET rp.privilegio_id = p.id
                     WHERE rp.privilegio_id IS NULL'
                );
            }
        }
        if (!$existeIndice($conexion, 'roles_privilegios', 'uq_roles_privilegios_ids')) {
            try {
                $conexion->exec('ALTER TABLE roles_privilegios ADD UNIQUE KEY uq_roles_privilegios_ids (rol_id, privilegio_id)');
            } catch (PDOException) {
                if (!$existeIndice($conexion, 'roles_privilegios', 'idx_roles_privilegios_ids')) {
                    $conexion->exec('ALTER TABLE roles_privilegios ADD INDEX idx_roles_privilegios_ids (rol_id, privilegio_id)');
                }
            }
        }
        if (!$existeColumna($conexion, 'usuarios_roles', 'usuario_id')) {
            $conexion->exec('ALTER TABLE usuarios_roles ADD COLUMN usuario_id INT UNSIGNED NULL');
        }
        if (!$existeColumna($conexion, 'usuarios_roles', 'rol_id')) {
            $conexion->exec('ALTER TABLE usuarios_roles ADD COLUMN rol_id INT UNSIGNED NULL');
        }
        if ($existeColumna($conexion, 'usuarios_roles', 'usuario_codigo') && $existeColumna($conexion, 'usuarios', 'codigo')) {
            $conexion->exec(
                'UPDATE usuarios_roles ur
                 JOIN usuarios u ON u.codigo = ur.usuario_codigo
                 SET ur.usuario_id = u.id
                 WHERE ur.usuario_id IS NULL'
            );
        }
        if ($existeColumna($conexion, 'usuarios_roles', 'rol_codigo') && $existeColumna($conexion, 'roles', 'codigo')) {
            $conexion->exec(
                'UPDATE usuarios_roles ur
                 JOIN roles r ON r.codigo = ur.rol_codigo
                 SET ur.rol_id = r.id
                 WHERE ur.rol_id IS NULL'
            );
        }
        if (!$existeIndice($conexion, 'usuarios_roles', 'uq_usuarios_roles_ids')) {
            try {
                $conexion->exec('ALTER TABLE usuarios_roles ADD UNIQUE KEY uq_usuarios_roles_ids (usuario_id, rol_id)');
            } catch (PDOException) {
                if (!$existeIndice($conexion, 'usuarios_roles', 'idx_usuarios_roles_ids')) {
                    $conexion->exec('ALTER TABLE usuarios_roles ADD INDEX idx_usuarios_roles_ids (usuario_id, rol_id)');
                }
            }
        }
        $defCodigoPrivilegios = $obtenerColumna($conexion, 'privilegios', 'codigo');
        $usaCodigoPrivilegioLegacy = false;
        if ($defCodigoPrivilegios !== null && $existeColumna($conexion, 'privilegios', 'codigo_privilegio')) {
            $usaCodigoPrivilegioLegacy = (bool) preg_match('/\b(int|tinyint|smallint|mediumint|bigint)\b/i', (string) $defCodigoPrivilegios['Type']);
        }
        $columnaCodigoPrivilegio = $usaCodigoPrivilegioLegacy ? 'codigo_privilegio' : 'codigo';
        $roles = [
            ['Administrador', 'Acceso completo al sistema'],
            ['Operador', 'Acceso a la operación diaria del laboratorio'],
        ];
        $insertarRol = $conexion->prepare('INSERT IGNORE INTO roles (nombre, descripcion) VALUES (:nombre, :descripcion)');
        foreach ($roles as $rol) {
            $insertarRol->execute(['nombre' => $rol[0], 'descripcion' => $rol[1]]);
        }
        $privilegios = [
            ['panel.ver', 'Ver panel principal', 'Acceso al panel principal'],
            ['pacientes.gestionar', 'Gestionar pacientes', 'Consultar, crear y editar pacientes'],
            ['ordenes.gestionar', 'Gestionar órdenes médicas', 'Crear y consultar órdenes médicas'],
            ['caja.gestionar', 'Gestionar caja', 'Consultar saldos y registrar pagos'],
            ['muestras.gestionar', 'Gestionar muestras', 'Validar y finalizar muestras'],
            ['resultados.gestionar', 'Gestionar resultados', 'Cargar y entregar resultados'],
            ['obras_sociales.gestionar', 'Gestionar obras sociales', 'Administrar obras sociales y convenios'],
            ['estudios.gestionar', 'Gestionar estudios', 'Administrar estudios'],
            ['parametros.gestionar', 'Gestionar parámetros', 'Administrar parámetros de estudios'],
            ['practicas.gestionar', 'Gestionar prácticas', 'Administrar precios y prácticas'],
            ['usuarios.gestionar', 'Gestionar usuarios', 'Administrar usuarios, roles y accesos'],
        ];
        $insertarPrivilegio = $conexion->prepare(
            "INSERT IGNORE INTO privilegios ($columnaCodigoPrivilegio, nombre, descripcion)
             VALUES (:codigo, :nombre, :descripcion)"
        );
        foreach ($privilegios as $privilegio) {
            $insertarPrivilegio->execute(['codigo' => $privilegio[0], 'nombre' => $privilegio[1], 'descripcion' => $privilegio[2]]);
        }
        $idRolAdministrador = (int) $conexion->query("SELECT id FROM roles WHERE nombre = 'Administrador'")->fetchColumn();
        $idRolOperador = (int) $conexion->query("SELECT id FROM roles WHERE nombre = 'Operador'")->fetchColumn();
        $todosPrivilegios = $conexion->query('SELECT id FROM privilegios')->fetchAll(PDO::FETCH_COLUMN);
        $insertarRolPrivilegio = $conexion->prepare(
            'INSERT INTO roles_privilegios (rol_id, privilegio_id)
             SELECT :rol_id, :privilegio_id
             FROM DUAL
             WHERE NOT EXISTS (
                 SELECT 1 FROM roles_privilegios
                 WHERE rol_id = :rol_id_existente AND privilegio_id = :privilegio_id_existente
             )'
        );
        foreach ($todosPrivilegios as $privilegioId) {
            $insertarRolPrivilegio->execute([
                'rol_id' => $idRolAdministrador,
                'privilegio_id' => (int) $privilegioId,
                'rol_id_existente' => $idRolAdministrador,
                'privilegio_id_existente' => (int) $privilegioId,
            ]);
        }
        $codigosPermisosOperador = ['panel.ver', 'pacientes.gestionar', 'ordenes.gestionar', 'caja.gestionar', 'muestras.gestionar', 'resultados.gestionar'];
        $privilegioOperador = $conexion->prepare(
            "INSERT INTO roles_privilegios (rol_id, privilegio_id)
             SELECT :rol_id, p.id
             FROM privilegios p
             WHERE p.$columnaCodigoPrivilegio = :codigo
               AND NOT EXISTS (
                   SELECT 1 FROM roles_privilegios rp
                   WHERE rp.rol_id = :rol_id_existente AND rp.privilegio_id = p.id
               )"
        );
        foreach ($codigosPermisosOperador as $codigo) {
            $privilegioOperador->execute([
                'rol_id' => $idRolOperador,
                'rol_id_existente' => $idRolOperador,
                'codigo' => $codigo,
            ]);
        }
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS obras_sociales (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(120) NOT NULL,
                identificador_fiscal VARCHAR(20) NOT NULL UNIQUE,
                condiciones_convenio TEXT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS pacientes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(20) NOT NULL UNIQUE,
                dni VARCHAR(20) NOT NULL UNIQUE,
                apellido VARCHAR(80) NOT NULL,
                nombre VARCHAR(80) NOT NULL,
                fecha_nacimiento DATE NULL,
                direccion VARCHAR(180) NULL,
                correo VARCHAR(120) NULL,
                telefono VARCHAR(30) NULL,
                obra_social_id INT UNSIGNED NULL,
                numero_afiliado VARCHAR(50) NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_patient_social_work FOREIGN KEY (obra_social_id) REFERENCES obras_sociales(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
        // Los códigos existentes se conservan; no se renumeran durante la migración.

        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS precios_practicas (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo_practica VARCHAR(30) NOT NULL UNIQUE,
                descripcion VARCHAR(150) NOT NULL DEFAULT '',
                precio DECIMAL(12,2) NOT NULL DEFAULT 0,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );
        if (!$conexion->query("SHOW COLUMNS FROM precios_practicas LIKE 'descripcion'")->fetch()) {
            $conexion->exec("ALTER TABLE precios_practicas ADD COLUMN descripcion VARCHAR(150) NOT NULL DEFAULT '' AFTER codigo_practica");
        }
        // Los códigos existentes se conservan; no se renumeran durante la migración.

        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS estudios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(30) NOT NULL UNIQUE,
                nombre VARCHAR(150) NOT NULL,
                parametros TEXT NULL,
                precio_practica_id INT UNSIGNED NULL,
                instrucciones TEXT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_study_practice_price FOREIGN KEY (precio_practica_id) REFERENCES precios_practicas(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS obras_sociales_estudios (
                obra_social_id INT UNSIGNED NOT NULL,
                estudio_id INT UNSIGNED NOT NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (obra_social_id, estudio_id),
                CONSTRAINT fk_sws_social_work FOREIGN KEY (obra_social_id) REFERENCES obras_sociales(id) ON DELETE CASCADE,
                CONSTRAINT fk_sws_study FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS parametros_estudios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                estudio_id INT UNSIGNED NOT NULL,
                nombre_seccion VARCHAR(120) NULL,
                nombre VARCHAR(120) NOT NULL,
                minimo VARCHAR(50) NULL,
                maximo VARCHAR(50) NULL,
                texto_referencia VARCHAR(255) NULL,
                descripcion VARCHAR(255) NULL,
                rango_min VARCHAR(50) NULL,
                rango_max VARCHAR(50) NULL,
                orden INT UNSIGNED NOT NULL DEFAULT 0,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_parameter_study FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        foreach ([
            "nombre_seccion VARCHAR(120) NULL AFTER estudio_id",
            "texto_referencia VARCHAR(255) NULL AFTER maximo",
            "rango_min VARCHAR(50) NULL AFTER descripcion",
            "rango_max VARCHAR(50) NULL AFTER rango_min"
        ] as $parameterColumn) {
            $columnName = explode(' ', trim($parameterColumn))[0];
            if (!$conexion->query("SHOW COLUMNS FROM parametros_estudios LIKE '$columnName'")->fetch()) {
                $conexion->exec("ALTER TABLE parametros_estudios ADD COLUMN $parameterColumn");
            }
        }
        $estudioColumns = $conexion->query("SHOW COLUMNS FROM estudios LIKE 'precio_practica_id'")->fetch();
        if (!$estudioColumns) {
            $conexion->exec('ALTER TABLE estudios ADD COLUMN precio_practica_id INT UNSIGNED NULL AFTER parametros');
            $conexion->exec('ALTER TABLE estudios ADD CONSTRAINT fk_study_practice_price FOREIGN KEY (precio_practica_id) REFERENCES precios_practicas(id) ON DELETE SET NULL');
        }
        $oldPracticeColumn = $conexion->query("SHOW COLUMNS FROM estudios LIKE 'codigo_practica'")->fetch();
        if ($oldPracticeColumn) {
            $conexion->exec("INSERT IGNORE INTO precios_practicas (codigo_practica, precio) SELECT codigo_practica, precio FROM estudios WHERE codigo_practica IS NOT NULL AND codigo_practica <> ''");
            $conexion->exec('UPDATE estudios s INNER JOIN precios_practicas p ON p.codigo_practica = s.codigo_practica SET s.precio_practica_id = p.id WHERE s.precio_practica_id IS NULL');
            $oldPracticeIndex = $conexion->query("SHOW INDEX FROM estudios WHERE Key_name = 'codigo_practica'")->fetch();
            if ($oldPracticeIndex) {
                $conexion->exec('ALTER TABLE estudios DROP INDEX codigo_practica');
            }
            $conexion->exec('ALTER TABLE estudios DROP COLUMN codigo_practica, DROP COLUMN precio');
        }
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS ordenes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(30) NOT NULL UNIQUE,
                paciente_id INT UNSIGNED NOT NULL,
                obra_social_id INT UNSIGNED NULL,
                fecha_orden DATE NOT NULL,
                medico VARCHAR(120) NOT NULL,
                estado ENUM('Pendiente','Validada','Finalizada') NOT NULL DEFAULT 'Pendiente',
                estado_pago ENUM('No requiere','Pendiente','Parcial','Pagado','Pagar al retirar') NOT NULL DEFAULT 'No requiere',
                total_adeudado DECIMAL(12,2) NOT NULL DEFAULT 0,
                monto_pagado DECIMAL(12,2) NOT NULL DEFAULT 0,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_order_patient FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE RESTRICT,
                CONSTRAINT fk_order_social_work FOREIGN KEY (obra_social_id) REFERENCES obras_sociales(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
        // Los códigos existentes se conservan; no se renumeran durante la migración.

        $ordenSocialWorkColumn = $conexion->query("SHOW COLUMNS FROM ordenes LIKE 'obra_social_id'")->fetch();
        if (!$ordenSocialWorkColumn) {
            $conexion->exec('ALTER TABLE ordenes ADD COLUMN obra_social_id INT UNSIGNED NULL AFTER paciente_id');
            $conexion->exec('ALTER TABLE ordenes ADD CONSTRAINT fk_order_social_work FOREIGN KEY (obra_social_id) REFERENCES obras_sociales(id) ON DELETE SET NULL');
        }
        foreach ([
            "estado_pago ENUM('No requiere','Pendiente','Parcial','Pagado','Pagar al retirar') NOT NULL DEFAULT 'No requiere' AFTER estado",
            "total_adeudado DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER estado_pago",
            "monto_pagado DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_adeudado"
        ] as $ordenColumnDefinition) {
            $columnName = explode(' ', trim($ordenColumnDefinition))[0];
            if (!$conexion->query("SHOW COLUMNS FROM ordenes LIKE '$columnName'")->fetch()) {
                $conexion->exec("ALTER TABLE ordenes ADD COLUMN $ordenColumnDefinition");
            }
        }
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS ordenes_estudios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                orden_id INT UNSIGNED NOT NULL,
                estudio_id INT UNSIGNED NOT NULL,
                precio_practica_id INT UNSIGNED NULL,
                precio DECIMAL(12,2) NOT NULL DEFAULT 0,
                estado ENUM('Pendiente','Validada','Finalizada') NOT NULL DEFAULT 'Pendiente',
                estado_pago ENUM('No requiere','Pendiente','Pagado','Pagar al retirar') NOT NULL DEFAULT 'No requiere',
                UNIQUE KEY uq_order_study (orden_id, estudio_id),
                CONSTRAINT fk_order_study_order FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE,
                CONSTRAINT fk_order_study_study FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE RESTRICT,
                CONSTRAINT fk_order_study_practice FOREIGN KEY (precio_practica_id) REFERENCES precios_practicas(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
        $paymentStatusColumn = $conexion->query("SHOW COLUMNS FROM ordenes_estudios LIKE 'estado_pago'")->fetch();
        if (!$paymentStatusColumn) {
            $conexion->exec("ALTER TABLE ordenes_estudios ADD COLUMN estado_pago ENUM('No requiere','Pendiente','Pagado','Pagar al retirar') NOT NULL DEFAULT 'No requiere' AFTER estado");
        }
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS pagos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                orden_estudio_id INT UNSIGNED NULL,
                orden_id INT UNSIGNED NULL,
                amount DECIMAL(12,2) NOT NULL,
                metodo ENUM('Efectivo','Tarjeta','Transferencia','Pagar al retirar') NOT NULL,
                pagado_en DATETIME NULL,
                estado ENUM('Pendiente','Pagado','Pagar al retirar') NOT NULL DEFAULT 'Pendiente',
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_payment_order_study FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        if (!$conexion->query("SHOW COLUMNS FROM pagos LIKE 'orden_id'")->fetch()) {
            $conexion->exec('ALTER TABLE pagos ADD COLUMN orden_id INT UNSIGNED NULL AFTER orden_estudio_id');
            $conexion->exec('ALTER TABLE pagos DROP FOREIGN KEY fk_payment_order_study');
            $conexion->exec('ALTER TABLE pagos DROP INDEX orden_estudio_id');
            $conexion->exec('ALTER TABLE pagos ADD INDEX idx_payment_order_study (orden_estudio_id)');
            $conexion->exec('ALTER TABLE pagos ADD CONSTRAINT fk_payment_order_study FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id) ON DELETE CASCADE');
            $conexion->exec('ALTER TABLE pagos ADD CONSTRAINT fk_payment_order FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE');
        }
        $oldPaymentIndex = $conexion->query("SHOW INDEX FROM pagos WHERE Key_name = 'orden_estudio_id' AND Non_unique = 0")->fetch();
        if ($oldPaymentIndex) {
            $conexion->exec('ALTER TABLE pagos DROP FOREIGN KEY fk_payment_order_study');
            $conexion->exec('ALTER TABLE pagos DROP INDEX orden_estudio_id');
            $conexion->exec('ALTER TABLE pagos ADD INDEX idx_payment_order_study (orden_estudio_id)');
            $conexion->exec('ALTER TABLE pagos ADD CONSTRAINT fk_payment_order_study FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id) ON DELETE CASCADE');
        }
        $conexion->exec('ALTER TABLE pagos MODIFY orden_estudio_id INT UNSIGNED NULL');
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS muestras (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(30) NOT NULL UNIQUE,
                orden_estudio_id INT UNSIGNED NOT NULL UNIQUE,
                recolectado_en DATETIME NULL,
                estado ENUM('Pendiente','Validada','Finalizada','Rechazada') NOT NULL DEFAULT 'Pendiente',
                motivo_rechazo VARCHAR(255) NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_sample_order_study FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        // Los códigos existentes se conservan; no se renumeran durante la migración.

        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS resultados (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                orden_estudio_id INT UNSIGNED NOT NULL UNIQUE,
                texto_resultado TEXT NULL,
                estado ENUM('Pendiente','Cargado','Entregado') NOT NULL DEFAULT 'Pendiente',
                entregado_en DATETIME NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_result_order_study FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $consulta = $conexion->prepare(
            "INSERT INTO usuarios (nombre_usuario, nombre_completo, hash_contrasena, rol)
             VALUES (:nombre_usuario, :nombre_completo, :hash_contrasena, 'admin')
             ON DUPLICATE KEY UPDATE nombre_usuario = nombre_usuario"
        );
        $consulta->execute([
            'nombre_usuario' => 'admin',
            'nombre_completo' => 'Administrador del sistema',
            'hash_contrasena' => password_hash('contrasena', PASSWORD_DEFAULT),
        ]);
        $asignarRolAdmin = $conexion->prepare(
            'INSERT INTO usuarios_roles (usuario_id, rol_id)
             SELECT u.id, :rol_id
             FROM usuarios u
             WHERE u.nombre_usuario = :nombre_usuario
               AND NOT EXISTS (
                   SELECT 1 FROM usuarios_roles ur
                   WHERE ur.usuario_id = u.id AND ur.rol_id = :rol_id_existente
               )'
        );
        $asignarRolAdmin->execute([
            'rol_id' => $idRolAdministrador,
            'rol_id_existente' => $idRolAdministrador,
            'nombre_usuario' => 'admin',
        ]);
        $asignarRolOperador = $conexion->prepare(
            'INSERT INTO usuarios_roles (usuario_id, rol_id)
             SELECT u.id, r.id
             FROM usuarios u
             JOIN roles r ON r.nombre = :nombre_rol
             WHERE u.rol = :rol_usuario
               AND NOT EXISTS (
                   SELECT 1 FROM usuarios_roles ur
                   WHERE ur.usuario_id = u.id AND ur.rol_id = r.id
               )'
        );
        $asignarRolOperador->execute(['nombre_rol' => 'Operador', 'rol_usuario' => 'operador']);
    }
    return $conexion;
}





