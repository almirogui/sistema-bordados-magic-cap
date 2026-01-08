<?php
/**
 * Hook para atribuição automática na criação de pedidos
 * 
 * Quando um pedido é criado, verifica se o cliente tem
 * atribuição automática habilitada e atribui imediatamente.
 * 
 * @package Sistema_Bordados
 * @since 2.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Hook_Criacao_Pedido {
    
    public function __construct() {
        // Hook após criar pedido
        add_action('bordados_pedido_criado', array($this, 'verificar_atribuicao_automatica'), 10, 2);
    }
    
    /**
     * Verificar se deve atribuir automaticamente
     * 
     * @param int $pedido_id ID do pedido criado
     * @param int $cliente_id ID do cliente
     */
    public function verificar_atribuicao_automatica($pedido_id, $cliente_id) {
        global $wpdb;
        
        // Log
        error_log("Bordados: Verificando atribuição automática para pedido #{$pedido_id}");
        
        // Verificar se pedido existe
        $pedido = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM pedidos_basicos WHERE id = %d",
            $pedido_id
        ));
        
        if (!$pedido) {
            error_log("Bordados: Pedido #{$pedido_id} não encontrado");
            return false;
        }
        
        // Se já tem programador atribuído, não fazer nada
        if (!empty($pedido->programador_id)) {
            error_log("Bordados: Pedido #{$pedido_id} já tem programador atribuído (ID: {$pedido->programador_id})");
            return false;
        }
        
        // PRIORIDADE 1: Verificar se cliente tem programador padrão
        $programador_padrao = get_user_meta($cliente_id, 'programador_padrao', true);
        
        if (!empty($programador_padrao)) {
            // Verificar se programador padrão está ativo
            $programador_ativo = get_user_meta($programador_padrao, 'programador_ativo', true);
            
            if ($programador_ativo === 'yes' || empty($programador_ativo)) {
                // Atribuir para programador padrão
                error_log("Bordados: Cliente tem programador padrão (ID: {$programador_padrao}). Atribuindo...");
                $this->atribuir_pedido($pedido_id, $programador_padrao, $cliente_id);
                return true;
            } else {
                error_log("Bordados: Programador padrão (ID: {$programador_padrao}) está INATIVO. Tentando atribuição automática...");
            }
        }
        
        // PRIORIDADE 2: Verificar se cliente tem atribuição automática habilitada
        $atribuicao_automatica = get_user_meta($cliente_id, 'atribuicao_automatica', true);
        
        if ($atribuicao_automatica !== 'yes') {
            error_log("Bordados: Cliente NÃO tem atribuição automática habilitada. Pedido ficará aguardando atribuição manual.");
            return false;
        }
        
        // Cliente tem atribuição automática! Buscar programador disponível
        error_log("Bordados: Cliente TEM atribuição automática. Buscando programador disponível...");
        
        if (!class_exists('Bordados_Atribuicao_Automatica')) {
            error_log("Bordados: ERRO - Classe Bordados_Atribuicao_Automatica não encontrada!");
            return false;
        }
        
        $programador_id = Bordados_Atribuicao_Automatica::buscar_programador_disponivel();
        
        if (!$programador_id) {
            error_log("Bordados: NENHUM programador ativo disponível! Pedido ficará aguardando.");
            return false;
        }
        
        // Atribuir para programador encontrado
        $this->atribuir_pedido($pedido_id, $programador_id, $cliente_id);
        return true;
    }
    
    /**
     * Atribuir pedido para um programador
     * 
     * @param int $pedido_id ID do pedido
     * @param int $programador_id ID do programador
     * @param int $cliente_id ID do cliente
     */
    private function atribuir_pedido($pedido_id, $programador_id, $cliente_id) {
        global $wpdb;
        
        // Buscar dados do programador
        $programador = get_userdata($programador_id);
        $programador_nome = $programador ? $programador->display_name : "ID: {$programador_id}";
        
        // Atualizar pedido
        $atualizado = $wpdb->update(
            'pedidos_basicos',
            array(
                'programador_id' => $programador_id,
                'status' => 'atribuido',
                'data_atribuicao' => current_time('mysql')
            ),
            array('id' => $pedido_id),
            array('%d', '%s', '%s'),
            array('%d')
        );
        
        if ($atualizado === false) {
            error_log("Bordados: ERRO ao atualizar pedido #{$pedido_id} no banco de dados!");
            return false;
        }
        
        error_log("Bordados: ✅ Pedido #{$pedido_id} atribuído AUTOMATICAMENTE para {$programador_nome} (ID: {$programador_id})");
        
        // Enviar email para programador
        $this->enviar_email_programador($pedido_id, $programador_id, $cliente_id);
        
        return true;
    }
    
    /**
     * Enviar email de notificação ao programador
     */
    private function enviar_email_programador($pedido_id, $programador_id, $cliente_id) {
        // Verificar se classe de emails existe
        if (!class_exists('Bordados_Emails')) {
            error_log("Bordados: Classe Bordados_Emails não encontrada. Email não será enviado.");
            return false;
        }
        
        global $wpdb;
        
        // Buscar dados do pedido
        $pedido = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM pedidos_basicos WHERE id = %d",
            $pedido_id
        ));
        
        if (!$pedido) {
            return false;
        }
        
        // Buscar dados do programador
        $programador = get_userdata($programador_id);
        if (!$programador) {
            return false;
        }
        
        // Buscar dados do cliente
        $cliente = get_userdata($cliente_id);
        $cliente_nome = $cliente ? $cliente->display_name : 'Cliente';
        
        // Enviar email
        try {
            Bordados_Emails::enviar_email_novo_trabalho(
                $programador->user_email,
                $programador->display_name,
                array(
                    'pedido_id' => $pedido_id,
                    'cliente_nome' => $cliente_nome,
                    'nome_bordado' => $pedido->nome_bordado,
                    'observacoes' => $pedido->observacoes
                )
            );
            
            error_log("Bordados: ✅ Email enviado para {$programador->display_name} ({$programador->user_email})");
        } catch (Exception $e) {
            error_log("Bordados: ERRO ao enviar email: " . $e->getMessage());
        }
        
        return true;
    }
}

// Inicializar
new Bordados_Hook_Criacao_Pedido();
