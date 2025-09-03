# REFERENCIA PERMANENTE DEL PROYECTO SAC

## MEMORIA TÉCNICA COMPLETA - NO PREGUNTAR NUNCA MÁS

### 📁 ESTRUCTURA DE DIRECTORIOS SAT DOWNLOADS

```
storage/sat_downloads/
├── BFM170822P38/           # RFC Principal
│   ├── EMITIDAS/
│   │   ├── 2020/1/ ... /12/
│   │   ├── 2021/1/ ... /12/
│   │   ├── 2022/1/ ... /12/
│   │   ├── 2023/1/ ... /12/  # TRANSICIÓN 3.3 + 4.0
│   │   ├── 2024/1/ ... /12/  # TRANSICIÓN 3.3 + 4.0
│   │   └── 2025/1/ ... /7/   # CFDI 4.0 PURO
│   └── RECIBIDAS/
│       └── [misma estructura]
└── BLM1706026AA/           # RFC Secundario
    └── [misma estructura]
```

### 🗄️ ESTRUCTURA DE BASE DE DATOS

#### TABLA: cfdi (Principal)

```sql
id                        int(11)              NOT NULL PRIMARY KEY
uuid                      varchar(255)         NULL
tipo                      varchar(50)          NULL
serie                     varchar(50)          NULL
folio                     varchar(50)          NULL
fecha                     datetime             NULL
fecha_timbrado            datetime             NULL
rfc_emisor                varchar(13)          NULL
nombre_emisor             varchar(500)         NULL
regimen_fiscal_emisor     varchar(10)          NULL
rfc_receptor              varchar(13)          NULL
nombre_receptor           varchar(500)         NULL
regimen_fiscal_receptor   varchar(10)          NULL
uso_cfdi                  varchar(10)          NULL
lugar_expedicion          varchar(10)          NULL
moneda                    varchar(10)          NULL
tipo_cambio               decimal(10,6)        NULL
subtotal                  decimal(15,2)        NULL
descuento                 decimal(15,2)        NULL
total                     decimal(15,2)        NULL
metodo_pago               varchar(10)          NULL
forma_pago                varchar(10)          NULL
exportacion               varchar(10)          NULL
archivo_xml               text                 NULL
complemento_tipo          text                 NULL
complemento_json          longtext             NULL
rfc_consultado            varchar(13)          NULL
direccion_flujo           varchar(20)          NULL
version                   varchar(10)          NULL
sello_cfd                 text                 NULL
sello_sat                 text                 NULL
no_certificado_sat        varchar(50)          NULL
rfc_prov_certif           varchar(13)          NULL
estatus_sat               varchar(20)          NULL
cfdi_relacionados         text                 NULL
no_certificado            varchar(50)          NULL
certificado               text                 NULL
condiciones_de_pago       text                 NULL
```

#### TABLA: cfdi_pagos (Complementos de Pago)

```sql
id                                int(11)              NOT NULL PRIMARY KEY
cfdi_id                          int(11)              NULL
version                          varchar(5)           NULL
fecha_pago                       datetime             NULL
forma_pago                       varchar(2)           NULL
moneda                           varchar(3)           NULL
tipo_cambio                      decimal(18,6)        NULL
monto                            decimal(18,2)        NULL
num_operacion                    varchar(100)         NULL
rfc_emisor_cuenta_ordenante      varchar(13)          NULL
nombre_banco_extranjero          varchar(150)         NULL
cuenta_ordenante                 varchar(50)          NULL
rfc_emisor_cuenta_beneficiario   varchar(13)          NULL
cuenta_beneficiario              varchar(50)          NULL
tipo_cadena_pago                 varchar(50)          NULL
certificado_pago                 text                 NULL
cadena_pago                      text                 NULL
sello_pago                       text                 NULL
```

#### TABLA: cfdi_pago_documentos_relacionados

```sql
id                        int(11)              NOT NULL PRIMARY KEY
pago_id                   int(11)              NULL
uuid_documento            varchar(36)          NULL
serie                     varchar(25)          NULL
folio                     varchar(40)          NULL
moneda_dr                 varchar(3)           NULL
equivalencia_dr           decimal(19,5)        NULL
num_parcialidad           int(11)              NULL
imp_saldo_ant             decimal(18,2)        NULL
imp_pagado                decimal(18,2)        NULL
imp_saldo_insoluto        decimal(18,2)        NULL
objeto_imp_dr             varchar(2)           NULL
```

#### OTRAS TABLAS RELACIONALES

- cfdi_conceptos (cfdi_id FK)
- cfdi_impuestos (cfdi_id FK)
- cfdi_timbre_fiscal (cfdi_id FK)
- cfdi_complementos (cfdi_id FK)
- cfdi_auditoria (uuid FK)

### 📋 TIPOS DE CFDI

- **I**: Ingreso
- **E**: Egreso
- **T**: Traslado
- **P**: Pago (Complemento de Pagos)
- **N**: Nómina

### 🏷️ VERSIONES CFDI

- **3.3**: 2020-2022 (puro) + 2023-2024 (mezclado)
- **4.0**: 2023-2024 (mezclado) + 2025 (puro)

### 🔧 ARCHIVOS PRINCIPALES DEL PROYECTO

#### IMPORTADORES

- `importador_inteligente_cfdi.php` - CLASE PRINCIPAL (ImportadorInteligenteCFDI)
- `importar_cfdi.php` - Script básico (OBSOLETO)
- `importar_cfdi_real_completo.php` - Script completo (USAR ESTE)

#### SCRIPTS DE PRUEBA

- `test_cfdi_40_2025.php` - Procesar solo 2025
- `test_cfdi_40.php` - Genérico CFDI 4.0
- `limpiar_tablas.php` - Limpiar TODAS las tablas

#### VERIFICACIÓN

- `check_db.php` - Estado de base de datos
- `verificar_tabla_cfdi.php` - Estructura de tablas

### ⚙️ PROCEDIMIENTOS ESTÁNDAR

#### LIMPIAR TABLAS

```bash
php limpiar_tablas.php
```

#### PROCESAR 2025 (CFDI 4.0 PURO)

```bash
php test_cfdi_40_2025.php
```

#### PROCESAR 2020-2022 (CFDI 3.3 PURO)

```bash
php importar_cfdi_real_completo.php
# Modificar para años 2020-2022
```

#### PROCESAR TODO 2020-2025 (TRANSICIÓN COMPLETA)

```bash
php importar_cfdi_real_completo.php
# Sin filtros de año
```

### 🎯 RUTAS ESPECÍFICAS PARA SCRIPTS

#### Para CFDI 4.0 (2025):

```php
$rutasBase = [
    'storage/sat_downloads/BFM170822P38/EMITIDAS/2025/',
    'storage/sat_downloads/BFM170822P38/RECIBIDAS/2025/',
    'storage/sat_downloads/BLM1706026AA/EMITIDAS/2025/',
    'storage/sat_downloads/BLM1706026AA/RECIBIDAS/2025/'
];
```

#### Para CFDI 3.3 (2020-2022):

```php
for ($año = 2020; $año <= 2022; $año++) {
    $rutasBase[] = "storage/sat_downloads/RFC/EMITIDAS/$año/";
    $rutasBase[] = "storage/sat_downloads/RFC/RECIBIDAS/$año/";
}
```

### 🔄 FLUJO DE TRABAJO COMPLETO

1. **LIMPIAR**: `php limpiar_tablas.php`
2. **PROCESAR 2025**: `php test_cfdi_40_2025.php`
3. **VERIFICAR**: Contar registros, complementos de pago
4. **PROCESAR 2020-2022**: Script con años específicos
5. **VERIFICAR**: Funcionamiento CFDI 3.3
6. **PROCESAR TODO**: Script completo 2020-2025
7. **VERIFICAR**: Transición automática 3.3→4.0

### ❌ NUNCA MÁS PREGUNTAR

- Estructura de carpetas SAT downloads
- Campos de tablas de base de datos
- Qué archivos usar para cada tarea
- Rutas de RFC y años
- Tipos de CFDI y versiones
- Procedimientos de limpieza

### 📝 ESTADÍSTICAS TÍPICAS ESPERADAS

- **2025**: ~1000 CFDIs, todos 4.0, ~50% pagos
- **2020-2022**: ~5000 CFDIs, todos 3.3, ~30% pagos
- **2023-2024**: ~3000 CFDIs, mezclado 3.3+4.0, ~40% pagos
