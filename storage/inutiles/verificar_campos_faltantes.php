<?php

/**
 * Verificar campos faltantes en la tabla CFDI
 */

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== VERIFICACIÓN DE CAMPOS FALTANTES ===\n\n";

    // Campos solicitados por el usuario
    $campos_solicitados = [
        'complemento_tipo',
        'complemento_json', 
        'rfc_consultado',
        'direccion_de_flujo',
        'version',
        'sello_sat',
        'no_certificado_sat',
        'rfc_prov_certif',
        'estatus_sat',
        'cfdi_relacionados'
    ];

    // Obtener estructura actual de la tabla cfdi
    $stmt = $pdo->query("DESCRIBE cfdi");
    $campos_actuales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 ESTRUCTURA ACTUAL TABLA CFDI:\n";
    $lista_campos_actuales = [];
    foreach ($campos_actuales as $campo) {
        $lista_campos_actuales[] = $campo['Field'];
        echo "   - {$campo['Field']} ({$campo['Type']}) - {$campo['Null']}\n";
    }

    echo "\n🔍 ANÁLISIS DE CAMPOS SOLICITADOS:\n";
    
    $campos_faltantes = [];
    $campos_existentes = [];
    
    foreach ($campos_solicitados as $campo) {
        if (in_array($campo, $lista_campos_actuales)) {
            $campos_existentes[] = $campo;
            echo "   ✅ {$campo} - EXISTE\n";
        } else {
            $campos_faltantes[] = $campo;
            echo "   ❌ {$campo} - FALTANTE\n";
        }
    }

    echo "\n📋 RESUMEN:\n";
    echo "   - Campos existentes: " . count($campos_existentes) . "\n";
    echo "   - Campos faltantes: " . count($campos_faltantes) . "\n";

    if (count($campos_faltantes) > 0) {
        echo "\n🛠️ CAMPOS QUE NECESITAN SER AGREGADOS:\n";
        foreach ($campos_faltantes as $campo) {
            echo "   - {$campo}\n";
        }
        
        echo "\n💡 ANÁLISIS POR CAMPO:\n";
        
        foreach ($campos_faltantes as $campo) {
            echo "\n--- {$campo} ---\n";
            
            switch ($campo) {
                case 'complemento_tipo':
                    echo "   Propósito: Tipo de complemento (pago, nomina, etc.)\n";
                    echo "   Tipo sugerido: VARCHAR(50)\n";
                    echo "   Origen: Complementos del CFDI\n";
                    break;
                    
                case 'complemento_json':
                    echo "   Propósito: Datos completos del complemento en JSON\n";
                    echo "   Tipo sugerido: TEXT o JSON\n";
                    echo "   Origen: Complementos completos del XML\n";
                    break;
                    
                case 'rfc_consultado':
                    echo "   Propósito: RFC que se consultó en el SAT\n";
                    echo "   Tipo sugerido: VARCHAR(13)\n";
                    echo "   Origen: Parámetro de consulta SAT\n";
                    break;
                    
                case 'direccion_de_flujo':
                    echo "   Propósito: Emitidas/Recibidas\n";
                    echo "   Tipo sugerido: ENUM('emitidas', 'recibidas')\n";
                    echo "   Origen: Directorio de descarga SAT\n";
                    break;
                    
                case 'version':
                    echo "   Propósito: Versión del CFDI (3.3, 4.0)\n";
                    echo "   Tipo sugerido: VARCHAR(10)\n";
                    echo "   Origen: Atributo Version del XML\n";
                    echo "   NOTA: ¿Ya existe este campo?\n";
                    break;
                    
                case 'sello_sat':
                    echo "   Propósito: Sello digital del SAT\n";
                    echo "   Tipo sugerido: TEXT\n";
                    echo "   Origen: TimbreFiscalDigital\n";
                    break;
                    
                case 'no_certificado_sat':
                    echo "   Propósito: Número de certificado del SAT\n";
                    echo "   Tipo sugerido: VARCHAR(30)\n";
                    echo "   Origen: TimbreFiscalDigital\n";
                    break;
                    
                case 'rfc_prov_certif':
                    echo "   Propósito: RFC del proveedor de certificación\n";
                    echo "   Tipo sugerido: VARCHAR(13)\n";
                    echo "   Origen: TimbreFiscalDigital\n";
                    break;
                    
                case 'estatus_sat':
                    echo "   Propósito: Estatus del CFDI en el SAT (vigente, cancelado)\n";
                    echo "   Tipo sugerido: VARCHAR(20)\n";
                    echo "   Origen: Consulta de estatus SAT\n";
                    break;
                    
                case 'cfdi_relacionados':
                    echo "   Propósito: CFDIs relacionados (JSON)\n";
                    echo "   Tipo sugerido: TEXT o JSON\n";
                    echo "   Origen: CfdiRelacionados del XML\n";
                    break;
            }
        }
    }

    // Verificar tabla de timbre fiscal
    echo "\n=== TABLA CFDI_TIMBRE_FISCAL ===\n";
    $stmt = $pdo->query("DESCRIBE cfdi_timbre_fiscal");
    $campos_timbre = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($campos_timbre as $campo) {
        echo "   - {$campo['Field']} ({$campo['Type']})\n";
    }

    // Verificar si algunos campos están en la tabla de timbre
    $campos_timbre_list = array_column($campos_timbre, 'Field');
    
    echo "\n🔍 CAMPOS EN TABLA TIMBRE:\n";
    foreach (['sello_sat', 'no_certificado_sat', 'rfc_prov_certif'] as $campo) {
        if (in_array($campo, $campos_timbre_list)) {
            echo "   ✅ {$campo} - EXISTE EN TIMBRE\n";
        } else {
            echo "   ❌ {$campo} - NO EXISTE EN TIMBRE\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
