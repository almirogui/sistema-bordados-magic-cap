# Advanced Puncher System - Documentacao Completa

**Plugin WordPress para Gerenciamento de Pedidos de Digitalizacao de Bordados**

**Versao:** 3.1  
**Ultima Atualizacao:** Dezembro 2025  
**Autor:** Puncher.com

---

## Indice

1. [Visao Geral](#1-visao-geral)
2. [Estrutura de Arquivos](#2-estrutura-de-arquivos)
3. [Banco de Dados](#3-banco-de-dados)
4. [Roles de Usuario](#4-roles-de-usuario)
5. [Shortcodes](#5-shortcodes)
6. [Fluxo de Trabalho](#6-fluxo-de-trabalho)
7. [Sistema de Cobranca](#7-sistema-de-cobranca)
8. [AJAX Handlers](#8-ajax-handlers)
9. [Emails Automaticos](#9-emails-automaticos)
10. [Configuracao](#10-configuracao)

---

## 1. Visao Geral

Sistema completo para gerenciar pedidos de digitalizacao de bordados, desde a criacao do pedido pelo cliente ate a entrega final e cobranca. Inclui dashboards especificos para cada tipo de usuario, sistema de revisao, e geracao automatica de invoices.

### Principais Funcionalidades

- Criacao e acompanhamento de pedidos
- Atribuicao automatica ou manual de programadores
- Sistema de revisao de trabalhos
- Geracao de invoices em PDF
- Gestao de cobrancas por metodo de pagamento
- Notificacoes por email automaticas
- Upload multiplo de arquivos

---

## 2. Estrutura de Arquivos

```
sistema-bordados-v5-https-fix/
|
├── sistema-bordados-simples.php      # Arquivo principal do plugin
|
├── assets/
│   ├── bordados.css                  # CSS principal
│   ├── bordados-modules.css          # CSS modular
│   ├── bordados-main.js              # JavaScript principal
│   ├── bordados-revisor.js           # JS do painel revisor
│   ├── bordados-admin-manager.js     # JS do gerenciador admin
│   └── bordados-toast.js             # Sistema de notificacoes toast
|
├── includes/
│   ├── class-database.php            # Operacoes de banco de dados
│   ├── class-ajax.php                # Loader de handlers AJAX
│   ├── class-emails.php              # Sistema de emails
│   ├── class-shortcodes.php          # Shortcodes principais
│   ├── class-helpers.php             # Funcoes auxiliares
│   ├── class-cobranca.php            # Sistema de cobranca
│   ├── class-cobranca-pdf.php        # Geracao de PDFs
│   ├── class-puncher-auth.php        # Login/Registro customizado
│   ├── class-perfil-admin.php        # Perfil no admin WP
│   ├── class-shortcode-perfil.php    # Perfil do cliente (frontend)
│   ├── class-ajax-perfil.php         # AJAX do perfil
│   ├── class-atribuicao-automatica.php  # Logica de atribuicao
│   ├── class-programador-dashboard.php  # Dashboard programador v2
│   ├── class-programador-admin.php   # Admin de programadores
│   ├── class-widget-programadores.php   # Widget de carga
│   ├── class-cliente-atribuicao-auto.php # Config cliente
│   ├── class-hook-criacao-pedido.php # Hooks de criacao
│   ├── class-precos.php              # Tabela de precos
│   ├── bordados-admin-manager.php    # Gerenciador admin completo
│   |
│   ├── ajax/                         # Handlers AJAX modulares
│   │   ├── class-ajax-cliente.php    # Acoes do cliente
│   │   ├── class-ajax-admin.php      # Acoes do admin
│   │   ├── class-ajax-programador.php # Acoes do programador
│   │   ├── class-ajax-revisor.php    # Acoes do revisor
│   │   ├── class-ajax-edicao.php     # Edicao de pedidos
│   │   ├── class-ajax-orcamento.php  # Sistema de orcamento
│   │   └── class-ajax-auth.php       # Autenticacao
│   |
│   └── shortcodes/                   # Shortcodes modulares
│       ├── class-shortcode-meus-pedidos.php
│       ├── class-shortcode-novo-pedido.php
│       ├── class-shortcode-meus-trabalhos.php
│       ├── class-shortcode-admin-pedidos.php
│       ├── class-shortcode-painel-revisor.php
│       ├── class-shortcode-embaixador.php
│       └── class-shortcode-login.php
|
└── vendor/                           # Dependencias Composer (DOMPDF)
```

---

## 3. Banco de Dados

### 3.1 Tabela: `pedidos_basicos`

Tabela principal que armazena todos os pedidos/servicos.

| Campo | Tipo | Descricao |
|-------|------|-----------|
| `id` | INT(11) AUTO_INCREMENT | ID unico do pedido |
| `cliente_id` | INT(11) | ID do usuario cliente (wp_users) |
| `programador_id` | INT(11) NULL | ID do programador atribuido |
| `nome_bordado` | VARCHAR(255) | Nome/titulo do design |
| `tamanho` | VARCHAR(100) | Tamanho solicitado |
| `cores` | VARCHAR(50) NULL | Numero de cores |
| `observacoes` | TEXT NULL | Instrucoes do cliente |
| `arquivo_referencia` | VARCHAR(500) NULL | URL do arquivo principal (legado) |
| `arquivos_cliente` | TEXT NULL | JSON com URLs dos arquivos enviados |
| `status` | VARCHAR(50) | Status atual do pedido |
| `preco_programador` | DECIMAL(10,2) NULL | Preco informado pelo programador |
| `preco_final` | DECIMAL(10,2) NULL | Preco final aprovado pelo revisor |
| `observacoes_programador` | TEXT NULL | Comentarios do programador |
| `arquivos_finais` | TEXT NULL | JSON com URLs dos arquivos entregues |
| `arquivos_programador_original` | TEXT NULL | Backup dos arquivos antes da revisao |
| `obs_revisor` | TEXT NULL | Observacoes do revisor |
| `obs_revisor_aprovacao` | TEXT NULL | Obs na aprovacao final |
| `revisor_id` | INT NULL | ID do revisor |
| `numero_pontos` | INT NULL | Quantidade de pontos do bordado |
| `largura` | DECIMAL(10,2) NULL | Largura em polegadas |
| `altura` | DECIMAL(10,2) NULL | Altura em polegadas |
| `data_criacao` | DATETIME | Data de criacao do pedido |
| `data_atribuicao` | DATETIME NULL | Data de atribuicao ao programador |
| `data_inicio_producao` | DATETIME NULL | Quando iniciou a producao |
| `data_conclusao` | DATETIME NULL | Data de conclusao/entrega |
| `cobrado` | TINYINT(1) DEFAULT 0 | 0=nao cobrado, 1=cobrado |
| `data_cobranca` | DATETIME NULL | Quando foi cobrado |
| `cobrado_por` | INT NULL | ID do admin que cobrou |
| `invoice_number` | INT NULL | Numero do invoice gerado |

#### Status Possiveis

| Status | Descricao |
|--------|-----------|
| `novo` | Pedido criado, aguardando atribuicao |
| `atribuido` | Atribuido a um programador |
| `em_producao` | Programador trabalhando |
| `aguardando_revisao` | Entregue, aguardando revisor |
| `em_revisao` | Revisor analisando |
| `acertos_solicitados` | Revisor pediu correcoes |
| `pronto` | Aprovado e entregue ao cliente |

---

### 3.2 Tabela: `{prefix}_bordados_invoice_control`

Controle de numeracao sequencial de invoices.

| Campo | Tipo | Descricao |
|-------|------|-----------|
| `id` | INT AUTO_INCREMENT | ID |
| `ultimo_numero` | INT DEFAULT 3500 | Ultimo numero usado |
| `updated_at` | DATETIME | Ultima atualizacao |

---

### 3.3 Tabela: `{prefix}_bordados_config`

Configuracoes parametrizaveis da empresa.

| Campo | Tipo | Descricao |
|-------|------|-----------|
| `id` | INT AUTO_INCREMENT | ID |
| `config_key` | VARCHAR(100) UNIQUE | Chave da configuracao |
| `config_value` | TEXT | Valor |
| `updated_at` | DATETIME | Ultima atualizacao |

#### Configuracoes Padrao

| Chave | Valor Padrao |
|-------|--------------|
| `empresa_nome` | WWW.PUNCHER.COM |
| `empresa_endereco` | Rua Adao Augusto Gomes 815 |
| `empresa_cidade` | Caxambu MG BRAZIL |
| `empresa_email` | puncher@puncher.com |
| `empresa_telefone` | (vazio) |
| `invoice_notas` | Notas legais do invoice |
| `invoice_rodape` | www.puncher.com - Magic Cap Embroidery - Since 1993 |

---

### 3.4 Tabela: `bordados_tabela_precos`

Tabela de precos por faixa de pontos.

| Campo | Tipo | Descricao |
|-------|------|-----------|
| `id` | INT AUTO_INCREMENT | ID |
| `pontos_min` | INT | Minimo de pontos |
| `pontos_max` | INT | Maximo de pontos |
| `preco` | DECIMAL(10,2) | Preco para esta faixa |
| `ativo` | TINYINT(1) | Se esta ativo |

---

### 3.5 Campos de Usuario (wp_usermeta)

#### Dados Pessoais

| Meta Key | Descricao |
|----------|-----------|
| `first_name` | Primeiro nome |
| `last_name` | Sobrenome |
| `titulo_cliente` | Mr./Mrs./Ms. |
| `apelido_cliente` | Apelido/nickname |
| `email_secundario` | Email alternativo |
| `email_invoice` | Email para invoices |
| `telefone_whatsapp` | WhatsApp |
| `cpf_cnpj` | Documento |
| `data_nascimento` | Data de nascimento |

#### Endereco

| Meta Key | Descricao |
|----------|-----------|
| `pais` | Codigo do pais (US, BR, etc) |
| `cep` | CEP/ZIP code |
| `endereco_rua` | Rua/Logradouro |
| `endereco_numero` | Numero |
| `endereco_complemento` | Complemento |
| `endereco_bairro` | Bairro |
| `endereco_cidade` | Cidade |
| `endereco_estado` | Estado/Provincia |

#### Dados Empresariais

| Meta Key | Descricao |
|----------|-----------|
| `razao_social` | Razao social da empresa |
| `nome_fantasia` | Nome fantasia |
| `cnpj_empresa` | CNPJ |

#### Preferencias de Trabalho

| Meta Key | Descricao |
|----------|-----------|
| `formato_arquivo_preferido` | Formato de arquivo (DST, PES, etc) |
| `unidade_medida_preferida` | in (polegadas) ou cm |
| `maquina_bordar` | Modelo da maquina |
| `obs_para_programador` | Instrucoes padrao |
| `programador_padrao` | ID do programador preferido |

#### Dados de Pagamento (Criptografados)

| Meta Key | Descricao |
|----------|-----------|
| `metodo_pagamento` | credit_card / paypal / bank_transfer |
| `card_brand` | Bandeira (visa, mastercard, etc) |
| `card_number` | Numero (criptografado AES-256) |
| `card_expiry` | Validade (criptografado) |
| `card_cvv` | CVV (criptografado) |
| `card_holder` | Nome no cartao (criptografado) |
| `paypal_email` | Email PayPal |
| `bank_details` | Dados bancarios |

---

## 4. Roles de Usuario

### 4.1 Cliente (`cliente_bordados`)

**Capacidades:**
- `read` - Acesso basico
- `ver_meus_pedidos` - Ver proprios pedidos
- `criar_pedido` - Criar novos pedidos
- `solicitar_edicao` - Solicitar alteracoes

**Acesso:**
- Dashboard de pedidos
- Criar novo pedido
- Perfil pessoal
- Download de arquivos finais

---

### 4.2 Programador (`programador_bordados`)

**Capacidades:**
- `read` - Acesso basico
- `ver_trabalhos_atribuidos` - Ver trabalhos designados
- `entregar_trabalho` - Fazer upload de arquivos finais
- `definir_preco` - Informar preco do trabalho

**Acesso:**
- Painel de trabalhos
- Iniciar/entregar producao
- Definir preco sugerido

---

### 4.3 Revisor (`revisor_bordados`)

**Capacidades:**
- `read` - Acesso basico
- `revisar_trabalhos` - Revisar trabalhos entregues
- `aprovar_trabalho` - Aprovar para cliente
- `solicitar_acertos` - Devolver para correcao
- `definir_preco_final` - Definir preco final

**Acesso:**
- Painel de revisao
- Aprovar/rejeitar trabalhos
- Substituir arquivos
- Definir preco final

---

### 4.4 Embaixador (`embaixador_bordados`)

**Capacidades:**
- `read` - Acesso basico
- `ver_indicados` - Ver clientes indicados
- `ver_comissoes` - Ver comissoes

**Acesso:**
- Dashboard de indicacoes
- Relatorio de comissoes

---

### 4.5 Administrador (`administrator`)

**Acesso total:**
- Todos os dashboards
- Atribuir pedidos
- Gerenciar usuarios
- Sistema de cobranca
- Configuracoes

---

## 5. Shortcodes

### Paginas Principais

| Shortcode | Funcao | Acesso |
|-----------|--------|--------|
| `[puncher_login]` | Formulario de login | Publico |
| `[puncher_register]` | Formulario de registro | Publico |
| `[bordados_meus_pedidos]` | Dashboard do cliente | Cliente/Admin |
| `[bordados_novo_pedido]` | Criar novo pedido | Cliente/Admin |
| `[bordados_perfil_cliente]` | Perfil do cliente | Cliente/Admin |
| `[bordados_meus_trabalhos]` | Dashboard programador | Programador/Admin |
| `[bordados_painel_programador_v2]` | Dashboard programador v2 | Programador/Admin |
| `[bordados_painel_revisor]` | Painel de revisao | Revisor/Admin |
| `[bordados_admin_pedidos]` | Gerenciamento admin | Admin |
| `[bordados_admin_cobranca]` | Sistema de cobranca | Admin |
| `[bordados_dashboard_embaixador]` | Dashboard embaixador | Embaixador/Admin |
| `[bordados_gerenciar_pedidos]` | Gerenciador completo | Admin |

### Paginas Recomendadas

| Pagina | Slug | Shortcode |
|--------|------|-----------|
| Login | `/login` | `[puncher_login]` |
| Registro | `/register` | `[puncher_register]` |
| Meus Pedidos | `/meus-pedidos` | `[bordados_meus_pedidos]` |
| Novo Pedido | `/novo-pedido` | `[bordados_novo_pedido]` |
| Meu Perfil | `/meu-perfil` | `[bordados_perfil_cliente]` |
| Painel Programador | `/painel-programador` | `[bordados_meus_trabalhos]` |
| Painel Revisor | `/painel-revisor` | `[bordados_painel_revisor]` |
| Admin Pedidos | `/admin-pedidos` | `[bordados_admin_pedidos]` |
| Cobranca | `/admin-cobranca` | `[bordados_admin_cobranca]` |

---

## 6. Fluxo de Trabalho

### 6.1 Fluxo Completo do Pedido

```
┌─────────────────┐
│  CLIENTE CRIA   │
│     PEDIDO      │
│  Status: NOVO   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ ADMIN/AUTO      │
│ ATRIBUI PROG.   │
│ Status:ATRIBUIDO│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  PROGRAMADOR    │
│ INICIA TRABALHO │
│Status:EM_PRODUC │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  PROGRAMADOR    │
│ ENTREGA ARQUIV. │
│Status:AG.REVISAO│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│    REVISOR      │
│ INICIA REVISAO  │
│Status:EM_REVISAO│
└────────┬────────┘
         │
    ┌────┴────┐
    ▼         ▼
┌───────┐ ┌───────────┐
│APROVA │ │ SOLICITA  │
│       │ │ ACERTOS   │
└───┬───┘ └─────┬─────┘
    │           │
    │     ┌─────┴─────┐
    │     │PROGRAMADOR│
    │     │ CORRIGE   │
    │     └─────┬─────┘
    │           │
    │     ┌─────┴─────┐
    │     │  ENTREGA  │
    │     │ NOVAMENTE │
    │     └─────┬─────┘
    │           │
    └─────┬─────┘
          │
          ▼
┌─────────────────┐
│   APROVADO!     │
│ Status: PRONTO  │
│ Email p/ Cliente│
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│    COBRANCA     │
│ (Admin gera     │
│  invoice/cobra) │
└─────────────────┘
```

### 6.2 Atribuicao Automatica

O sistema pode atribuir automaticamente pedidos:

1. **Programador Padrao do Cliente**: Se o cliente tem `programador_padrao` configurado
2. **Menor Carga**: Seleciona programador com menos trabalhos pendentes

---

## 7. Sistema de Cobranca

### 7.1 Visao Geral

O sistema de cobranca permite:
- Filtrar servicos por metodo de pagamento
- Gerar invoices individuais em PDF
- Gerar resumo de cobranca com dados do cartao
- Marcar servicos como cobrados
- Controle sequencial de numeracao de invoices

### 7.2 Fluxo de Cobranca

```
1. Admin acessa /admin-cobranca
2. Seleciona metodo (Cartao/PayPal/Banco)
3. Sistema lista servicos PRONTOS e NAO COBRADOS
4. Admin seleciona servicos
5. Gera Invoices (PDF por cliente)
6. Envia invoices para clientes
7. Apos confirmacao, gera Cobranca Resumo
8. Processa cobrancas externamente
9. Marca servicos como cobrados no sistema
```

### 7.3 Invoice do Cliente (PDF)

Conteudo:
- Cabecalho com dados da empresa
- Dados do cliente (razao social, endereco, emails)
- Numero do invoice (sequencial)
- Metodo de pagamento
- Lista de servicos com:
  - Data do pedido/entrega
  - Nome do design
  - Imagem do bordado
  - Numero de pontos
  - Dimensoes
  - Preco
- Totais e estatisticas
- Notas legais

### 7.4 Cobranca Resumo (PDF Interno)

Conteudo:
- Agrupado por bandeira de cartao
- Para cada cliente:
  - Numero completo do cartao
  - Validade
  - CVV
  - Nome no cartao
  - Total em USD
  - Total em BRL (convertido pela cotacao informada)
- Subtotais por bandeira
- Total geral

---

## 8. AJAX Handlers

### 8.1 Autenticacao

| Action | Funcao |
|--------|--------|
| `puncher_do_login` | Login de usuario |
| `puncher_do_register` | Registro de novo usuario |

### 8.2 Cliente

| Action | Funcao |
|--------|--------|
| `bordados_criar_pedido` | Criar novo pedido |
| `bordados_baixar_arquivo` | Download de arquivo |
| `salvar_perfil_cliente` | Salvar dados do perfil |
| `salvar_cartao_cliente` | Salvar dados do cartao |

### 8.3 Programador

| Action | Funcao |
|--------|--------|
| `bordados_iniciar_producao` | Mudar status para em_producao |
| `bordados_entregar_trabalho` | Upload de arquivos finais |

### 8.4 Revisor

| Action | Funcao |
|--------|--------|
| `bordados_iniciar_revisao` | Iniciar revisao |
| `bordados_aprovar_trabalho` | Aprovar trabalho |
| `bordados_aprovar_com_arquivos` | Aprovar substituindo arquivos |
| `bordados_solicitar_acertos` | Devolver para correcao |

### 8.5 Admin

| Action | Funcao |
|--------|--------|
| `bordados_atribuir_programador` | Atribuir pedido |
| `bordados_deletar_pedido` | Excluir pedido |
| `bordados_deletar_multiplos` | Excluir varios |
| `bordados_buscar_detalhes_pedido` | Obter detalhes |

### 8.6 Cobranca

| Action | Funcao |
|--------|--------|
| `bordados_get_servicos_cobranca` | Listar servicos para cobrar |
| `bordados_gerar_invoices` | Gerar PDFs de invoice |
| `bordados_gerar_cobranca_resumo` | Gerar PDF resumo |
| `bordados_marcar_cobrados` | Marcar como cobrados |

---

## 9. Emails Automaticos

### 9.1 Emails Enviados

| Evento | Destinatario | Conteudo |
|--------|--------------|----------|
| Novo pedido | Programador | Detalhes do pedido |
| Producao iniciada | Cliente | Notificacao de inicio |
| Trabalho entregue | Revisor/Cliente | Notificacao de entrega |
| Aprovado | Cliente | Links para download |
| Acertos solicitados | Programador | Instrucoes de correcao |

### 9.2 Configuracao de Email

O sistema usa `wp_mail()` do WordPress. Recomenda-se usar plugin SMTP para maior confiabilidade (ex: WP Mail SMTP).

---

## 10. Configuracao

### 10.1 Constantes (wp-config.php)

```php
// Chave de criptografia para dados de cartao
define('PUNCHER_CARD_KEY', 'sua-chave-secreta-aqui');
```

### 10.2 Dependencias

- **DOMPDF**: Instalado via Composer para geracao de PDFs
  ```bash
  cd wp-content/plugins/sistema-bordados-v5-https-fix
  composer require dompdf/dompdf
  ```

### 10.3 Permissoes de Diretorio

Verificar permissoes de escrita em:
- `wp-content/uploads/bordados/` - Arquivos de clientes
- `wp-content/uploads/bordados-invoices/` - PDFs gerados

### 10.4 Cache

Apos atualizacoes de arquivos PHP:
1. Limpar cache do servidor (Purge SG Cache no SiteGround)
2. Limpar cache do navegador (Ctrl+Shift+R)

---

## Changelog

### v3.1 (Dezembro 2025)
- Adicionado sistema de cobranca completo
- Geracao de invoices em PDF
- Geracao de resumo de cobranca
- Controle de numeracao de invoices
- Novos campos: cobrado, data_cobranca, cobrado_por, invoice_number
- Novas tabelas: bordados_invoice_control, bordados_config

### v3.0 (Dezembro 2025)
- Sistema de perfil do cliente
- Dados de pagamento criptografados
- Login/registro customizado
- Dashboard embaixador

### v2.x
- Sistema de revisao
- Atribuicao automatica
- Dashboard programador v2

---

## Suporte

Para suporte tecnico, consulte esta documentacao ou entre em contato com o desenvolvedor.

**Puncher.com - Magic Cap Embroidery - Since 1993**
