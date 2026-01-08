<?php
/**
 * Classe para atribuição automática inteligente de trabalhos
 * 
 * Lógica:
 * 1. Se cliente tem programador padrão → atribui para ele
 * 2. Se cliente NÃO tem programador padrão:
 *    - Busca programadores ATIVOS
 *    - Conta trabalhos pendentes de cada um
 *    - Atribui para quem tem MENOS trabalhos
 * 
 * @package Sistema_Bordados
 * @since 2.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Atribuicao_Automatica {
    
    /**
     * Atribuir trabalho automaticamente
     * 
     * @param int $pedido_id ID do pedido
     * @param int $cliente_id ID do cliente
     * @return int|false ID do programador atribuído ou false se falhar
     */
    public static function atribuir_trabalho($pedido_id, $cliente_id) {
        global $wpdb;
        
        // ETAPA 1: Verificar se cliente tem programador padrão
        $programador_padrao = get_user_meta($cliente_id, 'programador_padrao', true);
        
        if (!empty($programador_padrao)) {
            // Cliente tem programador padrão
            // Verificar se programador ainda está ativo
            $programador_ativo = get_user_meta($programador_padrao, 'programador_ativo', true);
            
            if ($programador_ativo === 'yes' || empty($programador_ativo)) {
                // Programador padrão está ativo
                return self::atribuir_para_programador($pedido_id, $programador_padrao);
            } else {
                // Programador padrão está INATIVO
                // Avisar admin e usar atribuição automática
                error_log("Bordados: Programador padrão (ID: {$programador_padrao}) do cliente (ID: {$cliente_id}) está inativo. Usando atribuição automática.");
            }
        }
        
        // ETAPA 2: Cliente NÃO tem programador padrão (ou está inativo)
        // Buscar programador com menos trabalhos
        $programador_id = self::buscar_programador_disponivel();
        
        if ($programador_id) {
            return self::atribuir_para_programador($pedido_id, $programador_id);
        }
        
        // Nenhum programador disponível
        error_log("Bordados: Nenhum programador ativo disponível para o pedido ID: {$pedido_id}");
        return false;
    }
    
    /**
     * Buscar programador disponível com menos trabalhos
     * 
     * @param string $tipo_servico 'bordado' ou 'vetor' (futuro)
     * @return int|false ID do programador ou false
     */
    public static function buscar_programador_disponivel($tipo_servico = 'bordado') {
        global $wpdb;
        
        // Buscar programadores ativos
        $query = "
            SELECT u.ID, u.display_name,
                COUNT(p.id) as trabalhos_pendentes
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            LEFT JOIN {$wpdb->usermeta} um_ativo ON u.ID = um_ativo.user_id 
                AND um_ativo.meta_key = 'programador_ativo'
            LEFT JOIN pedidos_basicos p ON u.ID = p.programador_id 
                AND p.status IN ('atribuido', 'em_producao')
            WHERE um.meta_key = '{$wpdb->prefix}capabilities'
            AND um.meta_value LIKE '%programador_bordados%'
            AND (um_ativo.meta_value = 'yes' OR um_ativo.meta_value IS NULL)
        ";
        
        // FUTURO: Filtrar por tipo de serviço
        if ($tipo_servico === 'vetor') {
            $query .= "
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->usermeta} um_vetor
                    WHERE um_vetor.user_id = u.ID
                    AND um_vetor.meta_key = 'programador_faz_vetorizacao'
                    AND um_vetor.meta_value = 'yes'
                )
            ";
        }
        
        $query .= "
            GROUP BY u.ID
            ORDER BY trabalhos_pendentes ASC, RAND()
            LIMIT 1
        ";
        
        $programador = $wpdb->get_row($query);
        
        if ($programador) {
            error_log("Bordados: Atribuição automática - Programador selecionado: {$programador->display_name} (ID: {$programador->ID}) com {$programador->trabalhos_pendentes} trabalhos pendentes");
            return $programador->ID;
        }
        
        return false;
    }
    
    /**
     * Atribuir pedido para um programador específico
     * 
     * @param int $pedido_id ID do pedido
     * @param int $programador_id ID do programador
     * @return int|false ID do programador ou false
     */
    private static function atribuir_para_programador($pedido_id, $programador_id) {
        global $wpdb;
        
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
        
        if ($atualizado) {
            // Buscar nome do programador para log
            $programador = get_userdata($programador_id);
            $programador_nome = $programador ? $programador->display_name : "ID: {$programador_id}";
            
            error_log("Bordados: Pedido ID {$pedido_id} atribuído para programador {$programador_nome}");
            
            // Enviar email para programador
            self::enviar_email_atribuicao($pedido_id, $programador_id);
            
            return $programador_id;
        }
        
        return false;
    }
    
    /**
     * Enviar email de notificação ao programador
     * 
     * @param int $pedido_id ID do pedido
     * @param int $programador_id ID do programador
     */
    private static function enviar_email_atribuicao($pedido_id, $programador_id) {
        // Verificar se classe de emails existe
        if (!class_exists('Bordados_Emails')) {
            return;
        }
        
        global $wpdb;
        
        // Buscar dados do pedido
        $pedido = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM pedidos_basicos WHERE id = %d",
            $pedido_id
        ));
        
        if (!$pedido) {
            return;
        }
        
        // Buscar dados do programador
        $programador = get_userdata($programador_id);
        if (!$programador) {
            return;
        }
        
        // Buscar dados do cliente
        $cliente = get_userdata($pedido->cliente_id);
        $cliente_nome = $cliente ? $cliente->display_name : 'Cliente';
        
        // Enviar email
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
    }
    
    /**
     * Obter lista de programadores ativos e seus trabalhos
     * 
     * @return array Lista de programadores
     */
    public static function listar_programadores_status() {
        global $wpdb;
        
        $query = "
            SELECT 
                u.ID,
                u.display_name as nome,
                u.user_email as email,
                COALESCE(um_ativo.meta_value, 'yes') as ativo,
                COALESCE(um_vetor.meta_value, 'no') as faz_vetorizacao,
                COUNT(CASE WHEN p.status IN ('atribuido', 'em_producao') THEN 1 END) as trabalhos_pendentes,
                COUNT(CASE WHEN p.status = 'pronto' THEN 1 END) as trabalhos_concluidos
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            LEFT JOIN {$wpdb->usermeta} um_ativo ON u.ID = um_ativo.user_id 
                AND um_ativo.meta_key = 'programador_ativo'
            LEFT JOIN {$wpdb->usermeta} um_vetor ON u.ID = um_vetor.user_id 
                AND um_vetor.meta_key = 'programador_faz_vetorizacao'
            LEFT JOIN pedidos_basicos p ON u.ID = p.programador_id
            WHERE um.meta_key = '{$wpdb->prefix}capabilities'
            AND um.meta_value LIKE '%programador_bordados%'
            GROUP BY u.ID, u.display_name, u.user_email, um_ativo.meta_value, um_vetor.meta_value
            ORDER BY 
                CASE WHEN COALESCE(um_ativo.meta_value, 'yes') = 'yes' THEN 0 ELSE 1 END,
                trabalhos_pendentes ASC
        ";
        
        return $wpdb->get_results($query);
    }
}
