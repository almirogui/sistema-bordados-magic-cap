<?php
/**
 * AJAX Handler para Salvar Perfil do Cliente
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Perfil {
    
    /**
     * Inicializar hooks AJAX
     */
    public static function init() {
        add_action('wp_ajax_salvar_perfil_cliente', array(__CLASS__, 'salvar_perfil_cliente'));
        add_action('wp_ajax_salvar_cartao_cliente', array(__CLASS__, 'salvar_cartao_cliente'));
    }
    
    /**
     * Salvar cartão de crédito do cliente
     */
    public static function salvar_cartao_cliente() {
        // Verificar nonce
        if (!isset($_POST['card_nonce']) || !wp_verify_nonce($_POST['card_nonce'], 'salvar_cartao_cliente')) {
            wp_send_json_error('Security error. Please reload the page.');
            return;
        }
        
        // Verificar login
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
            return;
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Verificar se é cliente ou admin
        if (!in_array('cliente_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
            wp_send_json_error('Access denied.');
            return;
        }
        
        // Validar campos obrigatórios
        $required = array('card_brand', 'card_holder', 'card_number', 'card_expiry', 'card_cvv');
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error('Please fill in all required fields.');
                return;
            }
        }
        
        // Limpar número do cartão (remover espaços)
        $card_number = preg_replace('/\s+/', '', sanitize_text_field($_POST['card_number']));
        
        // Validar número do cartão (deve ter 13-19 dígitos)
        if (!preg_match('/^\d{13,19}$/', $card_number)) {
            wp_send_json_error('Invalid card number.');
            return;
        }
        
        // Validar validade (MM/YY)
        $card_expiry = sanitize_text_field($_POST['card_expiry']);
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_expiry)) {
            wp_send_json_error('Invalid expiration date. Use MM/YY format.');
            return;
        }
        
        // Validar CVV (3-4 dígitos)
        $card_cvv = sanitize_text_field($_POST['card_cvv']);
        if (!preg_match('/^\d{3,4}$/', $card_cvv)) {
            wp_send_json_error('Invalid CVV.');
            return;
        }
        
        // Verificar se a classe de criptografia existe
        if (!class_exists('Bordados_Perfil_Cliente')) {
            wp_send_json_error('Encryption system not available.');
            return;
        }
        
        // Criptografar e salvar dados
        $card_brand = sanitize_text_field($_POST['card_brand']);
        $card_holder = strtoupper(sanitize_text_field($_POST['card_holder']));
        
        update_user_meta($user_id, 'card_brand', $card_brand);
        update_user_meta($user_id, 'card_holder', Bordados_Perfil_Cliente::encrypt_data($card_holder));
        update_user_meta($user_id, 'card_number', Bordados_Perfil_Cliente::encrypt_data($card_number));
        update_user_meta($user_id, 'card_expiry', Bordados_Perfil_Cliente::encrypt_data($card_expiry));
        update_user_meta($user_id, 'card_cvv', Bordados_Perfil_Cliente::encrypt_data($card_cvv));
        
        // Atualizar método de pagamento
        update_user_meta($user_id, 'metodo_pagamento', 'credit_card');
        
        // Gerar HTML do cartão mascarado para atualizar a tela
        $last4 = substr($card_number, -4);
        $card_masked = '•••• •••• •••• ' . $last4;
        
        $card_html = '
        <div class="form-row">
            <div class="form-col">
                <label>Card on File</label>
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; color: white; max-width: 350px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                    <p style="margin: 0 0 15px 0; font-size: 20px; letter-spacing: 3px; font-family: \'Courier New\', monospace;">
                        ' . esc_html($card_masked) . '
                    </p>
                    <div style="display: flex; justify-content: space-between; font-size: 12px; text-transform: uppercase;">
                        <div>
                            <span style="opacity: 0.7;">Card Holder</span><br>
                            <strong>' . esc_html($card_holder) . '</strong>
                        </div>
                        <div style="text-align: right;">
                            <span style="opacity: 0.7;">Expires</span><br>
                            <strong>' . esc_html($card_expiry) . '</strong>
                        </div>
                    </div>
                    <div style="text-align: right; margin-top: 10px;">
                        <span style="background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 4px; font-size: 11px; text-transform: uppercase;">
                            ' . esc_html($card_brand) . '
                        </span>
                    </div>
                </div>
                <button type="button" onclick="openCardModal()" class="btn-update-card" style="margin-top: 10px; background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer;">
                    🔄 Update Card
                </button>
            </div>
        </div>';
        
        wp_send_json_success(array(
            'message' => 'Card saved successfully!',
            'card_html' => $card_html
        ));
    }
    
    /**
     * Salvar perfil do cliente
     */
    public static function salvar_perfil_cliente() {
        // Verificar nonce
        if (!isset($_POST['perfil_nonce']) || !wp_verify_nonce($_POST['perfil_nonce'], 'salvar_perfil_cliente')) {
            wp_send_json_error('Erro de segurança. Recarregue a página e tente novamente.');
            return;
        }
        
        // Verificar login
        if (!is_user_logged_in()) {
            wp_send_json_error('Você precisa estar logado.');
            return;
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Verificar se é cliente
        if (!in_array('cliente_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        // ========================================
        // VALIDAÇÕES
        // ========================================
        
        $erros = array();
        
        // Validar email principal
        if (empty($_POST['user_email']) || !is_email($_POST['user_email'])) {
            $erros[] = 'Email principal inválido';
        }
        
        // Validar emails secundários
        if (!empty($_POST['email_secundario'])) {
            $validacao = self::validar_emails_multiplos($_POST['email_secundario']);
            if ($validacao !== true) {
                // $validacao agora contém a mensagem de erro específica
                $erros[] = $validacao;
            }
        }
        
        if (!empty($_POST['email_invoice']) && !is_email($_POST['email_invoice'])) {
            $erros[] = 'Email de invoice inválido';
        }
        
        // Validar CPF/CNPJ (apenas se país for BR)
        $pais = sanitize_text_field($_POST['pais']);
        if ($pais === 'BR' && !empty($_POST['cpf_cnpj'])) {
            $cpf_cnpj = self::limpar_cpf_cnpj($_POST['cpf_cnpj']);
            if (!self::validar_cpf_cnpj($cpf_cnpj)) {
                $erros[] = 'CPF/CNPJ inválido';
            }
        }
        
        // Se houver erros, retornar
        if (!empty($erros)) {
            wp_send_json_error(implode(', ', $erros));
            return;
        }
        
        // ========================================
        // SALVAR DADOS
        // ========================================
        
        // Atualizar email principal (WordPress)
        if ($_POST['user_email'] !== $user->user_email) {
            wp_update_user(array(
                'ID' => $user_id,
                'user_email' => sanitize_email($_POST['user_email'])
            ));
        }
        
        // DADOS PESSOAIS
        update_user_meta($user_id, 'first_name', sanitize_text_field($_POST['first_name']));
        update_user_meta($user_id, 'last_name', sanitize_text_field($_POST['last_name']));
        update_user_meta($user_id, 'titulo_cliente', sanitize_text_field($_POST['titulo_cliente']));
        update_user_meta($user_id, 'apelido_cliente', sanitize_text_field($_POST['apelido_cliente']));
        update_user_meta($user_id, 'email_secundario', sanitize_text_field($_POST['email_secundario']));
        update_user_meta($user_id, 'email_invoice', sanitize_email($_POST['email_invoice']));
        update_user_meta($user_id, 'telefone_whatsapp', sanitize_text_field($_POST['telefone_whatsapp']));
        update_user_meta($user_id, 'cpf_cnpj', sanitize_text_field($_POST['cpf_cnpj']));
        update_user_meta($user_id, 'data_nascimento', sanitize_text_field($_POST['data_nascimento']));
        
        // ENDEREÇO
        update_user_meta($user_id, 'pais', sanitize_text_field($_POST['pais']));
        update_user_meta($user_id, 'cep', sanitize_text_field($_POST['cep']));
        update_user_meta($user_id, 'endereco_rua', sanitize_text_field($_POST['endereco_rua']));
        update_user_meta($user_id, 'endereco_numero', sanitize_text_field($_POST['endereco_numero']));
        update_user_meta($user_id, 'endereco_complemento', sanitize_text_field($_POST['endereco_complemento']));
        update_user_meta($user_id, 'endereco_bairro', sanitize_text_field($_POST['endereco_bairro']));
        update_user_meta($user_id, 'endereco_cidade', sanitize_text_field($_POST['endereco_cidade']));
        update_user_meta($user_id, 'endereco_estado', sanitize_text_field($_POST['endereco_estado']));
        
        // DADOS DA EMPRESA
        update_user_meta($user_id, 'razao_social', sanitize_text_field($_POST['razao_social']));
        update_user_meta($user_id, 'nome_fantasia', sanitize_text_field($_POST['nome_fantasia']));
        update_user_meta($user_id, 'cnpj_empresa', sanitize_text_field($_POST['cnpj_empresa']));
        
        // PREFERÊNCIAS
        update_user_meta($user_id, 'formato_arquivo_preferido', sanitize_text_field($_POST['formato_arquivo_preferido']));
        update_user_meta($user_id, 'unidade_medida_preferida', sanitize_text_field($_POST['unidade_medida_preferida']));
        update_user_meta($user_id, 'maquina_bordar', sanitize_text_field($_POST['maquina_bordar']));
        update_user_meta($user_id, 'obs_para_programador', sanitize_textarea_field($_POST['obs_para_programador']));
        
        // PAGAMENTO (cliente pode alterar método, PayPal e dados bancários)
        if (isset($_POST['metodo_pagamento'])) {
            update_user_meta($user_id, 'metodo_pagamento', sanitize_text_field($_POST['metodo_pagamento']));
        }
        
        if (isset($_POST['paypal_email'])) {
            update_user_meta($user_id, 'paypal_email', sanitize_email($_POST['paypal_email']));
        }
        
        if (isset($_POST['bank_details'])) {
            update_user_meta($user_id, 'bank_details', sanitize_textarea_field($_POST['bank_details']));
        }
        
        wp_send_json_success('✅ Profile updated successfully!');
    }
    
    /**
     * Limpar CPF/CNPJ (remover pontos, traços, barras)
     */
    private static function limpar_cpf_cnpj($valor) {
        return preg_replace('/[^0-9]/', '', $valor);
    }
    
    /**
     * Validar CPF ou CNPJ
     */
    private static function validar_cpf_cnpj($valor) {
        $valor = self::limpar_cpf_cnpj($valor);
        
        // CPF
        if (strlen($valor) === 11) {
            return self::validar_cpf($valor);
        }
        
        // CNPJ
        if (strlen($valor) === 14) {
            return self::validar_cnpj($valor);
        }
        
        return false;
    }
    
    /**
     * Validar CPF
     */
    private static function validar_cpf($cpf) {
        // Elimina CPFs invalidos conhecidos
        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        // Valida 1o digito
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validar CNPJ
     */
    private static function validar_cnpj($cnpj) {
        // Elimina CNPJs invalidos conhecidos
        if (strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        // Valida primeiro dígito verificador
        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $resto = $soma % 11;
        
        if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
            return false;
        }
        
        // Valida segundo dígito verificador
        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        
        $resto = $soma % 11;
        
        return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
    }
/**
     * Validar múltiplos emails separados por vírgula
     * LIMITE: até 10 emails
     * 
     * @param string $emails_string Emails separados por vírgula
     * @return bool|string TRUE se válido, mensagem de erro se inválido
     */
    private static function validar_emails_multiplos($emails_string) {
        // Aceitar vazio
        if (empty($emails_string)) {
            return true;
        }
        
        // Separar por vírgula e limpar espaços
        $emails = array_map('trim', explode(',', $emails_string));
        
        // Filtrar vazios
        $emails = array_filter($emails, function($email) {
            return !empty($email);
        });
        
        // Validar limite de 10 emails
        if (count($emails) > 10) {
            return 'Maximum 10 secondary emails allowed (' . count($emails) . ' provided)';
        }
        
        // Validar cada email
        $emails_invalidos = array();
        foreach ($emails as $email) {
            if (!is_email($email)) {
                $emails_invalidos[] = $email;
            }
        }
        
        // Se houver inválidos, retornar erro específico
        if (!empty($emails_invalidos)) {
            return 'Invalid email(s): ' . implode(', ', $emails_invalidos);
        }
        
        // Tudo OK
        return true;
    }

}

// Inicializar
Bordados_Ajax_Perfil::init();
