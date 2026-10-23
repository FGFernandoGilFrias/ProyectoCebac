<?php
/**
 * includes/helpers.php
 * Funciones helper comunes del sistema CEBAC.
 * Se incluye automáticamente desde includes/auth.php.
 */

if (!function_exists('fecha_ddmmyyyy')) {
    /**
     * Formatea una fecha Y-m-d a d/m/Y (o d-m-Y si se pasa '-').
     */
    function fecha_ddmmyyyy(?string $fecha, string $separador = '/'): string
    {
        if (!$fecha) return '—';
        $partes = explode('-', substr($fecha, 0, 10));
        return count($partes) === 3
            ? $partes[2] . $separador . $partes[1] . $separador . $partes[0]
            : $fecha;
    }
}

if (!function_exists('formato_moneda')) {
    /**
     * Formatea un número como moneda argentina.
     */
    function formato_moneda(float $valor): string
    {
        return '$ ' . number_format($valor, 2, ',', '.');
    }
}

if (!function_exists('calcular_saldo_paciente')) {
    /**
     * Calcula el saldo pendiente de un paciente.
     * Devuelve: bruto, porcentaje, a_cargo, pagado, saldo.
     */
    function calcular_saldo_paciente(int $pacienteCodigo): array
    {
        $conexion = conexion_bd();

        $stmt = $conexion->prepare(
            "SELECT COALESCE(SUM(pp.precio), 0)
             FROM ordenes o
             JOIN estudios_orden eo ON eo.orden_codigo = o.codigo
             JOIN estudios e ON e.codigo = eo.estudio_codigo
             JOIN precio_practica pp ON pp.codigo = e.practica_codigo
             WHERE o.paciente_codigo = :pc"
        );
        $stmt->execute(['pc' => $pacienteCodigo]);
        $totalBruto = (float) $stmt->fetchColumn();

        $stmt = $conexion->prepare(
            'SELECT porcentaje_cobertura FROM paciente_obra_social WHERE paciente_codigo = :pc'
        );
        $stmt->execute(['pc' => $pacienteCodigo]);
        $porcentaje = (float) ($stmt->fetchColumn() ?: 0);

        $aCargo = $totalBruto * (1 - $porcentaje / 100);

        $stmt = $conexion->prepare(
            'SELECT COALESCE(SUM(monto), 0) FROM caja WHERE paciente_codigo = :pc'
        );
        $stmt->execute(['pc' => $pacienteCodigo]);
        $pagado = (float) $stmt->fetchColumn();

        return [
            'bruto'      => $totalBruto,
            'porcentaje' => $porcentaje,
            'a_cargo'    => $aCargo,
            'pagado'     => $pagado,
            'saldo'      => max(0, $aCargo - $pagado),
        ];
    }
}

if (!function_exists('parsear_errores_orden')) {
    /**
     * Parsea historial_errores de una orden.
     * Devuelve: pendientes y rechazados por nombre de estudio.
     */
    function parsear_errores_orden(int $ordenCodigo, array $estudios): array
    {
        $conexion = conexion_bd();
        $stmt = $conexion->prepare(
            'SELECT error_muestra FROM historial_errores he
             JOIN resultados r ON r.codigo = he.resultado_codigo
             WHERE r.orden_codigo = :oc'
        );
        $stmt->execute(['oc' => $ordenCodigo]);
        $errores = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $pendientes = [];
        $rechazados = [];

        foreach ($estudios as $est) {
            $nombreEst = is_array($est) ? ($est['estudio'] ?? $est['nombre_estudio'] ?? '') : $est;
            foreach ($errores as $err) {
                if (stripos($err, $nombreEst) === false) continue;
                if (stripos($err, 'RECHAZADO') !== false) {
                    $rechazados[$nombreEst] = $err;
                } elseif (stripos($err, 'PENDIENTE') !== false) {
                    $pendientes[$nombreEst] = $err;
                }
            }
        }

        return ['pendientes' => $pendientes, 'rechazados' => $rechazados];
    }
}

if (!function_exists('iniciales_listado')) {
    /**
     * Extrae las iniciales de un nombre completo.
     */
    function iniciales_listado(string $nombre): string
    {
        $partes = preg_split('/\s+/', trim($nombre));
        $a = mb_substr($partes[0] ?? '', 0, 1, 'UTF-8');
        $b = isset($partes[1]) ? mb_substr($partes[1], 0, 1, 'UTF-8') : '';
        return mb_strtoupper($a . $b, 'UTF-8');
    }
}

if (!function_exists('numero_a_letras')) {
    /**
     * Convierte un número a letras (con centavos).
     * Soporta hasta 999.999.999.
     */
    function numero_a_letras(float $numero): string
    {
        $entero = (int) $numero;
        $centavos = (int) round(($numero - $entero) * 100);

        if ($entero === 0) {
            return 'cero pesos con ' . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . '/100';
        }

        $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
                     'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete',
                     'dieciocho', 'diecinueve', 'veinte'];
        $decenas = ['', '', 'veinti', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
                     'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        $texto = '';

        // Millones
        if ($entero >= 1000000) {
            $millones = (int) ($entero / 1000000);
            $texto .= ($millones === 1 ? 'un millón' : numero_a_letras_simple($millones, $unidades, $decenas, $centenas) . ' millones') . ' ';
            $entero = $entero % 1000000;
        }

        // Miles
        if ($entero >= 1000) {
            $miles = (int) ($entero / 1000);
            $texto .= ($miles === 1 ? 'mil' : numero_a_letras_simple($miles, $unidades, $decenas, $centenas) . ' mil') . ' ';
            $entero = $entero % 1000;
        }

        // Cientos
        if ($entero >= 100) {
            if ($entero === 100) {
                $texto .= 'cien ';
            } else {
                $c = (int) ($entero / 100);
                $texto .= $centenas[$c] . ' ';
            }
            $entero = $entero % 100;
        }

        // Decenas + unidades
        if ($entero > 0) {
            if ($entero <= 20) {
                $texto .= $unidades[$entero];
            } elseif ($entero < 30) {
                $texto .= 'veinti' . $unidades[$entero - 20];
            } else {
                $d = (int) ($entero / 10);
                $u = $entero % 10;
                $texto .= $decenas[$d] . ($u > 0 ? ' y ' . $unidades[$u] : '');
            }
        }

        return trim($texto) . ' pesos con ' . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . '/100';
    }
}

if (!function_exists('numero_a_letras_simple')) {
    /**
     * Helper interno para numero_a_letras (hasta 999).
     */
    function numero_a_letras_simple(int $n, array $unidades, array $decenas, array $centenas): string
    {
        $texto = '';
        if ($n >= 100) {
            if ($n === 100) return 'cien';
            $texto .= $centenas[(int) ($n / 100)] . ' ';
            $n = $n % 100;
        }
        if ($n > 0) {
            if ($n <= 20) {
                $texto .= $unidades[$n];
            } elseif ($n < 30) {
                $texto .= 'veinti' . $unidades[$n - 20];
            } else {
                $d = (int) ($n / 10);
                $u = $n % 10;
                $texto .= $decenas[$d] . ($u > 0 ? ' y ' . $unidades[$u] : '');
            }
        }
        return trim($texto);
    }
}