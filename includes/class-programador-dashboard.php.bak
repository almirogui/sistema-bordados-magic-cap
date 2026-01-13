<?php
/**
 * Classe para Dashboard do Programador
 * 
 * @package Sistema_Bordados_Simples
 * @version 1.2
 * @author Magic Cap Bordados
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe responsável pelo painel do programador otimizado
 */
class Bordados_Programador_Dashboard {
    
    /**
     * Inicializar a classe
     */
    public function __construct() {
        // Registrar shortcode
        add_shortcode('bordados_painel_programador_v2', array($this, 'dashboard_programador_otimizado'));
    }
    
    /**
     * Dashboard do programador com filtros e paginação
     * 
     * @return string HTML do dashboard
     */
    public function dashboard_programador_otimizado() {
        // Verificar autenticação
        if (!is_user_logged_in()) {
            return '<p>Você precisa estar logado para acessar esta página.</p>';
        }
        
        // Verificar permissões
        $user = wp_get_current_user();
        if (!in_array('programador_bordados', $user->roles) && !current_user_can('manage_options')) {
            return '<p>Acesso negado. Apenas programadores podem acessar esta página.</p>';
        }
        
        // Obter parâmetros
        $parametros = $this->obter_parametros_filtros();
        
        // Buscar dados
        $pedidos = $this->buscar_pedidos_programador($user->ID, $parametros);
        $total_pedidos = $this->contar_pedidos_programador($user->ID, $parametros['status_filtro']);
        $contadores = $this->contar_por_status($user->ID);
        
        // Calcular paginação
        $total_pages = ceil($total_pedidos / $parametros['per_page']);
        
        // Gerar HTML
        return $this->gerar_html_dashboard($user, $pedidos, $parametros, $contadores, $total_pedidos, $total_pages);
    }
    
    /**
     * Obter parâmetros de filtros e paginação
     * 
     * @return array Parâmetros processados
     */
    private function obter_parametros_filtros() {
        $status_filtro = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'ativos';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        return array(
            'status_filtro' => $status_filtro,
            'page' => $page,
            'per_page' => $per_page,
            'offset' => $offset
        );
    }
    
    /**
     * Buscar pedidos do programador com filtros
     * 
     * @param int $programador_id ID do programador
     * @param array $parametros Parâmetros de filtro
     * @return array Lista de pedidos
     */
    private function buscar_pedidos_programador($programador_id, $parametros) {
        global $wpdb;
        
        $where_clause = "WHERE programador_id = %d";
        $params = array($programador_id);
        
        // Aplicar filtros de status
        switch ($parametros['status_filtro']) {
            case 'ativos':
                $where_clause .= " AND status IN ('atribuido', 'em_producao')";
                break;
            case 'atribuido':
            case 'em_producao':
            case 'pronto':
                $where_clause .= " AND status = %s";
                $params[] = $parametros['status_filtro'];
                break;
            case 'todos':
                // Mostrar todos os pedidos do programador
                break;
        }
        
        $sql = "SELECT * FROM pedidos_basicos 
                $where_clause 
                ORDER BY 
                    CASE status 
                        WHEN 'atribuido' THEN 1 
                        WHEN 'em_producao' THEN 2 
                        WHEN 'pronto' THEN 3 
                        ELSE 4 
                    END,
                    data_criacao DESC 
                LIMIT %d OFFSET %d";
        
        $params[] = $parametros['per_page'];
        $params[] = $parametros['offset'];
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Contar total de pedidos para paginação
     * 
     * @param int $programador_id ID do programador
     * @param string $status_filtro Filtro de status
     * @return int Total de pedidos
     */
    private function contar_pedidos_programador($programador_id, $status_filtro) {
        global $wpdb;
        
        $where_clause = "WHERE programador_id = %d";
        $params = array($programador_id);
        
        switch ($status_filtro) {
            case 'ativos':
                $where_clause .= " AND status IN ('atribuido', 'em_producao')";
                break;
            case 'atribuido':
            case 'em_producao':
            case 'pronto':
                $where_clause .= " AND status = %s";
                $params[] = $status_filtro;
                break;
        }
        
        $sql = "SELECT COUNT(*) FROM pedidos_basicos $where_clause";
        
        return $wpdb->get_var($wpdb->prepare($sql, $params));
    }
    
    /**
     * Contar pedidos por status para os badges
     * 
     * @param int $programador_id ID do programador
     * @return array Contadores por status
     */
    private function contar_por_status($programador_id) {
        global $wpdb;
        
        $sql = "SELECT status, COUNT(*) as total 
                FROM pedidos_basicos 
                WHERE programador_id = %d 
                GROUP BY status";
        
        $resultados = $wpdb->get_results($wpdb->prepare($sql, $programador_id));
        
        $contadores = array(
            'atribuido' => 0,
            'em_producao' => 0,
            'pronto' => 0
        );
        
        foreach ($resultados as $resultado) {
            if (isset($contadores[$resultado->status])) {
                $contadores[$resultado->status] = (int) $resultado->total;
            }
        }
        
        return $contadores;
    }
    
    /**
     * Gerar HTML completo do dashboard
     * 
     * @param WP_User $user Usuário logado
     * @param array $pedidos Lista de pedidos
     * @param array $parametros Parâmetros de filtros
     * @param array $contadores Contadores por status
     * @param int $total_pedidos Total de pedidos
     * @param int $total_pages Total de páginas
     * @return string HTML do dashboard
     */
    private function gerar_html_dashboard($user, $pedidos, $parametros, $contadores, $total_pedidos, $total_pages) {
        ob_start();
        ?>
        <?php echo $this->gerar_css_dashboard(); ?>
        
        <div class="bordados-dashboard-programador">
            <?php echo $this->gerar_header_dashboard($user); ?>
            <?php echo $this->gerar_cards_estatisticas($contadores); ?>
            <?php echo $this->gerar_filtros_dashboard($parametros['status_filtro'], $contadores); ?>
            <?php echo $this->gerar_lista_pedidos($pedidos, $parametros['status_filtro']); ?>
            <?php echo $this->gerar_paginacao($parametros, $total_pedidos, $total_pages); ?>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Gerar CSS do dashboard
     * 
     * @return string CSS inline - MODULARIZADO v2.0
     */
    private function gerar_css_dashboard() {
        // CSS movido para assets/bordados-modules.css (Fase 2)
        // Seção: DASHBOARD PROGRAMADOR V2
        return '<!-- CSS carregado de bordados-modules.css -->';
    }
    
    /**
     * Gerar header do dashboard
     * 
     * @param WP_User $user Usuário logado
     * @return string HTML do header
     */
    private function gerar_header_dashboard($user) {
        return '
        <div class="dashboard-header">
            <h2>🎯 Painel do Programador</h2>
            <p>Bem-vindo, <strong>' . esc_html($user->display_name) . '</strong></p>
        </div>';
    }
    
    /**
     * Gerar cards de estatísticas
     * 
     * @param array $contadores Contadores por status
     * @return string HTML dos cards
     */
    private function gerar_cards_estatisticas($contadores) {
        $html = '<div class="dashboard-stats">';
        
        $html .= '<div class="stat-card pendentes">';
        $html .= '<div class="stat-number">' . $contadores['atribuido'] . '</div>';
        $html .= '<div class="stat-label">📋 Pendentes</div>';
        $html .= '</div>';
        
        $html .= '<div class="stat-card producao">';
        $html .= '<div class="stat-number">' . $contadores['em_producao'] . '</div>';
        $html .= '<div class="stat-label">⚙️ Em Produção</div>';
        $html .= '</div>';
        
        $html .= '<div class="stat-card concluidos">';
        $html .= '<div class="stat-number">' . $contadores['pronto'] . '</div>';
        $html .= '<div class="stat-label">✅ Concluídos</div>';
        $html .= '</div>';
        
        $html .= '<div class="stat-card total">';
        $html .= '<div class="stat-number">' . array_sum($contadores) . '</div>';
        $html .= '<div class="stat-label">📊 Total</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Gerar filtros do dashboard
     * 
     * @param string $status_filtro Status atual selecionado
     * @param array $contadores Contadores por status
     * @return string HTML dos filtros
     */
    private function gerar_filtros_dashboard($status_filtro, $contadores) {
        $ativos_total = $contadores['atribuido'] + $contadores['em_producao'];
        $total_geral = array_sum($contadores);
        
        $html = '<div class="dashboard-filters">';
        $html .= '<div class="filter-tabs">';
        
        $filtros = array(
            'ativos' => "🔥 Ativos ($ativos_total)",
            'atribuido' => "📋 Pendentes ({$contadores['atribuido']})",
            'em_producao' => "⚙️ Em Produção ({$contadores['em_producao']})",
            'pronto' => "✅ Concluídos ({$contadores['pronto']})",
            'todos' => "📜 Todos ($total_geral)"
        );
        
        foreach ($filtros as $key => $label) {
            $active_class = ($status_filtro === $key) ? ' active' : '';
            $html .= '<a href="?status=' . $key . '" class="filter-tab' . $active_class . '">' . $label . '</a>';
        }
        
        $html .= '</div></div>';
        
        return $html;
    }
    
    /**
     * Gerar lista de pedidos
     * 
     * @param array $pedidos Lista de pedidos
     * @param string $status_filtro Filtro atual
     * @return string HTML da lista
     */
    private function gerar_lista_pedidos($pedidos, $status_filtro) {
        if (empty($pedidos)) {
            return $this->gerar_mensagem_sem_pedidos($status_filtro);
        }
        
        $html = '<div class="pedidos-lista">';
        
        foreach ($pedidos as $pedido) {
            $html .= $this->gerar_card_pedido($pedido);
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Gerar mensagem quando não há pedidos
     * 
     * @param string $status_filtro Filtro atual
     * @return string HTML da mensagem
     */
    private function gerar_mensagem_sem_pedidos($status_filtro) {
        $html = '<div class="no-pedidos">';
        $html .= '<h3>🎉 Nenhum pedido encontrado!</h3>';
        
        if ($status_filtro === 'ativos') {
            $html .= '<p>Você não tem trabalhos ativos no momento. Aproveite para descansar! 😊</p>';
        } else {
            $html .= '<p>Nenhum pedido encontrado para o filtro "' . esc_html($status_filtro) . '".</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Gerar card individual de pedido
     * 
     * @param object $pedido Dados do pedido
     * @return string HTML do card
     */
    private function gerar_card_pedido($pedido) {
        $cliente = get_userdata($pedido->cliente_id);
        
        $html = '<div class="pedido-card status-' . esc_attr($pedido->status) . '">';
        
        // Header
        $html .= '<div class="pedido-header">';
        $html .= '<div class="pedido-info">';
        $html .= '<h4>' . esc_html($pedido->nome_bordado) . '</h4>';
        $html .= '<div class="pedido-meta">';
        $html .= '<span class="pedido-id">#' . $pedido->id . '</span>';
        $html .= '<span>' . esc_html($pedido->tamanho) . '</span>';
        if ($pedido->cores) {
            $html .= '<span>' . esc_html($pedido->cores) . ' cores</span>';
        }
        $html .= '</div></div>';
        
        $html .= '<div class="pedido-status">';
        $html .= $this->gerar_badge_status($pedido->status);
        $html .= '</div></div>';
        
        // Content
        $html .= '<div class="pedido-content">';
        $html .= '<div><strong>Cliente:</strong> ' . esc_html($cliente->display_name) . '</div>';
        
        if ($pedido->observacoes) {
            $html .= '<div class="pedido-observacoes">';
            $html .= '<strong>Observações:</strong> ' . esc_html($pedido->observacoes);
            $html .= '</div>';
        }
        
        $html .= '<div class="pedido-datas">';
        $html .= '<span><strong>Criado:</strong> ' . date('d/m/Y H:i', strtotime($pedido->data_criacao)) . '</span>';
        if ($pedido->data_atribuicao) {
            $html .= '<span><strong>Atribuído:</strong> ' . date('d/m/Y H:i', strtotime($pedido->data_atribuicao)) . '</span>';
        }
        if ($pedido->data_conclusao) {
            $html .= '<span><strong>Concluído:</strong> ' . date('d/m/Y H:i', strtotime($pedido->data_conclusao)) . '</span>';
        }
        $html .= '</div>';
        
        // Preço final para pedidos prontos
        if ($pedido->status === 'pronto' && $pedido->preco_programador) {
            $html .= '<div class="preco-final">';
            $html .= '<strong>💰 Preço Final:</strong> R$ ' . number_format($pedido->preco_programador, 2, ',', '.');
            if ($pedido->observacoes_programador) {
                $html .= '<br><small>📝 ' . esc_html($pedido->observacoes_programador) . '</small>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div></div>';
        
        return $html;
    }
    
    /**
     * Gerar badge de status
     * 
     * @param string $status Status do pedido
     * @return string HTML do badge
     */
    private function gerar_badge_status($status) {
        $badges = array(
            'atribuido' => '<span class="status-badge atribuido">📋 Pendente</span>',
            'em_producao' => '<span class="status-badge em-producao">⚙️ Em Produção</span>',
            'pronto' => '<span class="status-badge pronto">✅ Concluído</span>'
        );
        
        return isset($badges[$status]) ? $badges[$status] : '<span class="status-badge">❓ ' . $status . '</span>';
    }
    
    /**
     * Gerar paginação
     * 
     * @param array $parametros Parâmetros atuais
     * @param int $total_pedidos Total de pedidos
     * @param int $total_pages Total de páginas
     * @return string HTML da paginação
     */
    private function gerar_paginacao($parametros, $total_pedidos, $total_pages) {
        if ($total_pages <= 1) {
            return '';
        }
        
        $current_url = remove_query_arg('paged');
        $current_url = add_query_arg('status', $parametros['status_filtro'], $current_url);
        
        $html = '<div class="dashboard-pagination">';
        
        if ($parametros['page'] > 1) {
            $prev_url = add_query_arg('paged', $parametros['page'] - 1, $current_url);
            $html .= '<a href="' . $prev_url . '" class="page-btn">← Anterior</a>';
        }
        
        $html .= '<span class="page-info">';
        $html .= 'Página ' . $parametros['page'] . ' de ' . $total_pages . ' ';
        $html .= '(' . $total_pedidos . ' pedidos)';
        $html .= '</span>';
        
        if ($parametros['page'] < $total_pages) {
            $next_url = add_query_arg('paged', $parametros['page'] + 1, $current_url);
            $html .= '<a href="' . $next_url . '" class="page-btn">Próxima →</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}

// Inicializar a classe
new Bordados_Programador_Dashboard();