<?php
/**
 * Classe para gerenciar o banco de dados
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Database {
    
    /**
     * Criar tabelas do plugin
     */
    public static function criar_tabelas() {
        global $wpdb;
        
       $table_name = 'pedidos_basicos';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            cliente_id int(11) NOT NULL,
            programador_id int(11) DEFAULT NULL,
            nome_bordado varchar(255) NOT NULL,
            tamanho varchar(100) NOT NULL,
            cores varchar(50) DEFAULT NULL,
            observacoes text,
            arquivo_referencia varchar(500) DEFAULT NULL,
            arquivos_cliente TEXT DEFAULT NULL,
            status varchar(50) DEFAULT 'novo',
            preco_programador decimal(10,2) DEFAULT NULL,
            observacoes_programador text,
            arquivos_finais TEXT DEFAULT NULL,
            data_criacao datetime NOT NULL,
            data_atribuicao datetime DEFAULT NULL,
            data_conclusao datetime DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Adicionar colunas se nÃ£o existirem (para sites existentes)
        self::atualizar_tabela_se_necessario();
    }
    
    /**
     * Atualizar tabela para versÃµes antigas
     */
    private static function atualizar_tabela_se_necessario() {
        global $wpdb;
        
        $table_name = 'pedidos_basicos';
        $columns = $wpdb->get_col("DESCRIBE $table_name");
        
        if (!in_array('arquivos_cliente', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN arquivos_cliente TEXT AFTER arquivo_referencia");
        }
        
        if (!in_array('arquivos_finais', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN arquivos_finais TEXT AFTER observacoes_programador");
        }
        
        // Campo para guardar arquivos originais do programador quando revisor substitui
        if (!in_array('arquivos_programador_original', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN arquivos_programador_original TEXT AFTER arquivos_finais");
        }
        
        // Campo para observações do revisor na aprovação
        if (!in_array('obs_revisor_aprovacao', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN obs_revisor_aprovacao TEXT AFTER obs_revisor");
        }
        
        // Campos de cobrança - v3.1
        if (!in_array('cobrado', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN cobrado TINYINT(1) DEFAULT 0");
        }
        
        if (!in_array('data_cobranca', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN data_cobranca DATETIME NULL");
        }
        
        if (!in_array('cobrado_por', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN cobrado_por INT NULL");
        }
        
        if (!in_array('invoice_number', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN invoice_number INT NULL");
        }
    }
    
    /**
     * Buscar pedidos do cliente
     */
public static function buscar_pedidos_cliente($cliente_id) {
    global $wpdb;
    
    $pedidos = $wpdb->get_results($wpdb->prepare("
        SELECT 
            p.*,
            prog.display_name as programador_nome
        FROM pedidos_basicos p
        LEFT JOIN {$wpdb->prefix}users prog ON p.programador_id = prog.ID
        WHERE p.cliente_id = %d 
        ORDER BY p.data_criacao DESC
    ", $cliente_id));
    
    return $pedidos ?: array();
}

/**
 * ADICIONE este novo mÃ©todo para buscar pedidos com filtros
 */
public static function buscar_pedidos_detalhados($filtros = array()) {
    global $wpdb;
    
    $where_clauses = array();
    $params = array();
    
    // Filtro por status
    if (!empty($filtros['status'])) {
        $where_clauses[] = "p.status = %s";
        $params[] = $filtros['status'];
    }
    
    // Filtro por programador
    if (!empty($filtros['programador_id'])) {
        $where_clauses[] = "p.programador_id = %d";
        $params[] = $filtros['programador_id'];
    }
    
    // Filtro por prazo
    if (!empty($filtros['prazo_entrega'])) {
        $where_clauses[] = "p.prazo_entrega = %s";
        $params[] = $filtros['prazo_entrega'];
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    $sql = "
        SELECT 
            p.*,
            prog.display_name as programador_nome
        FROM pedidos_basicos p
        LEFT JOIN {$wpdb->prefix}users prog ON p.programador_id = prog.ID
        {$where_sql}
        ORDER BY p.data_criacao DESC
    ";
    
    if (!empty($params)) {
        $pedidos = $wpdb->get_results($wpdb->prepare($sql, $params));
    } else {
        $pedidos = $wpdb->get_results($sql);
    }
    
    return $pedidos ?: array();
}

/**
 * Buscar pedidos do cliente com filtros (ativos + busca)
 */
public static function buscar_pedidos_cliente_filtrados($cliente_id, $filtros = array()) {
    global $wpdb;
    
    // Base da query
    $query = "
        SELECT
            p.*,
            prog.display_name as programador_nome
        FROM pedidos_basicos p
        LEFT JOIN {$wpdb->prefix}users prog ON p.programador_id = prog.ID
        WHERE p.cliente_id = %d
    ";
    
    $params = array($cliente_id);
    
    // Filtro de "ativos" (padrão)
    if (!isset($filtros['mostrar_todos']) || !$filtros['mostrar_todos']) {
        $query .= " AND (
            p.status IN ('novo', 'atribuido', 'em_producao', 'aguardando_revisao', 'em_revisao', 'em_acertos') 
            OR (p.status = 'pronto' AND p.data_conclusao >= DATE_SUB(NOW(), INTERVAL 30 DAY))
        )";
    }
    
    // Filtro de busca por nome
    if (!empty($filtros['busca'])) {
        $query .= " AND p.nome_bordado LIKE %s";
        $params[] = '%' . $wpdb->esc_like($filtros['busca']) . '%';
    }
    
    $query .= " ORDER BY p.data_criacao DESC";
    
    $pedidos = $wpdb->get_results($wpdb->prepare($query, $params));
    
    return $pedidos ?: array();
}
    
    /**
     * Buscar trabalhos do programador
     */
    /**
 * Buscar trabalhos do programador
 */
public static function buscar_trabalhos_programador($programador_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("
        SELECT p.*, 
               u.display_name as cliente_nome,
               rev.display_name as revisor_nome
        FROM pedidos_basicos p
        JOIN {$wpdb->prefix}users u ON p.cliente_id = u.ID
        LEFT JOIN {$wpdb->prefix}users rev ON p.revisor_id = rev.ID
        WHERE p.programador_id = %d AND p.status IN ('atribuido', 'em_producao', 'em_acertos')
        ORDER BY p.data_atribuicao DESC
    ", $programador_id));
}
    /**
     * Buscar pedidos novos para admin
     */
    public static function buscar_pedidos_novos() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT p.*, u.display_name as cliente_nome
            FROM pedidos_basicos p
            JOIN {$wpdb->prefix}users u ON p.cliente_id = u.ID
            WHERE p.status = 'novo'
            ORDER BY p.data_criacao DESC
        ");
    }
    
    /**
     * Buscar pedidos em andamento (atribuído + em produção)
     */
    public static function buscar_pedidos_em_andamento() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT p.*, 
                   c.display_name as cliente_nome,
                   prog.display_name as programador_nome
            FROM pedidos_basicos p
            JOIN {$wpdb->prefix}users c ON p.cliente_id = c.ID
            LEFT JOIN {$wpdb->prefix}users prog ON p.programador_id = prog.ID
            WHERE p.status IN ('atribuido', 'em_producao')
            ORDER BY p.data_atribuicao DESC
        ");
    }
    
    /**
     * Buscar trabalhos concluÃ­dos para admin
     */
    public static function buscar_trabalhos_concluidos($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT p.*, 
                   c.display_name as cliente_nome,
                   prog.display_name as programador_nome
            FROM pedidos_basicos p
            JOIN {$wpdb->prefix}users c ON p.cliente_id = c.ID
            LEFT JOIN {$wpdb->prefix}users prog ON p.programador_id = prog.ID
            WHERE p.status = 'pronto'
            ORDER BY p.data_conclusao DESC
            LIMIT %d
        ", $limit));
    }
    
/**
 * Criar novo pedido
 */
/**
 * Criar novo pedido no banco de dados
 * 
 * @param array $dados Array com dados do pedido
 * @return int|false ID do pedido criado ou false se falhar
 */
public static function criar_pedido($dados) {
    global $wpdb;
    
    // Inserir pedido no banco
    $resultado = $wpdb->insert(
        'pedidos_basicos',
        array(
            'cliente_id' => $dados['cliente_id'],
            'nome_bordado' => $dados['nome_bordado'],
            'prazo_entrega' => $dados['prazo_entrega'],
            'largura' => $dados['largura'],
            'altura' => $dados['altura'],
            'unidade_medida' => $dados['unidade_medida'],
            'local_bordado' => $dados['local_bordado'],
            'tipo_tecido' => $dados['tipo_tecido'],
            'cores' => $dados['cores'],
            'observacoes' => $dados['observacoes'],
            'arquivos_cliente' => $dados['arquivos_cliente'],
            'status' => $dados['status'],
            'data_criacao' => $dados['data_criacao'],
            
            // â­ CAMPOS PARA EDIÃ‡Ã•ES â­
            'pedido_pai_id' => isset($dados['pedido_pai_id']) ? $dados['pedido_pai_id'] : null,
            'versao' => isset($dados['versao']) ? $dados['versao'] : 1,
            'tipo_pedido' => isset($dados['tipo_pedido']) ? $dados['tipo_pedido'] : 'original',
            'edicao_gratuita' => isset($dados['edicao_gratuita']) ? $dados['edicao_gratuita'] : 0,
            'motivo_edicao' => isset($dados['motivo_edicao']) ? $dados['motivo_edicao'] : null,
            'programador_id' => isset($dados['programador_id']) ? $dados['programador_id'] : null,
            'data_atribuicao' => isset($dados['data_atribuicao']) ? $dados['data_atribuicao'] : null
        ),
        array(
            '%d', // cliente_id
            '%s', // nome_bordado
            '%s', // prazo_entrega
            '%f', // largura
            '%f', // altura
            '%s', // unidade_medida
            '%s', // local_bordado
            '%s', // tipo_tecido
            '%s', // cores
            '%s', // observacoes
            '%s', // arquivos_cliente
            '%s', // status
            '%s', // data_criacao
            
            // â­ FORMATOS PARA EDIÃ‡Ã•ES â­
            '%d', // pedido_pai_id
            '%d', // versao
            '%s', // tipo_pedido
            '%d', // edicao_gratuita
            '%s', // motivo_edicao
            '%d', // programador_id
            '%s'  // data_atribuicao
        )
    );
    
    // ============================================
    // âœ… ATRIBUIÃ‡ÃƒO AUTOMÃTICA v2.3
    // ============================================
    
if ($resultado) {
    $pedido_id = $wpdb->insert_id;
    
    $tipo_pedido = isset($dados['tipo_pedido']) ? $dados['tipo_pedido'] : 'original';
    
    if ($tipo_pedido === 'original') {
        
        // âœ… VERIFICAR SE JÃ FOI ATRIBUÃDO
        // Se jÃ¡ tem programador_id, nÃ£o precisa do hook
        if (empty($dados['programador_id'])) {
            
            // Pedido NÃƒO tem programador
            // Verificar se cliente tem atribuiÃ§Ã£o automÃ¡tica
            $atribuicao_automatica = get_user_meta($dados['cliente_id'], 'atribuicao_automatica', true);
            
            if ($atribuicao_automatica === 'yes') {
                // Agendar atribuiÃ§Ã£o automÃ¡tica
                wp_schedule_single_event(
                    time() + 2,
                    'bordados_processar_atribuicao',
                    array($pedido_id, $dados['cliente_id'])
                );
                
                error_log("Bordados: AtribuiÃ§Ã£o automÃ¡tica agendada para pedido #{$pedido_id}");
            }
        } else {
            error_log("Bordados: Pedido #{$pedido_id} jÃ¡ foi criado atribuÃ­do. Hook nÃ£o necessÃ¡rio.");
        }
    }
    
    return $pedido_id;
}
    
    return false;
}

    
    /**
     * Atualizar pedido
     */
    public static function atualizar_pedido($id, $dados, $formatos = null) {
        global $wpdb;
        
        return $wpdb->update(
            'pedidos_basicos',
            $dados,
            array('id' => $id),
            $formatos,
            array('%d')
        );
    }
    
    /**
     * Buscar pedido por ID
     */
    public static function buscar_pedido($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM pedidos_basicos WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Buscar pedido com dados do cliente e programador
     */
    public static function buscar_pedido_completo($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT p.*, 
                   c.user_email as cliente_email, 
                   c.display_name as cliente_nome,
                   prog.display_name as programador_nome
            FROM pedidos_basicos p
            JOIN {$wpdb->prefix}users c ON p.cliente_id = c.ID
            LEFT JOIN {$wpdb->prefix}users prog ON p.programador_id = prog.ID
            WHERE p.id = %d
        ", $id));
    }
/**
 * Buscar trabalhos aguardando revisÃ£o (fila aberta)
 */
public static function buscar_trabalhos_aguardando_revisao() {
    global $wpdb;
	$table = 'pedidos_basicos';
    $sql = "SELECT p.*, 
            c.display_name as cliente_nome,
            prog.display_name as programador_nome
            FROM $table p
            LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
            LEFT JOIN {$wpdb->users} prog ON p.programador_id = prog.ID
            WHERE p.status = 'aguardando_revisao'
            ORDER BY p.data_criacao ASC";
    
    return $wpdb->get_results($sql);
}

/**
 * Buscar trabalhos que o revisor estÃ¡ revisando
 */
public static function buscar_trabalhos_em_revisao($revisor_id) {
    global $wpdb;
$table = 'pedidos_basicos';
    
    $sql = $wpdb->prepare(
        "SELECT p.*, 
        c.display_name as cliente_nome,
        prog.display_name as programador_nome
        FROM $table p
        LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
        LEFT JOIN {$wpdb->users} prog ON p.programador_id = prog.ID
        WHERE p.revisor_id = %d AND p.status = 'em_revisao'
        ORDER BY p.data_inicio_revisao DESC",
        $revisor_id
    );
    
    return $wpdb->get_results($sql);
}

/**
 * Buscar trabalhos em acertos (voltaram do revisor para programador)
 */
public static function buscar_trabalhos_em_acertos($programador_id) {
    global $wpdb;
$table = 'pedidos_basicos';
    
    $sql = $wpdb->prepare(
        "SELECT p.*, 
        c.display_name as cliente_nome,
        rev.display_name as revisor_nome
        FROM $table p
        LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
        LEFT JOIN {$wpdb->users} rev ON p.revisor_id = rev.ID
        WHERE p.programador_id = %d AND p.status = 'em_acertos'
        ORDER BY p.data_fim_revisao DESC",
        $programador_id
    );
    
    return $wpdb->get_results($sql);
}
/**
 * FUNÇÕES ADICIONAIS PARA class-database.php
 * 
 * INSTRUÇÕES:
 * Adicione estas funções no final do arquivo class-database.php, antes do último }
 */

// ========== ADICIONAR AO FINAL DE class-database.php ==========

/**
 * Buscar orçamentos pendentes (para revisor)
 */
public static function buscar_orcamentos_pendentes() {
    global $wpdb;
    
    return $wpdb->get_results("
        SELECT 
            p.*,
            c.display_name as cliente_nome,
            c.user_email as cliente_email
        FROM pedidos_basicos p
        LEFT JOIN {$wpdb->prefix}users c ON p.cliente_id = c.ID
        WHERE p.status = 'orcamento_pendente'
        ORDER BY p.data_criacao ASC
    ");
}

/**
 * Buscar orçamentos enviados para um cliente
 */
public static function buscar_orcamentos_cliente($cliente_id) {
    global $wpdb;
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT 
            p.*,
            r.display_name as revisor_nome
        FROM pedidos_basicos p
        LEFT JOIN {$wpdb->prefix}users r ON p.revisor_id = r.ID
        WHERE p.cliente_id = %d 
        AND p.status = 'orcamento_enviado'
        ORDER BY p.data_criacao DESC
    ", $cliente_id));
}

/**
 * Atualizar orçamento (revisor envia preço)
 */
public static function enviar_orcamento($pedido_id, $dados) {
    global $wpdb;
    
    return $wpdb->update(
        'pedidos_basicos',
        array(
            'status' => 'orcamento_enviado',
            'revisor_id' => $dados['revisor_id'],
            'numero_pontos' => $dados['numero_pontos'],
            'preco_final' => $dados['preco_final'],
            'sistema_preco_usado' => $dados['sistema_preco_usado'],
            'preco_base_calculado' => $dados['preco_base_calculado'],
            'ajuste_manual_preco' => $dados['ajuste_manual_preco'],
            'motivo_ajuste_preco' => $dados['motivo_ajuste_preco'],
            'obs_revisor' => $dados['obs_revisor'],
            'data_inicio_revisao' => current_time('mysql')
        ),
        array('id' => $pedido_id),
        array('%s', '%d', '%d', '%f', '%s', '%f', '%f', '%s', '%s', '%s'),
        array('%d')
    );
}

/**
 * Aprovar orçamento (cliente aceita)
 */
public static function aprovar_orcamento($pedido_id) {
    global $wpdb;
    
    return $wpdb->update(
        'pedidos_basicos',
        array(
            'status' => 'novo',
            'data_fim_revisao' => current_time('mysql')
        ),
        array('id' => $pedido_id),
        array('%s', '%s'),
        array('%d')
    );
}

/**
 * Recusar orçamento (cliente recusa)
 */
public static function recusar_orcamento($pedido_id, $motivo = '') {
    global $wpdb;
    
    return $wpdb->update(
        'pedidos_basicos',
        array(
            'status' => 'orcamento_recusado',
            'observacoes' => $wpdb->get_var($wpdb->prepare(
                "SELECT CONCAT(IFNULL(observacoes, ''), '\n\n[QUOTE DECLINED] ', %s) FROM pedidos_basicos WHERE id = %d",
                $motivo, $pedido_id
            ))
        ),
        array('id' => $pedido_id),
        array('%s', '%s'),
        array('%d')
    );
}

/**
 * Buscar pedido completo com info de cliente e revisor
 */
public static function buscar_pedido_para_orcamento($pedido_id) {
    global $wpdb;
    
    return $wpdb->get_row($wpdb->prepare("
        SELECT 
            p.*,
            c.display_name as cliente_nome,
            c.user_email as cliente_email,
            r.display_name as revisor_nome
        FROM pedidos_basicos p
        LEFT JOIN {$wpdb->prefix}users c ON p.cliente_id = c.ID
        LEFT JOIN {$wpdb->prefix}users r ON p.revisor_id = r.ID
        WHERE p.id = %d
    ", $pedido_id));
}

// ========== FIM DAS FUNÇÕES ==========

}

?>
