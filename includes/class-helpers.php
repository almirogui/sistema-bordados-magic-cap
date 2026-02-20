<?php
/**
 * Classe com funções auxiliares
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Helpers {
    
    /**
     * Forçar HTTPS em URLs de arquivos
     * Corrige URLs salvas com http:// para https://
     * 
     * @param string $url URL do arquivo
     * @return string URL com HTTPS
     */
    public static function forcar_https($url) {
        if (empty($url)) {
            return $url;
        }
        // Substituir http:// por https://
        return str_replace('http://', 'https://', $url);
    }
    
    /**
     * Adicionar campo "Programador Padrão" no perfil do usuário
     */
    public static function adicionar_campo_programador_padrao($user) {
        if (!current_user_can('edit_users')) return;
        
        $programador_padrao = get_user_meta($user->ID, 'programador_padrao', true);
        $programadores = self::listar_programadores();
        ?>
        <h3>Configurações Bordados</h3>
        <table class="form-table">
            <tr>
                <th><label for="programador_padrao">Programador Padrão</label></th>
                <td>
                    <select name="programador_padrao" id="programador_padrao">
                        <option value="">Selecione um programador</option>
                        <?php foreach ($programadores as $prog): ?>
                            <option value="<?php echo $prog->ID; ?>" <?php selected($programador_padrao, $prog->ID); ?>>
                                <?php echo $prog->display_name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Programador que receberá automaticamente os pedidos deste cliente.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Listar programadores disponíveis
     */
    public static function listar_programadores() {
        $args = array(
            'role__in' => array('programador_bordados', 'administrator'),
            'orderby' => 'display_name',
            'order' => 'ASC'
        );
        
        return get_users($args);
    }

    /**
     * Salvar campo programador padrão
     */
    public static function salvar_campo_programador_padrao($user_id) {
        if (!current_user_can('edit_users')) return;
        
        if (isset($_POST['programador_padrao'])) {
            update_user_meta($user_id, 'programador_padrao', sanitize_text_field($_POST['programador_padrao']));
        }
    }
    
    /**
     * NOVO: Adicionar campo: Cliente requer revisão
     */
    public static function adicionar_campo_requer_revisao($user) {
        if (!current_user_can('edit_users')) return;
        
        // Só mostrar para clientes
        if (!in_array('cliente_bordados', $user->roles)) return;
        
        $requer_revisao = get_user_meta($user->ID, 'requer_revisao', true);
        ?>
        <h3>Configurações de Revisão</h3>
        <table class="form-table">
            <tr>
                <th><label for="requer_revisao">Requer Revisão?</label></th>
                <td>
                    <input type="checkbox" name="requer_revisao" id="requer_revisao" 
                           value="1" <?php checked($requer_revisao, '1'); ?>>
                    <p class="description">
                        Se marcado, os trabalhos deste cliente passarão por revisão antes de serem entregues.<br>
                        <strong>Nota:</strong> Trabalhos com revisão podem ter preço diferenciado.
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * NOVO: Adicionar campos: Embaixador
     */
    public static function adicionar_campos_embaixador($user) {
        if (!current_user_can('edit_users')) return;
        
        $embaixador_id = get_user_meta($user->ID, 'embaixador_id', true);
        $comissao_percentual = get_user_meta($user->ID, 'comissao_percentual', true);
        
        // Lista de embaixadores
        $embaixadores = get_users(array('role' => 'embaixador_bordados'));
        
        ?>
        <h3>Sistema de Comissões</h3>
        <table class="form-table">
            <?php if (in_array('cliente_bordados', $user->roles)): ?>
            <tr>
                <th><label for="embaixador_id">Indicado por Embaixador</label></th>
                <td>
                    <select name="embaixador_id" id="embaixador_id">
                        <option value="">Nenhum</option>
                        <?php foreach ($embaixadores as $emb): ?>
                            <option value="<?php echo $emb->ID; ?>" <?php selected($embaixador_id, $emb->ID); ?>>
                                <?php echo esc_html($emb->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        Embaixador que indicou este cliente.<br>
                        <strong>Nota:</strong> O embaixador receberá comissão sobre os pedidos deste cliente.
                    </p>
                </td>
            </tr>
            <?php endif; ?>
            
            <?php if (in_array('embaixador_bordados', $user->roles)): ?>
            <tr>
                <th><label for="comissao_percentual">Percentual de Comissão (%)</label></th>
                <td>
                    <input type="number" name="comissao_percentual" id="comissao_percentual" 
                           value="<?php echo esc_attr($comissao_percentual); ?>" 
                           step="0.01" min="0" max="100" style="width: 100px;">
                    <span>%</span>
                    <p class="description">
                        Percentual de comissão sobre o <strong>preço final</strong> dos pedidos dos clientes indicados.<br>
                        Exemplo: 10% em pedido de R$ 50,00 = R$ 5,00 de comissão.
                    </p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    /**
     * NOVO: Salvar campo: Requer revisão
     */
    public static function salvar_campo_requer_revisao($user_id) {
        if (!current_user_can('edit_users')) return;
        
        if (isset($_POST['requer_revisao'])) {
            update_user_meta($user_id, 'requer_revisao', '1');
        } else {
            update_user_meta($user_id, 'requer_revisao', '0');
        }
    }
    
    /**
     * NOVO: Salvar campos: Embaixador
     */
    public static function salvar_campos_embaixador($user_id) {
        if (!current_user_can('edit_users')) return;
        
        if (isset($_POST['embaixador_id'])) {
            update_user_meta($user_id, 'embaixador_id', sanitize_text_field($_POST['embaixador_id']));
        }
        
        if (isset($_POST['comissao_percentual'])) {
            update_user_meta($user_id, 'comissao_percentual', floatval($_POST['comissao_percentual']));
        }
    }
    
    /**
     * Helper para status badge (português)
     */
    public static function get_status_badge($status) {
        $badges = array(
            'novo' => '<span class="status-badge novo">🆕 Novo</span>',
            'atribuido' => '<span class="status-badge atribuido">👨‍💻 Atribuído</span>',
            'em_producao' => '<span class="status-badge producao">⚙️ Em Produção</span>',
            'aguardando_revisao' => '<span class="status-badge aguardando">⏳ Aguardando Revisão</span>',
            'em_revisao' => '<span class="status-badge revisao">🔍 Em Revisão</span>',
            'em_acertos' => '<span class="status-badge acertos">🔧 Em Acertos</span>',
            'pronto' => '<span class="status-badge pronto">🎉 Pronto</span>',
            'edicao_solicitada' => '<span class="status-badge edicao">✏️ Edição Solicitada</span>',
            'edicao_em_producao' => '<span class="status-badge edicao-prod">⚙️ Edição em Produção</span>'
        );
        
        return isset($badges[$status]) ? $badges[$status] : $status;
    }

    /**
     * Status badges em inglês
     */
    public static function get_status_badge_english($status) {
        $badges = array(
            'novo' => '<span class="status-badge novo">🆕 New</span>',
            'atribuido' => '<span class="status-badge atribuido">👨‍💻 Assigned</span>',
            'em_producao' => '<span class="status-badge producao">⚙️ In Production</span>',
            'aguardando_revisao' => '<span class="status-badge aguardando">⏳ Awaiting Review</span>',
            'em_revisao' => '<span class="status-badge revisao">🔍 In Review</span>',
            'em_acertos' => '<span class="status-badge acertos">🔧 Corrections Needed</span>',
            'pronto' => '<span class="status-badge pronto">🎉 Ready</span>',
            'edicao_solicitada' => '<span class="status-badge edicao">✏️ Edit Requested</span>',
            'edicao_em_producao' => '<span class="status-badge edicao-prod">⚙️ Edit in Production</span>',
            'orcamento_pendente' => '<span class="status-badge orcamento">💰 Quote Pending</span>',
            'orcamento_enviado' => '<span class="status-badge orcamento-env">📨 Quote Sent</span>',
            'orcamento_recusado' => '<span class="status-badge orcamento-rec">❌ Quote Declined</span>'
        );
        
        return isset($badges[$status]) ? $badges[$status] : $status;
    }

    /**
     * Obter badge do prazo de entrega (português)
     */
    public static function get_prazo_badge($prazo) {
        switch ($prazo) {
            case 'URGENTE - RUSH':
                return '<span style="background: #dc3545; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;">🔥 URGENTE</span>';
            case 'Normal':
            default:
                return '<span style="background: #28a745; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px;">📅 Normal</span>';
        }
    }

    /**
     * Prazo badges em inglês
     */
    public static function get_prazo_badge_english($prazo) {
        switch ($prazo) {
            case 'URGENTE - RUSH':
                return '<span style="background: #dc3545; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;">🔥 URGENT</span>';
            case 'Normal':
            default:
                return '<span style="background: #28a745; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px;">📅 Normal</span>';
        }
    }
    
    /**
     * Redirecionamento após login
     */
    public static function redirecionar_apos_login($redirect_to, $request, $user) {
        if (!is_wp_error($user) && isset($user->ID)) {
            if (in_array('programador_bordados', $user->roles)) {
                return site_url('/painel-programador/');
            }
            
            if (in_array('revisor_bordados', $user->roles)) {
                return site_url('/painel-revisor/');
            }
            
            if (in_array('embaixador_bordados', $user->roles)) {
                return site_url('/painel-embaixador/');
            }
            
            if (in_array('cliente_bordados', $user->roles)) {
                return site_url('/meus-pedidos/');
            }
            
            if (in_array('administrator', $user->roles)) {
                return site_url('/admin-pedidos/');
            }
        }
        
        return $redirect_to;
    }
    
    /**
     * Obter primeiro arquivo de uma lista
     */
    public static function obter_primeiro_arquivo($pedido) {
        $primeiro_arquivo = $pedido->arquivo_referencia;
        
        if (!empty($pedido->arquivos_cliente)) {
            $arquivos = json_decode($pedido->arquivos_cliente, true);
            if (is_array($arquivos) && !empty($arquivos)) {
                $primeiro_arquivo = $arquivos[0];
            }
        }
        
        return $primeiro_arquivo;
    }
    
    /**
     * Obter todos os arquivos do cliente
     */
    public static function obter_arquivos_cliente($pedido) {
        $arquivos_cliente = array();
        
        if (!empty($pedido->arquivos_cliente)) {
            $arquivos_cliente = json_decode($pedido->arquivos_cliente, true);
            if (!is_array($arquivos_cliente)) $arquivos_cliente = array();
        }
        
        // Adicionar arquivo_referencia se não estiver na lista
        if (!empty($pedido->arquivo_referencia) && !in_array($pedido->arquivo_referencia, $arquivos_cliente)) {
            array_unshift($arquivos_cliente, $pedido->arquivo_referencia);
        }
        
        return $arquivos_cliente;
    }
    
    /**
     * Verificar se arquivo é imagem
     */
    public static function is_imagem($arquivo) {
        $file_ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
        return in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    /**
     * Formatar dimensões do bordado (VERSÃO CORRIGIDA EM INGLÊS)
     */
    public static function formatar_dimensoes($pedido) {
        $dimensoes = array();
        
        // Verificar largura
        if (!empty($pedido->largura) && $pedido->largura > 0) {
            $unidade = !empty($pedido->unidade_medida) ? $pedido->unidade_medida : 'cm';
            $dimensoes[] = number_format($pedido->largura, 1) . ' ' . $unidade . ' (Width)';
        }
        
        // Verificar altura
        if (!empty($pedido->altura) && $pedido->altura > 0) {
            $unidade = !empty($pedido->unidade_medida) ? $pedido->unidade_medida : 'cm';
            $dimensoes[] = number_format($pedido->altura, 1) . ' ' . $unidade . ' (Height)';
        }
        
        // Retornar resultado
        if (empty($dimensoes)) {
            return '<span style="color: #999; font-style: italic;">Not specified</span>';
        }
        
        return implode(' × ', $dimensoes);
    }

    /**
     * Formatar local do bordado para exibição
     */
    public static function formatar_local_bordado($local) {
        // Extrair apenas a parte em português
        $partes = explode(' . ', $local);
        $parte_pt = !empty($partes[0]) ? $partes[0] : $local;
        
        // Traduzir para inglês (para dashboard do programador)
        $traducoes = array(
            'frente boné' => 'Cap front',
            'Lateral boné' => 'Cap side',
            'Atrás no boné' => 'Cap back',
            'Peito esquerdo' => 'Left chest',
            'costas jaqueta' => 'Full back',
            'Outro lugar' => 'Other place'
        );
        
        return isset($traducoes[$parte_pt]) ? $traducoes[$parte_pt] : $parte_pt;
    }

    /**
     * Formatar tipo de tecido para exibição
     */
    public static function formatar_tipo_tecido($tecido) {
        // Extrair apenas a parte principal
        $partes = explode(' - ', $tecido);
        return !empty($partes[0]) ? $partes[0] : $tecido;
    }

    /**
     * Validar campos obrigatórios do pedido
     */
    public static function validar_dados_pedido($dados) {
        $erros = array();
        
        // Campos obrigatórios básicos
        $campos_obrigatorios = array(
            'nome_bordado' => 'Nome do bordado',
            'prazo_entrega' => 'Prazo de entrega',
            'local_bordado' => 'Local do bordado'
        );
        
        foreach ($campos_obrigatorios as $campo => $nome) {
            if (empty($dados[$campo])) {
                $erros[] = $nome . ' é obrigatório.';
            }
        }
        
        // Validar dimensões (pelo menos uma)
        $largura = isset($dados['largura']) ? floatval($dados['largura']) : 0;
        $altura = isset($dados['altura']) ? floatval($dados['altura']) : 0;
        
        if ($largura <= 0 && $altura <= 0) {
            $erros[] = 'Informe pelo menos uma dimensão (largura OU altura).';
        }
        
        // Validar unidade de medida
        if (empty($dados['unidade_medida']) || !in_array($dados['unidade_medida'], array('cm', 'in'))) {
            $erros[] = 'Unidade de medida inválida.';
        }
        
        return $erros;
    }

    /**
     * Obter lista de opções para formulários
     */
    public static function get_opcoes_formulario() {
        return array(
            'prazo_entrega' => array(
                'Normal' => 'Normal',
                'URGENTE - RUSH' => 'URGENTE - RUSH'
            ),
            
            'unidade_medida' => array(
                'cm' => 'cm (centímetros)',
                'in' => 'in (polegadas)'
            ),
            
            'local_bordado' => array(
                'frente boné . cap front' => 'frente boné . cap front',
                'Lateral boné . cap side' => 'Lateral boné . cap side',
                'Atrás no boné . cap back' => 'Atrás no boné . cap back',
                'Peito esquerdo . left chest' => 'Peito esquerdo . left chest',
                'costas jaqueta . full back' => 'costas jaqueta . full back',
                'Outro lugar . other place' => 'Outro lugar . other place'
            ),
            
            'tipo_tecido' => array(
                'Brush Cotton Twill - brim normal' => 'Brush Cotton Twill - brim normal',
                'Canvas - brim grosso/lona' => 'Canvas - brim grosso/lona',
                'Denim - brim rustico / jeans fino' => 'Denim - brim rustico / jeans fino',
                'Leather - Couro' => 'Leather - Couro',
                'Micro Fiber - microfibra' => 'Micro Fiber - microfibra',
                'Nylon' => 'Nylon',
                'Jersey - tipo de lã' => 'Jersey - tipo de lã',
                'Pique - polo' => 'Pique - polo',
                'Fleece - moleton felpudo' => 'Fleece - moleton felpudo',
                'Terry cloth / Towel - toalha' => 'Terry cloth / Towel - toalha',
                'Knit - tricô/lãzinha' => 'Knit - tricô/lãzinha',
                'Velvet - veludo' => 'Velvet - veludo',
                'Suede - camurça' => 'Suede - camurça',
                'Sweatshirts - moleton' => 'Sweatshirts - moleton',
                'Woven - lã de carneiro bruta' => 'Woven - lã de carneiro bruta',
                'T-shirt - malha' => 'T-shirt - malha',
                'lycra' => 'lycra',
                'poplin - popeline' => 'poplin - popeline',
                'vinyl - vinil' => 'vinyl - vinil',
                'DRY-FIT - tecido fino' => 'DRY-FIT - tecido fino'
            ),
            
            'cores' => array(
                '' => 'Não sei / Deixar programador decidir',
                '1' => '1 cor',
                '2' => '2 cores',
                '3' => '3 cores',
                '4' => '4 cores',
                '5+' => '5 ou mais cores'
            )
        );
    }

    /**
     * Traduzir texto usando Google Translate (versão gratuita)
     */
    public static function traduzir_google_free($texto, $idioma_origem = 'pt', $idioma_destino = 'en') {
        if (empty($texto) || strlen($texto) < 3) return $texto;
        
        // Verificar cache primeiro
        $cache_key = 'traducao_' . md5($texto . $idioma_origem . $idioma_destino);
        $traducao_cache = get_transient($cache_key);
        
        if (false !== $traducao_cache) {
            return $traducao_cache;
        }
        
        try {
            // URL do Google Translate (endpoint público)
            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl={$idioma_origem}&tl={$idioma_destino}&dt=t&q=" . urlencode($texto);
            
            // Fazer requisição
            $response = wp_remote_get($url, array(
                'timeout' => 15,
                'headers' => array(
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                )
            ));
            
            // Verificar se requisição foi bem-sucedida
            if (is_wp_error($response)) {
                error_log('Erro Google Translate: ' . $response->get_error_message());
                return '[Translation failed] ' . $texto;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                error_log('Google Translate HTTP Error: ' . $http_code);
                return '[Translation unavailable] ' . $texto;
            }
            
            $body = wp_remote_retrieve_body($response);
            $json = json_decode($body, true);
            
            // Extrair tradução do JSON
            if (isset($json[0]) && is_array($json[0])) {
                $traducao = '';
                foreach ($json[0] as $segment) {
                    if (isset($segment[0])) {
                        $traducao .= $segment[0];
                    }
                }
                
                if (!empty($traducao)) {
                    // Salvar no cache por 7 dias
                    set_transient($cache_key, $traducao, 7 * DAY_IN_SECONDS);
                    return $traducao;
                }
            }
            
            // Se chegou aqui, algo deu errado
            error_log('Google Translate Parse Error: ' . $body);
            return '[Translation error] ' . $texto;
            
        } catch (Exception $e) {
            error_log('Google Translate Exception: ' . $e->getMessage());
            return '[Translation failed] ' . $texto;
        }
    }

    /**
     * Exibir observações com tradução bilíngue
     */
    public static function exibir_observacoes_bilingue($observacoes) {
        if (empty($observacoes)) return '';
        
        // Traduzir o texto
        $traducao = self::traduzir_google_free($observacoes);
        
        // HTML com visual profissional
        $html = '
        <div style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding: 20px; border-radius: 10px; margin: 15px 0; border-left: 4px solid #007cba;">
            <h4 style="margin: 0 0 15px 0; color: #333; font-size: 16px;">📝 Customer Instructions</h4>
            
            <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; margin-bottom: 8px;">
                    <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; margin-right: 10px;">🇧🇷 PORTUGUÊS</span>
                </div>
                <p style="margin: 0; font-style: italic; color: #444; line-height: 1.5;">"' . esc_html($observacoes) . '"</p>
            </div>
            
            <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; margin-bottom: 8px;">
                    <span style="background: #007cba; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; margin-right: 10px;">🇺🇸 ENGLISH</span>
                    <small style="color: #666; font-size: 10px;">Auto-translated</small>
                </div>
                <p style="margin: 0; color: #0066cc; font-weight: 500; line-height: 1.5;">"' . esc_html($traducao) . '"</p>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Gerar HTML para exibir arquivo
     */
    public static function exibir_arquivo($arquivo, $tamanho = '50px') {
        if (empty($arquivo)) {
            return '<span style="color: #999;">-</span>';
        }
        
        // Forçar HTTPS
        $arquivo_https = self::forcar_https($arquivo);
        
        if (self::is_imagem($arquivo)) {
            return '<img src="' . esc_url($arquivo_https) . '" 
                         style="width: ' . $tamanho . '; height: ' . $tamanho . '; object-fit: cover; border-radius: 5px; cursor: pointer;" 
                         onclick="mostrarImagemGrande(\'' . esc_url($arquivo_https) . '\')"
                         title="Clique para ampliar">';
        } else {
            $file_ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
            return '<span style="font-size: 24px;" title="' . $file_ext . '">📄</span>';
        }
    }
    
    /**
     * Formatar data brasileira
     */
    public static function formatar_data($data) {
        return wp_date('d/m/Y', strtotime($data));
    }
    
    /**
     * Formatar data e hora brasileira
     */
    public static function formatar_data_hora($data) {
        $ts = strtotime($data); $utc = gmdate('c', $ts); return '<span class="data-local" data-utc="' . esc_attr($utc) . '">' . wp_date('d/m/Y H:i', $ts) . ' ' . wp_date('T', $ts) . '</span>';
    }
    
    /**
     * Formatar preço brasileiro
     */
    public static function formatar_preco($preco) {
        return 'R$ ' . number_format($preco, 2, ',', '.');
    }
}

?>
