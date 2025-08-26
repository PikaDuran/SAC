<?php
/**
 * Instalador simple de complementos de pago
 * Solo crea las tablas necesarias
 */

require_once __DIR__ . '/src/config/database.php';

echo "🔧 INSTALANDO SISTEMA DE COMPLEMENTOS DE PAGO\n\n";

try {
    $pdo = getDatabase();
    
    echo "1. Creando tabla cfdi_pagos...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cfdi_pagos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cfdi_id INT NOT NULL,
            fecha_pago DATETIME,
            forma_pago_p VARCHAR(10),
            moneda_p VARCHAR(10) DEFAULT 'MXN',
            tipo_cambio_p DECIMAL(19,6) DEFAULT 1.000000,
            monto DECIMAL(19,6),
            num_operacion VARCHAR(100),
            rfc_emisor_cta_ord VARCHAR(13),
            nom_banco_ord_ext VARCHAR(300),
            cta_ordenante VARCHAR(50),
            rfc_emisor_cta_ben VARCHAR(13),
            cta_beneficiario VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cfdi_id (cfdi_id),
            INDEX idx_fecha_pago (fecha_pago),
            FOREIGN KEY (cfdi_id) REFERENCES cfdi(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "   ✅ Tabla cfdi_pagos creada\n";
    
    echo "2. Creando tabla cfdi_pago_documentos_relacionados...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cfdi_pago_documentos_relacionados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pago_id INT NOT NULL,
            id_documento VARCHAR(36) NOT NULL,
            serie VARCHAR(25),
            folio VARCHAR(40),
            moneda_dr VARCHAR(10) DEFAULT 'MXN',
            equivalencia_dr DECIMAL(19,6) DEFAULT 1.000000,
            num_parcialidad INT,
            imp_saldo_ant DECIMAL(19,6),
            imp_pagado DECIMAL(19,6),
            imp_saldo_insoluto DECIMAL(19,6),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pago_id (pago_id),
            INDEX idx_id_documento (id_documento),
            FOREIGN KEY (pago_id) REFERENCES cfdi_pagos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "   ✅ Tabla cfdi_pago_documentos_relacionados creada\n";
    
    echo "3. Verificando estructura...\n";
    
    // Verificar CFDIs de pago disponibles
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cfdi WHERE tipo = 'P'");
    $totalPagos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "   📊 CFDIs de tipo Pago encontrados: $totalPagos\n";
    
    echo "\n✅ INSTALACIÓN COMPLETADA\n";
    echo "Las tablas están listas para procesar complementos de pago\n\n";
    
    if ($totalPagos > 0) {
        echo "🔄 Para procesar los CFDIs existentes, ejecuta:\n";
        echo "   php procesar_complementos_pago.php\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
