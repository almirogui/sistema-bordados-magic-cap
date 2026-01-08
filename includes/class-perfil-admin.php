<?php
/**
 * Extensão da classe Bordados_Helpers
 * Campos Administrativos e de Perfil do Cliente
 * 
 * Este arquivo adiciona os novos campos ao sistema
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Perfil_Cliente {
    
    /**
     * Inicializar hooks
     */
    public static function init() {
        // Mostrar campos no perfil do usuário (admin)
        add_action('show_user_profile', array(__CLASS__, 'mostrar_campos_admin'));
        add_action('edit_user_profile', array(__CLASS__, 'mostrar_campos_admin'));
        
        // Mostrar campos de pagamento (admin e cliente)
        add_action('show_user_profile', array(__CLASS__, 'mostrar_campos_pagamento'), 20);
        add_action('edit_user_profile', array(__CLASS__, 'mostrar_campos_pagamento'), 20);
        
        // Salvar campos do perfil
        add_action('personal_options_update', array(__CLASS__, 'salvar_campos_admin'));
        add_action('edit_user_profile_update', array(__CLASS__, 'salvar_campos_admin'));
        
        // Salvar campos de pagamento
        add_action('personal_options_update', array(__CLASS__, 'salvar_campos_pagamento'));
        add_action('edit_user_profile_update', array(__CLASS__, 'salvar_campos_pagamento'));
        
        // Verificar bloqueio no login
        add_filter('wp_authenticate_user', array(__CLASS__, 'verificar_bloqueio_login'), 10, 2);
    }
    
    /**
     * ========================================
     * FUNÇÕES DE CRIPTOGRAFIA
     * ========================================
     */
    
    /**
     * Obter chave de criptografia
     */
    private static function get_encryption_key() {
        if (defined('PUNCHER_CARD_KEY') && !empty(PUNCHER_CARD_KEY)) {
            return PUNCHER_CARD_KEY;
        }
        // Fallback para AUTH_KEY do WordPress se PUNCHER_CARD_KEY não estiver definida
        return defined('AUTH_KEY') ? AUTH_KEY : 'default-key-change-me';
    }
    
    /**
     * Criptografar dados
     */
    public static function encrypt_data($data) {
        if (empty($data)) return '';
        
        $key = self::get_encryption_key();
        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        
        // Retorna IV + dados criptografados (base64)
        return base64_encode($iv . '::' . $encrypted);
    }
    
    /**
     * Descriptografar dados
     */
    public static function decrypt_data($encrypted_data) {
        if (empty($encrypted_data)) return '';
        
        $key = self::get_encryption_key();
        $method = 'AES-256-CBC';
        
        $data = base64_decode($encrypted_data);
        $parts = explode('::', $data, 2);
        
        if (count($parts) !== 2) return '';
        
        $iv = $parts[0];
        $encrypted = $parts[1];
        
        return openssl_decrypt($encrypted, $method, $key, 0, $iv);
    }
    
    /**
     * Mascarar número do cartão (mostrar apenas últimos 4 dígitos)
     */
    public static function mask_card_number($number) {
        if (empty($number)) return '';
        
        // Remover espaços e traços
        $clean = preg_replace('/[\s\-]/', '', $number);
        
        if (strlen($clean) < 4) return '••••';
        
        $last4 = substr($clean, -4);
        return '•••• •••• •••• ' . $last4;
    }
    
    /**
     * Mascarar CVV
     */
    public static function mask_cvv($cvv) {
        if (empty($cvv)) return '';
        return '•••';
    }
    
    /**
     * ========================================
     * CAMPOS DE PAGAMENTO (admin e cliente)
     * ========================================
     */
    public static function mostrar_campos_pagamento($user) {
        // Só mostrar para clientes
        if (!in_array('cliente_bordados', (array) $user->roles)) return;
        
        $is_admin = current_user_can('manage_options');
        $is_own_profile = ($user->ID === get_current_user_id());
        
        // Se não é admin e não é o próprio perfil, não mostra
        if (!$is_admin && !$is_own_profile) return;
        
        // Buscar valores atuais
        $metodo_pagamento = get_user_meta($user->ID, 'metodo_pagamento', true);
        
        // Dados do cartão (criptografados no banco)
        $card_holder_encrypted = get_user_meta($user->ID, 'card_holder', true);
        $card_number_encrypted = get_user_meta($user->ID, 'card_number', true);
        $card_expiry_encrypted = get_user_meta($user->ID, 'card_expiry', true);
        $card_cvv_encrypted = get_user_meta($user->ID, 'card_cvv', true);
        $card_brand = get_user_meta($user->ID, 'card_brand', true);
        
        // Descriptografar para exibição
        $card_holder = self::decrypt_data($card_holder_encrypted);
        $card_number = self::decrypt_data($card_number_encrypted);
        $card_expiry = self::decrypt_data($card_expiry_encrypted);
        $card_cvv = self::decrypt_data($card_cvv_encrypted);
        
        // PayPal e Bank
        $paypal_email = get_user_meta($user->ID, 'paypal_email', true);
        $bank_details = get_user_meta($user->ID, 'bank_details', true);
        $billing_notes = get_user_meta($user->ID, 'billing_notes', true);
        
        // Para cliente, mascarar dados sensíveis
        if (!$is_admin) {
            $card_number_display = self::mask_card_number($card_number);
            $card_cvv_display = self::mask_cvv($card_cvv);
            $card_holder_display = $card_holder; // Nome pode mostrar
            $card_expiry_display = $card_expiry; // Validade pode mostrar
        } else {
            $card_number_display = $card_number;
            $card_cvv_display = $card_cvv;
            $card_holder_display = $card_holder;
            $card_expiry_display = $card_expiry;
        }
        
        ?>
        <h2>💳 Payment Information</h2>
        
        <?php if (!$is_admin && !defined('PUNCHER_CARD_KEY')): ?>
        <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
            ⚠️ <strong>Note:</strong> Payment system is being configured. Contact support if you need to update payment info.
        </div>
        <?php endif; ?>
        
        <table class="form-table" role="presentation">
            
            <!-- MÉTODO DE PAGAMENTO -->
            <tr>
                <th><label for="metodo_pagamento">Preferred Payment Method</label></th>
                <td>
                    <select name="metodo_pagamento" id="metodo_pagamento" class="regular-text" <?php echo (!$is_admin && !empty($metodo_pagamento)) ? '' : ''; ?>>
                        <option value="">-- Select --</option>
                        <option value="credit_card" <?php selected($metodo_pagamento, 'credit_card'); ?>>💳 Credit Card</option>
                        <option value="paypal" <?php selected($metodo_pagamento, 'paypal'); ?>>🅿️ PayPal</option>
                        <option value="bank_transfer" <?php selected($metodo_pagamento, 'bank_transfer'); ?>>🏦 Bank Transfer</option>
                    </select>
                </td>
            </tr>
            
            <!-- DADOS DO CARTÃO -->
            <tbody class="payment-card-fields" style="<?php echo ($metodo_pagamento === 'credit_card') ? '' : 'display:none;'; ?>">
                <tr>
                    <th><label for="card_brand">Card Brand</label></th>
                    <td>
                        <select name="card_brand" id="card_brand" class="regular-text">
                            <option value="">-- Select --</option>
                            <option value="visa" <?php selected($card_brand, 'visa'); ?>>Visa</option>
                            <option value="mastercard" <?php selected($card_brand, 'mastercard'); ?>>Mastercard</option>
                            <option value="amex" <?php selected($card_brand, 'amex'); ?>>American Express</option>
                            <option value="discover" <?php selected($card_brand, 'discover'); ?>>Discover</option>
                            <option value="other" <?php selected($card_brand, 'other'); ?>>Other</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="card_holder">Name on Card</label></th>
                    <td>
                        <?php if ($is_admin): ?>
                            <input type="text" name="card_holder" id="card_holder" 
                                   value="<?php echo esc_attr($card_holder_display); ?>" 
                                   class="regular-text" placeholder="JOHN DOE">
                        <?php else: ?>
                            <input type="text" value="<?php echo esc_attr($card_holder_display); ?>" 
                                   class="regular-text" readonly style="background: #f5f5f5;">
                            <input type="hidden" name="card_holder_unchanged" value="1">
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="card_number">Card Number</label></th>
                    <td>
                        <?php if ($is_admin): ?>
                            <input type="text" name="card_number" id="card_number" 
                                   value="<?php echo esc_attr($card_number_display); ?>" 
                                   class="regular-text" placeholder="4111 1111 1111 1111"
                                   maxlength="19" autocomplete="off">
                            <p class="description">🔒 Stored encrypted in database</p>
                        <?php else: ?>
                            <input type="text" value="<?php echo esc_attr($card_number_display); ?>" 
                                   class="regular-text" readonly style="background: #f5f5f5;">
                            <p class="description">For security, only the last 4 digits are shown.</p>
                            <input type="hidden" name="card_number_unchanged" value="1">
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="card_expiry">Expiration Date</label></th>
                    <td>
                        <?php if ($is_admin): ?>
                            <input type="text" name="card_expiry" id="card_expiry" 
                                   value="<?php echo esc_attr($card_expiry_display); ?>" 
                                   class="small-text" placeholder="MM/YY" maxlength="5">
                        <?php else: ?>
                            <input type="text" value="<?php echo esc_attr($card_expiry_display); ?>" 
                                   class="small-text" readonly style="background: #f5f5f5;">
                            <input type="hidden" name="card_expiry_unchanged" value="1">
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="card_cvv">CVV</label></th>
                    <td>
                        <?php if ($is_admin): ?>
                            <input type="text" name="card_cvv" id="card_cvv" 
                                   value="<?php echo esc_attr($card_cvv_display); ?>" 
                                   class="small-text" placeholder="123" maxlength="4" autocomplete="off">
                            <p class="description">🔒 Stored encrypted in database</p>
                        <?php else: ?>
                            <input type="text" value="<?php echo esc_attr($card_cvv_display); ?>" 
                                   class="small-text" readonly style="background: #f5f5f5;">
                            <input type="hidden" name="card_cvv_unchanged" value="1">
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
            
            <!-- PAYPAL -->
            <tbody class="payment-paypal-fields" style="<?php echo ($metodo_pagamento === 'paypal') ? '' : 'display:none;'; ?>">
                <tr>
                    <th><label for="paypal_email">PayPal Email</label></th>
                    <td>
                        <input type="email" name="paypal_email" id="paypal_email" 
                               value="<?php echo esc_attr($paypal_email); ?>" 
                               class="regular-text" placeholder="your@email.com">
                        <p class="description">Email address associated with your PayPal account</p>
                    </td>
                </tr>
            </tbody>
            
            <!-- BANK TRANSFER -->
            <tbody class="payment-bank-fields" style="<?php echo ($metodo_pagamento === 'bank_transfer') ? '' : 'display:none;'; ?>">
                <tr>
                    <th><label for="bank_details">Bank Details</label></th>
                    <td>
                        <textarea name="bank_details" id="bank_details" rows="4" 
                                  class="large-text" placeholder="Bank Name&#10;Account Number&#10;Routing Number&#10;Account Holder Name"><?php echo esc_textarea($bank_details); ?></textarea>
                        <p class="description">Your bank account information for wire transfers</p>
                    </td>
                </tr>
            </tbody>
            
            <!-- NOTAS DE COBRANÇA (apenas admin) -->
            <?php if ($is_admin): ?>
            <tr>
                <th><label for="billing_notes">📝 Billing Notes</label></th>
                <td>
                    <textarea name="billing_notes" id="billing_notes" rows="3" 
                              class="large-text" placeholder="Internal notes about billing (only admin sees)"><?php echo esc_textarea($billing_notes); ?></textarea>
                    <p class="description">Private notes about this client's billing (admin only)</p>
                </td>
            </tr>
            <?php endif; ?>
            
        </table>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Mostrar/ocultar campos baseado no método de pagamento
            $('#metodo_pagamento').change(function() {
                var metodo = $(this).val();
                
                $('.payment-card-fields, .payment-paypal-fields, .payment-bank-fields').hide();
                
                if (metodo === 'credit_card') {
                    $('.payment-card-fields').show();
                } else if (metodo === 'paypal') {
                    $('.payment-paypal-fields').show();
                } else if (metodo === 'bank_transfer') {
                    $('.payment-bank-fields').show();
                }
            });
            
            // Formatar número do cartão enquanto digita
            $('#card_number').on('input', function() {
                var val = $(this).val().replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                var matches = val.match(/\d{4,16}/g);
                var match = matches && matches[0] || '';
                var parts = [];
                
                for (var i = 0, len = match.length; i < len; i += 4) {
                    parts.push(match.substring(i, i + 4));
                }
                
                if (parts.length) {
                    $(this).val(parts.join(' '));
                } else {
                    $(this).val(val);
                }
            });
            
            // Formatar data de validade
            $('#card_expiry').on('input', function() {
                var val = $(this).val().replace(/\D/g, '');
                if (val.length >= 2) {
                    val = val.substring(0, 2) + '/' + val.substring(2, 4);
                }
                $(this).val(val);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Salvar campos de pagamento
     */
    public static function salvar_campos_pagamento($user_id) {
        // Verificar se pode editar
        $is_admin = current_user_can('manage_options');
        $is_own_profile = ($user_id === get_current_user_id());
        
        if (!$is_admin && !$is_own_profile) return;
        
        // Verificar se é cliente
        $user = get_userdata($user_id);
        if (!in_array('cliente_bordados', (array) $user->roles)) return;
        
        // Método de pagamento
        if (isset($_POST['metodo_pagamento'])) {
            update_user_meta($user_id, 'metodo_pagamento', sanitize_text_field($_POST['metodo_pagamento']));
        }
        
        // Dados do cartão - APENAS ADMIN pode alterar
        if ($is_admin) {
            // Card Brand
            if (isset($_POST['card_brand'])) {
                update_user_meta($user_id, 'card_brand', sanitize_text_field($_POST['card_brand']));
            }
            
            // Card Holder (criptografado)
            if (isset($_POST['card_holder']) && !empty($_POST['card_holder'])) {
                $encrypted = self::encrypt_data(sanitize_text_field($_POST['card_holder']));
                update_user_meta($user_id, 'card_holder', $encrypted);
            }
            
            // Card Number (criptografado)
            if (isset($_POST['card_number']) && !empty($_POST['card_number'])) {
                // Remover espaços antes de criptografar
                $clean_number = preg_replace('/\s+/', '', sanitize_text_field($_POST['card_number']));
                $encrypted = self::encrypt_data($clean_number);
                update_user_meta($user_id, 'card_number', $encrypted);
            }
            
            // Card Expiry (criptografado)
            if (isset($_POST['card_expiry']) && !empty($_POST['card_expiry'])) {
                $encrypted = self::encrypt_data(sanitize_text_field($_POST['card_expiry']));
                update_user_meta($user_id, 'card_expiry', $encrypted);
            }
            
            // Card CVV (criptografado)
            if (isset($_POST['card_cvv']) && !empty($_POST['card_cvv'])) {
                $encrypted = self::encrypt_data(sanitize_text_field($_POST['card_cvv']));
                update_user_meta($user_id, 'card_cvv', $encrypted);
            }
            
            // Billing Notes
            if (isset($_POST['billing_notes'])) {
                update_user_meta($user_id, 'billing_notes', sanitize_textarea_field($_POST['billing_notes']));
            }
        }
        
        // PayPal e Bank - Cliente pode alterar
        if (isset($_POST['paypal_email'])) {
            update_user_meta($user_id, 'paypal_email', sanitize_email($_POST['paypal_email']));
        }
        
        if (isset($_POST['bank_details'])) {
            update_user_meta($user_id, 'bank_details', sanitize_textarea_field($_POST['bank_details']));
        }
    }
    
    /**
     * ========================================
     * CAMPOS ADMINISTRATIVOS (apenas admin vê)
     * ========================================
     */
    public static function mostrar_campos_admin($user) {
        if (!current_user_can('edit_users')) return;
        
        // Só mostrar para clientes
        if (!in_array('cliente_bordados', $user->roles)) return;
        
        // Buscar valores atuais
        $cliente_bloqueado = get_user_meta($user->ID, 'cliente_bloqueado', true);
        $motivo_bloqueio = get_user_meta($user->ID, 'motivo_bloqueio', true);
        $mensagem_bloqueio = get_user_meta($user->ID, 'mensagem_bloqueio', true);
        $sistema_preco = get_user_meta($user->ID, 'sistema_preco', true);
        $multiplicador_preco = get_user_meta($user->ID, 'multiplicador_preco', true);
        $saldo_creditos = get_user_meta($user->ID, 'saldo_creditos', true);
        $nivel_dificuldade_padrao = get_user_meta($user->ID, 'nivel_dificuldade_padrao', true);
        $cliente_inativo = get_user_meta($user->ID, 'cliente_inativo', true);
        $obs_admin = get_user_meta($user->ID, 'obs_admin', true);
        
        ?>
        <h2>🔐 Configurações Administrativas</h2>
        <table class="form-table" role="presentation">
            
            <!-- BLOQUEIO -->
            <tr>
                <th><label for="cliente_bloqueado">🚫 Cliente Bloqueado</label></th>
                <td>
                    <input type="checkbox" name="cliente_bloqueado" id="cliente_bloqueado" 
                           value="yes" <?php checked($cliente_bloqueado, 'yes'); ?>>
                    <span style="color: red; font-weight: bold;">Cliente NÃO poderá fazer login</span>
                    <p class="description">Use para bloquear acesso por falta de pagamento ou problemas graves.</p>
                </td>
            </tr>
            
            <tr class="bloqueio-campos" style="<?php echo ($cliente_bloqueado === 'yes') ? '' : 'display:none;'; ?>">
                <th><label for="motivo_bloqueio">Motivo do Bloqueio</label></th>
                <td>
                    <textarea name="motivo_bloqueio" id="motivo_bloqueio" rows="3" 
                              class="large-text" placeholder="Motivo interno (apenas admin vê)"><?php echo esc_textarea($motivo_bloqueio); ?></textarea>
                    <p class="description">Descrição interna do motivo (não mostrado ao cliente).</p>
                </td>
            </tr>
            
            <tr class="bloqueio-campos" style="<?php echo ($cliente_bloqueado === 'yes') ? '' : 'display:none;'; ?>">
                <th><label for="mensagem_bloqueio">Mensagem ao Cliente</label></th>
                <td>
                    <textarea name="mensagem_bloqueio" id="mensagem_bloqueio" rows="3" 
                              class="large-text" placeholder="Mensagem que o cliente verá ao tentar fazer login"><?php echo esc_textarea($mensagem_bloqueio); ?></textarea>
                    <p class="description">Esta mensagem será mostrada ao cliente quando tentar fazer login.</p>
                </td>
            </tr>
            
            <!-- SISTEMA DE PREÇOS -->
            <tr>
                <th><label for="sistema_preco">💰 Sistema de Preços</label></th>
                <td>
                    <select name="sistema_preco" id="sistema_preco" class="regular-text">
                        <option value="">-- Selecione --</option>
                        <option value="legacy_stitches" <?php selected($sistema_preco, 'legacy_stitches'); ?>>
                            Legacy (Por Pontos) - Sistema Antigo
                        </option>
                        <option value="fixed_tier" <?php selected($sistema_preco, 'fixed_tier'); ?>>
                            Fixed Tier (Tamanho + Dificuldade) - Sistema Novo
                        </option>
                        <option value="multiplier" <?php selected($sistema_preco, 'multiplier'); ?>>
                            Automático (Multiplicador) - Para Revendas
                        </option>
                        <option value="credits" <?php selected($sistema_preco, 'credits'); ?>>
                            Créditos/Pacotes - Pré-Pago
                        </option>
                    </select>
                    <p class="description">Defina qual sistema de precificação usar para este cliente.</p>
                </td>
            </tr>
            
            <tr class="multiplier-campo" style="<?php echo ($sistema_preco === 'multiplier') ? '' : 'display:none;'; ?>">
                <th><label for="multiplicador_preco">📊 Multiplicador de Preço</label></th>
                <td>
                    <input type="number" name="multiplicador_preco" id="multiplicador_preco" 
                           value="<?php echo esc_attr($multiplicador_preco ?: '1.00'); ?>" 
                           step="0.01" min="0.50" max="3.00" class="small-text">
                    <p class="description">
                        Multiplicador sobre o preço base:<br>
                        • 0.80 = 20% desconto (para grandes volumes)<br>
                        • 1.00 = preço normal<br>
                        • 1.50 = 50% markup
                    </p>
                </td>
            </tr>
            
            <tr class="credits-campo" style="<?php echo ($sistema_preco === 'credits') ? '' : 'display:none;'; ?>">
                <th><label for="saldo_creditos">💳 Saldo de Créditos</label></th>
                <td>
                    <input type="number" name="saldo_creditos" id="saldo_creditos" 
                           value="<?php echo esc_attr($saldo_creditos ?: '0'); ?>" 
                           step="0.01" min="0" class="regular-text">
                    <span>créditos</span>
                    <p class="description">Saldo atual de créditos do cliente.</p>
                </td>
            </tr>
            
            <!-- NÍVEL DE DIFICULDADE PADRÃO -->
            <tr>
                <th><label for="nivel_dificuldade_padrao">⚙️ Dificuldade Padrão</label></th>
                <td>
                    <select name="nivel_dificuldade_padrao" id="nivel_dificuldade_padrao" class="regular-text">
                        <option value="">-- Não definido --</option>
                        <option value="facil" <?php selected($nivel_dificuldade_padrao, 'facil'); ?>>Fácil</option>
                        <option value="medio" <?php selected($nivel_dificuldade_padrao, 'medio'); ?>>Médio</option>
                        <option value="complicado" <?php selected($nivel_dificuldade_padrao, 'complicado'); ?>>Complicado</option>
                    </select>
                    <p class="description">Nível de dificuldade típico dos trabalhos deste cliente (usado se sistema = fixed_tier).</p>
                </td>
            </tr>
            
            <!-- CLIENTE INATIVO -->
            <tr>
                <th><label for="cliente_inativo">😴 Cliente Inativo</label></th>
                <td>
                    <input type="checkbox" name="cliente_inativo" id="cliente_inativo" 
                           value="yes" <?php checked($cliente_inativo, 'yes'); ?>>
                    <span>Marcar como inativo (parou de usar o serviço)</span>
                    <p class="description">
                        Use para CRM/Marketing. Cliente inativo pode fazer login normalmente,<br>
                        mas está marcado para campanhas de reativação.
                    </p>
                </td>
            </tr>
            
            <!-- OBSERVAÇÕES DO ADMIN -->
            <tr>
                <th><label for="obs_admin">📝 Observações Internas</label></th>
                <td>
                    <textarea name="obs_admin" id="obs_admin" rows="5" 
                              class="large-text" placeholder="Anotações privadas sobre o cliente (apenas admin vê)"><?php echo esc_textarea($obs_admin); ?></textarea>
                    <p class="description">Observações privadas que só o administrador pode ver.</p>
                </td>
            </tr>
            
        </table>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Mostrar/ocultar campos de bloqueio
            $('#cliente_bloqueado').change(function() {
                if ($(this).is(':checked')) {
                    $('.bloqueio-campos').show();
                } else {
                    $('.bloqueio-campos').hide();
                }
            });
            
            // Mostrar/ocultar campos de sistema de preço
            $('#sistema_preco').change(function() {
                var sistema = $(this).val();
                $('.multiplier-campo, .credits-campo').hide();
                
                if (sistema === 'multiplier') {
                    $('.multiplier-campo').show();
                } else if (sistema === 'credits') {
                    $('.credits-campo').show();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Salvar campos administrativos
     */
    public static function salvar_campos_admin($user_id) {
        if (!current_user_can('edit_users')) return;
        
        // Cliente Bloqueado
        if (isset($_POST['cliente_bloqueado'])) {
            update_user_meta($user_id, 'cliente_bloqueado', 'yes');
        } else {
            update_user_meta($user_id, 'cliente_bloqueado', 'no');
        }
        
        // Motivo e Mensagem de Bloqueio
        if (isset($_POST['motivo_bloqueio'])) {
            update_user_meta($user_id, 'motivo_bloqueio', sanitize_textarea_field($_POST['motivo_bloqueio']));
        }
        
        if (isset($_POST['mensagem_bloqueio'])) {
            update_user_meta($user_id, 'mensagem_bloqueio', sanitize_textarea_field($_POST['mensagem_bloqueio']));
        }
        
        // Sistema de Preços
        if (isset($_POST['sistema_preco'])) {
            update_user_meta($user_id, 'sistema_preco', sanitize_text_field($_POST['sistema_preco']));
        }
        
        // Multiplicador
        if (isset($_POST['multiplicador_preco'])) {
            update_user_meta($user_id, 'multiplicador_preco', floatval($_POST['multiplicador_preco']));
        }
        
        // Saldo de Créditos
        if (isset($_POST['saldo_creditos'])) {
            update_user_meta($user_id, 'saldo_creditos', floatval($_POST['saldo_creditos']));
        }
        
        // Nível de Dificuldade Padrão
        if (isset($_POST['nivel_dificuldade_padrao'])) {
            update_user_meta($user_id, 'nivel_dificuldade_padrao', sanitize_text_field($_POST['nivel_dificuldade_padrao']));
        }
        
        // Cliente Inativo
        if (isset($_POST['cliente_inativo'])) {
            update_user_meta($user_id, 'cliente_inativo', 'yes');
        } else {
            update_user_meta($user_id, 'cliente_inativo', 'no');
        }
        
        // Observações Admin
        if (isset($_POST['obs_admin'])) {
            update_user_meta($user_id, 'obs_admin', sanitize_textarea_field($_POST['obs_admin']));
        }
    }
    
    /**
     * Verificar se cliente está bloqueado no login
     */
    public static function verificar_bloqueio_login($user, $password) {
        if (is_wp_error($user)) {
            return $user;
        }
        
        // Verificar se é cliente
        if (in_array('cliente_bordados', $user->roles)) {
            $bloqueado = get_user_meta($user->ID, 'cliente_bloqueado', true);
            
            if ($bloqueado === 'yes') {
                $mensagem = get_user_meta($user->ID, 'mensagem_bloqueio', true);
                
                if (empty($mensagem)) {
                    $mensagem = 'Sua conta está temporariamente bloqueada. Entre em contato com o suporte.';
                }
                
                return new WP_Error('cliente_bloqueado', $mensagem);
            }
        }
        
        return $user;
    }
}

// Inicializar
Bordados_Perfil_Cliente::init();
