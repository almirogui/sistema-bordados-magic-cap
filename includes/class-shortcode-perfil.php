<?php
/**
 * Shortcode: [bordados_perfil_cliente]
 * Page where the client edits their own profile
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Shortcode_Perfil {
    
    /**
     * Register shortcode
     */
    public static function init() {
        add_shortcode('bordados_perfil_cliente', array(__CLASS__, 'render'));
    }
    
    /**
     * Render profile page
     */
    public static function render() {
        // Check if logged in
        if (!is_user_logged_in()) {
            return '<p>You need to be logged in to access this page.</p>';
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Check if is client
        if (!in_array('cliente_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
            return '<p>Access denied. This page is for clients only.</p>';
        }
        
        // Get current data
        $dados = self::buscar_dados_cliente($user_id);
        
        ob_start();
        ?>
        
        <div class="bordados-perfil-container">
            <h1>👤 My Profile</h1>
            
            <form id="form-perfil-cliente" class="bordados-form-perfil" method="post">
                
                <?php wp_nonce_field('salvar_perfil_cliente', 'perfil_nonce'); ?>
                
                <!-- PERSONAL INFORMATION -->
                <div class="perfil-secao">
                    <h2>📋 Personal Information</h2>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo esc_attr($dados['first_name']); ?>" required>
                        </div>
                        <div class="form-col">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo esc_attr($dados['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="titulo_cliente">Title (optional)</label>
                            <select id="titulo_cliente" name="titulo_cliente">
                                <option value="">-- Select --</option>
                                <option value="Mr." <?php selected($dados['titulo_cliente'], 'Mr.'); ?>>Mr.</option>
                                <option value="Mrs." <?php selected($dados['titulo_cliente'], 'Mrs.'); ?>>Mrs.</option>
                                <option value="Ms." <?php selected($dados['titulo_cliente'], 'Ms.'); ?>>Ms.</option>
                                <option value="Dr." <?php selected($dados['titulo_cliente'], 'Dr.'); ?>>Dr.</option>
                            </select>
                        </div>
                        <div class="form-col">
                            <label for="apelido_cliente">Nickname / Trade Name</label>
                            <input type="text" id="apelido_cliente" name="apelido_cliente" 
                                   value="<?php echo esc_attr($dados['apelido_cliente']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="user_email">Primary Email *</label>
                            <input type="email" id="user_email" name="user_email" 
                                   value="<?php echo esc_attr($user->user_email); ?>" required>
                        </div>
                        <div class="form-col">
                            <label for="email_secundario">Secondary Email</label>
                            <input type="email" id="email_secundario" name="email_secundario" 
                                   value="<?php echo esc_attr($dados['email_secundario']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="email_invoice">Invoice Email</label>
                            <input type="email" id="email_invoice" name="email_invoice" 
                                   value="<?php echo esc_attr($dados['email_invoice']); ?>">
                            <small>Specific email to receive invoices</small>
                        </div>
                        <div class="form-col">
                            <label for="telefone_whatsapp">Phone / WhatsApp</label>
                            <input type="tel" id="telefone_whatsapp" name="telefone_whatsapp" 
                                   value="<?php echo esc_attr($dados['telefone_whatsapp']); ?>"
                                   placeholder="+1 (555) 123-4567">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="cpf_cnpj">Tax ID / EIN (optional)</label>
                            <input type="text" id="cpf_cnpj" name="cpf_cnpj" 
                                   value="<?php echo esc_attr($dados['cpf_cnpj']); ?>"
                                   placeholder="XX-XXXXXXX">
                        </div>
                        <div class="form-col">
                            <label for="data_nascimento">Date of Birth</label>
                            <input type="date" id="data_nascimento" name="data_nascimento" 
                                   value="<?php echo esc_attr($dados['data_nascimento']); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- ADDRESS -->
                <div class="perfil-secao">
                    <h2>🌍 Address</h2>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="pais">Country *</label>
                            <select id="pais" name="pais">
                                <?php echo self::opcoes_paises($dados['pais']); ?>
                            </select>
                        </div>
                        <div class="form-col">
                            <label for="cep">Zip Code / Postal Code</label>
                            <input type="text" id="cep" name="cep" 
                                   value="<?php echo esc_attr($dados['cep']); ?>"
                                   placeholder="12345 or 12345-6789">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col form-col-3">
                            <label for="endereco_rua">Street Address</label>
                            <input type="text" id="endereco_rua" name="endereco_rua" 
                                   value="<?php echo esc_attr($dados['endereco_rua']); ?>">
                        </div>
                        <div class="form-col form-col-1">
                            <label for="endereco_numero">Number</label>
                            <input type="text" id="endereco_numero" name="endereco_numero" 
                                   value="<?php echo esc_attr($dados['endereco_numero']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="endereco_complemento">Apt / Suite / Unit</label>
                            <input type="text" id="endereco_complemento" name="endereco_complemento" 
                                   value="<?php echo esc_attr($dados['endereco_complemento']); ?>"
                                   placeholder="Apt 4B, Suite 100...">
                        </div>
                        <div class="form-col">
                            <label for="endereco_bairro">Neighborhood / District</label>
                            <input type="text" id="endereco_bairro" name="endereco_bairro" 
                                   value="<?php echo esc_attr($dados['endereco_bairro']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="endereco_cidade">City</label>
                            <input type="text" id="endereco_cidade" name="endereco_cidade" 
                                   value="<?php echo esc_attr($dados['endereco_cidade']); ?>">
                        </div>
                        <div class="form-col">
                            <label for="endereco_estado">State / Province</label>
                            <input type="text" id="endereco_estado" name="endereco_estado" 
                                   value="<?php echo esc_attr($dados['endereco_estado']); ?>"
                                   placeholder="CA, TX, NY...">
                        </div>
                    </div>
                </div>
                
                <!-- COMPANY INFORMATION -->
                <div class="perfil-secao">
                    <h2>🏢 Company Information (Optional)</h2>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="razao_social">Legal Business Name</label>
                            <input type="text" id="razao_social" name="razao_social" 
                                   value="<?php echo esc_attr($dados['razao_social']); ?>">
                        </div>
                        <div class="form-col">
                            <label for="nome_fantasia">Trade Name / DBA</label>
                            <input type="text" id="nome_fantasia" name="nome_fantasia" 
                                   value="<?php echo esc_attr($dados['nome_fantasia']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="cnpj_empresa">Company Tax ID / EIN</label>
                            <input type="text" id="cnpj_empresa" name="cnpj_empresa" 
                                   value="<?php echo esc_attr($dados['cnpj_empresa']); ?>"
                                   placeholder="XX-XXXXXXX">
                        </div>
                    </div>
                </div>
                
                <!-- EMBROIDERY PREFERENCES -->
                <div class="perfil-secao">
                    <h2>⚙️ Embroidery Preferences</h2>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="formato_arquivo_preferido">Preferred File Format</label>
                            <select id="formato_arquivo_preferido" name="formato_arquivo_preferido">
                                <option value="">-- Select --</option>
                                <option value="EMB" <?php selected($dados['formato_arquivo_preferido'], 'EMB'); ?>>EMB</option>
                                <option value="DST" <?php selected($dados['formato_arquivo_preferido'], 'DST'); ?>>DST</option>
                                <option value="PES" <?php selected($dados['formato_arquivo_preferido'], 'PES'); ?>>PES</option>
                                <option value="JEF" <?php selected($dados['formato_arquivo_preferido'], 'JEF'); ?>>JEF</option>
                                <option value="EXP" <?php selected($dados['formato_arquivo_preferido'], 'EXP'); ?>>EXP</option>
                                <option value="VP3" <?php selected($dados['formato_arquivo_preferido'], 'VP3'); ?>>VP3</option>
                                <option value="SEW" <?php selected($dados['formato_arquivo_preferido'], 'SEW'); ?>>SEW</option>
                                <option value="CSD" <?php selected($dados['formato_arquivo_preferido'], 'CSD'); ?>>CSD</option>
                                <option value="XXX" <?php selected($dados['formato_arquivo_preferido'], 'XXX'); ?>>XXX</option>
                            </select>
                        </div>
                        <div class="form-col">
                            <label for="unidade_medida_preferida">Unit of Measurement</label>
                            <select id="unidade_medida_preferida" name="unidade_medida_preferida">
                                <option value="in" <?php selected($dados['unidade_medida_preferida'], 'in'); ?>>Inches (in)</option>
                                <option value="cm" <?php selected($dados['unidade_medida_preferida'], 'cm'); ?>>Centimeters (cm)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="maquina_bordar">Embroidery Machine</label>
                            <input type="text" id="maquina_bordar" name="maquina_bordar" 
                                   value="<?php echo esc_attr($dados['maquina_bordar']); ?>"
                                   placeholder="e.g., Brother PR1050X, Tajima TEMX-C1201">
                            <small>Brand and model of your embroidery machine</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="obs_para_programador">Notes for Digitizer</label>
                            <textarea id="obs_para_programador" name="obs_para_programador" 
                                      rows="4" placeholder="Useful information about your embroidery preferences..."><?php echo esc_textarea($dados['obs_para_programador']); ?></textarea>
                            <small>e.g., "I prefer medium density", "Avoid long stitches", etc.</small>
                        </div>
                    </div>
                </div>
                
                <!-- PAYMENT INFORMATION -->
                <div class="perfil-secao">
                    <h2>💳 Payment Information</h2>
                    
                    <?php
                    // Buscar dados de pagamento
                    $metodo_pagamento = get_user_meta($user_id, 'metodo_pagamento', true);
                    $card_brand = get_user_meta($user_id, 'card_brand', true);
                    $paypal_email = get_user_meta($user_id, 'paypal_email', true);
                    $bank_details = get_user_meta($user_id, 'bank_details', true);
                    
                    // Dados do cartão (criptografados) - mostrar mascarados para cliente
                    $card_number_encrypted = get_user_meta($user_id, 'card_number', true);
                    $card_expiry_encrypted = get_user_meta($user_id, 'card_expiry', true);
                    $card_holder_encrypted = get_user_meta($user_id, 'card_holder', true);
                    
                    // Descriptografar para exibição mascarada
                    $card_number = '';
                    $card_expiry = '';
                    $card_holder = '';
                    
                    if (class_exists('Bordados_Perfil_Cliente')) {
                        $card_number = Bordados_Perfil_Cliente::decrypt_data($card_number_encrypted);
                        $card_expiry = Bordados_Perfil_Cliente::decrypt_data($card_expiry_encrypted);
                        $card_holder = Bordados_Perfil_Cliente::decrypt_data($card_holder_encrypted);
                    }
                    
                    // Mascarar número do cartão para cliente
                    $card_number_masked = '';
                    $has_card = false;
                    if (!empty($card_number)) {
                        $has_card = true;
                        $clean = preg_replace('/[\s\-]/', '', $card_number);
                        $last4 = substr($clean, -4);
                        $card_number_masked = '•••• •••• •••• ' . $last4;
                    }
                    ?>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="metodo_pagamento">Preferred Payment Method</label>
                            <select id="metodo_pagamento" name="metodo_pagamento">
                                <option value="">-- Select --</option>
                                <option value="credit_card" <?php selected($metodo_pagamento, 'credit_card'); ?>>💳 Credit Card</option>
                                <option value="paypal" <?php selected($metodo_pagamento, 'paypal'); ?>>🅿️ PayPal</option>
                                <option value="bank_transfer" <?php selected($metodo_pagamento, 'bank_transfer'); ?>>🏦 Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Credit Card Section -->
                    <div class="payment-card-section" style="<?php echo ($metodo_pagamento === 'credit_card') ? '' : 'display:none;'; ?>">
                        <div id="card-display-area">
                            <?php if ($has_card): ?>
                            <!-- Card on file -->
                            <div class="form-row">
                                <div class="form-col">
                                    <label>Card on File</label>
                                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; color: white; max-width: 350px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                                        <p style="margin: 0 0 15px 0; font-size: 20px; letter-spacing: 3px; font-family: 'Courier New', monospace;">
                                            <?php echo esc_html($card_number_masked); ?>
                                        </p>
                                        <div style="display: flex; justify-content: space-between; font-size: 12px; text-transform: uppercase;">
                                            <div>
                                                <span style="opacity: 0.7;">Card Holder</span><br>
                                                <strong><?php echo esc_html($card_holder ?: 'N/A'); ?></strong>
                                            </div>
                                            <div style="text-align: right;">
                                                <span style="opacity: 0.7;">Expires</span><br>
                                                <strong><?php echo esc_html($card_expiry ?: 'N/A'); ?></strong>
                                            </div>
                                        </div>
                                        <?php if (!empty($card_brand)): ?>
                                        <div style="text-align: right; margin-top: 10px;">
                                            <span style="background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 4px; font-size: 11px; text-transform: uppercase;">
                                                <?php echo esc_html($card_brand); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" onclick="openCardModal()" class="btn-update-card" style="margin-top: 10px; background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer;">
                                        🔄 Update Card
                                    </button>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- No card on file -->
                            <div class="form-row">
                                <div class="form-col">
                                    <div style="background: #f8f9fa; border: 2px dashed #dee2e6; padding: 30px; border-radius: 12px; text-align: center;">
                                        <p style="margin: 0 0 15px 0; color: #6c757d; font-size: 16px;">
                                            💳 No credit card on file
                                        </p>
                                        <button type="button" onclick="openCardModal()" class="btn-add-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                            ➕ Add Credit Card
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- PayPal Fields -->
                    <div class="payment-paypal-section" style="<?php echo ($metodo_pagamento === 'paypal') ? '' : 'display:none;'; ?>">
                        <div class="form-row">
                            <div class="form-col">
                                <label for="paypal_email">PayPal Email</label>
                                <input type="email" id="paypal_email" name="paypal_email" 
                                       value="<?php echo esc_attr($paypal_email); ?>"
                                       placeholder="your@email.com">
                                <small>Email address associated with your PayPal account</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bank Transfer Fields -->
                    <div class="payment-bank-section" style="<?php echo ($metodo_pagamento === 'bank_transfer') ? '' : 'display:none;'; ?>">
                        <div class="form-row">
                            <div class="form-col">
                                <label for="bank_details">Bank Account Details</label>
                                <textarea id="bank_details" name="bank_details" rows="4"
                                          placeholder="Bank Name&#10;Account Number&#10;Routing Number&#10;Account Holder Name"><?php echo esc_textarea($bank_details); ?></textarea>
                                <small>Your bank account information for wire transfers</small>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <!-- BUTTONS -->
                <div class="perfil-acoes">
                    <button type="submit" class="btn-salvar-perfil">💾 Save Profile</button>
                    <a href="<?php echo site_url('/meus-pedidos/'); ?>" class="btn-voltar">← Back to My Orders</a>
                </div>
                
            </form>
            
            <!-- MODAL: Add/Update Credit Card (FORA do form principal) -->
            <div id="modal-credit-card" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
                <div style="background: white; padding: 30px; border-radius: 12px; max-width: 450px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e9ecef;">
                        <h3 style="margin: 0; color: #2c3e50; font-size: 18px;">💳 Credit Card Information</h3>
                        <button type="button" onclick="closeCardModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6c757d; padding: 0; line-height: 1;">&times;</button>
                    </div>
                    
                    <div style="background: #fff3cd; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                        <small style="color: #856404;">🔒 Your card information is encrypted and stored securely.</small>
                    </div>
                    
                    <form id="form-credit-card">
                        <?php wp_nonce_field('salvar_cartao_cliente', 'card_nonce'); ?>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Card Brand *</label>
                            <select id="modal_card_brand" name="card_brand" required style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px;">
                                <option value="">-- Select --</option>
                                <option value="visa">Visa</option>
                                <option value="mastercard">Mastercard</option>
                                <option value="amex">American Express</option>
                                <option value="discover">Discover</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Name on Card *</label>
                            <input type="text" id="modal_card_holder" name="card_holder" required 
                                   placeholder="JOHN DOE" 
                                   style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; text-transform: uppercase;">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Card Number *</label>
                            <input type="text" id="modal_card_number" name="card_number" required 
                                   placeholder="1234 5678 9012 3456" maxlength="19"
                                   style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 16px; letter-spacing: 2px;">
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">Expiration *</label>
                                <input type="text" id="modal_card_expiry" name="card_expiry" required 
                                       placeholder="MM/YY" maxlength="5"
                                       style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; text-align: center;">
                            </div>
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">CVV *</label>
                                <input type="text" id="modal_card_cvv" name="card_cvv" required 
                                       placeholder="123" maxlength="4"
                                       style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; text-align: center;">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; justify-content: flex-end; padding-top: 15px; border-top: 1px solid #e9ecef;">
                            <button type="button" onclick="closeCardModal()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">
                                Cancel
                            </button>
                            <button type="submit" id="btn-save-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                Save Card
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="mensagem-perfil" style="display:none;"></div>
        </div>
        
        <script>
        (function($) {
            'use strict';
            
            // Wait for DOM ready
            $(function() {
                console.log('=== PAYMENT SYSTEM INIT ===');
                
                var $metodoPagamento = $('#metodo_pagamento');
                var $cardSection = $('.payment-card-section');
                var $paypalSection = $('.payment-paypal-section');
                var $bankSection = $('.payment-bank-section');
                
                console.log('Select found:', $metodoPagamento.length > 0);
                console.log('Card section found:', $cardSection.length > 0);
                console.log('PayPal section found:', $paypalSection.length > 0);
                console.log('Bank section found:', $bankSection.length > 0);
                
                // Function to show correct payment section
                function showPaymentSection(metodo) {
                    console.log('Showing section for:', metodo);
                    
                    // Hide all sections
                    $cardSection.hide();
                    $paypalSection.hide();
                    $bankSection.hide();
                    
                    // Show selected section
                    if (metodo === 'credit_card') {
                        $cardSection.fadeIn(200);
                    } else if (metodo === 'paypal') {
                        $paypalSection.fadeIn(200);
                    } else if (metodo === 'bank_transfer') {
                        $bankSection.fadeIn(200);
                    }
                }
                
                // Bind change event
                $metodoPagamento.on('change', function() {
                    var selected = $(this).val();
                    console.log('Method changed to:', selected);
                    showPaymentSection(selected);
                });
                
                // Trigger on page load
                var initialMethod = $metodoPagamento.val();
                console.log('Initial method:', initialMethod);
                if (initialMethod) {
                    showPaymentSection(initialMethod);
                }
                
                // Format card number while typing
                $(document).on('input', '#modal_card_number', function() {
                    var val = $(this).val().replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                    var parts = [];
                    var maxLen = Math.min(val.length, 16);
                    for (var i = 0; i < maxLen; i += 4) {
                        parts.push(val.substring(i, i + 4));
                    }
                    $(this).val(parts.join(' '));
                });
                
                // Format expiry date
                $(document).on('input', '#modal_card_expiry', function() {
                    var val = $(this).val().replace(/\D/g, '');
                    if (val.length >= 2) {
                        val = val.substring(0, 2) + '/' + val.substring(2, 4);
                    }
                    $(this).val(val);
                });
                
                // Only numbers for CVV
                $(document).on('input', '#modal_card_cvv', function() {
                    $(this).val($(this).val().replace(/\D/g, ''));
                });
                
                // Save card form
                $(document).on('submit', '#form-credit-card', function(e) {
                    e.preventDefault();
                    console.log('Saving card...');
                    
                    var $btn = $('#btn-save-card');
                    $btn.prop('disabled', true).html('Saving...');
                    
                    $.ajax({
                        url: bordados_ajax.ajax_url,
                        type: 'POST',
                        data: $(this).serialize() + '&action=salvar_cartao_cliente',
                        success: function(response) {
                            console.log('Card save response:', response);
                            if (response.success) {
                                $('#card-display-area').html(response.data.card_html);
                                closeCardModal();
                                alert('Card saved successfully!');
                            } else {
                                alert('Error: ' + response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Card save error:', error);
                            alert('Connection error. Please try again.');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).html('Save Card');
                        }
                    });
                });
                
                // Profile form submit
                $('#form-perfil-cliente').on('submit', function(e) {
                    e.preventDefault();
                    console.log('Saving profile...');
                    
                    var $btn = $('.btn-salvar-perfil');
                    $btn.prop('disabled', true).text('Saving...');
                    
                    $.ajax({
                        url: bordados_ajax.ajax_url,
                        type: 'POST',
                        data: $(this).serialize() + '&action=salvar_perfil_cliente',
                        success: function(response) {
                            console.log('Profile save response:', response);
                            if (response.success) {
                                $('#mensagem-perfil')
                                    .html('<div class="notice notice-success" style="background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; margin: 15px 0;"><p style="margin:0; color: #155724;">' + response.data + '</p></div>')
                                    .show();
                                    
                                // Scroll to message
                                $('html, body').animate({
                                    scrollTop: $('#mensagem-perfil').offset().top - 100
                                }, 500);
                                
                                setTimeout(function() {
                                    $('#mensagem-perfil').fadeOut();
                                }, 3000);
                            } else {
                                alert('Error: ' + response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Profile save error:', error);
                            alert('Error saving profile. Please try again.');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Save Profile');
                        }
                    });
                });
            });
            
            // Modal functions - Global scope
            window.openCardModal = function() {
                console.log('Opening card modal');
                $('#modal-credit-card').css('display', 'flex');
            };
            
            window.closeCardModal = function() {
                console.log('Closing card modal');
                $('#modal-credit-card').hide();
                var cardForm = document.getElementById('form-credit-card');
                if (cardForm) {
                    cardForm.reset();
                }
            };
            
            // Close modal on outside click
            $(document).on('click', '#modal-credit-card', function(e) {
                if (e.target === this) {
                    closeCardModal();
                }
            });
            
        })(jQuery);
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get client data
     */
    private static function buscar_dados_cliente($user_id) {
        $user = get_userdata($user_id);
        
        return array(
            'first_name' => get_user_meta($user_id, 'first_name', true),
            'last_name' => get_user_meta($user_id, 'last_name', true),
            'titulo_cliente' => get_user_meta($user_id, 'titulo_cliente', true),
            'apelido_cliente' => get_user_meta($user_id, 'apelido_cliente', true),
            'email_secundario' => get_user_meta($user_id, 'email_secundario', true),
            'email_invoice' => get_user_meta($user_id, 'email_invoice', true),
            'telefone_whatsapp' => get_user_meta($user_id, 'telefone_whatsapp', true),
            'cpf_cnpj' => get_user_meta($user_id, 'cpf_cnpj', true),
            'data_nascimento' => get_user_meta($user_id, 'data_nascimento', true),
            'pais' => get_user_meta($user_id, 'pais', true) ?: 'US',
            'cep' => get_user_meta($user_id, 'cep', true),
            'endereco_rua' => get_user_meta($user_id, 'endereco_rua', true),
            'endereco_numero' => get_user_meta($user_id, 'endereco_numero', true),
            'endereco_complemento' => get_user_meta($user_id, 'endereco_complemento', true),
            'endereco_bairro' => get_user_meta($user_id, 'endereco_bairro', true),
            'endereco_cidade' => get_user_meta($user_id, 'endereco_cidade', true),
            'endereco_estado' => get_user_meta($user_id, 'endereco_estado', true),
            'razao_social' => get_user_meta($user_id, 'razao_social', true),
            'nome_fantasia' => get_user_meta($user_id, 'nome_fantasia', true),
            'cnpj_empresa' => get_user_meta($user_id, 'cnpj_empresa', true),
            'formato_arquivo_preferido' => get_user_meta($user_id, 'formato_arquivo_preferido', true),
            'unidade_medida_preferida' => get_user_meta($user_id, 'unidade_medida_preferida', true) ?: 'in',
            'maquina_bordar' => get_user_meta($user_id, 'maquina_bordar', true),
            'obs_para_programador' => get_user_meta($user_id, 'obs_para_programador', true),
        );
    }
    
    /**
     * Generate country options
     */
    private static function opcoes_paises($selecionado = 'US') {
        $paises = array(
            'US' => 'United States',
            'CA' => 'Canada',
            'MX' => 'Mexico',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'BR' => 'Brazil',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'PE' => 'Peru',
            'UY' => 'Uruguay',
            'PY' => 'Paraguay',
            'BO' => 'Bolivia',
            'VE' => 'Venezuela',
            'EC' => 'Ecuador',
            'PT' => 'Portugal',
            'ES' => 'Spain',
            'FR' => 'France',
            'DE' => 'Germany',
            'IT' => 'Italy'
        );
        
        $html = '';
        foreach ($paises as $codigo => $nome) {
            $selected = ($codigo === $selecionado) ? 'selected' : '';
            $html .= '<option value="' . esc_attr($codigo) . '" ' . $selected . '>' . esc_html($nome) . '</option>';
        }
        
        return $html;
    }
}

// Initialize
Bordados_Shortcode_Perfil::init();
