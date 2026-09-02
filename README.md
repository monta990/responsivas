<p align="center">
  <img src="https://raw.githubusercontent.com/monta990/responsivas/main/logo.png" alt="Responsivas logo" width="96">
</p>
<h1 align="center">Responsibility Forms</h1>
<p align="center">
  <strong>GLPI plugin — Automatically generates PDF responsibility documents and loan contracts for IT assets assigned to users</strong>
</p>
<p align="center">
  <a href="https://github.com/glpi-project/glpi" target="_blank"><img src="https://img.shields.io/badge/GLPI-11.0%2B-blue" alt="GLPI compatibility"></a>
  <a href="https://github.com/glpi-project/glpi" target="_blank"><img src="https://img.shields.io/badge/GLPI-12.0%2B-blue" alt="GLPI compatibility"></a>
  <a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank"><img src="https://img.shields.io/badge/License-GPL%20v3%2B-green" alt="License"></a>
  <a href="https://php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-%3E%3D8.2-purple" alt="PHP"></a>
  <a href="https://github.com/monta990/responsivas/releases" target="_blank"><img alt="GitHub Downloads (all assets, all releases)" src="https://img.shields.io/github/downloads/monta990/responsivas/total"></a>
</p>

---

# English

## Overview

**Responsivas** generates PDF responsibility documents and phone loan contracts for IT assets assigned to GLPI users. It also provides fixed, print-ready visual inspection and return forms for Computers, Printers and Phones.

The plugin is designed to use the same codebase on **GLPI 11.x and GLPI 12.x** and follows the modern plugin architecture with PSR-4 classes, Symfony/GLPI Controllers and Twig templates.

## Main features

- 📄 Automatic PDF responsibility documents for Computers, Printers and Phones.
- 🧾 Phone loan contracts with configurable legal clauses.
- 🧭 **Visual inspection forms** and **Return forms** for Computers, Printers and Phones.
- 🖨️ Fixed manual forms designed for **one Letter-size page**.
- ✍️ Physical-condition information is intended to be completed **by hand**.
- 🖼️ Approved PNG schematics for Computers, Printers and Phones.
- 📝 Compact damage guidance for **scratches, impacts/dents, wear/use and missing parts/other**.
- 👤 Manual forms prefill the real asset identification and assigned-user information from GLPI.
- 👷 Manual-form signatures use the **technician assigned to the asset**; when none is assigned, the document uses the GLPI user generating it.
- 🔢 Phone manual forms include the asset/line identification used by the normal phone responsibility.
- 🧩 Each asset type has independent controls for enabling/disabling **Visual inspection** and **Return**.
- 👁️ Configuration previews are available for the normal responsibility and for each enabled manual form.
- 🖊️ Each manual form has its own editable **title, Instructions and four footer fields**.
- 📬 Selective responsibility-document email sending.
- 💾 Validated configuration export/import, including the institutional logo.
- 🖼️ JPG/PNG institutional logo support, including transparent PNG preservation.
- 🔐 GLPI permission and CSRF protections.
- 🌍 Spanish (Mexico), French, German and Italian translations.

## Requirements

| Component | Minimum |
|-----------|---------|
| GLPI | 11.0.0 / 12.x |
| PHP | 8.2 |
| TCPDF | Included with GLPI |
| PHP extensions | `fileinfo`, `gd`, `intl`, `json` |

## Installation

1. Download the release ZIP.
2. Extract it into the GLPI plugins directory:
   ```text
   /var/www/glpi/plugins/responsivas/
   ```
3. Open **Setup → Plugins**.
4. Install **Responsivas**.
5. Enable the plugin.
6. Open the Responsivas configuration and review the templates, document options, manual forms and email settings.

On installation or update, the plugin clears the plugin-related Twig/locale cache required by its templates and translations.

## Manual visual inspection and return forms

The manual forms are independent printable documents. They are **not an additional page automatically appended to the normal responsibility PDF**.

Each asset type provides two independent formats:

| Asset type | Visual inspection | Return |
|-----------|:-----------------:|:------:|
| Computer | Yes | Yes |
| Printer | Yes | Yes |
| Phone | Yes | Yes |

A format can be enabled or disabled separately. When a format is disabled, its action is not available on the user page and configuration preview.

### What the manual forms do

When a manual form is generated for a specific asset, the PDF pre-fills the information that can be obtained from GLPI, such as:

- Asset identification.
- Assigned user.
- Brand.
- Model.
- Serial/UUID, where applicable.
- Asset type and condition.
- Computer hardware properties according to the Computer responsibility.
- Computer manual forms include monitors and peripherals associated with the computer, using the same visibility rules as the standard computer responsibility. The associated-device table uses a compact adaptive layout to keep the form suitable for a single Letter-size sheet.
- Printer properties according to the Printer responsibility.
- Phone properties such as storage, RAM, IMEI and line.
- The associated-device table uses **six columns**, separating **Serial** and **Asset** into independent columns for clearer identification.
- The main Computer table also uses a six-column layout, with **Asset** and **Identification (Name)** represented separately from the hardware fields.
- The redundant **Assigned to** line is not repeated in the forms because the assigned user is already identified in the user/signature area.
- Printer and Phone forms show the asset identification and the corresponding **Printer Identification** or **Phone Identification** using the asset **Name** from GLPI.

The physical inspection itself remains a paper workflow. The technician records the physical condition manually on the PDF.

### Condition and damage areas

The form includes:

1. **Condition** — Excellent, Good, Fair or Damaged.
2. **Visual condition map** — The corresponding approved asset schematic.
3. **Instructions** — The configured instructions for that specific manual format.
4. **Damage guidance** — Small icons and concise text for:
   - scratches;
   - impacts/dents;
   - wear/use;
   - missing parts/other.
5. **Additional notes** — Blank area for handwritten observations.
6. **Signatures** — Technician and assigned user.

The condition and notes areas are intentionally optimized for printing. Checkbox and notes-box borders use increased line weight to remain visible on paper.

### Manual-form templates

For each of the three asset types and for each movement (Visual inspection and Return), the configuration provides:

- Title.
- Instructions.
- Four footer positions.

These settings are independent from the normal responsibility template.

### Configuration preview behavior

Under each asset type, the preview area contains:

- **Preview of responsibility**.
- **Preview of visual inspection** — shown only when Visual inspection is enabled.
- **Preview of return** — shown only when Return is enabled.

Manual previews use representative data so they can be tested from configuration even when the administrator has no assigned asset of that type.

### Multiple assets of the same type

Manual forms support the same per-asset concept used by the normal responsibilities.

For example, if a user has:

```text
Computer EC-0001
Computer EC-0002
Computer EC-0003
```

the manual form can be generated for each specific asset and the asset information is taken from that asset's GLPI record.

The configuration preview is different: it is only a template demonstration and therefore uses representative data.

### Signatures

For the manual forms, the technician is resolved in this order:

1. Technician assigned to the asset, when available.
2. User generating the document, when no technician is assigned.

The assigned user is shown as the recipient of the asset.

The physical inspection/return markings written on paper are not intended to modify the asset assignment.

## Configuration

### PDF font and text-size recommendation

For best visual consistency in generated PDFs, it is recommended to configure **Helvetica** as the PDF font in GLPI.

For editable text fields in Responsivas, a **9 pt or 10 pt** font size is recommended. These sizes provide a good balance between readability and available space, especially in the manual inspection and return formats.

Open **Setup → Plugins → Responsivas**.

### General

Configure:

| Setting | Purpose |
|--------|---------|
| Company name | Company/institution name used by templates |
| Timezone | Document date and time |
| Employee number | Show or hide the employee number |
| QR code | Show or hide the asset QR code |
| PDF compression | Enable/disable PDF compression |
| PDF protection | Enable/disable copy/edit restrictions |
| Watermark text | Text used by PDF previews |
| Watermark opacity | Preview watermark opacity |
| Currency | Currency symbol used by supported price fields |
| Institutional logo | JPG/PNG used in document headers |

PNG logos keep transparency when the source file contains an alpha channel.

### Witnesses

Configure:

- Witness 1.
- Witness 2.
- Legal representative.
- Phone asset type.

### Templates

Computer, Printer and Phone templates are configured separately.

Depending on the asset type, the plugin provides:

- Document title.
- Introduction/opening paragraph.
- Body/clauses.
- Witness paragraph, where applicable.
- Useful-life paragraphs for Computer and Phone.
- Footer fields.
- Font size.
- Optional lender/borrower signatures for Computer and Printer responsibilities.

The manual inspection and Return templates are configured separately below the normal document template area.

## Template formatting

Editable text supports:

| Syntax | Result |
|--------|--------|
| `**text**` | **Bold** |
| `*text*` | *Italic* |
| `__text__` | Underline |

Formatting can be combined:

```text
*__**text**__*
```

The B/I/U toolbar can wrap or remove the corresponding markers.

## Available variables

Common variables include:

| Variable | Description |
|----------|-------------|
| `{nombre}` | Assigned user full name |
| `{empresa}` | Company name |
| `{activo}` | Asset identifier/tag |
| `{fecha}` | Localized document date |
| `{hora}` | Document time |
| `{lugar}` | City, State, Country from the active GLPI entity |
| `{representante}` | Legal representative |
| `{marca}` | Asset brand |
| `{modelo}` | Asset model |
| `{serie}` / `{serie_uuid}` | Serial / UUID |
| `{estado}` | Asset status/condition |
| `{direccion}` | Entity address |
| `{cp}` | Entity postal code |

Phone-specific variables include `{imei}`, `{linea}`, `{almacenamiento}` and `{ram}`.

Computer/Phone price variables include `{precio}`.

Computer/Phone useful-life templates support `{fecha_compra}`, `{factura}`, `{proveedor}` and `{clausula_vida_util}`.

## Useful-life paragraphs

Computer and Phone templates support two optional useful-life paragraphs:

- **With invoice** — used when invoice/supplier information exists.
- **Without invoice** — used when those data are not available.

The paragraph is inserted into the configured document body. It is not created as a new numbered clause.

When the applicable template is empty, no paragraph is inserted.

`{fecha_compra}` uses the same localized long-date style as `{fecha}`.

## Location validation

Before generating a preview or a real responsibility document, the active GLPI entity must have:

- **City**
- **State**

Country is optional.

This prevents documents from being generated with incomplete location information.

## Sending documents

From a user's **Responsivas** tab:

1. Review the assigned assets.
2. Click **Send responsibility documents**.
3. Select the asset types to include.
4. Confirm.

The plugin generates documents for the selected asset types and sends them to the user's registered email address.

## Permissions

| Action | GLPI right |
|--------|------------|
| View Responsivas tab | `user` → READ |
| Generate/send documents | `user` → READ |
| Access configuration | `config` → UPDATE |

## Configuration backup and import

The General configuration includes administrator-only JSON export/import.

The backup includes configuration values, templates, footer settings, selected users/reference values and the current logo.

Imports validate the format, allowed fields and reference data before applying the configuration. When user/object IDs differ between GLPI installations, the plugin can use stable names to resolve compatible references.

## Modern GLPI architecture

Responsivas uses:

- PSR-4 classes under `src/`.
- Symfony/GLPI Controllers with route attributes under `src/Controller/`.
- Twig configuration/presentation templates.
- Centralized services for configuration, mail and update checks.
- Centralized PDF generation.
- Versioned configuration migration.

The plugin keeps compatibility declarations for both GLPI 11.x and GLPI 12.x.

## File structure

```text
responsivas/
├── src/
│   ├── Controller/
│   ├── Exception/
│   ├── Pdf/
│   ├── Service/
│   ├── Generator.php
│   ├── Paths.php
│   ├── Twig.php
│   ├── UserTab.php
│   └── Utils.php
├── locales/
├── CHANGELOG.md
├── hook.php
├── LICENSE
├── logo.png
├── plugin.xml
├── README.md
└── setup.php
```
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
  <a href="https://github.com/glpi-project/glpi" target="_blank"><img src="https://img.shields.io/badge/GLPI-11.0%2B-blue" alt="GLPI 11 compatibility"></a>
  <a href="https://github.com/glpi-project/glpi" target="_blank"><img src="https://img.shields.io/badge/GLPI-12.0%2B-blue" alt="GLPI 12 compatibility"></a>
  <a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank"><img src="https://img.shields.io/badge/License-GPL%20v3%2B-green" alt="License"></a>
  <a href="https://php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-%3E%3D8.2-purple" alt="PHP"></a>
  <a href="https://github.com/monta990/responsivas/releases" target="_blank"><img alt="GitHub Downloads (all assets, all releases)" src="https://img.shields.io/github/downloads/monta990/responsivas/total"></a>
</p>

---

## Descripción

**Responsivas** genera cartas responsivas y contratos de comodato en PDF para activos de TI asignados a usuarios de GLPI. También proporciona **formatos fijos de inspección visual y devolución** para Computadoras, Impresoras y Teléfonos.

El plugin utiliza la misma base de código para **GLPI 11.x y GLPI 12.x** y sigue la arquitectura moderna de plugins con clases PSR-4, Controllers Symfony/GLPI y plantillas Twig.

## Características principales

- 📄 Generación automática de cartas responsivas para Computadoras, Impresoras y Teléfonos.
- 🧾 Contratos de comodato para teléfonos con cláusulas configurables.
- 🧭 **Formatos de inspección visual** y **devolución** para Computadoras, Impresoras y Teléfonos.
- 🖨️ Formatos manuales fijos diseñados para **una sola hoja tamaño Carta**.
- ✍️ La condición física está diseñada para ser **llenada a mano**.
- 🖼️ Esquemas PNG aprobados para Computadoras, Teléfonos e Impresoras.
- 📝 Instrucciones compactas para **rayones, golpes/abolladuras, desgaste/uso y piezas faltantes/otros**.
- 👤 Los formatos manuales prellenan la identificación real del activo y del usuario asignado desde GLPI.
- 👷 La firma del formato manual utiliza al **técnico asignado al activo**; si no existe, utiliza al usuario de GLPI que genera el documento.
- 🔢 Los formatos manuales de teléfono incluyen la información de línea e identificación utilizada por la responsiva normal.
- 🧩 Cada tipo de activo tiene controles independientes para activar/desactivar **Inspección visual** y **Devolución**.
- 👁️ Las vistas previas de configuración están disponibles para la responsiva normal y para cada formato manual habilitado.
- 🖊️ Cada formato manual tiene su propio **título, Instrucciones y cuatro campos de pie de página** editables.
- 📬 Envío selectivo de documentos por correo.
- 💾 Exportación/importación validada de la configuración y del logo institucional.
- 🖼️ Soporte de logo JPG/PNG, incluida la conservación de transparencia en PNG.
- 🔐 Protección mediante permisos de GLPI y CSRF.
- 🌍 Idiomas: Español (México), Francés, Alemán e Italiano.

## Requisitos

| Componente | Mínimo |
|-----------|--------|
| GLPI | 11.0.0 / 12.x |
| PHP | 8.2 |
| TCPDF | Incluido con GLPI |
| Extensiones PHP | `fileinfo`, `gd`, `intl`, `json` |

## Instalación

1. Descarga el ZIP de la versión publicada.
2. Descomprímelo en el directorio de plugins de GLPI:
   ```text
   /var/www/glpi/plugins/responsivas/
   ```
3. Abre **Configuración → Complementos**.
4. Instala **Responsivas**.
5. Activa el plugin.
6. Abre la configuración de Responsivas y revisa las plantillas, opciones del documento, formatos manuales y correo.

Durante la instalación o actualización, el plugin limpia la caché Twig/locales necesaria para que las plantillas y traducciones actualizadas se carguen correctamente.

## Formatos de inspección visual y devolución

Los formatos manuales son documentos independientes listos para imprimir. **No son una segunda página que se agregue automáticamente a la carta responsiva normal**.

Cada tipo de activo tiene dos formatos independientes:

| Tipo de activo | Inspección visual | Devolución |
|-----------|:-----------------:|:------:|
| Computadora | Sí | Sí |
| Impresora | Sí | Sí |
| Teléfono | Sí | Sí |

Cada formato se puede activar o desactivar por separado. Cuando un formato está deshabilitado, su acción no aparece en la página del usuario ni en la vista previa de configuración.

### ¿Cómo funcionan?

Al generar el formato manual para un activo específico, el PDF prellena la información que GLPI tiene disponible, por ejemplo:

- Identificación del activo.
- Usuario asignado.
- Marca.
- Modelo.
- Serie/UUID, cuando corresponde.
- Tipo y estado del activo.
- Propiedades de hardware de la computadora conforme a su responsiva.
- Los formatos manuales de computadora incluyen los monitores y periféricos asociados al equipo, usando las mismas reglas de visibilidad que la responsiva normal. La tabla de dispositivos asociados utiliza un diseño compacto y adaptativo para mantener el formato en una sola hoja tamaño Carta.
- Propiedades de la impresora conforme a su responsiva.
- Datos del teléfono como almacenamiento, RAM, IMEI y línea.

La inspección física se mantiene como un proceso en papel. El técnico registra a mano la condición física del equipo.

### Áreas del formato

El documento contiene:

1. **Condición** — Excelente, Bueno, Regular o Dañado.
2. **Esquema de condición visual** — El esquema correspondiente al tipo de activo.
3. **Instrucciones** — Las instrucciones configuradas para ese formato.
4. **Guía de daños** — Iconos pequeños y texto conciso para:
   - rayones;
   - golpes/abolladuras;
   - desgaste/uso;
   - piezas faltantes/otros.
5. **Notas adicionales** — Área libre para observaciones a mano.
6. **Firmas** — Técnico y usuario asignado.

Los bordes de los cuadros de condición y de notas están reforzados para que sean claramente visibles al imprimir.

### Plantillas de formatos manuales

Para cada tipo de activo y para cada movimiento (Inspección visual y Devolución), la configuración permite editar:

- Título.
- Instrucciones.
- Cuatro posiciones del pie de página.

Estas configuraciones son independientes de la plantilla de la carta responsiva normal.

### Vistas previas

En cada tipo de activo, el área de vistas previas contiene:

- **Vista previa de responsiva**.
- **Vista previa de inspección visual** — aparece solo cuando está habilitada.
- **Vista previa de devolución** — aparece solo cuando está habilitada.

Las vistas previas de formatos manuales utilizan datos de demostración, por lo que pueden probarse desde configuración incluso cuando el administrador no tiene activos asignados.

### Varios activos del mismo tipo

Los formatos manuales funcionan por activo, igual que las responsivas normales.

Por ejemplo, si un usuario tiene:

```text
Computadora EC-0001
Computadora EC-0002
Computadora EC-0003
```

el formato manual puede generarse para cada activo específico y toma la información del registro correspondiente de GLPI.

La vista previa de configuración es diferente: solamente demuestra la plantilla y por eso usa datos de demostración.

### Firmas

El técnico del formato manual se determina en este orden:

1. Técnico asignado al activo, cuando existe.
2. Usuario que genera el documento, cuando no existe técnico asignado.

El usuario asignado al activo aparece como destinatario.

Las anotaciones físicas realizadas a mano no están destinadas a modificar la asignación del activo en GLPI.

## Configuración

### Recomendación de fuente y tamaño de texto para PDF

Para obtener una mejor consistencia visual en los PDFs generados, se recomienda configurar **Helvetica** como fuente para PDF en GLPI.

Para los campos de texto editables de Responsivas, se recomienda utilizar un tamaño de fuente de **9 pt o 10 pt**. Estos tamaños ofrecen un buen equilibrio entre legibilidad y espacio disponible, especialmente en los formatos manuales de inspección y devolución.

Abre **Configuración → Complementos → Responsivas**.

### General

Configura:

| Ajuste | Función |
|--------|---------|
| Nombre de la empresa | Nombre utilizado por las plantillas |
| Zona horaria | Fecha y hora de los documentos |
| Número de empleado | Mostrar/ocultar número de empleado |
| Código QR | Mostrar/ocultar QR |
| Compresión PDF | Activar/desactivar compresión |
| Protección PDF | Activar/desactivar restricciones de copia/edición |
| Texto de marca de agua | Texto de las vistas previas |
| Opacidad | Opacidad de la marca de agua |
| Moneda | Símbolo utilizado por variables de precio |
| Logo institucional | JPG/PNG utilizado en el encabezado |

Los logos PNG conservan la transparencia cuando el archivo contiene canal alfa.

### Testigos

Configura:

- Testigo 1.
- Testigo 2.
- Representante legal.
- Tipo de teléfono.

### Plantillas

Las plantillas de Computadora, Impresora y Teléfono se configuran por separado.

Según el tipo de activo, se pueden configurar:

- Título del documento.
- Introducción/párrafo inicial.
- Cuerpo/cláusulas.
- Párrafo de testigos, cuando corresponda.
- Cláusulas de vida útil para Computadora y Teléfono.
- Campos del pie de página.
- Tamaño de fuente.
- Firmas opcionales comodante/comodatario para Computadora e Impresora.

Los formatos manuales de inspección y devolución se configuran por separado, debajo de la sección de la plantilla normal del documento.

## Formato de texto

Los campos de texto admiten:

| Sintaxis | Resultado |
|----------|-----------|
| `**texto**` | **Negrita** |
| `*texto*` | *Cursiva* |
| `__texto__` | Subrayado |

Se pueden combinar:

```text
*__**texto**__*
```

La barra B/I/U permite agregar o quitar los marcadores.

## Variables disponibles

| Variable | Descripción |
|----------|-------------|
| `{nombre}` | Nombre completo del usuario asignado |
| `{empresa}` | Nombre de la empresa |
| `{activo}` | Identificador/número de activo |
| `{fecha}` | Fecha localizada del documento |
| `{hora}` | Hora del documento |
| `{lugar}` | Ciudad, Estado, País de la entidad activa |
| `{representante}` | Representante legal |
| `{marca}` | Marca del activo |
| `{modelo}` | Modelo del activo |
| `{serie}` / `{serie_uuid}` | Serie / UUID |
| `{estado}` | Estado/condición del activo |
| `{direccion}` | Dirección de la entidad |
| `{cp}` | Código postal |

Variables específicas de teléfono:

```text
{imei}
{linea}
{almacenamiento}
{ram}
```

Variables de precio:

```text
{precio}
```

Variables de vida útil para Computadora y Teléfono:

```text
{fecha_compra}
{factura}
{proveedor}
{clausula_vida_util}
```

## Cláusulas de vida útil

Computadora y Teléfono tienen dos plantillas opcionales:

- **Con factura** — cuando existe información de factura/proveedor.
- **Sin factura** — cuando no existe.

El párrafo se inserta dentro del cuerpo configurado. **No se crea una nueva cláusula numerada.**

Si la plantilla aplicable está vacía, no se inserta nada.

`{fecha_compra}` utiliza el mismo formato largo localizado de `{fecha}`.

## Validación de ubicación

Antes de generar una vista previa o una carta responsiva real, la entidad activa de GLPI debe tener:

- **Ciudad**
- **Estado**

El País es opcional.

Esto evita generar documentos con ubicación incompleta.

## Envío de documentos

Desde la pestaña **Responsivas** del usuario:

1. Revisa los activos asignados.
2. Haz clic en **Enviar documentos de responsiva**.
3. Selecciona los tipos de activos que deseas incluir.
4. Confirma.

El plugin genera los documentos de los tipos seleccionados y los envía al correo registrado del usuario.

## Permisos

| Acción | Permiso de GLPI |
|--------|-----------------|
| Ver pestaña Responsivas | `user` → LECTURA |
| Generar/enviar documentos | `user` → LECTURA |
| Acceder a configuración | `config` → MODIFICAR |

## Respaldo e importación de configuración

La pestaña General incluye exportación/importación JSON para administradores.

El respaldo incluye configuración, plantillas, pies de página, referencias seleccionadas y el logo institucional actual.

La importación valida formato, campos permitidos y referencias antes de aplicar los datos. Cuando los IDs cambian entre instalaciones de GLPI, el plugin puede resolver referencias mediante nombres estables.

## Arquitectura moderna de GLPI

Responsivas utiliza:

- Clases PSR-4 bajo `src/`.
- Controllers Symfony/GLPI con atributos de rutas en `src/Controller/`.
- Twig para configuración y presentación.
- Servicios centralizados para configuración, correo y actualizaciones.
- Generación de PDF centralizada.
- Migración versionada de configuración.

El plugin conserva las declaraciones de compatibilidad para GLPI 11.x y GLPI 12.x.

## Estructura de archivos

```text
responsivas/
├── src/
│   ├── Controller/
│   ├── Exception/
│   ├── Pdf/
│   ├── Service/
│   ├── Generator.php
│   ├── Paths.php
│   ├── Twig.php
│   ├── UserTab.php
│   └── Utils.php
├── locales/
├── CHANGELOG.md
├── hook.php
├── LICENSE
├── logo.png
├── plugin.xml
├── README.md
└── setup.php
```

---

## Changelog

Consulta [CHANGELOG.md](CHANGELOG.md).

---

## Autor

**Edwin Elias Alvarez** — [GitHub](https://github.com/monta990/responsivas)

---

## Comprame un cafe :)

Si te gusta mi trabajo, me puedes apoyar con una donación:

<a href="https://www.buymeacoffee.com/monta990" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/default-yellow.png" alt="Buy Me A Coffee" height="51px" width="210px"></a>

---

## Licencia

GPL v3 o posterior. Consulta [LICENSE](LICENSE).

---

## Problemas

Reporta errores o solicita funcionalidades en el [issue tracker](https://github.com/monta990/responsivas/issues).
