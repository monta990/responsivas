# Responsivas — Plugin para GLPI

[![GLPI](https://img.shields.io/badge/GLPI-11.x-blue)](https://glpi-project.org)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://php.net)
[![Licencia](https://img.shields.io/badge/Licencia-GPLv2%2B-green)](LICENSE)
[![Versión](https://img.shields.io/badge/Versión-1.2.2-orange)](CHANGELOG.md)

**Responsivas** es un plugin para GLPI que genera automáticamente cartas responsivas y contratos de comodato en formato PDF para activos de TI asignados a usuarios. Los documentos se envían directamente al usuario por correo electrónico como archivos adjuntos.

---

## Características

- 📄 **Generación automática de PDFs** para computadoras, impresoras y teléfonos celulares
- 📧 **Envío por correo** — adjunta todos los PDFs al correo registrado del usuario en GLPI
- 🖊️ **Plantillas completamente configurables** — título, introducción, cuerpo/cláusulas, testigos y pie de página, por tipo de activo
- 🔢 **Códigos QR** en cada documento con enlace directo al activo en GLPI
- 👷 **Testigos y representante legal** — usuarios de GLPI configurables
- 📱 **Contratos de comodato** para teléfonos con set completo de cláusulas legales
- 🌍 **Multiidioma** — Español (México), Inglés, Francés, Alemán
- 🔒 **Protección CSRF** y modelo de permisos de GLPI
- ⚙️ **Configuración con schema versionado** — migraciones seguras al actualizar el plugin
- ✅ **Validación de plantillas** — avisa antes de generar si algún campo requerido está vacío

---

## Requisitos

| Componente | Versión mínima |
|------------|---------------|
| GLPI | 11.0 |
| PHP | 8.1 |
| TCPDF | incluido con GLPI |

> **Nota:** GLPI 10.x no está soportado oficialmente. El plugin utiliza APIs introducidas en GLPI 11.

---

## Instalación

### Desde el Marketplace de GLPI
1. Ve a **Configuración → Plugins → Marketplace**
2. Busca **Responsivas**
3. Haz clic en **Instalar** y luego en **Activar**

### Instalación manual
1. Descarga el `.zip` de la última versión desde [Releases](../../releases)
2. Descomprime dentro del directorio de plugins de GLPI:
   ```
   /var/www/glpi/plugins/responsivas/
   ```
3. Ve a **Configuración → Plugins**
4. Haz clic en **Instalar** junto a Responsivas y luego en **Activar**

---

## Configuración

Navega a **Configuración → Plugins → Responsivas** (o **Administración → Plugins → Configuración de Responsivas**).

### Pestaña General
| Campo | Descripción |
|-------|-------------|
| Nombre de la empresa | Aparece en las plantillas y en el correo |
| Zona horaria | Usada para la fecha y hora del documento |
| Mostrar número de empleado | Activa/desactiva el número de empleado en los PDFs |
| Mostrar QR | Activa/desactiva el código QR en los documentos |
| Símbolo de moneda | Se usa en los comodatos de teléfono para mostrar el precio |

### Pestaña Testigos
| Campo | Descripción |
|-------|-------------|
| Testigo 1 / Testigo 2 | Usuarios de GLPI que firman como testigos en contratos de teléfono |
| Representante legal | Usuario de GLPI que firma como representante de la empresa |
| Tipo de teléfono | Tipo de activo de teléfono usado para identificar equipos de comodato |

### Pestaña Plantillas (Computadora / Impresora / Teléfono)
Cada tipo de activo tiene su propio conjunto de campos de plantilla:

| Campo | Descripción |
|-------|-------------|
| Título del documento | Encabezado principal del PDF |
| Introducción / Párrafo de apertura | Texto antes de la tabla del activo |
| Cuerpo / Cláusulas | Texto principal de responsabilidad o cláusulas legales |
| Párrafo de testigos | *(Solo teléfono)* Declaración final de testigos |
| Campos del pie de página | Texto izquierdo/derecho en el pie del PDF |
| Tamaño de fuente | Tamaño de letra del cuerpo del PDF |

**Negritas en las plantillas:** Usa la sintaxis `**texto**` (como Markdown). Las etiquetas HTML no funcionan porque GLPI las sanitiza automáticamente.

**Variables disponibles en las plantillas:**

| Variable | Descripción |
|----------|-------------|
| `{nombre}` | Nombre completo del usuario asignado |
| `{empresa}` | Nombre de la empresa |
| `{activo}` | Número de activo / número de inventario |
| `{fecha}` | Fecha del documento (dd/mm/aaaa) |
| `{hora}` | Hora del documento |
| `{lugar}` | Ciudad, Estado, País de la entidad |
| `{representante}` | Nombre del representante legal |
| `{marca}` | Marca del activo |
| `{modelo}` | Modelo del activo |
| `{serie}` / `{serie_uuid}` | Número de serie / UUID |
| `{imei}` | *(Teléfono)* Número IMEI |
| `{linea}` | *(Teléfono)* Número de línea / celular |
| `{almacenamiento}` | *(Teléfono)* Capacidad de almacenamiento |
| `{ram}` | *(Teléfono)* Memoria RAM |
| `{precio}` | *(Teléfono)* Precio de compra |
| `{estado}` | Condición / estado del activo |
| `{clausula_vida_util}` | *(Teléfono)* Cláusula de vida útil generada automáticamente |
| `{testigo1}` / `{testigo2}` | Nombres de los testigos |
| `{direccion}` | Dirección de la entidad |
| `{cp}` | Código postal de la entidad |

### Pestaña Correo
| Campo | Descripción |
|-------|-------------|
| Asunto | Asunto del correo. Soporta `{nombre}`, `{empresa}`, `{fecha}` |
| Cuerpo | Cuerpo del correo. Soporta `{nombre}`, `{empresa}`, `{fecha}` |
| Pie de correo | Texto opcional bajo una línea separadora |
| Botón de correo de prueba | Envía un correo de prueba a tu propio correo registrado en GLPI |

> El botón de correo de prueba usa un formulario completamente independiente del botón Guardar, sin conflictos de token CSRF.

---

## Uso

### Enviar documentos de responsiva a un usuario

1. Abre cualquier registro de **Usuario** en GLPI (**Administración → Usuarios**)
2. Ve a la pestaña **Responsivas**
3. Revisa la lista de activos asignados (computadoras, impresoras, teléfonos)
4. Haz clic en **Enviar documentos de responsiva**
5. Confirma en el diálogo modal

El plugin genera un PDF por tipo de activo (uno para todas las computadoras, uno para todas las impresoras, uno por teléfono) y los envía como archivos adjuntos al correo registrado del usuario en GLPI.

### Descargar un documento directamente

Desde la pestaña **Responsivas** del usuario también puedes descargar cada PDF directamente sin enviar correo, usando los botones de descarga junto a cada tipo de activo.

---

## Permisos requeridos

| Acción | Permiso de GLPI requerido |
|--------|--------------------------|
| Ver pestaña Responsivas en un usuario | `user` → LECTURA |
| Enviar documentos / generar PDFs | `user` → LECTURA |
| Acceder a la configuración del plugin | `config` → MODIFICAR |

---

## Solución de problemas

**"La plantilla del documento tiene campos vacíos"**
Uno o más campos requeridos de la plantilla están en blanco. Ve a **Configuración → Responsivas → Plantillas** y completa todos los campos del tipo de activo afectado.

**"El usuario no tiene dirección de correo registrada"**
El usuario destino no tiene correo predeterminado en GLPI. Ve a **Administración → Usuarios → [usuario] → Direcciones de correo** y agrega una dirección predeterminada.

**"Servidor de correo de GLPI no configurado"**
Las notificaciones por correo deben estar activadas. Ve a **Configuración → Notificaciones → Configuración de correos** y activa las notificaciones.

**El botón de correo de prueba no hace nada**
Verifica que las notificaciones de correo de GLPI estén activadas y que tengas una dirección de correo predeterminada en tu propio perfil de GLPI.

---

## Contribuir

Los pull requests son bienvenidos. Para cambios importantes, por favor abre un issue primero.

1. Haz fork del repositorio
2. Crea tu rama de feature (`git checkout -b feature/mi-feature`)
3. Haz commit de tus cambios
4. Sube la rama (`git push origin feature/mi-feature`)
5. Abre un Pull Request

---

## Licencia

Este plugin se distribuye bajo la **Licencia Pública General GNU v2 o posterior (GPLv2+)**.  
Consulta [LICENSE](LICENSE) para los términos completos.

---

## Autor

**Edwin Elias Alvarez** — [Sontechs](https://sontechs.com)
