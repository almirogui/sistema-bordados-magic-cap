<?php
/**
 * Sistema de Cálculo de Preços
 * 
 * Gerencia os diferentes sistemas de precificação:
 * - legacy_stitches: Preço por quantidade de pontos
 * - fixed_tier: Preço fixo por tamanho + dificuldade
 * - multiplier: Preço do programador × fator
 * 
 * @package Sistema_Bordados
 * @since 2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Precos {
    
    /**
     * Sistema de preço padrão para novos clientes
     */
    const SISTEMA_PADRAO = 'legacy_stitches';
    
    /**
     * Tabela de preços no banco de dados
     */
    private static $tabela_precos = null;
    
    /**
     * Obter nome da tabela de preços (detecta automaticamente)
     * 
     * @return string Nome da tabela
     */
    private static function get_tabela_precos() {
        if (self::$tabela_precos !== null) {
            return self::$tabela_precos;
        }
        
        global $wpdb;
        
        // Tentar com prefixo wp_
        $tabela_com_prefixo = $wpdb->prefix . 'bordados_tabela_precos';
        $existe = $wpdb->get_var("SHOW TABLES LIKE '{$tabela_com_prefixo}'");
        
        if ($existe) {
            self::$tabela_precos = $tabela_com_prefixo;
            return self::$tabela_precos;
        }
        
        // Tentar sem prefixo
        $tabela_sem_prefixo = 'bordados_tabela_precos';
        $existe = $wpdb->get_var("SHOW TABLES LIKE '{$tabela_sem_prefixo}'");
        
        if ($existe) {
            self::$tabela_precos = $tabela_sem_prefixo;
            return self::$tabela_precos;
        }
        
        // Fallback
        self::$tabela_precos = $tabela_sem_prefixo;
        return self::$tabela_precos;
    }
    
    /**
     * Calcular preço final para o cliente
     * 
     * @param int    $cliente_id        ID do cliente WordPress
     * @param int    $pontos            Número de pontos do bordado
     * @param string $tamanho           Tamanho: 'ate_10cm', '10_20cm', 'acima_20cm'
     * @param string $dificuldade       Dificuldade: 'facil', 'medio', 'complicado'
     * @param float  $preco_programador Preço informado pelo programador
     * 
     * @return array [
     *     'preco_final' => float,
     *     'sistema_usado' => string,
     *     'detalhes_calculo' => string
     * ]
     */
    public static function calcular_preco_final($cliente_id, $pontos = 0, $tamanho = '', $dificuldade = '', $preco_programador = 0) {
        
        // Buscar sistema de preço do cliente
        $sistema = self::get_sistema_cliente($cliente_id);
        
        $resultado = array(
            'preco_final' => 0,
            'sistema_usado' => $sistema,
            'detalhes_calculo' => ''
        );
        
        switch ($sistema) {
            case 'legacy_stitches':
                $resultado = self::calcular_legacy_stitches($pontos);
                break;
                
            case 'fixed_tier':
                $resultado = self::calcular_fixed_tier($tamanho, $dificuldade);
                break;
                
            case 'multiplier':
                $multiplicador = self::get_multiplicador_cliente($cliente_id);
                $resultado = self::calcular_multiplier($preco_programador, $multiplicador);
                break;
                
            case 'credits':
                // Credits system uses legacy_stitches calculation but deducts from balance
                $resultado = self::calcular_legacy_stitches($pontos);
                $resultado['sistema_usado'] = 'credits';
                $resultado['detalhes_calculo'] .= ' (prepaid credits)';
                break;
                
            default:
                // Fallback para legacy_stitches
                $resultado = self::calcular_legacy_stitches($pontos);
                $resultado['sistema_usado'] = 'legacy_stitches';
                break;
        }
        
        return $resultado;
    }
    
    /**
     * Obter sistema de preço do cliente
     * 
     * @param int $cliente_id ID do cliente
     * @return string Sistema de preço
     */
    public static function get_sistema_cliente($cliente_id) {
        // Try the standard key first
        $sistema = get_user_meta($cliente_id, 'sistema_preco', true);
        
        // Fallback to legacy key for backwards compatibility
        if (empty($sistema)) {
            $sistema = get_user_meta($cliente_id, 'bordados_sistema_preco', true);
        }
        
        if (empty($sistema)) {
            return self::SISTEMA_PADRAO;
        }
        
        return $sistema;
    }
    
    /**
     * Definir sistema de preço do cliente
     * 
     * @param int    $cliente_id ID do cliente
     * @param string $sistema    Sistema: 'legacy_stitches', 'fixed_tier', 'multiplier', 'credits'
     * @return bool
     */
    public static function set_sistema_cliente($cliente_id, $sistema) {
        $sistemas_validos = array('legacy_stitches', 'fixed_tier', 'multiplier', 'credits');
        
        if (!in_array($sistema, $sistemas_validos)) {
            return false;
        }
        
        return update_user_meta($cliente_id, 'sistema_preco', $sistema);
    }
    
    /**
     * Obter multiplicador do cliente
     * 
     * @param int $cliente_id ID do cliente
     * @return float Multiplicador (padrão 1.5)
     */
    public static function get_multiplicador_cliente($cliente_id) {
        // Try the standard key first
        $multiplicador = get_user_meta($cliente_id, 'multiplicador_preco', true);
        
        // Fallback to legacy key for backwards compatibility
        if (empty($multiplicador) || !is_numeric($multiplicador)) {
            $multiplicador = get_user_meta($cliente_id, 'bordados_multiplicador', true);
        }
        
        if (empty($multiplicador) || !is_numeric($multiplicador)) {
            return 1.5; // Padrão
        }
        
        return floatval($multiplicador);
    }
    
    /**
     * Definir multiplicador do cliente
     * 
     * @param int   $cliente_id   ID do cliente
     * @param float $multiplicador Fator multiplicador
     * @return bool
     */
    public static function set_multiplicador_cliente($cliente_id, $multiplicador) {
        if (!is_numeric($multiplicador) || $multiplicador <= 0) {
            return false;
        }
        
        return update_user_meta($cliente_id, 'multiplicador_preco', floatval($multiplicador));
    }
    
    /**
     * Calcular preço pelo sistema Legacy (pontos)
     * 
     * @param int $pontos Número de pontos
     * @return array
     */
    private static function calcular_legacy_stitches($pontos) {
        global $wpdb;
        
        $tabela = self::get_tabela_precos();
        
        // Buscar faixa de preço para a quantidade de pontos
        $faixa = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tabela} 
             WHERE tipo_sistema = 'legacy_stitches' 
             AND ativo = 1
             AND pontos_min <= %d 
             AND pontos_max >= %d
             LIMIT 1",
            $pontos, $pontos
        ));
        
        if (!$faixa) {
            // Se não encontrar faixa, usar a última (maior)
            $faixa = $wpdb->get_row(
                "SELECT * FROM {$tabela} 
                 WHERE tipo_sistema = 'legacy_stitches' 
                 AND ativo = 1
                 ORDER BY pontos_max DESC 
                 LIMIT 1"
            );
        }
        
        if (!$faixa) {
            return array(
                'preco_final' => 0,
                'sistema_usado' => 'legacy_stitches',
                'detalhes_calculo' => 'Erro: Tabela de preços não configurada'
            );
        }
        
        // Calcular: (pontos / 1000) × preço_por_mil
        $preco_final = ($pontos / 1000) * floatval($faixa->preco_por_mil);
        
        return array(
            'preco_final' => round($preco_final, 2),
            'sistema_usado' => 'legacy_stitches',
            'detalhes_calculo' => sprintf(
                '%d pontos × $%.2f/mil = $%.2f',
                $pontos,
                $faixa->preco_por_mil,
                $preco_final
            )
        );
    }
    
    /**
     * Calcular preço pelo sistema Fixed Tier (tamanho + dificuldade)
     * 
     * @param string $tamanho     Tamanho: 'ate_10cm', '10_20cm', 'acima_20cm'
     * @param string $dificuldade Dificuldade: 'facil', 'medio', 'complicado'
     * @return array
     */
    private static function calcular_fixed_tier($tamanho, $dificuldade) {
        global $wpdb;
        
        $tabela = self::get_tabela_precos();
        
        // Buscar preço fixo para tamanho + dificuldade
        $tier = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tabela} 
             WHERE tipo_sistema = 'fixed_tier' 
             AND ativo = 1
             AND tamanho_faixa = %s 
             AND nivel_dificuldade = %s
             LIMIT 1",
            $tamanho, $dificuldade
        ));
        
        if (!$tier) {
            return array(
                'preco_final' => 0,
                'sistema_usado' => 'fixed_tier',
                'detalhes_calculo' => sprintf(
                    'Erro: Preço não encontrado para %s / %s',
                    $tamanho,
                    $dificuldade
                )
            );
        }
        
        return array(
            'preco_final' => round(floatval($tier->preco_fixo), 2),
            'sistema_usado' => 'fixed_tier',
            'detalhes_calculo' => sprintf(
                'Tamanho: %s, Dificuldade: %s = $%.2f',
                $tamanho,
                $dificuldade,
                $tier->preco_fixo
            )
        );
    }
    
    /**
     * Calcular preço pelo sistema Multiplicador
     * 
     * @param float $preco_programador Preço do programador
     * @param float $multiplicador     Fator multiplicador
     * @return array
     */
    private static function calcular_multiplier($preco_programador, $multiplicador) {
        $preco_final = floatval($preco_programador) * floatval($multiplicador);
        
        return array(
            'preco_final' => round($preco_final, 2),
            'sistema_usado' => 'multiplier',
            'detalhes_calculo' => sprintf(
                '$%.2f × %.2f = $%.2f',
                $preco_programador,
                $multiplicador,
                $preco_final
            )
        );
    }
    
    /**
     * Obter nome amigável do sistema de preço
     * 
     * @param string $sistema Código do sistema
     * @return string Nome amigável
     */
    public static function get_nome_sistema($sistema) {
        $nomes = array(
            'legacy_stitches' => 'Price per Stitches',
            'fixed_tier' => 'Fixed Price (Size + Complexity)',
            'multiplier' => 'Custom Multiplier',
            'credits' => 'Prepaid Credits'
        );
        
        return isset($nomes[$sistema]) ? $nomes[$sistema] : $sistema;
    }
    
    /**
     * Listar todos os sistemas de preço disponíveis
     * 
     * @return array
     */
    public static function get_sistemas_disponiveis() {
        return array(
            'legacy_stitches' => 'Price per Stitches',
            'fixed_tier' => 'Fixed Price (Size + Complexity)',
            'multiplier' => 'Custom Multiplier',
            'credits' => 'Prepaid Credits'
        );
    }
    
    /**
     * Verificar se cliente tem sistema de preço configurado
     * 
     * @param int $cliente_id ID do cliente
     * @return bool
     */
    public static function cliente_tem_sistema_configurado($cliente_id) {
        $sistema = get_user_meta($cliente_id, 'sistema_preco', true);
        if (!empty($sistema)) {
            return true;
        }
        // Check legacy key
        $sistema = get_user_meta($cliente_id, 'bordados_sistema_preco', true);
        return !empty($sistema);
    }
    
    /**
     * Inicializar sistema de preço padrão para novo cliente
     * 
     * @param int $cliente_id ID do cliente
     * @return bool
     */
    public static function inicializar_cliente($cliente_id) {
        if (self::cliente_tem_sistema_configurado($cliente_id)) {
            return false; // Já configurado
        }
        
        return self::set_sistema_cliente($cliente_id, self::SISTEMA_PADRAO);
    }
    
    /**
     * Obter configuração completa de preço do cliente
     * 
     * @param int $cliente_id ID do cliente
     * @return array
     */
    public static function get_config_cliente($cliente_id) {
        return array(
            'sistema' => self::get_sistema_cliente($cliente_id),
            'sistema_nome' => self::get_nome_sistema(self::get_sistema_cliente($cliente_id)),
            'multiplicador' => self::get_multiplicador_cliente($cliente_id),
            'saldo_creditos' => self::get_saldo_creditos($cliente_id)
        );
    }
    
    /**
     * Obter saldo de créditos do cliente
     * 
     * @param int $cliente_id ID do cliente
     * @return float Saldo de créditos
     */
    public static function get_saldo_creditos($cliente_id) {
        $saldo = get_user_meta($cliente_id, 'saldo_creditos', true);
        
        if (empty($saldo) || !is_numeric($saldo)) {
            return 0.00;
        }
        
        return floatval($saldo);
    }
    
    /**
     * Definir saldo de créditos do cliente
     * 
     * @param int   $cliente_id ID do cliente
     * @param float $saldo      Novo saldo
     * @return bool
     */
    public static function set_saldo_creditos($cliente_id, $saldo) {
        if (!is_numeric($saldo) || $saldo < 0) {
            return false;
        }
        
        return update_user_meta($cliente_id, 'saldo_creditos', floatval($saldo));
    }
    
    /**
     * Adicionar créditos ao cliente
     * 
     * @param int   $cliente_id ID do cliente
     * @param float $valor      Valor a adicionar
     * @return float Novo saldo
     */
    public static function adicionar_creditos($cliente_id, $valor) {
        $saldo_atual = self::get_saldo_creditos($cliente_id);
        $novo_saldo = $saldo_atual + floatval($valor);
        self::set_saldo_creditos($cliente_id, $novo_saldo);
        return $novo_saldo;
    }
    
    /**
     * Debitar créditos do cliente
     * 
     * @param int   $cliente_id ID do cliente
     * @param float $valor      Valor a debitar
     * @return float|false Novo saldo ou false se saldo insuficiente
     */
    public static function debitar_creditos($cliente_id, $valor) {
        $saldo_atual = self::get_saldo_creditos($cliente_id);
        
        if ($saldo_atual < $valor) {
            return false; // Saldo insuficiente
        }
        
        $novo_saldo = $saldo_atual - floatval($valor);
        self::set_saldo_creditos($cliente_id, $novo_saldo);
        return $novo_saldo;
    }
    
    /**
     * Verificar se cliente tem créditos suficientes
     * 
     * @param int   $cliente_id ID do cliente
     * @param float $valor      Valor necessário
     * @return bool
     */
    public static function tem_creditos_suficientes($cliente_id, $valor) {
        return self::get_saldo_creditos($cliente_id) >= floatval($valor);
    }
}
