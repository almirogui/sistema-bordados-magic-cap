<?php
/**
 * Shortcode: Dashboard do Cliente - [bordados_meus_pedidos]
 * Extraído de class-shortcodes.php na Fase 3 da modularização
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Shortcode_Meus_Pedidos {
    
    /**
     * Renderizar dashboard do cliente
     */
    public static function render($atts) {

    if (!is_user_logged_in()) {
        return '<p>You need to be logged in to view your orders.</p>';
    }

    $cliente_id = get_current_user_id();
    
    // Verificar filtros via GET/POST
    $filtros = array();
    if (isset($_GET['busca']) && !empty($_GET['busca'])) {
        $filtros['busca'] = sanitize_text_field($_GET['busca']);
    }
    if (isset($_GET['mostrar_todos']) && $_GET['mostrar_todos'] == '1') {
        $filtros['mostrar_todos'] = true;
    }
    
    $pedidos = Bordados_Database::buscar_pedidos_cliente_filtrados($cliente_id, $filtros);

    ob_start();
    ?>
    <div class="bordados-dashboard-cliente">
        <?php 
        // Obter dados do cliente logado
        $current_user = wp_get_current_user();
        $cliente_nome = $current_user->display_name;
        $cliente_empresa = get_user_meta($cliente_id, 'billing_company', true);
        if (empty($cliente_empresa)) {
            $cliente_empresa = get_user_meta($cliente_id, 'empresa', true);
        }
        ?>
        <!-- Client Header -->
        <div style="background: #f8f9fa; padding: 8px 15px; border-radius: 5px; margin-bottom: 15px; border-left: 3px solid #0073aa;">
            <span style="font-size: 13px; color: #555;">
                👤 <strong><?php echo esc_html($cliente_nome); ?></strong><?php if (!empty($cliente_empresa)): ?> | <?php echo esc_html($cliente_empresa); ?><?php endif; ?>
            </span>
        </div>
        
        <h3>📋 My Orders</h3>
        
        <!-- ⭐ PAINEL DE ESTATÍSTICAS ⭐ -->
        <?php
        // Calcular estatísticas
        global $wpdb;
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pronto' THEN 1 ELSE 0 END) as prontos,
                SUM(CASE WHEN tipo_pedido = 'edicao' THEN 1 ELSE 0 END) as edicoes,
                SUM(CASE WHEN tipo_pedido = 'edicao' AND edicao_gratuita = 1 THEN 1 ELSE 0 END) as edicoes_gratis,
                SUM(CASE WHEN status IN ('novo', 'atribuido', 'em_producao') THEN 1 ELSE 0 END) as em_andamento
            FROM pedidos_basicos
            WHERE cliente_id = %d
        ", $cliente_id));
        ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <!-- Total Orders -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px; color: white; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;"><?php echo $stats->total; ?></div>
                <div style="font-size: 13px; opacity: 0.9;">📦 Total Orders</div>
            </div>
            
            <!-- Orders Ready -->
            <div style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 20px; border-radius: 10px; color: white; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;"><?php echo $stats->prontos; ?></div>
                <div style="font-size: 13px; opacity: 0.9;">🎉 Completed</div>
            </div>
            
            <!-- In Progress -->
            <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 20px; border-radius: 10px; color: white; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;"><?php echo $stats->em_andamento; ?></div>
                <div style="font-size: 13px; opacity: 0.9;">⚙️Â In Progress</div>
            </div>
            
            <!-- Edições Solicitadas -->
            <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); padding: 20px; border-radius: 10px; color: white; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;"><?php echo $stats->edicoes; ?></div>
                <div style="font-size: 13px; opacity: 0.9;">✏️ Edits Made</div>
            </div>
            
            <!-- Edições Gratuitas Usadas -->
            <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 20px; border-radius: 10px; color: white; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; margin-bottom: 5px;"><?php echo $stats->edicoes_gratis; ?></div>
                <div style="font-size: 13px; opacity: 0.9;">🎁 Free Edits</div>
            </div>
        </div>

        <?php
        // ⭐ ETAPA 3: Buscar orçamentos pendentes do cliente
        $orcamentos_recebidos = Bordados_Database::buscar_orcamentos_cliente($cliente_id);
        
        if (!empty($orcamentos_recebidos)):
        ?>
        <!-- ⭐ SEÇÃO DE ORÇAMENTOS RECEBIDOS ⭐ -->
        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #ffc107;">
            <h4 style="margin: 0 0 15px 0;">💰 Quotes Pending Approval (<?php echo count($orcamentos_recebidos); ?>)</h4>
            
            <?php foreach ($orcamentos_recebidos as $orcamento): ?>
            <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h5 style="margin: 0 0 5px 0;">
                            #<?php echo $orcamento->id; ?> - <?php echo esc_html($orcamento->nome_bordado); ?>
                        </h5>
                        <p style="margin: 0; color: #666; font-size: 14px;">
                            Type: <?php echo $orcamento->tipo_produto == 'vetor' ? '✏️ Vector Art' : '🧵 Embroidery Digitizing'; ?> |
                            Stitches: <?php echo number_format($orcamento->numero_pontos); ?>
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 24px; font-weight: bold; color: #28a745;">
                            $<?php echo number_format($orcamento->preco_final, 2); ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($orcamento->obs_revisor)): ?>
                <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 15px 0; font-size: 14px;">
                    <strong>📝 Notes:</strong> <?php echo esc_html($orcamento->obs_revisor); ?>
                </div>
                <?php endif; ?>
                
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button onclick="aprovarOrcamento(<?php echo $orcamento->id; ?>)" 
                            class="button button-primary" 
                            style="background: #28a745; border-color: #28a745;">
                        ✅ Approve Quote
                    </button>
                    <button onclick="recusarOrcamento(<?php echo $orcamento->id; ?>)" 
                            class="button" 
                            style="background: #dc3545; border-color: #dc3545; color: white;">
                        ❌ Decline
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

	<!-- Botões de ação no topo -->
	<div style="text-align: right; margin-bottom: 15px;">
    	<a href="<?php echo esc_url(site_url('/meu-perfil/')); ?>" class="button" style="background: #6c757d; border-color: #6c757d; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: 500; margin-right: 10px;">
        👤 My Profile
    	</a>
    	<a href="<?php echo esc_url(site_url('/novo-pedido/')); ?>" class="button button-primary" style="background: #0073aa; border-color: #0073aa; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: 500;">
        ➕ New Order
    	</a>
	</div>
        <!-- NOVO: Filtros e busca -->
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                <!-- Campo de busca -->
                <div style="flex: 1; min-width: 200px;">
                    <input type="text" 
                           id="busca-pedidos" 
                           placeholder="🔍 Search by design name..." 
                           value="<?php echo isset($filtros['busca']) ? esc_attr($filtros['busca']) : ''; ?>"
                           style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <!-- Toggle todos/ativos -->
<div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
    <!-- Toggle todos/ativos -->
    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 14px;">
        <input type="checkbox" 
               id="mostrar-todos" 
               <?php echo isset($filtros['mostrar_todos']) && $filtros['mostrar_todos'] ? 'checked' : ''; ?>
               style="margin: 0;">
        <span>Show all orders</span>
    </label>
    
    <!-- ⭐ NOVO: Filtro por tipo ⭐ -->
    <select id="filtro-tipo" style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
        <option value="todos">📋 All types</option>
        <option value="original">📄 Originals Only</option>
        <option value="edicao">📝 Edits Only</option>
    </select>
    
    <!-- ⭐ NOVO: Filtro por status ⭐ -->
    <select id="filtro-status" style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
        <option value="todos">📊 All statuses</option>
        <option value="novo">🆕 New</option>
        <option value="atribuido">👨‍💻 Assigned</option>
        <option value="em_producao">⚙️ In Production</option>
        <option value="aguardando_revisao">⏳ Awaiting Review</option>
        <option value="em_revisao">🔍 In Review</option>
        <option value="em_acertos">🔧 Corrections</option>
        <option value="pronto">🎉 Ready</option>
    </select>
</div>
                
                <!-- Contador -->
                <div style="font-size: 13px; color: #666;">
                    <span id="contador-pedidos"><?php echo count($pedidos); ?> order(s) found</span>
                </div>
            </div>
        </div>

        <?php if (empty($pedidos)): ?>
            <p>No orders found with current filters.</p>
            <a href="<?php echo esc_url(site_url('/novo-pedido/')); ?>" class="button">➕ Place First Order</a>
        <?php else: ?>
		<table class="bordados-table" style="table-layout: fixed; width: 100%;">
		    <colgroup>
		        <col style="width: 70px;">  <!-- Order -->
		        <col style="width: 150px;"> <!-- Imagem -->
		        <col style="width: 350px;"> <!-- Nome -->
		        <col style="width: 130px;"> <!-- Dimensões -->
		        <col style="width: 90px;">  <!-- Prazo -->
		        <col style="width: 130px;"> <!-- Status -->
		        <col style="width: 100px;"> <!-- Data -->
		        <col style="width: 150px;"> <!-- Ações -->
		    </colgroup>
		<thead>
		    <tr>
		        <th style="width: 70px;">Order</th>
		        <th style="width: 150px;">Image</th>
		        <th style="width: 350px;">Name</th>
		        <th style="width: 130px;">Dimensions</th>
		        <th style="width: 90px;">Deadline</th>
		        <th style="width: 130px;">Status</th>
		        <th style="width: 100px;">Date</th>
		        <th style="width: 150px;">Actions</th>
		    </tr>
		</thead>
                <tbody id="tabela-pedidos">
                    <?php foreach ($pedidos as $pedido): ?>
	<tr class="linha-pedido" 
    data-nome="<?php echo esc_attr(strtolower($pedido->nome_bordado)); ?>"
    data-tipo="<?php echo esc_attr($pedido->tipo_pedido ?: 'original'); ?>"
    data-status="<?php echo esc_attr($pedido->status); ?>">
                        <td style="font-weight: bold;">#<?php echo $pedido->id; ?></td>
                        <td style="text-align: center;">
                            <?php 
                            // Se pronto, mostrar imagem final (se houver), senão imagem original
                            $imagem_display = Bordados_Helpers::obter_primeiro_arquivo($pedido);
                            
                            if ($pedido->status == 'pronto' && !empty($pedido->arquivos_finais)) {
                                $arquivos_finais_img = json_decode($pedido->arquivos_finais, true);
                                if (is_array($arquivos_finais_img)) {
                                    foreach ($arquivos_finais_img as $arq_final) {
                                        $ext_final = strtolower(pathinfo($arq_final, PATHINFO_EXTENSION));
                                        if (in_array($ext_final, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                            $imagem_display = $arq_final;
                                            break; // Usar a primeira imagem encontrada
                                        }
                                    }
                                }
                            }
                            
                            echo Bordados_Helpers::exibir_arquivo($imagem_display, '50px');
                            ?>
                        </td>
<td>
    <strong><?php echo esc_html($pedido->nome_bordado); ?></strong>
    
        <!-- ⭐ BADGES DE EDIÇÃO ⭐ -->
    <?php if ($pedido->tipo_pedido == 'edicao'): ?>
        <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; margin-left: 5px;">
            📝 EDIÇÃO v<?php echo $pedido->versao; ?>
        </span>
        
        <?php if ($pedido->edicao_gratuita == 1): ?>
            <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; margin-left: 5px;">
                🎁 GRATUITA
            </span>
        <?php else: ?>
            <span style="background: #ff9800; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; margin-left: 5px;">
                💰 PAGA
            </span>
        <?php endif; ?>
        
    <?php endif; ?>
<!-- ⭐ CONTADOR DE EDIÁÕES PARA PEDIDOS ORIGINAIS ⭐ -->
<?php if ($pedido->tipo_pedido == 'original' || empty($pedido->tipo_pedido)): ?>
    <?php
    // Contar quantas edições este pedido tem
    global $wpdb;
    $total_edicoes = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM pedidos_basicos WHERE pedido_pai_id = %d",
        $pedido->id
    ));
    
    if ($total_edicoes > 0):
    ?>
        <span style="background: #6c757d; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; margin-left: 5px;">
            📂 <?php echo $total_edicoes; ?> edição<?php echo $total_edicoes > 1 ? 'ões' : ''; ?>
        </span>
        <br><a href="#" 
               onclick="verHistoricoVersoes(<?php echo $pedido->id; ?>); return false;"
               style="font-size: 11px; color: #667eea; text-decoration: none; font-weight: 500;">
            📁 Ver Histórico
        </a>
    <?php endif; ?>
<?php endif; ?>
            <!-- ⭐ LINK PARA HISTÓRICO DE VERSÕES ⭐ -->
        <?php if ($pedido->tipo_pedido == 'edicao'): ?>
            <br><a href="#" 
                   onclick="verHistoricoVersoes(<?php echo $pedido->pedido_pai_id; ?>); return false;"
                   style="font-size: 11px; color: #667eea; text-decoration: none; font-weight: 500;">
                📁 Ver Histórico de Versões
            </a>
        <?php endif; ?>
    <?php if (!empty($pedido->cores)): ?>
        <br><small>🎨 <?php echo esc_html($pedido->cores); ?></small>
    <?php endif; ?>
</td>
                        <td>
                            <span style="font-size: 13px;">
                                <?php echo Bordados_Helpers::formatar_dimensoes($pedido); ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <?php echo Bordados_Helpers::get_prazo_badge_english($pedido->prazo_entrega); ?>
                        </td>
                        <td style="text-align: center;">
                            <?php echo Bordados_Helpers::get_status_badge_english($pedido->status); ?>
                        </td>
                        <td style="font-size: 12px;">
                            <?php echo Bordados_Helpers::formatar_data_hora($pedido->data_criacao); ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($pedido->status == 'pronto'): ?>
                                <?php
                                // Verificar se tem arquivos finais
                                $tem_arquivos = !empty($pedido->arquivos_finais);
                                $arquivos_finais = $tem_arquivos ? json_decode($pedido->arquivos_finais, true) : array();
                                $total_arquivos = is_array($arquivos_finais) ? count($arquivos_finais) : 0;
                                ?>

                                <?php if ($total_arquivos > 0): ?>
                                    <a href="#"
                                       onclick="baixarArquivos(<?php echo $pedido->id; ?>); return false;"
                                       class="button button-primary button-small"
                                       title="Download <?php echo $total_arquivos; ?> file(s)"
                                       style="background: #28a745; border-color: #28a745; text-decoration: none; cursor: pointer;">
                                        ⬇️ Download <?php echo $total_arquivos > 1 ? "($total_arquivos)" : ""; ?>
                                    </a>

                                    <!-- Botão alternativo para preview dos arquivos -->
                                    <br><small style="margin-top: 5px; display: block;">
                                        <a href="#" onclick="mostrarArquivosFinais(<?php echo $pedido->id; ?>); return false;"
                                           style="color: #666; text-decoration: none; font-size: 11px;">
                                           👁️ View files
                                        </a>
                                    </small>
                                <?php else: ?>
                                    <span style="color: #dc3545; font-size: 12px; font-style: italic;">
                                        ⚠️ Files not available
                                    </span>
                                <?php endif; ?>
                                <!-- ⭐ NOVO: Botão de Edição ⭐ -->
                                <?php if ($total_arquivos > 0): ?>
                                    <br><br>
                                    <button onclick="solicitarEdicao(<?php echo $pedido->id; ?>)" 
                                            class="button button-small"
                                            style="background: #ff9800; border-color: #ff9800; color: white; width: 100%;">
                                        ✏️ Request Edit
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="aguardando" style="font-size: 11px; color: #666;">
                                    <?php echo Bordados_Helpers::get_status_badge_english($pedido->status); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Linha expansível com detalhes completos -->
<tr class="detalhes-pedido linha-pedido" 
    style="display: none;" 
    id="detalhes-<?php echo $pedido->id; ?>" 
    data-nome="<?php echo esc_attr(strtolower($pedido->nome_bordado)); ?>"
    data-tipo="<?php echo esc_attr($pedido->tipo_pedido ?: 'original'); ?>"
    data-status="<?php echo esc_attr($pedido->status); ?>">
                        <td colspan="11" style="background: #f8f9fa; padding: 15px;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                <!-- Technical Specifications -->
                                <div>
                                    <h5>📋 Technical Specifications</h5>
                                    <p><strong>Dimensions:</strong> <?php echo Bordados_Helpers::formatar_dimensoes($pedido); ?></p>
                                    <?php if (!empty($pedido->local_bordado)): ?>
                                    <p><strong>Placement:</strong> <?php echo esc_html($pedido->local_bordado); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($pedido->tipo_tecido)): ?>
                                    <p><strong>Fabric:</strong> <?php echo esc_html($pedido->tipo_tecido); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($pedido->cores)): ?>
                                    <p><strong>Colors:</strong> <?php echo esc_html($pedido->cores); ?></p>
                                    <?php endif; ?>
                                    <p><strong>Turnaround:</strong> <?php echo esc_html($pedido->prazo_entrega); ?></p>
                                    <?php if (!empty($pedido->numero_pontos) && $pedido->numero_pontos > 0): ?>
                                    <p><strong>Stitch Count:</strong> <?php echo number_format($pedido->numero_pontos); ?></p>
                                    <?php endif; ?>
                                </div>

                                <!-- Reference Files -->
                                <div>
                                    <h5>📎 Reference Files</h5>
                                    <?php
                                    $arquivos_cliente = Bordados_Helpers::obter_arquivos_cliente($pedido);
                                    if (!empty($arquivos_cliente)): ?>
                                        <ul style="margin: 0; padding-left: 15px;">
                                            <?php foreach ($arquivos_cliente as $index => $arquivo): ?>
                                                <li style="margin-bottom: 5px;">
                                                    <a href="<?php echo esc_url(Bordados_Helpers::forcar_https($arquivo)); ?>" target="_blank">
                                                        📎 File <?php echo ($index + 1); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>No files found.</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Customer Notes -->
                                <?php if (!empty($pedido->observacoes)): ?>
                                <div>
                                    <h5>💬 Customer Notes</h5>
                                    <p style="font-style: italic;"><?php echo esc_html($pedido->observacoes); ?></p>
                                </div>
                                <?php endif; ?>

                                <!-- Programmer Notes -->
                                <?php if (!empty($pedido->observacoes_programador)): ?>
                                <div>
                                    <h5>👨‍💻 Programmer Notes</h5>
                                    <p style="font-style: italic; color: #0066cc;"><?php echo esc_html($pedido->observacoes_programador); ?></p>
                                </div>
                                <?php endif; ?>

                                <!-- Order Info -->
                                <div>
                                    <h5>📅 Order Info</h5>
                                    <p><strong>Order Date:</strong> <?php echo Bordados_Helpers::formatar_data_hora($pedido->data_criacao); ?></p>
                                    <?php if ($pedido->status == 'pronto' && !empty($pedido->data_conclusao)): ?>
                                    <p><strong>Completed:</strong> <?php echo Bordados_Helpers::formatar_data_hora($pedido->data_conclusao); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($pedido->preco_final) && $pedido->preco_final > 0): ?>
                                    <p><strong>Price:</strong> $<?php echo number_format($pedido->preco_final, 2); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="margin-top: 10px; text-align: right;">
                                <button onclick="toggleDetalhes(<?php echo $pedido->id; ?>)" class="button button-small">🔼 Close Details</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <br>
            <div style="text-align: center;">
                
            </div>
        <?php endif; ?>
    </div>

    <script>
    // Função para mostrar/esconder detalhes dos pedidos
    function toggleDetalhes(pedidoId) {
        const detalhes = document.getElementById('detalhes-' + pedidoId);
        const botao = event.target;

        if (detalhes.style.display === 'none' || detalhes.style.display === '') {
            detalhes.style.display = 'table-row';
            botao.innerHTML = '🔼 Close Details';
            botao.onclick = function() { toggleDetalhes(pedidoId); };
        } else {
            detalhes.style.display = 'none';
            botao.innerHTML = '🔽 View Details';
            botao.onclick = function() { toggleDetalhes(pedidoId); };
        }
    }

    // NOVO: Sistema de busca em tempo real
    document.addEventListener('DOMContentLoaded', function() {
        const campoBusca = document.getElementById('busca-pedidos');
        const checkboxTodos = document.getElementById('mostrar-todos');
        const contador = document.getElementById('contador-pedidos');
        const linhasPedidos = document.querySelectorAll('.linha-pedido');

// Função de busca e filtros em tempo real
function filtrarPedidos() {
    const termo = campoBusca.value.toLowerCase();
    const tipoSelecionado = document.getElementById('filtro-tipo').value;
    const statusSelecionado = document.getElementById('filtro-status').value;
    let contador_visivel = 0;

    linhasPedidos.forEach(function(linha) {
        const nome = linha.getAttribute('data-nome') || '';
        const tipo = linha.getAttribute('data-tipo') || 'original';
        const status = linha.getAttribute('data-status') || '';
        
        // Filtro de busca
        const matchBusca = nome.includes(termo);
        
        // Filtro de tipo
        const matchTipo = (tipoSelecionado === 'todos') || (tipo === tipoSelecionado);
        
        // Filtro de status
        const matchStatus = (statusSelecionado === 'todos') || (status === statusSelecionado);
        
        if (matchBusca && matchTipo && matchStatus) {
            linha.style.display = '';
            contador_visivel++;
        } else {
            linha.style.display = 'none';
        }
    });

    // Atualizar contador
    contador.textContent = contador_visivel + ' order(s) found';
}

// Busca em tempo real
campoBusca.addEventListener('input', filtrarPedidos);

// Filtros de tipo e status
document.getElementById('filtro-tipo').addEventListener('change', filtrarPedidos);
document.getElementById('filtro-status').addEventListener('change', filtrarPedidos);
        // Mudança de filtro todos/ativos
        checkboxTodos.addEventListener('change', function() {
            const url = new URL(window.location);
            if (this.checked) {
                url.searchParams.set('mostrar_todos', '1');
            } else {
                url.searchParams.delete('mostrar_todos');
            }
            window.location.href = url.toString();
        });

        // Adicionar botões "View Details" nas linhas principais
        const linhasNormais = document.querySelectorAll('.bordados-table tbody tr:not(.detalhes-pedido)');
        linhasNormais.forEach(function(linha, index) {
            if (linha.cells && linha.cells.length > 0) {
                const pedidoId = linha.cells[0].textContent.replace('#', '');
                const ultimaColuna = linha.cells[linha.cells.length - 1];
                
                // Adicionar botão de detalhes se não existir
                if (!ultimaColuna.querySelector('.btn-detalhes')) {
                    const btnDetalhes = document.createElement('br');
                    ultimaColuna.appendChild(btnDetalhes);
                    
                    const linkDetalhes = document.createElement('a');
                    linkDetalhes.href = '#';
                    linkDetalhes.className = 'btn-detalhes';
                    linkDetalhes.style.cssText = 'font-size: 11px; color: #666; text-decoration: none; margin-top: 5px; display: block;';
                    linkDetalhes.innerHTML = '🔽 View Details';
                    linkDetalhes.onclick = function(e) {
                        e.preventDefault();
                        toggleDetalhes(pedidoId);
                    };
                    ultimaColuna.appendChild(linkDetalhes);
                }
            }
        });
    });
// ===============================
// FUNÁÃO PARA SOLICITAR EDIÁÃO
// ===============================

function solicitarEdicao(pedidoId) {
    // Abrir modal de edição
    document.getElementById('modal-edicao').style.display = 'block';
    document.getElementById('pedido-id-edicao').value = pedidoId;
}

function fecharModalEdicao() {
    document.getElementById('modal-edicao').style.display = 'none';
    document.getElementById('form-edicao').reset();
}
function adicionarUploadEdicao() {
    const items = document.querySelectorAll('.upload-edicao-item');
    for (let i = 0; i < items.length; i++) {
        if (items[i].style.display === 'none') {
            items[i].style.display = 'block';
            if (i === items.length - 1) {
                document.getElementById('btn-add-upload-edicao').style.display = 'none';
            }
            break;
        }
    }
}

function removerUploadEdicao(btn) {
    const item = btn.closest('.upload-edicao-item');
    const input = item.querySelector('input[type="file"]');
    input.value = '';
    item.style.display = 'none';
    document.getElementById('btn-add-upload-edicao').style.display = 'inline-block';
}
// ===============================
// FUNÁÃO PARA VER HISTÓRICO DE VERSÕES
// ===============================

function verHistoricoVersoes(pedidoPaiId) {
    // Buscar histórico via AJAX
    jQuery.ajax({
        url: bordados_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'buscar_historico_versoes',
            nonce: bordados_ajax.nonce,
            pedido_pai_id: pedidoPaiId
        },
        success: function(response) {
            if (response.success) {
                mostrarModalHistorico(response.data);
            } else {
                alert('Error fetching histórico: ' + response.data);
            }
        }
    });
}

function mostrarModalHistorico(versoes) {
    let html = '<div id="modal-historico" style="display:block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 999999;">';
    html += '<div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; max-width: 800px; width: 90%; max-height: 80%; overflow-y: auto;">';
    html += '<h3 style="margin-top: 0;">📁 Histórico de Versões</h3>';
    
    html += '<div style="position: relative; padding-left: 30px;">';
    
    versoes.forEach((v, index) => {
        const isUltima = (index === 0);
        const cor = v.status === 'pronto' ? '#28a745' : (v.status === 'atribuido' ? '#ffc107' : '#6c757d');
        
        html += '<div style="position: relative; margin-bottom: 25px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid ' + cor + ';">';
        
        // Bolinha na timeline
        html += '<div style="position: absolute; left: -42px; top: 20px; width: 16px; height: 16px; background: ' + cor + '; border: 3px solid white; border-radius: 50%; box-shadow: 0 0 0 2px ' + cor + ';"></div>';
        
        // Linha vertical
        if (!isUltima) {
            html += '<div style="position: absolute; left: -38px; top: 36px; width: 2px; height: calc(100% + 10px); background: #ddd;"></div>';
        }
        
        html += '<div style="display: flex; justify-content: space-between; align-items: start;">';
        html += '<div style="flex: 1;">';
        html += '<h4 style="margin: 0 0 8px 0; color: ' + cor + ';">';
        html += (v.tipo_pedido === 'original' ? '📄 Versão Original' : '🔄 Edição v' + v.versao);
        
        if (v.edicao_gratuita == 1) {
            html += ' <span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: 5px;">🎁 GRATUITA</span>';
        } else if (v.tipo_pedido === 'edicao') {
            html += ' <span style="background: #ff9800; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: 5px;">💰 PAGA</span>';
        }
        
        html += '</h4>';
        
        if (v.motivo_edicao) {
            html += '<p style="margin: 5px 0; font-size: 13px; color: #666;"><strong>Motivo:</strong> ' + v.motivo_edicao + '</p>';
        }
        
        html += '<p style="margin: 5px 0; font-size: 12px; color: #999;">';
        html += '<strong>Criado:</strong> ' + v.data_criacao_formatada;
        html += ' | <strong>Status:</strong> ' + v.status_badge;
        html += '</p>';
        html += '</div>';
        
html += '<div style="text-align: right;">';
html += '<span style="background: ' + cor + '; color: white; padding: 5px 12px; border-radius: 15px; font-size: 11px; font-weight: bold;">#' + v.id + '</span>';

// Botão comparar (só para edições)
if (v.tipo_pedido === 'edicao' && index < versoes.length - 1) {
    html += '<br><button onclick="compararVersoes(' + v.id + ', ' + versoes[index + 1].id + ')" style="margin-top: 8px; padding: 4px 10px; background: #667eea; color: white; border: none; border-radius: 12px; font-size: 10px; cursor: pointer;">🔍 Comparar</button>';
}

html += '</div>';
        
        html += '</div>';
        html += '</div>';
    });
    
    html += '</div>';
    
    html += '<div style="margin-top: 20px; text-align: center;">';
        html += '<button onclick="fecharModalHistorico()" class="button">❌ Close</button>';
    html += '</div>';
    
    html += '</div></div>';
    
    document.body.insertAdjacentHTML('beforeend', html);
}

function fecharModalHistorico() {
    const modal = document.getElementById('modal-historico');
    if (modal) {
        modal.remove();
    }
}

// ===============================
// COMPARAR VERSÕES
// ===============================

function compararVersoes(versaoNovaId, versaoAntigaId) {
    jQuery.ajax({
        url: bordados_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'comparar_versoes',
            nonce: bordados_ajax.nonce,
            versao_nova: versaoNovaId,
            versao_antiga: versaoAntigaId
        },
        success: function(response) {
            if (response.success) {
                mostrarModalComparacao(response.data);
            } else {
                alert('Erro ao comparar: ' + response.data);
            }
        }
    });
}

function mostrarModalComparacao(dados) {
    let html = '<div id="modal-comparacao" style="display:block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 999999;">';
    html += '<div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; max-width: 900px; width: 90%; max-height: 80%; overflow-y: auto;">';
    html += '<h3 style="margin-top: 0;">🔍 Comparação de Versões</h3>';
    
    html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';
    
    // Versão Antiga
    html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #6c757d;">';
    html += '<h4 style="margin: 0 0 10px 0; color: #6c757d;">📄 Versão Anterior #' + dados.antiga.id + '</h4>';
    html += '<p><strong>Nome:</strong> ' + dados.antiga.nome_bordado + '</p>';
    html += '<p><strong>Data:</strong> ' + dados.antiga.data_criacao_formatada + '</p>';
    if (dados.antiga.motivo_edicao) {
        html += '<p><strong>Motivo:</strong> ' + dados.antiga.motivo_edicao + '</p>';
    }
    html += '</div>';
    
    // Versão Nova
    html += '<div style="padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ff9800;">';
    html += '<h4 style="margin: 0 0 10px 0; color: #ff9800;">🔄 Nova Versão #' + dados.nova.id + '</h4>';
    html += '<p><strong>Nome:</strong> ' + dados.nova.nome_bordado + '</p>';
    html += '<p><strong>Data:</strong> ' + dados.nova.data_criacao_formatada + '</p>';
    if (dados.nova.motivo_edicao) {
        html += '<p><strong>Motivo:</strong> ' + dados.nova.motivo_edicao + '</p>';
    }
    html += '</div>';
    
    html += '</div>';
    
    // Diferenças
    if (dados.diferencas && dados.diferencas.length > 0) {
        html += '<div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 15px;">';
        html += '<h4 style="margin: 0 0 10px 0;">📋 O que mudou:</h4>';
        html += '<ul style="margin: 0; padding-left: 20px;">';
        dados.diferencas.forEach(diff => {
            html += '<li>' + diff + '</li>';
        });
        html += '</ul>';
        html += '</div>';
    }
    
    html += '<div style="text-align: center;">';
    html += '<button onclick="fecharModalComparacao()" class="button">✕ Close</button>';
    html += '</div>';
    
    html += '</div></div>';
    
    document.body.insertAdjacentHTML('beforeend', html);
}

function fecharModalComparacao() {
    const modal = document.getElementById('modal-comparacao');
    if (modal) {
        modal.remove();
    }
}

// ===============================
// FUNÇÕES PARA ORÇAMENTOS (ETAPA 3)
// ===============================

function aprovarOrcamento(pedidoId) {
    if (!confirm('Are you sure you want to approve this quote? Your order will be processed.')) {
        return;
    }
    
    jQuery.ajax({
        url: bordados_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'aprovar_orcamento',
            nonce: bordados_ajax.nonce,
            pedido_id: pedidoId
        },
        success: function(response) {
            if (response.success) {
                alert('✅ ' + response.data.message);
                location.reload();
            } else {
                alert('❌ Error: ' + response.data.message);
            }
        },
        error: function() {
            alert('❌ Connection error. Please try again.');
        }
    });
}

function recusarOrcamento(pedidoId) {
    var motivo = prompt('Would you like to tell us why you\'re declining? (optional)');
    
    if (motivo === null) {
        return; // Usuário cancelou
    }
    
    jQuery.ajax({
        url: bordados_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'recusar_orcamento',
            nonce: bordados_ajax.nonce,
            pedido_id: pedidoId,
            motivo: motivo
        },
        success: function(response) {
            if (response.success) {
                alert('Quote declined.');
                location.reload();
            } else {
                alert('❌ Error: ' + response.data.message);
            }
        },
        error: function() {
            alert('❌ Connection error. Please try again.');
        }
    });
}

    </script>

    <!-- ⭐ MODAL PARA SOLICITAR EDIÇÃO ⭐ -->
    <div id="modal-edicao" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10001;">
        <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; max-width: 600px; width: 90%; max-height: 80%; overflow-y: auto;">
            <h4>✏️ Request Design Edit</h4>
            
            <div style="background: #fff3cd; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ff9800;">
                <p style="margin: 0; font-size: 14px;">
        <strong>📌 Important:</strong> The first edit is <strong>free</strong>.
                    Additional edits may have a cost.
                </p>
            </div>
            
            <form id="form-edicao" enctype="multipart/form-data">
                <input type="hidden" id="pedido-id-edicao" name="pedido_id">
                
                <div class="campo" style="margin-bottom: 20px;">
                    <label for="motivo-edicao">📂 What needs to be changed? *</label>
                    <textarea id="motivo-edicao" 
                              name="motivo_edicao" 
                              required 
                              rows="4"
                              placeholder="Describe the changes needed..."
                              style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    <small style="color: #666;">Be specific about the changes you want.</small>
                </div>
                
                <div class="campo" style="margin-bottom: 20px;">
                    <label>📎 New reference files (optional)</label>
                    
                    <div id="uploads-edicao-container">
                        <div class="upload-edicao-item" style="margin-bottom: 10px;">
                            <input type="file" 
                                   name="arquivos_edicao[]" 
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.ai,.eps,.svg" 
                                   style="width: 100%; margin-bottom: 5px;">
                            <small>File 1 - Optional</small>
                        </div>
                        
                        <div class="upload-edicao-item" style="display: none; margin-bottom: 10px;">
                            <input type="file" 
                                   name="arquivos_edicao[]" 
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.ai,.eps,.svg" 
                                   style="width: 80%; margin-bottom: 5px;">
                            <button type="button" onclick="removerUploadEdicao(this)" class="button-small" style="margin-left: 10px;">✕</button>
                            <br><small>File 2 - Optional</small>
                        </div>
                        
                        <div class="upload-edicao-item" style="display: none; margin-bottom: 10px;">
                            <input type="file" 
                                   name="arquivos_edicao[]" 
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.ai,.eps,.svg" 
                                   style="width: 80%; margin-bottom: 5px;">
                            <button type="button" onclick="removerUploadEdicao(this)" class="button-small" style="margin-left: 10px;">✕</button>
                            <br><small>File 3 - Optional</small>
                        </div>
                    </div>
                    
                    <button type="button" onclick="adicionarUploadEdicao()" class="button button-small" id="btn-add-upload-edicao">
                        ➕ Add File
                    </button>
                    
                    <small style="display: block; margin-top: 8px; color: #666;">
                        If you don't upload new files, we'll use the original order files.
                    </small>
                </div>
                
                <div style="margin-top: 25px; text-align: center;">
                    <button type="submit" class="button button-primary" style="background: #ff9800; border-color: #ff9800;">
                        ✅ Request Edit
                    </button>
                    <button type="button" onclick="fecharModalEdicao()" class="button" style="margin-left: 10px;">
                        ✕ Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
}

?>
