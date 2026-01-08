<?php
/**
 * Shortcode: Dashboard Embaixador - [bordados_dashboard_embaixador]
 * Extraído de class-shortcodes.php na Fase 3 da modularização
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Shortcode_Embaixador {
    
    /**
     * Renderizar dashboard embaixador
     */
    public static function render($atts) {
    if (!is_user_logged_in()) {
        return '<p>Você precisa estar logado para acessar o dashboard.</p>';
    }
    
    $user = wp_get_current_user();
    if (!in_array('embaixador_bordados', $user->roles) && !in_array('administrator', $user->roles)) {
        return '<p>Acesso restrito a embaixadores.</p>';
    }
    
    // Buscar percentual de comissão
    $comissao_percentual = get_user_meta($user->ID, 'comissao_percentual', true);
    
    ob_start();
    ?>
    <div class="bordados-dashboard-embaixador">
        <h3>💰 Dashboard do Embaixador</h3>
        
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0; color: white;">�â€˜â€¹ Olá, <?php echo esc_html($user->display_name); ?>!</h4>
            <p style="margin: 0; font-size: 18px;">
                <strong>Sua Comissão:</strong> <?php echo !empty($comissao_percentual) ? number_format($comissao_percentual, 2) . '%' : 'Não configurada'; ?>
            </p>
        </div>
        
        <?php if (empty($comissao_percentual)): ?>
        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0;">⚠️ Atenção</h4>
            <p style="margin: 0;">
                Seu percentual de comissão ainda não foi configurado. Entre em contato com o administrador.
            </p>
        </div>
        <?php endif; ?>
        
        <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0;">🏧 Dashboard em Desenvolvimento</h4>
            <p style="margin: 0;">
                <strong>Status:</strong> Estrutura criada com sucesso! ✅<br>
                <strong>Próximos passos:</strong> Implementação do sistema de comissões e relatórios.
            </p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
            <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #4caf50;">
                <h4 style="margin: 0 0 10px 0;">👁¥ Clientes Indicados</h4>
                <p style="font-size: 32px; margin: 0; color: #4caf50; font-weight: bold;">0</p>
                <small style="color: #666;">Total de clientes ativos</small>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3;">
                <h4 style="margin: 0 0 10px 0;">💵 Comissões Este Mês</h4>
                <p style="font-size: 32px; margin: 0; color: #2196f3; font-weight: bold;">R$ 0,00</p>
                <small style="color: #666;">Mês atual</small>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #ff9800;">
                <h4 style="margin: 0 0 10px 0;">�â€œÅ Total Acumulado</h4>
                <p style="font-size: 32px; margin: 0; color: #ff9800; font-weight: bold;">R$ 0,00</p>
                <small style="color: #666;">Desde o início</small>
            </div>
        </div>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
            <h4>📋 Suas Funcionalidades:</h4>
            <ul>
                <li>✅ Ver lista de clientes indicados</li>
                <li>✅ Acompanhar pedidos dos seus clientes</li>
                <li>✅ Visualizar comissões recebidas</li>
                <li>✅ Relatórios mensais e anuais</li>
                <li>✅ Histórico completo de ganhos</li>
            </ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
}

?>
