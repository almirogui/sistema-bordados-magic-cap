<?php
/**
 * Adicionar campo de Atribuição Automática ao perfil do CLIENTE
 * 
 * Permite admin configurar se o cliente terá atribuição automática
 * de trabalhos quando não tiver programador padrão definido.
 * 
 * @package Sistema_Bordados
 * @since 2.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Cliente_Atribuicao_Auto {
    
    public function __construct() {
        // Adicionar campo ao perfil do cliente
        add_action('show_user_profile', array($this, 'adicionar_campo_atribuicao_auto'));
        add_action('edit_user_profile', array($this, 'adicionar_campo_atribuicao_auto'));
        
        // Salvar campo
        add_action('personal_options_update', array($this, 'salvar_campo_atribuicao_auto'));
        add_action('edit_user_profile_update', array($this, 'salvar_campo_atribuicao_auto'));
    }
    
    /**
     * Adicionar campo de atribuição automática ao perfil
     */
    public function adicionar_campo_atribuicao_auto($user) {
        // Verificar se é cliente
        if (!in_array('cliente_bordados', (array) $user->roles)) {
            return;
        }
        
        // Verificar se é admin editando
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Buscar valores atuais
        $programador_padrao = get_user_meta($user->ID, 'programador_padrao', true);
        $atribuicao_automatica = get_user_meta($user->ID, 'atribuicao_automatica', true);
        
        // Default: se não tem programador padrão, sugerir atribuição automática
        if (empty($atribuicao_automatica)) {
            $atribuicao_automatica = empty($programador_padrao) ? 'yes' : 'no';
        }
        
        // Buscar nome do programador padrão (se existir)
        $programador_nome = '';
        if (!empty($programador_padrao)) {
            $prog = get_userdata($programador_padrao);
            $programador_nome = $prog ? $prog->display_name : 'Programador removido';
        }
        ?>
        
        <h2>🤖 Atribuição de Trabalhos</h2>
        <table class="form-table">
            
            <!-- INFORMAÇÃO SOBRE PROGRAMADOR PADRÃO -->
            <?php if (!empty($programador_padrao)): ?>
            <tr>
                <th>Status Atual</th>
                <td>
                    <p style="background: #d4edda; padding: 10px; border-left: 4px solid #28a745;">
                        ✅ <strong>Este cliente TEM programador padrão definido:</strong><br>
                        👤 <?php echo esc_html($programador_nome); ?>
                    </p>
                    <p class="description">
                        Todos os trabalhos deste cliente são automaticamente atribuídos para 
                        <strong><?php echo esc_html($programador_nome); ?></strong>.
                    </p>
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <th>Status Atual</th>
                <td>
                    <p style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;">
                        ⚠️ <strong>Este cliente NÃO TEM programador padrão definido</strong>
                    </p>
                    <p class="description">
                        Configure abaixo como os trabalhos deste cliente serão atribuídos.
                    </p>
                </td>
            </tr>
            <?php endif; ?>
            
            <!-- CAMPO ATRIBUIÇÃO AUTOMÁTICA -->
            <tr>
                <th>
                    <label for="atribuicao_automatica">
                        Atribuição Automática
                    </label>
                </th>
                <td>
                    <label for="atribuicao_automatica">
                        <input 
                            type="checkbox" 
                            name="atribuicao_automatica" 
                            id="atribuicao_automatica" 
                            value="yes" 
                            <?php checked($atribuicao_automatica, 'yes'); ?>
                            <?php disabled(!empty($programador_padrao)); ?>
                        />
                        <strong>Atribuir trabalhos automaticamente</strong>
                    </label>
                    
                    <?php if (!empty($programador_padrao)): ?>
                        <p class="description" style="color: #999;">
                            ⚠️ <strong>Desabilitado:</strong> Cliente tem programador padrão definido.
                            Remova o programador padrão para habilitar atribuição automática.
                        </p>
                    <?php else: ?>
                        <p class="description">
                            <?php if ($atribuicao_automatica === 'yes'): ?>
                                ✅ <strong style="color: green;">ATIVO</strong> - Trabalhos serão atribuídos automaticamente<br>
                                <br>
                                <strong>Como funciona:</strong><br>
                                1️⃣ Cliente cria pedido<br>
                                2️⃣ Sistema atribui automaticamente para programador ATIVO com MENOS trabalhos<br>
                                3️⃣ Programador recebe email imediatamente<br>
                                4️⃣ Admin NÃO precisa fazer nada! 🎉
                            <?php else: ?>
                                ❌ <strong style="color: red;">DESATIVADO</strong> - Admin precisará atribuir manualmente<br>
                                <br>
                                <strong>Se desativado:</strong><br>
                                1️⃣ Cliente cria pedido<br>
                                2️⃣ Pedido fica com status "Novo"<br>
                                3️⃣ Admin precisa atribuir manualmente (ou clicar em "Atribuir Automaticamente")
                            <?php endif; ?>
                        </p>
                        
                        <p class="description" style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-left: 4px solid #2196F3;">
                            💡 <strong>Dica:</strong> 
                            <?php if ($atribuicao_automatica === 'yes'): ?>
                                Para controle manual, desmarque esta opção.
                            <?php else: ?>
                                Para economizar tempo, marque esta opção e deixe o sistema escolher automaticamente.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            
            <!-- PRIORIDADE DE ATRIBUIÇÃO -->
            <tr>
                <th>Prioridade de Atribuição</th>
                <td>
                    <ol style="margin: 0; padding-left: 20px;">
                        <li>
                            <strong>Programador Padrão</strong> 
                            <?php echo !empty($programador_padrao) ? '✅ (Ativo)' : '❌ (Não definido)'; ?>
                            <br>
                            <small>Se definido, sempre atribui para este programador</small>
                        </li>
                        <li>
                            <strong>Atribuição Automática</strong> 
                            <?php echo $atribuicao_automatica === 'yes' && empty($programador_padrao) ? '✅ (Ativo)' : '❌ (Inativo)'; ?>
                            <br>
                            <small>Se habilitado, sistema escolhe automaticamente</small>
                        </li>
                        <li>
                            <strong>Atribuição Manual</strong> 
                            <?php echo empty($programador_padrao) && $atribuicao_automatica !== 'yes' ? '✅ (Ativo)' : '❌ (Não usado)'; ?>
                            <br>
                            <small>Admin atribui manualmente no dashboard</small>
                        </li>
                    </ol>
                </td>
            </tr>
            
        </table>
        
        <!-- CSS movido para assets/bordados-modules.css (Fase 2) -->
        
        <?php
    }
    
    /**
     * Salvar campo de atribuição automática
     */
    public function salvar_campo_atribuicao_auto($user_id) {
        // Verificar permissões
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        // Verificar se é cliente
        $user = get_userdata($user_id);
        if (!in_array('cliente_bordados', (array) $user->roles)) {
            return false;
        }
        
        // Verificar se tem programador padrão
        $programador_padrao = get_user_meta($user_id, 'programador_padrao', true);
        
        // Se tem programador padrão, forçar atribuição automática = no
        if (!empty($programador_padrao)) {
            update_user_meta($user_id, 'atribuicao_automatica', 'no');
            return true;
        }
        
        // Salvar atribuição automática
        $atribuicao_auto = isset($_POST['atribuicao_automatica']) && $_POST['atribuicao_automatica'] === 'yes' ? 'yes' : 'no';
        update_user_meta($user_id, 'atribuicao_automatica', $atribuicao_auto);
        
        return true;
    }
}

// Inicializar
new Bordados_Cliente_Atribuicao_Auto();
