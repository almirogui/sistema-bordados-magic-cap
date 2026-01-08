<?php
/**
 * AJAX: Funções de Edição de Pedidos
 * Extraído de class-ajax.php na Fase 4 da modularização
 * 
 * Funções:
 * - solicitar_edicao
 * - processar_uploads_edicao (helper)
 * - buscar_historico_versoes
 * - comparar_versoes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Edicao {
    
    public function __construct() {
        add_action('wp_ajax_solicitar_edicao', array($this, 'solicitar_edicao'));
        add_action('wp_ajax_buscar_historico_versoes', array($this, 'buscar_historico_versoes'));
        add_action('wp_ajax_comparar_versoes', array($this, 'comparar_versoes'));
    }
    
public function solicitar_edicao() {
    check_ajax_referer('bordados_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Você precisa estar logado.');
        return;
    }
    
    $cliente_id = get_current_user_id();
    $pedido_original_id = intval($_POST['pedido_id']);
    $motivo_edicao = sanitize_textarea_field($_POST['motivo_edicao']);
    
    error_log("=== SOLICITAÇÃO DE EDIÇÃO ===");
    error_log("Pedido Original: #$pedido_original_id");
    error_log("Cliente: #$cliente_id");
    
    // Validações
    if (empty($pedido_original_id) || empty($motivo_edicao)) {
        wp_send_json_error('Dados obrigatórios não preenchidos.');
        return;
    }
    
    // Buscar pedido original
    $pedido_original = Bordados_Database::buscar_pedido_completo($pedido_original_id);
    
    if (!$pedido_original || $pedido_original->cliente_id != $cliente_id) {
        wp_send_json_error('Pedido não encontrado ou você não tem permissão.');
        return;
    }
    
    if ($pedido_original->status !== 'pronto') {
        wp_send_json_error('Apenas pedidos prontos podem ser editados.');
        return;
    }
    
    // Contar edições existentes
    global $wpdb;
    $total_edicoes = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM pedidos_basicos WHERE pedido_pai_id = %d",
        $pedido_original_id
    ));
    
    error_log("Total de edições existentes: $total_edicoes");
    
    // Determinar se é gratuita (primeira edição = gratuita)
    $edicao_gratuita = ($total_edicoes == 0) ? 1 : 0;
    $nova_versao = $total_edicoes + 2; // Original é v1, primeira edição é v2
    
    // Processar novos arquivos (se houver)
    $novos_arquivos = $this->processar_uploads_edicao();
    
    // Se não houver novos arquivos, usar os originais
    if (empty($novos_arquivos)) {
        $novos_arquivos = json_decode($pedido_original->arquivos_cliente, true);
    }
    
    // Criar pedido de edição
    $dados_edicao = array(
        'pedido_pai_id' => $pedido_original_id,
        'versao' => $nova_versao,
        'tipo_pedido' => 'edicao',
        'edicao_gratuita' => $edicao_gratuita,
        'motivo_edicao' => $motivo_edicao,
        'cliente_id' => $cliente_id,
        'programador_id' => $pedido_original->programador_id, // Mesmo programador
        'nome_bordado' => $pedido_original->nome_bordado . " (v$nova_versao)",
        'prazo_entrega' => $pedido_original->prazo_entrega,
        'largura' => $pedido_original->largura,
        'altura' => $pedido_original->altura,
        'unidade_medida' => $pedido_original->unidade_medida,
        'local_bordado' => $pedido_original->local_bordado,
        'tipo_tecido' => $pedido_original->tipo_tecido,
        'cores' => $pedido_original->cores,
        'observacoes' => "EDIÇÃO v$nova_versao: " . $motivo_edicao,
        'arquivos_cliente' => json_encode($novos_arquivos),
        'status' => 'atribuido', // Já atribuir ao programador
        'data_criacao' => current_time('mysql'),
        'data_atribuicao' => current_time('mysql')
    );
    
    $nova_edicao_id = Bordados_Database::criar_pedido($dados_edicao);
    
    if ($nova_edicao_id) {
        error_log("✅ Edição criada: #$nova_edicao_id (v$nova_versao)");
        
        $mensagem = "Edição solicitada com sucesso! ";
        $mensagem .= $edicao_gratuita ? "(Primeira edição GRATUITA)" : "(Edição será cobrada)";
        $mensagem .= " O programador foi notificado.";
        
        wp_send_json_success(array(
            'message' => $mensagem,
            'edicao_id' => $nova_edicao_id,
            'versao' => $nova_versao,
            'gratuita' => $edicao_gratuita
        ));
    } else {
        wp_send_json_error('Erro ao criar solicitação de edição.');
    }
}

/**
 * Processar uploads de arquivos de edição
 */

private function processar_uploads_edicao() {
    $arquivos_urls = array();
    
    if (!isset($_FILES['arquivos_edicao']) || !is_array($_FILES['arquivos_edicao'])) {
        return $arquivos_urls;
    }
    
    $files = $_FILES['arquivos_edicao'];
    
    if (!isset($files['name']) || !is_array($files['name'])) {
        return $arquivos_urls;
    }
    
    $upload_dir = wp_upload_dir();
    $edicoes_dir = $upload_dir['basedir'] . '/bordados-edicoes/';
    
    if (!file_exists($edicoes_dir)) {
        wp_mkdir_p($edicoes_dir);
    }
    
    for ($i = 0; $i < count($files['name']); $i++) {
        if (isset($files['error'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK && !empty($files['name'][$i])) {
            
            $nome_arquivo = sanitize_file_name($files['name'][$i]);
            $tmp_name = $files['tmp_name'][$i];
            
            $extensoes_permitidas = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'ai', 'eps', 'svg');
            $extensao = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));
            
            if (!in_array($extensao, $extensoes_permitidas)) {
                continue;
            }
            
            $nome_unico = time() . '_edicao_' . uniqid() . '_' . $nome_arquivo;
            $caminho_arquivo = $edicoes_dir . $nome_unico;
            
            if (move_uploaded_file($tmp_name, $caminho_arquivo)) {
                $url_arquivo = $upload_dir['baseurl'] . '/bordados-edicoes/' . $nome_unico;
                $arquivos_urls[] = $url_arquivo;
            }
        }
    }
    
    return $arquivos_urls;
}
/**
 * AJAX: Buscar histórico de versões de um pedido
 */

public function buscar_historico_versoes() {
    check_ajax_referer('bordados_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Você precisa estar logado.');
        return;
    }
    
    $cliente_id = get_current_user_id();
    $pedido_pai_id = intval($_POST['pedido_pai_id']);
    
    if (empty($pedido_pai_id)) {
        wp_send_json_error('ID do pedido não informado.');
        return;
    }
    
    global $wpdb;
    
    // Buscar pedido original + todas as edições
    $versoes = $wpdb->get_results($wpdb->prepare("
        SELECT id, nome_bordado, tipo_pedido, versao, pedido_pai_id, 
               edicao_gratuita, motivo_edicao, status, data_criacao
        FROM pedidos_basicos
        WHERE (id = %d OR pedido_pai_id = %d) AND cliente_id = %d
        ORDER BY versao DESC
    ", $pedido_pai_id, $pedido_pai_id, $cliente_id));
    
    if (empty($versoes)) {
        wp_send_json_error('Nenhuma versão encontrada.');
        return;
    }
    
    // Formatar dados para o frontend
    $versoes_formatadas = array();
    
    foreach ($versoes as $v) {
        $versoes_formatadas[] = array(
            'id' => $v->id,
            'nome_bordado' => $v->nome_bordado,
            'tipo_pedido' => $v->tipo_pedido,
            'versao' => $v->versao,
            'edicao_gratuita' => $v->edicao_gratuita,
            'motivo_edicao' => $v->motivo_edicao,
            'status' => $v->status,
            'status_badge' => Bordados_Helpers::get_status_badge($v->status),
            'data_criacao_formatada' => Bordados_Helpers::formatar_data_hora($v->data_criacao)
        );
    }
    
    wp_send_json_success($versoes_formatadas);
}
/**
 * AJAX: Comparar duas versões
 */

public function comparar_versoes() {
    check_ajax_referer('bordados_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Você precisa estar logado.');
        return;
    }
    
    $versao_nova_id = intval($_POST['versao_nova']);
    $versao_antiga_id = intval($_POST['versao_antiga']);
    
    $nova = Bordados_Database::buscar_pedido_completo($versao_nova_id);
    $antiga = Bordados_Database::buscar_pedido_completo($versao_antiga_id);
    
    if (!$nova || !$antiga) {
        wp_send_json_error('Versões não encontradas.');
        return;
    }
    
    // Montar diferenças
    $diferencas = array();
    
    if ($nova->motivo_edicao) {
        $diferencas[] = '✏️ ' . $nova->motivo_edicao;
    }
    
    if ($nova->largura != $antiga->largura || $nova->altura != $antiga->altura) {
        $diferencas[] = '📏 Dimensões alteradas';
    }
    
    if ($nova->cores != $antiga->cores) {
        $diferencas[] = '🎨 Cores alteradas';
    }
    
    $dados = array(
        'nova' => array(
            'id' => $nova->id,
            'nome_bordado' => $nova->nome_bordado,
            'motivo_edicao' => $nova->motivo_edicao,
            'data_criacao_formatada' => Bordados_Helpers::formatar_data_hora($nova->data_criacao)
        ),
        'antiga' => array(
            'id' => $antiga->id,
            'nome_bordado' => $antiga->nome_bordado,
            'motivo_edicao' => $antiga->motivo_edicao ?? '',
            'data_criacao_formatada' => Bordados_Helpers::formatar_data_hora($antiga->data_criacao)
        ),
        'diferencas' => $diferencas
    );
    
    wp_send_json_success($dados);
}

/**
 * Buscar programador com menos trabalhos pendentes
 */

}

?>
