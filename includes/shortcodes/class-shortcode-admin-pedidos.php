<?php
/**
 * Shortcode: Dashboard Admin - [bordados_admin_pedidos]
 * Extraído de class-shortcodes.php na Fase 3 da modularização
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Shortcode_Admin_Pedidos {
    
    /**
     * Renderizar dashboard admin
     */
    public static function render($atts) {
    if (!current_user_can('manage_options')) {
        return '<p>Acesso restrito a administradores.</p>';
    }
    
    $pedidos_novos = Bordados_Database::buscar_pedidos_novos();
    $pedidos_em_andamento = Bordados_Database::buscar_pedidos_em_andamento();
    $trabalhos_concluidos = Bordados_Database::buscar_trabalhos_concluidos(10);
    $programadores = Bordados_Helpers::listar_programadores();
    
    ob_start();
    ?>
    <div class="bordados-dashboard-admin">
        
        <!-- Link de navegação -->
        <div style="margin-bottom: 20px;">
            <a href="<?php echo esc_url(site_url('/gerenciar-pedidos/')); ?>" class="button" style="background: #6c757d; border-color: #6c757d; color: white;">
                ← Back to Order Management
            </a>
        </div>
        
        <div id="mensagem-admin" style="display: none; padding: 15px; margin: 20px 0; border-radius: 5px;"></div>
        
        <!-- ========== PEDIDOS NOVOS - SEÇÃO 1 (TOPO) ========== -->
        <?php if (!empty($pedidos_novos)): ?>
        <!-- VERSAO REORGANIZADA - 18/Nov/2025 20:40 -->
            <h3>⚙️Â Orders to Assign (<?php echo count($pedidos_novos); ?>)</h3>
            
            <?php if (empty($programadores)): ?>
                <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    ⚠️ <strong>Warning:</strong> No programmer found! 
                    <br>Check if there are users com a role "programador_bordados".
                </div>
            <?php else: ?>
                <div style="background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 15px 0;">
                    📋 <strong>Available programmers:</strong> 
                    <?php 
                    $nomes = array_map(function($p) { return $p->display_name; }, $programadores);
                    echo implode(', ', $nomes);
                    ?>
                </div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(450px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <?php foreach ($pedidos_novos as $pedido): ?>
                <div style="background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h4 style="margin: 0; color: #333;">
                            Order #<?php echo $pedido->id; ?> - <?php echo esc_html($pedido->nome_bordado); ?>
                        </h4>
                        <?php echo Bordados_Helpers::get_prazo_badge_english($pedido->prazo_entrega); ?>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                        <!-- Imagem -->
                        <div style="flex-shrink: 0;">
                            <?php echo Bordados_Helpers::exibir_arquivo(Bordados_Helpers::obter_primeiro_arquivo($pedido), '80px'); ?>
                        </div>
                        
                        <!-- Informações básicas -->
                        <div style="flex: 1;">
                            <p style="margin: 0 0 8px 0;"><strong>Cliente:</strong> <?php echo esc_html($pedido->cliente_nome); ?></p>
                            <p style="margin: 0 0 8px 0;"><strong>Data:</strong> <?php echo Bordados_Helpers::formatar_data_hora($pedido->data_criacao); ?></p>
                            <p style="margin: 0 0 8px 0;"><strong>Dimensões:</strong> <?php echo Bordados_Helpers::formatar_dimensoes($pedido); ?></p>
                            <?php if (!empty($pedido->cores)): ?>
                                <p style="margin: 0 0 8px 0;"><strong>Cores:</strong> <?php echo esc_html($pedido->cores); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Especificações técnicas -->
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <strong>📂 Local:</strong><br>
                                <?php echo esc_html(Bordados_Helpers::formatar_local_bordado($pedido->local_bordado)); ?>
                            </div>
                            <div>
                                <strong>🧵 Tecido:</strong><br>
                                <?php echo esc_html(Bordados_Helpers::formatar_tipo_tecido($pedido->tipo_tecido)); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Arquivos -->
                    <?php 
                    $arquivos_cliente = Bordados_Helpers::obter_arquivos_cliente($pedido);
                    if (count($arquivos_cliente) > 1): ?>
                        <p style="margin: 0 0 15px 0; font-size: 13px;">
                            <strong>📎 Arquivos:</strong> <?php echo count($arquivos_cliente); ?> arquivo(s) - 
                            <a href="#" onclick="toggleArquivos(<?php echo $pedido->id; ?>)" style="text-decoration: none;">
                                <span id="toggle-arquivos-<?php echo $pedido->id; ?>">👁️ View files</span>
                            </a>
                        </p>
                        
                        <div id="arquivos-<?php echo $pedido->id; ?>" style="display: none; background: #f0f0f0; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                            <?php foreach ($arquivos_cliente as $index => $arquivo): ?>
                                <div style="margin-bottom: 5px;">
                                    <a href="<?php echo esc_url(Bordados_Helpers::forcar_https($arquivo)); ?>" target="_blank" style="font-size: 12px;">
                                        📎 Arquivo <?php echo ($index + 1); ?> - <?php echo basename($arquivo); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Observações -->
                    <?php if (!empty($pedido->observacoes)): ?>
                        <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px;">
                            <strong>💬 Observações do cliente:</strong><br>
                            <?php echo esc_html($pedido->observacoes); ?>
                        </div>
                    <?php endif; ?>
                    
<?php if (!empty($pedido->obs_revisor)): ?>
<div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ff9800;">
    <h5 style="margin: 0 0 10px 0; color: #e65100; font-size: 14px;">🔧 Acertos Solicitados pelo Revisor</h5>
    
    <!-- Timeline das datas -->
    <div style="background: rgba(255,255,255,0.7); padding: 10px; border-radius: 5px; margin-bottom: 12px; font-size: 12px;">
        <strong style="color: #666;">�â€œâ€¦ Timeline do Pedido:</strong>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; margin-top: 8px;">
            <div>
                <span style="color: #2196f3;">📂 Criado:</span><br>
                <strong><?php echo Bordados_Helpers::formatar_data_hora($pedido->data_criacao); ?></strong>
            </div>
            <?php if (!empty($pedido->data_atribuicao)): ?>
            <div>
                <span style="color: #4caf50;">✅ Atribuído:</span><br>
                <strong><?php echo Bordados_Helpers::formatar_data_hora($pedido->data_atribuicao); ?></strong>
            </div>
            <?php endif; ?>
            <?php if (!empty($pedido->data_inicio_revisao)): ?>
            <div>
                <span style="color: #ff9800;">� Início Revisão:</span><br>
                <strong><?php echo Bordados_Helpers::formatar_data_hora($pedido->data_inicio_revisao); ?></strong>
            </div>
            <?php endif; ?>
            <?php if (!empty($pedido->data_fim_revisao)): ?>
            <div>
                <span style="color: #f44336;">🔧 Acertos em:</span><br>
                <strong><?php echo Bordados_Helpers::formatar_data_hora($pedido->data_fim_revisao); ?></strong>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Mensagem do revisor -->
    <div style="background: white; padding: 12px; border-radius: 5px; margin-bottom: 8px;">
        <span style="background: #ff5722; color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">🇧🇷 PT</span>
        <p style="margin: 8px 0 0 0; font-style: italic; font-weight: 600; color: #d84315;">"<?php echo esc_html($pedido->obs_revisor); ?>"</p>
    </div>
    
    <div style="background: white; padding: 12px; border-radius: 5px;">
        <span style="background: #ff9800; color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">🇺🇸 EN</span>
        <p style="margin: 8px 0 0 0; color: #f57c00; font-weight: 500;">"<?php echo esc_html(Bordados_Helpers::traduzir_google_free($pedido->obs_revisor)); ?>"</p>
    </div>
    
    <!-- Info adicional -->
    <div style="margin-top: 10px; padding: 8px; background: #fff; border-radius: 4px; border-left: 3px solid #ff5722;">
        <small style="color: #666; font-size: 12px;">
            <strong>Ciclo de acertos:</strong> #<?php echo $pedido->ciclos_acertos; ?> | 
            <strong>Revisor:</strong> <?php echo get_userdata($pedido->revisor_id)->display_name; ?>
        </small>
    </div>
</div>
<?php endif; ?>
                    <!-- Atribuição -->
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <select id="programador-<?php echo $pedido->id; ?>" style="flex: 1; padding: 8px;">
                            <option value="">Escolher programador...</option>
                            <?php foreach ($programadores as $prog): ?>
                                <option value="<?php echo $prog->ID; ?>"><?php echo esc_html($prog->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="atribuirPedido(<?php echo $pedido->id; ?>)" class="button button-primary" id="btn-atribuir-<?php echo $pedido->id; ?>">✅ Assign</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
		<div style="text-align: center; margin: 20px 0;">
                	<a href="<?php echo esc_url(site_url('/gerenciar-pedidos/')); ?>" class="button button-primary">
                    🗂️ Manage All Orders
                	</a>
            </div>
		<h3>✅ All orders are assigned!</h3>
            <p>Não há pedidos novos aguardando atribuição.</p>
        <?php endif; ?>
        
        <!-- ========== PEDIDOS EM ANDAMENTO - SEÇÃO 2 ========== -->

        <?php if (!empty($pedidos_em_andamento)): ?>
            <h3>⚙️ Pedidos em Andamento (<?php echo count($pedidos_em_andamento); ?>)</h3>
            <p style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                📊 Trabalhos que foram atribuídos e estão sendo processados pelos programadores.
            </p>
            
            <table class="bordados-table" style="margin-bottom: 30px;">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Customer</th>
                        <th>Programador</th>
                        <th>Trabalho</th>
                        <th>Dimensions</th>
                        <th>Prazo</th>
                        <th>Status</th>
                        <th>Atribuído em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos_em_andamento as $pedido): ?>
                    <tr>
                        <td style="font-weight: bold;">#<?php echo $pedido->id; ?></td>
                        <td><?php echo esc_html($pedido->cliente_nome); ?></td>
                        <td><?php echo esc_html($pedido->programador_nome); ?></td>
                        <td>
                            <?php echo esc_html($pedido->nome_bordado); ?>
                            <br><small style="color: #666;">
                                <?php echo esc_html(Bordados_Helpers::formatar_local_bordado($pedido->local_bordado)); ?>
                            </small>
                        </td>
                        <td style="font-size: 12px;">
                            <?php echo Bordados_Helpers::formatar_dimensoes($pedido); ?>
                        </td>
                        <td style="text-align: center;">
                            <?php echo Bordados_Helpers::get_prazo_badge_english($pedido->prazo_entrega); ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($pedido->status === 'atribuido'): ?>
                                <span style="background: #ffc107; color: #000; padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: bold;">
                                    👨‍💻 Atribuído
                                </span>
                            <?php else: ?>
                                <span style="background: #2196f3; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: bold;">
                                    ⚙️ Em Produção
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12px;">
                            <?php echo Bordados_Helpers::formatar_data($pedido->data_atribuicao); ?>
                        </td>
                        <td style="text-align: center;">
                            <button onclick="visualizarPedidoAdmin(<?php echo $pedido->id; ?>, '<?php echo esc_js($pedido->nome_bordado); ?>')" 
                                    class="button button-small" 
                                    style="padding: 5px 10px; font-size: 12px;">
                                👁️ Ver
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <hr style="margin: 30px 0;">
        <?php endif; ?>
        
        <!-- ========== TRABALHOS CONCLUÃDOS - SEÇÃO 3 (FINAL) ========== -->

        <?php if (!empty($trabalhos_concluidos)): ?>
            <h3>📊 Últimos Trabalhos Concluídos</h3>
            <table class="bordados-table">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Customer</th>
                        <th>Programador</th>
                        <th>Trabalho</th>
                        <th>Dimensions</th>
                        <th>Prazo</th>
                        <th>Preço</th>
                        <th>Date</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trabalhos_concluidos as $trabalho): ?>
                    <tr>
                        <td style="font-weight: bold;">#<?php echo $trabalho->id; ?></td>
                        <td><?php echo esc_html($trabalho->cliente_nome); ?></td>
                        <td><?php echo esc_html($trabalho->programador_nome); ?></td>
                        <td>
                            <?php echo esc_html($trabalho->nome_bordado); ?>
                            <br><small style="color: #666;">
                                <?php echo esc_html(Bordados_Helpers::formatar_local_bordado($trabalho->local_bordado)); ?>
                            </small>
                        </td>
                        <td style="font-size: 12px;">
                            <?php echo Bordados_Helpers::formatar_dimensoes($trabalho); ?>
                        </td>
                        <td style="text-align: center;">
                            <?php echo Bordados_Helpers::get_prazo_badge_english($trabalho->prazo_entrega); ?>
                        </td>
                        <td style="text-align: right;">
                            <?php echo Bordados_Helpers::formatar_preco($trabalho->preco_programador); ?>
                        </td>
                        <td style="font-size: 12px;">
                            <?php echo Bordados_Helpers::formatar_data($trabalho->data_conclusao); ?>
                        </td>
                        <td style="text-align: center;">
                            <button onclick="visualizarPedidoAdmin(<?php echo $trabalho->id; ?>, '<?php echo esc_js($trabalho->nome_bordado); ?>')" 
                                    class="button button-small" 
                                    style="padding: 5px 10px; font-size: 12px;">
                                👁️ Ver
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <hr>
        <?php endif; ?>
        
            </div>
        
    </div>
    
    <!-- MODAL DE VISUALIZAÇÃO -->
    <div id="modal-visualizar-admin" style="display: none; position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; background: rgba(0,0,0,0.7) !important; z-index: 999999 !important; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 10px; max-width: 800px; max-height: 90vh; overflow-y: auto; position: relative; z-index: 1000000 !important;">
            <button onclick="fecharModalVisualizarAdmin()" style="position: absolute; top: 15px; right: 15px; background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; z-index: 1000001 !important;">✕ Fechar</button>
            
            <h2 style="margin: 0 0 20px 0; color: #333;">
                📋 Detalhes do Pedido <span id="visual-pedido-id-admin"></span>
            </h2>
            
            <div id="conteudo-visualizacao-admin"></div>
        </div>
    </div>
    
    <script>
    // Função para visualizar pedido com AJAX
    function visualizarPedidoAdmin(pedidoId, nomeBordado) {
        console.log('👁️ Visualizando pedido:', pedidoId, nomeBordado);
        
        document.getElementById('visual-pedido-id-admin').textContent = '#' + pedidoId;
        
        // Buscar dados completos do pedido
        const conteudo = document.getElementById('conteudo-visualizacao-admin');
        conteudo.innerHTML = '<div style="text-align: center; padding: 40px;"><div style="font-size: 20px;">⏳</div><p>Carregando detalhes...</p></div>';
        
        document.getElementById('modal-visualizar-admin').style.display = 'flex';
        
        // Buscar dados reais via AJAX
        jQuery.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: {
                action: 'bordados_buscar_detalhes_pedido_admin',
                nonce: '<?php echo wp_create_nonce("bordados_admin_nonce"); ?>',
                pedido_id: pedidoId
            },
            success: function(response) {
                console.log('✅ Dados recebidos:', response);
                
                if (response.success) {
                    const pedido = response.data;
                    
                    // Montar HTML com dados reais
                    let html = `
                        <div class="pedido-detalhes-completo">
                            <h4>📋 Informações Básicas</h4>
                            <p><strong>Nome do Bordado:</strong> ${pedido.nome_bordado}</p>
                            <p><strong>ID do Pedido:</strong> #${pedido.id}</p>
                            <p><strong>Status:</strong> <span class="status-badge">${pedido.status}</span></p>
                            <p><strong>Tamanho:</strong> ${pedido.tamanho || 'Não informado'}</p>
                            <p><strong>Cores:</strong> ${pedido.cores || 'Não informado'}</p>
                            
                            <h4>👤 Cliente</h4>
                            <p><strong>Nome:</strong> ${pedido.cliente.nome}</p>
                            <p><strong>Email:</strong> ${pedido.cliente.email}</p>
                            
                            ${pedido.programador.nome ? `
                            <h4>👨‍💻 Programador</h4>
                            <p><strong>Nome:</strong> ${pedido.programador.nome}</p>
                            <p><strong>Email:</strong> ${pedido.programador.email}</p>
                            ` : ''}
                            
                            ${pedido.observacoes ? `
                            <h4>📝 Observações do Cliente</h4>
                            <p>${pedido.observacoes}</p>
                            ` : ''}
                            
                            ${pedido.observacoes_programador ? `
                            <h4>💬 Observações do Programador</h4>
                            <p>${pedido.observacoes_programador}</p>
                            ` : ''}
                            
                            ${pedido.preco_programador ? `
                            <h4>💰 Preço</h4>
                            <p>R$ ${pedido.preco_programador}</p>
                            ` : ''}
                            
                            <h4>📅 Datas</h4>
                            <p><strong>Criação:</strong> ${pedido.datas.criacao}</p>
                            ${pedido.datas.atribuicao ? `<p><strong>Atribuição:</strong> ${pedido.datas.atribuicao}</p>` : ''}
                            ${pedido.datas.conclusao ? `<p><strong>Conclusão:</strong> ${pedido.datas.conclusao}</p>` : ''}
                    `;
                    
                    // Arquivos do cliente
                    if (pedido.arquivos_cliente && pedido.arquivos_cliente.length > 0) {
                        html += '<h4>📎 Arquivos do Cliente</h4><ul>';
                        pedido.arquivos_cliente.forEach(arquivo => {
                            const arquivoHttps = arquivo.replace('http://', 'https://');
                            html += `<li><a href="${arquivoHttps}" target="_blank">${arquivo.split('/').pop()}</a></li>`;
                        });
                        html += '</ul>';
                    }
                    
                    // Arquivos finais
                    if (pedido.arquivos_finais && pedido.arquivos_finais.length > 0) {
                        html += '<h4>✅ Arquivos Finais</h4><ul>';
                        pedido.arquivos_finais.forEach(arquivo => {
                            const arquivoHttps = arquivo.replace('http://', 'https://');
                            html += `<li><a href="${arquivoHttps}" target="_blank">${arquivo.split('/').pop()}</a></li>`;
                        });
                        html += '</ul>';
                    }
                    
                    html += '</div>';
                    conteudo.innerHTML = html;
                } else {
                    conteudo.innerHTML = '<div style="color: red; padding: 20px;">❌ ' + (response.data || 'Erro ao carregar detalhes') + '</div>';
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Erro AJAX:', error);
                conteudo.innerHTML = '<div style="color: red; padding: 20px;">❌ Erro ao carregar detalhes do pedido</div>';
            }
        });
    }
    
    function fecharModalVisualizarAdmin() {
        document.getElementById('modal-visualizar-admin').style.display = 'none';
    }
    
    </script>
    <script>
    // Função para mostrar/esconder arquivos
    function toggleArquivos(pedidoId) {
        const arquivos = document.getElementById('arquivos-' + pedidoId);
        const toggle = document.getElementById('toggle-arquivos-' + pedidoId);
        
        if (arquivos.style.display === 'none' || arquivos.style.display === '') {
            arquivos.style.display = 'block';
            toggle.innerHTML = '🙆 Esconder arquivos';
        } else {
            arquivos.style.display = 'none';
            toggle.innerHTML = '👁️ View files';
        }
    }
    </script>
    <?php
    return ob_get_clean();
}
}

?>
