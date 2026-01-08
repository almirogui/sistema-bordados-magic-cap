<?php
/**
 * AJAX: Funções do Cliente
 * Extraído de class-ajax.php na Fase 4 da modularização
 * 
 * Funções:
 * - buscar_arquivos_pedido
 * - criar_pedido
 * - processar_uploads_multiplos (helper)
 * 
 * ATUALIZADO: Suporte a orçamentos (Etapa 3)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Ajax_Cliente {
    
    public function __construct() {
        add_action('wp_ajax_criar_pedido', array($this, 'criar_pedido'));
        add_action('wp_ajax_buscar_arquivos_pedido', array($this, 'buscar_arquivos_pedido'));
    }
    
    public function buscar_arquivos_pedido() {
        // Log para debug
        error_log('=== BUSCAR ARQUIVOS PEDIDO INICIADO ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Verificar nonce - CORRIGIDO para usar isset
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
            error_log('❌ Token inválido ou não encontrado');
            wp_send_json_error('Token de segurança inválido');
            return;
        }
        
        // Verificar se usuário está logado
        if (!is_user_logged_in()) {
            error_log('❌ Usuário não logado');
            wp_send_json_error('Você precisa estar logado para acessar os arquivos');
            return;
        }
        
        $pedido_id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;
        $user_id = get_current_user_id();
        
        error_log("📋 Pedido solicitado: #$pedido_id por usuário #$user_id");
        
        if (empty($pedido_id)) {
            error_log('❌ ID do pedido inválido');
            wp_send_json_error('ID do pedido inválido');
            return;
        }
        
        // Buscar pedido completo
        $pedido = Bordados_Database::buscar_pedido_completo($pedido_id);
        
        if (!$pedido) {
            error_log("❌ Pedido #$pedido_id não encontrado no banco");
            wp_send_json_error('Pedido não encontrado');
            return;
        }
        
        error_log("📋 Pedido encontrado: Status = {$pedido->status}, Cliente = {$pedido->cliente_id}");
        
        // Verificar permissões: cliente dono do pedido ou admin
        if ($pedido->cliente_id != $user_id && !current_user_can('manage_options')) {
            error_log("❌ Usuário #$user_id não tem permissão para pedido #$pedido_id (dono: {$pedido->cliente_id})");
            wp_send_json_error('Você não tem permissão para acessar este pedido');
            return;
        }
        
        // Verificar se pedido está pronto
        if ($pedido->status !== 'pronto') {
            error_log("❌ Pedido #$pedido_id não está pronto (status: {$pedido->status})");
            wp_send_json_error('Este pedido ainda não está pronto para download. Status atual: ' . $pedido->status);
            return;
        }
        
        // Verificar se tem arquivos finais
        if (empty($pedido->arquivos_finais)) {
            error_log("❌ Pedido #$pedido_id não tem arquivos finais");
            wp_send_json_error('Nenhum arquivo final disponível para este pedido');
            return;
        }
        
        error_log("📁 Arquivos finais raw: " . $pedido->arquivos_finais);
        
        // Decodificar arquivos finais
        $arquivos_finais = json_decode($pedido->arquivos_finais, true);
        
        if (!is_array($arquivos_finais)) {
            error_log("❌ Erro ao decodificar JSON dos arquivos finais");
            wp_send_json_error('Erro ao processar arquivos finais');
            return;
        }
        
        if (empty($arquivos_finais)) {
            error_log("❌ Array de arquivos finais está vazio");
            wp_send_json_error('Nenhum arquivo encontrado para download');
            return;
        }
        
        error_log("📁 Arquivos decodificados: " . print_r($arquivos_finais, true));
        
        // Verificar se arquivos ainda existem
        $arquivos_validos = array();
        $arquivos_invalidos = array();
        
        foreach ($arquivos_finais as $index => $arquivo) {
            if (!empty($arquivo)) {
                // Converter URL para path local se necessário
                $upload_dir = wp_upload_dir();
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $arquivo);
                
                error_log("🔍 Verificando arquivo #$index: $arquivo");
                error_log("   Path local: $file_path");
                error_log("   Existe? " . (file_exists($file_path) ? 'SIM' : 'NÃO'));
                error_log("   URL válida? " . (filter_var($arquivo, FILTER_VALIDATE_URL) ? 'SIM' : 'NÃO'));
                
                if (file_exists($file_path) || filter_var($arquivo, FILTER_VALIDATE_URL)) {
                    $arquivos_validos[] = $arquivo;
                } else {
                    $arquivos_invalidos[] = $arquivo;
                    error_log("⚠️ Arquivo não encontrado: $arquivo (path: $file_path)");
                }
            }
        }
        
        error_log("✅ Arquivos válidos: " . count($arquivos_validos));
        error_log("❌ Arquivos inválidos: " . count($arquivos_invalidos));
        
        if (empty($arquivos_validos)) {
            error_log("❌ Nenhum arquivo válido encontrado no servidor");
            wp_send_json_error('Arquivos não encontrados no servidor. Entre em contato com o suporte.');
            return;
        }
        
        // Log final para debug
        error_log("✅ Download autorizado para pedido #$pedido_id");
        error_log("📤 Retornando " . count($arquivos_validos) . " arquivo(s) válido(s)");
        
        // FORÇAR HTTPS em todas as URLs antes de retornar
        $arquivos_https = array_map(function($url) {
            return str_replace('http://', 'https://', $url);
        }, $arquivos_validos);
        
        error_log("🔒 URLs convertidas para HTTPS: " . print_r($arquivos_https, true));
        
        // Retornar arquivos válidos com HTTPS
        wp_send_json_success(array(
            'pedido_id' => $pedido_id,
            'arquivos' => $arquivos_https,
            'total_arquivos' => count($arquivos_https),
            'nome_bordado' => $pedido->nome_bordado,
            'cliente_nome' => $pedido->cliente_nome ?? 'Cliente'
        ));
    }

    public function criar_pedido() {
        error_log("=== CRIAR PEDIDO INICIADO ===");
        error_log("POST DATA: " . print_r($_POST, true));
        error_log("FILES DATA: " . print_r($_FILES, true));
        
        // ✅ CORREÇÃO 1: Verificar nonce com AMBOS os nomes possíveis
        $nonce_valido = false;
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'bordados_nonce')) {
                $nonce_valido = true;
                error_log("✅ Nonce válido: bordados_nonce");
            } elseif (wp_verify_nonce($_POST['nonce'], 'bordados_ajax_nonce')) {
                $nonce_valido = true;
                error_log("✅ Nonce válido: bordados_ajax_nonce");
            }
        }
        
        if (!$nonce_valido) {
            error_log("❌ Nonce inválido");
            wp_send_json_error(array('message' => 'Token de segurança inválido'));
            return;
        }
        
        // Verificar se está logado
        if (!is_user_logged_in()) {
            error_log("❌ Usuário não logado");
            wp_send_json_error(array('message' => 'Você precisa estar logado'));
            return;
        }
        
        $cliente_id = get_current_user_id();
        $cliente = wp_get_current_user();
        
        error_log("✅ Cliente: {$cliente->display_name} (ID: {$cliente_id})");
        
        // ✅ CORREÇÃO 2: Validar e sanitizar TODOS os campos
        $nome_bordado = isset($_POST['nome_bordado']) ? sanitize_text_field($_POST['nome_bordado']) : '';
        $prazo_entrega = isset($_POST['prazo_entrega']) ? sanitize_text_field($_POST['prazo_entrega']) : '';
        $largura = isset($_POST['largura']) ? floatval($_POST['largura']) : 0;
        $altura = isset($_POST['altura']) ? floatval($_POST['altura']) : 0;
        $unidade_medida = isset($_POST['unidade_medida']) ? sanitize_text_field($_POST['unidade_medida']) : 'cm';
        $local_bordado = isset($_POST['local_bordado']) ? sanitize_text_field($_POST['local_bordado']) : '';
        $tipo_tecido = isset($_POST['tipo_tecido']) ? sanitize_text_field($_POST['tipo_tecido']) : '';
        $cores = isset($_POST['cores']) ? sanitize_text_field($_POST['cores']) : '';
        $observacoes = isset($_POST['observacoes']) ? sanitize_textarea_field($_POST['observacoes']) : '';
        
        // ✅ ETAPA 3: Campos para tipo de produto e orçamento
        $tipo_produto = isset($_POST['tipo_produto']) ? sanitize_text_field($_POST['tipo_produto']) : 'bordado';
        $is_orcamento = isset($_POST['is_orcamento']) ? sanitize_text_field($_POST['is_orcamento']) : '0';
        
        error_log("📋 Tipo de produto: {$tipo_produto}");
        error_log("💰 É orçamento: {$is_orcamento}");
        
        // Validar campos obrigatórios (tipo_tecido agora é opcional)
        if (empty($nome_bordado) || empty($prazo_entrega) || empty($local_bordado)) {
            error_log("❌ Campos obrigatórios faltando");
            wp_send_json_error(array('message' => 'Please fill in all required fields (name, turnaround, placement)'));
            return;
        }
        
        error_log("✅ Campos validados");
        
        // ✅ CORREÇÃO 3: PROCESSAR ARQUIVOS (estava faltando!)
        $arquivos_salvos = array();
        
        if (isset($_FILES['arquivos_referencia']) && !empty($_FILES['arquivos_referencia']['name'][0])) {
            error_log("📎 Processando arquivos de referência...");
            
            $upload_dir = wp_upload_dir();
            $bordados_dir = $upload_dir['basedir'] . '/bordados-referencias/';
            
            // Criar diretório se não existir
            if (!file_exists($bordados_dir)) {
                wp_mkdir_p($bordados_dir);
                error_log("📁 Diretório criado: {$bordados_dir}");
            }
            
            $total_arquivos = count($_FILES['arquivos_referencia']['name']);
            error_log("📎 Total de arquivos para processar: {$total_arquivos}");
            
            for ($i = 0; $i < $total_arquivos && $i < 3; $i++) {
                if (!empty($_FILES['arquivos_referencia']['name'][$i])) {
                    $nome_arquivo = $_FILES['arquivos_referencia']['name'][$i];
                    $tmp_name = $_FILES['arquivos_referencia']['tmp_name'][$i];
                    $tamanho = $_FILES['arquivos_referencia']['size'][$i];
                    
                    error_log("📎 Processando arquivo {$i}: {$nome_arquivo}");
                    
                    // Validar arquivo
                    $extensoes_permitidas = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'ai', 'eps', 'svg');
                    $extensao = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));
                    
                    if (!in_array($extensao, $extensoes_permitidas)) {
                        error_log("❌ Extensão não permitida: {$extensao}");
                        wp_send_json_error(array('message' => 'Arquivo ' . $nome_arquivo . ' tem extensão não permitida.'));
                        return;
                    }
                    
                    if ($tamanho > 10 * 1024 * 1024) { // 10MB
                        error_log("❌ Arquivo muito grande: {$tamanho} bytes");
                        wp_send_json_error(array('message' => 'Arquivo ' . $nome_arquivo . ' é muito grande (máx. 10MB).'));
                        return;
                    }
                    
                    // Gerar nome único para o arquivo
                    $nome_unico = time() . '_' . $i . '_' . uniqid() . '_' . sanitize_file_name($nome_arquivo);
                    $caminho_arquivo = $bordados_dir . $nome_unico;
                    
                    // Mover arquivo
                    if (move_uploaded_file($tmp_name, $caminho_arquivo)) {
                        $url_arquivo = $upload_dir['baseurl'] . '/bordados-referencias/' . $nome_unico;
                        $arquivos_salvos[] = $url_arquivo;
                        error_log("✅ Arquivo salvo: {$url_arquivo}");
                    } else {
                        error_log("❌ Erro ao mover arquivo: {$nome_arquivo}");
                        wp_send_json_error(array('message' => 'Erro ao salvar arquivo ' . $nome_arquivo));
                        return;
                    }
                }
            }
        }
        
        if (empty($arquivos_salvos)) {
            error_log("❌ Nenhum arquivo foi processado");
            wp_send_json_error(array('message' => 'Por favor, envie pelo menos um arquivo de referência.'));
            return;
        }
        
        error_log("✅ " . count($arquivos_salvos) . " arquivo(s) processado(s) com sucesso");
        
        // ✅ CORREÇÃO 4: Converter arquivos para JSON (estava faltando!)
        $arquivos_json = json_encode($arquivos_salvos);
        
        // ============================================
        // VERIFICAR PROGRAMADOR PADRÃO
        // ============================================
        
        $programador_padrao = get_user_meta($cliente_id, 'programador_padrao', true);
        
        error_log("🔍 Verificando programador padrão...");
        error_log("Programador padrão encontrado: " . ($programador_padrao ? $programador_padrao : 'NENHUM'));
        
        // Determinar status e programador inicial
        $status_inicial = 'novo';
        $programador_inicial = null;
        $data_atribuicao = null;
        
        // ✅ ETAPA 3: SE FOR ORÇAMENTO - não atribuir programador, vai para revisão
        if ($is_orcamento === '1') {
            $status_inicial = 'orcamento_pendente';
            error_log("💰 MODO ORÇAMENTO: Status definido como 'orcamento_pendente'");
        } else {
            // MODO PEDIDO NORMAL - verificar programador padrão
            if (!empty($programador_padrao)) {
                // Cliente TEM programador padrão
                
                // Verificar se programador está ativo
                $programador_ativo = get_user_meta($programador_padrao, 'programador_ativo', true);
                
                if ($programador_ativo === 'yes' || empty($programador_ativo)) {
                    // Programador padrão está ativo
                    // Criar pedido JÁ ATRIBUÍDO
                    $status_inicial = 'atribuido';
                    $programador_inicial = $programador_padrao;
                    $data_atribuicao = current_time('mysql');
                    
                    error_log("✅ Cliente tem programador padrão ativo (ID: {$programador_padrao}). Criando pedido já atribuído.");
                } else {
                    error_log("⚠️ Programador padrão (ID: {$programador_padrao}) está inativo. Criando pedido como 'novo'.");
                }
            } else {
                // Cliente NÃO tem programador padrão
                
                // Verificar se tem atribuição automática
                $atribuicao_automatica = get_user_meta($cliente_id, 'atribuicao_automatica', true);
                
                if ($atribuicao_automatica === 'yes') {
                    error_log("ℹ️ Cliente tem atribuição automática habilitada. Buscando programador disponível...");
                    
                    // ✅ BUSCAR PROGRAMADOR COM MENOS TRABALHOS PENDENTES
                    $programador_disponivel = $this->buscar_programador_com_menos_trabalhos();
                    
                    if ($programador_disponivel) {
                        // Atribuir automaticamente
                        $status_inicial = 'atribuido';
                        $programador_inicial = $programador_disponivel;
                        $data_atribuicao = current_time('mysql');
                        
                        error_log("✅ Programador disponível encontrado (ID: {$programador_disponivel}). Atribuindo automaticamente.");
                    } else {
                        error_log("⚠️ Nenhum programador disponível. Pedido ficará como 'novo'.");
                    }
                } else {
                    error_log("ℹ️ Cliente sem programador padrão e sem atribuição automática. Admin terá que atribuir.");
                }
            }
        }
        
        // ============================================
        // Preparar dados para criar pedido
        // ============================================
        
        $dados = array(
            'cliente_id' => $cliente_id,
            'cliente_nome' => $cliente->display_name,
            'cliente_email' => $cliente->user_email,
            'nome_bordado' => $nome_bordado,
            'prazo_entrega' => $prazo_entrega,
            'largura' => $largura,
            'altura' => $altura,
            'unidade_medida' => $unidade_medida,
            'local_bordado' => $local_bordado,
            'tipo_tecido' => $tipo_tecido,
            'cores' => $cores,
            'observacoes' => $observacoes,
            'arquivos_cliente' => $arquivos_json, // ✅ CORRIGIDO: agora está definido!
            
            // ✅ ATRIBUIÇÃO INICIAL
            'status' => $status_inicial,
            'programador_id' => $programador_inicial,
            'data_atribuicao' => $data_atribuicao,
            
            'data_criacao' => current_time('mysql'),
            'tipo_produto' => $tipo_produto,  // ✅ ETAPA 3: Novo campo
            'tipo_pedido' => 'original'
        );
        
        error_log("💾 Tentando criar pedido no banco...");
        error_log("Dados: " . print_r($dados, true));
        
        // Criar pedido
        try {
            $pedido_id = Bordados_Database::criar_pedido($dados);
            
            if (!$pedido_id) {
                error_log("❌ Bordados_Database::criar_pedido retornou false");
                wp_send_json_error(array('message' => 'Erro ao criar pedido. Tente novamente.'));
                return;
            }
            
            error_log("✅ PEDIDO CRIADO COM SUCESSO! ID: {$pedido_id}");
            
            // ============================================
            // Se foi atribuído para programador padrão
            // Enviar email AGORA (não pelo hook)
            // ============================================
            
            if (!empty($programador_inicial)) {
                error_log("📧 Enviando email para programador padrão...");
                
                // Enviar email ao programador
                if (class_exists('Bordados_Emails')) {
                    // ✅ CORREÇÃO: Usar método correto com parâmetros corretos
                    Bordados_Emails::enviar_novo_trabalho(
                        $programador_inicial,  // ID do programador
                        $pedido_id,           // ID do pedido
                        $dados                // Array com dados do pedido
                    );
                    
                    error_log("✅ Email enviado para programador padrão ID: {$programador_inicial}");
                }
            }
            
            // ✅ ETAPA 3: Mensagem de sucesso diferente para orçamento
            if ($is_orcamento === '1') {
                $mensagem = 'Quote request #' . $pedido_id . ' submitted successfully! You will receive a quote by email.';
            } else {
                $mensagem = 'Order #' . $pedido_id . ' created successfully!';
                if (!empty($programador_inicial)) {
                    $programador = get_userdata($programador_inicial);
                    $mensagem .= ' Automatically assigned to ' . $programador->display_name . '.';
                } else {
                    $mensagem .= ' An administrator will assign a digitizer shortly.';
                }
            }
            
            wp_send_json_success(array(
                'message' => $mensagem,
                'pedido_id' => $pedido_id,
                'atribuido' => !empty($programador_inicial),
                'is_orcamento' => ($is_orcamento === '1')  // ✅ ETAPA 3: Flag para frontend
            ));
            
        } catch (Exception $e) {
            error_log("❌ EXCEÇÃO ao criar pedido: " . $e->getMessage());
            wp_send_json_error(array('message' => 'Erro interno: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Atribuir pedido
     */

    private function processar_uploads_multiplos($field_name) {
        error_log("=== MÉTODO LEGADO CHAMADO: $field_name ===");
        
        if ($field_name === 'arquivos_finais') {
            return $this->processar_uploads_finais_melhorado();
        }
        
        // Código original para outros tipos de upload
        $arquivos_urls = array();
        
        if (isset($_FILES[$field_name]) && is_array($_FILES[$field_name]['name'])) {
            $files = $_FILES[$field_name];
            
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK && !empty($files['name'][$i])) {
                    // Reorganizar array para wp_handle_upload
                    $file = array(
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    );
                    
                    $upload = wp_handle_upload($file, array('test_form' => false));
                    if (isset($upload['url'])) {
                        $arquivos_urls[] = $upload['url'];
                    }
                }
            }
        }
        
        return $arquivos_urls;
    }

    /**
     * Buscar programador ativo com menos trabalhos pendentes
     */
    private function buscar_programador_com_menos_trabalhos() {
        error_log("=== BUSCANDO PROGRAMADOR COM MENOS TRABALHOS ===");
        
        // Buscar todos os usuários com role programador_bordados
        $args = array(
            'role' => 'programador_bordados',
            'orderby' => 'ID'
        );
        
        $programadores = get_users($args);
        
        if (empty($programadores)) {
            error_log("❌ Nenhum programador encontrado no sistema");
            return null;
        }
        
        error_log("✅ " . count($programadores) . " programador(es) encontrado(s)");
        
        global $wpdb;
        $table_name = 'pedidos_basicos';
        
        $programador_escolhido = null;
        $menor_quantidade = PHP_INT_MAX;
        
        foreach ($programadores as $prog) {
            // Verificar se programador está ativo
            $ativo = get_user_meta($prog->ID, 'programador_ativo', true);
            
            if ($ativo === 'no') {
                error_log("⏭️ Programador {$prog->display_name} (ID: {$prog->ID}) está INATIVO. Pulando.");
                continue;
            }
            
            // Contar trabalhos pendentes
            $trabalhos_pendentes = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                WHERE programador_id = %d 
                AND status IN ('atribuido', 'em_producao', 'em_acertos')",
                $prog->ID
            ));
            
            error_log("👨‍💻 Programador: {$prog->display_name} (ID: {$prog->ID}) - Trabalhos pendentes: {$trabalhos_pendentes}");
            
            if ($trabalhos_pendentes < $menor_quantidade) {
                $menor_quantidade = $trabalhos_pendentes;
                $programador_escolhido = $prog->ID;
            }
        }
        
        if ($programador_escolhido) {
            $prog_obj = get_userdata($programador_escolhido);
            error_log("✅ ESCOLHIDO: {$prog_obj->display_name} (ID: {$programador_escolhido}) com {$menor_quantidade} trabalho(s) pendente(s)");
        } else {
            error_log("❌ Nenhum programador ativo disponível");
        }
        
        return $programador_escolhido;
    }
}

?>
