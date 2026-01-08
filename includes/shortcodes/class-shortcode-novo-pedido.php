<?php
/**
 * Shortcode: Formulário Novo Pedido - [bordados_novo_pedido]
 * 
 * ATUALIZADO: Adicionado suporte para:
 * - Tipo de produto (bordado/vetor)
 * - Solicitação de orçamento
 * - Preferência de unidade de medida do perfil do cliente
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Shortcode_Novo_Pedido {
    
    /**
     * Renderizar formulário novo pedido
     */
    public static function render($atts) {
        if (!is_user_logged_in()) {
            return '<p>You need to be logged in to place an order. <a href="' . wp_login_url() . '">Login</a></p>';
        }
        
        $usuario = wp_get_current_user();
        
        // ============================================================
        // NOVO: Buscar preferência de unidade do perfil do cliente
        // ============================================================
        $user_id = get_current_user_id();
        $unidade_preferida = get_user_meta($user_id, 'unidade_medida_preferida', true);
        
        // Se não tem preferência, usar padrão baseado no idioma do site
        if (empty($unidade_preferida)) {
            $locale = get_locale();
            // Português (pt_BR, pt_PT) -> cm (centímetros)
            // Inglês e outros -> in (polegadas)
            if (strpos($locale, 'pt_') === 0 || strpos($locale, 'es_') === 0) {
                $unidade_preferida = 'cm';
            } else {
                $unidade_preferida = 'in';
            }
        }
        // ============================================================
        
        ob_start();
        ?>
        <div class="bordados-novo-pedido">
            <h3>📂 New Order</h3>
            
            <div id="mensagem-resposta"></div>
            
            <form id="form-novo-pedido" enctype="multipart/form-data">
                <?php wp_nonce_field('bordados_nonce', 'nonce'); ?>
            
            <!-- NOVO: Tipo de Solicitação -->
            <div class="secao-form" style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h4>📋 Request Type</h4>
                
                <div class="form-row" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div class="campo" style="flex: 1; min-width: 200px;">
                        <label for="tipo_produto">Service Type: *</label>
                        <select id="tipo_produto" name="tipo_produto" required style="width: 100%; padding: 10px;">
                            <option value="bordado">🧵 Embroidery Digitizing</option>
                            <option value="vetor">✏️ Vector Art</option>
                        </select>
                    </div>
                    
                    <div class="campo" style="flex: 1; min-width: 200px;">
                        <label for="is_orcamento">Order Type: *</label>
                        <select id="is_orcamento" name="is_orcamento" required style="width: 100%; padding: 10px;">
                            <option value="0">📤 Place Order Now</option>
                            <option value="1">💰 Request Quote First</option>
                        </select>
                    </div>
                </div>
                
                <div id="aviso-orcamento" style="display: none; margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px; border-left: 4px solid #ffc107;">
                    💡 <strong>Quote Mode:</strong> You will receive a quote by email. If you approve, the order will be processed automatically.
                </div>
            </div>
            
            <!-- Informações do Cliente -->
            <div class="secao-form">
                <h4>👤 Customer Info</h4>
                <div class="form-row">
                    <div class="campo">
                        <label>Name:</label>
                        <input type="text" value="<?php echo esc_attr($usuario->display_name); ?>" readonly>
                    </div>
                    <div class="campo">
                        <label>Email:</label>
                        <input type="email" value="<?php echo esc_attr($usuario->user_email); ?>" readonly>
                    </div>
                </div>
            </div>
            
            <!-- Detalhes do Trabalho -->
            <div class="secao-form">
                <h4>🎨 Work Details</h4>
                
                <div class="campo">
                    <label for="nome_bordado">Design Name/Title: *</label>
                    <input type="text" id="nome_bordado" name="nome_bordado" required 
                           placeholder="Ex: Company Logo, Custom Name, etc.">
                </div>
                
                <div class="form-row">
                    <div class="campo">
                        <label for="prazo_entrega">Turnaround: *</label>
                        <select id="prazo_entrega" name="prazo_entrega" required>
                            <option value="Normal">Normal</option>
                            <option value="URGENTE - RUSH">RUSH - Urgent</option>
                        </select>
                    </div>
                    
                    <div class="campo">
                        <label for="cores">Number of Colors:</label>
                        <select id="cores" name="cores">
                            <option value="">Not sure / Let digitizer decide</option>
                            <option value="1">1 color</option>
                            <option value="2">2 colors</option>
                            <option value="3">3 colors</option>
                            <option value="4">4 colors</option>
                            <option value="5+">5 or more colors</option>
                        </select>
                    </div>
                </div>
                
                <!-- Dimensões -->
                <div class="secao-form">
                    <h4>📐 Dimensions</h4>
                    <p><small>Please provide at least one dimension (width OR height).</small></p>
                    
                    <div class="form-row">
                        <div class="campo">
                            <label for="largura">Width:</label>
                            <input type="number" id="largura" name="largura" step="0.1" min="0" 
                                   placeholder="Ex: 4.5">
                        </div>
                        
                        <div class="campo">
                            <label for="altura">Height:</label>
                            <input type="number" id="altura" name="altura" step="0.1" min="0" 
                                   placeholder="Ex: 3.2">
                        </div>
                        
                        <div class="campo">
                            <label for="unidade_medida">Unit: *</label>
                            <select id="unidade_medida" name="unidade_medida" required>
                                <option value="in" <?php selected($unidade_preferida, 'in'); ?>>in (inches)</option>
                                <option value="cm" <?php selected($unidade_preferida, 'cm'); ?>>cm (centimeters)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Local do Bordado -->
                <div class="campo">
                    <label for="local_bordado">Placement: *</label>
                    <select id="local_bordado" name="local_bordado" required>
                        <option value="">Select placement...</option>
                        <option value="cap front">Cap Front</option>
                        <option value="cap side">Cap Side</option>
                        <option value="cap back">Cap Back</option>
                        <option value="left chest">Left Chest</option>
                        <option value="full back">Full Back</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <!-- Tipo de Tecido -->
                <div class="campo">
                    <label for="tipo_tecido">Fabric Type:</label>
                    <select id="tipo_tecido" name="tipo_tecido">
                        <option value="">Not sure / Optional</option>
                        <option value="Cotton Twill">Cotton Twill</option>
                        <option value="Canvas">Canvas</option>
                        <option value="Denim">Denim</option>
                        <option value="Leather">Leather</option>
                        <option value="Microfiber">Microfiber</option>
                        <option value="Nylon">Nylon</option>
                        <option value="Jersey">Jersey</option>
                        <option value="Pique Polo">Pique / Polo</option>
                        <option value="Fleece">Fleece</option>
                        <option value="Terry Towel">Terry / Towel</option>
                        <option value="Knit">Knit</option>
                        <option value="Velvet">Velvet</option>
                        <option value="Suede">Suede</option>
                        <option value="Sweatshirt">Sweatshirt</option>
                        <option value="T-shirt">T-shirt</option>
                        <option value="Lycra">Lycra</option>
                        <option value="Poplin">Poplin</option>
                        <option value="Vinyl">Vinyl</option>
                        <option value="Dry-Fit">Dry-Fit</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <!-- Upload de Arquivos -->
            <?php echo self::secao_upload_multiplo(); ?>

            <!-- Botões -->
            <div class="form-acoes" style="margin-top: 20px;">
                <button type="submit" class="button button-primary button-large" id="btn-enviar-pedido">
                    📤 Submit Order
                </button>
                <a href="<?php echo esc_url(site_url('/meus-pedidos/')); ?>" class="button" style="margin-left: 10px;">
                    ← Back to My Orders
                </a>
            </div>
            
            </form>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar/ocultar aviso de orçamento
            var isOrcamento = document.getElementById('is_orcamento');
            var aviso = document.getElementById('aviso-orcamento');
            var btnEnviar = document.getElementById('btn-enviar-pedido');
            
            isOrcamento.addEventListener('change', function() {
                if (this.value === '1') {
                    aviso.style.display = 'block';
                    btnEnviar.innerHTML = '💰 Request Quote';
                    btnEnviar.style.background = '#ffc107';
                    btnEnviar.style.borderColor = '#ffc107';
                    btnEnviar.style.color = '#000';
                } else {
                    aviso.style.display = 'none';
                    btnEnviar.innerHTML = '📤 Submit Order';
                    btnEnviar.style.background = '';
                    btnEnviar.style.borderColor = '';
                    btnEnviar.style.color = '';
                }
            });
            
            // Validação de dimensões
            var form = document.getElementById('form-novo-pedido');
            var largura = document.getElementById('largura');
            var altura = document.getElementById('altura');
            
            form.addEventListener('submit', function(e) {
                var larguraVal = parseFloat(largura.value) || 0;
                var alturaVal = parseFloat(altura.value) || 0;
                
                if (larguraVal <= 0 && alturaVal <= 0) {
                    e.preventDefault();
                    alert('⚠️ Please provide at least one dimension (width OR height).');
                    largura.focus();
                    return false;
                }
            });
            
            // Visual feedback
            [largura, altura].forEach(function(campo) {
                campo.addEventListener('input', function() {
                    if (this.value) {
                        this.style.borderColor = '#28a745';
                        this.style.backgroundColor = '#f8fff8';
                    } else {
                        this.style.borderColor = '';
                        this.style.backgroundColor = '';
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Seção de upload múltiplo
     */
    private static function secao_upload_multiplo() {
        return '
        <div class="secao-form">
            <h4>📎 Reference Files</h4>
            
            <div class="campo">
                <label for="arquivos_referencia">Reference Images/Logos (up to 3 files): *</label>
                
                <div id="uploads-container">
                    <div class="upload-item" style="margin-bottom: 10px;">
                        <input type="file" name="arquivos_referencia[]" 
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.ai,.eps,.svg" required style="width: 100%;">
                        <small>File 1 - Required</small>
                    </div>
                    
                    <div class="upload-item" style="display: none; margin-bottom: 10px;">
                        <input type="file" name="arquivos_referencia[]" 
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.ai,.eps,.svg" style="width: 80%;">
                        <button type="button" onclick="removerUpload(this)" class="button-small" style="margin-left: 10px;">✕ Remove</button>
                        <br><small>File 2 - Optional</small>
                    </div>
                    
                    <div class="upload-item" style="display: none; margin-bottom: 10px;">
                        <input type="file" name="arquivos_referencia[]" 
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.ai,.eps,.svg" style="width: 80%;">
                        <button type="button" onclick="removerUpload(this)" class="button-small" style="margin-left: 10px;">✕ Remove</button>
                        <br><small>File 3 - Optional</small>
                    </div>
                </div>
                
                <button type="button" onclick="adicionarUpload()" class="button button-small" id="btn-add-upload">➕ Add Another File</button>
                
                <small style="display: block; margin-top: 10px;">Accepted formats: JPG, PNG, GIF, PDF, AI, EPS, SVG (max 10MB each)</small>
            </div>
            
            <div class="campo">
                <label for="observacoes">Special Instructions/Notes:</label>
                <textarea id="observacoes" name="observacoes" rows="5" 
                          placeholder="Describe any important details: specific colors, positioning, style, etc."></textarea>
            </div>
        </div>';
    }
}
