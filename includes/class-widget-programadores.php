<?php
/**
 * Widget Dashboard - Status dos Programadores
 * 
 * Mostra lista de programadores com:
 * - Status (Ativo/Inativo)
 * - Se faz vetorização
 * - Trabalhos pendentes
 * - Trabalhos concluídos
 * 
 * @package Sistema_Bordados
 * @since 2.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Widget_Programadores {
    
    public function __construct() {
        // Adicionar widget ao dashboard admin
        add_action('wp_dashboard_setup', array($this, 'adicionar_widget'));
    }
    
    /**
     * Adicionar widget ao dashboard
     */
    public function adicionar_widget() {
        // Apenas para admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'bordados_status_programadores',
            '👨‍💻 Status dos Programadores',
            array($this, 'renderizar_widget')
        );
    }
    
    /**
     * Renderizar conteúdo do widget
     */
    public function renderizar_widget() {
        if (!class_exists('Bordados_Atribuicao_Automatica')) {
            echo '<p>Sistema de atribuição automática não está disponível.</p>';
            return;
        }
        
        $programadores = Bordados_Atribuicao_Automatica::listar_programadores_status();
        
        if (empty($programadores)) {
            echo '<p>Nenhum programador cadastrado.</p>';
            return;
        }
        ?>
        
        <!-- CSS movido para assets/bordados-modules.css (Fase 2) -->
        
        <table class="bordados-programadores-table">
            <thead>
                <tr>
                    <th>Programador</th>
                    <th>Status</th>
                    <th>Vetorização</th>
                    <th>Pendentes</th>
                    <th>Concluídos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($programadores as $prog): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($prog->nome); ?></strong>
                    </td>
                    <td>
                        <?php if ($prog->ativo === 'yes'): ?>
                            <span class="status-ativo">✅ Ativo</span>
                        <?php else: ?>
                            <span class="status-inativo">❌ Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($prog->faz_vetorizacao === 'yes'): ?>
                            🎨 Sim
                        <?php else: ?>
                            📋 Apenas bordados
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($prog->trabalhos_pendentes > 0): ?>
                            <span class="badge-trabalhos badge-pendentes">
                                <?php echo intval($prog->trabalhos_pendentes); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #999;">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-trabalhos badge-concluidos">
                            <?php echo intval($prog->trabalhos_concluidos); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p style="margin-top: 15px; font-size: 12px; color: #666;">
            💡 <strong>Dica:</strong> Programadores inativos não recebem trabalhos na atribuição automática.
        </p>
        
        <?php
    }
}

// Inicializar
new Bordados_Widget_Programadores();
