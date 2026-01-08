<?php
/**
 * AJAX: Funções do Revisor
 * Extraído de class-ajax.php na Fase 4 da modularização
 * 
 * Funções:
 * - iniciar_revisao
 * - aprovar_trabalho
 * - solicitar_acertos
 * - aprovar_trabalho_com_arquivos
 * - processar_uploads_revisados (helper)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Revisor {
    
    public function __construct() {
        add_action('wp_ajax_iniciar_revisao', array($this, 'iniciar_revisao'));
        add_action('wp_ajax_aprovar_trabalho', array($this, 'aprovar_trabalho'));
        add_action('wp_ajax_aprovar_trabalho_com_arquivos', array($this, 'aprovar_trabalho_com_arquivos'));
        add_action('wp_ajax_solicitar_acertos', array($this, 'solicitar_acertos'));
        add_action('wp_ajax_aprovar_trabalho_revisor', array($this, 'aprovar_trabalho_revisor'));
    }
    
public function iniciar_revisao() {
    check_ajax_referer('bordados_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Você precisa estar logado.');
        return;
    }
    
    $user = wp_get_current_user();
    if (!in_array('revisor_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
        wp_send_json_error('Acesso restrito a revisores.');
        return;
    }
    
    $pedido_id = intval($_POST['pedido_id']);
    $revisor_id = $user->ID;
    
    if (empty($pedido_id)) {
        wp_send_json_error('ID do pedido inválido.');
        return;
    }
    
    // Verificar se o pedido existe e está aguardando revisão
    $pedido = Bordados_Database::buscar_pedido($pedido_id);
    
    if (!$pedido || $pedido->status !== 'aguardando_revisao') {
        wp_send_json_error('Pedido não encontrado ou não está aguardando revisão.');
        return;
    }
    
    // Atualizar status para em_revisao
    $resultado = Bordados_Database::atualizar_pedido(
        $pedido_id,
        array(
            'revisor_id' => $revisor_id,
            'status' => 'em_revisao',
            'data_inicio_revisao' => current_time('mysql')
        ),
        array('%d', '%s', '%s')
    );
    
    if ($resultado === false) {
        wp_send_json_error('Erro ao iniciar revisão.');
        return;
    }
    
    wp_send_json_success('Revisão iniciada! O trabalho foi atribuído a você.');
}

/**
 * AJAX: Aprovar trabalho
 */

public function aprovar_trabalho() {
    check_ajax_referer('bordados_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Você precisa estar logado.');
        return;
    }
    
    $user = wp_get_current_user();
    if (!in_array('revisor_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
        wp_send_json_error('Acesso restrito a revisores.');
        return;
    }
    
    $pedido_id = intval($_POST['pedido_id']);
    $revisor_id = $user->ID;
    
    // Verificar se o pedido está em revisão pelo revisor atual
    $pedido = Bordados_Database::buscar_pedido_completo($pedido_id);
    
    if (!$pedido || $pedido->status !== 'em_revisao' || $pedido->revisor_id != $revisor_id) {
        wp_send_json_error('Pedido não encontrado ou você não está revisando este trabalho.');
        return;
    }
    
    // Aprovar e finalizar
    $resultado = Bordados_Database::atualizar_pedido(
        $pedido_id,
        array(
            'status' => 'pronto',
            'data_fim_revisao' => current_time('mysql'),
            'data_conclusao' => current_time('mysql')
        ),
        array('%s', '%s', '%s')
    );
    
    if ($resultado === false) {
        wp_send_json_error('Erro ao aprovar trabalho.');
        return;
    }
    
    // Enviar email para o cliente
    $arquivos_finais = !empty($pedido->arquivos_finais) ? json_decode($pedido->arquivos_finais, true) : array();
    Bordados_Emails::enviar_trabalho_concluido($pedido, $arquivos_finais);
    
    wp_send_json_success('Trabalho aprovado e entregue ao cliente! Email de notificação enviado.');
}

/**
 * AJAX: Solicitar acertos
 */

public function solicitar_acertos() {
    check_ajax_referer('bordados_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Você precisa estar logado.');
        return;
    }
    
    $user = wp_get_current_user();
    if (!in_array('revisor_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
        wp_send_json_error('Acesso restrito a revisores.');
        return;
    }
    
    $pedido_id = intval($_POST['pedido_id']);
    $revisor_id = $user->ID;
    $observacoes_revisor = sanitize_textarea_field($_POST['observacoes_revisor']);
    
    if (empty($observacoes_revisor)) {
        wp_send_json_error('Por favor, descreva os acertos necessários.');
        return;
    }
    
    // Verificar se o pedido está em revisão pelo revisor atual
    $pedido = Bordados_Database::buscar_pedido_completo($pedido_id);
    
    if (!$pedido || $pedido->status !== 'em_revisao' || $pedido->revisor_id != $revisor_id) {
        wp_send_json_error('Pedido não encontrado ou você não está revisando este trabalho.');
        return;
    }
    
    // Incrementar contador de ciclos
    $ciclos_acertos = intval($pedido->ciclos_acertos) + 1;
    
    // Voltar para programador
    $resultado = Bordados_Database::atualizar_pedido(
        $pedido_id,
        array(
            'status' => 'em_acertos',
            'obs_revisor' => $observacoes_revisor,
            'ciclos_acertos' => $ciclos_acertos,
            'data_fim_revisao' => current_time('mysql')
        ),
        array('%s', '%s', '%d', '%s')
    );
    
    if ($resultado === false) {
        wp_send_json_error('Erro ao solicitar acertos.');
        return;
    }
    
    // TODO: Enviar email para o programador notificando sobre os acertos
    
    wp_send_json_success("Acertos solicitados! O programador foi notificado. (Ciclo de acertos: $ciclos_acertos)");
}
/**
 * AJAX: Aprovar trabalho COM arquivos revisados pelo revisor
 */

public function aprovar_trabalho_com_arquivos() {
    check_ajax_referer('bordados_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Você precisa estar logado.');
        return;
    }
    
    $user = wp_get_current_user();
    if (!in_array('revisor_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
        wp_send_json_error('Acesso restrito a revisores.');
        return;
    }
    
    $pedido_id = intval($_POST['pedido_id']);
    $revisor_id = $user->ID;
    
    error_log("=== APROVAR COM ARQUIVOS REVISADOS ===");
    error_log("Pedido ID: $pedido_id");
    error_log("Revisor ID: $revisor_id");
    
    // Verificar se o pedido está em revisão pelo revisor atual
    $pedido = Bordados_Database::buscar_pedido_completo($pedido_id);
    
    if (!$pedido || $pedido->status !== 'em_revisao' || $pedido->revisor_id != $revisor_id) {
        wp_send_json_error('Pedido não encontrado ou você não está revisando este trabalho.');
        return;
    }
    
    // Processar uploads dos arquivos revisados
    $arquivos_revisados = $this->processar_uploads_revisados();
    
    error_log("Arquivos revisados processados: " . count($arquivos_revisados));
    
    if (empty($arquivos_revisados)) {
        wp_send_json_error('Erro ao processar arquivos revisados. Verifique se os arquivos foram selecionados corretamente.');
        return;
    }
    
    // IMPORTANTE: Substituir os arquivos finais pelos revisados
    $resultado = Bordados_Database::atualizar_pedido(
        $pedido_id,
        array(
            'arquivos_finais' => json_encode($arquivos_revisados), // Substitui pelos revisados
            'status' => 'pronto',
            'data_fim_revisao' => current_time('mysql'),
            'data_conclusao' => current_time('mysql'),
            'obs_revisor' => 'Pequenos ajustes feitos pelo revisor'
        ),
        array('%s', '%s', '%s', '%s', '%s')
    );
    
    if ($resultado === false) {
        wp_send_json_error('Erro ao aprovar trabalho no banco de dados.');
        return;
    }
    
    // Buscar pedido atualizado para enviar email
    $pedido_atualizado = Bordados_Database::buscar_pedido_completo($pedido_id);
    
    // Enviar email para o cliente com os arquivos revisados
    Bordados_Emails::enviar_trabalho_concluido($pedido_atualizado, $arquivos_revisados);
    
    wp_send_json_success('Trabalho aprovado com arquivos revisados e entregue ao cliente! (' . count($arquivos_revisados) . ' arquivo(s) enviado(s))');
}

/**
 * Processar uploads de arquivos revisados pelo revisor
 */

private function processar_uploads_revisados() {
    $arquivos_urls = array();
    
    error_log('=== PROCESSANDO UPLOADS REVISADOS ===');
    
    if (!isset($_FILES['arquivos_revisados']) || !is_array($_FILES['arquivos_revisados'])) {
        error_log('$_FILES[arquivos_revisados] não encontrado');
        return $arquivos_urls;
    }
    
    $files = $_FILES['arquivos_revisados'];
    error_log('Estrutura do $_FILES: ' . print_r($files, true));
    
    if (!isset($files['name']) || !is_array($files['name'])) {
        error_log('Estrutura inválida - files[name] não é array');
        return $arquivos_urls;
    }
    
    $total_arquivos = count($files['name']);
    error_log("Total de arquivos para processar: $total_arquivos");
    
    // Configurar diretório de upload
    $upload_dir = wp_upload_dir();
    $bordados_revisados_dir = $upload_dir['basedir'] . '/bordados-revisados/';
    
    // Criar diretório se não existir
    if (!file_exists($bordados_revisados_dir)) {
        wp_mkdir_p($bordados_revisados_dir);
        error_log('Diretório criado: ' . $bordados_revisados_dir);
    }
    
    // Processar cada arquivo
    for ($i = 0; $i < $total_arquivos; $i++) {
        error_log("=== Processando arquivo revisado $i ===");
        error_log("Nome: " . ($files['name'][$i] ?? 'vazio'));
        error_log("Erro: " . ($files['error'][$i] ?? 'não definido'));
        
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
            $nome_unico = time() . '_revisado_' . uniqid() . '_' . $nome_arquivo;
            $caminho_arquivo = $bordados_revisados_dir . $nome_unico;
            
            error_log("Tentando mover arquivo para: $caminho_arquivo");
            
            // Mover arquivo
            if (move_uploaded_file($tmp_name, $caminho_arquivo)) {
                $url_arquivo = $upload_dir['baseurl'] . '/bordados-revisados/' . $nome_unico;
                $arquivos_urls[] = $url_arquivo;
                error_log("Arquivo revisado movido com sucesso! URL: $url_arquivo");
            } else {
                error_log("ERRO: Falha ao mover arquivo $nome_arquivo");
            }
        }
    }
    
    error_log("=== UPLOAD REVISADOS FINALIZADO ===");
    error_log("Total de arquivos processados com sucesso: " . count($arquivos_urls));
    
    return $arquivos_urls;
}

/**
 * AJAX: Aprovar trabalho pelo revisor com modal completo
 * - Calcula preço final automaticamente baseado no sistema de preços do cliente
 * - Pode anexar arquivos revisados
 * - Guarda arquivos originais do programador
 */
public function aprovar_trabalho_revisor() {
    check_ajax_referer('bordados_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('You need to be logged in.');
        return;
    }
    
    $user = wp_get_current_user();
    if (!in_array('revisor_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
        wp_send_json_error('Access restricted to reviewers.');
        return;
    }
    
    $pedido_id = intval($_POST['pedido_id']);
    $revisor_id = $user->ID;
    $preco_manual = isset($_POST['preco_final']) ? floatval($_POST['preco_final']) : 0;
    $obs_revisor = sanitize_textarea_field($_POST['obs_revisor_aprovacao']);
    
    error_log("=== APROVAR TRABALHO REVISOR ===");
    error_log("Pedido ID: $pedido_id");
    error_log("Revisor ID: $revisor_id");
    error_log("Preço Manual: $preco_manual");
    
    if (empty($pedido_id)) {
        wp_send_json_error('Invalid order ID.');
        return;
    }
    
    // Verificar se o pedido está em revisão pelo revisor atual
    $pedido = Bordados_Database::buscar_pedido_completo($pedido_id);
    
    if (!$pedido || $pedido->status !== 'em_revisao' || $pedido->revisor_id != $revisor_id) {
        wp_send_json_error('Order not found or you are not reviewing this work.');
        return;
    }
    
    // ============ CALCULAR PREÇO AUTOMATICAMENTE ============
    $cliente_id = $pedido->cliente_id;
    $numero_pontos = intval($pedido->numero_pontos);
    $preco_programador = floatval($pedido->preco_programador);
    
    // Se tem preço manual informado pelo revisor, usar ele
    // Senão, calcular automaticamente
    if ($preco_manual > 0) {
        $preco_final = $preco_manual;
        $detalhes_preco = 'Price set manually by reviewer';
    } else {
        // Calcular usando sistema de preços do cliente
        $calculo = Bordados_Precos::calcular_preco_final(
            $cliente_id,
            $numero_pontos,
            '', // tamanho
            '', // dificuldade
            $preco_programador
        );
        
        $preco_final = $calculo['preco_final'];
        $detalhes_preco = $calculo['detalhes_calculo'];
        
        error_log("Preço calculado automaticamente: $preco_final");
        error_log("Sistema usado: " . $calculo['sistema_usado']);
        error_log("Detalhes: $detalhes_preco");
    }
    
    if ($preco_final <= 0) {
        wp_send_json_error('Unable to calculate final price. Please enter manually or check pricing system configuration.');
        return;
    }
    // ============ FIM CÁLCULO DE PREÇO ============
    
    // Processar uploads de arquivos revisados (se houver)
    $arquivos_revisados = $this->processar_uploads_revisados();
    $arquivos_finais_para_cliente = array();
    $arquivos_programador_original = null;
    
    if (!empty($arquivos_revisados)) {
        // Revisor enviou arquivos novos
        // Guardar arquivos originais do programador
        $arquivos_originais = !empty($pedido->arquivos_finais) ? json_decode($pedido->arquivos_finais, true) : array();
        if (!empty($arquivos_originais)) {
            $arquivos_programador_original = json_encode($arquivos_originais);
        }
        // Arquivos do revisor são os que vão para o cliente
        $arquivos_finais_para_cliente = $arquivos_revisados;
        error_log("Revisor enviou " . count($arquivos_revisados) . " arquivo(s) revisado(s)");
        error_log("Arquivos originais do programador guardados como backup");
    } else {
        // Revisor não enviou arquivos, usar os do programador
        $arquivos_finais_para_cliente = !empty($pedido->arquivos_finais) ? json_decode($pedido->arquivos_finais, true) : array();
        error_log("Usando arquivos originais do programador");
    }
    
    // Preparar dados para atualização
    $dados_update = array(
        'status' => 'pronto',
        'preco_final' => $preco_final,
        'arquivos_finais' => json_encode($arquivos_finais_para_cliente),
        'data_fim_revisao' => current_time('mysql'),
        'data_conclusao' => current_time('mysql')
    );
    $formatos = array('%s', '%f', '%s', '%s', '%s');
    
    // Adicionar observações do revisor se preenchidas
    if (!empty($obs_revisor)) {
        $dados_update['obs_revisor_aprovacao'] = $obs_revisor;
        $formatos[] = '%s';
    }
    
    // Adicionar arquivos originais do programador se foram substituídos
    if ($arquivos_programador_original) {
        $dados_update['arquivos_programador_original'] = $arquivos_programador_original;
        $formatos[] = '%s';
    }
    
    // Atualizar pedido
    $resultado = Bordados_Database::atualizar_pedido($pedido_id, $dados_update, $formatos);
    
    if ($resultado === false) {
        wp_send_json_error('Error approving work.');
        return;
    }
    
    // Buscar pedido atualizado para email
    $pedido_atualizado = Bordados_Database::buscar_pedido_completo($pedido_id);
    
    // Enviar email para o cliente
    Bordados_Emails::enviar_trabalho_concluido($pedido_atualizado, $arquivos_finais_para_cliente);
    
    $mensagem = 'Work approved and delivered to customer! Final price: $' . number_format($preco_final, 2);
    if (!empty($arquivos_revisados)) {
        $mensagem .= ' (' . count($arquivos_revisados) . ' revised file(s) sent)';
    }
    
    error_log("=== APROVAÇÃO CONCLUÍDA ===");
    error_log($mensagem);
    
    wp_send_json_success($mensagem);
}

}

?>
