<?php
/**
 * AJAX: Funções do Revisor
 * 
 * Handlers AJAX para o painel do revisor:
 * - Iniciar revisão
 * - Aprovar trabalho
 * - Aprovar trabalho com arquivos revisados
 * - Solicitar acertos com upload de imagens
 * 
 * @package Sistema_Bordados
 * @since 3.2.1
 * @updated 2025-01-09 - Adicionados handlers faltantes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Revisor {
    
    /**
     * Construtor - registra os handlers AJAX
     */
    public function __construct() {
        // Iniciar revisão
        add_action('wp_ajax_iniciar_revisao', array($this, 'iniciar_revisao'));
        
        // Aprovar trabalho (sem arquivos novos)
        add_action('wp_ajax_aprovar_trabalho', array($this, 'aprovar_trabalho'));
        
        // Aprovar trabalho com arquivos revisados
        add_action('wp_ajax_aprovar_trabalho_com_arquivos', array($this, 'aprovar_trabalho_com_arquivos'));
        add_action('wp_ajax_aprovar_trabalho_revisor', array($this, 'aprovar_trabalho_com_arquivos')); // Alias
        
        // Solicitar acertos (ambos os nomes para compatibilidade)
        add_action('wp_ajax_solicitar_acertos', array($this, 'solicitar_acertos'));
        add_action('wp_ajax_solicitar_acertos_revisor', array($this, 'solicitar_acertos'));
    }
    
    /**
     * Verificar permissões (revisor, assistente ou admin)
     */
    private function verificar_permissao() {
        $user = wp_get_current_user();
        $roles_permitidas = array('revisor_bordados', 'assistente_bordados', 'administrator');
        
        foreach ($roles_permitidas as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * AJAX: Iniciar revisão de um trabalho
     */
    public function iniciar_revisao() {
        error_log('=== AJAX: iniciar_revisao ===');
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            error_log('❌ Nonce inválido');
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            error_log('❌ Permissão negada');
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        $revisor_id = get_current_user_id();
        
        error_log("Pedido ID: $pedido_id, Revisor ID: $revisor_id");
        
        if (empty($pedido_id)) {
            wp_send_json_error('ID do pedido inválido.');
            return;
        }
        
        global $wpdb;
        $tabela = 'pedidos_basicos';
        
        // Buscar pedido
        $pedido = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id = %d", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // Verificar se está aguardando revisão
        if ($pedido->status !== 'aguardando_revisao') {
            wp_send_json_error('Este pedido não está aguardando revisão. Status atual: ' . $pedido->status);
            return;
        }
        
        // Atualizar para "em_revisao"
        $resultado = $wpdb->update(
            $tabela,
            array(
                'status' => 'em_revisao',
                'revisor_id' => $revisor_id,
                'data_inicio_revisao' => current_time('mysql')
            ),
            array('id' => $pedido_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        if ($resultado === false) {
            error_log('❌ Erro ao atualizar: ' . $wpdb->last_error);
            wp_send_json_error('Erro ao iniciar revisão: ' . $wpdb->last_error);
            return;
        }
        
        error_log('✅ Revisão iniciada com sucesso');
        wp_send_json_success('Revisão iniciada! Você agora pode revisar este trabalho.');
    }
    
    /**
     * AJAX: Aprovar trabalho (sem arquivos novos - usa os do programador)
     */
    public function aprovar_trabalho() {
        error_log('=== AJAX: aprovar_trabalho ===');
        
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
        $revisor_id = get_current_user_id();
        
        if (empty($pedido_id)) {
            wp_send_json_error('ID do pedido inválido.');
            return;
        }
        
        global $wpdb;
        $tabela = 'pedidos_basicos';
        
        // Buscar pedido
        $pedido = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id = %d", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // Verificar se está em revisão
        if ($pedido->status !== 'em_revisao') {
            wp_send_json_error('Este pedido não está em revisão. Status atual: ' . $pedido->status);
            return;
        }
        
        // Atualizar para "pronto"
        $resultado = $wpdb->update(
            $tabela,
            array(
                'status' => 'pronto',
                'revisor_id' => $revisor_id,
                'data_conclusao' => current_time('mysql'),
                'data_fim_revisao' => current_time('mysql')
            ),
            array('id' => $pedido_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($resultado === false) {
            wp_send_json_error('Erro ao aprovar trabalho: ' . $wpdb->last_error);
            return;
        }
        
        // Enviar email para o cliente
        if (class_exists('Bordados_Emails')) {
            $pedido_atualizado = $wpdb->get_row($wpdb->prepare("
                SELECT p.*, c.user_email as cliente_email, c.display_name as cliente_nome
                FROM $tabela p
                LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
                WHERE p.id = %d
            ", $pedido_id));
            
            if ($pedido_atualizado) {
                Bordados_Emails::enviar_trabalho_pronto($pedido_atualizado);
            }
        }
        
        wp_send_json_success('Trabalho aprovado e entregue ao cliente!');
    }
    
    /**
     * AJAX: Aprovar trabalho com arquivos revisados
     */
    public function aprovar_trabalho_com_arquivos() {
        error_log('=== AJAX: aprovar_trabalho_com_arquivos ===');
        
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
        $revisor_id = get_current_user_id();
        $preco_final = isset($_POST['preco_final']) ? floatval($_POST['preco_final']) : 0;
        $obs_revisor = isset($_POST['obs_revisor_aprovacao']) ? sanitize_textarea_field($_POST['obs_revisor_aprovacao']) : '';
        
        if (empty($pedido_id)) {
            wp_send_json_error('ID do pedido inválido.');
            return;
        }
        
        global $wpdb;
        $tabela = 'pedidos_basicos';
        
        // Buscar pedido
        $pedido = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id = %d", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // Processar upload de arquivos revisados (se houver)
        $arquivos_revisados = array();
        
        if (!empty($_FILES['arquivos_revisados']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $upload_dir = wp_upload_dir();
            $upload_path = $upload_dir['basedir'] . '/bordados-revisados/' . date('Y/m');
            
            if (!file_exists($upload_path)) {
                wp_mkdir_p($upload_path);
            }
            
            $files = $_FILES['arquivos_revisados'];
            
            for ($i = 0; $i < count($files['name']); $i++) {
                if (empty($files['name'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                $file_type = wp_check_filetype($files['name'][$i]);
                $filename = 'revisado-' . $pedido_id . '-' . time() . '-' . ($i + 1) . '.' . $file_type['ext'];
                $destination = $upload_path . '/' . $filename;
                
                if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                    $arquivos_revisados[] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $destination);
                }
            }
        }
        
        // Se não tiver novos arquivos, manter os originais
        $arquivos_finais_atuais = !empty($pedido->arquivos_finais) ? $pedido->arquivos_finais : '[]';
        if (!empty($arquivos_revisados)) {
            // Salvar os arquivos originais como backup
            $backup_arquivos = json_decode($arquivos_finais_atuais, true) ?: array();
            
            // Atualizar com os novos arquivos revisados
            $arquivos_finais_atuais = json_encode($arquivos_revisados);
        }
        
        // Preparar dados para atualização
        $dados = array(
            'status' => 'pronto',
            'revisor_id' => $revisor_id,
            'data_conclusao' => current_time('mysql'),
            'data_fim_revisao' => current_time('mysql')
        );
        $formatos = array('%s', '%d', '%s', '%s');
        
        // Adicionar preço final se informado
        if ($preco_final > 0) {
            $dados['preco_final'] = $preco_final;
            $formatos[] = '%f';
        }
        
        // Adicionar observações do revisor se houver
        if (!empty($obs_revisor)) {
            $dados['obs_revisor'] = $obs_revisor;
            $formatos[] = '%s';
        }
        
        // Atualizar arquivos se houver novos
        if (!empty($arquivos_revisados)) {
            $dados['arquivos_finais'] = json_encode($arquivos_revisados);
            $formatos[] = '%s';
        }
        
        // Atualizar pedido
        $resultado = $wpdb->update(
            $tabela,
            $dados,
            array('id' => $pedido_id),
            $formatos,
            array('%d')
        );
        
        if ($resultado === false) {
            wp_send_json_error('Erro ao aprovar trabalho: ' . $wpdb->last_error);
            return;
        }
        
        // Enviar email para o cliente
        if (class_exists('Bordados_Emails')) {
            $pedido_atualizado = $wpdb->get_row($wpdb->prepare("
                SELECT p.*, c.user_email as cliente_email, c.display_name as cliente_nome
                FROM $tabela p
                LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
                WHERE p.id = %d
            ", $pedido_id));
            
            if ($pedido_atualizado) {
                Bordados_Emails::enviar_trabalho_pronto($pedido_atualizado);
            }
        }
        
        $msg = 'Trabalho aprovado e entregue ao cliente!';
        if (!empty($arquivos_revisados)) {
            $msg .= ' (' . count($arquivos_revisados) . ' arquivo(s) revisado(s) enviado(s))';
        }
        
        wp_send_json_success($msg);
    }
    
    /**
     * AJAX: Solicitar acertos ao programador (com upload de imagens)
     */
    public function solicitar_acertos() {
        error_log('=== AJAX: solicitar_acertos ===');
        
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
        
        // Aceitar ambos os campos para compatibilidade (obs_revisor ou observacoes_revisor)
        $obs_revisor = '';
        if (!empty($_POST['obs_revisor'])) {
            $obs_revisor = sanitize_textarea_field($_POST['obs_revisor']);
        } elseif (!empty($_POST['observacoes_revisor'])) {
            $obs_revisor = sanitize_textarea_field($_POST['observacoes_revisor']);
        }
        
        if (empty($pedido_id)) {
            wp_send_json_error('ID do pedido inválido.');
            return;
        }
        
        if (empty($obs_revisor)) {
            wp_send_json_error('Por favor, descreva os acertos necessários.');
            return;
        }
        
        global $wpdb;
        $tabela = 'pedidos_basicos';
        
        // Verificar se pedido existe e está em revisão
        $pedido = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id = %d", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // Processar upload de imagens
        $imagens_acertos = array();
        
        if (!empty($_FILES['imagens_acertos']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $upload_dir = wp_upload_dir();
            $upload_path = $upload_dir['basedir'] . '/bordados-acertos/' . date('Y/m');
            
            // Criar diretório se não existir
            if (!file_exists($upload_path)) {
                wp_mkdir_p($upload_path);
            }
            
            $files = $_FILES['imagens_acertos'];
            $max_files = 3;
            
            for ($i = 0; $i < min(count($files['name']), $max_files); $i++) {
                if (empty($files['name'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                // Verificar tipo de arquivo
                $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'application/pdf');
                $file_type = wp_check_filetype($files['name'][$i]);
                
                if (!in_array($files['type'][$i], $allowed_types)) {
                    continue;
                }
                
                // Gerar nome único
                $filename = 'acerto-' . $pedido_id . '-' . time() . '-' . ($i + 1) . '.' . $file_type['ext'];
                $destination = $upload_path . '/' . $filename;
                
                // Mover arquivo
                if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                    $imagens_acertos[] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $destination);
                }
            }
        }
        
        // Preparar dados para atualização
        $user = wp_get_current_user();
        $ciclos = intval($pedido->ciclos_acertos) + 1;
        
        $dados = array(
            'status'         => 'em_acertos',
            'obs_revisor'    => $obs_revisor,
            'ciclos_acertos' => $ciclos,
            'revisor_id'     => $user->ID
        );
        
        $formatos = array('%s', '%s', '%d', '%d');
        
        // Se houver imagens, salvar na coluna arquivos_extras como JSON
        if (!empty($imagens_acertos)) {
            // Buscar imagens existentes e adicionar as novas
            $arquivos_extras = !empty($pedido->arquivos_extras) ? json_decode($pedido->arquivos_extras, true) : array();
            
            // Estruturar por ciclo de acertos
            $arquivos_extras['acertos_ciclo_' . $ciclos] = $imagens_acertos;
            
            $dados['arquivos_extras'] = json_encode($arquivos_extras);
            $formatos[] = '%s';
        }
        
        // Atualizar pedido
        $resultado = $wpdb->update(
            $tabela,
            $dados,
            array('id' => $pedido_id),
            $formatos,
            array('%d')
        );
        
        if ($resultado === false) {
            wp_send_json_error('Erro ao solicitar acertos: ' . $wpdb->last_error);
            return;
        }
        
        // Enviar email para o programador
        if (class_exists('Bordados_Emails') && method_exists('Bordados_Emails', 'enviar_acertos_solicitados')) {
            $pedido_atualizado = $wpdb->get_row($wpdb->prepare("
                SELECT p.*, 
                       pr.user_email as programador_email, 
                       pr.display_name as programador_nome,
                       c.display_name as cliente_nome
                FROM $tabela p
                LEFT JOIN {$wpdb->users} pr ON p.programador_id = pr.ID
                LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
                WHERE p.id = %d
            ", $pedido_id));
            
            if ($pedido_atualizado) {
                Bordados_Emails::enviar_acertos_solicitados($pedido_atualizado);
            }
        }
        
        $msg = 'Acertos solicitados ao programador!';
        if (!empty($imagens_acertos)) {
            $msg .= ' (' . count($imagens_acertos) . ' imagem(ns) anexada(s))';
        }
        
        wp_send_json_success($msg);
    }
}

?>
