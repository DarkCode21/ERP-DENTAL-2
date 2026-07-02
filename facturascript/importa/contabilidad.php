<?php
function calcularAmortizacion($importe, $fecha_compra, $porcentaje_amortizacion, $tipo = "anual") {
    $fecha_inicio = new DateTime($fecha_compra);
    $año_inicio = (int)$fecha_inicio->format('Y');
    $mes_inicio = (int)$fecha_inicio->format('m');
    $dia_inicio = (int)$fecha_inicio->format('d');
    
    $amortizacion_anual = $importe * ($porcentaje_amortizacion / 100);
    $saldo_pendiente = $importe;
    $amortizaciones = [];
    
    // Si es mensual, calcula la amortización mensual
    if ($tipo === "mensual") {
        $amortizacion_mensual = $amortizacion_anual / 12;
    }
    
    echo "<h2>Plan de Amortización ($tipo)</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Año</th><th>Mes</th><th>Amortización</th><th>Saldo Pendiente</th></tr>";

    while ($saldo_pendiente > 0) {
        if ($tipo === "anual") {
            // Amortización del primer año (proporcional si no se compra en enero)
            if ($saldo_pendiente == $importe) {
                $dias_totales = date("L", mktime(0, 0, 0, 1, 1, $año_inicio)) ? 366 : 365;
                $dias_uso = $dias_totales - $fecha_inicio->diff(new DateTime("$año_inicio-12-31"))->days - 1;
                $amortizacion = ($amortizacion_anual * $dias_uso) / $dias_totales;
            } else {
                $amortizacion = min($amortizacion_anual, $saldo_pendiente);
            }
            
            echo "<tr><td>$año_inicio</td><td>-</td><td>" . number_format($amortizacion, 2) . " €</td><td>" . number_format($saldo_pendiente - $amortizacion, 2) . " €</td></tr>";
            $saldo_pendiente -= $amortizacion;
            $año_inicio++;

        } else if ($tipo === "mensual") {
            $año_actual = $fecha_inicio->format('Y');
            $mes_actual = $fecha_inicio->format('m');

            for ($i = 0; $i < 12; $i++) {
                if ($saldo_pendiente <= 0) break;

                $amortizacion = min($amortizacion_mensual, $saldo_pendiente);
                echo "<tr><td>$año_actual</td><td>$mes_actual</td><td>" . number_format($amortizacion, 2) . " €</td><td>" . number_format($saldo_pendiente - $amortizacion, 2) . " €</td></tr>";

                $saldo_pendiente -= $amortizacion;
                $mes_actual++;
                if ($mes_actual > 12) {
                    $mes_actual = 1;
                    $año_actual++;
                }
            }

            $fecha_inicio->modify('+1 year');
        }
    }

    echo "</table>";
}

// Definir valores
//$importe = 2000; // Valor del activo en euros
//$fecha_compra = "2023-10-13"; // Fecha de compra
//$porcentaje_amortizacion = 33; // % de amortización anual
//$tipo = "anual"; // "anual" o "mensual"


// Definir valores
$importe = 24951.4; // Valor del activo en euros
$fecha_compra = "2018-10-13"; // Fecha de compra
$porcentaje_amortizacion = 2; // % de amortización anual
$tipo = "anual"; // "anual" o "mensual"


// Llamada a la función
calcularAmortizacion($importe, $fecha_compra, $porcentaje_amortizacion, $tipo);
?>
