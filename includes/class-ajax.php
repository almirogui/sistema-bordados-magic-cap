<?php
/**
 * Classe para gerenciar requisições AJAX - MODULARIZADO v4.0
 * 
 * Este arquivo agora é um "loader" que carrega os arquivos
 * de AJAX individuais e instancia suas classes.
 * 
 * Arquivos de AJAX em includes/ajax/:
 * - class-ajax-cliente.php     → criar_pedido, buscar_arquivos_pedido
 * - class-ajax-admin.php       → atribuir_pedido
 * - class-ajax-programador.php → iniciar_producao, finalizar_trabalho
 * - class-ajax-revisor.php     → iniciar/aprovar/solicitar_acertos revisão
 * - class-ajax-edicao.php      → solicitar_edicao, historico, comparar
 * - class-ajax-auth.php        → login
 */

if (!defined('ABSPATH')) {
    exit;
}

// Carregar arquivos de AJAX
require_once BORDADOS_PLUGIN_PATH . 'includes/ajax/class-ajax-cliente.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/ajax/class-ajax-admin.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/ajax/class-ajax-programador.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/ajax/class-ajax-revisor.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/ajax/class-ajax-edicao.php';
require_once BORDADOS_PLUGIN_PATH . 'includes/ajax/class-ajax-auth.php';

class Bordados_Ajax {
    
    private $cliente;
    private $admin;
    private $programador;
    private $revisor;
    private $edicao;
    private $auth;
    
    public function __construct() {
        // Instanciar cada módulo AJAX
        $this->cliente = new Bordados_Ajax_Cliente();
        $this->admin = new Bordados_Ajax_Admin();
        $this->programador = new Bordados_Ajax_Programador();
        $this->revisor = new Bordados_Ajax_Revisor();
        $this->edicao = new Bordados_Ajax_Edicao();
        $this->auth = new Bordados_Ajax_Auth();
    }
    
    /**
     * Métodos proxy para compatibilidade retroativa
     * Caso algum código externo chame diretamente os métodos da classe principal
     */
    
    public function criar_pedido() {
        return $this->cliente->criar_pedido();
    }
    
    public function buscar_arquivos_pedido() {
        return $this->cliente->buscar_arquivos_pedido();
    }
    
    public function atribuir_pedido() {
        return $this->admin->atribuir_pedido();
    }
    
    public function iniciar_producao() {
        return $this->programador->iniciar_producao();
    }
    
    public function finalizar_trabalho() {
        return $this->programador->finalizar_trabalho();
    }
    
    public function iniciar_revisao() {
        return $this->revisor->iniciar_revisao();
    }
    
    public function aprovar_trabalho() {
        return $this->revisor->aprovar_trabalho();
    }
    
    public function aprovar_trabalho_com_arquivos() {
        return $this->revisor->aprovar_trabalho_com_arquivos();
    }
    
    public function solicitar_acertos() {
        return $this->revisor->solicitar_acertos();
    }
    
    public function solicitar_edicao() {
        return $this->edicao->solicitar_edicao();
    }
    
    public function buscar_historico_versoes() {
        return $this->edicao->buscar_historico_versoes();
    }
    
    public function comparar_versoes() {
        return $this->edicao->comparar_versoes();
    }
    
    public function login() {
        return $this->auth->login();
    }
}

?>
