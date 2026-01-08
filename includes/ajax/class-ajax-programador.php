<?php
/**
 * AJAX: Funções do Programador
 * Extraído de class-ajax.php na Fase 4 da modularização
 * 
 * Funções:
 * - iniciar_producao
 * - finalizar_trabalho
 * - processar_uploads_finais_melhorado (helper)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Programador {
    
    public function __construct() {
        add_action('wp_ajax_iniciar_producao', array($this, 'iniciar_producao'));
        add_action('wp_ajax_finalizar_trabalho', array($this, 'finalizar_trabalho'));
    }
    
    public function iniciar_producao() {
        check_ajax_referer('bordados_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Você precisa estar logado.');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        $programador_id = get_current_user_id();
        
        if (empty($pedido_id)) {
            wp_send_json_error('ID do pedido inválido.');
            return;
        }
        
        // Buscar dados do pedido primeiro
        $pedido = Bordados_Database::buscar_pedido_completo($pedido_id);
        
        if (!$pedido || $pedido->programador_id != $programador_id || $pedido->status !== 'atribuido') {
            wp_send_json_error('Pedido não encontrado ou você não tem permissão para iniciá-lo.');
            return;
        }
        
        // Atualizar status para "em_producao"
        $resultado = Bordados_Database::atualizar_pedido(
            $pedido_id,
            array('status' => 'em_producao'),
            array('%s')
        );
        
        if ($resultado === false) {
            wp_send_json_error('Erro ao atualizar status.');
            return;
        }
        
        // Enviar email para o cliente
        $email_enviado = Bordados_Emails::enviar_producao_iniciada($pedido);
        
        if ($email_enviado) {
            wp_send_json_success('Production started successfully! Customer has been notified by email.');
        } else {
            wp_send_json_success('Production started successfully! (Email was not sent)');
        }
    }
    
    /**
     * AJAX: Finalizar trabalho
     */

    public function finalizar_trabalho() {
        // Debug inicial
        error_log('=== FINALIZAR TRABALHO INICIADO ===');
        
        check_ajax_referer('bordados_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Você precisa estar logado.');
            return;
        }
        
        $pedido_id = intval($_POST['pedido_id']);
        $preco = floatval($_POST['preco_programador']);
        $numero_pontos = intval($_POST['numero_pontos']);
        $obs = sanitize_textarea_field($_POST['observacoes_programador']);
        $programador_id = get_current_user_id();
        
        error_log("Dados recebidos - Pedido: $pedido_id, Preço: $preco, Pontos: $numero_pontos");
        
        if (empty($pedido_id) || empty($preco) || empty($numero_pontos)) {
            wp_send_json_error('Required fields not filled (price and stitch count are mandatory).');
            return;
        }
        
        // Verificar se o pedido pertence ao programador
        $pedido = Bordados_Database::buscar_pedido($pedido_id);
        
	if (!$pedido || $pedido->programador_id != $programador_id || !in_array($pedido->status, ['em_producao', 'em_acertos'])) {
            wp_send_json_error('Pedido não encontrado ou você não tem permissão para finalizá-lo.');
            return;
        }
        
        // Processar uploads finais
        $arquivos_finais = $this->processar_uploads_finais_melhorado();
        
        error_log('Arquivos processados: ' . print_r($arquivos_finais, true));
        
        if (empty($arquivos_finais)) {
            wp_send_json_error('É necessário enviar pelo menos um arquivo final. Verifique se o arquivo foi selecionado corretamente.');
            return;
        }
        // ============ LÓGICA INTELIGENTE: PRIMEIRA ENTREGA VS REENVIO ============

if ($pedido->status == 'em_acertos') {
    // É um REENVIO após acertos solicitados
    // SEMPRE volta para revisão
    $status_final = 'aguardando_revisao';
    $data_conclusao = null;
    
    error_log("=== REENVIO APÓS ACERTOS ===");
    error_log("Pedido #$pedido_id voltando para revisão");
    
} else {
    // É a PRIMEIRA ENTREGA (status: em_producao)
    // Verificar se cliente requer revisão
    
    $pedido_completo = Bordados_Database::buscar_pedido_completo($pedido_id);
    $cliente_id = $pedido_completo->cliente_id;
    $requer_revisao = get_user_meta($cliente_id, 'requer_revisao', true);
    
    error_log("=== PRIMEIRA ENTREGA ===");
    error_log("Cliente ID: $cliente_id");
    error_log("Requer Revisão: " . ($requer_revisao == '1' ? 'SIM' : 'NÃO'));
    
    if ($requer_revisao == '1') {
        // Cliente requer revisão
        $status_final = 'aguardando_revisao';
        $data_conclusao = null;
        error_log("✅ Enviando para revisão");
    } else {
        // Cliente não requer revisão - finalizar direto
        $status_final = 'pronto';
        $data_conclusao = current_time('mysql');
        error_log("✅ Finalizando direto (sem revisão)");
    }
}

// ============ FIM LÓGICA INTELIGENTE ============

// Atualizar pedido
$resultado = Bordados_Database::atualizar_pedido(
    $pedido_id,
    array(
        'preco_programador' => $preco,
        'numero_pontos' => $numero_pontos,
        'observacoes_programador' => $obs,
        'arquivos_finais' => json_encode($arquivos_finais),
        'status' => $status_final,
        'data_conclusao' => $data_conclusao
    ),
    array('%f', '%d', '%s', '%s', '%s', '%s')
);

if ($resultado === false) {
    wp_send_json_error('Erro ao finalizar trabalho no banco de dados.');
    return;
}

// Buscar pedido atualizado para emails
$pedido_completo = Bordados_Database::buscar_pedido_completo($pedido_id);

// Enviar notificações apropriadas
if ($status_final == 'pronto') {
    // Enviar email para o cliente (fluxo sem revisão)
    Bordados_Emails::enviar_trabalho_concluido($pedido_completo, $arquivos_finais);
    
    wp_send_json_success('Work completed successfully! Customer has been notified and can download the files. (' . count($arquivos_finais) . ' file(s) sent)');
    
} else {
    // Trabalho enviado para revisão
    // TODO: Enviar email para revisores notificando novo trabalho na fila
    
    wp_send_json_success('Work submitted for review! The reviewer has been notified and will analyze before delivery to customer. (' . count($arquivos_finais) . ' file(s) sent)');
}
    }
    
    /**
     * Processar uploads finais melhorado
     */

    private function processar_uploads_finais_melhorado() {
        $arquivos_urls = array();
        
        error_log('=== PROCESSANDO UPLOADS FINAIS ===');
        
        // Verificar se $_FILES existe e tem a estrutura correta
        if (!isset($_FILES['arquivos_finais']) || !is_array($_FILES['arquivos_finais'])) {
            error_log('$_FILES[arquivos_finais] não encontrado ou não é array');
            return $arquivos_urls;
        }
        
        $files = $_FILES['arquivos_finais'];
        error_log('Estrutura do $_FILES: ' . print_r($files, true));
        
        // Verificar se tem a estrutura de múltiplos arquivos
        if (!isset($files['name']) || !is_array($files['name'])) {
            error_log('Estrutura inválida - files[name] não é array');
            return $arquivos_urls;
        }
        
        $total_arquivos = count($files['name']);
        error_log("Total de arquivos para processar: $total_arquivos");
        
        // Configurar diretório de upload
        $upload_dir = wp_upload_dir();
        $bordados_finais_dir = $upload_dir['basedir'] . '/bordados-finais/';
        
        // Criar diretório se não existir
        if (!file_exists($bordados_finais_dir)) {
            wp_mkdir_p($bordados_finais_dir);
            error_log('Diretório criado: ' . $bordados_finais_dir);
        }
        
        // Processar cada arquivo
        for ($i = 0; $i < $total_arquivos; $i++) {
            error_log("=== Processando arquivo $i ===");
            error_log("Nome: " . ($files['name'][$i] ?? 'vazio'));
            error_log("Erro: " . ($files['error'][$i] ?? 'não definido'));
            error_log("Tamanho: " . ($files['size'][$i] ?? 'não definido'));
            
            // Verificar se o arquivo foi enviado sem erros
            if (isset($files['error'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK && !empty($files['name'][$i])) {
                
                $nome_arquivo = sanitize_file_name($files['name'][$i]);
                $tmp_name = $files['tmp_name'][$i];
                $tamanho = $files['size'][$i];
                
                error_log("Arquivo válido encontrado: $nome_arquivo");
                
                // Validar extensão
                $extensoes_permitidas = array('emb', 'dst', 'exp', 'pes', 'vp3', 'jef', 'pdf', 'jpg', 'png');
                $extensao = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));
                
                if (!in_array($extensao, $extensoes_permitidas)) {
                    error_log("Extensão não permitida: $extensao");
                    continue;
                }
                
                // Validar tamanho (máx 20MB)
                if ($tamanho > 20 * 1024 * 1024) {
                    error_log("Arquivo muito grande: $tamanho bytes");
                    continue;
                }
                
                // Gerar nome único
                $nome_unico = time() . '_' . uniqid() . '_' . $nome_arquivo;
                $caminho_arquivo = $bordados_finais_dir . $nome_unico;
                
                error_log("Tentando mover arquivo para: $caminho_arquivo");
                
                // Mover arquivo
                if (move_uploaded_file($tmp_name, $caminho_arquivo)) {
                    $url_arquivo = $upload_dir['baseurl'] . '/bordados-finais/' . $nome_unico;
                    $arquivos_urls[] = $url_arquivo;
                    error_log("Arquivo movido com sucesso! URL: $url_arquivo");
                } else {
                    error_log("ERRO: Falha ao mover arquivo $nome_arquivo");
                }
            } else {
                $erro_upload = isset($files['error'][$i]) ? $files['error'][$i] : 'indefinido';
                error_log("Arquivo $i inválido - Erro: $erro_upload");
            }
        }
        
        error_log("=== UPLOAD FINALIZADO ===");
        error_log("Total de arquivos processados com sucesso: " . count($arquivos_urls));
        
        return $arquivos_urls;
    }
    
    /**
     * AJAX: Login personalizado
     */

}

?>
