<?php
/**
 * Shortcode: Painel do Revisor - [bordados_painel_revisor]
 * 
 * ATUALIZADO v3.2.2: 
 * - Permissão para assistente_bordados
 * - Link para painel assistente
 * - Modal de solicitar acertos com upload de imagens (1-3)
 * - Upload múltiplo de arquivos revisados (2026-01-10)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Shortcode_Painel_Revisor {
    
    /**
     * Renderizar painel do revisor
     */
    public static function render($atts) {
        if (!is_user_logged_in()) {
            return '<p>You need to be logged in to access the review panel.</p>';
        }
        
        $user = wp_get_current_user();
        // ATUALIZADO: Permitir assistente_bordados também
        $roles_permitidas = array('revisor_bordados', 'assistente_bordados', 'administrator');
        $tem_permissao = false;
        foreach ($roles_permitidas as $role) {
            if (in_array($role, $user->roles)) {
                $tem_permissao = true;
                break;
            }
        }
        
        if (!$tem_permissao) {
            return '<p>Access restricted to reviewers.</p>';
        }
        
        // Verificar se é assistente (para mostrar link de volta)
        $is_assistente = in_array('assistente_bordados', $user->roles);
        
        $revisor_id = $user->ID;
        
        // Buscar trabalhos
        $trabalhos_aguardando = Bordados_Database::buscar_trabalhos_aguardando_revisao();
        $trabalhos_em_revisao = Bordados_Database::buscar_trabalhos_em_revisao($revisor_id);
        
        // NOVO: Buscar orçamentos pendentes
        $orcamentos_pendentes = Bordados_Database::buscar_orcamentos_pendentes();
        
        ob_start();
        ?>
        <div class="bordados-dashboard-revisor">
            <h3>🔍 Painel do Revisor</h3>
            
            <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h4 style="margin: 0 0 10px 0;">👋 Bem-vindo, <?php echo esc_html($user->display_name); ?>!</h4>
                        <p style="margin: 0;">
                            <strong>Orçamentos Pendentes:</strong> <?php echo count($orcamentos_pendentes); ?> |
                            <strong>Aguardando Revisão:</strong> <?php echo count($trabalhos_aguardando); ?> |
                            <strong>Em Revisão (seus):</strong> <?php echo count($trabalhos_em_revisao); ?>
                        </p>
                    </div>
                    <?php if ($is_assistente): ?>
                    <a href="<?php echo site_url('/painel-assistente/'); ?>" 
                       style="background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 500;">
                        👩‍💼 Painel Assistente
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- NOVO: SEÇÃO DE ORÇAMENTOS -->
            <?php if (!empty($orcamentos_pendentes)): ?>
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                <h4 style="margin: 0 0 10px 0;">💰 Orçamentos Pendentes (<?php echo count($orcamentos_pendentes); ?>)</h4>
                <p style="margin: 0;">Estes clientes estão aguardando um orçamento de preço.</p>
            </div>
            
            <?php foreach ($orcamentos_pendentes as $orcamento): ?>
                <?php echo self::card_orcamento($orcamento); ?>
            <?php endforeach; ?>
            
            <hr style="margin: 30px 0;">
            <?php endif; ?>
            
            <!-- Trabalhos em revisão -->
            <?php if (!empty($trabalhos_em_revisao)): ?>
            <div style="background: #d1ecf1; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0;">⚠️ Você tem <?php echo count($trabalhos_em_revisao); ?> trabalho(s) em revisão</h4>
                <p style="margin: 0;">Complete a revisão dos itens abaixo antes de pegar novos.</p>
            </div>
            
            <h4>🔍 Trabalhos que Você Está Revisando</h4>
            <?php foreach ($trabalhos_em_revisao as $trabalho): ?>
                <?php echo self::card_trabalho_revisao($trabalho, true); ?>
            <?php endforeach; ?>
            
            <hr style="margin: 30px 0;">
            <?php endif; ?>
            
            <!-- Fila de revisão -->
            <h4>📋 Fila de Revisão (<?php echo count($trabalhos_aguardando); ?>)</h4>
            
            <?php if (empty($trabalhos_aguardando)): ?>
                <div style="background: #d4edda; padding: 20px; border-radius: 8px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0;">✅ Ótimo!</h4>
                    <p style="margin: 0;">Não há trabalhos aguardando revisão no momento.</p>
                </div>
            <?php else: ?>
                <?php foreach ($trabalhos_aguardando as $trabalho): ?>
                    <?php echo self::card_trabalho_revisao($trabalho, false); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Modal para enviar orçamento -->
        <div id="modal-orcamento" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10001; align-items: center; justify-content: center;">
            <div style="background: white; padding: 30px; border-radius: 10px; max-width: 600px; width: 90%; max-height: 90%; overflow-y: auto;">
                <h4>💰 Enviar Orçamento ao Cliente</h4>
                
                <div id="orcamento-info" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;"></div>
                
                <form id="form-orcamento">
                    <input type="hidden" id="orcamento-pedido-id">
                    <input type="hidden" id="orcamento-cliente-id">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label><strong>Número de Pontos: *</strong></label>
                            <input type="number" id="orcamento-pontos" required style="width: 100%; padding: 10px;" 
                                   placeholder="Ex: 8500" onchange="calcularPrecoOrcamento()">
                        </div>
                        
                        <div>
                            <label><strong>Preço Calculado:</strong></label>
                            <input type="text" id="orcamento-preco-calculado" readonly style="width: 100%; padding: 10px; background: #e9ecef;">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label><strong>Preço Final (ajuste se necessário): *</strong></label>
                        <input type="number" id="orcamento-preco-final" step="0.01" required style="width: 100%; padding: 10px;" 
                               placeholder="0.00">
                        <small>O sistema calcula automaticamente, mas você pode ajustar.</small>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label><strong>Observações para o Cliente:</strong></label>
                        <textarea id="orcamento-obs" rows="3" style="width: 100%; padding: 10px;" 
                                  placeholder="Observações opcionais sobre o orçamento..."></textarea>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" class="button button-primary" style="background: #28a745; border-color: #28a745;">
                            📧 Enviar Orçamento ao Cliente
                        </button>
                        <button type="button" onclick="fecharModalOrcamento()" class="button" style="margin-left: 10px;">
                            ✕ Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- NOVO: Modal para solicitar acertos com upload de imagens -->
        <div id="modal-acertos" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10003; align-items: center; justify-content: center;">
            <div style="background: white; padding: 30px; border-radius: 10px; max-width: 600px; width: 90%; max-height: 90%; overflow-y: auto;">
                <h4>🔧 Solicitar Acertos ao Programador</h4>
                
                <div id="acertos-info" style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px;"></div>
                
                <form id="form-acertos" enctype="multipart/form-data">
                    <input type="hidden" id="acertos-pedido-id" name="pedido_id">
                    
                    <div style="margin-bottom: 20px;">
                        <label><strong>📝 Descreva os acertos necessários: *</strong></label>
                        <textarea id="acertos-descricao" name="obs_revisor" rows="5" required
                                  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"
                                  placeholder="Descreva detalhadamente o que precisa ser corrigido..."></textarea>
                    </div>
                    
                    <!-- Upload de imagens para acertos -->
                    <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <label><strong>📷 Anexar Imagens de Referência (opcional):</strong></label>
                        <p style="margin: 5px 0 10px 0; font-size: 13px; color: #666;">
                            Envie até 3 imagens mostrando os problemas a serem corrigidos (JPG, PNG ou PDF).
                        </p>
                        
                        <div id="uploads-acertos-container">
                            <div class="upload-acertos-item" style="margin-bottom: 10px;">
                                <input type="file" name="imagens_acertos[]" 
                                       accept=".jpg,.jpeg,.png,.pdf" 
                                       style="width: 80%;">
                                <small>Imagem 1</small>
                            </div>
                            <div class="upload-acertos-item" style="display: none; margin-bottom: 10px;">
                                <input type="file" name="imagens_acertos[]" 
                                       accept=".jpg,.jpeg,.png,.pdf" 
                                       style="width: 70%;">
                                <button type="button" onclick="removerUploadAcertos(this)" class="button-small" style="margin-left: 5px;">✕</button>
                                <small>Imagem 2</small>
                            </div>
                            <div class="upload-acertos-item" style="display: none; margin-bottom: 10px;">
                                <input type="file" name="imagens_acertos[]" 
                                       accept=".jpg,.jpeg,.png,.pdf" 
                                       style="width: 70%;">
                                <button type="button" onclick="removerUploadAcertos(this)" class="button-small" style="margin-left: 5px;">✕</button>
                                <small>Imagem 3</small>
                            </div>
                        </div>
                        <button type="button" onclick="adicionarUploadAcertos()" class="button button-small" id="btn-add-upload-acertos">
                            ➕ Adicionar Outra Imagem
                        </button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" class="button" style="background: #ffc107; border-color: #ffc107; color: #000; padding: 12px 30px; font-size: 16px;">
                            🔧 Solicitar Acertos
                        </button>
                        <button type="button" onclick="fecharModalAcertos()" class="button" style="margin-left: 10px; padding: 12px 20px;">
                            ✕ Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        // ========================================
        // MODAL DE ORÇAMENTO
        // ========================================
        function abrirModalOrcamento(pedidoId, clienteId, nomeBordado, clienteNome) {
            document.getElementById('orcamento-pedido-id').value = pedidoId;
            document.getElementById('orcamento-cliente-id').value = clienteId;
            document.getElementById('orcamento-info').innerHTML = 
                '<strong>Pedido #' + pedidoId + ':</strong> ' + nomeBordado + '<br>' +
                '<strong>Cliente:</strong> ' + clienteNome;
            document.getElementById('orcamento-pontos').value = '';
            document.getElementById('orcamento-preco-calculado').value = '';
            document.getElementById('orcamento-preco-final').value = '';
            document.getElementById('orcamento-obs').value = '';
            document.getElementById('modal-orcamento').style.display = 'flex';
        }
        
        function fecharModalOrcamento() {
            document.getElementById('modal-orcamento').style.display = 'none';
        }
        
        function calcularPrecoOrcamento() {
            var pontos = parseInt(document.getElementById('orcamento-pontos').value) || 0;
            var clienteId = document.getElementById('orcamento-cliente-id').value;
            
            if (pontos > 0) {
                jQuery.ajax({
                    url: bordados_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'calcular_preco_orcamento',
                        nonce: bordados_ajax.nonce,
                        cliente_id: clienteId,
                        pontos: pontos
                    },
                    success: function(response) {
                        if (response.success) {
                            document.getElementById('orcamento-preco-calculado').value = 
                                'R$ ' + response.data.preco_final + ' (' + response.data.detalhes + ')';
                            document.getElementById('orcamento-preco-final').value = response.data.preco_final;
                        }
                    }
                });
            }
        }
        
        document.getElementById('form-orcamento').addEventListener('submit', function(e) {
            e.preventDefault();
            
            var pedidoId = document.getElementById('orcamento-pedido-id').value;
            var pontos = document.getElementById('orcamento-pontos').value;
            var precoFinal = document.getElementById('orcamento-preco-final').value;
            var obs = document.getElementById('orcamento-obs').value;
            
            if (!pontos || !precoFinal) {
                alert('Por favor, preencha todos os campos obrigatórios.');
                return;
            }
            
            jQuery.ajax({
                url: bordados_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'enviar_orcamento',
                    nonce: bordados_ajax.nonce,
                    pedido_id: pedidoId,
                    numero_pontos: pontos,
                    preco_final: precoFinal,
                    obs_revisor: obs
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ Orçamento enviado com sucesso!');
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('❌ Erro de conexão. Tente novamente.');
                }
            });
        });
        
        // ========================================
        // MODAL DE SOLICITAR ACERTOS (NOVO)
        // ========================================
        function solicitarAcertos(pedidoId) {
            document.getElementById('acertos-pedido-id').value = pedidoId;
            document.getElementById('acertos-info').innerHTML = '<strong>Pedido #' + pedidoId + '</strong><br>Descreva os problemas encontrados e anexe imagens se necessário.';
            document.getElementById('acertos-descricao').value = '';
            
            // Reset uploads
            var items = document.querySelectorAll('#uploads-acertos-container .upload-acertos-item');
            items.forEach(function(item, index) {
                var input = item.querySelector('input[type="file"]');
                input.value = '';
                if (index > 0) item.style.display = 'none';
            });
            document.getElementById('btn-add-upload-acertos').style.display = 'inline-block';
            
            document.getElementById('modal-acertos').style.display = 'flex';
        }
        
        function fecharModalAcertos() {
            document.getElementById('modal-acertos').style.display = 'none';
        }
        
        function adicionarUploadAcertos() {
            var items = document.querySelectorAll('#uploads-acertos-container .upload-acertos-item');
            for (var i = 0; i < items.length; i++) {
                if (items[i].style.display === 'none') {
                    items[i].style.display = 'block';
                    if (i === items.length - 1) {
                        document.getElementById('btn-add-upload-acertos').style.display = 'none';
                    }
                    break;
                }
            }
        }
        
        function removerUploadAcertos(btn) {
            var item = btn.parentNode;
            var input = item.querySelector('input[type="file"]');
            input.value = '';
            item.style.display = 'none';
            document.getElementById('btn-add-upload-acertos').style.display = 'inline-block';
        }
        
        document.getElementById('form-acertos').addEventListener('submit', function(e) {
            e.preventDefault();
            
            var descricao = document.getElementById('acertos-descricao').value.trim();
            if (!descricao) {
                alert('Por favor, descreva os acertos necessários.');
                return;
            }
            
            var formData = new FormData(this);
            formData.append('action', 'solicitar_acertos_revisor');
            formData.append('nonce', bordados_ajax.nonce);
            
            // Mostrar loading
            var btnSubmit = this.querySelector('button[type="submit"]');
            var textoOriginal = btnSubmit.innerHTML;
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '⏳ Enviando...';
            
            jQuery.ajax({
                url: bordados_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = textoOriginal;
                    
                    if (response.success) {
                        alert('✅ ' + response.data);
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + response.data);
                    }
                },
                error: function() {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = textoOriginal;
                    alert('❌ Erro de conexão. Tente novamente.');
                }
            });
        });
        </script>
        
        <!-- Modal para aprovação do revisor -->
        <div id="modal-aprovacao" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10002; align-items: center; justify-content: center;">
            <div style="background: white; padding: 30px; border-radius: 10px; max-width: 700px; width: 90%; max-height: 90%; overflow-y: auto;">
                <h4>✅ Aprovar e Entregar ao Cliente</h4>
                
                <div id="aprovacao-info" style="background: #d4edda; padding: 15px; border-radius: 5px; margin-bottom: 20px;"></div>
                
                <form id="form-aprovacao" enctype="multipart/form-data">
                    <input type="hidden" id="aprovacao-pedido-id" name="pedido_id">
                    
                    <!-- Preços -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div>
                            <label><strong>💰 Preço do Programador:</strong></label>
                            <input type="text" id="aprovacao-preco-programador" readonly 
                                   style="width: 100%; padding: 10px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 5px;">
                            <small style="color: #666;">Definido pelo programador (apenas referência)</small>
                        </div>
                        <div>
                            <label><strong>💵 Preço Final para o Cliente:</strong></label>
                            <input type="number" id="aprovacao-preco-final" name="preco_final" step="0.01" min="0"
                                   style="width: 100%; padding: 10px; border: 1px solid #28a745; border-radius: 5px;">
                            <small style="color: #666;">Calculado automaticamente ou ajuste manualmente</small>
                        </div>
                    </div>
                    
                    <!-- Upload de arquivos revisados - ATUALIZADO: MÚLTIPLOS -->
                    <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <label><strong>📎 Enviar Arquivos Revisados (opcional):</strong></label>
                        <p style="margin: 5px 0 10px 0; font-size: 13px; color: #666;">
                            Se você fez alterações nos arquivos, envie as versões revisadas aqui (máx. 5 arquivos). 
                            Os arquivos originais do programador serão mantidos como backup.
                        </p>
                        
                        <input type="file" name="arquivos_revisados[]" id="arquivos-revisados-input"
                               accept=".emb,.dst,.exp,.pes,.vp3,.jef,.pdf,.jpg,.jpeg,.png" 
                               multiple
                               style="width: 100%; padding: 10px; border: 2px dashed #90caf9; border-radius: 5px; background: white; cursor: pointer;">
                        <div id="arquivos-selecionados" style="margin-top: 10px; font-size: 13px; color: #1976d2;"></div>
                    </div>
                    
                    <!-- Observações -->
                    <div style="margin-bottom: 20px;">
                        <label><strong>📝 Observações do Revisor (opcional):</strong></label>
                        <textarea id="aprovacao-obs" name="obs_revisor_aprovacao" rows="3" 
                                  placeholder="Observações sobre esta aprovação ou alterações feitas..."
                                  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" class="button button-primary" style="background: #28a745; border-color: #28a745; padding: 12px 30px; font-size: 16px;">
                            ✅ Aprovar e Entregar ao Cliente
                        </button>
                        <button type="button" onclick="fecharModalAprovacao()" class="button" style="margin-left: 10px; padding: 12px 20px;">
                            ✕ Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        // ========================================
        // MODAL DE APROVAÇÃO
        // ========================================
        function abrirModalAprovacao(pedidoId, nomeBordado, clienteNome, precoProgramador, clienteId, numeroPontos) {
            document.getElementById('aprovacao-pedido-id').value = pedidoId;
            document.getElementById('aprovacao-info').innerHTML = 
                '<strong>Pedido #' + pedidoId + ':</strong> ' + nomeBordado + '<br>' +
                '<strong>Cliente:</strong> ' + clienteNome;
            document.getElementById('aprovacao-preco-programador').value = precoProgramador ? 'R$ ' + parseFloat(precoProgramador).toFixed(2) : 'Não definido';
            document.getElementById('aprovacao-preco-final').value = '';
            document.getElementById('aprovacao-obs').value = '';
            
            // Reset upload múltiplo
            document.getElementById('arquivos-revisados-input').value = '';
            document.getElementById('arquivos-selecionados').innerHTML = '';
            
            document.getElementById('modal-aprovacao').style.display = 'flex';
            
            // CALCULAR PREÇO AUTOMATICAMENTE via AJAX
            if (clienteId && numeroPontos && numeroPontos > 0) {
                document.getElementById('aprovacao-preco-final').placeholder = 'Calculando...';
                
                jQuery.ajax({
                    url: bordados_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'calcular_preco_orcamento',
                        nonce: bordados_ajax.nonce,
                        cliente_id: clienteId,
                        pontos: numeroPontos,
                        preco_programador: precoProgramador
                    },
                    success: function(response) {
                        if (response.success && response.data.preco_final) {
                            document.getElementById('aprovacao-preco-final').value = response.data.preco_final;
                            document.getElementById('aprovacao-preco-final').placeholder = 'Calculado: R$ ' + response.data.preco_final;
                        } else {
                            document.getElementById('aprovacao-preco-final').value = precoProgramador || '';
                            document.getElementById('aprovacao-preco-final').placeholder = 'Informe o preço manualmente';
                        }
                    },
                    error: function() {
                        document.getElementById('aprovacao-preco-final').value = precoProgramador || '';
                        document.getElementById('aprovacao-preco-final').placeholder = 'Informe o preço manualmente';
                    }
                });
            } else {
                document.getElementById('aprovacao-preco-final').value = precoProgramador || '';
            }
        }
        
        function fecharModalAprovacao() {
            document.getElementById('modal-aprovacao').style.display = 'none';
        }
        
        // Mostrar arquivos selecionados no upload múltiplo
        document.getElementById('arquivos-revisados-input').addEventListener('change', function() {
            var files = this.files;
            var lista = document.getElementById('arquivos-selecionados');
            if (files.length > 0) {
                var html = '<strong>' + files.length + ' arquivo(s) selecionado(s):</strong><ul style="margin:5px 0;padding-left:20px;">';
                for (var i = 0; i < Math.min(files.length, 5); i++) {
                    html += '<li>' + files[i].name + '</li>';
                }
                if (files.length > 5) {
                    html += '<li>... e mais ' + (files.length - 5) + ' arquivo(s)</li>';
                }
                html += '</ul>';
                lista.innerHTML = html;
            } else {
                lista.innerHTML = '';
            }
        });
        
        document.getElementById('form-aprovacao').addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'aprovar_trabalho_revisor');
            formData.append('nonce', bordados_ajax.nonce);
            
            var btnSubmit = this.querySelector('button[type="submit"]');
            var textoOriginal = btnSubmit.innerHTML;
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '⏳ Processando...';
            
            jQuery.ajax({
                url: bordados_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = textoOriginal;
                    
                    if (response.success) {
                        alert('✅ ' + response.data);
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + response.data);
                    }
                },
                error: function() {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = textoOriginal;
                    alert('❌ Erro de conexão. Tente novamente.');
                }
            });
        });
        
        // ========================================
        // FUNÇÕES AUXILIARES
        // ========================================
        function iniciarRevisao(pedidoId) {
            if (!confirm('Iniciar revisão deste trabalho?')) return;
            
            jQuery.ajax({
                url: bordados_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'iniciar_revisao',
                    nonce: bordados_ajax.nonce,
                    pedido_id: pedidoId
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ Revisão iniciada!');
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + response.data);
                    }
                },
                error: function() {
                    alert('❌ Erro de conexão.');
                }
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * NOVO: Card de orçamento pendente
     */
    private static function card_orcamento($orcamento) {
        ob_start();
        
        // Buscar arquivos
        $arquivos = !empty($orcamento->arquivos_cliente) ? json_decode($orcamento->arquivos_cliente, true) : array();
        
        ?>
        <div class="orcamento-card" style="background: white; border: 2px solid #ffc107; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                <div>
                    <h4 style="margin: 0 0 5px 0;">
                        💰 Orçamento #<?php echo $orcamento->id; ?> - <?php echo esc_html($orcamento->nome_bordado); ?>
                    </h4>
                    <small style="color: #666;">
                        Cliente: <strong><?php echo esc_html($orcamento->cliente_nome); ?></strong> |
                        Tipo: <strong><?php echo $orcamento->tipo_produto == 'vetor' ? '✏️ Vetor' : '🧵 Digitalização'; ?></strong>
                    </small>
                </div>
                <span style="background: #ffc107; color: #000; padding: 5px 10px; border-radius: 5px; font-size: 12px;">
                    ⏳ ORÇAMENTO PENDENTE
                </span>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <p style="margin: 5px 0;"><strong>Dimensões:</strong> <?php echo Bordados_Helpers::formatar_dimensoes($orcamento); ?></p>
                    <p style="margin: 5px 0;"><strong>Local:</strong> <?php echo esc_html($orcamento->local_bordado); ?></p>
                </div>
                <div>
                    <p style="margin: 5px 0;"><strong>Tecido:</strong> <?php echo esc_html($orcamento->tipo_tecido); ?></p>
                    <p style="margin: 5px 0;"><strong>Prazo:</strong> <?php echo esc_html($orcamento->prazo_entrega); ?></p>
                </div>
            </div>
            
            <?php if (!empty($orcamento->observacoes)): ?>
            <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <strong>📝 Observações do Cliente:</strong><br>
                <?php echo esc_html($orcamento->observacoes); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($arquivos)): ?>
            <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <strong>📎 Arquivos de Referência (<?php echo count($arquivos); ?>):</strong><br>
                <?php foreach ($arquivos as $index => $arquivo): ?>
                    <a href="<?php echo esc_url(Bordados_Helpers::forcar_https($arquivo)); ?>" target="_blank" style="display: inline-block; margin: 5px 10px 5px 0;">
                        📄 Arquivo <?php echo ($index + 1); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div style="text-align: center;">
                <button onclick="abrirModalOrcamento(<?php echo $orcamento->id; ?>, <?php echo $orcamento->cliente_id; ?>, '<?php echo esc_js($orcamento->nome_bordado); ?>', '<?php echo esc_js($orcamento->cliente_nome); ?>')" 
                        class="button button-primary" style="background: #ffc107; border-color: #ffc107; color: #000;">
                    💰 Enviar Orçamento
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Card de trabalho para revisão (existente)
     */
    private static function card_trabalho_revisao($trabalho, $em_revisao = false) {
        ob_start();
        
        $arquivos_finais = !empty($trabalho->arquivos_finais) ? json_decode($trabalho->arquivos_finais, true) : array();
        
        ?>
        <div class="trabalho-card" style="background: white; border: 2px solid <?php echo $em_revisao ? '#ff9800' : '#2196f3'; ?>; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                <div>
                    <h4 style="margin: 0 0 5px 0;">Pedido #<?php echo $trabalho->id; ?> - <?php echo esc_html($trabalho->nome_bordado); ?></h4>
                    <small style="color: #666;">
                        Cliente: <strong><?php echo esc_html($trabalho->cliente_nome); ?></strong> | 
                        Programador: <strong><?php echo esc_html($trabalho->programador_nome); ?></strong>
                    </small>
                </div>
                <div>
                    <?php if ($em_revisao): ?>
                        <span style="background: #ff9800; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px;">
                            🔍 EM REVISÃO
                        </span>
                    <?php else: ?>
                        <span style="background: #2196f3; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px;">
                            ⏳ AGUARDANDO
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <p style="margin: 5px 0;"><strong>Dimensões:</strong> <?php echo Bordados_Helpers::formatar_dimensoes($trabalho); ?></p>
                    <p style="margin: 5px 0;"><strong>Local:</strong> <?php echo esc_html($trabalho->local_bordado); ?></p>
                    <p style="margin: 5px 0;"><strong>Tecido:</strong> <?php echo esc_html($trabalho->tipo_tecido); ?></p>
                </div>
                <div>
                    <p style="margin: 5px 0;"><strong>Preço Programador:</strong> <?php echo Bordados_Helpers::formatar_preco($trabalho->preco_programador); ?></p>
                    <p style="margin: 5px 0;"><strong>Prazo:</strong> <?php echo esc_html($trabalho->prazo_entrega); ?></p>
                    <?php if (!empty($trabalho->cores)): ?>
                        <p style="margin: 5px 0;"><strong>Cores:</strong> <?php echo esc_html($trabalho->cores); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($trabalho->numero_pontos)): ?>
                        <p style="margin: 5px 0;"><strong>Pontos:</strong> <?php echo number_format($trabalho->numero_pontos); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($trabalho->observacoes_programador)): ?>
            <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <strong>💬 Observações do Programador:</strong><br>
                <?php echo esc_html($trabalho->observacoes_programador); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($arquivos_finais)): ?>
            <div style="background: #f5f5f5; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <strong>📎 Arquivos Finais (<?php echo count($arquivos_finais); ?>):</strong><br>
                <?php foreach ($arquivos_finais as $index => $arquivo): ?>
                    <a href="<?php echo esc_url(Bordados_Helpers::forcar_https($arquivo)); ?>" target="_blank" style="display: inline-block; margin: 5px 10px 5px 0;">
                        📄 Arquivo <?php echo ($index + 1); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <?php if ($em_revisao): ?>
                    <button onclick="abrirModalAprovacao(<?php echo $trabalho->id; ?>, '<?php echo esc_js($trabalho->nome_bordado); ?>', '<?php echo esc_js($trabalho->cliente_nome); ?>', '<?php echo esc_attr($trabalho->preco_programador); ?>', <?php echo intval($trabalho->cliente_id); ?>, <?php echo intval($trabalho->numero_pontos); ?>)" 
                            class="button button-primary" 
                            style="background: #28a745; border-color: #28a745;">
                        ✅ Aprovar e Entregar
                    </button>
                    <button onclick="solicitarAcertos(<?php echo $trabalho->id; ?>)" 
                            class="button" 
                            style="background: #ffc107; border-color: #ffc107; color: #000;">
                        🔧 Solicitar Acertos
                    </button>
                <?php else: ?>
                    <button onclick="iniciarRevisao(<?php echo $trabalho->id; ?>)" 
                            class="button button-primary">
                        🔍 Iniciar Revisão
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
