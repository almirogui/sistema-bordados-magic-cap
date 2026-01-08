<?php
/**
 * AJAX: Funções do Administrador
 * Extraído de class-ajax.php na Fase 4 da modularização
 * 
 * Funções:
 * - atribuir_pedido
 * - buscar_programador_com_menos_trabalhos (helper)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Admin {
    
    public function __construct() {
        add_action('wp_ajax_atribuir_pedido', array($this, 'atribuir_pedido'));
    }
    
    public function atribuir_pedido() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        // Validar dados
        $pedido_id = intval($_POST['pedido_id']);
        $programador_id = intval($_POST['programador_id']);
        
        if (empty($pedido_id) || empty($programador_id)) {
            wp_send_json_error('Dados inválidos.');
            return;
        }
        
        // Verificar se o programador existe e tem a role correta
        $programador = get_userdata($programador_id);
        if (!$programador || (!in_array('programador_bordados', $programador->roles) && !in_array('administrator', $programador->roles))) {
            wp_send_json_error('Programador inválido.');
            return;
        }
        
        // Verificar se o pedido existe e está disponível
        $pedido = Bordados_Database::buscar_pedido($pedido_id);
        
        if (!$pedido || $pedido->status !== 'novo') {
            wp_send_json_error('Pedido não encontrado ou já foi atribuído.');
            return;
        }
        
        // Atualizar o pedido
        $resultado = Bordados_Database::atualizar_pedido(
            $pedido_id,
            array(
                'programador_id' => $programador_id,
                'status' => 'atribuido',
                'data_atribuicao' => current_time('mysql')
            ),
            array('%d', '%s', '%s')
        );
        
        if ($resultado === false) {
            wp_send_json_error('Erro ao atribuir pedido.');
            return;
        }
        
        // Enviar email para o programador
        Bordados_Emails::enviar_novo_trabalho($programador_id, $pedido_id, (array)$pedido);
        
        wp_send_json_success(array(
            'message' => 'Pedido #' . $pedido_id . ' atribuído com sucesso para ' . $programador->display_name . '!',
            'pedido_id' => $pedido_id,
            'programador' => $programador->display_name
        ));
    }
    
    /**
     * AJAX: Iniciar produção
     */

private function buscar_programador_com_menos_trabalhos() {
    error_log("=== BUSCANDO PROGRAMADOR COM MENOS TRABALHOS ===");
    
    // Buscar todos os usuários com role programador_bordados
    $args = array(
        'role' => 'programador_bordados',
        'orderby' => 'ID'
    );
    
    $programadores = get_users($args);
    
    if (empty($programadores)) {
        error_log("❌ Nenhum programador encontrado no sistema");
        return null;
    }
    
    error_log("✅ " . count($programadores) . " programador(es) encontrado(s)");
    
    global $wpdb;
    $table_name = 'pedidos_basicos'; // Tabela sem prefixo (padrão do plugin)
    
    $programador_escolhido = null;
    $menor_quantidade = PHP_INT_MAX;
    
    foreach ($programadores as $prog) {
        // Verificar se programador está ativo
        $ativo = get_user_meta($prog->ID, 'programador_ativo', true);
        
        if ($ativo === 'no') {
            error_log("⏭️ Programador {$prog->display_name} (ID: {$prog->ID}) está INATIVO. Pulando.");
            continue;
        }
        
        // Contar trabalhos pendentes (atribuido, em_producao, em_acertos)
        $trabalhos_pendentes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE programador_id = %d 
            AND status IN ('atribuido', 'em_producao', 'em_acertos')",
            $prog->ID
        ));
        
        error_log("👨‍💻 Programador: {$prog->display_name} (ID: {$prog->ID}) - Trabalhos pendentes: {$trabalhos_pendentes}");
        
        // Escolher o que tem menos trabalhos
        if ($trabalhos_pendentes < $menor_quantidade) {
            $menor_quantidade = $trabalhos_pendentes;
            $programador_escolhido = $prog->ID;
        }
    }
    
    if ($programador_escolhido) {
        $prog_obj = get_userdata($programador_escolhido);
        error_log("✅ ESCOLHIDO: {$prog_obj->display_name} (ID: {$programador_escolhido}) com {$menor_quantidade} trabalho(s) pendente(s)");
    } else {
        error_log("❌ Nenhum programador ativo disponível");
    }
    
    return $programador_escolhido;
}

}

?>
