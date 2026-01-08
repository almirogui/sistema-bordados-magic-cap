<?php
/**
 * Shortcode: Dashboard do Programador - [bordados_meus_trabalhos]
 * Extraído de class-shortcodes.php na Fase 3 da modularização
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Shortcode_Meus_Trabalhos {
    
    /**
     * Renderizar dashboard do programador
     */
    public static function render($atts) {
    if (!is_user_logged_in()) {
        return '<p>Você precisa estar logado.</p>';
    }

    $programador_id = get_current_user_id();
    $pedidos = Bordados_Database::buscar_trabalhos_programador($programador_id);

    ob_start();
    ?>
    <div class="bordados-dashboard-programador">
        <h3>🎁¯ My Work Orders</h3>

        <?php if (empty($pedidos)): ?>
            <p>You have no pending work orders at the moment.</p>
        <?php else: ?>
            <?php foreach ($pedidos as $pedido): ?>
            <div class="trabalho-card">
                <h4>Order #<?php echo $pedido->id; ?> - <?php echo esc_html($pedido->nome_bordado); ?></h4>

                <div class="trabalho-info" style="display: flex; gap: 20px; margin: 15px 0;">
                    <!-- Coluna da imagem -->
                    <div style="flex-shrink: 0;">
                        <?php
                        $primeiro_arquivo = Bordados_Helpers::obter_primeiro_arquivo($pedido);
                        if (!empty($primeiro_arquivo) && Bordados_Helpers::is_imagem($primeiro_arquivo)): ?>
                            <img src="<?php echo esc_url(Bordados_Helpers::forcar_https($primeiro_arquivo)); ?>"
                                 style="width: 120px; height: 120px; object-fit: cover; border-radius: 10px; cursor: pointer; border: 2px solid #ddd;"
                                 onclick="mostrarImagemGrande('<?php echo esc_url(Bordados_Helpers::forcar_https($primeiro_arquivo)); ?>')"
                                 title="Clique para ampliar">
                        <?php else: ?>
                            <div style="width: 120px; height: 120px; background: #f9f9f9; border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 2px dashed #ccc;">
                                <span style="color: #999; font-size: 14px;">Sem imagem</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Coluna das informações -->
                    <div style="flex: 1;">
                        <p><strong>Customer:</strong> <?php echo esc_html($pedido->cliente_nome); ?></p>
                        <p><strong>Dimensions:</strong> <?php echo Bordados_Helpers::formatar_dimensoes($pedido); ?></p>
                        <?php if (!empty($pedido->cores)): ?>
                            <p><strong>Colors:</strong> <?php echo esc_html($pedido->cores); ?></p>
                        <?php endif; ?>
                        <p><strong>Status:</strong> <?php echo Bordados_Helpers::get_status_badge_english($pedido->status); ?></p>
                        
                        <?php if (!empty($pedido->observacoes)): ?>
                            <div style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #1976d2;">
                                <h5 style="margin: 0 0 10px 0; color: #1565c0; font-size: 14px;">📂 Customer Instructions</h5>

                                <div style="background: white; padding: 10px; border-radius: 5px; margin-bottom: 8px;">
                                    <span style="background: #4caf50; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; font-weight: bold;">🇧🇷 PT</span>
                                    <p style="margin: 5px 0 0 0; font-style: italic;">"<?php echo esc_html($pedido->observacoes); ?>"</p>
                                </div>

                                <div style="background: white; padding: 10px; border-radius: 5px;">
                                    <span style="background: #2196f3; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; font-weight: bold;">🇺🇸 EN</span>
                                    <p style="margin: 5px 0 0 0; color: #1976d2; font-weight: 500;">"<?php echo esc_html(Bordados_Helpers::traduzir_google_free($pedido->observacoes)); ?>"</p>
                                </div>

                                <small style="color: #666; font-size: 11px; margin-top: 5px; display: block;">Auto-translated by Google</small>
                            </div>
                        <?php endif; ?>

                        <!-- ⭐ NOVO: OBSERVAÁÕES DO REVISOR ⭐ -->
                        <?php if (!empty($pedido->obs_revisor)): ?>
                        <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ff9800;">
                            <h5 style="margin: 0 0 10px 0; color: #e65100; font-size: 14px;">🔧 Reviewer Corrections Needed</h5>
                            
                            <!-- Timeline das datas -->
                            <div style="background: rgba(255,255,255,0.7); padding: 10px; border-radius: 5px; margin-bottom: 12px; font-size: 12px;">
                                <strong style="color: #666;">�â€œâ€¦ Order Timeline:</strong>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin-top: 8px;">
                                    <div>
                                        <span style="color: #2196f3;">📂 Created:</span><br>
                                        <strong><?php echo Bordados_Helpers::formatar_data_hora($pedido->data_criacao); ?></strong>
                                    </div>
                                    <?php if (!empty($pedido->data_inicio_revisao)): ?>
                                    <div>
                                        <span style="color: #ff9800;">🔍 Review Started:</span><br>
                                        <strong><?php echo Bordados_Helpers::formatar_data_hora($pedido->data_inicio_revisao); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($pedido->data_fim_revisao)): ?>
                                    <div>
                                        <span style="color: #f44336;">🔧 Corrections on:</span><br>
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
                                    <strong>Correction cycle:</strong> #<?php echo $pedido->ciclos_acertos; ?> | 
                                    <strong>Reviewer:</strong> <?php echo $pedido->revisor_nome ?? 'N/A'; ?>
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                        <!-- ⭐ FIM OBSERVAÁÕES DO REVISOR ⭐ -->

                        <!-- Mostrar todos os arquivos do cliente -->
                        <?php
                        $arquivos_cliente = Bordados_Helpers::obter_arquivos_cliente($pedido);
                        if (!empty($arquivos_cliente)): ?>
                            <p><strong>Reference files:</strong></p>
                            <ul style="margin: 5px 0; padding-left: 20px;">
                                <?php foreach ($arquivos_cliente as $index => $arquivo): ?>
                                    <li><a href="<?php echo esc_url(Bordados_Helpers::forcar_https($arquivo)); ?>" target="_blank">📎 File <?php echo ($index + 1); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="trabalho-acoes">
                    <?php if ($pedido->status == 'atribuido'): ?>
                        <button onclick="iniciarProducao(<?php echo $pedido->id; ?>)" class="button button-primary">&#9654; Start Production</button>
                    <?php endif; ?>

                    <?php if ($pedido->status == 'em_producao'): ?>
                        <button onclick="entregarTrabalho(<?php echo $pedido->id; ?>)" class="button button-success">📤 Deliver Work</button>
                    <?php endif; ?>
                    
                    <?php if ($pedido->status == 'em_acertos'): ?>
                        <button onclick="reenviarTrabalho(<?php echo $pedido->id; ?>)"
                                class="button button-success"
                                style="background: #ff9800; border-color: #ff9800;">
                            🔄 Reenviar Trabalho Corrigido
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Modal para entrega - INCLUÍDO DIRETAMENTE -->
    <div id="modal-entrega" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10001;">
        <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%; max-height: 80%; overflow-y: auto;">
            <h4>📤 Deliver Work</h4>
            <form id="form-entrega" enctype="multipart/form-data">
                <input type="hidden" id="pedido-id-entrega">

                <div class="campo" style="margin-bottom: 20px;">
                    <label>Final Files (up to 3 files): *</label>

                    <div id="uploads-finais-container">
                        <div class="upload-final-item" style="margin-bottom: 10px;">
                            <input type="file" name="arquivos_finais[]" accept=".emb,.dst,.exp,.pes,.vp3,.jef,.pdf" required style="width: 100%; margin-bottom: 5px;">
                            <small>File 1 - Required (.emb, .dst, .exp, etc.)</small>
                        </div>

                        <div class="upload-final-item" style="display: none; margin-bottom: 10px;">
                            <input type="file" name="arquivos_finais[]" accept=".emb,.dst,.exp,.pes,.vp3,.jef,.pdf" style="width: 80%; margin-bottom: 5px;">
                            <button type="button" onclick="removerUploadFinal(this)" class="button-small" style="margin-left: 10px;">✕</button>
                            <br><small>File 2 - Optional</small>
                        </div>

                        <div class="upload-final-item" style="display: none; margin-bottom: 10px;">
                            <input type="file" name="arquivos_finais[]" accept=".emb,.dst,.exp,.pes,.vp3,.jef,.pdf" style="width: 80%; margin-bottom: 5px;">
                            <button type="button" onclick="removerUploadFinal(this)" class="button-small" style="margin-left: 10px;">✕</button>
                            <br><small>File 3 - Optional</small>
                        </div>
                    </div>

                    <button type="button" onclick="adicionarUploadFinal()" class="button button-small" id="btn-add-upload-final">➕ Add File</button>
                </div>

                <div class="campo" style="margin-bottom: 20px;">
                    <label for="numero-pontos">Stitch Count: *</label>
                    <input type="number" id="numero-pontos" min="0" placeholder="Ex: 8500" required style="width: 100%; padding: 8px;">
                    <small style="color: #666;">Number of stitches in the design (required for pricing)</small>
                </div>

                <div class="campo" style="margin-bottom: 20px;">
                    <label for="preco-programador">My Price: *</label>
                    <input type="number" id="preco-programador" step="0.01" placeholder="0.00" required style="width: 100%; padding: 8px;">
                </div>

                <div class="campo" style="margin-bottom: 20px;">
                    <label for="obs-programador">Notes:</label>
                    <textarea id="obs-programador" placeholder="Notes about the work done..." style="width: 100%; padding: 8px; rows: 4;"></textarea>
                </div>

                <div style="margin-top: 20px; text-align: center;">
                    <button type="submit" class="button button-primary">✅ Finish and Deliver</button>
                    <button type="button" onclick="fecharModal()" class="button" style="margin-left: 10px;">✕ Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
}
?>
