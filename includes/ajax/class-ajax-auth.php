<?php
/**
 * AJAX: Funções de Autenticação
 * Extraído de class-ajax.php na Fase 4 da modularização
 * 
 * Funções:
 * - login
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Auth {
    
    public function __construct() {
        add_action('wp_ajax_nopriv_bordados_login', array($this, 'login'));
    }
    
    public function login() {
        check_ajax_referer('bordados_login_nonce', 'nonce');
        
        $usuario = sanitize_text_field($_POST['usuario']);
        $senha = $_POST['senha'];
        
        if (empty($usuario) || empty($senha)) {
            wp_send_json_error('Por favor, preencha todos os campos.');
        }
        
        $creds = array(
            'user_login' => $usuario,
            'user_password' => $senha,
            'remember' => false
        );
        
        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
            wp_send_json_error('Usuário ou senha incorretos.');
        }
        
        wp_send_json_success('Login realizado com sucesso!');
    }
    
    /**
     * Processar uploads múltiplos (método legacy)
     */

}

?>
