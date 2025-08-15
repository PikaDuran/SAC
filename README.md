# SAC - Sistema Empresarial Modular

## 🚨 REGLA DE ORO - NO TOCAR LO QUE FUNCIONA

**NUNCA modificar código que funciona correctamente por temas visuales o arquitecturales.**
**Si funciona, NO lo toques. Funcionalidad > Arquitectura perfecta.**

## Descripción

Sistema modular, seguro y escalable para gestión empresarial con integración SAT. Backend en PHP estructurado, frontend HTML/CSS/JS, base de datos MySQL, despliegue con Docker.

## Características Principales

- ✅ **Autenticación segura** con control de sesiones
- ✅ **Roles y permisos** dinámicos por módulo
- ✅ **Integración SAT Real** (50% completado - 2/4 funcionalidades)
  - ✅ **Gestión FIEL** con validación en tiempo real
  - ✅ **Descarga Masiva** con tokens SAT auténticos
  - 🔄 **Procesamiento XMLs** (próxima implementación)
  - 🔄 **Reportes Fiscales** (próxima implementación)
- ✅ **Log de actividades** completo y centralizado
- ✅ **Sistema de auditoría** para todas las operaciones
- ✅ **Trazabilidad completa** por usuario, módulo y acción
- ✅ **Diseño profesional** y responsive
- ✅ **Arquitectura modular** fácil de extender

## Roles y Módulos

- **Admin**: Acceso total a todos los módulos
- **Contabilidad**:
  - **SAT**: e.Firma, Descarga XML, Reportes
  - Opciones adicionales de contabilidad
- **Operaciones**: Clientes (Reporte, Admin), Buró de Crédito (Tablas amortización, Macros), RIBC
- **HR**: Solicitudes, Horarios

## Módulo SAT - Implementación Progresiva (2/4 Completado)

### 🎯 Progreso Actual: 50% Completado (2 de 4 funcionalidades)

#### ✅ 1. Gestión de Certificados FIEL (COMPLETADO)

- **Estado**: ✅ COMPLETAMENTE FUNCIONAL y validado con SAT real
- **Funcionalidades**:
  - Validación en tiempo real con servicios web del SAT
  - Gestión de certificados FIEL (.cer/.key)
  - Almacenamiento seguro de certificados
  - Historial de actividades por usuario
  - Validación de vigencia automática
  - Interfaz intuitiva con validación de archivos en tiempo real

#### ✅ 2. Descarga Masiva SAT (COMPLETADO)

- **Estado**: ✅ COMPLETAMENTE FUNCIONAL con integración SAT real
- **Características**:
  - Integración real con API SAT usando `phpcfdi/sat-ws-descarga-masiva`
  - Autenticación FIEL con certificados reales
  - Generación de tokens SAT auténticos
  - Soporte para 3 tipos de solicitud: Emitidos, Recibidos, y por Folio
  - Validación automática de periodos (máximo 30 días)
  - Historial completo de solicitudes con estados en tiempo real

#### 🔄 3. Procesamiento de XMLs (PENDIENTE)

- **Estado**: ⏳ PRÓXIMA IMPLEMENTACIÓN
- **Funcionalidades planeadas**:
  - Descarga de paquetes ZIP desde SAT
  - Extracción y procesamiento de XMLs individuales
  - Almacenamiento en base de datos con indexación
  - Validación de integridad de archivos

#### 🔄 4. Reportes y Análisis (PENDIENTE)

- **Estado**: ⏳ PRÓXIMA IMPLEMENTACIÓN
- **Funcionalidades planeadas**:
  - Dashboard de análisis fiscal
  - Reportes de facturación por periodo
  - Conciliación contable automatizada
  - Exportación en múltiples formatos

### Tecnologías SAT Implementadas

- **Librería Oficial**: `phpcfdi/sat-ws-descarga-masiva` v0.14.0+
- **Autenticación Real**: FIEL con firma digital SHA1/Base64
- **Protocolo**: SOAP con validación XML
- **Tokens**: JWT del SAT con expiración automática
- **Certificados**: X.509 con validación de vigencia

### Servicios SAT Operativos

- **✅ SolicitarDescarga**: Para CFDIs emitidos y recibidos
- **✅ VerificarSolicitud**: Verificación de estado en tiempo real
- **✅ DescargarPaquetes**: Descarga de archivos ZIP
- **✅ Autenticación**: Generación de tokens con FIEL real

### Certificados Validados

- **RFC Activo**: BFM170822P38 con certificado vigente
- **Autenticación**: Contraseña validada y funcional
- **Token Real Generado**: `8b29edff-c601-4cab-b66d-b7445cce9a77`
- **Estado**: ✅ Comunicación directa con servidores SAT

### Última Actualización

- **Versión**: 0.5.0 (07/08/2025)
- **Hito**: 50% del módulo SAT completado (2/4 funcionalidades)
- **Estado**: Sistemas críticos operativos con SAT real

## Estructura

- **docker/**: Archivos de despliegue Docker
- **public/**: Archivos públicos y módulos por rol
  - **login/**: Sistema de autenticación
  - **dashboard/**: Panel principal con menú dinámico
  - **contabilidad/sat/**: Módulos SAT (e.Firma, Descarga XML, Reportes)
  - **operaciones/**: Módulos de operaciones
  - **rh/**: Módulos de recursos humanos
  - **it/**: Módulos de IT (solo admin)
- **src/**: Lógica backend (MVC + Servicios)
  - **controllers/**: Controladores
  - **models/**: Modelos de datos
  - **views/**: Vistas compartidas
  - **Services/**: Servicios especializados (SAT, etc.)
  - **helpers/**: Funciones auxiliares
  - **config/**: Configuración (base de datos, constantes)
- **sql/**: Scripts de base de datos
- **storage/**: Almacenamiento de archivos
  - **fiel_certificates/**: Certificados FIEL
  - **sat_downloads/**: Descargas SAT
- **vendor/**: Dependencias de Composer
- **.env**: Configuración de entorno

## Requisitos del Sistema

### PHP

- **Versión**: PHP 8.2 o superior
- **Extensiones requeridas**:
  - `ext-openssl` (para certificados FIEL)
  - `ext-curl` (para servicios web SAT)
  - `ext-dom` (para procesamiento XML)
  - `ext-libxml` (para validación XML)
  - `ext-mysqli` (para base de datos)
  - `ext-pdo` (para base de datos)

### Base de Datos

- **MySQL**: 8.0 o superior
- **MariaDB**: 10.6 o superior

### Servidor Web

- **Apache**: 2.4 o superior (con mod_rewrite)
- **Nginx**: 1.18 o superior

## Instalación Completa

### Opción 1: XAMPP Local

1. **Preparar entorno**:

   ```bash
   # Copiar proyecto
   cp -r SAC c:\xampp\htdocs\

   # Instalar Composer (si no está instalado)
   # Descargar desde: https://getcomposer.org/
   ```

2. **Instalar dependencias**:

   ```bash
   cd c:\xampp\htdocs\SAC
   composer install
   ```

3. **Configurar base de datos**:

   - Inicia Apache y MySQL en XAMPP
   - Crea la base de datos `sac_db`
   - Ejecuta los scripts SQL en orden:
     ```sql
     source sql/01_create_database.sql
     source sql/02_users_table.sql
     source sql/03_initial_data.sql
     source sql/06_sat_tables.sql
     ```

4. **Configurar permisos**:

   ```bash
   # Crear directorios necesarios
   mkdir storage/fiel_certificates
   mkdir storage/sat_downloads
   mkdir temp

   # Configurar permisos (Linux/Mac)
   chmod 755 storage/
   chmod 755 temp/
   ```

5. **Acceder al sistema**:
   - Visita: `http://localhost/SAC/public/login/login.html`
   - Usuario: `admin` | Contraseña: `admin123`

### Opción 2: Docker

1. **Configurar entorno**:

   ```bash
   # Clonar repositorio
   git clone [repo-url]
   cd SAC

   # Configurar .env (opcional)
   cp .env.example .env
   ```

2. **Ejecutar con Docker**:

   ```bash
   cd docker
   docker-compose up --build
   ```

3. **Acceder al sistema**:
   - Visita: `http://localhost:8080/login/login.html`
   - Usuario: `admin` | Contraseña: `admin123`

## Acceso al Sistema

### Credenciales por defecto

- **URL**: `http://localhost/SAC/public/login/login.html`
- **Usuario**: `admin`
- **Contraseña**: `admin123`

### Navegación Rápida

- **Dashboard**: Panel principal con menús dinámicos por rol
- **SAT e.Firma**: Dashboard → Contabilidad → SAT → e.Firma
- **Gestión Clientes**: Dashboard → Operaciones → Clientes
- **RH**: Dashboard → RH → [Solicitudes/Horarios]

### Roles Disponibles

- **Admin**: Acceso completo a todos los módulos
- **Contabilidad**: Módulos SAT y contabilidad
- **Operaciones**: Clientes, Buró de Crédito, RIBC
- **HR**: Recursos Humanos

## Características de Seguridad

- Hash de contraseñas con `password_hash()`
- Control de sesiones con timeout de 25 minutos
- Sanitización de datos de entrada
- Control de acceso por roles
- Protección básica contra CSRF/XSS/SQLi

## Módulos Implementados

### Login

- Diseño profesional y compacto
- Validación frontend y backend
- Control de sesiones seguro

### Dashboard

- Menú lateral dinámico según rol
- Estructura de 3 niveles (Contabilidad > SAT > e.Firma)
- Información del usuario logueado
- Diseño responsive moderno

### SAT - e.Firma

- **Validación en tiempo real** con servicios del SAT
- **Gestión de certificados FIEL** (.cer/.key)
- **Almacenamiento seguro** de archivos
- **Registro de actividades** por usuario
- **Interfaz intuitiva** con validación de archivos

### Contabilidad

- **Descarga XML**: Procesamiento masivo (próximamente)
- **Reportes SAT**: Análisis de descargas (próximamente)

### Operaciones

- **Clientes**: CRUD completo con búsqueda y filtros
- **Buró de Crédito**: Tablas de amortización y macros
- **RIBC**: Módulo especializado

### RH

- **Solicitudes**: Gestión de permisos, vacaciones, licencias
- **Horarios**: Control de entradas y salidas

### IT (Solo Admin)

- **Sistemas**: Administración de sistemas
- **Usuarios**: Gestión de usuarios del sistema

## Base de Datos

- **usuarios**: Control de acceso y roles
- **clientes**: Gestión de clientes
- **solicitudes_rh**: Solicitudes de RH
- **horarios**: Registro de horarios
- **log_actividades**: Auditoría del sistema

## Arquitectura Modular

- Separación estricta: HTML, CSS, JS, PHP
- Sin datos hardcodeados
- Configuración centralizada en .env
- Estructura MVC limpia
- Componentes reutilizables

## Contacto y contribuciones

Sistema desarrollado siguiendo buenas prácticas de desarrollo modular y seguridad empresarial.

**Versión Actual**: v0.5.0 - Hito SAT 50% (2/4 funcionalidades completadas)
**Última Actualización**: 07 de Agosto de 2025

### Estado del Proyecto

- **Módulo SAT**: 50% completado con integración real operativa
- **Sistemas Base**: 100% funcionales (autenticación, roles, auditoría)
- **Próximas Fases**: Procesamiento XMLs y reportes fiscales

# NOTA IMPORTANTE PARA DESARROLLO

- Los estilos y scripts globales (header, menú, etc.) deben estar en archivos CSS/JS globales, no en archivos de páginas específicas.
- No inventar ni modificar código fuera del alcance solicitado.
- Si tienes dudas, pregunta antes de asumir.
- Documenta cualquier cambio global en este README y en el CHANGELOG.
