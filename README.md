<p align="center">
  <img src="https://raw.githubusercontent.com/monta990/responsivas/main/logo.png" alt="Responsivas logo" width="96">
</p>
<h1 align="center">Responsivas</h1>
<p align="center">
  <strong>GLPI plugin — Automatically generates PDF responsibility documents and loan contracts for IT assets assigned to users</strong>
</p>
<p align="center">
  <a href="https://github.com/glpi-project/glpi" target="_blank"><img src="https://img.shields.io/badge/GLPI-11.0%2B-blue" alt="GLPI compatibility"></a>
  <a href="https://www.gnu.org/licenses/old-licenses/gpl-2.0.html" target="_blank"><img src="https://img.shields.io/badge/License-GPL%20v3%2B-green" alt="License"></a>
  <a href="https://php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-%3E%3D8.2-purple" alt="PHP"></a>
  <a href="https://github.com/monta990/responsivas/releases" target="_blank"><img alt="GitHub Downloads (all assets, all releases)" src="https://img.shields.io/github/downloads/monta990/responsivas/total"></a>
</p>

---

## Overview

**Responsivas** is a GLPI plugin that automatically generates PDF responsibility documents (*cartas responsivas*) and loan contracts (*comodatos*) for IT assets assigned to users. Documents are sent directly to users via email as attachments.

---

## Features

- 📄 **Automatic PDF generation** for computers, printers, and mobile phones
- 📧 **Email delivery** — sends all PDFs as attachments to the assigned user's email
- 🖊️ **Fully customizable templates** — title, introduction, body/clauses, witnesses, footer, per asset type
- 🎨 **Text formatting** — `**bold**`, `*italic*`, `__underline__` in all template and email fields, combinable and nestable
- ✏️ **Format toolbar (B / I / U)** — toggle buttons above every text field with smart selection wrapping
- 🔤 **Clickable variable tags** — click any `{variable}` in the hints panel to insert it at the cursor
- 🔢 **QR codes** on every document linking directly to the asset in GLPI
- 👷 **Legal witnesses and representative** — configurable GLPI users
- 📱 **Phone loan contracts** (*comodatos*) with full legal clause set
- 🌍 **Multi-language** — Spanish (Mexico), English (US & GB), French, German
- 🔒 **CSRF protection** and GLPI permission model
- ⚙️ **Schema-versioned configuration** — safe migrations on plugin updates
- 👁️ **PDF preview with watermark** — each asset tab has a "Vista previa" button that generates a full watermarked PDF using current templates and real data (or realistic demo data if the admin has no assets of that type)
- 🔐 **Compression and protection toggles** — enable/disable PDF compression and copy/edit restrictions directly from the General configuration tab
- 📝 **Editable useful-life clauses** — two separate templates at the bottom of the Teléfonos tab: one for phones with an invoice/supplier (variables `{fecha_compra}`, `{factura}`, `{proveedor}`), one for phones without. Pre-filled on install with the standard text; never overwritten on update
- 📬 **Selective email sending** — the send confirmation modal lets you choose which document types to include (Computers, Printers, Phones). Only asset types with at least one assigned asset are shown, each with its count. All available types are pre-checked; uncheck any to exclude it.
- ✅ **Template validation** — warns before generating if required fields are empty

---

## Requirements

| Component | Minimum version |
|-----------|----------------|
| GLPI | ≥ 11.0.0 |
| PHP | ≥ 8.2 |
| TCPDF | included with GLPI |

---

## Installation

### From the GLPI Marketplace
1. Go to **Setup → Plugins → Marketplace**
2. Search for **Responsivas**
3. Click **Install**, then **Enable**

### Manual installation
1. Download the latest release `.zip` from [Releases](../../releases)
2. Unzip into your GLPI plugins directory:
   ```
   /var/www/glpi/plugins/responsivas/
   ```
3. Go to **Setup → Plugins**
4. Click **Install** next to Responsivas, then click **Enable**

---

## File Structure

```
responsivas/
├── front/
│   ├── computer.php          # Computer tab endpoint
│   ├── config.form.php       # Plugin configuration UI
│   ├── phone.php             # Phone tab endpoint
│   ├── preview.php           # Watermarked PDF preview endpoint
│   ├── printer.php           # Printer tab endpoint
│   ├── resource.send.php     # Logo resource endpoint
│   └── send_mail.php         # Email send endpoint
├── inc/
│   ├── config.class.php      # Configuration form and storage
│   ├── generator.class.php   # PDF generation orchestrator
│   ├── helpers.php           # Template renderer, editor, variable hints
│   ├── paths.class.php       # Plugin path helpers
│   ├── pdf.class.php         # TCPDF wrapper (watermark, footer)
│   ├── pdfbuilder.class.php  # PDF factory, render methods, demo builder
│   └── user.class.php        # User tab integration
├── locales/
│   ├── responsivas.pot       # Translation template (193 strings)
│   ├── es_MX.po / es_MX.mo  # Spanish (Mexico)
│   ├── en_US.po / en_US.mo  # English (US)
│   ├── en_GB.po / en_GB.mo  # English (GB)
│   ├── fr_FR.po / fr_FR.mo  # French
│   └── de_DE.po / de_DE.mo  # German
├── CHANGELOG.md
├── hook.php                  # Install / uninstall hooks
├── LICENSE                   # GNU General Public License v3
├── logo.png                  # Plugin icon (128×128) — read by GLPI Marketplace
├── plugin.xml                # GLPI catalog metadata
├── README.md
└── setup.php                 # Plugin registration and schema migration
```

---

## Configuration

Navigate to **Setup → Plugins → Responsivas** (or **Administration → Plugins → Responsivas Configuration**).

### General tab
| Field | Description |
|-------|-------------|
| Company name | Appears in document templates and email |
| Timezone | Used for document date/time |
| Show employee number | Toggle employee number display on PDFs |
| Show QR code | Toggle QR code on documents |
| Compress PDF | Enable/disable PDF file compression |
| Protect PDF | Enable/disable copy and edit restrictions on the PDF |
| Watermark text | Diagonal text shown on preview PDFs (default: `VISTA PREVIA`) |
| Watermark opacity | Opacity percentage for the watermark (5–100, default: 25) |
| Currency symbol | Used in phone loan contract price display |

### Witnesses tab
| Field | Description |
|-------|-------------|
| Witness 1 / Witness 2 | GLPI users who sign as witnesses on phone contracts |
| Legal representative | GLPI user who signs as the company representative |
| Phone asset type | The GLPI phone type used to identify loan phones |

### Templates tab (Computer / Printer / Phone)
Each asset type has its own set of template fields:

| Field | Description |
|-------|-------------|
| Document title | Header title on the PDF |
| Introduction / Opening paragraph | Text before the asset table |
| Body / Clauses | Main responsibility text or legal clauses |
| Witnesses paragraph | *(Phone only)* Closing witness statement |
| Useful-life clause (with invoice) | *(Phone only)* Template when phone has invoice and supplier data |
| Useful-life clause (without invoice) | *(Phone only)* Template when phone has no invoice or supplier |
| Footer fields | Left/right text on PDF page footer |
| Font size | PDF body font size |

**Text formatting in templates:** Use Markdown-style syntax — HTML tags are not supported because GLPI sanitizes them automatically.

| Syntax | Result |
|--------|--------|
| `**text**` | **Bold** |
| `*text*` | *Italic* |
| `__text__` | Underlined |

Formats can be combined and nested: `*__**text**__*` renders as bold + italic + underline simultaneously.

**Format toolbar (B / I / U):** Every editable text field — template fields, email body/footer, and PDF footer corner fields — has a small toolbar above it. Selecting text and clicking a button wraps it with the appropriate markers. Clicking the same button again on already-marked text removes the markers (toggle behavior).

**Clickable variable tags:** All `{variable}` tags in the hints panel are clickable. Click while a field is focused to insert the tag at the cursor position. If no field is focused, the tag is copied to the clipboard.

**Available template variables:**

| Variable | Description |
|----------|-------------|
| `{nombre}` | Full name of the assigned user |
| `{empresa}` | Company name |
| `{activo}` | Asset tag / other serial |
| `{fecha}` | Document date (dd/mm/yyyy) |
| `{hora}` | Document time |
| `{lugar}` | City, State, Country from entity |
| `{representante}` | Legal representative name |
| `{marca}` | Asset brand |
| `{modelo}` | Asset model |
| `{serie}` / `{serie_uuid}` | Serial number / UUID |
| `{imei}` | *(Phone)* IMEI number |
| `{linea}` | *(Phone)* Phone line / mobile number |
| `{almacenamiento}` | *(Phone)* Storage capacity |
| `{ram}` | *(Phone)* RAM |
| `{precio}` | *(Phone)* Purchase price |
| `{estado}` | Asset condition/status |
| `{clausula_vida_util}` | *(Phone)* Useful life clause — text defined in configuration |
| `{fecha_compra}` | *(Phone — useful-life template)* Purchase date |
| `{factura}` | *(Phone — useful-life template)* Invoice number |
| `{proveedor}` | *(Phone — useful-life template)* Supplier name |
| `{testigo1}` / `{testigo2}` | Witness names |
| `{direccion}` | Entity address |
| `{cp}` | Entity postal code |

### Email tab
| Field | Description |
|-------|-------------|
| Subject | Email subject line. Supports `{nombre}`, `{empresa}`, `{fecha}` |
| Body | Email body text. Supports `{nombre}`, `{empresa}`, `{fecha}` and `**bold**`, `*italic*`, `__underline__` |
| Footer | Optional footer below a separator line. Same formatting support |
| Test email button | Sends a test email (no PDFs attached) to your own GLPI registered address |

> The email test button uses a completely independent form from the Save button — no CSRF conflicts.

---

## Usage

### Sending a responsibility document to a user

1. Open any **User** record in GLPI (**Administration → Users**)
2. Go to the **Responsivas** tab
3. Review the list of assigned assets (computers, printers, phones)
4. Click **Send responsibility documents**
5. Confirm in the modal dialog

The plugin will generate one PDF per asset type (one for all computers, one for all printers, one per phone) and send them all as email attachments to the user's registered email address.

---

## Permissions

| Action | Required GLPI right |
|--------|-------------------|
| View Responsivas tab on a user | `user` → READ |
| Send documents / generate PDFs | `user` → READ |
| Access plugin configuration | `config` → UPDATE |

---

## Troubleshooting

**"The document template has empty fields"**
One or more required template fields are blank. Go to **Configuration → Responsivas → Templates** and fill in all required fields for the affected asset type.

**"No email address registered"**
The target user has no default email set in GLPI. Go to **Administration → Users → [user] → Email addresses** and add a default address.

**"GLPI mail server not configured"**
Email notifications must be enabled. Go to **Setup → Notifications → Email followups configuration** and enable notifications.

---

## Contributing

Pull requests are welcome. For major changes, please open an issue first.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes
4. Push to the branch
5. Open a Pull Request

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## Author

**Edwin Elias Alvarez** — [GitHub](https://github.com/monta990)

---

## Buy me a coffee :)

If you like my work, you can support me by a donate here:

<a href="https://www.buymeacoffee.com/monta990" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/default-yellow.png" alt="Buy Me A Coffee" height="51px" width="210px"></a>

---

## License

GPL v3 or later. See [LICENSE](LICENSE).

## Issues

Report bugs or request features on the [issue tracker](https://github.com/monta990/responsivas/issues).

---

<p align="center">
  <img src="https://raw.githubusercontent.com/monta990/responsivas/main/logo.png" alt="Responsivas logo" width="96">
</p>
<h1 align="center">Responsivas</h1>
<p align="center">
  <strong>Plugin para GLPI — Genera automáticamente cartas responsivas y contratos de comodato en PDF para activos de TI asignados a usuarios</strong>
</p>
<p align="center">
  <a href="https://github.com/glpi-project/glpi" target="_blank"><img src="https://img.shields.io/badge/GLPI-11.0%2B-blue" alt="GLPI compatibility"></a>
  <a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank"><img src="https://img.shields.io/badge/License-GPL%20v3%2B-green" alt="License"></a>
  <a href="https://php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-%3E%3D8.2-purple" alt="PHP"></a>
  <a href="https://github.com/monta990/responsivas/releases" target="_blank"><img alt="GitHub Downloads (all assets, all releases)" src="https://img.shields.io/github/downloads/monta990/responsivas/total"></a>
</p>

---

## Overview

**Responsivas** es un plugin para GLPI que genera automáticamente cartas responsivas y contratos de comodato en formato PDF para activos de TI asignados a usuarios. Los documentos se envían directamente al usuario por correo electrónico como archivos adjuntos.

---

## Características

- 📄 **Generación automática de PDFs** para computadoras, impresoras y teléfonos celulares
- 📧 **Envío por correo** — adjunta todos los PDFs al correo registrado del usuario en GLPI
- 🖊️ **Plantillas completamente configurables** — título, introducción, cuerpo/cláusulas, testigos y pie de página, por tipo de activo
- 🎨 **Formato de texto** — `**negrita**`, `*cursiva*`, `__subrayado__` en todos los campos, combinables y anidables
- ✏️ **Barra de formato (B / I / U)** — botones toggle sobre cada campo de texto
- 🔤 **Variables clicleables** — haz clic en cualquier `{variable}` para insertarla en el cursor
- 🔢 **Códigos QR** en cada documento con enlace directo al activo en GLPI
- 👷 **Testigos y representante legal** — usuarios de GLPI configurables
- 📱 **Contratos de comodato** para teléfonos con set completo de cláusulas legales
- 🌍 **Multiidioma** — Español (México), Inglés (EE. UU. y Reino Unido), Francés, Alemán
- 🔒 **Protección CSRF** y modelo de permisos de GLPI
- ⚙️ **Configuración con schema versionado** — migraciones seguras al actualizar el plugin
- 👁️ **Vista previa con marca de agua** — cada pestaña de activo tiene un botón "Vista previa" que genera un PDF completo con los datos actuales (o datos demo si el admin no tiene activos de ese tipo)
- 🔐 **Toggles de compresión y protección** — activa/desactiva la compresión del PDF y las restricciones de copia/edición desde la pestaña General
- 📝 **Cláusulas de vida útil editables** — dos plantillas al fondo de la pestaña Teléfonos: una para teléfonos con factura/proveedor (variables `{fecha_compra}`, `{factura}`, `{proveedor}`), otra para teléfonos sin datos de factura. Pre-llenadas en la instalación; nunca sobrescritas en actualizaciones
- 📬 **Envío selectivo por correo** — el modal de confirmación de envío permite elegir qué tipos de documentos incluir (Computadoras, Impresoras, Teléfonos). Solo se muestran los tipos con al menos un activo asignado, con su conteo. Todos los tipos disponibles vienen pre-seleccionados; desmarca los que no quieras enviar.
- ✅ **Validación de plantillas** — avisa antes de generar si algún campo requerido está vacío

---

## Requisitos

| Componente | Versión mínima |
|------------|---------------|
| GLPI | ≥ 11.0.0 |
| PHP | ≥ 8.2 |
| TCPDF | incluido con GLPI |

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

## Estructura de archivos

```
responsivas/
├── front/
│   ├── computer.php          # Endpoint de pestaña de computadoras
│   ├── config.form.php       # Interfaz de configuración del plugin
│   ├── phone.php             # Endpoint de pestaña de teléfonos
│   ├── preview.php           # Endpoint de vista previa con marca de agua
│   ├── printer.php           # Endpoint de pestaña de impresoras
│   ├── resource.send.php     # Endpoint de recurso de logo
│   └── send_mail.php         # Endpoint de envío de correo
├── inc/
│   ├── config.class.php      # Formulario y almacenamiento de configuración
│   ├── generator.class.php   # Orquestador de generación de PDFs
│   ├── helpers.php           # Renderizador de plantillas, editor, hints de variables
│   ├── paths.class.php       # Helpers de rutas del plugin
│   ├── pdf.class.php         # Wrapper de TCPDF (marca de agua, pie de página)
│   ├── pdfbuilder.class.php  # Factory de PDF, métodos render, constructor demo
│   └── user.class.php        # Integración de pestaña en usuario
├── locales/
│   ├── responsivas.pot       # Plantilla de traducciones (193 cadenas)
│   ├── es_MX.po / es_MX.mo  # Español (México)
│   ├── en_US.po / en_US.mo  # Inglés (EE. UU.)
│   ├── en_GB.po / en_GB.mo  # Inglés (Reino Unido)
│   ├── fr_FR.po / fr_FR.mo  # Francés
│   └── de_DE.po / de_DE.mo  # Alemán
├── CHANGELOG.md
├── hook.php                  # Hooks de instalación / desinstalación
├── LICENSE                   # Licencia Pública General GNU v3
├── logo.png                  # Ícono del plugin (128×128) — leído por el Marketplace de GLPI
├── plugin.xml                # Metadatos del catálogo de GLPI
├── README.md
└── setup.php                 # Registro del plugin y migración de schema
```

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
| Comprimir PDF | Activa/desactiva la compresión del archivo PDF |
| Proteger PDF | Activa/desactiva las restricciones de copia y edición del PDF |
| Texto de marca de agua | Texto diagonal en las vistas previas (predeterminado: `VISTA PREVIA`) |
| Opacidad de marca de agua | Porcentaje de opacidad de la marca de agua (5–100, predeterminado: 25) |
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
| Cláusula de vida útil (con factura) | *(Solo teléfono)* Plantilla cuando el teléfono tiene factura y proveedor registrados |
| Cláusula de vida útil (sin factura) | *(Solo teléfono)* Plantilla cuando el teléfono no tiene factura o proveedor registrado |
| Campos del pie de página | Texto izquierdo/derecho en el pie del PDF |
| Tamaño de fuente | Tamaño de letra del cuerpo del PDF |

**Formato de texto en las plantillas:** Usa sintaxis estilo Markdown — las etiquetas HTML no funcionan porque GLPI las sanitiza automáticamente.

| Sintaxis | Resultado |
|----------|-----------|
| `**texto**` | **Negrita** |
| `*texto*` | *Cursiva* |
| `__texto__` | Subrayado |

Los formatos se pueden combinar y anidar: `*__**texto**__*` produce negrita + cursiva + subrayado simultáneamente.

**Barra de formato (B / I / U):** Cada campo de texto editable — plantillas del documento, cuerpo/pie del correo y los cuatro campos del pie de página del PDF — tiene una pequeña barra de botones encima. Selecciona texto y haz clic en un botón para envolver con los marcadores. Volver a hacer clic en el mismo botón sobre texto ya marcado elimina los marcadores (comportamiento de toggle).

**Etiquetas de variable clicleables:** Todas las etiquetas `{variable}` del panel de hints son clicleables. Haz clic mientras un campo está activo para insertar la etiqueta en la posición del cursor. Si ningún campo está activo, la etiqueta se copia al portapapeles.

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
| `{clausula_vida_util}` | *(Teléfono)* Cláusula de vida útil — texto definido en la configuración |
| `{fecha_compra}` | *(Teléfono — plantilla vida útil)* Fecha de compra |
| `{factura}` | *(Teléfono — plantilla vida útil)* Número de factura |
| `{proveedor}` | *(Teléfono — plantilla vida útil)* Nombre del proveedor |
| `{testigo1}` / `{testigo2}` | Nombres de los testigos |
| `{direccion}` | Dirección de la entidad |
| `{cp}` | Código postal de la entidad |

### Pestaña Correo
| Campo | Descripción |
|-------|-------------|
| Asunto | Asunto del correo. Soporta `{nombre}`, `{empresa}`, `{fecha}` |
| Cuerpo | Cuerpo del correo. Soporta `{nombre}`, `{empresa}`, `{fecha}` y `**negrita**`, `*cursiva*`, `__subrayado__` |
| Pie de correo | Texto opcional bajo una línea separadora. Mismo soporte de formato |
| Botón de correo de prueba | Envía un correo de prueba (sin PDFs adjuntos) a tu propio correo registrado en GLPI |

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

---

## Contribuir

Los pull requests son bienvenidos. Para cambios importantes, por favor abre un issue primero.

1. Haz fork del repositorio
2. Crea tu rama de feature (`git checkout -b feature/mi-feature`)
3. Haz commit de tus cambios
4. Sube la rama (`git push origin feature/mi-feature`)
5. Abre un Pull Request

---

## Cambios

Ver [CHANGELOG.md](CHANGELOG.md).

---

## Autor

**Edwin Elias Alvarez** — [GitHub](https://github.com/monta990)

---

## Comprame un cafe :)

Si te gusta mi trabajo, me puedes apoyar con una donación:

<a href="https://www.buymeacoffee.com/monta990" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/default-yellow.png" alt="Buy Me A Coffee" height="51px" width="210px"></a>

---

## Licencia

GPL v3 o posterior. Ver [LICENSE](LICENSE).

## Problemas

Reporta errores o solicita funcionalidades en el [issue tracker](https://github.com/monta990/responsivas/issues).
