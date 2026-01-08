<?php
/**
 * AJAX: Funções de Orçamento
 * VERSÃO CORRIGIDA - Bugs de chamada de função resolvidos
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Orcamento {
    
    public function __construct() {
        // Ações do Revisor
        add_action('wp_ajax_calcular_preco_orcamento', array($this, 'calcular_preco_orcamento'));
        add_action('wp_ajax_enviar_orcamento', array($this, 'enviar_orcamento'));
        
        // Ações do Cliente
        add_action('wp_ajax_aprovar_orcamento', array($this, 'aprovar_orcamento'));
        add_action('wp_ajax_recusar_orcamento', array($this, 'recusar_orcamento'));
    }
    
    /**
     * Calcular preço do orçamento baseado nos pontos
     */
    public function calcular_preco_orcamento() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        // Verificar permissão (revisor ou admin)
        if (!current_user_can('administrator') && !in_array('revisor_bordados', wp_get_current_user()->roles)) {
            wp_send_json_error(array('message' => 'Access denied'));
            return;
        }
        
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        $pontos = isset($_POST['pontos']) ? intval($_POST['pontos']) : 0;
        
        if ($pontos <= 0) {
            wp_send_json_error(array('message' => 'Invalid stitch count'));
            return;
        }
        
        // Carregar classe de preços
        if (!class_exists('Bordados_Precos')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'class-precos.php';
        }
        
        // Calcular preço
        $resultado = Bordados_Precos::calcular_preco_final($cliente_id, $pontos, '', '', 0);
        
        wp_send_json_success(array(
            'preco_final' => $resultado['preco_final'],
            'sistema_usado' => $resultado['sistema_usado'],
            'detalhes' => $resultado['detalhes_calculo']
        ));
    }
    
    /**
     * Enviar orçamento para o cliente
     */
    public function enviar_orcamento() {
        error_log('=== ENVIAR ORÇAMENTO ===');
        
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        // Verificar permissão
        $user = wp_get_current_user();
        if (!current_user_can('administrator') && !in_array('revisor_bordados', $user->roles)) {
            wp_send_json_error(array('message' => 'Access denied'));
            return;
        }
        
        $pedido_id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;
        $numero_pontos = isset($_POST['numero_pontos']) ? intval($_POST['numero_pontos']) : 0;
        $preco_final = isset($_POST['preco_final']) ? floatval($_POST['preco_final']) : 0;
        $obs_revisor = isset($_POST['obs_revisor']) ? sanitize_textarea_field($_POST['obs_revisor']) : '';
        
        if (!$pedido_id || !$numero_pontos || !$preco_final) {
            wp_send_json_error(array('message' => 'Please fill in all required fields'));
            return;
        }
        
        // Buscar pedido
        $pedido = Bordados_Database::buscar_pedido_para_orcamento($pedido_id);
        
        if (!$pedido) {
            wp_send_json_error(array('message' => 'Order not found'));
            return;
        }
        
        if ($pedido->status !== 'orcamento_pendente') {
            wp_send_json_error(array('message' => 'This order is not pending quote'));
            return;
        }
        
        // Carregar classe de preços para calcular preço base
        if (!class_exists('Bordados_Precos')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'class-precos.php';
        }
        
        $calculo = Bordados_Precos::calcular_preco_final($pedido->cliente_id, $numero_pontos, '', '', 0);
        $preco_base = $calculo['preco_final'];
        $ajuste = $preco_final - $preco_base;
        
        // Atualizar pedido
        $dados = array(
            'revisor_id' => $user->ID,
            'numero_pontos' => $numero_pontos,
            'preco_final' => $preco_final,
            'sistema_preco_usado' => $calculo['sistema_usado'],
            'preco_base_calculado' => $preco_base,
            'ajuste_manual_preco' => $ajuste != 0 ? $ajuste : null,
            'motivo_ajuste_preco' => $ajuste != 0 ? 'Manual adjustment by reviewer' : null,
            'obs_revisor' => $obs_revisor
        );
        
        $resultado = Bordados_Database::enviar_orcamento($pedido_id, $dados);
        
        if ($resultado === false) {
            wp_send_json_error(array('message' => 'Error updating order'));
            return;
        }
        
        // ✅ BUG CORRIGIDO: Enviar email ao cliente com array correto
        if (class_exists('Bordados_Emails')) {
            $dados_orcamento = array(
                'numero_pontos' => $numero_pontos,
                'preco_final' => $preco_final,
                'obs_revisor' => $obs_revisor
            );
            Bordados_Emails::enviar_orcamento_cliente($pedido_id, $dados_orcamento);
        }
        
        error_log("✅ Orçamento enviado para pedido #{$pedido_id}");
        
        wp_send_json_success(array(
            'message' => 'Quote sent successfully!',
            'pedido_id' => $pedido_id
        ));
    }
    
    /**
     * Cliente aprova orçamento
     */
    public function aprovar_orcamento() {
        error_log('=== APROVAR ORÇAMENTO ===');
        
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please login'));
            return;
        }
        
        $pedido_id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;
        $cliente_id = get_current_user_id();
        
        // Buscar pedido
        $pedido = Bordados_Database::buscar_pedido_para_orcamento($pedido_id);
        
        if (!$pedido) {
            wp_send_json_error(array('message' => 'Order not found'));
            return;
        }
        
        // Verificar se é o dono do pedido
        if ($pedido->cliente_id != $cliente_id && !current_user_can('administrator')) {
            wp_send_json_error(array('message' => 'Access denied'));
            return;
        }
        
        if ($pedido->status !== 'orcamento_enviado') {
            wp_send_json_error(array('message' => 'This quote cannot be approved'));
            return;
        }
        
        // Aprovar orçamento
        $resultado = Bordados_Database::aprovar_orcamento($pedido_id);
        
        if ($resultado === false) {
            wp_send_json_error(array('message' => 'Error approving quote'));
            return;
        }
        
        // ✅ BUG CORRIGIDO: Enviar notificação para admin (só 1 parâmetro)
        if (class_exists('Bordados_Emails')) {
            Bordados_Emails::notificar_orcamento_aprovado($pedido_id);
        }
        
        error_log("✅ Orçamento #{$pedido_id} aprovado pelo cliente");
        
        wp_send_json_success(array(
            'message' => 'Quote approved! Your order is now in the queue.',
            'pedido_id' => $pedido_id
        ));
    }
    
    /**
     * Cliente recusa orçamento
     */
    public function recusar_orcamento() {
        error_log('=== RECUSAR ORÇAMENTO ===');
        
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please login'));
            return;
        }
        
        $pedido_id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;
        $motivo = isset($_POST['motivo']) ? sanitize_textarea_field($_POST['motivo']) : '';
        $cliente_id = get_current_user_id();
        
        // Buscar pedido
        $pedido = Bordados_Database::buscar_pedido_para_orcamento($pedido_id);
        
        if (!$pedido) {
            wp_send_json_error(array('message' => 'Order not found'));
            return;
        }
        
        // Verificar se é o dono do pedido
        if ($pedido->cliente_id != $cliente_id && !current_user_can('administrator')) {
            wp_send_json_error(array('message' => 'Access denied'));
            return;
        }
        
        if ($pedido->status !== 'orcamento_enviado') {
            wp_send_json_error(array('message' => 'This quote cannot be declined'));
            return;
        }
        
        // Recusar orçamento
        $resultado = Bordados_Database::recusar_orcamento($pedido_id, $motivo);
        
        if ($resultado === false) {
            wp_send_json_error(array('message' => 'Error declining quote'));
            return;
        }
        
        error_log("❌ Orçamento #{$pedido_id} recusado pelo cliente");
        
        wp_send_json_success(array(
            'message' => 'Quote declined.',
            'pedido_id' => $pedido_id
        ));
    }
}
