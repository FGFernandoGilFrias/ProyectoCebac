<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'cebac';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo;
    if (!$pdo instanceof PDO) {
        $server = new PDO(
            'mysql:host=' . DB_HOST . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $legacyTables = [
            'users' => 'usuarios', 'social_works' => 'obras_sociales', 'pacientes' => 'pacientes',
            'practice_prices' => 'precios_practicas', 'studies' => 'estudios', 'social_work_studies' => 'obras_sociales_estudios',
            'orders' => 'ordenes', 'order_studies' => 'ordenes_estudios', 'payments' => 'pagos', 'muestras' => 'muestras',
            'results' => 'resultados', 'study_parameters' => 'parametros_estudios',
        ];
        foreach ($legacyTables as $legacyTable => $spanishTable) {
            $legacyExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name');
            $legacyExists->execute(['table_name' => $legacyTable]);
            $spanishExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name');
            $spanishExists->execute(['table_name' => $spanishTable]);
            if ((int) $legacyExists->fetchColumn() > 0 && (int) $spanishExists->fetchColumn() === 0) {
                $pdo->exec("RENAME TABLE `$legacyTable` TO `$spanishTable`");
            }
        }
        $legacyColumns = [
            'usuarios' => ['username'=>'nombre_usuario','full_name'=>'nombre_completo','password_hash'=>'hash_contrasena','role'=>'rol','active'=>'activo','created_at'=>'creado_en'],
            'roles' => ['name'=>'nombre','description'=>'descripcion','active'=>'activo','created_at'=>'creado_en'],
            'privilegios' => ['code'=>'codigo','name'=>'nombre','description'=>'descripcion','created_at'=>'creado_en'],
            'roles_privilegios' => ['role_id'=>'rol_id','privilege_id'=>'privilegio_id'], 'usuarios_roles' => ['user_id'=>'usuario_id','role_id'=>'rol_id'],
            'obras_sociales' => ['name'=>'nombre','tax_id'=>'identificacion_fiscal','agreement_conditions'=>'condiciones_convenio','active'=>'activo','created_at'=>'creado_en'],
            'pacientes' => ['code'=>'codigo','last_name'=>'apellido','first_name'=>'nombre','birth_date'=>'fecha_nacimiento','address'=>'direccion','email'=>'correo','phone'=>'telefono','social_work_id'=>'obra_social_id','credential_number'=>'numero_afiliado','created_at'=>'creado_en','updated_at'=>'actualizado_en'],
            'precios_practicas' => ['practice_code'=>'codigo_practica','description'=>'descripcion','price'=>'precio','active'=>'activo','created_at'=>'creado_en','updated_at'=>'actualizado_en'],
            'estudios' => ['code'=>'codigo','name'=>'nombre','parameters'=>'parametros','practice_price_id'=>'precio_practica_id','instructions'=>'instrucciones','active'=>'activo','created_at'=>'creado_en','updated_at'=>'actualizado_en'],
            'obras_sociales_estudios' => ['social_work_id'=>'obra_social_id','study_id'=>'estudio_id','created_at'=>'creado_en'],
            'parametros_estudios' => ['study_id'=>'estudio_id','section_name'=>'nombre_seccion','name'=>'nombre','minimum'=>'minimo','maximum'=>'maximo','reference_text'=>'texto_referencia','description'=>'descripcion','range_min'=>'rango_min','range_max'=>'rango_max','sort_order'=>'orden','created_at'=>'creado_en'],
            'ordenes' => ['code'=>'codigo','patient_id'=>'paciente_id','social_work_id'=>'obra_social_id','order_date'=>'fecha_orden','doctor'=>'medico','status'=>'estado','payment_status'=>'estado_pago','total_due'=>'total_adeudado','paid_amount'=>'monto_pagado','created_at'=>'creado_en','updated_at'=>'actualizado_en'],
            'ordenes_estudios' => ['order_id'=>'orden_id','study_id'=>'estudio_id','practice_price_id'=>'precio_practica_id','price'=>'precio','status'=>'estado','payment_status'=>'estado_pago'],
            'pagos' => ['order_study_id'=>'orden_estudio_id','order_id'=>'orden_id','amount'=>'importe','method'=>'metodo','paid_at'=>'pagado_en','status'=>'estado','created_at'=>'creado_en'],
            'muestras' => ['code'=>'codigo','order_study_id'=>'orden_estudio_id','collected_at'=>'recolectado_en','status'=>'estado','rejection_reason'=>'motivo_rechazo','created_at'=>'creado_en','updated_at'=>'actualizado_en'],
            'resultados' => ['order_study_id'=>'orden_estudio_id','result_text'=>'texto_resultado','status'=>'estado','delivered_at'=>'entregado_en','created_at'=>'creado_en','updated_at'=>'actualizado_en'],
        ];
        foreach ($legacyColumns as $table => $columns) {
            $tableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table");
            $tableCheck->execute(['table' => $table]);
            if ((int) $tableCheck->fetchColumn() === 0) {
                continue;
            }
            foreach ($columns as $legacyColumn => $spanishColumn) {
                $old = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
                $old->execute(['column' => $legacyColumn]); $oldDefinition = $old->fetch();
                $new = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column"); $new->execute(['column' => $spanishColumn]);
                if ($oldDefinition && !$new->fetch()) {
                    $definition = $oldDefinition['Type'] . ($oldDefinition['Null'] === 'NO' ? ' NOT NULL' : ' NULL');
                    if ($oldDefinition['Default'] !== null) {
                        $default = (string) $oldDefinition['Default'];
                        $definition .= preg_match('/^current_timestamp(?:\(\))?$/i', $default)
                            ? ' DEFAULT CURRENT_TIMESTAMP'
                            : ' DEFAULT ' . $pdo->quote($default);
                    }
                    if (stripos((string) $oldDefinition['Extra'], 'on update') !== false) {
                        $definition .= ' ON UPDATE CURRENT_TIMESTAMP';
                    }
                    $pdo->exec("ALTER TABLE `$table` CHANGE COLUMN `$legacyColumn` `$spanishColumn` $definition");
                }
            }
        }
        $pdo->exec(
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
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS roles (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(50) NOT NULL UNIQUE,
                descripcion VARCHAR(150) NOT NULL DEFAULT '',
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS privilegios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(80) NOT NULL UNIQUE,
                nombre VARCHAR(100) NOT NULL,
                descripcion VARCHAR(180) NOT NULL DEFAULT '',
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS roles_privilegios (
                rol_id INT UNSIGNED NOT NULL,
                privilegio_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (rol_id, privilegio_id),
                CONSTRAINT fk_role_privilege_role FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
                CONSTRAINT fk_role_privilege_privilege FOREIGN KEY (privilegio_id) REFERENCES privilegios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS usuarios_roles (
                usuario_id INT UNSIGNED NOT NULL,
                rol_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (usuario_id, rol_id),
                CONSTRAINT fk_user_role_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                CONSTRAINT fk_user_role_role FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $roles = [
            ['Administrador', 'Acceso completo al sistema'],
            ['Operador', 'Acceso a la operación diaria del laboratorio'],
        ];
        $insertarRol = $pdo->prepare('INSERT IGNORE INTO roles (nombre, descripcion) VALUES (:nombre, :descripcion)');
        foreach ($roles as $rol) {
            $insertarRol->execute(['nombre' => $rol[0], 'descripcion' => $rol[1]]);
        }
        $privilegios = [
            ['dashboard.view', 'Ver panel principal', 'Acceso al panel principal'],
            ['pacientes.manage', 'Gestionar pacientes', 'Consultar, crear y editar pacientes'],
            ['ordenes.manage', 'Gestionar órdenes médicas', 'Crear y consultar órdenes médicas'],
            ['cash.manage', 'Gestionar caja', 'Consultar saldos y registrar pagos'],
            ['muestras.manage', 'Gestionar muestras', 'Validar y finalizar muestras'],
            ['resultados.manage', 'Gestionar resultados', 'Cargar y entregar resultados'],
            ['obras_sociales.manage', 'Gestionar obras sociales', 'Administrar obras sociales y convenios'],
            ['estudios.manage', 'Gestionar estudios', 'Administrar estudios'],
            ['parametros.manage', 'Gestionar parámetros', 'Administrar parámetros de estudios'],
            ['practices.manage', 'Gestionar prácticas', 'Administrar precios y prácticas'],
            ['usuarios.manage', 'Gestionar usuarios', 'Administrar usuarios, roles y accesos'],
        ];
        $insertarPrivilegio = $pdo->prepare('INSERT IGNORE INTO privilegios (codigo, nombre, descripcion) VALUES (:codigo, :nombre, :descripcion)');
        foreach ($privilegios as $privilegio) {
            $insertarPrivilegio->execute(['codigo' => $privilegio[0], 'nombre' => $privilegio[1], 'descripcion' => $privilegio[2]]);
        }
        $rolAdministradorId = (int) $pdo->query("SELECT id FROM roles WHERE nombre = 'Administrador'")->fetchColumn();
        $rolOperadorId = (int) $pdo->query("SELECT id FROM roles WHERE nombre = 'Operador'")->fetchColumn();
        $todosLosPrivilegios = $pdo->query('SELECT id FROM privilegios')->fetchAll(PDO::FETCH_COLUMN);
        $insertarRolPrivilegio = $pdo->prepare('INSERT IGNORE INTO roles_privilegios (rol_id, privilegio_id) VALUES (:rol_id, :privilegio_id)');
        foreach ($todosLosPrivilegios as $privilegioId) {
            $insertarRolPrivilegio->execute(['rol_id' => $rolAdministradorId, 'privilegio_id' => (int) $privilegioId]);
        }
        $codigosPrivilegiosOperador = ['dashboard.view', 'pacientes.manage', 'ordenes.manage', 'cash.manage', 'muestras.manage', 'resultados.manage'];
        $insertarPrivilegioOperador = $pdo->prepare('INSERT IGNORE INTO roles_privilegios (rol_id, privilegio_id) SELECT :rol_id, id FROM privilegios WHERE codigo = :codigo');
        foreach ($codigosPrivilegiosOperador as $codigo) {
            $insertarPrivilegioOperador->execute(['rol_id' => $rolOperadorId, 'codigo' => $codigo]);
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS obras_sociales (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(120) NOT NULL,
                identificacion_fiscal VARCHAR(20) NOT NULL UNIQUE,
                condiciones_convenio TEXT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );
        $pdo->exec(
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
                CONSTRAINT fk_paciente_social_work FOREIGN KEY (obra_social_id) REFERENCES obras_sociales(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
        $invalidPatientCodes = (int) $pdo->query("SELECT COUNT(*) FROM pacientes WHERE codigo NOT REGEXP '^PAC-[0-9]{3,}$'")->fetchColumn();
        if ($invalidPatientCodes > 0) {
            $pacienteIds = $pdo->query('SELECT id FROM pacientes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
            $updatePatientCode = $pdo->prepare('UPDATE pacientes SET codigo = :codigo WHERE id = :id');
            foreach ($pacienteIds as $posicion => $pacienteId) {
                $updatePatientCode->execute([
                    'codigo' => 'PAC-' . str_pad((string) ($posicion + 1), 3, '0', STR_PAD_LEFT),
                    'id' => (int) $pacienteId,
                ]);
            }
        }
        $pdo->exec(
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
        if (!$pdo->query("SHOW COLUMNS FROM precios_practicas LIKE 'descripcion'")->fetch()) {
            $pdo->exec("ALTER TABLE precios_practicas ADD COLUMN descripcion VARCHAR(150) NOT NULL DEFAULT '' AFTER codigo_practica");
        }
        $invalidPracticeCodes = (int) $pdo->query("SELECT COUNT(*) FROM precios_practicas WHERE codigo_practica NOT REGEXP '^PRA-[0-9]{3,}$'")->fetchColumn();
        if ($invalidPracticeCodes > 0) {
            $practiceIds = $pdo->query('SELECT id FROM precios_practicas ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
            $updatePracticeCode = $pdo->prepare('UPDATE precios_practicas SET codigo_practica = :codigo WHERE id = :id');
            foreach ($practiceIds as $posicion => $practiceId) {
                $updatePracticeCode->execute([
                    'codigo' => 'PRA-' . str_pad((string) ($posicion + 1), 3, '0', STR_PAD_LEFT),
                    'id' => (int) $practiceId,
                ]);
            }
        }
        $pdo->exec(
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
                CONSTRAINT fk_estudio_practice_price FOREIGN KEY (precio_practica_id) REFERENCES precios_practicas(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS obras_sociales_estudios (
                obra_social_id INT UNSIGNED NOT NULL,
                estudio_id INT UNSIGNED NOT NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (obra_social_id, estudio_id),
                CONSTRAINT fk_sws_social_work FOREIGN KEY (obra_social_id) REFERENCES obras_sociales(id) ON DELETE CASCADE,
                CONSTRAINT fk_sws_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $pdo->exec(
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
                CONSTRAINT fk_parameter_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        foreach ([
            "nombre_seccion VARCHAR(120) NULL AFTER estudio_id",
            "texto_referencia VARCHAR(255) NULL AFTER maximo",
            "rango_min VARCHAR(50) NULL AFTER descripcion",
            "rango_max VARCHAR(50) NULL AFTER rango_min"
        ] as $parameterColumn) {
            $columnName = explode(' ', trim($parameterColumn))[0];
            if (!$pdo->query("SHOW COLUMNS FROM parametros_estudios LIKE '$columnName'")->fetch()) {
                $pdo->exec("ALTER TABLE parametros_estudios ADD COLUMN $parameterColumn");
            }
        }
        $estudioColumns = $pdo->query("SHOW COLUMNS FROM estudios LIKE 'precio_practica_id'")->fetch();
        if (!$estudioColumns) {
            $pdo->exec('ALTER TABLE estudios ADD COLUMN precio_practica_id INT UNSIGNED NULL AFTER parametros');
            $pdo->exec('ALTER TABLE estudios ADD CONSTRAINT fk_estudio_practice_price FOREIGN KEY (precio_practica_id) REFERENCES precios_practicas(id) ON DELETE SET NULL');
        }
        $oldPracticeColumn = $pdo->query("SHOW COLUMNS FROM estudios LIKE 'codigo_practica'")->fetch();
        if ($oldPracticeColumn) {
            $pdo->exec("INSERT IGNORE INTO precios_practicas (codigo_practica, precio) SELECT codigo_practica, precio FROM estudios WHERE codigo_practica IS NOT NULL AND codigo_practica <> ''");
            $pdo->exec('UPDATE estudios s INNER JOIN precios_practicas p ON p.codigo_practica = s.codigo_practica SET s.precio_practica_id = p.id WHERE s.precio_practica_id IS NULL');
            $oldPracticeIndex = $pdo->query("SHOW INDEX FROM estudios WHERE Key_name = 'codigo_practica'")->fetch();
            if ($oldPracticeIndex) {
                $pdo->exec('ALTER TABLE estudios DROP INDEX codigo_practica');
            }
            $pdo->exec('ALTER TABLE estudios DROP COLUMN codigo_practica, DROP COLUMN precio');
        }
        $pdo->exec(
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
                CONSTRAINT fk_order_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE RESTRICT,
                CONSTRAINT fk_order_social_work FOREIGN KEY (obra_social_id) REFERENCES obras_sociales(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
        $invalidOrderCodes = (int) $pdo->query("SELECT COUNT(*) FROM ordenes WHERE codigo NOT REGEXP '^ORD-[0-9]{3,}$'")->fetchColumn();
        if ($invalidOrderCodes > 0) {
            $orderIds = $pdo->query('SELECT id FROM ordenes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
            $temporaryOrderCode = $pdo->prepare('UPDATE ordenes SET codigo = :codigo WHERE id = :id');
            foreach ($orderIds as $ordenId) {
                $temporaryOrderCode->execute([
                    'codigo' => 'TMP-ORD-' . (int) $ordenId,
                    'id' => (int) $ordenId,
                ]);
            }
            $updateOrderCode = $pdo->prepare('UPDATE ordenes SET codigo = :codigo WHERE id = :id');
            foreach ($orderIds as $posicion => $ordenId) {
                $updateOrderCode->execute([
                    'codigo' => 'ORD-' . str_pad((string) ($posicion + 1), 3, '0', STR_PAD_LEFT),
                    'id' => (int) $ordenId,
                ]);
            }
        }
        $orderSocialWorkColumn = $pdo->query("SHOW COLUMNS FROM ordenes LIKE 'obra_social_id'")->fetch();
        if (!$orderSocialWorkColumn) {
            $pdo->exec('ALTER TABLE ordenes ADD COLUMN obra_social_id INT UNSIGNED NULL AFTER paciente_id');
            $pdo->exec('ALTER TABLE ordenes ADD CONSTRAINT fk_order_social_work FOREIGN KEY (obra_social_id) REFERENCES obras_sociales(id) ON DELETE SET NULL');
        }
        foreach ([
            "estado_pago ENUM('No requiere','Pendiente','Parcial','Pagado','Pagar al retirar') NOT NULL DEFAULT 'No requiere' AFTER estado",
            "total_adeudado DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER estado_pago",
            "monto_pagado DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_adeudado"
        ] as $orderColumnDefinition) {
            $columnName = explode(' ', trim($orderColumnDefinition))[0];
            if (!$pdo->query("SHOW COLUMNS FROM ordenes LIKE '$columnName'")->fetch()) {
                $pdo->exec("ALTER TABLE ordenes ADD COLUMN $orderColumnDefinition");
            }
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ordenes_estudios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                orden_id INT UNSIGNED NOT NULL,
                estudio_id INT UNSIGNED NOT NULL,
                precio_practica_id INT UNSIGNED NULL,
                precio DECIMAL(12,2) NOT NULL DEFAULT 0,
                estado ENUM('Pendiente','Validada','Finalizada') NOT NULL DEFAULT 'Pendiente',
                estado_pago ENUM('No requiere','Pendiente','Pagado','Pagar al retirar') NOT NULL DEFAULT 'No requiere',
                UNIQUE KEY uq_order_estudio (orden_id, estudio_id),
                CONSTRAINT fk_order_estudio_order FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE,
                CONSTRAINT fk_order_estudio_estudio FOREIGN KEY (estudio_id) REFERENCES estudios(id) ON DELETE RESTRICT,
                CONSTRAINT fk_order_estudio_practice FOREIGN KEY (precio_practica_id) REFERENCES precios_practicas(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
        $paymentStatusColumn = $pdo->query("SHOW COLUMNS FROM ordenes_estudios LIKE 'estado_pago'")->fetch();
        if (!$paymentStatusColumn) {
            $pdo->exec("ALTER TABLE ordenes_estudios ADD COLUMN estado_pago ENUM('No requiere','Pendiente','Pagado','Pagar al retirar') NOT NULL DEFAULT 'No requiere' AFTER estado");
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS pagos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                orden_estudio_id INT UNSIGNED NULL,
                orden_id INT UNSIGNED NULL,
                importe DECIMAL(12,2) NOT NULL,
                metodo ENUM('Efectivo','Tarjeta','Transferencia','Pagar al retirar') NOT NULL,
                pagado_en DATETIME NULL,
                estado ENUM('Pendiente','Pagado','Pagar al retirar') NOT NULL DEFAULT 'Pendiente',
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_payment_order_estudio FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        if (!$pdo->query("SHOW COLUMNS FROM pagos LIKE 'orden_id'")->fetch()) {
            $pdo->exec('ALTER TABLE pagos ADD COLUMN orden_id INT UNSIGNED NULL AFTER orden_estudio_id');
            $pdo->exec('ALTER TABLE pagos DROP FOREIGN KEY fk_payment_order_estudio');
            $pdo->exec('ALTER TABLE pagos DROP INDEX orden_estudio_id');
            $pdo->exec('ALTER TABLE pagos ADD INDEX idx_payment_order_estudio (orden_estudio_id)');
            $pdo->exec('ALTER TABLE pagos ADD CONSTRAINT fk_payment_order_estudio FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id) ON DELETE CASCADE');
            $pdo->exec('ALTER TABLE pagos ADD CONSTRAINT fk_payment_order FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE CASCADE');
        }
        $oldPaymentIndex = $pdo->query("SHOW INDEX FROM pagos WHERE Key_name = 'orden_estudio_id' AND Non_unique = 0")->fetch();
        if ($oldPaymentIndex) {
            $pdo->exec('ALTER TABLE pagos DROP FOREIGN KEY fk_payment_order_estudio');
            $pdo->exec('ALTER TABLE pagos DROP INDEX orden_estudio_id');
            $pdo->exec('ALTER TABLE pagos ADD INDEX idx_payment_order_estudio (orden_estudio_id)');
            $pdo->exec('ALTER TABLE pagos ADD CONSTRAINT fk_payment_order_estudio FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id) ON DELETE CASCADE');
        }
        $pdo->exec('ALTER TABLE pagos MODIFY orden_estudio_id INT UNSIGNED NULL');
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS muestras (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(30) NOT NULL UNIQUE,
                orden_estudio_id INT UNSIGNED NOT NULL UNIQUE,
                recolectado_en DATETIME NULL,
                estado ENUM('Pendiente','Validada','Finalizada','Rechazada') NOT NULL DEFAULT 'Pendiente',
                motivo_rechazo VARCHAR(255) NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_muestra_order_estudio FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $invalidSampleCodes = (int) $pdo->query("SELECT COUNT(*) FROM muestras WHERE codigo NOT REGEXP '^MUE-[0-9]{3,}$'")->fetchColumn();
        if ($invalidSampleCodes > 0) {
            $muestraIds = $pdo->query('SELECT id FROM muestras ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
            $temporarySampleCode = $pdo->prepare('UPDATE muestras SET codigo = :codigo WHERE id = :id');
            foreach ($muestraIds as $muestraId) {
                $temporarySampleCode->execute([
                    'codigo' => 'TMP-MUE-' . (int) $muestraId,
                    'id' => (int) $muestraId,
                ]);
            }
            $updateSampleCode = $pdo->prepare('UPDATE muestras SET codigo = :codigo WHERE id = :id');
            foreach ($muestraIds as $posicion => $muestraId) {
                $updateSampleCode->execute([
                    'codigo' => 'MUE-' . str_pad((string) ($posicion + 1), 3, '0', STR_PAD_LEFT),
                    'id' => (int) $muestraId,
                ]);
            }
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS resultados (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                orden_estudio_id INT UNSIGNED NOT NULL UNIQUE,
                texto_resultado TEXT NULL,
                estado ENUM('Pendiente','Cargado','Entregado') NOT NULL DEFAULT 'Pendiente',
                entregado_en DATETIME NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_resultado_order_estudio FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nombre_usuario, nombre_completo, hash_contrasena, rol)
             VALUES (:nombre_usuario, :nombre_completo, :hash_contrasena, 'admin')
             ON DUPLICATE KEY UPDATE nombre_usuario = nombre_usuario"
        );
        $stmt->execute([
            'nombre_usuario' => 'admin',
            'nombre_completo' => 'Administrador del sistema',
            'hash_contrasena' => password_hash('contrasena', PASSWORD_DEFAULT),
        ]);
        $pdo->exec("INSERT IGNORE INTO usuarios_roles (usuario_id, rol_id) SELECT id, $rolAdministradorId FROM usuarios WHERE nombre_usuario = 'admin'");
        $pdo->exec("INSERT IGNORE INTO usuarios_roles (usuario_id, rol_id) SELECT u.id, r.id FROM usuarios u JOIN roles r ON r.nombre = 'Operador' WHERE u.rol = 'operador'");
    }
    return $pdo;
}
