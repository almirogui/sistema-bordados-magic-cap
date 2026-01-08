<?php
/**
 * Classe para gerenciar shortcodes - MODULARIZADO v3.0
 * 
 * Este arquivo agora é apenas um "loader" que carrega os arquivos
 * de shortcode individuais e faz proxy para as classes específicas.
 * 
 * Arquivos de shortcode em includes/shortcodes/:
 * - class-shortcode-meus-pedidos.php     → [bordados_meus_pedidos]
 * - class-shortcode-meus-trabalhos.php   → [bordados_meus_trabalhos]
 * - class-shortcode-admin-pedidos.php    → [bordados_admin_pedidos]
 * - class-shortcode-painel-revisor.php   → [bordados_painel_revisor]
 * - class-shortcode-embaixador.php       → [bordados_dashboard_embaixador]
 * - class-shortcode-painel-assistente.php → [bordados_painel_assistente]
 * - class-shortcode-novo-pedido.php      → [bordados_novo_pedido]
 * - class-shortcode-login.php            → [bordados_login]
 */

if (!defined('ABSPATH')) {
    exit;
}

// Carregar arquivos de shortcode
require_once BORDADOS_PLUGIN_PATH . 'includes/shortcodes/class-shortcode-meus-pedidos.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/shortcodes/class-shortcode-meus-trabalhos.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/shortcodes/class-shortcode-admin-pedidos.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/shortcodes/class-shortcode-painel-revisor.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/shortcodes/class-shortcode-embaixador.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/shortcodes/class-shortcode-painel-assistente.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/shortcodes/class-shortcode-novo-pedido.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/shortcodes/class-shortcode-login.php';

class Bordados_Shortcodes {
    
    /**
     * Dashboard do Cliente - [bordados_meus_pedidos]
     */
    public static function dashboard_cliente($atts) {
        return Bordados_Shortcode_Meus_Pedidos::render($atts);
    }
    
    /**
     * Dashboard do Programador - [bordados_meus_trabalhos]
     */
    public static function dashboard_programador($atts) {
        return Bordados_Shortcode_Meus_Trabalhos::render($atts);
    }
    
    /**
     * Dashboard do Admin - [bordados_admin_pedidos]
     */
    public static function dashboard_admin($atts) {
        return Bordados_Shortcode_Admin_Pedidos::render($atts);
    }
    
    /**
     * Painel do Revisor - [bordados_painel_revisor]
     */
    public static function dashboard_revisor($atts) {
        return Bordados_Shortcode_Painel_Revisor::render($atts);
    }
    
    /**
     * Dashboard do Embaixador - [bordados_dashboard_embaixador]
     */
    public static function dashboard_embaixador($atts) {
        return Bordados_Shortcode_Embaixador::render($atts);
    }
    
    /**
     * Painel da Assistente - [bordados_painel_assistente]
     */
    public static function dashboard_assistente($atts) {
        return Bordados_Shortcode_Painel_Assistente::render($atts);
    }
    
    /**
     * Formulário Novo Pedido - [bordados_novo_pedido]
     */
    public static function formulario_novo_pedido($atts) {
        return Bordados_Shortcode_Novo_Pedido::render($atts);
    }
    
    /**
     * Formulário de Login - [bordados_login]
     */
    public static function formulario_login($atts) {
        return Bordados_Shortcode_Login::render($atts);
    }
    
    /**
     * AJAX: Buscar detalhes do pedido para modal admin
     */
    public static function ajax_buscar_detalhes_pedido_admin() {
        // Verificar nonce - aceita ambos os nomes para compatibilidade
        $nonce_valido = false;
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'bordados_admin_nonce') || 
                wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
                $nonce_valido = true;
            }
        }
        
        if (!$nonce_valido) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Verificar se é admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        
        if (!$pedido_id) {
            wp_send_json_error('ID do pedido inválido');
            return;
        }
        
        global $wpdb;
        
        // Buscar pedido com dados do cliente e programador
        $pedido = $wpdb->get_row($wpdb->prepare("
            SELECT p.*,
                   c.display_name as cliente_nome,
                   c.user_email as cliente_email,
                   pr.display_name as programador_nome,
                   pr.user_email as programador_email
            FROM pedidos_basicos p
            LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
            LEFT JOIN {$wpdb->users} pr ON p.programador_id = pr.ID
            WHERE p.id = %d
        ", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado');
            return;
        }
        
        // Decodificar arquivos JSON
        $arquivos_cliente = !empty($pedido->arquivos_cliente) ? json_decode($pedido->arquivos_cliente, true) : array();
        $arquivos_finais = !empty($pedido->arquivos_finais) ? json_decode($pedido->arquivos_finais, true) : array();
        
        // Retornar dados
        wp_send_json_success(array(
            'id' => $pedido->id,
            'nome_bordado' => $pedido->nome_bordado,
            'status' => $pedido->status,
            'tamanho' => $pedido->tamanho,
            'cores' => $pedido->cores,
            'observacoes' => $pedido->observacoes,
            'observacoes_programador' => $pedido->observacoes_programador,
            'preco_programador' => $pedido->preco_programador,
            'cliente' => array(
                'nome' => $pedido->cliente_nome, 
                'email' => $pedido->cliente_email
            ),
            'programador' => array(
                'nome' => $pedido->programador_nome, 
                'email' => $pedido->programador_email
            ),
            'datas' => array(
                'criacao' => $pedido->data_criacao, 
                'atribuicao' => $pedido->data_atribuicao, 
                'conclusao' => $pedido->data_conclusao
            ),
            'arquivos_cliente' => $arquivos_cliente,
            'arquivos_finais' => $arquivos_finais
        ));
    }
}

?>
