# Turtle — Portal de Gestión de Inquilinos

Una aplicación web para gestionar propiedades de alquiler, inquilinos, contratos de arrendamiento, tickets de mantenimiento, pagos de renta y solicitudes de alquiler. Diseñada para entornos Docker y complementos de Home Assistant con soporte completo de ingress.

## Inicio Rápido

### Docker

```bash
git clone --branch stable https://github.com/jessica12ryan/Turtle.git
cd Turtle
docker compose up -d --build
open http://localhost
```

El primer inicio presenta un asistente de configuración. Elija **Nueva instalación** para configurar la información del sitio o **Restaurar copia de seguridad** para cargar un archivo `.turtle`.

**Prueba de correo electrónico:** http://localhost:8025 (Mailpit)

### Complemento de Home Assistant

Turtle está disponible como complemento de Home Assistant en dos variantes:

| Complemento | Canal | Fuente |
|-------------|-------|--------|
| **Turtle** | Estable | `turtle-ha/` |
| **Turtle (Dev)** | Desarrollo | `turtle-ha-dev/` (compila desde `master`) |

Ambos soportan **ingress** (integrado en la interfaz de HA) y **acceso directo** a través del puerto.

## Funcionalidades

- **Propiedades** — gestionar detalles, fotos, tipo de calefacción, depósitos de garantía, estado de listado
- **Inquilinos** — inquilinos principal/secundario, fechas de contrato, mudanza programada, archivado automático
- **Contratos y documentos** — carga con título automático, tipos de documento, correo electrónico con archivos adjuntos
- **Tickets de mantenimiento** — crear, asignar, comentar, seguimiento de estado, archivos adjuntos
- **Panel de renta** — seguimiento de pagos por propiedad, indicadores de estado (pagado/parcial/no pagado)
- **Solicitudes de alquiler** — formulario de envío público, flujo de revisión, conversión a inquilino
- **Asistente de IA** — consultas en lenguaje natural sobre propiedades, inquilinos, tickets
- **Calendario** — fechas de mudanza, fin de contrato y mudanza programada
- **Recursos** — página de enlaces compartidos, categorías generales y solo para el personal
- **Copia de seguridad y restauración** — copia de seguridad completa del sistema (formato `.turtle`) desde la configuración de administración
- **Correo electrónico** — cliente SMTP ligero, preferencias de notificación por rol
- **Control de acceso** — Administrador, Propietario, Gestor de propiedades, Mantenimiento, Inquilino

## Permisos

El control de acceso utiliza middleware de rutas y permisos granulares configurables en **Configuración → Permisos**.

| Rol | Alcance |
|-----|---------|
| **Administrador** | Acceso sin restricciones |
| **Propietario** | Gestión completa de propiedades, inquilinos, personal, contratos, tickets, renta |
| **Gestor de propiedades** | Propiedades asignadas, inquilinos, tickets, renta |
| **Mantenimiento** | Tickets (ver, actualizar estado, comentar) |
| **Inquilino** | Sus propios tickets, contratos asignados, estado de renta, recursos |

## Correo Electrónico

Configurado a través de **Configuración → General** (SMTP) y **Configuración → Notificaciones** (preferencias por rol).

- **Docker dev**: Mailpit incluido en `mailpit:1025`, interfaz en `localhost:8025`
- **Complemento HA**: Mailpit incluido en el contenedor, puerto configurable
- **SMTP personalizado**: Establecido en la interfaz de configuración o archivo `.env`

## Localización

- Idiomas: inglés, francés, español — configurables en Configuración o por usuario
- Zona horaria: valor global predeterminado + anulación por usuario
- País: Canadá o Estados Unidos (provincias/estados, formatos postales/código postal)
- Sincronización NTP: servidor configurable, almacenamiento en caché por hora, alerta de desviación en el panel

## Estructura del Proyecto

```
www/                  Raíz de Apache — controladores, vistas, framework principal
database/             Esquema (schema.sql), datos iniciales (seed.sql), migraciones (migrate.sh)
docker/php/           Dockerfile + punto de entrada + configuración PHP
turtle-ha/            Complemento Home Assistant producción
turtle-ha-dev/        Complemento Home Assistant desarrollo
docker-compose.yml    Entorno de desarrollo local
update.sh             Script de actualización (git pull + docker compose up)
```

## Actualización

**En la aplicación** (admin): Configuración → Actualizaciones → Aplicar actualización (ejecuta `git pull` + migraciones).

**Manual**:
```bash
git checkout stable && git pull && docker compose up -d --build
```

**Complemento HA**: Reconstruir el complemento o usar el actualizador dentro del contenedor.

## Datos Persistentes

- Base de datos MySQL → volumen Docker `mysql-data`
- Archivos cargados (contratos, fotos, tickets) → volumen Docker `turtle-storage`

## Contribución

Consulte [CONTRIBUTING.md](CONTRIBUTING.md) para la configuración de desarrollo, estándares de codificación y pautas para solicitudes de extracción.

## Versiones

- **`stable`** — Versiones etiquetadas para producción
- **`master`** — Rama de desarrollo activa
