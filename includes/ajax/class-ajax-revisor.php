<?php
/**
 * AJAX: Funções do Revisor
 * 
 * Handlers AJAX para o painel do revisor:
 * - Iniciar revisão
 * - Aprovar trabalho
 * - Aprovar trabalho com arquivos revisados
 * - Solicitar acertos com upload de imagens
 * 
 * @package Sistema_Bordados
 * @since 3.2.1
 * @updated 2025-01-11 - CORRIGIDO: Nomenclatura de arquivos
 * 
 * =====================================================
 * PASSO 2 - SUBSTITUIR O ARQUIVO INTEIRO
 * =====================================================
 * 
 * Este arquivo substitui: includes/ajax/class-ajax-revisor.php
 * 
 * CORREÇÕES APLICADAS:
 * - Usa pathinfo() em vez de wp_check_filetype() para preservar extensões
 * - Nome do arquivo inclui nome_bordado do pedido
 * - Formato: NomeBordado-PedidoID-final.extensao
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Revisor {
    
    /**
     * Construtor - registra os handlers AJAX
     */
    public function __construct() {
        // Iniciar revisão
        add_action('wp_ajax_iniciar_revisao', array($this, 'iniciar_revisao'));
        
        // Aprovar trabalho (sem arquivos novos)
        add_action('wp_ajax_aprovar_trabalho', array($this, 'aprovar_trabalho'));
        
        // Aprovar trabalho com arquivos revisados
        add_action('wp_ajax_aprovar_trabalho_com_arquivos', array($this, 'aprovar_trabalho_com_arquivos'));
        add_action('wp_ajax_aprovar_trabalho_revisor', array($this, 'aprovar_trabalho_com_arquivos')); // Alias
        
        // Solicitar acertos (ambos os nomes para compatibilidade)
        add_action('wp_ajax_solicitar_acertos', array($this, 'solicitar_acertos'));
        add_action('wp_ajax_solicitar_acertos_revisor', array($this, 'solicitar_acertos'));
    }
    
    /**
     * Verificar permissões (revisor, assistente ou admin)
     */
    private function verificar_permissao() {
        $user = wp_get_current_user();
        $roles_permitidas = array('revisor_bordados', 'assistente_bordados', 'administrator');
        
        foreach ($roles_permitidas as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * AJAX: Iniciar revisão de um trabalho
     */
    public function iniciar_revisao() {
        error_log('=== AJAX: iniciar_revisao ===');
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            error_log('❌ Nonce inválido');
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            error_log('❌ Permissão negada');
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        $revisor_id = get_current_user_id();
        
        error_log("Pedido ID: $pedido_id, Revisor ID: $revisor_id");
        
        if (empty($pedido_id)) {
            wp_send_json_error('ID do pedido inválido.');
            return;
        }
        
        global $wpdb;
        $tabela = 'pedidos_basicos';
        
        // Buscar pedido
        $pedido = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id = %d", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // Verificar se está aguardando revisão
        if ($pedido->status !== 'aguardando_revisao') {
            wp_send_json_error('Este pedido não está aguardando revisão. Status atual: ' . $pedido->status);
            return;
        }
        
        // Atualizar para "em_revisao"
        $resultado = $wpdb->update(
            $tabela,
            array(
                'status' => 'em_revisao',
                'revisor_id' => $revisor_id,
                'data_inicio_revisao' => current_time('mysql')
            ),
            array('id' => $pedido_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        if ($resultado === false) {
            error_log('❌ Erro ao atualizar: ' . $wpdb->last_error);
            wp_send_json_error('Erro ao iniciar revisão: ' . $wpdb->last_error);
            return;
        }
        
        error_log('✅ Revisão iniciada com sucesso');
        wp_send_json_success('Revisão iniciada! Você agora pode revisar este trabalho.');
    }
    
    /**
     * AJAX: Aprovar trabalho (sem arquivos novos - usa os do programador)
     */
    public function aprovar_trabalho() {
        error_log('=== AJAX: aprovar_trabalho ===');
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        $revisor_id = get_current_user_id();
        
        if (empty($pedido_id)) {
            wp_send_json_error('ID do pedido inválido.');
            return;
        }
        
        global $wpdb;
        $tabela = 'pedidos_basicos';
        
        // Buscar pedido
        $pedido = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id = %d", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // Verificar se está em revisão
        if ($pedido->status !== 'em_revisao') {
            wp_send_json_error('Este pedido não está em revisão. Status atual: ' . $pedido->status);
            return;
        }
        
        // Atualizar para "pronto"
        $resultado = $wpdb->update(
            $tabela,
            array(
                'status' => 'pronto',
                'revisor_id' => $revisor_id,
                'data_conclusao' => current_time('mysql'),
                'data_fim_revisao' => current_time('mysql')
            ),
            array('id' => $pedido_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($resultado === false) {
            wp_send_json_error('Erro ao aprovar trabalho: ' . $wpdb->last_error);
            return;
        }
        
        // Enviar email para o cliente
        if (class_exists('Bordados_Emails')) {
            $pedido_atualizado = $wpdb->get_row($wpdb->prepare("
                SELECT p.*, c.user_email as cliente_email, c.display_name as cliente_nome
                FROM $tabela p
                LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
                WHERE p.id = %d
            ", $pedido_id));
            
            if ($pedido_atualizado) {
                Bordados_Emails::enviar_trabalho_concluido($pedido_atualizado, json_decode($pedido_atualizado->arquivos_finais, true) ?: array());
            }
        }
        
        wp_send_json_success('Trabalho aprovado e entregue ao cliente!');
    }
    
    /**
     * AJAX: Aprovar trabalho com arquivos revisados
     * 
     * =====================================================
     * FUNÇÃO CORRIGIDA - v3.3.0
     * =====================================================
     * 
     * Correções:
     * - Usa pathinfo() para preservar extensões .emb, .dst, etc.
     * - Nome do arquivo baseado no nome_bordado do pedido
     * - Formato: NomeBordado-PedidoID-final.extensao
     */
    public function aprovar_trabalho_com_arquivos() {
        error_log('=== AJAX: aprovar_trabalho_com_arquivos (CORRIGIDO v3.3.0) ===');
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        $revisor_id = get_current_user_id();
        $preco_final = isset($_POST['preco_final']) ? floatval($_POST['preco_final']) : 0;
        $obs_revisor = isset($_POST['obs_revisor_aprovacao']) ? sanitize_textarea_field($_POST['obs_revisor_aprovacao']) : '';
        
        if (empty($pedido_id)) {
            wp_send_json_error('ID do pedido inválido.');
            return;
        }
        
        global $wpdb;
        $tabela = 'pedidos_basicos';
        
        // Buscar pedido
        $pedido = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id = %d", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // =====================================================
        // INÍCIO DA CORREÇÃO - Processamento de Upload
        // =====================================================
        
        $arquivos_revisados = array();
        
        if (!empty($_FILES['arquivos_revisados']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $upload_dir = wp_upload_dir();
            $upload_path = $upload_dir['basedir'] . '/bordados-revisados/' . date('Y/m');
            
            if (!file_exists($upload_path)) {
                wp_mkdir_p($upload_path);
            }
            
            $files = $_FILES['arquivos_revisados'];
            
            // ✅ CORREÇÃO 1: Preparar nome do bordado para usar no arquivo
            $nome_bordado_sanitizado = '';
            if (!empty($pedido->nome_bordado)) {
                // Sanitizar nome do bordado para uso em nome de arquivo
                $nome_bordado_sanitizado = sanitize_file_name($pedido->nome_bordado);
                // Remover caracteres especiais extras e limitar tamanho
                $nome_bordado_sanitizado = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $nome_bordado_sanitizado);
                $nome_bordado_sanitizado = preg_replace('/-+/', '-', $nome_bordado_sanitizado); // Remove hífens duplicados
                $nome_bordado_sanitizado = trim($nome_bordado_sanitizado, '-');
                $nome_bordado_sanitizado = substr($nome_bordado_sanitizado, 0, 50); // Limitar a 50 caracteres
            }
            
            // Fallback se nome estiver vazio
            if (empty($nome_bordado_sanitizado)) {
                $nome_bordado_sanitizado = 'design';
            }
            
            error_log("📁 Nome bordado sanitizado: " . $nome_bordado_sanitizado);
            
            // ✅ CORREÇÃO 2: Lista de extensões de bordado permitidas
            $extensoes_bordado = array('emb', 'dst', 'exp', 'pes', 'vp3', 'jef', 'hus', 'pec', 'pcs', 'sew', 'xxx');
            $extensoes_imagem = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');
            $extensoes_documento = array('pdf', 'txt');
            $extensoes_permitidas = array_merge($extensoes_bordado, $extensoes_imagem, $extensoes_documento);
            
            // Contador para evitar nomes duplicados da mesma extensão
            $extensoes_usadas = array();
            
            for ($i = 0; $i < count($files['name']); $i++) {
                if (empty($files['name'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                $nome_original = $files['name'][$i];
                
                // ✅ CORREÇÃO 3: Usar pathinfo() em vez de wp_check_filetype()
                $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
                
                error_log("📎 Processando arquivo: $nome_original (extensão: $extensao)");
                
                // Verificar se extensão é permitida
                if (!in_array($extensao, $extensoes_permitidas)) {
                    error_log("❌ Extensão não permitida: $extensao");
                    continue;
                }
                
                // ✅ CORREÇÃO 4: Criar nome de arquivo significativo
                // Formato: NomeBordado-PedidoID-final.extensao
                
                // Verificar se já existe arquivo com mesma extensão
                if (isset($extensoes_usadas[$extensao])) {
                    $extensoes_usadas[$extensao]++;
                    $sufixo = '-' . $extensoes_usadas[$extensao];
                } else {
                    $extensoes_usadas[$extensao] = 1;
                    $sufixo = '';
                }
                
                $filename = $nome_bordado_sanitizado . '-' . $pedido_id . '-final' . $sufixo . '.' . $extensao;
                $destination = $upload_path . '/' . $filename;
                
                error_log("💾 Salvando como: $filename");
                
                if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                    $arquivos_revisados[] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $destination);
                    error_log("✅ Arquivo salvo com sucesso: $filename");
                } else {
                    error_log("❌ Erro ao mover arquivo: " . $files['tmp_name'][$i]);
                }
            }
        }
        
        // =====================================================
        // FIM DA CORREÇÃO - Processamento de Upload
        // =====================================================
        
        // Se não tiver novos arquivos, manter os originais
        $arquivos_finais_atuais = !empty($pedido->arquivos_finais) ? $pedido->arquivos_finais : '[]';
        if (!empty($arquivos_revisados)) {
            // Salvar os arquivos originais como backup (opcional)
            $backup_arquivos = json_decode($arquivos_finais_atuais, true) ?: array();
            
            // Atualizar com os novos arquivos revisados
            $arquivos_finais_atuais = json_encode($arquivos_revisados);
        }
        
        // Preparar dados para atualização
        $dados = array(
            'status' => 'pronto',
            'revisor_id' => $revisor_id,
            'data_conclusao' => current_time('mysql'),
            'data_fim_revisao' => current_time('mysql')
        );
        $formatos = array('%s', '%d', '%s', '%s');
        
        // Adicionar preço final se informado
        if ($preco_final > 0) {
            $dados['preco_final'] = $preco_final;
            $formatos[] = '%f';
        }
        
        // Adicionar observações do revisor se houver
        if (!empty($obs_revisor)) {
            $dados['obs_revisor'] = $obs_revisor;
            $formatos[] = '%s';
        }
        
        // Atualizar arquivos se houver novos
        if (!empty($arquivos_revisados)) {
            $dados['arquivos_finais'] = json_encode($arquivos_revisados);
            $formatos[] = '%s';
        }
        
        // Atualizar pedido
        $resultado = $wpdb->update(
            $tabela,
            $dados,
            array('id' => $pedido_id),
            $formatos,
            array('%d')
        );
        
        if ($resultado === false) {
            wp_send_json_error('Erro ao aprovar trabalho: ' . $wpdb->last_error);
            return;
        }
        
        // Enviar email para o cliente
        if (class_exists('Bordados_Emails')) {
            $pedido_atualizado = $wpdb->get_row($wpdb->prepare("
                SELECT p.*, c.user_email as cliente_email, c.display_name as cliente_nome
                FROM $tabela p
                LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
                WHERE p.id = %d
            ", $pedido_id));
            
            if ($pedido_atualizado) {
                Bordados_Emails::enviar_trabalho_concluido($pedido_atualizado, json_decode($pedido_atualizado->arquivos_finais, true) ?: array());
            }
        }
        
        $msg = 'Trabalho aprovado e entregue ao cliente!';
        if (!empty($arquivos_revisados)) {
            $msg .= ' (' . count($arquivos_revisados) . ' arquivo(s) revisado(s) enviado(s))';
        }
        
        wp_send_json_success($msg);
    }
    
    /**
     * AJAX: Solicitar acertos ao programador (com upload de imagens)
     */
    public function solicitar_acertos() {
        error_log('=== AJAX: solicitar_acertos ===');
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            wp_send_json_error('Verificação de segurança falhou.');
            return;
        }
        
        // Verificar permissão
        if (!$this->verificar_permissao()) {
            wp_send_json_error('Acesso negado.');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        
        // Aceitar ambos os campos para compatibilidade (obs_revisor ou observacoes_revisor)
        $obs_revisor = '';
        if (!empty($_POST['obs_revisor'])) {
            $obs_revisor = sanitize_textarea_field($_POST['obs_revisor']);
        } elseif (!empty($_POST['observacoes_revisor'])) {
            $obs_revisor = sanitize_textarea_field($_POST['observacoes_revisor']);
        }
        
        if (empty($pedido_id)) {
            wp_send_json_error('ID do pedido inválido.');
            return;
        }
        
        if (empty($obs_revisor)) {
            wp_send_json_error('Por favor, descreva os acertos necessários.');
            return;
        }
        
        global $wpdb;
        $tabela = 'pedidos_basicos';
        
        // Verificar se pedido existe e está em revisão
        $pedido = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id = %d", $pedido_id));
        
        if (!$pedido) {
            wp_send_json_error('Pedido não encontrado.');
            return;
        }
        
        // Processar upload de imagens
        $imagens_acertos = array();
        
        if (!empty($_FILES['imagens_acertos']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $upload_dir = wp_upload_dir();
            $upload_path = $upload_dir['basedir'] . '/bordados-acertos/' . date('Y/m');
            
            // Criar diretório se não existir
            if (!file_exists($upload_path)) {
                wp_mkdir_p($upload_path);
            }
            
            $files = $_FILES['imagens_acertos'];
            $max_files = 3;
            
            for ($i = 0; $i < min(count($files['name']), $max_files); $i++) {
                if (empty($files['name'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                // Verificar tipo de arquivo
                $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'application/pdf');
                $file_type = wp_check_filetype($files['name'][$i]);
                
                if (!in_array($files['type'][$i], $allowed_types)) {
                    continue;
                }
                
                // Gerar nome único
                $filename = 'acerto-' . $pedido_id . '-' . time() . '-' . ($i + 1) . '.' . $file_type['ext'];
                $destination = $upload_path . '/' . $filename;
                
                // Mover arquivo
                if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                    $imagens_acertos[] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $destination);
                }
            }
        }
        
        // Preparar dados para atualização
        $user = wp_get_current_user();
        $ciclos = intval($pedido->ciclos_acertos) + 1;
        
        $dados = array(
            'status'         => 'em_acertos',
            'obs_revisor'    => $obs_revisor,
            'ciclos_acertos' => $ciclos,
            'revisor_id'     => $user->ID
        );
        
        $formatos = array('%s', '%s', '%d', '%d');
        
        // Se houver imagens, salvar na coluna arquivos_extras como JSON
        if (!empty($imagens_acertos)) {
            // Buscar imagens existentes e adicionar as novas
            $arquivos_extras = !empty($pedido->arquivos_extras) ? json_decode($pedido->arquivos_extras, true) : array();
            
            // Estruturar por ciclo de acertos
            $arquivos_extras['acertos_ciclo_' . $ciclos] = $imagens_acertos;
            
            $dados['arquivos_extras'] = json_encode($arquivos_extras);
            $formatos[] = '%s';
        }
        
        // Atualizar pedido
        $resultado = $wpdb->update(
            $tabela,
            $dados,
            array('id' => $pedido_id),
            $formatos,
            array('%d')
        );
        
        if ($resultado === false) {
            wp_send_json_error('Erro ao solicitar acertos: ' . $wpdb->last_error);
            return;
        }
        
        // Enviar email para o programador
        if (class_exists('Bordados_Emails') && method_exists('Bordados_Emails', 'enviar_acertos_solicitados')) {
            $pedido_atualizado = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id = %d", $pedido_id));
            Bordados_Emails::enviar_acertos_solicitados($pedido_atualizado, $obs_revisor, $imagens_acertos);
        }
        
        $msg = 'Acertos solicitados com sucesso! O programador foi notificado.';
        if (!empty($imagens_acertos)) {
            $msg .= ' (' . count($imagens_acertos) . ' imagem(ns) anexada(s))';
        }
        
        wp_send_json_success($msg);
    }
}
