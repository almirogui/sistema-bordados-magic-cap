<?php
/**
 * Shortcode: Formulário de Login - [bordados_login]
 * Extraído de class-shortcodes.php na Fase 3 da modularização
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Shortcode_Login {
    
    /**
     * Renderizar formulário de login
     */
    public static function render($atts) {
        if (is_user_logged_in()) {
            return '<p>Você já está logado! <a href="' . esc_url(site_url('/meus-pedidos/')) . '">Ver meus pedidos</a></p>';
        }
        
        ob_start();
        ?>
        <div style="max-width: 400px; margin: 50px auto; padding: 20px;">
            <div style="background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 30px;">
                <h2 style="text-align: center;">🎨 Magic Cap Bordados</h2>
                <h3 style="text-align: center;">Acesso ao Sistema</h3>
                
                <div id="login-mensagem" style="display: none; padding: 10px; margin-bottom: 15px; border-radius: 5px;"></div>
                
                <form id="bordados-login-form">
                    <?php wp_nonce_field('bordados_login_nonce', 'nonce'); ?>
                    
                    <div style="margin-bottom: 15px;">
                        <label>👤 Usuário ou Email:</label><br>
                        <input type="text" name="usuario" required style="width: 100%; padding: 10px; margin-top: 5px;">
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label>�â€â€™ Senha:</label><br>
                        <input type="password" name="senha" required style="width: 100%; padding: 10px; margin-top: 5px;">
                    </div>
                    
                    <button type="submit" style="width: 100%; background: #0073aa; color: white; padding: 15px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;">
                        🏀 Entrar no Sistema
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="<?php echo wp_lostpassword_url(); ?>">�â€â€˜ Esqueci minha senha</a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

?>
