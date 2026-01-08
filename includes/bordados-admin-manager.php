<?php
/**
 * MÓDULO SEPARADO - GERENCIAMENTO DE PEDIDOS PARA ADMIN
 * Arquivo: bordados-admin-manager.php - VERSÃO TOTALMENTE CORRIGIDA
 * 
 * ✅ PROBLEMAS CORRIGIDOS:
 * - Botões de ação individual funcionando
 * - Seleção múltipla implementada
 * - JavaScript com debugging
 * - CSS proteção contra bloqueios
 * - AJAX robusto com fallbacks
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Admin_Manager {
    
    public function __construct() {
        // Registrar shortcode
        add_shortcode('bordados_gerenciar_pedidos', array($this, 'pagina_gerenciamento'));
        
        // Registrar AJAX
        add_action('wp_ajax_bordados_deletar_pedido', array($this, 'ajax_deletar_pedido'));
        add_action('wp_ajax_bordados_deletar_multiplos', array($this, 'ajax_deletar_multiplos'));
        add_action('wp_ajax_bordados_buscar_detalhes_pedido', array($this, 'ajax_buscar_detalhes_pedido')); // NOVO
        
        // Scripts e estilos específicos
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Carregar assets específicos desta página - MODULARIZADO v2.0
     */
    public function enqueue_assets() {
        // Apenas carregar na página de gerenciamento
        if (is_page() && has_shortcode(get_post()->post_content, 'bordados_gerenciar_pedidos')) {
            wp_enqueue_script('jquery');
            
            // Registrar um style handle para adicionar CSS inline no frontend
            wp_register_style('bordados-admin-manager-css', false);
            wp_enqueue_style('bordados-admin-manager-css');
            wp_add_inline_style('bordados-admin-manager-css', $this->get_css_admin_manager());
            
            // JavaScript externo (NOVO - extraído do inline)
            wp_enqueue_script(
                'bordados-admin-manager-js',
                BORDADOS_PLUGIN_URL . 'assets/bordados-admin-manager.js',
                array('jquery'),
                '1.0',
                true
            );
            
            // Localizar AJAX para o script externo
            wp_localize_script('bordados-admin-manager-js', 'bordados_manager_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bordados_manager_nonce')
            ));
        }
    }
    
    /**
     * SHORTCODE: Página de gerenciamento - VERSÃO TOTALMENTE FUNCIONAL
     */
    public function pagina_gerenciamento($atts) {
        // Verificar se é administrador
        if (!current_user_can('manage_options')) {
            return '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; text-align: center;">❌ Acesso restrito a administradores.</div>';
        }
        
        // Buscar pedidos não finalizados
        $pedidos_ativos = $this->buscar_pedidos_nao_finalizados();
        
        ob_start();
        ?>
        <div class="bordados-manager-container">
            <!-- Header da página -->
            <div class="manager-header">
                <h2>🗂️ Gerenciamento de Pedidos Ativos</h2>
                <p class="manager-subtitle">
                    Visualize e gerencie todos os pedidos que ainda não foram finalizados. 
                    <strong>Atenção:</strong> Exclusões são permanentes!
                </p>
            </div>
            
            <!-- Área de mensagens -->
            <div id="manager-mensagem" class="manager-mensagem" style="display: none;"></div>
            
            <!-- Estatísticas rápidas -->
            <div class="manager-stats">
                <div class="stat-card stat-novo">
                    <h4>🆕 Novos</h4>
                    <span class="stat-number"><?php echo $this->contar_por_status($pedidos_ativos, 'novo'); ?></span>
                </div>
                <div class="stat-card stat-atribuido">
                    <h4>👨‍💻 Atribuídos</h4>
                    <span class="stat-number"><?php echo $this->contar_por_status($pedidos_ativos, 'atribuido'); ?></span>
                </div>
                <div class="stat-card stat-producao">
                    <h4>⚙️ Em Produção</h4>
                    <span class="stat-number"><?php echo $this->contar_por_status($pedidos_ativos, 'em_producao'); ?></span>
                </div>
                <div class="stat-card stat-total">
                    <h4>📊 Total Ativo</h4>
                    <span class="stat-number"><?php echo count($pedidos_ativos); ?></span>
                </div>
            </div>
            
            <?php if (empty($pedidos_ativos)): ?>
                <!-- Estado vazio -->
                <div class="manager-empty">
                    <div class="empty-icon">🎉</div>
                    <h3>Nenhum pedido ativo encontrado!</h3>
                    <p>Todos os pedidos foram finalizados ou não há pedidos no sistema.</p>
                    <div style="margin: 20px 0;">
                        <a href="<?php echo esc_url(site_url('/admin-pedidos/')); ?>" class="btn btn-primary">
                            📋 Ver Dashboard Principal
                        </a>
                        <a href="<?php echo esc_url(site_url('/novo-pedido/')); ?>" class="btn btn-outline" style="margin-left: 10px;">
                            ➕ Criar Pedido de Teste
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Tabela de pedidos -->
                <div class="manager-table-container">
                    <div class="table-header">
                        <h3>📋 Lista de Pedidos Ativos (<?php echo count($pedidos_ativos); ?>)</h3>
                        <div class="table-actions">
                            <button type="button" onclick="selecionarTodos()" class="btn btn-outline">
                                ☑️ Selecionar/Desmarcar Todos
                            </button>
                            <button type="button" onclick="deletarSelecionados()" class="btn btn-danger" id="btn-deletar-multiplos" disabled>
                                🗑️ Deletar Selecionados (<span id="contador-selecionados">0</span>)
                            </button>
                        </div>
                    </div>
                    
                    <table class="manager-table">
                        <thead>
                            <tr>
                                <th class="col-checkbox">
                                    <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)">
                                </th>
                                <th class="col-id">ID</th>
                                <th class="col-imagem">Imagem</th>
                                <th class="col-pedido">Pedido</th>
                                <th class="col-cliente">Cliente</th>
                                <th class="col-programador">Programador</th>
                                <th class="col-status">Status</th>
                                <th class="col-data">Data</th>
                                <th class="col-acoes">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos_ativos as $pedido): ?>
                            <tr id="pedido-row-<?php echo $pedido->id; ?>" class="pedido-row">
                                <td class="col-checkbox">
                                    <input type="checkbox" class="pedido-checkbox" 
                                           value="<?php echo $pedido->id; ?>" 
                                           data-nome="<?php echo esc_attr($pedido->nome_bordado); ?>"
                                           onchange="updateDeleteButton()">
                                </td>
                                <td class="col-id">
                                    <strong>#<?php echo $pedido->id; ?></strong>
                                </td>
                                <td class="col-imagem">
                                    <?php echo $this->exibir_imagem_pedido($pedido); ?>
                                </td>
                                <td class="col-pedido">
                                    <div class="pedido-info">
                                        <strong><?php echo esc_html($pedido->nome_bordado); ?></strong>
                                        <div class="pedido-detalhes">
                                            <span class="detalhe">📏 <?php echo $this->formatar_tamanho($pedido); ?></span>
                                            <?php if (!empty($pedido->cores)): ?>
                                                <span class="detalhe">🎨 <?php echo esc_html($pedido->cores); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($pedido->prazo_entrega) && $pedido->prazo_entrega !== 'Normal'): ?>
                                                <span class="detalhe urgente">⚡ <?php echo esc_html($pedido->prazo_entrega); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="col-cliente">
                                    <div class="cliente-info">
                                        <?php echo esc_html($pedido->cliente_nome); ?>
                                        <small><?php echo esc_html($pedido->cliente_email); ?></small>
                                    </div>
                                </td>
                                <td class="col-programador">
                                    <?php if (!empty($pedido->programador_nome)): ?>
                                        <span class="programador-atribuido">
                                            👨‍💻 <?php echo esc_html($pedido->programador_nome); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="programador-vazio">— Não atribuído</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-status">
                                    <?php echo $this->get_status_badge($pedido->status); ?>
                                </td>
                                <td class="col-data">
                                    <div class="data-info">
                                        <?php echo date('d/m/Y', strtotime($pedido->data_criacao)); ?>
                                        <small><?php echo date('H:i', strtotime($pedido->data_criacao)); ?></small>
                                    </div>
                                </td>
                                <td class="col-acoes">
                                    <div class="acoes-grupo">
                                        <button type="button" 
                                                onclick="visualizarPedido(<?php echo $pedido->id; ?>, '<?php echo esc_js($pedido->nome_bordado); ?>')" 
                                                class="btn btn-info btn-sm action-btn" 
                                                title="Visualizar detalhes"
                                                data-pedido-id="<?php echo $pedido->id; ?>">
                                            👁️
                                        </button>
                                        <button type="button" 
                                                onclick="confirmarDelete(<?php echo $pedido->id; ?>, '<?php echo esc_js($pedido->nome_bordado); ?>')" 
                                                class="btn btn-danger btn-sm action-btn" 
                                                title="Deletar pedido"
                                                data-pedido-id="<?php echo $pedido->id; ?>">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Informações adicionais -->
                <div class="manager-footer">
                    <div class="footer-info">
                        <p>
                            <strong>⚠️ Atenção:</strong> A exclusão de pedidos é permanente e não pode ser desfeita. 
                            Certifique-se antes de confirmar qualquer exclusão.
                        </p>
                        <p>
                            <strong>📊 Total de pedidos ativos:</strong> <?php echo count($pedidos_ativos); ?> | 
                            <strong>🕒 Última atualização:</strong> <?php echo date('d/m/Y H:i:s'); ?>
                        </p>
                    </div>
                    <div class="footer-actions">
                        <a href="<?php echo esc_url(site_url('/admin-pedidos/')); ?>" class="btn btn-outline">
                            ← Dashboard Principal
                        </a>
                        <button type="button" onclick="window.location.reload()" class="btn btn-secondary">
                            🔄 Atualizar Lista
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Modal de confirmação individual -->
        <div id="modal-confirmar-delete" class="modal-overlay" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>⚠️ Confirmar Exclusão</h3>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja deletar este pedido?</p>
                    <div class="pedido-delete-info">
                        <strong id="delete-pedido-nome">Nome do pedido</strong>
                        <small id="delete-pedido-id">#000</small>
                    </div>
                    <p class="warning-text">
                        <strong>⚠️ Esta ação não pode ser desfeita!</strong><br>
                        O pedido e todos os arquivos associados serão removidos permanentemente.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="fecharModalDelete()" class="btn btn-outline">
                        ❌ Cancelar
                    </button>
                    <button type="button" onclick="executarDelete()" class="btn btn-danger" id="btn-confirmar-delete">
                        🗑️ Sim, Deletar
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal de confirmação múltipla -->
        <div id="modal-confirmar-delete-multiplo" class="modal-overlay" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>⚠️ Confirmar Exclusão Múltipla</h3>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja deletar os pedidos selecionados?</p>
                    <div class="pedidos-selecionados-info">
                        <strong>Pedidos que serão deletados:</strong>
                        <ul id="lista-pedidos-selecionados"></ul>
                    </div>
                    <p class="warning-text">
                        <strong>⚠️ Esta ação não pode ser desfeita!</strong><br>
                        Todos os pedidos selecionados e seus arquivos serão removidos permanentemente.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="fecharModalDeleteMultiplo()" class="btn btn-outline">
                        ❌ Cancelar
                    </button>
                    <button type="button" onclick="executarDeleteMultiplo()" class="btn btn-danger" id="btn-confirmar-delete-multiplo">
                        🗑️ Sim, Deletar Todos
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal de visualização -->
        <div id="modal-visualizar" class="modal-overlay" style="display: none;">
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h3>👁️ Detalhes do Pedido <span id="visual-pedido-id">#000</span></h3>
                    <button type="button" onclick="fecharModalVisualizar()" class="btn-close">×</button>
                </div>
                <div class="modal-body" id="conteudo-visualizacao">
                    <!-- Conteúdo será preenchido via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="fecharModalVisualizar()" class="btn btn-outline">
                        ✅ Fechar
                    </button>
                </div>
            </div>
        </div>

        <!-- JAVASCRIPT MODULARIZADO v2.0 -->
        <!-- 
            O JavaScript que estava inline neste arquivo foi extraído para:
            assets/bordados-admin-manager.js
            
            O script é carregado via wp_enqueue_script no método enqueue_assets()
        -->
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Deletar pedido individual
     */
    public function ajax_deletar_pedido() {
        // Log para debug
        error_log('=== AJAX DELETAR PEDIDO INDIVIDUAL ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_manager_nonce')) {
            error_log('❌ Nonce inválido');
            wp_send_json_error('Token de segurança inválido');
            return;
        }
        
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            error_log('❌ Permissão negada');
            wp_send_json_error('Acesso negado');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        
        if (empty($pedido_id)) {
            error_log('❌ ID inválido: ' . $_POST['pedido_id']);
            wp_send_json_error('ID do pedido inválido');
            return;
        }
        
        $resultado = $this->deletar_pedido_por_id($pedido_id);
        
        if ($resultado['success']) {
            wp_send_json_success($resultado);
        } else {
            wp_send_json_error($resultado['message']);
        }
    }
    
    /**
     * AJAX: Deletar múltiplos pedidos - NOVO
     */
    public function ajax_deletar_multiplos() {
        error_log('=== AJAX DELETAR MÚLTIPLOS PEDIDOS ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_manager_nonce')) {
            error_log('❌ Nonce inválido');
            wp_send_json_error('Token de segurança inválido');
            return;
        }
        
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            error_log('❌ Permissão negada');
            wp_send_json_error('Acesso negado');
            return;
        }
        
        $pedidos_ids = isset($_POST['pedidos_ids']) ? $_POST['pedidos_ids'] : array();
        
        if (empty($pedidos_ids) || !is_array($pedidos_ids)) {
            error_log('❌ IDs inválidos: ' . print_r($pedidos_ids, true));
            wp_send_json_error('Lista de IDs inválida');
            return;
        }
        
        $deletados = 0;
        $erros = array();
        
        foreach ($pedidos_ids as $pedido_id) {
            $pedido_id = intval($pedido_id);
            if ($pedido_id > 0) {
                $resultado = $this->deletar_pedido_por_id($pedido_id);
                if ($resultado['success']) {
                    $deletados++;
                } else {
                    $erros[] = "Pedido #{$pedido_id}: " . $resultado['message'];
                }
            }
        }
        
        if ($deletados > 0) {
            $mensagem = "✅ {$deletados} pedido(s) deletado(s) com sucesso";
            if (!empty($erros)) {
                $mensagem .= " (com " . count($erros) . " erro(s))";
            }
            
            wp_send_json_success(array(
                'message' => $mensagem,
                'deletados' => $deletados,
                'erros' => $erros
            ));
        } else {
            wp_send_json_error('Nenhum pedido foi deletado. Erros: ' . implode(', ', $erros));
        }
    }
    
    /**
     * Função auxiliar para deletar pedido por ID
     */
    
    /**
     * AJAX: Buscar detalhes completos do pedido
     */
    public function ajax_buscar_detalhes_pedido() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_manager_nonce')) {
            wp_send_json_error('Token de segurança inválido');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        if (empty($pedido_id)) {
            wp_send_json_error('ID inválido');
            return;
        }
        
        global $wpdb;
        $pedido = $wpdb->get_row($wpdb->prepare("
            SELECT p.*, c.display_name as cliente_nome, c.user_email as cliente_email,
                   prog.display_name as programador_nome, prog.user_email as programador_email
            FROM pedidos_basicos p
            LEFT JOIN {$wpdb->prefix}users c ON p.cliente_id = c.ID
            LEFT JOIN {$wpdb->prefix}users prog ON p.programador_id = prog.ID
            WHERE p.id = %d
        ", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado');
            return;
        }
        
        $arquivos_cliente = !empty($pedido->arquivos_cliente) ? json_decode($pedido->arquivos_cliente, true) : array();
        $arquivos_finais = !empty($pedido->arquivos_finais) ? json_decode($pedido->arquivos_finais, true) : array();
        
        wp_send_json_success(array(
            'id' => $pedido->id,
            'nome_bordado' => $pedido->nome_bordado,
            'status' => $pedido->status,
            'tamanho' => $pedido->tamanho,
            'cores' => $pedido->cores,
            'observacoes' => $pedido->observacoes,
            'observacoes_programador' => $pedido->observacoes_programador,
            'preco_programador' => $pedido->preco_programador,
            'cliente' => array('nome' => $pedido->cliente_nome, 'email' => $pedido->cliente_email),
            'programador' => array('nome' => $pedido->programador_nome, 'email' => $pedido->programador_email),
            'datas' => array('criacao' => $pedido->data_criacao, 'atribuicao' => $pedido->data_atribuicao, 'conclusao' => $pedido->data_conclusao),
            'arquivos_cliente' => $arquivos_cliente,
            'arquivos_finais' => $arquivos_finais
        ));
    }


    private function deletar_pedido_por_id($pedido_id) {
        global $wpdb;
        
        error_log("🗑️ Deletando pedido ID: $pedido_id");
        
        // Buscar pedido antes de deletar
        $pedido = $wpdb->get_row($wpdb->prepare("SELECT * FROM pedidos_basicos WHERE id = %d", $pedido_id));
        
        if (!$pedido) {
            error_log("❌ Pedido #$pedido_id não encontrado");
            return array('success' => false, 'message' => 'Pedido não encontrado');
        }
        
        // Verificar se não está pronto (segurança extra)
        if ($pedido->status === 'pronto') {
            error_log("❌ Tentativa de deletar pedido pronto: $pedido_id");
            return array('success' => false, 'message' => 'Não é possível deletar pedidos já finalizados');
        }
        
        error_log("📋 Pedido encontrado: {$pedido->nome_bordado} (Status: {$pedido->status})");
        
        // Deletar arquivos associados
        $this->deletar_arquivos_pedido($pedido);
        
        // Deletar do banco
        $resultado = $wpdb->delete(
            'pedidos_basicos',
            array('id' => $pedido_id),
            array('%d')
        );
        
        if ($resultado === false) {
            error_log("❌ Erro ao deletar do banco: " . $wpdb->last_error);
            return array('success' => false, 'message' => 'Erro ao deletar pedido do banco de dados');
        }
        
        // Log da ação
        error_log("✅ SUCESSO: Pedido #{$pedido_id} ({$pedido->nome_bordado}) deletado por usuário " . get_current_user_id());
        
        return array(
            'success' => true,
            'message' => "Pedido #{$pedido_id} ({$pedido->nome_bordado}) deletado com sucesso",
            'pedido_id' => $pedido_id,
            'nome_bordado' => $pedido->nome_bordado
        );
    }
    
    /**
     * Buscar pedidos não finalizados
     */
    private function buscar_pedidos_nao_finalizados() {
        global $wpdb;
        
        $pedidos = $wpdb->get_results("
            SELECT 
                p.*,
                c.display_name as cliente_nome,
                c.user_email as cliente_email,
                prog.display_name as programador_nome
            FROM pedidos_basicos p
            JOIN {$wpdb->prefix}users c ON p.cliente_id = c.ID
            LEFT JOIN {$wpdb->prefix}users prog ON p.programador_id = prog.ID
            WHERE p.status IN ('novo', 'atribuido', 'em_producao')
            ORDER BY p.data_criacao DESC
        ");
        
        return $pedidos ?: array();
    }
    
    /**
     * Contar pedidos por status
     */
    private function contar_por_status($pedidos, $status) {
        return count(array_filter($pedidos, function($p) use ($status) {
            return $p->status === $status;
        }));
    }
    
    /**
     * Exibir imagem do pedido
     */
    private function exibir_imagem_pedido($pedido) {
        $primeiro_arquivo = null;
        
        // Verificar arquivos_cliente (formato JSON)
        if (!empty($pedido->arquivos_cliente)) {
            $arquivos = json_decode($pedido->arquivos_cliente, true);
            if (is_array($arquivos) && !empty($arquivos)) {
                $primeiro_arquivo = $arquivos[0];
            }
        }
        
        // Fallback para arquivo_referencia (formato antigo)
        if (empty($primeiro_arquivo) && !empty($pedido->arquivo_referencia)) {
            $primeiro_arquivo = $pedido->arquivo_referencia;
        }
        
        if (!empty($primeiro_arquivo)) {
            // Forçar HTTPS
            $primeiro_arquivo_https = Bordados_Helpers::forcar_https($primeiro_arquivo);
            $file_ext = strtolower(pathinfo($primeiro_arquivo, PATHINFO_EXTENSION));
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                return '<img src="' . esc_url($primeiro_arquivo_https) . '" class="pedido-thumb" alt="Preview" onclick="mostrarImagemGrande(\'' . esc_url($primeiro_arquivo_https) . '\')">';
            } else {
                return '<div class="pedido-thumb-placeholder" title="Arquivo: ' . strtoupper($file_ext) . '">📄</div>';
            }
        }
        
        return '<div class="pedido-thumb-placeholder">📷</div>';
    }
    
    /**
     * Formatar tamanho do pedido
     */
    private function formatar_tamanho($pedido) {
        // Verificar se tem campos de dimensões
        if (!empty($pedido->largura) || !empty($pedido->altura)) {
            $dims = array();
            if (!empty($pedido->largura) && $pedido->largura > 0) {
                $dims[] = number_format($pedido->largura, 1);
            }
            if (!empty($pedido->altura) && $pedido->altura > 0) {
                $dims[] = number_format($pedido->altura, 1);
            }
            
            if (!empty($dims)) {
                $result = implode('x', $dims);
                if (!empty($pedido->unidade_medida)) {
                    $result .= ' ' . $pedido->unidade_medida;
                }
                return $result;
            }
        }
        
        // Verificar campo tamanho (formato antigo)
        if (!empty($pedido->tamanho)) {
            return $pedido->tamanho;
        }
        
        return 'Não especificado';
    }
    
    /**
     * Status badges
     */
    private function get_status_badge($status) {
        $badges = array(
            'novo' => '<span class="status-badge status-novo">🆕 Novo</span>',
            'atribuido' => '<span class="status-badge status-atribuido">👨‍💻 Atribuído</span>',
            'em_producao' => '<span class="status-badge status-producao">⚙️ Em Produção</span>',
        );
        
        return isset($badges[$status]) ? $badges[$status] : '<span class="status-badge">' . esc_html($status) . '</span>';
    }
    
    /**
     * Deletar arquivos físicos do pedido
     */
    private function deletar_arquivos_pedido($pedido) {
        $upload_dir = wp_upload_dir();
        
        // Deletar arquivos do cliente (formato JSON)
        if (!empty($pedido->arquivos_cliente)) {
            $arquivos_cliente = json_decode($pedido->arquivos_cliente, true);
            if (is_array($arquivos_cliente)) {
                foreach ($arquivos_cliente as $arquivo) {
                    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $arquivo);
                    if (file_exists($file_path)) {
                        unlink($file_path);
                        error_log("🗑️ Arquivo deletado: $file_path");
                    }
                }
            }
        }
        
        // Deletar arquivo de referência (formato antigo)
        if (!empty($pedido->arquivo_referencia)) {
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $pedido->arquivo_referencia);
            if (file_exists($file_path)) {
                unlink($file_path);
                error_log("🗑️ Arquivo de referência deletado: $file_path");
            }
        }
        
        // Deletar arquivos finais (se existirem)
        if (!empty($pedido->arquivos_finais)) {
            $arquivos_finais = json_decode($pedido->arquivos_finais, true);
            if (is_array($arquivos_finais)) {
                foreach ($arquivos_finais as $arquivo) {
                    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $arquivo);
                    if (file_exists($file_path)) {
                        unlink($file_path);
                        error_log("🗑️ Arquivo final deletado: $file_path");
                    }
                }
            }
        }
    }
    
    /**
     * CSS específico para esta página - VERSÃO MELHORADA
     */
    private function get_css_admin_manager() {
        return '
        .bordados-manager-container {
            max-width: 1400px;
            margin: 20px auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        
        .manager-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .manager-header h2 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        
        .manager-subtitle {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }
        
        .manager-mensagem {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .manager-mensagem.sucesso {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .manager-mensagem.erro {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .manager-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-novo { border-color: #007cba; }
        .stat-atribuido { border-color: #f57400; }
        .stat-producao { border-color: #28a745; }
        .stat-total { border-color: #6f42c1; }
        
        .manager-empty {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .manager-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            margin: 0;
            color: #333;
        }
        
        .table-actions {
            display: flex;
            gap: 10px;
        }
        
        .manager-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .manager-table th,
        .manager-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .manager-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        .manager-table tbody tr {
            transition: background-color 0.2s;
        }
        
        .manager-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .col-checkbox { width: 40px; text-align: center; }
        .col-id { width: 60px; }
        .col-imagem { width: 80px; text-align: center; }
        .col-pedido { width: 250px; }
        .col-cliente { width: 180px; }
        .col-programador { width: 150px; }
        .col-status { width: 120px; text-align: center; }
        .col-data { width: 100px; }
        .col-acoes { width: 100px; text-align: center; }
        
        .pedido-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            transition: transform 0.2s;
            cursor: pointer;
        }
        
        .pedido-thumb:hover {
            transform: scale(1.1);
        }
        
        .pedido-thumb-placeholder {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #6c757d;
            border: 2px solid #dee2e6;
            margin: 0 auto;
        }
        
        .pedido-info strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        .pedido-detalhes {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .detalhe {
            font-size: 11px;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 10px;
            color: #495057;
        }
        
        .detalhe.urgente {
            background: #ff6b6b;
            color: white;
            font-weight: bold;
        }
        
        .cliente-info {
            font-size: 13px;
        }
        
        .cliente-info small {
            display: block;
            color: #6c757d;
            margin-top: 2px;
        }
        
        .programador-atribuido {
            font-size: 12px;
            color: #495057;
        }
        
        .programador-vazio {
            font-size: 12px;
            color: #6c757d;
            font-style: italic;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-novo { background: #cfe2ff; color: #0a58ca; }
        .status-atribuido { background: #fff3cd; color: #997404; }
        .status-producao { background: #d1e7dd; color: #0a3622; }
        
        .data-info {
            font-size: 13px;
        }
        
        .data-info small {
            display: block;
            color: #6c757d;
            margin-top: 2px;
        }
        
        /* ===================================
           🔧 CORREÇÃO CRÍTICA - BOTÕES AÇÃO
           =================================== */
        
        .acoes-grupo {
            display: flex !important;
            gap: 5px !important;
            justify-content: center !important;
            pointer-events: auto !important;
            z-index: 1000 !important;
        }
        
        .action-btn {
            pointer-events: auto !important;
            cursor: pointer !important;
            z-index: 9999 !important;
            position: relative !important;
            display: inline-block !important;
            min-width: 36px !important;
            min-height: 36px !important;
            touch-action: manipulation !important;
            user-select: none !important;
            border: none !important;
            outline: none !important;
            background: #007cba !important;
            color: white !important;
            border-radius: 6px !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            transition: all 0.2s ease !important;
        }
        
        .action-btn:hover {
            transform: scale(1.1) !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important;
            background: #005a8b !important;
        }
        
        .action-btn:active {
            transform: scale(1.05) !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.3) !important;
        }
        
        .btn-info.action-btn {
            background: #17a2b8 !important;
        }
        
        .btn-info.action-btn:hover {
            background: #138496 !important;
        }
        
        .btn-danger.action-btn {
            background: #dc3545 !important;
        }
        
        .btn-danger.action-btn:hover {
            background: #c82333 !important;
        }
        
        /* Remover qualquer overlay ou pseudo-elemento */
        .col-acoes::before,
        .col-acoes::after,
        .acoes-grupo::before,
        .acoes-grupo::after,
        .action-btn::before,
        .action-btn::after {
            display: none !important;
            content: none !important;
            pointer-events: none !important;
        }
        
        /* ===================================
           BOTÕES GERAIS
           =================================== */
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            text-align: center;
            background: #007cba;
            color: white;
            user-select: none;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            min-width: 36px;
            min-height: 36px;
        }
        
        .btn-primary { background: #007cba; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-outline { background: white; color: #6c757d; border: 1px solid #dee2e6; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover { 
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            opacity: 0.9;
        }
        
        .btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        .btn:disabled,
        .btn.btn-disabled { 
            opacity: 0.5; 
            cursor: not-allowed; 
            transform: none; 
            box-shadow: none;
            background: #6c757d !important;
        }
        
        .manager-footer {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .footer-info p {
            margin: 5px 0;
            font-size: 13px;
            color: #6c757d;
        }
        
        .footer-actions {
            display: flex;
            gap: 10px;
        }
        
        
        /* ===================================
           BOTÕES DE AÇÃO - CORREÇÃO SOBREPOSIÇÃO
           =================================== */
        
        table.manager-table td.col-acoes {
            text-align: center;
            vertical-align: middle;
            padding: 10px 15px;
            min-width: 120px;
            white-space: nowrap;
        }
        
        table.manager-table .acoes-grupo {
            display: inline-flex;
            flex-direction: row;
            gap: 10px;
            justify-content: center;
            align-items: center;
        }
        
        table.manager-table button.action-btn {
            flex: 0 0 auto;
            width: 42px !important;
            height: 42px !important;
            min-width: 42px !important;
            min-height: 42px !important;
            max-width: 42px !important;
            max-height: 42px !important;
            padding: 0 !important;
            margin: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 20px !important;
            line-height: 1 !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            border: 1px solid rgba(0,0,0,0.1) !important;
            transition: all 0.2s ease !important;
        }
        
        table.manager-table button.action-btn:hover {
            transform: scale(1.08) !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2) !important;
        }
        
        table.manager-table button.action-btn:active {
            transform: scale(0.95) !important;
        }

        
        /* ===================================
           MODALS
           =================================== */
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        .modal-large {
            max-width: 800px;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            padding: 20px 20px 0 20px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #dc3545;
        }
        
        .btn-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }
        
        .btn-close:hover {
            background: #f8f9fa;
            color: #333;
        }
        
        .modal-body {
            padding: 0 20px 20px 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .pedido-delete-info,
        .pedidos-selecionados-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
        }
        
        .pedido-delete-info strong {
            display: block;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .pedidos-selecionados-info ul {
            text-align: left;
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .pedidos-selecionados-info li {
            margin: 5px 0;
        }
        
        .warning-text {
            color: #dc3545;
            font-weight: 500;
            text-align: center;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .pedido-detalhes-completo h4 {
            color: #333;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        .pedido-detalhes-completo h4:first-child {
            margin-top: 0;
        }
        
        /* ===================================
           RESPONSIVIDADE
           =================================== */
        
        @media (max-width: 768px) {
            .manager-stats { 
                grid-template-columns: repeat(2, 1fr); 
            }
            
            .table-header { 
                flex-direction: column; 
                gap: 15px; 
                align-items: stretch; 
            }
            
            .table-actions { 
                justify-content: center; 
            }
            
            .manager-footer { 
                flex-direction: column; 
                gap: 15px; 
                align-items: center; 
            }
            
            .footer-actions { 
                order: -1; 
            }
            
            .manager-table {
                font-size: 12px;
            }
            
            .manager-table th,
            .manager-table td {
                padding: 8px 4px;
            }
            
            .modal-content {
                width: 95%;
                margin: 20px;
            }
            
            .action-btn {
                min-width: 44px !important;
                min-height: 44px !important;
                padding: 8px 12px !important;
            }
            
            .acoes-grupo {
                flex-direction: column !important;
                gap: 3px !important;
            }
        }
        ';
    }
}

// Inicializar o módulo
new Bordados_Admin_Manager();

?>