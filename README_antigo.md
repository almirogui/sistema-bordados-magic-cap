# Advanced Puncher System

**WordPress Plugin for Embroidery Digitizing Order Management**

Version: 3.1  
Last Update: December 2025  
Author: Puncher.com

---

## 📋 Overview

A complete system for managing embroidery digitizing orders from creation to final delivery. Includes role-specific dashboards for clients, digitizers, reviewers, and administrators with automated workflow and email notifications.

### User Roles

| Role | Slug | Description |
|------|------|-------------|
| Client | `cliente_bordados` | Creates orders, tracks status, downloads files |
| Digitizer | `programador_bordados` | Receives orders, produces embroidery files, delivers work |
| Reviewer | `revisor_bordados` | Reviews work before final delivery, sends quotes |
| Ambassador | `embaixador_bordados` | Referrals and commissions |
| Administrator | `administrator` | Full system access |

---

## 📁 File Structure

```
sistema-bordados-simples/
│
├── sistema-bordados-simples.php     # Main plugin file
│
├── assets/
│   ├── bordados.css                 # Main CSS
│   ├── bordados-modules.css         # Modular CSS (Phase 2)
│   ├── bordados-main.js             # Main JavaScript
│   ├── bordados-revisor.js          # Reviewer JS (Phase 1)
│   ├── bordados-admin-manager.js    # Admin manager JS (Phase 1)
│   └── bordados-toast.js            # Toast notifications
│
└── includes/
    │
    ├── class-ajax.php               # AJAX Loader (Phase 4)
    ├── ajax/                        # AJAX Modules
    │   ├── class-ajax-cliente.php
    │   ├── class-ajax-admin.php
    │   ├── class-ajax-programador.php
    │   ├── class-ajax-revisor.php
    │   ├── class-ajax-edicao.php
    │   ├── class-ajax-auth.php
    │   └── class-ajax-orcamento.php
    │
    ├── class-shortcodes.php         # Shortcode Loader (Phase 3)
    ├── shortcodes/                  # Shortcode Modules
    │   ├── class-shortcode-meus-pedidos.php
    │   ├── class-shortcode-meus-trabalhos.php
    │   ├── class-shortcode-admin-pedidos.php
    │   ├── class-shortcode-painel-revisor.php
    │   ├── class-shortcode-embaixador.php
    │   ├── class-shortcode-novo-pedido.php
    │   └── class-shortcode-login.php
    │
    ├── class-database.php           # Database operations
    ├── class-helpers.php            # Helper functions
    ├── class-emails.php             # Email system
    ├── class-precos.php             # Pricing system
    ├── class-shortcode-perfil.php   # Profile shortcode
    ├── class-ajax-perfil.php        # Profile AJAX handler
    ├── class-perfil-admin.php       # Admin profile fields
    ├── class-puncher-auth.php       # Login/Register pages
    ├── class-programador-dashboard.php
    ├── class-programador-admin.php
    ├── class-cliente-atribuicao-auto.php
    ├── class-atribuicao-automatica.php
    ├── class-hook-criacao-pedido.php
    ├── class-widget-programadores.php
    └── bordados-admin-manager.php
```

---

## 🔌 Available Shortcodes

| Shortcode | File | Recommended Page |
|-----------|------|------------------|
| `[puncher_login]` | class-puncher-auth.php | `/` or `/login/` |
| `[puncher_register]` | class-puncher-auth.php | `/register/` |
| `[bordados_meus_pedidos]` | shortcode-meus-pedidos.php | `/meus-pedidos/` |
| `[bordados_novo_pedido]` | shortcode-novo-pedido.php | `/novo-pedido/` |
| `[bordados_perfil_cliente]` | class-shortcode-perfil.php | `/meu-perfil/` |
| `[bordados_meus_trabalhos]` | shortcode-meus-trabalhos.php | `/painel-programador/` |
| `[bordados_admin_pedidos]` | shortcode-admin-pedidos.php | `/admin-pedidos/` |
| `[bordados_painel_revisor]` | shortcode-painel-revisor.php | `/painel-revisor/` |
| `[bordados_dashboard_embaixador]` | shortcode-embaixador.php | `/dashboard-embaixador/` |
| `[bordados_login]` | shortcode-login.php | `/area-cliente/` (legacy) |

---

## 🗄️ Database Schema

### Table: `pedidos_basicos`

| Field | Type | Description |
|-------|------|-------------|
| `id` | INT(11) | Auto-increment primary key |
| `cliente_id` | INT(11) | Client user ID (NOT NULL) |
| `programador_id` | INT(11) | Digitizer user ID |
| `revisor_id` | INT(11) | Reviewer user ID |
| `nome_bordado` | VARCHAR(255) | Order title (NOT NULL) |
| `tamanho` | VARCHAR(100) | Dimensions (NOT NULL) |
| `cores` | VARCHAR(50) | Number of colors |
| `observacoes` | TEXT | Client instructions |
| `arquivo_referencia` | VARCHAR(500) | Reference file path (legacy) |
| `arquivos_cliente` | TEXT | Client files (JSON array) |
| `status` | VARCHAR(50) | Order status (default: 'novo') |
| `preco_programador` | DECIMAL(10,2) | Price charged |
| `preco_final` | DECIMAL(10,2) | Final price to client |
| `preco_base_calculado` | DECIMAL(10,2) | Base calculated price |
| `ajuste_manual_preco` | DECIMAL(10,2) | Manual price adjustment |
| `motivo_ajuste_preco` | TEXT | Reason for adjustment |
| `numero_pontos` | INT | Stitch count |
| `sistema_preco_usado` | VARCHAR(50) | Pricing system used |
| `observacoes_programador` | TEXT | Digitizer notes |
| `obs_revisor` | TEXT | Reviewer notes |
| `arquivos_finais` | TEXT | Final delivery files (JSON array) |
| `tipo_pedido` | ENUM | 'original' or 'edicao' |
| `versao` | INT | Version number |
| `edicao_gratuita` | TINYINT | 1 = free edit, 0 = paid |
| `prazo_entrega` | VARCHAR(50) | Delivery deadline |
| `data_criacao` | DATETIME | Creation date (NOT NULL) |
| `data_atribuicao` | DATETIME | Assignment date |
| `data_inicio_producao` | DATETIME | Production start |
| `data_inicio_revisao` | DATETIME | Review start |
| `data_fim_revisao` | DATETIME | Review end |
| `data_conclusao` | DATETIME | Completion date |

### Order Status Values

| Status | Description |
|--------|-------------|
| `novo` | New order, waiting assignment |
| `atribuido` | Assigned to digitizer |
| `em_producao` | In production |
| `aguardando_revisao` | Waiting for reviewer |
| `em_revisao` | Being reviewed |
| `em_acertos` | Returned for corrections |
| `pronto` | Completed and delivered |
| `orcamento_pendente` | Quote pending |
| `orcamento_enviado` | Quote sent to client |
| `orcamento_recusado` | Quote declined by client |

---

## 👤 User Meta Fields

### Client Fields (`cliente_bordados`)

#### Personal Information
| Meta Key | Description | Example |
|----------|-------------|---------|
| `first_name` | First name | John |
| `last_name` | Last name | Doe |
| `titulo_cliente` | Title | Mr., Mrs., Ms., Dr. |
| `apelido_cliente` | Nickname / Trade name | JD Embroidery |
| `email_secundario` | Secondary email | backup@email.com |
| `email_invoice` | Invoice email | billing@company.com |
| `telefone_whatsapp` | Phone / WhatsApp | +1 (555) 123-4567 |
| `cpf_cnpj` | Tax ID / EIN (optional) | XX-XXXXXXX |
| `data_nascimento` | Date of birth | 1990-01-15 |

#### Address
| Meta Key | Description | Example |
|----------|-------------|---------|
| `pais` | Country code | US, CA, BR |
| `cep` | Zip / Postal code | 90210 |
| `endereco_rua` | Street address | 123 Main St |
| `endereco_numero` | Number | 456 |
| `endereco_complemento` | Apt / Suite | Suite 100 |
| `endereco_bairro` | Neighborhood / District | Downtown |
| `endereco_cidade` | City | Los Angeles |
| `endereco_estado` | State / Province | CA |

#### Company Information
| Meta Key | Description | Example |
|----------|-------------|---------|
| `razao_social` | Legal business name | ABC Embroidery LLC |
| `nome_fantasia` | Trade name / DBA | ABC Stitches |
| `cnpj_empresa` | Company Tax ID / EIN | XX-XXXXXXX |

#### Embroidery Preferences
| Meta Key | Description | Options/Example |
|----------|-------------|-----------------|
| `formato_arquivo_preferido` | Preferred file format | EMB, DST, PES, JEF, EXP, VP3, SEW, CSD, XXX |
| `unidade_medida_preferida` | Unit of measurement | in (inches), cm (centimeters) |
| `maquina_bordar` | Embroidery machine | Brother PR1050X, Tajima TEMX-C1201 |
| `obs_para_programador` | Notes for digitizer | "I prefer medium density" |

#### Administrative Fields (Admin Only)
| Meta Key | Description | Values |
|----------|-------------|--------|
| `cliente_bloqueado` | Client blocked | yes / no |
| `motivo_bloqueio` | Block reason (internal) | Text |
| `mensagem_bloqueio` | Block message (shown to client) | Text |
| `sistema_preco` | Pricing system | legacy_stitches, fixed_tier, multiplier, credits |
| `multiplicador_preco` | Price multiplier | 0.50 - 3.00 (default: 1.00) |
| `saldo_creditos` | Credit balance | Decimal |
| `nivel_dificuldade_padrao` | Default difficulty | facil, medio, complicado |
| `cliente_inativo` | Client inactive (CRM) | yes / no |
| `obs_admin` | Admin notes (private) | Text |
| `atribuicao_automatica` | Auto-assignment enabled | yes / no |
| `programador_preferido` | Preferred digitizer ID | User ID |

### Digitizer Fields (`programador_bordados`)

| Meta Key | Description | Values |
|----------|-------------|--------|
| `programador_ativo` | Digitizer active | yes / no (default: yes) |
| `programador_faz_vetorizacao` | Does vectorization | yes / no (default: no) |

---

## ⚡ AJAX Handlers

### Client (`class-ajax-cliente.php`)
- `criar_pedido` - Create new order
- `buscar_arquivos_pedido` - Get files for download

### Admin (`class-ajax-admin.php`)
- `atribuir_pedido` - Assign order to digitizer

### Digitizer (`class-ajax-programador.php`)
- `iniciar_producao` - Start production
- `finalizar_trabalho` - Complete and deliver work

### Reviewer (`class-ajax-revisor.php`)
- `iniciar_revisao` - Pick work for review
- `aprovar_trabalho` - Approve simple work
- `aprovar_trabalho_com_arquivos` - Approve with revised files
- `solicitar_acertos` - Request corrections

### Quote (`class-ajax-orcamento.php`)
- `enviar_orcamento` - Send quote to client
- `aprovar_orcamento` - Client approves quote
- `recusar_orcamento` - Client declines quote

### Editing (`class-ajax-edicao.php`)
- `solicitar_edicao` - Request edit of existing order
- `buscar_historico_versoes` - View version history
- `comparar_versoes` - Compare two versions

### Authentication (`class-puncher-auth.php`)
- `puncher_do_login` - User login
- `puncher_do_register` - User registration

---

## 🔄 System Workflow

```
1. CLIENT creates order
   ↓
2. ADMIN assigns to digitizer (or auto-assignment)
   ↓ [Email sent to digitizer]
3. DIGITIZER starts production
   ↓ [Email sent to client]
4. DIGITIZER completes work
   ↓
5. REVIEWER approves or requests corrections (optional)
   ↓ [Email sent]
6. CLIENT receives and downloads files
```

### Quote Flow (Optional)
```
1. CLIENT submits order → status: orcamento_pendente
   ↓
2. REVIEWER sends quote with price → status: orcamento_enviado
   ↓ [Email sent to client]
3a. CLIENT approves → status: novo (enters normal flow)
3b. CLIENT declines → status: orcamento_recusado
```

---

## 📧 Automatic Emails

| Event | Recipient | Description |
|-------|-----------|-------------|
| Order assigned | Digitizer | New work notification |
| Production started | Client | Status update |
| Work completed | Client | Download links |
| Corrections requested | Digitizer | Changes needed |
| Quote sent | Client | Price quote details |
| Quote approved | Admin | Client accepted quote |

---

## 💰 Pricing Systems

| System | Description |
|--------|-------------|
| `legacy_stitches` | Price per 1,000 stitches (old system) |
| `fixed_tier` | Fixed price by size + difficulty level |
| `multiplier` | Base price × multiplier (for resellers) |
| `credits` | Prepaid credit/package system |

---

## 🎨 CSS Files

### bordados.css
Main system styles:
- Order tables
- Status cards
- Buttons and forms
- Modals

### bordados-modules.css
Modular CSS extracted from PHP:
- `.bordados-hide-admin-bar`
- `.bordados-admin-form`
- `.bordados-programadores-table`
- `.bordados-perfil-container`
- `.bordados-dashboard-programador`

---

## 📜 JavaScript Files

### bordados-main.js
Main functions for the system.

### bordados-revisor.js
Reviewer and editing functions:
- Toast notifications
- Approval/rejection
- File upload
- Edit form

### bordados-admin-manager.js
Admin manager functions:
- Single/multiple deletion
- Order selection
- View modal
- Button protection

### bordados-toast.js
Toast notification system.

---

## 🛠️ Maintenance Guide

### Add New Shortcode
1. Create file in `includes/shortcodes/class-shortcode-NAME.php`
2. Follow existing class patterns
3. Add `require_once` in `class-shortcodes.php`
4. Add proxy method in `Bordados_Shortcodes` class
5. Register shortcode in main file

### Add New AJAX Handler
1. Create method in appropriate file in `includes/ajax/`
2. Add `add_action` in class constructor
3. Add proxy method in `class-ajax.php` (optional)

### Modify Styles
- General styles: `assets/bordados.css`
- Modular styles: `assets/bordados-modules.css`
- Avoid inline CSS in PHP files

### Modify JavaScript
- General functions: `assets/bordados-main.js`
- Reviewer functions: `assets/bordados-revisor.js`
- Admin functions: `assets/bordados-admin-manager.js`
- Avoid inline JS in PHP files

---

## 📝 WordPress Pages Setup

After installing the plugin, create these pages:

| Page Title | Slug | Shortcode | Access |
|------------|------|-----------|--------|
| Login | `login` | `[puncher_login]` | Public (homepage) |
| Create Account | `register` | `[puncher_register]` | Public |
| My Orders | `meus-pedidos` | `[bordados_meus_pedidos]` | Clients |
| New Order | `novo-pedido` | `[bordados_novo_pedido]` | Clients |
| My Profile | `meu-perfil` | `[bordados_perfil_cliente]` | Clients |
| Digitizer Panel | `painel-programador` | `[bordados_meus_trabalhos]` | Digitizers |
| Reviewer Panel | `painel-revisor` | `[bordados_painel_revisor]` | Reviewers |
| Admin Orders | `admin-pedidos` | `[bordados_admin_pedidos]` | Admins |
| Ambassador | `dashboard-embaixador` | `[bordados_dashboard_embaixador]` | Ambassadors |
| Billing | `admin-cobranca` | `[bordados_admin_cobranca]` | Admins |

---

## 💳 Billing System (v3.1)

### Overview

Complete billing management system for generating invoices and managing charges by payment method.

### Features

1. **Invoice Generation (PDF)**: Professional invoices with images, stitch counts, prices
2. **Billing Summary (PDF)**: Consolidated report with credit card data for processing
3. **USD to BRL Conversion**: Admin enters exchange rate for local currency conversion
4. **Automatic Invoice Numbering**: Sequential invoice numbers stored in database

### Database Tables

| Table | Purpose |
|-------|---------|
| `wp_bordados_invoice_control` | Stores last invoice number |
| `wp_bordados_config` | Company configuration (address, email, notes) |

### New Fields in `pedidos_basicos`

| Field | Type | Description |
|-------|------|-------------|
| `cobrado` | TINYINT(1) | 0 = not charged, 1 = charged |
| `data_cobranca` | DATETIME | When marked as charged |
| `cobrado_por` | INT | Admin ID who marked |
| `invoice_number` | INT | Invoice number assigned |

### Workflow

1. Admin accesses `/admin-cobranca/`
2. Selects payment method (Credit Card / PayPal / Bank Transfer)
3. System lists all completed services not yet charged
4. Admin generates Invoices (PDF per client) for client review
5. Admin enters USD exchange rate
6. Admin generates Billing Summary (PDF with card data)
7. Admin processes charges externally
8. Admin marks services as charged in system
9. Invoice counter updates automatically

### Configuration

Company data is stored in `wp_bordados_config`:

- `empresa_nome`: WWW.PUNCHER.COM
- `empresa_endereco`: Rua Adão Augusto Gomes 815
- `empresa_cidade`: Caxambu MG BRAZIL
- `empresa_email`: puncher@puncher.com

---

## ⚠️ Important Notes

1. **UTF-8 Encoding**: Always save PHP files as UTF-8 without BOM
2. **AJAX Nonce**: All handlers verify `bordados_nonce`
3. **Uploads**: Maximum 3 files per order (client and digitizer)
4. **Cache**: Clear browser cache after JS/CSS updates
5. **site_url()**: All internal links use `site_url()` for subdirectory compatibility

---

## 📞 Support

For technical support or questions about the system, refer to this documentation or contact the developer.

---

*Documentation updated December 2025*
