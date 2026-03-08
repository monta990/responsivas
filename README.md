# Responsivas — GLPI Plugin

[![GLPI](https://img.shields.io/badge/GLPI-11.x-blue)](https://glpi-project.org)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://php.net)
[![License](https://img.shields.io/badge/License-GPLv2%2B-green)](LICENSE)
[![Version](https://img.shields.io/badge/Version-1.2.2-orange)](CHANGELOG.md)

**Responsivas** is a GLPI plugin that automatically generates PDF responsibility documents (*cartas responsivas*) and loan contracts (*comodatos*) for IT assets assigned to users. Documents are sent directly to users via email as attachments.

---

## Features

- 📄 **Automatic PDF generation** for computers, printers, and mobile phones
- 📧 **Email delivery** — sends all PDFs as attachments to the assigned user's email
- 🖊️ **Fully customizable templates** — title, introduction, body/clauses, witnesses, footer, per asset type
- 🔢 **QR codes** on every document linking directly to the asset in GLPI
- 👷 **Legal witnesses and representative** — configurable GLPI users
- 📱 **Phone loan contracts** (*comodatos*) with full legal clause set
- 🌍 **Multi-language** — Spanish (Mexico), English, French, German
- 🔒 **CSRF protection** and GLPI permission model
- ⚙️ **Schema-versioned configuration** — safe migrations on plugin updates
- ✅ **Template validation** — warns before generating if required fields are empty

---

## Requirements

| Component | Minimum version |
|-----------|----------------|
| GLPI | 11.0 |
| PHP | 8.1 |
| TCPDF | included with GLPI |

> **Note:** GLPI 10.x is not officially supported. The plugin uses APIs introduced in GLPI 11.

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

## Configuration

Navigate to **Setup → Plugins → Responsivas** (or **Administration → Plugins → Responsivas Configuration**).

### General tab
| Field | Description |
|-------|-------------|
| Company name | Appears in document templates and email |
| Timezone | Used for document date/time |
| Show employee number | Toggle employee number display on PDFs |
| Show QR code | Toggle QR code on documents |
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
| Footer fields | Left/right text on PDF page footer |
| Font size | PDF body font size |

**Bold text in templates:** Use `**text**` syntax (like Markdown). HTML tags are not supported because GLPI sanitizes them.

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
| `{clausula_vida_util}` | *(Phone)* Auto-generated useful life clause |
| `{testigo1}` / `{testigo2}` | Witness names |
| `{direccion}` | Entity address |
| `{cp}` | Entity postal code |

### Email tab
| Field | Description |
|-------|-------------|
| Subject | Email subject line. Supports `{nombre}`, `{empresa}`, `{fecha}` |
| Body | Email body text. Supports `{nombre}`, `{empresa}`, `{fecha}` |
| Footer | Optional footer below a separator line |
| Test email button | Sends a test email to your own GLPI registered address |

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

### Downloading a document directly

From the user's **Responsivas** tab, you can also download each PDF directly without sending an email, using the download buttons next to each asset type.

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

**Test email button does nothing**
Make sure GLPI email notifications are enabled and you have a default email address configured in your own GLPI profile.

---

## Contributing

Pull requests are welcome. For major changes, please open an issue first.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes
4. Push to the branch
5. Open a Pull Request

---

## License

This plugin is released under the **GNU General Public License v2 or later (GPLv2+)**.  
See [LICENSE](LICENSE) for full terms.

---

## Author

**Edwin Elias Alvarez** — [Sontechs](https://sontechs.com)
