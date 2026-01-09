<?php
/**
 * AJAX: Funções da Assistente - EXPANDIDO
 * 
 * MODIFICAÇÃO: Formulário de edição de cliente expandido com todos os campos do perfil
 * 
 * Handlers AJAX para o painel da assistente:
 * - Cadastrar cliente
 * - Ver detalhes do pedido
 * - Editar pedido
 * - Editar cliente (EXPANDIDO)
 * 
 * @package Sistema_Bordados
 * @since 3.2
 * @modified 2026-01-09 - Formulário expandido
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Assistente {
    
    /**
     * Construtor - registra os handlers AJAX
     */
    public function __construct() {
        // Cadastrar cliente
        add_action('wp_ajax_assistente_cadastrar_cliente', array($this, 'cadastrar_cliente'));
        
        // Ver detalhes do pedido
        add_action('wp_ajax_assistente_ver_pedido', array($this, 'ver_pedido'));
        
        // Form editar pedido
        add_action('wp_ajax_assistente_form_editar_pedido', array($this, 'form_editar_pedido'));
        
        // Salvar pedido
        add_action('wp_ajax_assistente_salvar_pedido', array($this, 'salvar_pedido'));
        
        // Form editar cliente
        add_action('wp_ajax_assistente_form_editar_cliente', array($this, 'form_editar_cliente'));
        
        // Salvar cliente
        add_action('wp_ajax_assistente_salvar_cliente', array($this, 'salvar_cliente'));
        
        // Atribuir pedido a programador
        add_action('wp_ajax_assistente_atribuir_pedido', array($this, 'atribuir_pedido'));
    }
    
    /**
     * Verificar permissões (assistente ou admin)
     */
    private function verificar_permissao() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('assistente_bordados', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles);
    }
    
    /**
     * AJAX: Atribuir pedido a programador
     */
    public function atribuir_pedido() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        $programador_id = intval($_POST['programador_id']);
        
        if (empty($pedido_id) || empty($programador_id)) {
            wp_send_json_error('Dados inválidos.');
            return;
        }
        
        // Verificar se programador existe e tem a role correta
        $programador = get_userdata($programador_id);
        if (!$programador || (!in_array('programador_bordados', (array) $programador->roles) && !in_array('administrator', (array) $programador->roles))) {
            wp_send_json_error('Programador inválido.');
            return;
        }
        
        global $wpdb;
        $tabela = 'pedidos_basicos';
        
        // Atualizar pedido
        $resultado = $wpdb->update(
            $tabela,
            array(
                'programador_id' => $programador_id,
                'status' => 'atribuido',
                'data_atribuicao' => current_time('mysql')
            ),
            array('id' => $pedido_id),
            array('%d', '%s', '%s'),
            array('%d')
        );
        
        if ($resultado === false) {
            wp_send_json_error('Erro ao atribuir pedido: ' . $wpdb->last_error);
            return;
        }
        
        // Enviar email para programador
        if (class_exists('Bordados_Emails')) {
            Bordados_Emails::notificar_programador_novo_trabalho($pedido_id);
        }
        
        wp_send_json_success(array(
            'message' => 'Pedido #' . $pedido_id . ' atribuído para ' . $programador->display_name . '!'
        ));
    }
    
    /**
     * AJAX: Cadastrar novo cliente
     */
    public function cadastrar_cliente() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $nome = sanitize_text_field($_POST['nome']);
        $email = sanitize_email($_POST['email']);
        $senha = !empty($_POST['senha']) ? $_POST['senha'] : wp_generate_password(12, true);
        $programador_padrao = !empty($_POST['programador_padrao']) ? intval($_POST['programador_padrao']) : '';
        
        // Validar
        if (empty($nome) || empty($email)) {
            wp_send_json_error('Nome e email são obrigatórios.');
            return;
        }
        
        if (!is_email($email)) {
            wp_send_json_error('Email inválido.');
            return;
        }
        
        if (email_exists($email)) {
            wp_send_json_error('Este email já está cadastrado.');
            return;
        }
        
        // Criar usuário
        $user_id = wp_create_user($email, $senha, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error('Erro ao criar usuário: ' . $user_id->get_error_message());
            return;
        }
        
        // Atualizar dados
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $nome,
            'first_name' => $nome
        ));
        
        // Definir role
        $user = new WP_User($user_id);
        $user->set_role('cliente_bordados');
        
        // Programador padrão
        if (!empty($programador_padrao)) {
            update_user_meta($user_id, 'programador_padrao', $programador_padrao);
        }
        
        // Enviar email de boas-vindas
        wp_new_user_notification($user_id, null, 'user');
        
        wp_send_json_success(array(
            'message' => 'Cliente "' . $nome . '" cadastrado com sucesso!',
            'user_id' => $user_id
        ));
    }
    
    /**
     * AJAX: Ver detalhes do pedido
     */
    public function ver_pedido() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        
        $pedido = Bordados_Database::buscar_pedido($pedido_id);
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // Montar HTML dos detalhes
        $html = $this->gerar_html_detalhes_pedido($pedido);
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Gerar HTML dos detalhes do pedido
     */
    private function gerar_html_detalhes_pedido($pedido) {
        $cliente = get_userdata($pedido->cliente_id);
        $programador = $pedido->programador_id ? get_userdata($pedido->programador_id) : null;
        
        $status_labels = array(
            'novo' => '🆕 Novo',
            'atribuido' => '👨‍💻 Atribuído',
            'em_producao' => '⚙️ Em Produção',
            'aguardando_revisao' => '🔍 Aguardando Revisão',
            'em_revisao' => '📝 Em Revisão',
            'pronto' => '✅ Pronto'
        );
        
        ob_start();
        ?>
        <div class="detalhes-pedido">
            <p><strong>Cliente:</strong> <?php echo esc_html($cliente ? $cliente->display_name : 'N/A'); ?></p>
            <p><strong>Email:</strong> <?php echo esc_html($cliente ? $cliente->user_email : 'N/A'); ?></p>
            <p><strong>Bordado:</strong> <?php echo esc_html($pedido->nome_bordado); ?></p>
            <p><strong>Tamanho:</strong> <?php echo esc_html($pedido->largura); ?> x <?php echo esc_html($pedido->altura); ?> <?php echo esc_html($pedido->unidade_medida ?: 'cm'); ?></p>
            <p><strong>Cores:</strong> <?php echo esc_html($pedido->cores ?: 'Não informado'); ?></p>
            <p><strong>Status:</strong> <?php echo $status_labels[$pedido->status] ?? $pedido->status; ?></p>
            <p><strong>Programador:</strong> <?php echo esc_html($programador ? $programador->display_name : 'Não atribuído'); ?></p>
            <p><strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido->data_criacao)); ?></p>
            <?php if (!empty($pedido->observacoes)): ?>
                <p><strong>Observações:</strong><br><?php echo nl2br(esc_html($pedido->observacoes)); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Formulário para editar pedido
     */
    public function form_editar_pedido() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        
        // Buscar pedido
        $pedido = Bordados_Database::buscar_pedido($pedido_id);
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // Buscar programadores
        $programadores = Bordados_Helpers::listar_programadores();
        
        $html = $this->gerar_form_editar_pedido($pedido, $programadores);
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Gerar formulário de edição de pedido
     */
    private function gerar_form_editar_pedido($pedido, $programadores) {
        ob_start();
        ?>
        <form id="form-editar-pedido-assistente">
            <input type="hidden" name="pedido_id" value="<?php echo $pedido->id; ?>">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nome do Bordado</label>
                <input type="text" name="nome_bordado" value="<?php echo esc_attr($pedido->nome_bordado); ?>" required
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Largura (<?php echo esc_html($pedido->unidade_medida ?: 'cm'); ?>)</label>
                    <input type="number" step="0.01" name="largura" value="<?php echo esc_attr($pedido->largura); ?>"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Altura (<?php echo esc_html($pedido->unidade_medida ?: 'cm'); ?>)</label>
                    <input type="number" step="0.01" name="altura" value="<?php echo esc_attr($pedido->altura); ?>"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Cores</label>
                <input type="text" name="cores" value="<?php echo esc_attr($pedido->cores); ?>"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Programador</label>
                <select name="programador_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="">Não atribuído</option>
                    <?php foreach ($programadores as $prog): ?>
                        <option value="<?php echo $prog->ID; ?>" <?php selected($pedido->programador_id, $prog->ID); ?>>
                            <?php echo esc_html($prog->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Observações</label>
                <textarea name="observacoes" rows="4"
                          style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"><?php echo esc_textarea($pedido->observacoes); ?></textarea>
            </div>
            
            <button type="submit" style="width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer;">
                💾 Salvar Alterações
            </button>
        </form>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Salvar edição do pedido
     */
    public function salvar_pedido() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        
        if (empty($pedido_id)) {
            wp_send_json_error('ID do pedido inválido.');
            return;
        }
        
        global $wpdb;
        $tabela = 'pedidos_basicos';
        
        // Buscar pedido atual para verificar mudança de programador
        $pedido_atual = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id = %d", $pedido_id));
        
        $dados = array(
            'nome_bordado' => sanitize_text_field($_POST['nome_bordado']),
            'largura' => floatval($_POST['largura']),
            'altura' => floatval($_POST['altura']),
            'cores' => sanitize_text_field($_POST['cores']),
            'observacoes' => sanitize_textarea_field($_POST['observacoes'])
        );
        
        // Se programador foi alterado
        $novo_programador = !empty($_POST['programador_id']) ? intval($_POST['programador_id']) : null;
        if ($novo_programador && $novo_programador != $pedido_atual->programador_id) {
            $dados['programador_id'] = $novo_programador;
            if ($pedido_atual->status === 'novo') {
                $dados['status'] = 'atribuido';
                $dados['data_atribuicao'] = current_time('mysql');
            }
        }
        
        $resultado = $wpdb->update($tabela, $dados, array('id' => $pedido_id));
        
        if ($resultado === false) {
            wp_send_json_error('Erro ao atualizar: ' . $wpdb->last_error);
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Pedido #' . $pedido_id . ' atualizado com sucesso!'
        ));
    }
    
    /**
     * AJAX: Formulário para editar cliente - EXPANDIDO
     */
    public function form_editar_cliente() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $cliente_id = intval($_POST['cliente_id']);
        
        $cliente = get_userdata($cliente_id);
        
        if (!$cliente) {
            wp_send_json_error('Cliente não encontrado.');
            return;
        }
        
        // Buscar programadores
        $programadores = Bordados_Helpers::listar_programadores();
        
        // Buscar todos os meta dados do cliente
        $dados = $this->buscar_dados_cliente($cliente_id);
        
        $html = $this->gerar_form_editar_cliente($cliente, $programadores, $dados);
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Buscar todos os dados do cliente
     */
    private function buscar_dados_cliente($cliente_id) {
        return array(
            // Dados pessoais
            'first_name' => get_user_meta($cliente_id, 'first_name', true),
            'last_name' => get_user_meta($cliente_id, 'last_name', true),
            'titulo_cliente' => get_user_meta($cliente_id, 'titulo_cliente', true),
            'apelido_cliente' => get_user_meta($cliente_id, 'apelido_cliente', true),
            'email_secundario' => get_user_meta($cliente_id, 'email_secundario', true),
            'email_invoice' => get_user_meta($cliente_id, 'email_invoice', true),
            'telefone_whatsapp' => get_user_meta($cliente_id, 'telefone_whatsapp', true),
            'cpf_cnpj' => get_user_meta($cliente_id, 'cpf_cnpj', true),
            'data_nascimento' => get_user_meta($cliente_id, 'data_nascimento', true),
            
            // Endereço
            'pais' => get_user_meta($cliente_id, 'pais', true),
            'cep' => get_user_meta($cliente_id, 'cep', true),
            'endereco_rua' => get_user_meta($cliente_id, 'endereco_rua', true),
            'endereco_numero' => get_user_meta($cliente_id, 'endereco_numero', true),
            'endereco_complemento' => get_user_meta($cliente_id, 'endereco_complemento', true),
            'endereco_bairro' => get_user_meta($cliente_id, 'endereco_bairro', true),
            'endereco_cidade' => get_user_meta($cliente_id, 'endereco_cidade', true),
            'endereco_estado' => get_user_meta($cliente_id, 'endereco_estado', true),
            
            // Empresa
            'razao_social' => get_user_meta($cliente_id, 'razao_social', true),
            'nome_fantasia' => get_user_meta($cliente_id, 'nome_fantasia', true),
            'cnpj_empresa' => get_user_meta($cliente_id, 'cnpj_empresa', true),
            
            // Preferências de bordado
            'formato_arquivo_preferido' => get_user_meta($cliente_id, 'formato_arquivo_preferido', true),
            'unidade_medida_preferida' => get_user_meta($cliente_id, 'unidade_medida_preferida', true),
            'maquina_bordar' => get_user_meta($cliente_id, 'maquina_bordar', true),
            'obs_para_programador' => get_user_meta($cliente_id, 'obs_para_programador', true),
            
            // Configurações do sistema
            'programador_padrao' => get_user_meta($cliente_id, 'programador_padrao', true),
            'atribuicao_automatica' => get_user_meta($cliente_id, 'atribuicao_automatica', true),
            'requer_revisao' => get_user_meta($cliente_id, 'requer_revisao', true),
        );
    }
    
    /**
     * Gerar formulário de edição de cliente - EXPANDIDO
     */
    private function gerar_form_editar_cliente($cliente, $programadores, $dados) {
        
        // Lista de países principais
        $paises = array(
            'US' => 'United States',
            'CA' => 'Canada',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'PT' => 'Portugal',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
        );
        
        // Formatos de arquivo
        $formatos = array('EMB', 'DST', 'PES', 'JEF', 'EXP', 'VP3', 'SEW', 'CSD', 'XXX', 'HUS', 'VIP');
        
        ob_start();
        ?>
        <form id="form-editar-cliente-assistente">
            <input type="hidden" name="cliente_id" value="<?php echo $cliente->ID; ?>">
            
            <!-- ========================== -->
            <!-- SEÇÃO 1: DADOS BÁSICOS -->
            <!-- ========================== -->
            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #1565c0;">👤 Dados Básicos</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Primeiro Nome *</label>
                        <input type="text" name="first_name" value="<?php echo esc_attr($dados['first_name']); ?>" required
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Sobrenome</label>
                        <input type="text" name="last_name" value="<?php echo esc_attr($dados['last_name']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 100px 1fr; gap: 15px; margin-top: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Título</label>
                        <select name="titulo_cliente" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                            <option value="">--</option>
                            <option value="Mr." <?php selected($dados['titulo_cliente'], 'Mr.'); ?>>Mr.</option>
                            <option value="Mrs." <?php selected($dados['titulo_cliente'], 'Mrs.'); ?>>Mrs.</option>
                            <option value="Ms." <?php selected($dados['titulo_cliente'], 'Ms.'); ?>>Ms.</option>
                            <option value="Dr." <?php selected($dados['titulo_cliente'], 'Dr.'); ?>>Dr.</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Apelido / Nome Fantasia</label>
                        <input type="text" name="apelido_cliente" value="<?php echo esc_attr($dados['apelido_cliente']); ?>"
                               placeholder="Ex: JD Embroidery"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Email Principal *</label>
                    <input type="email" name="email" value="<?php echo esc_attr($cliente->user_email); ?>" required
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Email Secundário</label>
                        <input type="text" name="email_secundario" value="<?php echo esc_attr($dados['email_secundario']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Email para Invoice</label>
                        <input type="email" name="email_invoice" value="<?php echo esc_attr($dados['email_invoice']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Telefone / WhatsApp</label>
                        <input type="text" name="telefone_whatsapp" value="<?php echo esc_attr($dados['telefone_whatsapp']); ?>"
                               placeholder="+1 (555) 123-4567"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">CPF/CNPJ / Tax ID</label>
                        <input type="text" name="cpf_cnpj" value="<?php echo esc_attr($dados['cpf_cnpj']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                </div>
            </div>
            
            <!-- ========================== -->
            <!-- SEÇÃO 2: ENDEREÇO -->
            <!-- ========================== -->
            <div style="background: #f3e5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #7b1fa2;">📍 Endereço</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">País</label>
                        <select name="pais" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                            <option value="">-- Selecione --</option>
                            <?php foreach ($paises as $codigo => $nome): ?>
                                <option value="<?php echo $codigo; ?>" <?php selected($dados['pais'], $codigo); ?>><?php echo $nome; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">CEP / Zip Code</label>
                        <input type="text" name="cep" value="<?php echo esc_attr($dados['cep']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 15px; margin-top: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Rua / Street</label>
                        <input type="text" name="endereco_rua" value="<?php echo esc_attr($dados['endereco_rua']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Número</label>
                        <input type="text" name="endereco_numero" value="<?php echo esc_attr($dados['endereco_numero']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Complemento / Suite</label>
                        <input type="text" name="endereco_complemento" value="<?php echo esc_attr($dados['endereco_complemento']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Bairro / District</label>
                        <input type="text" name="endereco_bairro" value="<?php echo esc_attr($dados['endereco_bairro']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-top: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Cidade</label>
                        <input type="text" name="endereco_cidade" value="<?php echo esc_attr($dados['endereco_cidade']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Estado / State</label>
                        <input type="text" name="endereco_estado" value="<?php echo esc_attr($dados['endereco_estado']); ?>"
                               placeholder="Ex: CA, SP"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                </div>
            </div>
            
            <!-- ========================== -->
            <!-- SEÇÃO 3: DADOS DA EMPRESA -->
            <!-- ========================== -->
            <div style="background: #fff3e0; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #e65100;">🏢 Dados da Empresa (Opcional)</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Razão Social / Legal Name</label>
                        <input type="text" name="razao_social" value="<?php echo esc_attr($dados['razao_social']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Nome Fantasia / Trade Name</label>
                        <input type="text" name="nome_fantasia" value="<?php echo esc_attr($dados['nome_fantasia']); ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">CNPJ / Company Tax ID / EIN</label>
                    <input type="text" name="cnpj_empresa" value="<?php echo esc_attr($dados['cnpj_empresa']); ?>"
                           placeholder="XX-XXXXXXX"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
            </div>
            
            <!-- ========================== -->
            <!-- SEÇÃO 4: PREFERÊNCIAS BORDADO -->
            <!-- ========================== -->
            <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #2e7d32;">⚙️ Preferências de Bordado</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Formato de Arquivo</label>
                        <select name="formato_arquivo_preferido" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                            <option value="">-- Selecione --</option>
                            <?php foreach ($formatos as $formato): ?>
                                <option value="<?php echo $formato; ?>" <?php selected($dados['formato_arquivo_preferido'], $formato); ?>><?php echo $formato; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Unidade de Medida</label>
                        <select name="unidade_medida_preferida" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                            <option value="">-- Selecione --</option>
                            <option value="in" <?php selected($dados['unidade_medida_preferida'], 'in'); ?>>Polegadas (in)</option>
                            <option value="cm" <?php selected($dados['unidade_medida_preferida'], 'cm'); ?>>Centímetros (cm)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Máquina de Bordar</label>
                        <input type="text" name="maquina_bordar" value="<?php echo esc_attr($dados['maquina_bordar']); ?>"
                               placeholder="Ex: Brother PR1050X"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Observações Padrão para o Programador</label>
                    <textarea name="obs_para_programador" rows="3"
                              placeholder="Ex: Prefiro densidade média, evitar pontos longos..."
                              style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"><?php echo esc_textarea($dados['obs_para_programador']); ?></textarea>
                </div>
            </div>
            
            <!-- ========================== -->
            <!-- SEÇÃO 5: CONFIG. DO SISTEMA -->
            <!-- ========================== -->
            <div style="background: #fce4ec; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #c2185b;">🔧 Configurações do Sistema</h4>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Programador Padrão</label>
                    <select name="programador_padrao" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="">Nenhum (atribuição manual)</option>
                        <?php foreach ($programadores as $prog): ?>
                            <option value="<?php echo $prog->ID; ?>" <?php selected($dados['programador_padrao'], $prog->ID); ?>>
                                <?php echo esc_html($prog->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666; display: block; margin-top: 5px;">Novos pedidos serão atribuídos automaticamente a este programador.</small>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div style="background: white; padding: 10px; border-radius: 5px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="atribuicao_automatica" value="yes" 
                                   <?php checked($dados['atribuicao_automatica'], 'yes'); ?>
                                   style="width: 18px; height: 18px;">
                            <span style="font-weight: 600; font-size: 13px;">Atribuição Automática</span>
                        </label>
                        <small style="color: #666; display: block; margin-top: 5px; margin-left: 28px;">
                            Se não tiver programador padrão, atribui ao menos ocupado.
                        </small>
                    </div>
                    <div style="background: white; padding: 10px; border-radius: 5px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="requer_revisao" value="1" 
                                   <?php checked($dados['requer_revisao'], '1'); ?>
                                   style="width: 18px; height: 18px;">
                            <span style="font-weight: 600; font-size: 13px;">Requer Revisão</span>
                        </label>
                        <small style="color: #666; display: block; margin-top: 5px; margin-left: 28px;">
                            Trabalhos passam por revisão antes de serem entregues.
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- ========================== -->
            <!-- SEÇÃO 6: SENHA (OPCIONAL) -->
            <!-- ========================== -->
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 15px 0; color: #856404;">🔑 Alterar Senha (Opcional)</h4>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Nova Senha</label>
                    <input type="text" name="nova_senha" 
                           placeholder="Deixe vazio para manter a senha atual"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    <small style="color: #856404; display: block; margin-top: 5px;">
                        Preencha apenas se quiser alterar a senha do cliente.
                    </small>
                </div>
            </div>
            
            <!-- BOTÃO SALVAR -->
            <button type="submit" style="width: 100%; padding: 15px; background: #28a745; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; transition: background 0.3s;">
                💾 Salvar Todas as Alterações
            </button>
            
        </form>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Salvar edição do cliente - EXPANDIDO
     */
    public function salvar_cliente() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $cliente_id = intval($_POST['cliente_id']);
        
        if (empty($cliente_id)) {
            wp_send_json_error('ID do cliente inválido.');
            return;
        }
        
        $cliente = get_userdata($cliente_id);
        
        if (!$cliente) {
            wp_send_json_error('Cliente não encontrado.');
            return;
        }
        
        // ========================================
        // ATUALIZAR DADOS BÁSICOS DO USUÁRIO
        // ========================================
        $nome = sanitize_text_field($_POST['first_name']);
        $sobrenome = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        
        // Verificar se email já existe para outro usuário
        $email_existe = email_exists($email);
        if ($email_existe && $email_existe != $cliente_id) {
            wp_send_json_error('Este email já está em uso por outro usuário.');
            return;
        }
        
        // Atualizar dados do usuário
        $display_name = trim($nome . ' ' . $sobrenome);
        if (empty($display_name)) {
            $display_name = $nome;
        }
        
        wp_update_user(array(
            'ID' => $cliente_id,
            'user_email' => $email,
            'display_name' => $display_name
        ));
        
        // ========================================
        // ATUALIZAR USER META - DADOS PESSOAIS
        // ========================================
        update_user_meta($cliente_id, 'first_name', $nome);
        update_user_meta($cliente_id, 'last_name', $sobrenome);
        update_user_meta($cliente_id, 'titulo_cliente', sanitize_text_field($_POST['titulo_cliente']));
        update_user_meta($cliente_id, 'apelido_cliente', sanitize_text_field($_POST['apelido_cliente']));
        update_user_meta($cliente_id, 'email_secundario', sanitize_text_field($_POST['email_secundario']));
        update_user_meta($cliente_id, 'email_invoice', sanitize_email($_POST['email_invoice']));
        update_user_meta($cliente_id, 'telefone_whatsapp', sanitize_text_field($_POST['telefone_whatsapp']));
        update_user_meta($cliente_id, 'cpf_cnpj', sanitize_text_field($_POST['cpf_cnpj']));
        
        // ========================================
        // ATUALIZAR USER META - ENDEREÇO
        // ========================================
        update_user_meta($cliente_id, 'pais', sanitize_text_field($_POST['pais']));
        update_user_meta($cliente_id, 'cep', sanitize_text_field($_POST['cep']));
        update_user_meta($cliente_id, 'endereco_rua', sanitize_text_field($_POST['endereco_rua']));
        update_user_meta($cliente_id, 'endereco_numero', sanitize_text_field($_POST['endereco_numero']));
        update_user_meta($cliente_id, 'endereco_complemento', sanitize_text_field($_POST['endereco_complemento']));
        update_user_meta($cliente_id, 'endereco_bairro', sanitize_text_field($_POST['endereco_bairro']));
        update_user_meta($cliente_id, 'endereco_cidade', sanitize_text_field($_POST['endereco_cidade']));
        update_user_meta($cliente_id, 'endereco_estado', sanitize_text_field($_POST['endereco_estado']));
        
        // ========================================
        // ATUALIZAR USER META - EMPRESA
        // ========================================
        update_user_meta($cliente_id, 'razao_social', sanitize_text_field($_POST['razao_social']));
        update_user_meta($cliente_id, 'nome_fantasia', sanitize_text_field($_POST['nome_fantasia']));
        update_user_meta($cliente_id, 'cnpj_empresa', sanitize_text_field($_POST['cnpj_empresa']));
        
        // ========================================
        // ATUALIZAR USER META - PREFERÊNCIAS
        // ========================================
        update_user_meta($cliente_id, 'formato_arquivo_preferido', sanitize_text_field($_POST['formato_arquivo_preferido']));
        update_user_meta($cliente_id, 'unidade_medida_preferida', sanitize_text_field($_POST['unidade_medida_preferida']));
        update_user_meta($cliente_id, 'maquina_bordar', sanitize_text_field($_POST['maquina_bordar']));
        update_user_meta($cliente_id, 'obs_para_programador', sanitize_textarea_field($_POST['obs_para_programador']));
        
        // ========================================
        // ATUALIZAR USER META - CONFIG. SISTEMA
        // ========================================
        $programador_padrao = !empty($_POST['programador_padrao']) ? intval($_POST['programador_padrao']) : '';
        update_user_meta($cliente_id, 'programador_padrao', $programador_padrao);
        
        $atribuicao_automatica = isset($_POST['atribuicao_automatica']) ? 'yes' : '';
        update_user_meta($cliente_id, 'atribuicao_automatica', $atribuicao_automatica);
        
        $requer_revisao = isset($_POST['requer_revisao']) ? '1' : '';
        update_user_meta($cliente_id, 'requer_revisao', $requer_revisao);
        
        // ========================================
        // ALTERAR SENHA (SE FORNECIDA)
        // ========================================
        if (!empty($_POST['nova_senha'])) {
            wp_set_password($_POST['nova_senha'], $cliente_id);
        }
        
        wp_send_json_success(array(
            'message' => 'Cliente "' . $display_name . '" atualizado com sucesso!'
        ));
    }
}

// Inicializar a classe
new Bordados_Ajax_Assistente();
