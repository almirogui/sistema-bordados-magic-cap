<?php
/**
 * Plugin Name: Advanced Puncher System
 * Description: Complete order management system for embroidery digitizing services
 * Version: 3.2
 * Author: Puncher.com
 */
// FORÇAR UTF-8 EM TODAS AS PÁGINAS DO PLUGIN
   if (!headers_sent()) {
       header('Content-Type: text/html; charset=UTF-8');
   }
// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes do plugin
define('BORDADOS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BORDADOS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BORDADOS_PLUGIN_VERSION', '3.2');

// Carregar autoload do Composer (DOMPDF)
$composer_autoload = BORDADOS_PLUGIN_PATH . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

// Classe principal do plugin
class SistemaBordadosSimples {

    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'ativar_plugin'));
        add_action('after_setup_theme', array($this, 'configurar_admin_bar'));
        
        // Verificar atualização de versão
        add_action('plugins_loaded', array($this, 'verificar_atualizacao'));

        // Incluir arquivos necessários
        $this->incluir_arquivos();
    }
    
    /**
     * Verificar se precisa atualizar tabelas (para sites existentes)
     */
    public function verificar_atualizacao() {
        $versao_instalada = get_option('bordados_plugin_version', '0');
        
        if (version_compare($versao_instalada, BORDADOS_PLUGIN_VERSION, '<')) {
            // Atualizar tabelas de cobrança
            if (class_exists('Bordados_Cobranca')) {
                Bordados_Cobranca::criar_tabelas();
            }
            
            // Atualizar versão no banco
            update_option('bordados_plugin_version', BORDADOS_PLUGIN_VERSION);
        }
    }

    /**
     * Incluir todos os arquivos do plugin
     */
    private function incluir_arquivos() {
        // Incluir classes principais
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-database.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-ajax.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-emails.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-shortcodes.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-helpers.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/bordados-admin-manager.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-programador-dashboard.php';
    // Perfil do Cliente - NOVO
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-perfil-admin.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-shortcode-perfil.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-ajax-perfil.php';
    // Sistema de Atribuição Automática - v2.2
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-programador-admin.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-atribuicao-automatica.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-widget-programadores.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-cliente-atribuicao-auto.php';
    // NOVO
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-hook-criacao-pedido.php';
    // Classe de Orçamentos
        require_once plugin_dir_path(__FILE__) . 'includes/ajax/class-ajax-orcamento.php';
        new Bordados_Ajax_Orcamento();
    // Classe de Preços
        require_once plugin_dir_path(__FILE__) . 'includes/class-precos.php';
    // Custom Login/Register Pages
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-puncher-auth.php';
    // Sistema de Cobranca e Invoices - v3.1
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-cobranca.php';
    // Sistema de Assistente - v3.2
        require_once BORDADOS_PLUGIN_PATH . 'includes/class-assistente.php';
        require_once BORDADOS_PLUGIN_PATH . 'includes/ajax/class-ajax-assistente.php';
        new Bordados_Ajax_Assistente();
      }

    public function init() {
        // Registrar shortcodes
        add_shortcode('bordados_meus_pedidos', array('Bordados_Shortcodes', 'dashboard_cliente'));
        add_shortcode('bordados_meus_trabalhos', array('Bordados_Shortcodes', 'dashboard_programador'));
        add_shortcode('bordados_admin_pedidos', array('Bordados_Shortcodes', 'dashboard_admin'));
        add_action('wp_ajax_bordados_buscar_detalhes_pedido_admin', array('Bordados_Shortcodes', 'ajax_buscar_detalhes_pedido_admin'));
	    add_shortcode('bordados_painel_revisor', array('Bordados_Shortcodes', 'dashboard_revisor'));
	    add_shortcode('bordados_dashboard_embaixador', array('Bordados_Shortcodes', 'dashboard_embaixador'));
        add_shortcode('bordados_painel_assistente', array('Bordados_Shortcodes', 'dashboard_assistente'));
        add_shortcode('bordados_novo_pedido', array('Bordados_Shortcodes', 'formulario_novo_pedido'));
        add_shortcode('bordados_login', array('Bordados_Shortcodes', 'formulario_login'));
        add_shortcode('bordados_painel_programador_v2', function() {
    if (class_exists('Bordados_Programador_Dashboard')) {
        try {
            $dashboard = new Bordados_Programador_Dashboard();
            return $dashboard->dashboard_programador_otimizado();
        } catch (Exception $e) {
            return '<div style="background: red; color: white; padding: 20px;">ERRO na classe: ' . $e->getMessage() . '</div>';
        }
    } else {
        return '<div style="background: orange; color: white; padding: 20px;">ERRO: Classe Bordados_Programador_Dashboard não foi carregada!</div>';
    }
});

        // Garantir que shortcodes funcionem
        add_filter('the_content', 'do_shortcode', 11);

        // Inicializar AJAX
        new Bordados_Ajax();

        // Proteção de páginas
        add_action('template_redirect', array($this, 'proteger_paginas'));

        // Campos de perfil do usuário
        add_action('show_user_profile', array('Bordados_Helpers', 'adicionar_campo_programador_padrao'));
        add_action('edit_user_profile', array('Bordados_Helpers', 'adicionar_campo_programador_padrao'));
        add_action('personal_options_update', array('Bordados_Helpers', 'salvar_campo_programador_padrao'));
        add_action('edit_user_profile_update', array('Bordados_Helpers', 'salvar_campo_programador_padrao'));

        // NOVO: Campos de revisão
        add_action('show_user_profile', array('Bordados_Helpers', 'adicionar_campo_requer_revisao'));
        add_action('edit_user_profile', array('Bordados_Helpers', 'adicionar_campo_requer_revisao'));
        add_action('personal_options_update', array('Bordados_Helpers', 'salvar_campo_requer_revisao'));
        add_action('edit_user_profile_update', array('Bordados_Helpers', 'salvar_campo_requer_revisao'));

        // NOVO: Campos de embaixador
        add_action('show_user_profile', array('Bordados_Helpers', 'adicionar_campos_embaixador'));
        add_action('edit_user_profile', array('Bordados_Helpers', 'adicionar_campos_embaixador'));
        add_action('personal_options_update', array('Bordados_Helpers', 'salvar_campos_embaixador'));
        add_action('edit_user_profile_update', array('Bordados_Helpers', 'salvar_campos_embaixador'));

        // Scripts e estilos
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Redirecionamento após login
        add_filter('login_redirect', array('Bordados_Helpers', 'redirecionar_apos_login'), 10, 3);
    }

    /**
     * Ativação do plugin
     */
    public function ativar_plugin() {
        // Criar pasta de uploads
        $upload_dir = wp_upload_dir();
        $bordados_dir = $upload_dir['basedir'] . '/bordados';

        if (!file_exists($bordados_dir)) {
            wp_mkdir_p($bordados_dir);
        }

        // Criar/atualizar tabela
        Bordados_Database::criar_tabelas();
        
        // Criar tabelas de cobranca e invoices
        Bordados_Cobranca::criar_tabelas();
        
        // NOVO: Criar/atualizar roles
        $this->criar_roles();
    }

    /**
     * NOVO: Criar roles customizadas
     */
    private function criar_roles() {
        // Role: Cliente
        add_role('cliente_bordados', 'Cliente Bordados', array(
            'read' => true,
            'ver_meus_pedidos' => true,
            'criar_pedido' => true,
            'solicitar_edicao' => true
        ));
        
        // Role: Programador
        add_role('programador_bordados', 'Programador Bordados', array(
            'read' => true,
            'ver_trabalhos_atribuidos' => true,
            'entregar_trabalho' => true,
            'definir_preco' => true
        ));
        
        // Role: Revisor (NOVO)
        add_role('revisor_bordados', 'Revisor Bordados', array(
            'read' => true,
            'ver_trabalhos_revisao' => true,
            'aprovar_trabalho' => true,
            'solicitar_acertos' => true,
            'editar_arquivos' => true,
            'ajustar_preco' => true
        ));
        
        // Role: Embaixador (NOVO)
        add_role('embaixador_bordados', 'Embaixador', array(
            'read' => true,
            'ver_indicados' => true,
            'ver_comissoes' => true
        ));
        
        // Role: Assistente (v3.2)
        add_role('assistente_bordados', 'Assistente', array(
            'read' => true,
            'ver_todos_pedidos' => true,
            'atribuir_pedidos' => true,
            'editar_pedidos' => true,
            'cadastrar_clientes' => true,
            'editar_clientes' => true,
            'ver_precos' => true
        ));
    }

    /**
     * Enqueue scripts - MODULARIZADO v2.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');

        // CSS principal
        wp_enqueue_style('bordados-css', BORDADOS_PLUGIN_URL . 'assets/bordados.css', array(), '2.0');
        
        // CSS modular (NOVO - extraído dos arquivos PHP na Fase 2)
        wp_enqueue_style('bordados-modules-css', BORDADOS_PLUGIN_URL . 'assets/bordados-modules.css', array(), '1.0');

        // JavaScript principal
        wp_enqueue_script(
            'bordados-js',
            BORDADOS_PLUGIN_URL . 'assets/bordados-main.js',
            array('jquery'),
            '2.0',
            true
        );

        // JavaScript do Revisor/Edição (extraído na Fase 1)
        wp_enqueue_script(
            'bordados-revisor',
            BORDADOS_PLUGIN_URL . 'assets/bordados-revisor.js',
            array('jquery'),
            '1.0',
            true
        );

        // Localizar variáveis AJAX para ambos os scripts
        wp_localize_script('bordados-js', 'bordados_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bordados_nonce')
        ));

        wp_localize_script('bordados-revisor', 'bordados_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bordados_nonce')
        ));
    }

    /**
     * Proteção de páginas
     */
    public function proteger_paginas() {
        if (is_page()) {
            global $post;
            $slug = $post->post_name;
            $user = wp_get_current_user();

            // Páginas protegidas
            // Páginas protegidas
		$paginas_clientes = array('meus-pedidos', 'novo-pedido');
		$paginas_programadores = array('painel-programador', 'meus-trabalhos');
		$paginas_revisores = array('painel-revisor');
		$paginas_embaixadores = array('dashboard-embaixador');
		$paginas_assistentes = array('painel-assistente');
		$paginas_admin = array('admin-pedidos');
		$todas_protegidas = array_merge($paginas_clientes, $paginas_programadores, $paginas_revisores, $paginas_embaixadores, $paginas_assistentes, $paginas_admin);

            if (in_array($slug, $todas_protegidas)) {
                if (!is_user_logged_in()) {
                    wp_redirect(wp_login_url(get_permalink()));
                    exit;
                }

                // Verificar permissões específicas
                if (in_array($slug, $paginas_clientes)) {
                    if (!in_array('cliente_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
                        wp_redirect(site_url('/acesso-negado/'));
                        exit;
                    }
                }

                if (in_array($slug, $paginas_programadores)) {
                    if (!in_array('programador_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
                        wp_redirect(site_url('/acesso-negado/'));
                        exit;
                    }
                }

                if (in_array($slug, $paginas_admin)) {
                    if (!current_user_can('manage_options')) {
                        wp_redirect(site_url('/acesso-negado/'));
                        exit;
                    }
                }
		if (in_array($slug, $paginas_revisores)) {
		    if (!in_array('revisor_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
		        wp_redirect(site_url('/acesso-negado/'));
		        exit;
		    }
		}
		if (in_array($slug, $paginas_embaixadores)) {
		    if (!in_array('embaixador_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
		        wp_redirect(site_url('/acesso-negado/'));
		        exit;
		    }
		}
		if (in_array($slug, $paginas_assistentes)) {
		    if (!in_array('assistente_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
		        wp_redirect(site_url('/acesso-negado/'));
		        exit;
		    }
		}
            }
        }
    }

    /**
     * Configurar admin bar
     */
    public function configurar_admin_bar() {
        if (!is_user_logged_in()) return;

        $user = wp_get_current_user();

 	       // Remover admin bar para clientes, programadores e assistentes (manter para admin)
        if (in_array('cliente_bordados', $user->roles) || in_array('programador_bordados', $user->roles) || in_array('assistente_bordados', $user->roles)) {
            // Método 1: show_admin_bar
            show_admin_bar(false);

            // Método 2: Filter backup
            add_filter('show_admin_bar', '__return_false');

            // Método 3: CSS forçado (garantia)
            add_action('wp_head', array($this, 'css_remover_admin_bar'));

            // Método 4: Remover do perfil
            add_action('personal_options', array($this, 'esconder_opcao_admin_bar'));
        }
    }

    /**
     * CSS para forçar remoção da admin bar - MODULARIZADO v2.0
     * Agora usa classe CSS do arquivo bordados-modules.css
     */
    public function css_remover_admin_bar() {
        // Adicionar classe ao body para ativar CSS externo
        add_filter('body_class', function($classes) {
            $classes[] = 'bordados-hide-admin-bar';
            return $classes;
        });
    }

    /**
     * Esconder opção no perfil do usuário - MODULARIZADO v2.0
     * CSS movido para bordados-modules.css
     */
    public function esconder_opcao_admin_bar() {
        // CSS já está no arquivo externo via classe .bordados-hide-admin-bar
    }
}

// Inicializar o plugin
new SistemaBordadosSimples();

// Forçar shortcodes sempre
add_action('init', function() {
    add_filter('the_content', 'do_shortcode', 11);
});

/**
 * Redirecionamento inteligente após login - MANTIDO ORIGINAL
 */
if (!function_exists('bordados_redirecionamento_inteligente')) {
    function bordados_redirecionamento_inteligente($redirect_to, $request, $user) {
        // DEBUG: Log para ver se a função está sendo chamada
        error_log('=== REDIRECIONAMENTO CHAMADO ===');
        error_log('User: ' . print_r($user, true));

        // Verificar se é um objeto user válido
        if (!is_wp_error($user) && isset($user->roles) && is_array($user->roles)) {
            error_log('Roles do usuário: ' . print_r($user->roles, true));

            // Se é programador
            if (in_array('programador_bordados', $user->roles)) {
                error_log('REDIRECIONANDO PROGRAMADOR para /painel-programador/');
                return site_url('/painel-programador/');
            }

            // Se é revisor
            if (in_array('revisor_bordados', $user->roles)) {
                error_log('REDIRECIONANDO REVISOR para /painel-revisor/');
                return site_url('/painel-revisor/');
            }

            // Se é embaixador
            if (in_array('embaixador_bordados', $user->roles)) {
                error_log('REDIRECIONANDO EMBAIXADOR para /painel-embaixador/');
                return site_url('/painel-embaixador/');
            }

            // Se é assistente
            if (in_array('assistente_bordados', $user->roles)) {
                error_log('REDIRECIONANDO ASSISTENTE para /painel-assistente/');
                return site_url('/painel-assistente/');
            }

            // Se é cliente
            if (in_array('cliente_bordados', $user->roles)) {
                error_log('REDIRECIONANDO CLIENTE para /meus-pedidos/');
                return site_url('/meus-pedidos/');
            }

            // Se é administrador
            if (in_array('administrator', $user->roles)) {
                error_log('REDIRECIONANDO ADMIN para /admin-pedidos/');
                return site_url('/admin-pedidos/');
            }
        }

        error_log('Sem redirecionamento - retornando padrão: ' . $redirect_to);
        return $redirect_to;
    }

    // Adicionar o filtro
    add_filter('login_redirect', 'bordados_redirecionamento_inteligente', 10, 3);
}

/**
 * Login WordPress shortcode - MANTIDO ORIGINAL
 */
function bordados_login_wordpress() {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        if (in_array('programador_bordados', $user->roles)) {
            return '<p>Você já está logado! <a href="' . esc_url(site_url('/painel-programador/')) . '">Ir para Painel</a></p>';
        }
        return '<p>Você já está logado! <a href="' . esc_url(site_url('/meus-pedidos/')) . '">Ver pedidos</a></p>';
    }

    return wp_login_form(array('echo' => false, 'redirect' => home_url($_SERVER['REQUEST_URI'])));
}
add_shortcode('bordados_login_wp', 'bordados_login_wordpress');

/**
 * ======================================
 * JAVASCRIPT MODULARIZADO - v2.0
 * ======================================
 * 
 * O JavaScript que estava inline neste arquivo foi extraído para:
 * - assets/bordados-revisor.js (Sistema Toast, funções do revisor, edição)
 * 
 * Os scripts são carregados via wp_enqueue_script no método enqueue_scripts()
 * da classe SistemaBordadosSimples.
 */

/**
 * ======================================
 * LAYOUT CUSTOMIZADO - Header/Footer Limpo
 * ======================================
 */

/**
 * Verificar se é uma página do sistema de bordados
 */
function bordados_is_system_page() {
    if (!is_page()) return false;
    
    $system_slugs = array(
        'meus-pedidos',
        'novo-pedido',
        'meu-perfil',
        'painel-programador',
        'painel-programador-novo',
        'painel-revisor',
        'painel-assistente',
        'admin-pedidos',
        'gerenciar-pedidos',
        'dashboard-embaixador',
        'area-cliente',
        'register',
        'login'
    );
    
    global $post;
    return in_array($post->post_name, $system_slugs);
}

/**
 * Verificar se é página de autenticação (login/register) - usa layout próprio
 */
function bordados_is_auth_page() {
    if (!is_page()) return false;
    
    global $post;
    return in_array($post->post_name, array('register', 'login'));
}

/**
 * Adicionar CSS para esconder header/footer do tema e mostrar layout limpo
 */
function bordados_custom_layout_css() {
    if (!bordados_is_system_page()) return;
    ?>
    <style>
        /* Esconder header e footer do tema */
        header.wp-block-template-part,
        .wp-block-template-part[data-type="header"],
        footer.wp-block-template-part,
        .wp-block-template-part[data-type="footer"],
        header#masthead,
        footer#colophon,
        .site-header,
        .site-footer,
        .wp-site-blocks > header,
        .wp-site-blocks > footer {
            display: none !important;
        }
        
        /* Header customizado Puncher */
        .puncher-custom-header {
            background: #ffffff;
            padding: 15px 30px;
            text-align: center;
            border-bottom: 1px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .puncher-custom-header img {
            max-height: 50px;
            width: auto;
        }
        
        /* Footer customizado */
        .puncher-custom-footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
            margin-top: 40px;
            font-size: 14px;
            color: #666;
        }
        
        .puncher-custom-footer a {
            color: #0073aa;
            text-decoration: none;
            font-weight: 500;
        }
        
        .puncher-custom-footer a:hover {
            text-decoration: underline;
        }
        
        /* Ajustar conteúdo principal */
        .wp-site-blocks {
            padding-top: 0 !important;
        }
        
        /* Remover admin bar margin quando logado */
        .admin-bar .puncher-custom-header {
            top: 32px;
        }
        
        @media screen and (max-width: 782px) {
            .admin-bar .puncher-custom-header {
                top: 46px;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'bordados_custom_layout_css');

/**
 * Adicionar header customizado no início do body
 */
function bordados_custom_header() {
    if (!bordados_is_system_page()) return;
    if (bordados_is_auth_page()) return; // Auth pages have their own layout
    ?>
    <div class="puncher-custom-header">
        <a href="<?php echo esc_url(site_url('/')); ?>">
            <img src="https://puncher.com/images/logo.png" alt="Puncher">
        </a>
    </div>
    <?php
}
add_action('wp_body_open', 'bordados_custom_header');

/**
 * Adicionar footer customizado
 */
function bordados_custom_footer() {
    if (!bordados_is_system_page()) return;
    if (bordados_is_auth_page()) return; // Auth pages have their own layout
    ?>
    <div class="puncher-custom-footer">
        made by <a href="https://zingovia.com/" target="_blank" rel="noopener">Zingovia</a>
    </div>
    <?php
}
add_action('wp_footer', 'bordados_custom_footer', 5);

/**
 * ======================================
 * DIAGNÓSTICO - Verificar atribuição automática
 * ======================================
 * Uso: Criar página com shortcode [bordados_diagnostico]
 * Remover após resolver o problema!
 */
function bordados_diagnostico_shortcode() {
    if (!current_user_can('manage_options')) {
        return '<p>Acesso restrito a administradores.</p>';
    }
    
    ob_start();
    ?>
    <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; font-family: monospace;">
        <h2>🔧 Diagnóstico do Sistema de Atribuição</h2>
        
        <h3>📋 Verificar Cliente Específico</h3>
        <form method="GET" style="margin-bottom: 20px;">
            <input type="hidden" name="page_id" value="<?php echo get_the_ID(); ?>">
            <label>ID do Cliente: 
                <input type="number" name="cliente_id" value="<?php echo isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : ''; ?>" style="width: 100px;">
            </label>
            <button type="submit" class="button">Verificar</button>
        </form>
        
        <?php
        if (isset($_GET['cliente_id']) && !empty($_GET['cliente_id'])) {
            $cliente_id = intval($_GET['cliente_id']);
            $cliente = get_userdata($cliente_id);
            
            if (!$cliente) {
                echo '<p style="color: red;">❌ Cliente ID ' . $cliente_id . ' não encontrado!</p>';
            } else {
                // Buscar metadados
                $programador_padrao = get_user_meta($cliente_id, 'programador_padrao', true);
                $atribuicao_automatica = get_user_meta($cliente_id, 'atribuicao_automatica', true);
                
                echo '<div style="background: white; padding: 15px; border-radius: 5px; margin-bottom: 15px;">';
                echo '<h4>👤 Cliente: ' . esc_html($cliente->display_name) . ' (ID: ' . $cliente_id . ')</h4>';
                echo '<p><strong>Email:</strong> ' . esc_html($cliente->user_email) . '</p>';
                echo '<p><strong>Roles:</strong> ' . implode(', ', $cliente->roles) . '</p>';
                echo '</div>';
                
                echo '<div style="background: white; padding: 15px; border-radius: 5px; margin-bottom: 15px;">';
                echo '<h4>⚙️ Configurações de Atribuição</h4>';
                
                // Programador Padrão
                echo '<p><strong>programador_padrao (raw):</strong> ';
                var_dump($programador_padrao);
                echo '</p>';
                
                if (!empty($programador_padrao)) {
                    $prog = get_userdata($programador_padrao);
                    if ($prog) {
                        echo '<p style="color: green;">✅ Programador Padrão: ' . esc_html($prog->display_name) . ' (ID: ' . $programador_padrao . ')</p>';
                        
                        // Verificar se programador está ativo
                        $prog_ativo = get_user_meta($programador_padrao, 'programador_ativo', true);
                        echo '<p><strong>programador_ativo (raw):</strong> ';
                        var_dump($prog_ativo);
                        echo '</p>';
                        
                        if ($prog_ativo === 'yes' || empty($prog_ativo)) {
                            echo '<p style="color: green;">✅ Programador está ATIVO</p>';
                        } else {
                            echo '<p style="color: red;">❌ Programador está INATIVO (valor: ' . esc_html($prog_ativo) . ')</p>';
                        }
                    } else {
                        echo '<p style="color: red;">❌ Programador ID ' . $programador_padrao . ' não encontrado no sistema!</p>';
                    }
                } else {
                    echo '<p style="color: orange;">⚠️ Cliente NÃO tem programador padrão definido</p>';
                }
                
                // Atribuição Automática
                echo '<p><strong>atribuicao_automatica (raw):</strong> ';
                var_dump($atribuicao_automatica);
                echo '</p>';
                
                echo '</div>';
                
                // Simulação
                echo '<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 15px;">';
                echo '<h4>🎯 O que aconteceria ao criar um pedido?</h4>';
                
                if (!empty($programador_padrao)) {
                    $prog_ativo = get_user_meta($programador_padrao, 'programador_ativo', true);
                    if ($prog_ativo === 'yes' || empty($prog_ativo)) {
                        $prog = get_userdata($programador_padrao);
                        echo '<p style="color: green; font-size: 16px;">✅ <strong>DEVERIA ATRIBUIR AUTOMATICAMENTE para ' . esc_html($prog->display_name) . '</strong></p>';
                    } else {
                        echo '<p style="color: red;">❌ Programador padrão está INATIVO - pedido ficaria como "novo"</p>';
                    }
                } elseif ($atribuicao_automatica === 'yes') {
                    echo '<p style="color: blue;">🔄 Sistema buscaria programador com menos trabalhos</p>';
                } else {
                    echo '<p style="color: orange;">⏳ Pedido ficaria como "novo" aguardando atribuição manual</p>';
                }
                
                echo '</div>';
                
                // Últimos pedidos
                global $wpdb;
                $ultimos_pedidos = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, nome_bordado, status, programador_id, data_criacao 
                     FROM pedidos_basicos 
                     WHERE cliente_id = %d 
                     ORDER BY id DESC 
                     LIMIT 5",
                    $cliente_id
                ));
                
                if ($ultimos_pedidos) {
                    echo '<div style="background: white; padding: 15px; border-radius: 5px;">';
                    echo '<h4>📦 Últimos 5 Pedidos deste Cliente</h4>';
                    echo '<table style="width: 100%; border-collapse: collapse;">';
                    echo '<tr style="background: #f0f0f0;"><th>ID</th><th>Nome</th><th>Status</th><th>Programador ID</th><th>Data</th></tr>';
                    foreach ($ultimos_pedidos as $p) {
                        $prog_nome = $p->programador_id ? get_userdata($p->programador_id)->display_name ?? 'N/A' : 'Nenhum';
                        echo '<tr style="border-bottom: 1px solid #ddd;">';
                        echo '<td>#' . $p->id . '</td>';
                        echo '<td>' . esc_html($p->nome_bordado) . '</td>';
                        echo '<td>' . esc_html($p->status) . '</td>';
                        echo '<td>' . ($p->programador_id ?: 'NULL') . ' (' . esc_html($prog_nome) . ')</td>';
                        echo '<td>' . $p->data_criacao . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '</div>';
                }
            }
        }
        ?>
        
        <hr style="margin: 20px 0;">
        
        <h3>👥 Todos os Programadores</h3>
        <?php
        $programadores = get_users(array('role__in' => array('programador_bordados', 'administrator')));
        echo '<table style="width: 100%; border-collapse: collapse; background: white;">';
        echo '<tr style="background: #f0f0f0;"><th>ID</th><th>Nome</th><th>Email</th><th>Ativo?</th></tr>';
        foreach ($programadores as $prog) {
            $ativo = get_user_meta($prog->ID, 'programador_ativo', true);
            $ativo_display = ($ativo === 'yes' || empty($ativo)) ? '✅ Sim' : '❌ Não (' . $ativo . ')';
            echo '<tr style="border-bottom: 1px solid #ddd;">';
            echo '<td>' . $prog->ID . '</td>';
            echo '<td>' . esc_html($prog->display_name) . '</td>';
            echo '<td>' . esc_html($prog->user_email) . '</td>';
            echo '<td>' . $ativo_display . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        ?>
        
        <hr style="margin: 20px 0;">
        <p style="color: #999;">⚠️ Remova este shortcode após resolver o problema!</p>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bordados_diagnostico', 'bordados_diagnostico_shortcode');

?>
