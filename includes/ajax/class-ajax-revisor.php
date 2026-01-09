<?php
/**
 * AJAX: Funções do Revisor
 * 
 * Handlers AJAX para o painel do revisor:
 * - Solicitar acertos com upload de imagens
 * 
 * @package Sistema_Bordados
 * @since 3.2.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Revisor {
    
    /**
     * Construtor - registra os handlers AJAX
     */
    public function __construct() {
        // Solicitar acertos
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
     * AJAX: Solicitar acertos ao programador (com upload de imagens)
     */
    public function solicitar_acertos() {
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
        $obs_revisor = sanitize_textarea_field($_POST['obs_revisor']);
        
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
            $arquivos_extras['acertos_ciclo_' . $ciclos] = array(
                'data'    => current_time('mysql'),
                'revisor' => $user->display_name,
                'obs'     => $obs_revisor,
                'imagens' => $imagens_acertos
            );
            
            $dados['arquivos_extras'] = json_encode($arquivos_extras, JSON_UNESCAPED_UNICODE);
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
            wp_send_json_error('Erro ao atualizar pedido: ' . $wpdb->last_error);
            return;
        }
        
        // Tentar enviar notificação ao programador
        if (class_exists('Bordados_Emails') && method_exists('Bordados_Emails', 'notificar_programador_acertos')) {
            Bordados_Emails::notificar_programador_acertos($pedido_id, $obs_revisor, $imagens_acertos);
        }
        
        $msg = 'Solicitação de acertos enviada ao programador.';
        if (!empty($imagens_acertos)) {
            $msg .= ' (' . count($imagens_acertos) . ' imagem(ns) anexada(s))';
        }
        
        wp_send_json_success($msg);
    }
}
