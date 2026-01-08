<?php
/**
 * AJAX: Funções da Assistente
 * 
 * Handlers AJAX para o painel da assistente:
 * - Cadastrar cliente
 * - Ver detalhes do pedido
 * - Editar pedido
 * - Editar cliente
 * 
 * @package Sistema_Bordados
 * @since 3.2
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
        
        // Verificar se pedido existe e está com status 'novo'
        $pedido = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id = %d", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        if ($pedido->status !== 'novo') {
            wp_send_json_error('Este pedido já foi atribuído ou está em outro status.');
            return;
        }
        
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
        
        // Enviar email para programador (usando a classe de emails existente)
        if (class_exists('Bordados_Emails') && method_exists('Bordados_Emails', 'enviar_notificacao_atribuicao')) {
            Bordados_Emails::enviar_notificacao_atribuicao($pedido_id, $programador_id);
        }
        
        wp_send_json_success(array(
            'message' => 'Pedido atribuído com sucesso para ' . $programador->display_name . '!'
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
        
        // Validar dados
        $nome = sanitize_text_field($_POST['nome']);
        $email = sanitize_email($_POST['email']);
        $senha = isset($_POST['senha']) ? $_POST['senha'] : '';
        $programador_padrao = intval($_POST['programador_padrao']);
        $enviar_email = isset($_POST['enviar_email']) && $_POST['enviar_email'] == 1;
        
        if (empty($nome) || empty($email)) {
            wp_send_json_error('Nome e email são obrigatórios.');
            return;
        }
        
        // Verificar se email já existe
        if (email_exists($email)) {
            wp_send_json_error('Este email já está cadastrado no sistema.');
            return;
        }
        
        // Gerar senha se não informada
        $senha_gerada = false;
        if (empty($senha)) {
            $senha = wp_generate_password(12, true, false);
            $senha_gerada = true;
        }
        
        // Criar username a partir do email
        $username = sanitize_user(strstr($email, '@', true));
        $username_original = $username;
        $contador = 1;
        while (username_exists($username)) {
            $username = $username_original . $contador;
            $contador++;
        }
        
        // Criar usuário
        $user_id = wp_create_user($username, $senha, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error('Erro ao criar usuário: ' . $user_id->get_error_message());
            return;
        }
        
        // Definir role
        $user = new WP_User($user_id);
        $user->set_role('cliente_bordados');
        
        // Atualizar dados
        wp_update_user(array(
            'ID'           => $user_id,
            'display_name' => $nome,
            'first_name'   => $nome
        ));
        
        // Definir programador padrão
        if ($programador_padrao > 0) {
            update_user_meta($user_id, 'programador_padrao', $programador_padrao);
        }
        
        // Definir sistema de preço padrão
        update_user_meta($user_id, 'sistema_preco', 'legacy_stitches');
        
        // Enviar email com credenciais
        if ($enviar_email) {
            $this->enviar_email_boas_vindas($email, $nome, $username, $senha);
        }
        
        $mensagem = 'Cliente "' . $nome . '" cadastrado com sucesso!';
        if ($senha_gerada && $enviar_email) {
            $mensagem .= ' Senha enviada por email.';
        } elseif ($senha_gerada) {
            $mensagem .= ' Senha gerada: ' . $senha;
        }
        
        wp_send_json_success(array(
            'message'  => $mensagem,
            'user_id'  => $user_id,
            'username' => $username
        ));
    }
    
    /**
     * Enviar email de boas-vindas
     */
    private function enviar_email_boas_vindas($email, $nome, $username, $senha) {
        $assunto = 'Bem-vindo ao Puncher.com - Suas credenciais de acesso';
        
        $mensagem = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2>Bem-vindo ao Puncher.com!</h2>
            <p>Olá <strong>{$nome}</strong>,</p>
            <p>Sua conta foi criada com sucesso. Aqui estão suas credenciais de acesso:</p>
            <div style='background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Senha:</strong> {$senha}</p>
            </div>
            <p>Acesse o sistema em: <a href='" . site_url('/login/') . "'>" . site_url('/login/') . "</a></p>
            <p>Recomendamos que você altere sua senha após o primeiro acesso.</p>
            <hr style='margin: 30px 0;'>
            <p style='color: #666; font-size: 12px;'>Puncher.com - Professional Embroidery Digitizing</p>
        </body>
        </html>
        ";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Puncher.com <noreply@puncher.com>'
        );
        
        wp_mail($email, $assunto, $mensagem, $headers);
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
        
        if (empty($pedido_id)) {
            wp_send_json_error('ID do pedido inválido.');
            return;
        }
        
        // Buscar pedido
        global $wpdb;
        $tabela = 'pedidos_basicos';
        
        $pedido = $wpdb->get_row($wpdb->prepare("
            SELECT p.*, 
                   c.display_name as cliente_nome,
                   c.user_email as cliente_email,
                   prog.display_name as programador_nome
            FROM $tabela p
            LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
            LEFT JOIN {$wpdb->users} prog ON p.programador_id = prog.ID
            WHERE p.id = %d
        ", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // Gerar HTML dos detalhes
        $html = $this->gerar_html_detalhes_pedido($pedido);
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Gerar HTML dos detalhes do pedido
     */
    private function gerar_html_detalhes_pedido($pedido) {
        $arquivos_cliente = !empty($pedido->arquivos_cliente) ? json_decode($pedido->arquivos_cliente, true) : array();
        $arquivos_finais = !empty($pedido->arquivos_finais) ? json_decode($pedido->arquivos_finais, true) : array();
        
        ob_start();
        ?>
        <div class="detalhes-pedido">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #eee; width: 40%;"><strong>Cliente:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo esc_html($pedido->cliente_nome); ?> (<?php echo esc_html($pedido->cliente_email); ?>)</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Nome do Bordado:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo esc_html($pedido->nome_bordado); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Tamanho:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo esc_html($pedido->tamanho); ?></td>
                </tr>
                <?php if (!empty($pedido->cores)): ?>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Cores:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo esc_html($pedido->cores); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($pedido->programador_nome)): ?>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Programador:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo esc_html($pedido->programador_nome); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($pedido->preco_programador)): ?>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Preço:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;">$<?php echo number_format($pedido->preco_programador, 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Status:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo esc_html($pedido->status); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Data Criação:</strong></td>
                    <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo date('d/m/Y H:i', strtotime($pedido->data_criacao)); ?></td>
                </tr>
            </table>
            
            <?php if (!empty($pedido->observacoes)): ?>
            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                <strong>Observações do Cliente:</strong><br>
                <?php echo nl2br(esc_html($pedido->observacoes)); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($arquivos_cliente)): ?>
            <div style="margin-top: 15px;">
                <strong>Arquivos de Referência:</strong>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                    <?php foreach ($arquivos_cliente as $arquivo): 
                        $url = Bordados_Helpers::forcar_https($arquivo);
                        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                        $is_image = in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'));
                    ?>
                        <?php if ($is_image): ?>
                            <a href="<?php echo esc_url($url); ?>" target="_blank">
                                <img src="<?php echo esc_url($url); ?>" style="max-width: 100px; max-height: 100px; border-radius: 5px;">
                            </a>
                        <?php else: ?>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" style="display: inline-block; padding: 10px; background: #e0e0e0; border-radius: 5px; text-decoration: none; color: #333;">
                                📎 <?php echo esc_html(basename($url)); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($arquivos_finais)): ?>
            <div style="margin-top: 15px;">
                <strong>Arquivos Finais:</strong>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                    <?php foreach ($arquivos_finais as $arquivo): 
                        $url = Bordados_Helpers::forcar_https($arquivo);
                    ?>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" style="display: inline-block; padding: 10px; background: #d4edda; border-radius: 5px; text-decoration: none; color: #155724;">
                            ⬇️ <?php echo esc_html(basename($url)); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
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
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Tamanho</label>
                <input type="text" name="tamanho" value="<?php echo esc_attr($pedido->tamanho); ?>" required
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
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
        
        // Buscar pedido atual
        $pedido_atual = Bordados_Database::buscar_pedido($pedido_id);
        
        if (!$pedido_atual) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // Preparar dados para atualização
        $dados = array(
            'nome_bordado' => sanitize_text_field($_POST['nome_bordado']),
            'tamanho'      => sanitize_text_field($_POST['tamanho']),
            'cores'        => sanitize_text_field($_POST['cores']),
            'observacoes'  => sanitize_textarea_field($_POST['observacoes'])
        );
        
        $formatos = array('%s', '%s', '%s', '%s');
        
        // Verificar se está mudando programador
        $programador_id = intval($_POST['programador_id']);
        
        if ($programador_id > 0 && $programador_id != $pedido_atual->programador_id) {
            $dados['programador_id'] = $programador_id;
            $formatos[] = '%d';
            
            // Se estava sem programador, mudar status para atribuído
            if (empty($pedido_atual->programador_id) && $pedido_atual->status === 'novo') {
                $dados['status'] = 'atribuido';
                $dados['data_atribuicao'] = current_time('mysql');
                $formatos[] = '%s';
                $formatos[] = '%s';
            }
        } elseif ($programador_id == 0 && !empty($pedido_atual->programador_id)) {
            // Removendo programador
            $dados['programador_id'] = null;
            $formatos[] = '%d';
        }
        
        // Atualizar
        $resultado = Bordados_Database::atualizar_pedido($pedido_id, $dados, $formatos);
        
        if ($resultado === false) {
            wp_send_json_error('Erro ao atualizar pedido.');
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Pedido #' . $pedido_id . ' atualizado com sucesso!'
        ));
    }
    
    /**
     * AJAX: Formulário para editar cliente
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
        $programador_padrao = get_user_meta($cliente_id, 'programador_padrao', true);
        
        $html = $this->gerar_form_editar_cliente($cliente, $programadores, $programador_padrao);
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Gerar formulário de edição de cliente
     */
    private function gerar_form_editar_cliente($cliente, $programadores, $programador_padrao) {
        ob_start();
        ?>
        <form id="form-editar-cliente-assistente">
            <input type="hidden" name="cliente_id" value="<?php echo $cliente->ID; ?>">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nome</label>
                <input type="text" name="nome" value="<?php echo esc_attr($cliente->display_name); ?>" required
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email</label>
                <input type="email" name="email" value="<?php echo esc_attr($cliente->user_email); ?>" required
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Programador Padrão</label>
                <select name="programador_padrao" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="">Nenhum (atribuição manual)</option>
                    <?php foreach ($programadores as $prog): ?>
                        <option value="<?php echo $prog->ID; ?>" <?php selected($programador_padrao, $prog->ID); ?>>
                            <?php echo esc_html($prog->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom: 20px; padding: 10px; background: #fff3cd; border-radius: 5px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nova Senha (opcional)</label>
                <input type="text" name="nova_senha" 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"
                       placeholder="Deixe vazio para manter a senha atual">
                <small style="color: #856404;">Preencha apenas se quiser alterar a senha do cliente.</small>
            </div>
            
            <button type="submit" style="width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer;">
                💾 Salvar Alterações
            </button>
        </form>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Salvar edição do cliente
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
        
        // Verificar se é cliente
        if (!in_array('cliente_bordados', (array) $cliente->roles)) {
            wp_send_json_error('Este usuário não é um cliente.');
            return;
        }
        
        // Preparar dados
        $nome = sanitize_text_field($_POST['nome']);
        $email = sanitize_email($_POST['email']);
        $programador_padrao = intval($_POST['programador_padrao']);
        $nova_senha = isset($_POST['nova_senha']) ? $_POST['nova_senha'] : '';
        
        // Verificar email duplicado
        if ($email !== $cliente->user_email && email_exists($email)) {
            wp_send_json_error('Este email já está sendo usado por outro usuário.');
            return;
        }
        
        // Atualizar usuário
        $dados_update = array(
            'ID'           => $cliente_id,
            'display_name' => $nome,
            'user_email'   => $email
        );
        
        if (!empty($nova_senha)) {
            $dados_update['user_pass'] = $nova_senha;
        }
        
        $resultado = wp_update_user($dados_update);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error('Erro ao atualizar: ' . $resultado->get_error_message());
            return;
        }
        
        // Atualizar programador padrão
        if ($programador_padrao > 0) {
            update_user_meta($cliente_id, 'programador_padrao', $programador_padrao);
        } else {
            delete_user_meta($cliente_id, 'programador_padrao');
        }
        
        wp_send_json_success(array(
            'message' => 'Cliente atualizado com sucesso!'
        ));
    }
}

// Inicializar
new Bordados_Ajax_Assistente();
