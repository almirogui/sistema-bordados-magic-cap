<?php
/**
 * Shortcode: Painel da Assistente - [bordados_painel_assistente]
 * 
 * Dashboard para funcionários operarem o sistema sem acesso ao WordPress.
 * Funcionalidades: ver pedidos, atribuir, editar, cadastrar clientes.
 * 
 * @package Sistema_Bordados
 * @since 3.2.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Shortcode_Painel_Assistente {
    
    // Configurações
    const PEDIDOS_POR_PAGINA = 50;
    const DIAS_PRONTOS = 15;
    
    /**
     * Renderizar painel da assistente
     */
    public static function render($atts) {
        // Verificar login
        if (!is_user_logged_in()) {
            return '<p>Você precisa estar logado para acessar este painel.</p>';
        }
        
        $user = wp_get_current_user();
        
        // Verificar permissão (assistente ou admin)
        if (!in_array('assistente_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
            return '<p>Acesso restrito a assistentes.</p>';
        }
        
        // Determinar qual aba mostrar
        $aba_ativa = isset($_GET['aba']) ? sanitize_text_field($_GET['aba']) : 'pedidos';
        
        // Buscar dados
        $contadores = self::buscar_contadores();
        $programadores = Bordados_Helpers::listar_programadores();
        
        // URL de logout
        $logout_url = wp_logout_url(site_url('/login/'));
        
        ob_start();
        ?>
        <div class="bordados-dashboard-assistente">
            
            <!-- Header com Logout -->
            <div class="assistente-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h2 style="margin: 0 0 5px 0;">👩‍💼 Painel da Assistente</h2>
                        <p style="margin: 0; opacity: 0.9;">Bem-vinda, <strong><?php echo esc_html($user->display_name); ?></strong>!</p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="<?php echo site_url('/painel-revisor/'); ?>" 
                           style="background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                            🔍 Painel Revisor
                        </a>
                        <a href="<?php echo esc_url($logout_url); ?>" 
                           style="background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px;"
                           onclick="return confirm('Deseja realmente sair do sistema?');">
                            🚪 Sair
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Cards de Resumo -->
            <div class="assistente-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px;">
                <div class="card-stat" style="background: #fff3cd; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 28px; font-weight: bold; color: #856404;"><?php echo $contadores['novos']; ?></div>
                    <div style="font-size: 14px; color: #856404;">🆕 Novos</div>
                </div>
                <div class="card-stat" style="background: #cce5ff; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 28px; font-weight: bold; color: #004085;"><?php echo $contadores['atribuidos']; ?></div>
                    <div style="font-size: 14px; color: #004085;">👨‍💻 Atribuídos</div>
                </div>
                <div class="card-stat" style="background: #d4edda; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 28px; font-weight: bold; color: #155724;"><?php echo $contadores['em_producao']; ?></div>
                    <div style="font-size: 14px; color: #155724;">⚙️ Produção</div>
                </div>
                <div class="card-stat" style="background: #d1ecf1; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 28px; font-weight: bold; color: #0c5460;"><?php echo $contadores['prontos_recentes']; ?></div>
                    <div style="font-size: 14px; color: #0c5460;">✅ Prontos (<?php echo self::DIAS_PRONTOS; ?>d)</div>
                </div>
            </div>
            
            <!-- Abas de Navegação -->
            <div class="assistente-tabs" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                <a href="?aba=pedidos" class="tab-btn <?php echo $aba_ativa === 'pedidos' ? 'active' : ''; ?>" 
                   style="padding: 10px 20px; border-radius: 5px 5px 0 0; text-decoration: none; 
                          <?php echo $aba_ativa === 'pedidos' ? 'background: #667eea; color: white;' : 'background: #f0f0f0; color: #333;'; ?>">
                    📋 Pedidos
                </a>
                <a href="?aba=clientes" class="tab-btn <?php echo $aba_ativa === 'clientes' ? 'active' : ''; ?>"
                   style="padding: 10px 20px; border-radius: 5px 5px 0 0; text-decoration: none;
                          <?php echo $aba_ativa === 'clientes' ? 'background: #667eea; color: white;' : 'background: #f0f0f0; color: #333;'; ?>">
                    👥 Clientes
                </a>
                <a href="?aba=novo-cliente" class="tab-btn <?php echo $aba_ativa === 'novo-cliente' ? 'active' : ''; ?>"
                   style="padding: 10px 20px; border-radius: 5px 5px 0 0; text-decoration: none;
                          <?php echo $aba_ativa === 'novo-cliente' ? 'background: #667eea; color: white;' : 'background: #f0f0f0; color: #333;'; ?>">
                    ➕ Novo Cliente
                </a>
            </div>
            
            <!-- Conteúdo das Abas -->
            <div class="assistente-conteudo">
                <?php
                switch ($aba_ativa) {
                    case 'clientes':
                        echo self::render_aba_clientes();
                        break;
                    case 'novo-cliente':
                        echo self::render_aba_novo_cliente($programadores);
                        break;
                    case 'pedidos':
                    default:
                        echo self::render_aba_pedidos($programadores);
                        break;
                }
                ?>
            </div>
            
        </div>
        
        <?php echo self::render_javascript(); ?>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Buscar contadores de pedidos
     */
    private static function buscar_contadores() {
        global $wpdb;
        $tabela = 'pedidos_basicos';
        $dias_prontos = self::DIAS_PRONTOS;
        
        $contadores = array(
            'novos'           => 0,
            'atribuidos'      => 0,
            'em_producao'     => 0,
            'prontos_recentes' => 0
        );
        
        // Contar novos, atribuídos, em produção
        $resultados = $wpdb->get_results("
            SELECT status, COUNT(*) as total 
            FROM $tabela 
            WHERE status IN ('novo', 'atribuido', 'em_producao')
            GROUP BY status
        ");
        
        foreach ($resultados as $row) {
            switch ($row->status) {
                case 'novo':
                    $contadores['novos'] = $row->total;
                    break;
                case 'atribuido':
                    $contadores['atribuidos'] = $row->total;
                    break;
                case 'em_producao':
                    $contadores['em_producao'] = $row->total;
                    break;
            }
        }
        
        // Contar prontos dos últimos X dias
        $contadores['prontos_recentes'] = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $tabela 
            WHERE status = 'pronto' 
            AND data_conclusao >= DATE_SUB(NOW(), INTERVAL $dias_prontos DAY)
        ");
        
        return $contadores;
    }
    
    /**
     * Renderizar aba de Pedidos com paginação
     */
    private static function render_aba_pedidos($programadores) {
        global $wpdb;
        $tabela = 'pedidos_basicos';
        $por_pagina = self::PEDIDOS_POR_PAGINA;
        $dias_prontos = self::DIAS_PRONTOS;
        
        // Filtro de status
        $status_filtro = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'ativos';
        
        // Página atual
        $pagina_atual = isset($_GET['pag']) ? max(1, intval($_GET['pag'])) : 1;
        $offset = ($pagina_atual - 1) * $por_pagina;
        
        // Construir query WHERE
        $where = "1=1";
        if ($status_filtro === 'ativos') {
            $where = "p.status IN ('novo', 'atribuido', 'em_producao', 'aguardando_revisao', 'em_revisao', 'em_acertos')";
        } elseif ($status_filtro === 'pronto') {
            // Prontos apenas dos últimos X dias
            $where = "p.status = 'pronto' AND p.data_conclusao >= DATE_SUB(NOW(), INTERVAL $dias_prontos DAY)";
        } elseif ($status_filtro !== 'todos') {
            $where = $wpdb->prepare("p.status = %s", $status_filtro);
        } else {
            // "Todos" = ativos + prontos recentes
            $where = "(p.status IN ('novo', 'atribuido', 'em_producao', 'aguardando_revisao', 'em_revisao', 'em_acertos') 
                      OR (p.status = 'pronto' AND p.data_conclusao >= DATE_SUB(NOW(), INTERVAL $dias_prontos DAY)))";
        }
        
        // Contar total
        $total_pedidos = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $tabela p
            WHERE $where
        ");
        
        $total_paginas = ceil($total_pedidos / $por_pagina);
        
        // Buscar pedidos
        $pedidos = $wpdb->get_results("
            SELECT p.*, 
                   c.display_name as cliente_nome,
                   prog.display_name as programador_nome,
                   rev.display_name as revisor_nome
            FROM $tabela p
            LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
            LEFT JOIN {$wpdb->users} prog ON p.programador_id = prog.ID
            LEFT JOIN {$wpdb->users} rev ON p.revisor_id = rev.ID
            WHERE $where
            ORDER BY p.data_criacao DESC
            LIMIT $por_pagina OFFSET $offset
        ");
        
        ob_start();
        ?>
        <!-- Filtros -->
        <div class="filtros-pedidos" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
            <a href="?aba=pedidos&status=ativos" 
               class="filtro-btn" style="padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 13px;
                      <?php echo $status_filtro === 'ativos' ? 'background: #667eea; color: white;' : 'background: #e0e0e0; color: #333;'; ?>">
                🔥 Ativos
            </a>
            <a href="?aba=pedidos&status=novo" 
               class="filtro-btn" style="padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 13px;
                      <?php echo $status_filtro === 'novo' ? 'background: #ffc107; color: #333;' : 'background: #e0e0e0; color: #333;'; ?>">
                🆕 Novos
            </a>
            <a href="?aba=pedidos&status=atribuido" 
               class="filtro-btn" style="padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 13px;
                      <?php echo $status_filtro === 'atribuido' ? 'background: #17a2b8; color: white;' : 'background: #e0e0e0; color: #333;'; ?>">
                👨‍💻 Atribuídos
            </a>
            <a href="?aba=pedidos&status=em_producao" 
               class="filtro-btn" style="padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 13px;
                      <?php echo $status_filtro === 'em_producao' ? 'background: #28a745; color: white;' : 'background: #e0e0e0; color: #333;'; ?>">
                ⚙️ Produção
            </a>
            <a href="?aba=pedidos&status=pronto" 
               class="filtro-btn" style="padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 13px;
                      <?php echo $status_filtro === 'pronto' ? 'background: #6c757d; color: white;' : 'background: #e0e0e0; color: #333;'; ?>">
                ✅ Prontos (<?php echo self::DIAS_PRONTOS; ?>d)
            </a>
            <a href="?aba=pedidos&status=todos" 
               class="filtro-btn" style="padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 13px;
                      <?php echo $status_filtro === 'todos' ? 'background: #343a40; color: white;' : 'background: #e0e0e0; color: #333;'; ?>">
                📜 Todos
            </a>
            
            <span style="margin-left: auto; color: #666; font-size: 13px;">
                <?php echo $total_pedidos; ?> pedido(s) encontrado(s)
            </span>
        </div>
        
        <!-- Lista de Pedidos -->
        <?php if (empty($pedidos)): ?>
            <div style="background: #f8f9fa; padding: 30px; text-align: center; border-radius: 10px;">
                <p style="margin: 0; color: #666;">Nenhum pedido encontrado com este filtro.</p>
            </div>
        <?php else: ?>
            <div class="lista-pedidos">
                <?php foreach ($pedidos as $pedido): ?>
                    <?php echo self::render_card_pedido($pedido, $programadores); ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
                <div class="paginacao" style="display: flex; justify-content: center; gap: 5px; margin-top: 30px; flex-wrap: wrap;">
                    <?php if ($pagina_atual > 1): ?>
                        <a href="?aba=pedidos&status=<?php echo $status_filtro; ?>&pag=<?php echo $pagina_atual - 1; ?>" 
                           style="padding: 8px 15px; background: #667eea; color: white; border-radius: 5px; text-decoration: none;">
                            ← Anterior
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    // Mostrar páginas próximas
                    $inicio = max(1, $pagina_atual - 2);
                    $fim = min($total_paginas, $pagina_atual + 2);
                    
                    if ($inicio > 1): ?>
                        <a href="?aba=pedidos&status=<?php echo $status_filtro; ?>&pag=1" 
                           style="padding: 8px 12px; background: #e0e0e0; color: #333; border-radius: 5px; text-decoration: none;">1</a>
                        <?php if ($inicio > 2): ?>
                            <span style="padding: 8px;">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                        <a href="?aba=pedidos&status=<?php echo $status_filtro; ?>&pag=<?php echo $i; ?>" 
                           style="padding: 8px 12px; border-radius: 5px; text-decoration: none;
                                  <?php echo $i === $pagina_atual ? 'background: #667eea; color: white;' : 'background: #e0e0e0; color: #333;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($fim < $total_paginas): ?>
                        <?php if ($fim < $total_paginas - 1): ?>
                            <span style="padding: 8px;">...</span>
                        <?php endif; ?>
                        <a href="?aba=pedidos&status=<?php echo $status_filtro; ?>&pag=<?php echo $total_paginas; ?>" 
                           style="padding: 8px 12px; background: #e0e0e0; color: #333; border-radius: 5px; text-decoration: none;">
                            <?php echo $total_paginas; ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="?aba=pedidos&status=<?php echo $status_filtro; ?>&pag=<?php echo $pagina_atual + 1; ?>" 
                           style="padding: 8px 15px; background: #667eea; color: white; border-radius: 5px; text-decoration: none;">
                            Próxima →
                        </a>
                    <?php endif; ?>
                </div>
                <div style="text-align: center; margin-top: 10px; color: #666; font-size: 13px;">
                    Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar card de pedido
     */
    private static function render_card_pedido($pedido, $programadores) {
        $status_labels = array(
            'novo'               => array('🆕 Novo', '#ffc107', '#333'),
            'atribuido'          => array('👨‍💻 Atribuído', '#17a2b8', 'white'),
            'em_producao'        => array('⚙️ Em Produção', '#28a745', 'white'),
            'aguardando_revisao' => array('🔍 Aguard. Revisão', '#6f42c1', 'white'),
            'em_revisao'         => array('🔎 Em Revisão', '#e83e8c', 'white'),
            'em_acertos'         => array('🔧 Em Acertos', '#ff6b6b', 'white'),
            'pronto'             => array('✅ Pronto', '#6c757d', 'white'),
        );
        
        $status_info = isset($status_labels[$pedido->status]) ? $status_labels[$pedido->status] : array($pedido->status, '#999', 'white');
        $is_pronto = ($pedido->status === 'pronto');

        // Determinar imagem para exibir no card
        // Prioridade 1: imagem nos arquivos_finais (revisor ou programador)
        $imagem_card = '';
        if (!empty($pedido->arquivos_finais)) {
            $arquivos_finais_arr = json_decode($pedido->arquivos_finais, true);
            if (is_array($arquivos_finais_arr)) {
                foreach ($arquivos_finais_arr as $arq) {
                    if (Bordados_Helpers::is_imagem($arq)) {
                        $imagem_card = $arq;
                        break;
                    }
                }
            }
        }
        // Prioridade 2: primeiro arquivo de imagem enviado pelo cliente
        if (empty($imagem_card)) {
            $primeiro = Bordados_Helpers::obter_primeiro_arquivo($pedido);
            if (!empty($primeiro) && Bordados_Helpers::is_imagem($primeiro)) {
                $imagem_card = $primeiro;
            }
        }

        ob_start();
        ?>
        <div class="pedido-card" style="background: white; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: start; gap: 12px;">
                    <?php if (!empty($imagem_card)): ?>
                    <div style="flex-shrink: 0;">
                        <?php echo Bordados_Helpers::exibir_arquivo($imagem_card, '70px'); ?>
                    </div>
                    <?php endif; ?>
                    <div>
                        <h4 style="margin: 0 0 5px 0; color: #333;">
                            #<?php echo $pedido->id; ?> - <?php echo esc_html($pedido->nome_bordado); ?>
                        </h4>
                        <p style="margin: 0; font-size: 13px; color: #666;">
                            👤 <strong><?php echo esc_html($pedido->cliente_nome ?: 'Cliente não encontrado'); ?></strong>
                            <?php if ($pedido->programador_nome): ?>
                                | 👨‍💻 <?php echo esc_html($pedido->programador_nome); ?>
                            <?php endif; ?>
                            <?php if ($pedido->status === 'em_revisao' && !empty($pedido->revisor_nome)): ?>
                                | 🔍 <span style="color: #e83e8c;"><?php echo esc_html($pedido->revisor_nome); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <span style="background: <?php echo $status_info[1]; ?>; color: <?php echo $status_info[2]; ?>; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: 500;">
                    <?php echo $status_info[0]; ?>
                </span>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 15px; font-size: 13px;">
                <?php if (!empty($pedido->largura) || !empty($pedido->altura)): ?>
                    <?php $dim = Bordados_Helpers::formatar_dimensoes($pedido); ?>
                    <?php if (!empty($dim)): ?>
                    <div><strong>Tamanho:</strong> <?php echo esc_html($dim); ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($pedido->cores)): ?>
                    <div><strong>Cores:</strong> <?php echo esc_html($pedido->cores); ?></div>
                <?php endif; ?>
                <?php if (!empty($pedido->preco_programador) && current_user_can('administrator')): ?>
                    <div><strong>Preço:</strong> $<?php echo number_format($pedido->preco_programador, 2); ?></div>
                <?php endif; ?>
                <div><strong>Data:</strong> <?php echo wp_date('d/m/Y H:i', strtotime($pedido->data_criacao)) . ' ' . wp_date('T', strtotime($pedido->data_criacao)); ?></div>
            </div>
            
            <!-- Ações -->
            <div class="pedido-acoes" style="display: flex; gap: 10px; flex-wrap: wrap; padding-top: 15px; border-top: 1px solid #f0f0f0;">
                
                <!-- Botão Ver Detalhes -->
                <button type="button" class="btn-detalhes" data-pedido-id="<?php echo $pedido->id; ?>"
                        style="padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px;">
                    👁️ Detalhes
                </button>
                
                <!-- Atribuir (só se status novo) -->
                <?php if ($pedido->status === 'novo'): ?>
                    <select class="select-programador" data-pedido-id="<?php echo $pedido->id; ?>"
                            style="padding: 8px; border: 1px solid #ddd; border-radius: 5px; font-size: 13px;">
                        <option value="">Selecionar programador...</option>
                        <?php foreach ($programadores as $prog): ?>
                            <option value="<?php echo $prog->ID; ?>"><?php echo esc_html($prog->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn-atribuir" data-pedido-id="<?php echo $pedido->id; ?>"
                            style="padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px;">
                        ✅ Atribuir
                    </button>
                <?php endif; ?>
                
                <!-- Editar (NÃO mostrar para prontos) -->
                <?php if (!$is_pronto): ?>
                    <button type="button" class="btn-editar-pedido" data-pedido-id="<?php echo $pedido->id; ?>"
                            style="padding: 8px 15px; background: #17a2b8; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px;">
                        ✏️ Editar
                    </button>
                    
                    <!-- Deletar (apenas se NÃO for pronto) -->
                    <button type="button" class="btn-deletar-pedido" 
                            data-pedido-id="<?php echo $pedido->id; ?>"
                            data-nome="<?php echo esc_attr($pedido->nome_bordado); ?>"
                            style="padding: 8px 15px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; margin-left: auto;">
                        🗑️ Deletar
                    </button>
                <?php endif; ?>
                
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar aba de Clientes
     */
    private static function render_aba_clientes() {
        // Verificar se há pesquisa
        $termo_pesquisa = isset($_GET['busca_cliente']) ? sanitize_text_field($_GET['busca_cliente']) : '';
        
        // Buscar clientes
        $args = array(
            'role'    => 'cliente_bordados',
            'orderby' => 'display_name',
            'order'   => 'ASC'
        );
        
        // Se há termo de pesquisa, buscar por nome ou email
        if (!empty($termo_pesquisa)) {
            $args['search'] = '*' . $termo_pesquisa . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }
        
        $clientes = get_users($args);
        
        ob_start();
        ?>
        <div class="lista-clientes">
            <h4 style="margin-bottom: 15px;">👥 Clientes Cadastrados</h4>
            
            <!-- Campo de Pesquisa -->
            <div style="margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 10px;">
                <form method="GET" action="" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="aba" value="clientes">
                    <div style="flex: 1; min-width: 200px;">
                        <input type="text" name="busca_cliente" placeholder="🔍 Pesquisar por nome ou email..."
                               value="<?php echo esc_attr($termo_pesquisa); ?>"
                               style="width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                    </div>
                    <button type="submit" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 500;">
                        🔍 Buscar
                    </button>
                    <?php if (!empty($termo_pesquisa)): ?>
                        <a href="?aba=clientes" style="padding: 10px 20px; background: #6c757d; color: white; border-radius: 5px; text-decoration: none; font-weight: 500;">
                            ✕ Limpar
                        </a>
                    <?php endif; ?>
                </form>
                <?php if (!empty($termo_pesquisa)): ?>
                    <p style="margin: 10px 0 0 0; font-size: 13px; color: #666;">
                        Mostrando <?php echo count($clientes); ?> resultado(s) para "<strong><?php echo esc_html($termo_pesquisa); ?></strong>"
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if (empty($clientes)): ?>
                <div style="background: #f8f9fa; padding: 30px; text-align: center; border-radius: 10px;">
                    <p style="margin: 0; color: #666;">Nenhum cliente cadastrado ainda.</p>
                    <a href="?aba=novo-cliente" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">
                        ➕ Cadastrar Primeiro Cliente
                    </a>
                </div>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e0e0e0;">Nome</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e0e0e0;">Email</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e0e0e0;">Programador Padrão</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #e0e0e0;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): 
                            $prog_padrao_id = get_user_meta($cliente->ID, 'programador_padrao', true);
                            $prog_padrao = $prog_padrao_id ? get_userdata($prog_padrao_id) : null;
                        ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td style="padding: 12px;">
                                    <strong><?php echo esc_html($cliente->display_name); ?></strong>
                                </td>
                                <td style="padding: 12px; font-size: 13px; color: #666;">
                                    <?php echo esc_html($cliente->user_email); ?>
                                </td>
                                <td style="padding: 12px; font-size: 13px;">
                                    <?php if ($prog_padrao): ?>
                                        <span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 10px; font-size: 12px;">
                                            👨‍💻 <?php echo esc_html($prog_padrao->display_name); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">Não definido</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <button type="button" class="btn-editar-cliente" data-cliente-id="<?php echo $cliente->ID; ?>"
                                            style="padding: 6px 12px; background: #17a2b8; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 12px;">
                                        ✏️ Editar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar aba de Novo Cliente
     */
    private static function render_aba_novo_cliente($programadores) {
        ob_start();
        ?>
        <div class="form-novo-cliente" style="max-width: 600px;">
            <h4 style="margin-bottom: 20px;">➕ Cadastrar Novo Cliente</h4>
            
            <form id="form-cadastrar-cliente" style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nome Completo *</label>
                    <input type="text" name="nome" required
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;"
                           placeholder="Nome do cliente">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email *</label>
                    <input type="email" name="email" required
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;"
                           placeholder="email@exemplo.com">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Senha</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="senha" id="campo-senha"
                               style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;"
                               placeholder="Deixe vazio para gerar automaticamente">
                        <button type="button" id="btn-gerar-senha"
                                style="padding: 10px 15px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            🎲 Gerar
                        </button>
                    </div>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Se deixar vazio, uma senha será gerada e enviada por email.
                    </small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Programador Padrão</label>
                    <select name="programador_padrao"
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                        <option value="">Nenhum (atribuição manual)</option>
                        <?php foreach ($programadores as $prog): ?>
                            <option value="<?php echo $prog->ID; ?>"><?php echo esc_html($prog->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Pedidos deste cliente serão automaticamente atribuídos para este programador.
                    </small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="enviar_email" value="1" checked>
                        <span>Enviar email com credenciais para o cliente</span>
                    </label>
                </div>
                
                <button type="submit" id="btn-cadastrar-cliente"
                        style="width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer;">
                    ✅ Cadastrar Cliente
                </button>
                
            </form>
            
            <div id="resultado-cadastro" style="margin-top: 20px;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar JavaScript
     */
    private static function render_javascript() {
        ob_start();
        ?>
        <script>
        jQuery(document).ready(function($) {
            
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            var nonce = '<?php echo wp_create_nonce('bordados_nonce'); ?>';
            
            // ========================================
            // GERAR SENHA ALEATÓRIA
            // ========================================
            $('#btn-gerar-senha').on('click', function() {
                var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
                var senha = '';
                for (var i = 0; i < 12; i++) {
                    senha += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                $('#campo-senha').val(senha);
            });
            
            // ========================================
            // CADASTRAR CLIENTE
            // ========================================
            $('#form-cadastrar-cliente').on('submit', function(e) {
                e.preventDefault();
                
                var btn = $('#btn-cadastrar-cliente');
                var textoOriginal = btn.text();
                btn.prop('disabled', true).text('Cadastrando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'assistente_cadastrar_cliente',
                        nonce: nonce,
                        nome: $('input[name="nome"]').val(),
                        email: $('input[name="email"]').val(),
                        senha: $('input[name="senha"]').val(),
                        programador_padrao: $('select[name="programador_padrao"]').val(),
                        enviar_email: $('input[name="enviar_email"]').is(':checked') ? 1 : 0
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#resultado-cadastro').html(
                                '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px;">' +
                                '✅ ' + response.data.message + '</div>'
                            );
                            $('#form-cadastrar-cliente')[0].reset();
                        } else {
                            $('#resultado-cadastro').html(
                                '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;">' +
                                '❌ ' + response.data + '</div>'
                            );
                        }
                        btn.prop('disabled', false).text(textoOriginal);
                    },
                    error: function() {
                        $('#resultado-cadastro').html(
                            '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;">' +
                            '❌ Erro de conexão. Tente novamente.</div>'
                        );
                        btn.prop('disabled', false).text(textoOriginal);
                    }
                });
            });
            
            // ========================================
            // ATRIBUIR PEDIDO
            // ========================================
            $('.btn-atribuir').on('click', function() {
                var btn = $(this);
                var pedidoId = btn.data('pedido-id');
                var select = $('.select-programador[data-pedido-id="' + pedidoId + '"]');
                var programadorId = select.val();
                
                if (!programadorId) {
                    alert('Selecione um programador primeiro!');
                    return;
                }
                
                btn.prop('disabled', true).text('Atribuindo...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'assistente_atribuir_pedido',
                        nonce: nonce,
                        pedido_id: pedidoId,
                        programador_id: programadorId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ Pedido atribuído com sucesso!');
                            location.reload();
                        } else {
                            alert('❌ ' + (response.data || 'Erro ao atribuir'));
                            btn.prop('disabled', false).text('✅ Atribuir');
                        }
                    },
                    error: function() {
                        alert('❌ Erro de conexão');
                        btn.prop('disabled', false).text('✅ Atribuir');
                    }
                });
            });
            
            // ========================================
            // VER DETALHES DO PEDIDO
            // ========================================
            $('.btn-detalhes').on('click', function() {
                var pedidoId = $(this).data('pedido-id');
                var btn = $(this);
                btn.prop('disabled', true).text('Carregando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'assistente_ver_pedido',
                        nonce: nonce,
                        pedido_id: pedidoId
                    },
                    success: function(response) {
                        btn.prop('disabled', false).text('👁️ Detalhes');
                        
                        if (response.success) {
                            // Criar modal com detalhes
                            var modal = $('<div class="modal-detalhes" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px;">' +
                                '<div style="background: white; padding: 30px; border-radius: 10px; max-width: 700px; max-height: 80vh; overflow-y: auto; width: 100%;">' +
                                '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">' +
                                '<h3 style="margin: 0;">📋 Pedido #' + pedidoId + '</h3>' +
                                '<button class="fechar-modal" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 14px;">✕ Fechar</button>' +
                                '</div>' +
                                response.data.html +
                                '</div></div>');
                            
                            $('body').append(modal);
                            
                            modal.find('.fechar-modal').on('click', function() {
                                modal.remove();
                            });
                            
                            modal.on('click', function(e) {
                                if ($(e.target).hasClass('modal-detalhes')) {
                                    modal.remove();
                                }
                            });
                        } else {
                            alert('❌ ' + (response.data || 'Erro ao carregar detalhes'));
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('👁️ Detalhes');
                        alert('❌ Erro de conexão');
                    }
                });
            });
            
            // ========================================
            // EDITAR PEDIDO (abre modal)
            // ========================================
            $('.btn-editar-pedido').on('click', function() {
                var pedidoId = $(this).data('pedido-id');
                var btn = $(this);
                btn.prop('disabled', true).text('Carregando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'assistente_form_editar_pedido',
                        nonce: nonce,
                        pedido_id: pedidoId
                    },
                    success: function(response) {
                        btn.prop('disabled', false).text('✏️ Editar');
                        
                        if (response.success) {
                            var modal = $('<div class="modal-editar" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px;">' +
                                '<div style="background: white; padding: 30px; border-radius: 10px; max-width: 600px; max-height: 80vh; overflow-y: auto; width: 100%;">' +
                                '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">' +
                                '<h3 style="margin: 0;">✏️ Editar Pedido #' + pedidoId + '</h3>' +
                                '<button class="fechar-modal" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 14px;">✕ Fechar</button>' +
                                '</div>' +
                                response.data.html +
                                '</div></div>');
                            
                            $('body').append(modal);
                            
                            modal.find('.fechar-modal').on('click', function() {
                                modal.remove();
                            });
                        } else {
                            alert('❌ ' + (response.data || 'Erro ao carregar formulário'));
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('✏️ Editar');
                        alert('❌ Erro de conexão');
                    }
                });
            });
            
            // ========================================
            // EDITAR CLIENTE (abre modal)
            // ========================================
            $('.btn-editar-cliente').on('click', function() {
                var clienteId = $(this).data('cliente-id');
                var btn = $(this);
                btn.prop('disabled', true).text('...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'assistente_form_editar_cliente',
                        nonce: nonce,
                        cliente_id: clienteId
                    },
                    success: function(response) {
                        btn.prop('disabled', false).text('✏️ Editar');
                        
                        if (response.success) {
                            var modal = $('<div class="modal-editar-cliente" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px;">' +
                                '<div style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; max-height: 80vh; overflow-y: auto; width: 100%;">' +
                                '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">' +
                                '<h3 style="margin: 0;">✏️ Editar Cliente</h3>' +
                                '<button class="fechar-modal" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 14px;">✕ Fechar</button>' +
                                '</div>' +
                                response.data.html +
                                '</div></div>');
                            
                            $('body').append(modal);
                            
                            modal.find('.fechar-modal').on('click', function() {
                                modal.remove();
                            });
                        } else {
                            alert('❌ ' + (response.data || 'Erro ao carregar formulário'));
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('✏️ Editar');
                        alert('❌ Erro de conexão');
                    }
                });
            });
            
            // ========================================
            // SUBMIT EDIÇÃO PEDIDO (delegado)
            // ========================================
            $(document).on('submit', '#form-editar-pedido-assistente', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var btn = form.find('button[type="submit"]');
                var textoOriginal = btn.text();
                btn.prop('disabled', true).text('Salvando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: form.serialize() + '&action=assistente_salvar_pedido&nonce=' + nonce,
                    success: function(response) {
                        if (response.success) {
                            alert('✅ ' + response.data.message);
                            $('.modal-editar').remove();
                            location.reload();
                        } else {
                            alert('❌ ' + (response.data || 'Erro ao salvar'));
                            btn.prop('disabled', false).text(textoOriginal);
                        }
                    },
                    error: function() {
                        alert('❌ Erro de conexão');
                        btn.prop('disabled', false).text(textoOriginal);
                    }
                });
            });
            
            // ========================================
            // SUBMIT EDIÇÃO CLIENTE (delegado)
            // ========================================
            $(document).on('submit', '#form-editar-cliente-assistente', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var btn = form.find('button[type="submit"]');
                var textoOriginal = btn.text();
                btn.prop('disabled', true).text('Salvando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: form.serialize() + '&action=assistente_salvar_cliente&nonce=' + nonce,
                    success: function(response) {
                        if (response.success) {
                            alert('✅ ' + response.data.message);
                            $('.modal-editar-cliente').remove();
                            location.reload();
                        } else {
                            alert('❌ ' + (response.data || 'Erro ao salvar'));
                            btn.prop('disabled', false).text(textoOriginal);
                        }
                    },
                    error: function() {
                        alert('❌ Erro de conexão');
                        btn.prop('disabled', false).text(textoOriginal);
                    }
                });
            });
            
            // ========================================
            // DELETAR PEDIDO
            // ========================================
            $(document).on('click', '.btn-deletar-pedido', function() {
                var pedidoId = $(this).data('pedido-id');
                var nomePedido = $(this).data('nome');
                abrirModalDeletar(pedidoId, nomePedido);
            });
            
            function abrirModalDeletar(pedidoId, nomePedido) {
                // Criar modal dinamicamente
                var modal = $('<div id="modal-deletar-assistente" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px;">' +
                    '<div style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 100%;">' +
                        '<h3 style="margin: 0 0 20px 0; color: #dc3545;">⚠️ Confirmar Exclusão Permanente</h3>' +
                        
                        '<div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; border-radius: 5px; margin-bottom: 20px;">' +
                            '<p style="margin: 0 0 10px 0; font-weight: bold; color: #856404;">⚠️ ATENÇÃO: Esta ação não pode ser desfeita!</p>' +
                            '<p style="margin: 0; font-size: 14px; color: #856404;">O pedido e todos os arquivos associados serão removidos permanentemente do sistema.</p>' +
                        '</div>' +
                        
                        '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">' +
                            '<p style="margin: 0 0 5px 0; font-weight: bold;">Pedido a ser deletado:</p>' +
                            '<p style="margin: 0 0 5px 0;"><strong>#' + pedidoId + '</strong> - ' + nomePedido + '</p>' +
                        '</div>' +
                        
                        '<div style="margin-bottom: 20px;">' +
                            '<label style="display: block; margin-bottom: 10px; font-weight: bold; color: #dc3545;">Digite DELETE para confirmar:</label>' +
                            '<input type="text" id="input-confirmar-delete" placeholder="DELETE" ' +
                                'style="width: 100%; padding: 10px; border: 2px solid #dc3545; border-radius: 5px; font-size: 16px; font-family: monospace;">' +
                            '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">* A palavra deve ser digitada exatamente como mostrado (maiúsculas)</p>' +
                        '</div>' +
                        
                        '<div style="display: flex; gap: 10px; justify-content: flex-end;">' +
                            '<button type="button" id="btn-cancelar-delete" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">✖ Cancelar</button>' +
                            '<button type="button" id="btn-confirmar-delete" disabled style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: not-allowed; font-size: 14px; opacity: 0.5;">🗑️ Confirmar Exclusão</button>' +
                        '</div>' +
                    '</div>' +
                '</div>');
                
                $('body').append(modal);
                
                // Armazenar ID temporariamente
                modal.data('pedido-id', pedidoId);
                
                // Focar no input
                setTimeout(function() {
                    $('#input-confirmar-delete').focus();
                }, 100);
                
                // Validar input em tempo real
                $('#input-confirmar-delete').on('input', function() {
                    var valor = $(this).val();
                    var btnConfirmar = $('#btn-confirmar-delete');
                    
                    if (valor === 'DELETE') {
                        btnConfirmar.prop('disabled', false).css({
                            'cursor': 'pointer',
                            'opacity': '1'
                        });
                    } else {
                        btnConfirmar.prop('disabled', true).css({
                            'cursor': 'not-allowed',
                            'opacity': '0.5'
                        });
                    }
                });
                
                // Botão cancelar
                $('#btn-cancelar-delete').on('click', function() {
                    modal.remove();
                });
                
                // Botão confirmar
                $('#btn-confirmar-delete').on('click', function() {
                    if ($('#input-confirmar-delete').val() === 'DELETE') {
                        executarDeletePedido(pedidoId, modal);
                    }
                });
                
                // ESC para fechar
                $(document).on('keyup.modal-delete', function(e) {
                    if (e.key === 'Escape') {
                        modal.remove();
                        $(document).off('keyup.modal-delete');
                    }
                });
            }
            
            function executarDeletePedido(pedidoId, modal) {
                var btnConfirmar = $('#btn-confirmar-delete');
                btnConfirmar.prop('disabled', true).text('⏳ Deletando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'assistente_deletar_pedido',
                        nonce: nonce,
                        pedido_id: pedidoId
                    },
                    success: function(response) {
                        if (response.success) {
                            modal.remove();
                            alert('✅ Pedido #' + pedidoId + ' deletado permanentemente!');
                            location.reload();
                        } else {
                            alert('❌ Erro: ' + (response.data || 'Erro ao deletar pedido'));
                            btnConfirmar.prop('disabled', false).text('🗑️ Confirmar Exclusão');
                        }
                    },
                    error: function() {
                        alert('❌ Erro de conexão com o servidor');
                        btnConfirmar.prop('disabled', false).text('🗑️ Confirmar Exclusão');
                    }
                });
            }
            
            // ========================================
            // MODAL DE ZOOM DE IMAGEM
            // ========================================
            window.abrirImagemModal = function(urlImagem, nomeArquivo) {
                // Remover modal existente se houver
                $('#modal-zoom-imagem').remove();
                
                var modal = $('<div id="modal-zoom-imagem" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.95); z-index: 999999; display: flex; align-items: center; justify-content: center; padding: 20px; cursor: zoom-out;">' +
                    '<div style="position: relative; max-width: 90%; max-height: 90vh; display: flex; flex-direction: column; align-items: center;">' +
                        '<div style="position: absolute; top: -40px; right: 0; display: flex; gap: 10px; align-items: center;">' +
                            '<a href="' + urlImagem + '" download style="background: #667eea; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 14px;">⬇ Download</a>' +
                            '<button onclick="fecharModalZoom()" style="background: #dc3545; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">✕ Fechar</button>' +
                        '</div>' +
                        '<img src="' + urlImagem + '" alt="' + nomeArquivo + '" style="max-width: 100%; max-height: 85vh; object-fit: contain; border-radius: 5px; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">' +
                        '<p style="margin: 15px 0 0 0; color: white; font-size: 14px; text-align: center;">' + nomeArquivo + '</p>' +
                    '</div>' +
                '</div>');
                
                $('body').append(modal);
                
                // Fechar ao clicar fora ou pressionar ESC
                modal.on('click', function(e) {
                    if (e.target.id === 'modal-zoom-imagem') {
                        fecharModalZoom();
                    }
                });
                
                $(document).on('keyup.modal-zoom', function(e) {
                    if (e.key === 'Escape') {
                        fecharModalZoom();
                    }
                });
            };
            
            window.fecharModalZoom = function() {
                $('#modal-zoom-imagem').remove();
                $(document).off('keyup.modal-zoom');
            };
            
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
