<?php
/**
 * Classe para gerenciar campos administrativos dos PROGRAMADORES
 * 
 * Adiciona campos ao perfil do programador para:
 * - Status (Ativo/Inativo)
 * - Se faz vetorização
 * 
 * @package Sistema_Bordados
 * @since 2.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Programador_Admin {
    
    public function __construct() {
        // Adicionar campos ao perfil do programador
        add_action('show_user_profile', array($this, 'adicionar_campos_programador'));
        add_action('edit_user_profile', array($this, 'adicionar_campos_programador'));
        
        // Salvar campos
        add_action('personal_options_update', array($this, 'salvar_campos_programador'));
        add_action('edit_user_profile_update', array($this, 'salvar_campos_programador'));
    }
    
    /**
     * Adicionar campos personalizados ao perfil do programador
     */
    public function adicionar_campos_programador($user) {
        // Verificar se é programador
        if (!in_array('programador_bordados', (array) $user->roles)) {
            return;
        }
        
        // Buscar valores atuais
        $ativo = get_user_meta($user->ID, 'programador_ativo', true);
        $faz_vetorizacao = get_user_meta($user->ID, 'programador_faz_vetorizacao', true);
        
        // Default: ativo = yes, vetorização = no
        if (empty($ativo)) {
            $ativo = 'yes';
        }
        if (empty($faz_vetorizacao)) {
            $faz_vetorizacao = 'no';
        }
        
        // Contar trabalhos
        global $wpdb;
        $trabalhos_pendentes = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM pedidos_basicos 
            WHERE programador_id = %d 
            AND status IN ('atribuido', 'em_producao')
        ", $user->ID));
        
        $trabalhos_concluidos = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM pedidos_basicos 
            WHERE programador_id = %d 
            AND status = 'pronto'
        ", $user->ID));
        ?>
        
        <h2>⚙️ Configurações do Programador</h2>
        <table class="form-table">
            
            <!-- STATUS DO PROGRAMADOR -->
            <tr>
                <th>
                    <label for="programador_ativo">
                        Status do Programador
                    </label>
                </th>
                <td>
                    <label for="programador_ativo">
                        <input 
                            type="checkbox" 
                            name="programador_ativo" 
                            id="programador_ativo" 
                            value="yes" 
                            <?php checked($ativo, 'yes'); ?>
                        />
                        <strong>Programador Ativo</strong>
                    </label>
                    <p class="description">
                        <?php if ($ativo === 'yes'): ?>
                            ✅ <strong style="color: green;">Ativo</strong> - Pode receber novos trabalhos
                        <?php else: ?>
                            ❌ <strong style="color: red;">Inativo</strong> - NÃO receberá novos trabalhos
                        <?php endif; ?>
                    </p>
                    <p class="description">
                        ⚠️ <strong>Importante:</strong> Apenas programadores ativos são elegíveis para receber trabalhos na atribuição automática.
                    </p>
                </td>
            </tr>
            
            <!-- FAZ VETORIZAÇÃO -->
            <tr>
                <th>
                    <label for="programador_faz_vetorizacao">
                        Tipo de Serviços
                    </label>
                </th>
                <td>
                    <label for="programador_faz_vetorizacao">
                        <input 
                            type="checkbox" 
                            name="programador_faz_vetorizacao" 
                            id="programador_faz_vetorizacao" 
                            value="yes" 
                            <?php checked($faz_vetorizacao, 'yes'); ?>
                        />
                        <strong>Faz Vetorização de Imagens</strong>
                    </label>
                    <p class="description">
                        📋 <strong>Digitização de Bordados:</strong> Todos os programadores fazem<br>
                        🎨 <strong>Vetorização de Imagens:</strong> 
                        <?php if ($faz_vetorizacao === 'yes'): ?>
                            ✅ Este programador FAZ vetorização
                        <?php else: ?>
                            ❌ Este programador NÃO faz vetorização
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
            
            <!-- ESTATÍSTICAS -->
            <tr>
                <th>Estatísticas</th>
                <td>
                    <p>
                        <strong>📊 Trabalhos Pendentes:</strong> <?php echo intval($trabalhos_pendentes); ?><br>
                        <strong>✅ Trabalhos Concluídos:</strong> <?php echo intval($trabalhos_concluidos); ?>
                    </p>
                </td>
            </tr>
            
        </table>
        
        <!-- CSS movido para assets/bordados-modules.css (Fase 2) -->
        
        <?php
    }
    
    /**
     * Salvar campos personalizados do programador
     */
    public function salvar_campos_programador($user_id) {
        // Verificar permissões
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        // Verificar se é programador
        $user = get_userdata($user_id);
        if (!in_array('programador_bordados', (array) $user->roles)) {
            return false;
        }
        
        // Salvar status (ativo/inativo)
        $ativo = isset($_POST['programador_ativo']) && $_POST['programador_ativo'] === 'yes' ? 'yes' : 'no';
        update_user_meta($user_id, 'programador_ativo', $ativo);
        
        // Salvar se faz vetorização
        $faz_vetorizacao = isset($_POST['programador_faz_vetorizacao']) && $_POST['programador_faz_vetorizacao'] === 'yes' ? 'yes' : 'no';
        update_user_meta($user_id, 'programador_faz_vetorizacao', $faz_vetorizacao);
        
        return true;
    }
}

// Inicializar
new Bordados_Programador_Admin();
