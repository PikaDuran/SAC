<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentación - Descarga Masiva SAT v1.5 - SAC</title>
    <link rel="stylesheet" href="../../dashboard/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="descarga-xml.css">
    <style>
        .documentation {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .compliance-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .compliance-table th,
        .compliance-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            vertical-align: top;
        }

        .compliance-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .status-implemented {
            color: #28a745;
            font-weight: bold;
        }

        .status-partial {
            color: #ffc107;
            font-weight: bold;
        }

        .status-pending {
            color: #dc3545;
            font-weight: bold;
        }

        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 10px 0;
        }

        .requirement-section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }

        .requirement-section h3 {
            color: #007bff;
            margin-top: 0;
        }

        .field-mapping {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }

        .field-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }

        .field-group h4 {
            margin-top: 0;
            color: #495057;
        }

        .field-list {
            list-style: none;
            padding: 0;
        }

        .field-list li {
            padding: 5px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .field-list li:last-child {
            border-bottom: none;
        }

        .obligatorio {
            color: #dc3545;
            font-weight: bold;
        }

        .opcional {
            color: #6c757d;
        }

        .implementado {
            color: #28a745;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php
        session_start();
        require_once '../../../src/helpers/auth.php';
        checkAuth(['admin', 'contabilidad']);
        include '../../../src/views/sidebar.php';
        ?>

        <main class="main-content">
            <?php include '../../../src/views/header.php'; ?>

            <div class="content">
                <div class="content-header">
                    <nav class="breadcrumb">
                        <a href="../../dashboard/dashboard.php">Dashboard</a> &gt;
                        <a href="../dashboard.php">Contabilidad</a> &gt;
                        <a href="dashboard.php">SAT</a> &gt;
                        <a href="descarga-xml.php">Descarga Masiva</a> &gt;
                        Documentación
                    </nav>
                </div>

                <div class="documentation">
                    <h1>📚 Documentación - Servicio de Descarga Masiva SAT v1.5</h1>

                    <div class="alert alert-info">
                        <h3>✅ Estado de Cumplimiento</h3>
                        <p>Este sistema implementa completamente las especificaciones del <strong>Servicio de Solicitud de Descarga Masiva v1.5</strong> del SAT, según la documentación oficial.</p>
                    </div>

                    <!-- Resumen de Cumplimiento -->
                    <div class="requirement-section">
                        <h2>📋 Resumen de Cumplimiento</h2>
                        <table class="compliance-table">
                            <thead>
                                <tr>
                                    <th>Componente</th>
                                    <th>Estado</th>
                                    <th>Descripción</th>
                                    <th>Implementación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>SolicitaDescargaEmitidos</strong></td>
                                    <td><span class="status-implemented">✅ IMPLEMENTADO</span></td>
                                    <td>Descarga de CFDIs/Metadata emitidos</td>
                                    <td>SatDescargaMasivaService::solicitarDescargaEmitidos()</td>
                                </tr>
                                <tr>
                                    <td><strong>SolicitaDescargaRecibidos</strong></td>
                                    <td><span class="status-implemented">✅ IMPLEMENTADO</span></td>
                                    <td>Descarga de CFDIs/Metadata recibidos</td>
                                    <td>SatDescargaMasivaService::solicitarDescargaRecibidos()</td>
                                </tr>
                                <tr>
                                    <td><strong>SolicitaDescargaFolio</strong></td>
                                    <td><span class="status-implemented">✅ IMPLEMENTADO</span></td>
                                    <td>Descarga de CFDI específico por UUID</td>
                                    <td>SatDescargaMasivaService::solicitarDescargaFolio()</td>
                                </tr>
                                <tr>
                                    <td><strong>Verificación de Estado</strong></td>
                                    <td><span class="status-implemented">✅ IMPLEMENTADO</span></td>
                                    <td>Verificar estado de solicitudes</td>
                                    <td>SatDescargaMasivaService::verificarEstadoSolicitud()</td>
                                </tr>
                                <tr>
                                    <td><strong>Descarga de Paquetes</strong></td>
                                    <td><span class="status-implemented">✅ IMPLEMENTADO</span></td>
                                    <td>Descarga real de archivos ZIP del SAT</td>
                                    <td>SatDescargaMasivaService::descargarPaquetes()</td>
                                </tr>
                                <tr>
                                    <td><strong>Autenticación FIEL</strong></td>
                                    <td><span class="status-implemented">✅ IMPLEMENTADO</span></td>
                                    <td>Firma digital con certificado FIEL</td>
                                    <td>PhpCfdi\SatWsDescargaMasiva (librería oficial)</td>
                                </tr>
                                <tr>
                                    <td><strong>Manejo de Errores</strong></td>
                                    <td><span class="status-implemented">✅ IMPLEMENTADO</span></td>
                                    <td>Códigos de error según documentación SAT</td>
                                    <td>Mapeo completo de códigos 300-5005</td>
                                </tr>
                                <tr>
                                    <td><strong>Validaciones</strong></td>
                                    <td><span class="status-implemented">✅ IMPLEMENTADO</span></td>
                                    <td>Validación de RFC, fechas, formatos</td>
                                    <td>Validaciones según especificaciones SAT</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tipos de Solicitud -->
                    <div class="requirement-section">
                        <h2>🔧 Tipos de Solicitud Implementados</h2>

                        <h3>1. 📤 SolicitaDescargaEmitidos</h3>
                        <p>Permite solicitar la descarga de CFDIs o Metadata de comprobantes <strong>emitidos</strong>.</p>

                        <div class="field-mapping">
                            <div class="field-group">
                                <h4>Campos Obligatorios</h4>
                                <ul class="field-list">
                                    <li><span class="obligatorio">Fecha inicial</span> - Formato: AAAA-MM-DDThh:mm:ss</li>
                                    <li><span class="obligatorio">Fecha final</span> - Formato: AAAA-MM-DDThh:mm:ss</li>
                                    <li><span class="obligatorio">RFC Emisor</span> - RFC del emisor</li>
                                    <li><span class="obligatorio">Tipo de Solicitud</span> - "CFDI" o "Metadata"</li>
                                </ul>
                            </div>
                            <div class="field-group">
                                <h4>Campos Opcionales</h4>
                                <ul class="field-list">
                                    <li><span class="opcional">RFC Receptores</span> - Máximo 5 RFCs</li>
                                    <li><span class="opcional">RFC Solicitante</span> - Debe coincidir con emisor</li>
                                    <li><span class="opcional">Tipo Comprobante</span> - I, E, T, N, P</li>
                                    <li><span class="opcional">Estado Comprobante</span> - Todos, Cancelado, Vigente</li>
                                    <li><span class="opcional">RFC A Cuenta Terceros</span></li>
                                    <li><span class="opcional">Complemento</span></li>
                                </ul>
                            </div>
                        </div>

                        <h3>2. 📥 SolicitaDescargaRecibidos</h3>
                        <p>Permite solicitar la descarga de CFDIs o Metadata de comprobantes <strong>recibidos</strong>.</p>

                        <div class="field-mapping">
                            <div class="field-group">
                                <h4>Campos Obligatorios</h4>
                                <ul class="field-list">
                                    <li><span class="obligatorio">Fecha inicial</span> - Formato: AAAA-MM-DDThh:mm:ss</li>
                                    <li><span class="obligatorio">Fecha final</span> - Formato: AAAA-MM-DDThh:mm:ss</li>
                                    <li><span class="obligatorio">RFC Receptor</span> - RFC del receptor</li>
                                </ul>
                            </div>
                            <div class="field-group">
                                <h4>Campos Opcionales</h4>
                                <ul class="field-list">
                                    <li><span class="opcional">RFC Emisor</span></li>
                                    <li><span class="opcional">RFC Solicitante</span> - Debe coincidir con receptor</li>
                                    <li><span class="opcional">Tipo Comprobante</span> - I, E, T, N, P</li>
                                    <li><span class="opcional">Estado Comprobante</span> - Vigente (por defecto)</li>
                                    <li><span class="opcional">RFC A Cuenta Terceros</span></li>
                                    <li><span class="opcional">Complemento</span></li>
                                </ul>
                            </div>
                        </div>

                        <h3>3. 📄 SolicitaDescargaFolio</h3>
                        <p>Permite solicitar la descarga de un CFDI específico por folio UUID.</p>

                        <div class="field-mapping">
                            <div class="field-group">
                                <h4>Campos Obligatorios</h4>
                                <ul class="field-list">
                                    <li><span class="obligatorio">Folio</span> - UUID formato: XXXXXXXX-XXXX-XXXX-XXXXXXXXXXXXXXXX</li>
                                </ul>
                            </div>
                            <div class="field-group">
                                <h4>Campos Opcionales</h4>
                                <ul class="field-list">
                                    <li><span class="opcional">RFC Solicitante</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Autenticación y Seguridad -->
                    <div class="requirement-section">
                        <h2>🔐 Autenticación y Firma Digital</h2>

                        <h3>Requisitos de Autenticación Implementados:</h3>
                        <ul>
                            <li><span class="implementado">✅</span> <strong>Certificado (.cer)</strong> - Archivo de certificado FIEL</li>
                            <li><span class="implementado">✅</span> <strong>Llave privada (.key)</strong> - Archivo de llave privada FIEL</li>
                            <li><span class="implementado">✅</span> <strong>Password</strong> - Contraseña de la llave privada</li>
                            <li><span class="implementado">✅</span> <strong>Validación RFC</strong> - Verificación de coincidencia con certificado</li>
                            <li><span class="implementado">✅</span> <strong>Validación vigencia</strong> - Verificación de fechas válidas</li>
                        </ul>

                        <h3>Proceso de Firma Digital:</h3>
                        <ol>
                            <li><strong>Extracción de datos del certificado:</strong>
                                <ul>
                                    <li>Issuer/Emisor</li>
                                    <li>Serial number</li>
                                    <li>Certificado en base64</li>
                                </ul>
                            </li>
                            <li><strong>Cálculo de DigestValue:</strong>
                                <ul>
                                    <li>SHA1 del nodo de solicitud sin espacios</li>
                                    <li>Codificación en base64</li>
                                </ul>
                            </li>
                            <li><strong>Cálculo de SignatureValue:</strong>
                                <ul>
                                    <li>Digestión SHA1 con la llave privada</li>
                                    <li>SignedInfo canonicalizado</li>
                                    <li>Codificación en base64</li>
                                </ul>
                            </li>
                        </ol>
                    </div>

                    <!-- Códigos de Error -->
                    <div class="requirement-section">
                        <h2>❌ Manejo de Códigos de Error</h2>

                        <table class="compliance-table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Mensaje</th>
                                    <th>Implementado</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>300</td>
                                    <td>Usuario No Válido</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Validación de autenticación FIEL</td>
                                </tr>
                                <tr>
                                    <td>301</td>
                                    <td>XML Mal Formado</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Validación de parámetros y formato</td>
                                </tr>
                                <tr>
                                    <td>302</td>
                                    <td>Sello Mal Formado</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Validación de firma digital</td>
                                </tr>
                                <tr>
                                    <td>303</td>
                                    <td>Sello no corresponde con RFC</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Verificación de correspondencia RFC-Certificado</td>
                                </tr>
                                <tr>
                                    <td>304</td>
                                    <td>Certificado Revocado o Caduco</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Validación de vigencia del certificado</td>
                                </tr>
                                <tr>
                                    <td>305</td>
                                    <td>Certificado Inválido</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Validación de formato y tipo de certificado</td>
                                </tr>
                                <tr>
                                    <td>5000</td>
                                    <td>Solicitud de descarga recibida con éxito</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Respuesta exitosa del SAT</td>
                                </tr>
                                <tr>
                                    <td>5001</td>
                                    <td>Tercero no autorizado</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Validación de autorización para descargar</td>
                                </tr>
                                <tr>
                                    <td>5002</td>
                                    <td>Se han agotado las solicitudes de por vida</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Límite de solicitudes alcanzado</td>
                                </tr>
                                <tr>
                                    <td>5005</td>
                                    <td>Ya se tiene una solicitud registrada</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Solicitud duplicada con mismos criterios</td>
                                </tr>
                                <tr>
                                    <td>5012</td>
                                    <td>No se permite descarga de XML cancelados</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Solo para descarga de Folio</td>
                                </tr>
                                <tr>
                                    <td>404</td>
                                    <td>Error no controlado</td>
                                    <td><span class="status-implemented">✅</span></td>
                                    <td>Errores no clasificados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Validaciones -->
                    <div class="requirement-section">
                        <h2>✅ Validaciones Implementadas</h2>

                        <h3>Validaciones de Datos:</h3>
                        <ul>
                            <li><span class="implementado">✅</span> <strong>Formato RFC:</strong> Expresión regular para RFC válido mexicano</li>
                            <li><span class="implementado">✅</span> <strong>Formato UUID:</strong> Validación de folio con formato UUID estándar</li>
                            <li><span class="implementado">✅</span> <strong>Rango de fechas:</strong> Máximo 30 días entre fecha inicial y final</li>
                            <li><span class="implementado">✅</span> <strong>Fechas lógicas:</strong> Fecha inicial no mayor que fecha final</li>
                            <li><span class="implementado">✅</span> <strong>RFC Receptores:</strong> Máximo 5 RFCs para emitidos</li>
                            <li><span class="implementado">✅</span> <strong>Tipos de comprobante:</strong> Solo valores válidos (I, E, T, N, P)</li>
                            <li><span class="implementado">✅</span> <strong>Estados comprobante:</strong> Solo valores válidos (Todos, Cancelado, Vigente)</li>
                            <li><span class="implementado">✅</span> <strong>Correspondencia RFC:</strong> RFC solicitante debe coincidir con emisor/receptor</li>
                        </ul>

                        <h3>Validaciones de Seguridad:</h3>
                        <ul>
                            <li><span class="implementado">✅</span> <strong>Certificado vigente:</strong> Verificación de fechas de validez</li>
                            <li><span class="implementado">✅</span> <strong>Contraseña correcta:</strong> Validación de descifrado de llave privada</li>
                            <li><span class="implementado">✅</span> <strong>Integridad archivos:</strong> Verificación de existencia y formato</li>
                            <li><span class="implementado">✅</span> <strong>Autorización usuario:</strong> Solo usuarios autorizados pueden operar</li>
                        </ul>
                    </div>

                    <!-- Arquitectura Técnica -->
                    <div class="requirement-section">
                        <h2>🏗️ Arquitectura Técnica</h2>

                        <h3>Librerías Utilizadas:</h3>
                        <div class="code-block">
                            use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
                            use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
                            use PhpCfdi\SatWsDescargaMasiva\Service;
                            use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
                            use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
                        </div>

                        <h3>Estructura de Clases:</h3>
                        <ul>
                            <li><strong>SatDescargaMasivaService:</strong> Servicio principal con los 3 tipos de solicitud</li>
                            <li><strong>SatAuthenticationService:</strong> Manejo de autenticación y validación FIEL</li>
                            <li><strong>APIs REST:</strong> solicitar-descarga.php, verificar-solicitud.php, descargar-paquetes.php</li>
                            <li><strong>Base de datos:</strong> Almacenamiento de solicitudes y seguimiento de estados</li>
                        </ul>

                        <h3>Flujo de Operación:</h3>
                        <ol>
                            <li><strong>Autenticación:</strong> Validación de certificado FIEL y contraseña</li>
                            <li><strong>Solicitud:</strong> Envío de parámetros al SAT según tipo de solicitud</li>
                            <li><strong>Verificación:</strong> Consulta periódica del estado en el SAT</li>
                            <li><strong>Descarga:</strong> Obtención de paquetes ZIP cuando estén listos</li>
                            <li><strong>Almacenamiento:</strong> Guardado local de archivos descargados</li>
                        </ol>
                    </div>

                    <!-- Endpoints del Servicio SAT -->
                    <div class="requirement-section">
                        <h2>🌐 Endpoints del SAT Implementados</h2>

                        <div class="code-block">
                            <strong>URL Base:</strong> https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/SolicitaDescargaService.svc

                            <strong>SOAPActions Implementadas:</strong>
                            • SolicitaDescargaEmitidos:
                            "http://DescargaMasivaTerceros.sat.gob.mx/ISolicitaDescargaService/SolicitaDescargaEmitidos"

                            • SolicitaDescargaRecibidos:
                            "http://DescargaMasivaTerceros.sat.gob.mx/ISolicitaDescargaService/SolicitaDescargaRecibidos"

                            • SolicitaDescargaFolio:
                            "http://DescargaMasivaTerceros.sat.gob.mx/ISolicitaDescargaService/SolicitaDescargaFolio"

                            <strong>Headers Requeridos:</strong>
                            • Content-Type: text/xml;charset=UTF-8
                            • Authorization: WRAP access_token="[token]"&wrap_subject="[subject]"
                            • Accept-Encoding: gzip,deflate
                        </div>
                    </div>

                    <!-- Conclusión -->
                    <div class="requirement-section">
                        <h2>🎯 Conclusión de Cumplimiento</h2>

                        <div class="alert alert-success">
                            <h3>✅ CUMPLIMIENTO COMPLETO</h3>
                            <p>El sistema implementa <strong>completamente</strong> todas las especificaciones del <em>Servicio de Solicitud de Descarga Masiva v1.5</em> del SAT:</p>

                            <ul>
                                <li>✅ <strong>Los 3 tipos de solicitud</strong> están implementados según documentación</li>
                                <li>✅ <strong>Todos los campos obligatorios y opcionales</strong> son manejados correctamente</li>
                                <li>✅ <strong>Autenticación FIEL</strong> con firma digital según estándares SAT</li>
                                <li>✅ <strong>Códigos de error</strong> mapeados según documentación oficial</li>
                                <li>✅ <strong>Validaciones</strong> implementadas según reglas de negocio SAT</li>
                                <li>✅ <strong>Endpoints oficiales</strong> del SAT utilizados correctamente</li>
                                <li>✅ <strong>Formato XML</strong> generado según especificaciones SOAP</li>
                                <li>✅ <strong>Verificación y descarga</strong> de paquetes implementada</li>
                            </ul>

                            <p><strong>El sistema está listo para operación en producción</strong> con el SAT oficial.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../dashboard/dashboard.js"></script>
</body>

</html>