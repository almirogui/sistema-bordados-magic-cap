<?php
/**
 * Classe para gerenciamento de cobrancas e invoices
 * 
 * @package Sistema_Bordados
 * @since 3.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Cobranca {
    
    /**
     * Instancia singleton
     */
    private static $instance = null;
    
    /**
     * Dados da empresa (carregados do banco)
     */
    private $empresa_dados = null;
    
    /**
     * Construtor
     */
    public function __construct() {
        add_action('init', array($this, 'registrar_shortcodes'));
        add_action('wp_ajax_bordados_gerar_invoices', array($this, 'ajax_gerar_invoices'));
        add_action('wp_ajax_bordados_gerar_cobranca_resumo', array($this, 'ajax_gerar_cobranca_resumo'));
        add_action('wp_ajax_bordados_marcar_cobrados', array($this, 'ajax_marcar_cobrados'));
        add_action('wp_ajax_bordados_get_servicos_cobranca', array($this, 'ajax_get_servicos'));
    }
    
    /**
     * Singleton
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Registrar shortcodes
     */
    public function registrar_shortcodes() {
        add_shortcode('bordados_admin_cobranca', array($this, 'shortcode_admin_cobranca'));
    }
    
    /**
     * Criar tabelas necessarias
     */
    public static function criar_tabelas() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela de controle de invoice
        $table_invoice = $wpdb->prefix . 'bordados_invoice_control';
        $sql_invoice = "CREATE TABLE IF NOT EXISTS $table_invoice (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ultimo_numero INT DEFAULT 3500,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;";
        
        // Tabela de configuracao da empresa
        $table_config = $wpdb->prefix . 'bordados_config';
        $sql_config = "CREATE TABLE IF NOT EXISTS $table_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(100) NOT NULL UNIQUE,
            config_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_invoice);
        dbDelta($sql_config);
        
        // Inserir registro inicial se nao existir
        $existe = $wpdb->get_var("SELECT COUNT(*) FROM $table_invoice");
        if ($existe == 0) {
            $wpdb->insert($table_invoice, array('ultimo_numero' => 3500));
        }
        
        // Inserir configuracoes padrao da empresa
        self::inserir_config_padrao();
        
        // Adicionar campos a tabela pedidos_basicos
        self::adicionar_campos_cobranca();
    }
    
    /**
     * Inserir configuracoes padrao
     */
    private static function inserir_config_padrao() {
        global $wpdb;
        $table = $wpdb->prefix . 'bordados_config';
        
        $configs = array(
            'empresa_nome' => 'WWW.PUNCHER.COM',
            'empresa_endereco' => 'Rua Adao Augusto Gomes 815',
            'empresa_cidade' => 'Caxambu MG BRAZIL',
            'empresa_email' => 'puncher@puncher.com',
            'empresa_telefone' => '',
            'invoice_notas' => "PLEASE NOTE THE FOLLOWINGS:\nOur main company name is Magic Cap Puncher, so when you receive your credit card statement, that's the name it's going to be under.\n- When invoice is charged to your credit card it may not be the exactly value as assigned above. It can be a small amount higher or lower due Dollar fluctuation over the Brazilian currency (Real).\n- Magic Cap - Puncher is a company owned and directed by Mr. Almiro Almeida Guimaraes (aag@puncher.com) since 1993.",
            'invoice_rodape' => 'www.puncher.com - Magic Cap Embroidery - Since 1993'
        );
        
        foreach ($configs as $key => $value) {
            $existe = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE config_key = %s",
                $key
            ));
            
            if ($existe == 0) {
                $wpdb->insert($table, array(
                    'config_key' => $key,
                    'config_value' => $value
                ));
            }
        }
    }
    
    /**
     * Adicionar campos de cobranca a tabela pedidos_basicos
     */
    private static function adicionar_campos_cobranca() {
        global $wpdb;
        $table = 'pedidos_basicos';
        
        $columns = $wpdb->get_col("DESCRIBE $table");
        
        if (!in_array('cobrado', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN cobrado TINYINT(1) DEFAULT 0");
        }
        
        if (!in_array('data_cobranca', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN data_cobranca DATETIME NULL");
        }
        
        if (!in_array('cobrado_por', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN cobrado_por INT NULL");
        }
        
        if (!in_array('invoice_number', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN invoice_number INT NULL");
        }
    }
    
    /**
     * Obter proximo numero de invoice
     */
    public function get_proximo_invoice() {
        global $wpdb;
        $table = $wpdb->prefix . 'bordados_invoice_control';
        
        $ultimo = $wpdb->get_var("SELECT ultimo_numero FROM $table LIMIT 1");
        return intval($ultimo) + 1;
    }
    
    /**
     * Atualizar numero de invoice
     */
    public function atualizar_numero_invoice($novo_numero) {
        global $wpdb;
        $table = $wpdb->prefix . 'bordados_invoice_control';
        
        return $wpdb->update(
            $table,
            array('ultimo_numero' => $novo_numero),
            array('id' => 1)
        );
    }
    
    /**
     * Obter configuracao
     */
    public function get_config($key, $default = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'bordados_config';
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT config_value FROM $table WHERE config_key = %s",
            $key
        ));
        
        return $value !== null ? $value : $default;
    }
    
    /**
     * Obter dados da empresa
     */
    public function get_empresa_dados() {
        if ($this->empresa_dados === null) {
            $this->empresa_dados = array(
                'nome' => $this->get_config('empresa_nome', 'WWW.PUNCHER.COM'),
                'endereco' => $this->get_config('empresa_endereco', 'Rua Adao Augusto Gomes 815'),
                'cidade' => $this->get_config('empresa_cidade', 'Caxambu MG BRAZIL'),
                'email' => $this->get_config('empresa_email', 'puncher@puncher.com'),
                'telefone' => $this->get_config('empresa_telefone', ''),
                'notas' => $this->get_config('invoice_notas', ''),
                'rodape' => $this->get_config('invoice_rodape', 'www.puncher.com - Magic Cap Embroidery - Since 1993')
            );
        }
        return $this->empresa_dados;
    }
    
    /**
     * Buscar servicos nao cobrados por metodo de pagamento
     */
    public function buscar_servicos_nao_cobrados($metodo_pagamento = 'credit_card') {
        global $wpdb;
        
        // Buscar clientes com este metodo de pagamento
        $clientes_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'metodo_pagamento' AND meta_value = %s",
            $metodo_pagamento
        ));
        
        if (empty($clientes_ids)) {
            return array();
        }
        
        $ids_placeholder = implode(',', array_map('intval', $clientes_ids));
        
        // Buscar servicos prontos nao cobrados desses clientes
        $servicos = $wpdb->get_results("
            SELECT p.*, 
                   u.display_name as cliente_nome,
                   u.user_email as cliente_email
            FROM pedidos_basicos p
            JOIN {$wpdb->users} u ON p.cliente_id = u.ID
            WHERE p.status = 'pronto'
            AND p.cobrado = 0
            AND p.cliente_id IN ($ids_placeholder)
            ORDER BY u.display_name ASC, p.data_conclusao ASC
        ");
        
        // Agrupar por cliente
        $agrupado = array();
        foreach ($servicos as $servico) {
            $cliente_id = $servico->cliente_id;
            
            if (!isset($agrupado[$cliente_id])) {
                // Buscar dados completos do cliente
                $cliente_dados = $this->get_dados_cliente($cliente_id);
                
                $agrupado[$cliente_id] = array(
                    'cliente_id' => $cliente_id,
                    'cliente_nome' => $servico->cliente_nome,
                    'cliente_email' => $servico->cliente_email,
                    'dados_completos' => $cliente_dados,
                    'servicos' => array(),
                    'total' => 0
                );
            }
            
            // Usar preco_final se disponivel, senao preco_programador
            $preco = !empty($servico->preco_final) ? floatval($servico->preco_final) : floatval($servico->preco_programador);
            $servico->preco_exibir = $preco;
            
            $agrupado[$cliente_id]['servicos'][] = $servico;
            $agrupado[$cliente_id]['total'] += $preco;
        }
        
        return $agrupado;
    }
    
    /**
     * Obter dados completos do cliente
     */
    public function get_dados_cliente($cliente_id) {
        $user = get_userdata($cliente_id);
        if (!$user) {
            return null;
        }
        
        return array(
            'id' => $cliente_id,
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'first_name' => get_user_meta($cliente_id, 'first_name', true),
            'last_name' => get_user_meta($cliente_id, 'last_name', true),
            'razao_social' => get_user_meta($cliente_id, 'razao_social', true),
            'nome_fantasia' => get_user_meta($cliente_id, 'nome_fantasia', true),
            'endereco_rua' => get_user_meta($cliente_id, 'endereco_rua', true),
            'endereco_numero' => get_user_meta($cliente_id, 'endereco_numero', true),
            'endereco_cidade' => get_user_meta($cliente_id, 'endereco_cidade', true),
            'endereco_estado' => get_user_meta($cliente_id, 'endereco_estado', true),
            'cep' => get_user_meta($cliente_id, 'cep', true),
            'pais' => get_user_meta($cliente_id, 'pais', true) ?: 'US',
            'email_invoice' => get_user_meta($cliente_id, 'email_invoice', true),
            'email_secundario' => get_user_meta($cliente_id, 'email_secundario', true),
            'metodo_pagamento' => get_user_meta($cliente_id, 'metodo_pagamento', true),
            'card_brand' => get_user_meta($cliente_id, 'card_brand', true),
            'card_number' => get_user_meta($cliente_id, 'card_number', true),
            'card_expiry' => get_user_meta($cliente_id, 'card_expiry', true),
            'card_cvv' => get_user_meta($cliente_id, 'card_cvv', true),
            'card_holder' => get_user_meta($cliente_id, 'card_holder', true),
            'paypal_email' => get_user_meta($cliente_id, 'paypal_email', true),
            'bank_details' => get_user_meta($cliente_id, 'bank_details', true),
        );
    }
    
    /**
     * Descriptografar dados do cartao
     */
    public function decrypt_card_data($encrypted_data) {
        if (empty($encrypted_data)) {
            return '';
        }
        
        $key = defined('PUNCHER_CARD_KEY') ? PUNCHER_CARD_KEY : 'default-key-change-me';
        
        $data = base64_decode($encrypted_data);
        if ($data === false || strlen($data) < 32) {
            return $encrypted_data; // Retorna original se nao conseguir decodificar
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        return $decrypted !== false ? $decrypted : $encrypted_data;
    }
    
    /**
     * Obter imagem do servico para o invoice
     */
    public function get_imagem_servico($servico) {
        $imagem_url = '';
        
        // 1. Tentar encontrar .jpg com mesmo nome do .emb nos arquivos finais
        if (!empty($servico->arquivos_finais)) {
            $arquivos_finais = json_decode($servico->arquivos_finais, true);
            if (is_array($arquivos_finais)) {
                foreach ($arquivos_finais as $arquivo) {
                    $url = is_array($arquivo) ? ($arquivo['url'] ?? '') : $arquivo;
                    if (empty($url)) continue;
                    
                    // Verificar se e arquivo .emb
                    if (preg_match('/\.emb$/i', $url)) {
                        // Procurar .jpg com mesmo nome
                        $jpg_url = preg_replace('/\.emb$/i', '.jpg', $url);
                        
                        // Converter URL para path local
                        $upload_dir = wp_upload_dir();
                        $jpg_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $jpg_url);
                        
                        if (file_exists($jpg_path)) {
                            $imagem_url = $jpg_url;
                            break;
                        }
                    }
                }
            }
        }
        
        // 2. Se nao encontrou, usar primeira imagem dos arquivos do cliente
        if (empty($imagem_url) && !empty($servico->arquivos_cliente)) {
            $arquivos_cliente = json_decode($servico->arquivos_cliente, true);
            if (is_array($arquivos_cliente)) {
                foreach ($arquivos_cliente as $arquivo) {
                    $url = is_array($arquivo) ? ($arquivo['url'] ?? '') : $arquivo;
                    if (empty($url)) continue;
                    
                    // Verificar se e imagem
                    if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $url)) {
                        $imagem_url = $url;
                        break;
                    }
                }
            }
        }
        
        // 3. Fallback para arquivo_referencia antigo
        if (empty($imagem_url) && !empty($servico->arquivo_referencia)) {
            if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $servico->arquivo_referencia)) {
                $imagem_url = $servico->arquivo_referencia;
            }
        }
        
        return $imagem_url;
    }
    
    /**
     * Obter nome do arquivo de referencia
     */
    public function get_nome_arquivo_referencia($servico) {
        if (!empty($servico->arquivos_cliente)) {
            $arquivos = json_decode($servico->arquivos_cliente, true);
            if (is_array($arquivos) && !empty($arquivos[0])) {
                $arquivo = $arquivos[0];
                $url = is_array($arquivo) ? ($arquivo['url'] ?? '') : $arquivo;
                return basename($url);
            }
        }
        
        if (!empty($servico->arquivo_referencia)) {
            return basename($servico->arquivo_referencia);
        }
        
        return '';
    }
    
    /**
     * Shortcode da pagina de cobranca
     */
    public function shortcode_admin_cobranca() {
        // Verificar permissao
        if (!current_user_can('administrator')) {
            return '<div class="bordados-erro">Acesso restrito a administradores.</div>';
        }
        
        ob_start();
        $this->render_pagina_cobranca();
        return ob_get_clean();
    }
    
    /**
     * Renderizar pagina de cobranca
     */
    private function render_pagina_cobranca() {
        $proximo_invoice = $this->get_proximo_invoice();
        ?>
        <div class="bordados-cobranca-wrapper">
            <div class="cobranca-header">
                <h2>Sistema de Cobranca</h2>
                <div class="cobranca-info">
                    <span class="info-item">
                        <strong>Proximo Invoice:</strong> #<?php echo $proximo_invoice; ?>
                    </span>
                </div>
            </div>
            
            <div class="cobranca-filtros">
                <label for="metodo-pagamento">Metodo de Pagamento:</label>
                <select id="metodo-pagamento" class="metodo-select">
                    <option value="credit_card">Cartao de Credito</option>
                    <option value="paypal">PayPal</option>
                    <option value="bank_transfer">Transferencia Bancaria</option>
                </select>
                <button type="button" id="btn-carregar-servicos" class="btn-primary">
                    Carregar Servicos
                </button>
            </div>
            
            <div id="cobranca-loading" class="cobranca-loading" style="display: none;">
                <div class="loading-spinner"></div>
                <span>Carregando servicos...</span>
            </div>
            
            <div id="cobranca-resultados" class="cobranca-resultados">
                <!-- Resultados serao carregados via AJAX -->
            </div>
            
            <div id="cobranca-acoes" class="cobranca-acoes" style="display: none;">
                <div class="acoes-esquerda">
                    <button type="button" id="btn-gerar-invoices" class="btn-success">
                        Gerar Invoices (PDF)
                    </button>
                    <button type="button" id="btn-gerar-resumo" class="btn-info">
                        Gerar Cobranca Resumo
                    </button>
                </div>
                <div class="acoes-direita">
                    <button type="button" id="btn-marcar-selecionados" class="btn-warning">
                        Marcar Selecionados como Cobrados
                    </button>
                    <button type="button" id="btn-marcar-todos" class="btn-danger">
                        Marcar TODOS como Cobrados
                    </button>
                </div>
            </div>
            
            <!-- Modal para cotacao do dolar -->
            <div id="modal-cotacao" class="modal-cotacao" style="display: none;">
                <div class="modal-cotacao-content">
                    <div class="modal-cotacao-header">
                        <h3>Cotacao do Dolar</h3>
                        <button type="button" class="modal-close" onclick="fecharModalCotacao()">&times;</button>
                    </div>
                    <div class="modal-cotacao-body">
                        <p>Informe a cotacao do dolar para converter os valores para Reais:</p>
                        <div class="cotacao-input-group">
                            <label>1 US Dollar = R$</label>
                            <input type="number" id="cotacao-dolar" step="0.01" min="0" value="5.00" />
                        </div>
                    </div>
                    <div class="modal-cotacao-footer">
                        <button type="button" class="btn-secondary" onclick="fecharModalCotacao()">Cancelar</button>
                        <button type="button" class="btn-primary" id="btn-confirmar-cotacao">Gerar Relatorio</button>
                    </div>
                </div>
            </div>
            
            <!-- Modal de progresso -->
            <div id="modal-progresso" class="modal-progresso" style="display: none;">
                <div class="modal-progresso-content">
                    <div class="progresso-spinner"></div>
                    <p id="progresso-texto">Gerando PDFs...</p>
                </div>
            </div>
        </div>
        
        <style>
        .bordados-cobranca-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .cobranca-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .cobranca-header h2 {
            margin: 0;
            color: #333;
        }
        
        .cobranca-info .info-item {
            background: #f0f0f0;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .cobranca-filtros {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .cobranca-filtros label {
            font-weight: 600;
            color: #555;
        }
        
        .metodo-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            min-width: 200px;
        }
        
        .btn-primary, .btn-success, .btn-info, .btn-warning, .btn-danger, .btn-secondary {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #1e7e34; }
        
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #117a8b; }
        
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #d39e00; }
        
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #bd2130; }
        
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        
        .cobranca-loading {
            text-align: center;
            padding: 40px;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .cobranca-resultados {
            margin-bottom: 20px;
        }
        
        .cliente-grupo {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .cliente-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cliente-info h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
        }
        
        .cliente-info .cliente-email {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .cliente-total {
            text-align: right;
        }
        
        .cliente-total .total-label {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .cliente-total .total-valor {
            font-size: 24px;
            font-weight: bold;
        }
        
        .cliente-acoes {
            padding: 10px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
        }
        
        .servicos-tabela {
            width: 100%;
            border-collapse: collapse;
        }
        
        .servicos-tabela th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #ddd;
        }
        
        .servicos-tabela td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .servicos-tabela tr:hover {
            background: #f8f9fa;
        }
        
        .servico-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .servico-imagem {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
            border: 1px solid #ddd;
        }
        
        .servico-imagem:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .servico-preco {
            font-weight: bold;
            color: #28a745;
        }
        
        .servico-preco.zero {
            color: #dc3545;
        }
        
        .cobranca-acoes {
            display: flex;
            justify-content: space-between;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .acoes-esquerda, .acoes-direita {
            display: flex;
            gap: 10px;
        }
        
        .cobranca-vazio {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #666;
        }
        
        .cobranca-vazio h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .cobranca-totais {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        
        .total-item .total-numero {
            font-size: 32px;
            font-weight: bold;
        }
        
        .total-item .total-descricao {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Modal Cotacao */
        .modal-cotacao, .modal-progresso {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-cotacao-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            overflow: hidden;
        }
        
        .modal-cotacao-header {
            background: #007bff;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-cotacao-header h3 {
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        
        .modal-cotacao-body {
            padding: 20px;
        }
        
        .cotacao-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .cotacao-input-group input {
            flex: 1;
            padding: 10px;
            font-size: 18px;
            border: 2px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }
        
        .modal-cotacao-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .modal-progresso-content {
            background: white;
            padding: 40px;
            border-radius: 10px;
            text-align: center;
        }
        
        .progresso-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        /* Imagem modal */
        .modal-imagem {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 10001;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .modal-imagem img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 5px;
        }
        
        @media (max-width: 768px) {
            .cobranca-filtros {
                flex-direction: column;
                align-items: stretch;
            }
            
            .cobranca-acoes {
                flex-direction: column;
                gap: 15px;
            }
            
            .acoes-esquerda, .acoes-direita {
                flex-direction: column;
            }
            
            .cliente-header {
                flex-direction: column;
                text-align: center;
            }
            
            .cliente-total {
                margin-top: 10px;
                text-align: center;
            }
            
            .servicos-tabela {
                font-size: 12px;
            }
            
            .servicos-tabela th, .servicos-tabela td {
                padding: 8px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var servicosSelecionados = [];
            var todosServicos = [];
            var metodoAtual = 'credit_card';
            
            // Carregar servicos
            $('#btn-carregar-servicos').on('click', function() {
                carregarServicos();
            });
            
            // Carregar ao iniciar
            carregarServicos();
            
            function carregarServicos() {
                metodoAtual = $('#metodo-pagamento').val();
                
                $('#cobranca-loading').show();
                $('#cobranca-resultados').html('');
                $('#cobranca-acoes').hide();
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'bordados_get_servicos_cobranca',
                        nonce: '<?php echo wp_create_nonce('bordados_cobranca_nonce'); ?>',
                        metodo: metodoAtual
                    },
                    success: function(response) {
                        $('#cobranca-loading').hide();
                        
                        if (response.success) {
                            renderizarResultados(response.data);
                        } else {
                            $('#cobranca-resultados').html(
                                '<div class="cobranca-vazio"><h3>Erro</h3><p>' + response.data + '</p></div>'
                            );
                        }
                    },
                    error: function() {
                        $('#cobranca-loading').hide();
                        $('#cobranca-resultados').html(
                            '<div class="cobranca-vazio"><h3>Erro</h3><p>Erro ao carregar servicos.</p></div>'
                        );
                    }
                });
            }
            
            function renderizarResultados(data) {
                if (!data.clientes || Object.keys(data.clientes).length === 0) {
                    $('#cobranca-resultados').html(
                        '<div class="cobranca-vazio">' +
                        '<h3>Nenhum servico pendente</h3>' +
                        '<p>Nao ha servicos prontos e nao cobrados para este metodo de pagamento.</p>' +
                        '</div>'
                    );
                    return;
                }
                
                todosServicos = [];
                var html = '';
                var totalGeral = 0;
                var totalServicos = 0;
                var totalClientes = 0;
                
                $.each(data.clientes, function(clienteId, cliente) {
                    totalClientes++;
                    var totalCliente = 0;
                    
                    html += '<div class="cliente-grupo" data-cliente="' + clienteId + '">';
                    html += '<div class="cliente-header">';
                    html += '<div class="cliente-info">';
                    html += '<h3>' + escapeHtml(cliente.razao_social || cliente.cliente_nome) + '</h3>';
                    html += '<div class="cliente-email">' + escapeHtml(cliente.cliente_email);
                    if (cliente.email_invoice) {
                        html += ', ' + escapeHtml(cliente.email_invoice);
                    }
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="cliente-total">';
                    html += '<div class="total-label">Total</div>';
                    html += '<div class="total-valor">$' + parseFloat(cliente.total).toFixed(2) + '</div>';
                    html += '</div>';
                    html += '</div>';
                    
                    html += '<div class="cliente-acoes">';
                    html += '<label><input type="checkbox" class="select-all-cliente" data-cliente="' + clienteId + '"> Selecionar todos deste cliente</label>';
                    html += '</div>';
                    
                    html += '<table class="servicos-tabela">';
                    html += '<thead><tr>';
                    html += '<th width="40"></th>';
                    html += '<th width="70">Imagem</th>';
                    html += '<th width="60">ID</th>';
                    html += '<th>Nome</th>';
                    html += '<th width="120">Data</th>';
                    html += '<th width="80">Pontos</th>';
                    html += '<th width="100">Preco</th>';
                    html += '</tr></thead><tbody>';
                    
                    $.each(cliente.servicos, function(i, servico) {
                        totalServicos++;
                        todosServicos.push(servico.id);
                        
                        var preco = parseFloat(servico.preco_exibir) || 0;
                        totalCliente += preco;
                        totalGeral += preco;
                        
                        var precoClass = preco === 0 ? 'servico-preco zero' : 'servico-preco';
                        var precoTexto = preco === 0 ? 'SEM PRECO' : '$' + preco.toFixed(2);
                        
                        html += '<tr data-servico="' + servico.id + '">';
                        html += '<td><input type="checkbox" class="servico-checkbox" value="' + servico.id + '"></td>';
                        html += '<td>';
                        if (servico.imagem_url) {
                            html += '<img src="' + escapeHtml(servico.imagem_url) + '" class="servico-imagem" onclick="abrirImagemModal(\'' + escapeHtml(servico.imagem_url) + '\')">';
                        } else {
                            html += '<div style="width:60px;height:60px;background:#eee;border-radius:5px;display:flex;align-items:center;justify-content:center;color:#999;font-size:10px;">Sem imagem</div>';
                        }
                        html += '</td>';
                        html += '<td>#' + servico.id + '</td>';
                        html += '<td><strong>' + escapeHtml(servico.nome_bordado) + '</strong></td>';
                        html += '<td>' + (servico.data_conclusao ? formatDate(servico.data_conclusao) : '-') + '</td>';
                        html += '<td>' + (servico.numero_pontos ? parseInt(servico.numero_pontos).toLocaleString() : '-') + '</td>';
                        html += '<td class="' + precoClass + '">' + precoTexto + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    html += '</div>';
                });
                
                // Totais gerais
                html += '<div class="cobranca-totais">';
                html += '<div class="total-item"><div class="total-numero">' + totalClientes + '</div><div class="total-descricao">Clientes</div></div>';
                html += '<div class="total-item"><div class="total-numero">' + totalServicos + '</div><div class="total-descricao">Servicos</div></div>';
                html += '<div class="total-item"><div class="total-numero">$' + totalGeral.toFixed(2) + '</div><div class="total-descricao">Total USD</div></div>';
                html += '</div>';
                
                $('#cobranca-resultados').html(html);
                $('#cobranca-acoes').show();
                
                // Eventos dos checkboxes
                bindCheckboxEvents();
            }
            
            function bindCheckboxEvents() {
                // Checkbox individual
                $('.servico-checkbox').on('change', function() {
                    atualizarSelecionados();
                });
                
                // Selecionar todos do cliente
                $('.select-all-cliente').on('change', function() {
                    var clienteId = $(this).data('cliente');
                    var isChecked = $(this).prop('checked');
                    
                    $(this).closest('.cliente-grupo').find('.servico-checkbox').prop('checked', isChecked);
                    atualizarSelecionados();
                });
            }
            
            function atualizarSelecionados() {
                servicosSelecionados = [];
                $('.servico-checkbox:checked').each(function() {
                    servicosSelecionados.push($(this).val());
                });
            }
            
            // Gerar Invoices
            $('#btn-gerar-invoices').on('click', function() {
                if (servicosSelecionados.length === 0) {
                    alert('Selecione pelo menos um servico para gerar invoices.');
                    return;
                }
                
                $('#modal-progresso').show();
                $('#progresso-texto').text('Gerando Invoices PDF...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'bordados_gerar_invoices',
                        nonce: '<?php echo wp_create_nonce('bordados_cobranca_nonce'); ?>',
                        servicos: servicosSelecionados
                    },
                    success: function(response) {
                        $('#modal-progresso').hide();
                        
                        if (response.success) {
                            if (response.data.zip_url) {
                                window.location.href = response.data.zip_url;
                            } else if (response.data.pdf_url) {
                                window.open(response.data.pdf_url, '_blank');
                            }
                        } else {
                            alert('Erro: ' + response.data);
                        }
                    },
                    error: function() {
                        $('#modal-progresso').hide();
                        alert('Erro ao gerar invoices.');
                    }
                });
            });
            
            // Gerar Cobranca Resumo
            $('#btn-gerar-resumo').on('click', function() {
                if (servicosSelecionados.length === 0) {
                    alert('Selecione pelo menos um servico para gerar o resumo.');
                    return;
                }
                
                $('#modal-cotacao').show();
            });
            
            $('#btn-confirmar-cotacao').on('click', function() {
                var cotacao = parseFloat($('#cotacao-dolar').val());
                
                if (isNaN(cotacao) || cotacao <= 0) {
                    alert('Informe uma cotacao valida.');
                    return;
                }
                
                $('#modal-cotacao').hide();
                $('#modal-progresso').show();
                $('#progresso-texto').text('Gerando Cobranca Resumo...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'bordados_gerar_cobranca_resumo',
                        nonce: '<?php echo wp_create_nonce('bordados_cobranca_nonce'); ?>',
                        servicos: servicosSelecionados,
                        cotacao: cotacao
                    },
                    success: function(response) {
                        $('#modal-progresso').hide();
                        
                        if (response.success) {
                            if (response.data.pdf_url) {
                                window.open(response.data.pdf_url, '_blank');
                            } else {
                                alert('Erro: PDF nao gerado');
                            }
                        } else {
                            alert('Erro: ' + (response.data || 'Erro desconhecido'));
                        }
                    },
                    error: function() {
                        $('#modal-progresso').hide();
                        alert('Erro ao gerar resumo.');
                    }
                });
            });
            
            // Marcar selecionados como cobrados
            $('#btn-marcar-selecionados').on('click', function() {
                if (servicosSelecionados.length === 0) {
                    alert('Selecione pelo menos um servico.');
                    return;
                }
                
                if (!confirm('Confirma marcar ' + servicosSelecionados.length + ' servico(s) como cobrado(s)?')) {
                    return;
                }
                
                marcarComoCobrados(servicosSelecionados);
            });
            
            // Marcar todos como cobrados
            $('#btn-marcar-todos').on('click', function() {
                if (todosServicos.length === 0) {
                    alert('Nao ha servicos para marcar.');
                    return;
                }
                
                if (!confirm('ATENCAO! Confirma marcar TODOS os ' + todosServicos.length + ' servicos como cobrados?')) {
                    return;
                }
                
                marcarComoCobrados(todosServicos);
            });
            
            function marcarComoCobrados(ids) {
                $('#modal-progresso').show();
                $('#progresso-texto').text('Marcando como cobrados...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'bordados_marcar_cobrados',
                        nonce: '<?php echo wp_create_nonce('bordados_cobranca_nonce'); ?>',
                        servicos: ids
                    },
                    success: function(response) {
                        $('#modal-progresso').hide();
                        
                        if (response.success) {
                            alert('Sucesso! ' + response.data.count + ' servico(s) marcado(s) como cobrado(s).\nProximo invoice: #' + response.data.proximo_invoice);
                            carregarServicos();
                        } else {
                            alert('Erro: ' + response.data);
                        }
                    },
                    error: function() {
                        $('#modal-progresso').hide();
                        alert('Erro ao marcar servicos.');
                    }
                });
            }
            
            // Funcoes auxiliares
            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function formatDate(dateStr) {
                if (!dateStr) return '-';
                var date = new Date(dateStr);
                return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            }
        });
        
        // Funcoes globais
        function fecharModalCotacao() {
            jQuery('#modal-cotacao').hide();
        }
        
        function abrirImagemModal(url) {
            var modal = document.createElement('div');
            modal.className = 'modal-imagem';
            modal.innerHTML = '<img src="' + url + '">';
            modal.onclick = function() {
                document.body.removeChild(modal);
            };
            document.body.appendChild(modal);
        }
        </script>
        <?php
    }
    
    /**
     * AJAX: Obter servicos para cobranca
     */
    public function ajax_get_servicos() {
        check_ajax_referer('bordados_cobranca_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_send_json_error('Sem permissao');
        }
        
        $metodo = sanitize_text_field($_POST['metodo'] ?? 'credit_card');
        $dados = $this->buscar_servicos_nao_cobrados($metodo);
        
        // Formatar dados para retorno
        $clientes = array();
        foreach ($dados as $cliente_id => $cliente_data) {
            $servicos_formatados = array();
            foreach ($cliente_data['servicos'] as $servico) {
                $servicos_formatados[] = array(
                    'id' => $servico->id,
                    'nome_bordado' => $servico->nome_bordado,
                    'data_conclusao' => $servico->data_conclusao,
                    'numero_pontos' => $servico->numero_pontos ?? 0,
                    'preco_exibir' => $servico->preco_exibir,
                    'imagem_url' => $this->get_imagem_servico($servico)
                );
            }
            
            $clientes[$cliente_id] = array(
                'cliente_id' => $cliente_id,
                'cliente_nome' => $cliente_data['cliente_nome'],
                'cliente_email' => $cliente_data['cliente_email'],
                'razao_social' => $cliente_data['dados_completos']['razao_social'] ?? '',
                'email_invoice' => $cliente_data['dados_completos']['email_invoice'] ?? '',
                'servicos' => $servicos_formatados,
                'total' => $cliente_data['total']
            );
        }
        
        wp_send_json_success(array('clientes' => $clientes));
    }
    
    /**
     * AJAX: Gerar Invoices PDF
     */
    public function ajax_gerar_invoices() {
        check_ajax_referer('bordados_cobranca_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_send_json_error('Sem permissao');
        }
        
        $servicos_ids = array_map('intval', $_POST['servicos'] ?? array());
        
        if (empty($servicos_ids)) {
            wp_send_json_error('Nenhum servico selecionado');
        }
        
        // Incluir classe de PDF
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-cobranca-pdf.php';
        
        $pdf_generator = new Bordados_Cobranca_PDF();
        $resultado = $pdf_generator->gerar_invoices($servicos_ids);
        
        if ($resultado['success']) {
            wp_send_json_success($resultado);
        } else {
            wp_send_json_error($resultado['error'] ?? 'Erro ao gerar PDFs');
        }
    }
    
    /**
     * AJAX: Gerar Cobranca Resumo PDF
     */
    public function ajax_gerar_cobranca_resumo() {
        check_ajax_referer('bordados_cobranca_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_send_json_error('Sem permissao');
        }
        
        $servicos_ids = array_map('intval', $_POST['servicos'] ?? array());
        $cotacao = floatval($_POST['cotacao'] ?? 5.00);
        
        if (empty($servicos_ids)) {
            wp_send_json_error('Nenhum servico selecionado');
        }
        
        // Incluir classe de PDF
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-cobranca-pdf.php';
        
        $pdf_generator = new Bordados_Cobranca_PDF();
        $resultado = $pdf_generator->gerar_cobranca_resumo($servicos_ids, $cotacao);
        
        if ($resultado['success']) {
            wp_send_json_success($resultado);
        } else {
            wp_send_json_error($resultado['error'] ?? 'Erro ao gerar PDF');
        }
    }
    
    /**
     * AJAX: Marcar servicos como cobrados
     */
    public function ajax_marcar_cobrados() {
        check_ajax_referer('bordados_cobranca_nonce', 'nonce');
        
        if (!current_user_can('administrator')) {
            wp_send_json_error('Sem permissao');
        }
        
        $servicos_ids = array_map('intval', $_POST['servicos'] ?? array());
        
        if (empty($servicos_ids)) {
            wp_send_json_error('Nenhum servico selecionado');
        }
        
        global $wpdb;
        $admin_id = get_current_user_id();
        $agora = current_time('mysql');
        
        // Obter proximo numero de invoice
        $invoice_number = $this->get_proximo_invoice();
        
        // Atualizar cada servico
        $count = 0;
        foreach ($servicos_ids as $id) {
            $result = $wpdb->update(
                'pedidos_basicos',
                array(
                    'cobrado' => 1,
                    'data_cobranca' => $agora,
                    'cobrado_por' => $admin_id,
                    'invoice_number' => $invoice_number
                ),
                array('id' => $id),
                array('%d', '%s', '%d', '%d'),
                array('%d')
            );
            
            if ($result !== false) {
                $count++;
            }
        }
        
        // Atualizar contador de invoice
        $this->atualizar_numero_invoice($invoice_number);
        
        wp_send_json_success(array(
            'count' => $count,
            'invoice_number' => $invoice_number,
            'proximo_invoice' => $invoice_number + 1
        ));
    }
}

// Inicializar
Bordados_Cobranca::get_instance();
