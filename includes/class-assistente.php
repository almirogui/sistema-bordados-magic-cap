<?php
/**
 * Classe para Role Assistente
 * 
 * Permite funcionários operarem o sistema sem acesso ao WordPress admin.
 * Funcionalidades: ver pedidos, atribuir, editar, cadastrar clientes.
 * 
 * @package Sistema_Bordados
 * @since 3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Assistente {
    
    /**
     * Construtor - registra todos os hooks
     */
    public function __construct() {
        // Registrar role na ativação
        add_action('init', array($this, 'registrar_role'));
        
        // Bloquear acesso ao wp-admin
        add_action('admin_init', array($this, 'bloquear_wp_admin'));
        
        // Remover admin bar
        add_action('after_setup_theme', array($this, 'remover_admin_bar'));
        
        // NOTA: O redirecionamento após login já é tratado pela função
        // bordados_redirecionamento_inteligente() no arquivo principal
    }
    
    /**
     * Registrar role assistente_bordados
     */
    public function registrar_role() {
        // Verificar se role já existe
        if (get_role('assistente_bordados')) {
            return;
        }
        
        add_role('assistente_bordados', 'Assistente', array(
            'read'               => true,
            'ver_todos_pedidos'  => true,
            'atribuir_pedidos'   => true,
            'editar_pedidos'     => true,
            'cadastrar_clientes' => true,
            'editar_clientes'    => true,
            'ver_precos'         => true
        ));
        
        error_log('✅ Role assistente_bordados criada com sucesso');
    }
    
    /**
     * Atualizar capabilities da role (usar se precisar adicionar novas)
     */
    public static function atualizar_role() {
        $role = get_role('assistente_bordados');
        
        if (!$role) {
            return false;
        }
        
        // Capabilities permitidas
        $capabilities = array(
            'read'               => true,
            'ver_todos_pedidos'  => true,
            'atribuir_pedidos'   => true,
            'editar_pedidos'     => true,
            'cadastrar_clientes' => true,
            'editar_clientes'    => true,
            'ver_precos'         => true
        );
        
        foreach ($capabilities as $cap => $grant) {
            $role->add_cap($cap, $grant);
        }
        
        return true;
    }
    
    /**
     * Remover role (usar na desativação do plugin se necessário)
     */
    public static function remover_role() {
        remove_role('assistente_bordados');
    }
    
    /**
     * Bloquear acesso ao wp-admin para assistentes
     */
    public function bloquear_wp_admin() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user = wp_get_current_user();
        
        // Verificar se é assistente
        if (!in_array('assistente_bordados', (array) $user->roles)) {
            return;
        }
        
        // Permitir requisições AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // Bloquear acesso ao wp-admin
        wp_redirect(site_url('/painel-assistente/'));
        exit;
    }
    
    /**
     * Remover admin bar para assistentes
     */
    public function remover_admin_bar() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user = wp_get_current_user();
        
        if (in_array('assistente_bordados', (array) $user->roles)) {
            // Método 1: show_admin_bar
            show_admin_bar(false);
            
            // Método 2: Filter backup
            add_filter('show_admin_bar', '__return_false');
        }
    }
    
    /**
     * Verificar se usuário atual é assistente
     */
    public static function is_assistente() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('assistente_bordados', (array) $user->roles);
    }
    
    /**
     * Verificar se usuário tem acesso ao painel assistente
     * (assistente ou admin)
     */
    public static function pode_acessar_painel() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        
        return in_array('assistente_bordados', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles);
    }
}

// Inicializar a classe
new Bordados_Assistente();
