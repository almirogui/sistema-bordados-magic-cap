<?php
/**
 * Classe para gerenciar envio de emails
 *
 * ATUALIZADO:
 * - Funções para orçamentos (Etapa 3)
 * - Suporte a CC para email_secundario (2026-01-09)
 * - Suporte a múltiplos emails separados por vírgula no campo secundário
 * - NOVO: Confirmação de pedido para cliente (inglês) e admin (português) (2026-01-10)
 * - NOVO: Ocultar nome do cliente do programador + Email com downloads categorizados (2026-01-14) v3.3.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Emails {

    /**
     * ========================================
     * FUNÇÃO AUXILIAR: Obter headers com CC
     * ========================================
     *
     * Busca o email_secundario do cliente e adiciona como CC.
     * Suporta múltiplos emails separados por vírgula.
     *
     * @param int $cliente_id ID do cliente
     * @param string $from_name Nome do remetente
     * @param string $from_email Email do remetente (opcional)
     * @return array Headers para wp_mail()
     */
    private static function get_headers_com_cc($cliente_id, $from_name = 'Puncher Digitizing', $from_email = null) {
        // Email do remetente
        if (empty($from_email)) {
            $from_email = 'noreply@' . $_SERVER['HTTP_HOST'];
        }

        // Headers básicos
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );

        // Buscar email secundário do cliente
        $email_secundario = get_user_meta($cliente_id, 'email_secundario', true);

        if (!empty($email_secundario)) {
            // Suporta múltiplos emails separados por vírgula
            $emails_cc = array_map('trim', explode(',', $email_secundario));

            // Filtrar apenas emails válidos
            $emails_validos = array();
            foreach ($emails_cc as $email) {
                if (is_email($email)) {
                    $emails_validos[] = $email;
                }
            }

            // Adicionar CC se houver emails válidos
            if (!empty($emails_validos)) {
                $headers[] = 'Cc: ' . implode(', ', $emails_validos);
                error_log("📧 CC adicionado para cliente #{$cliente_id}: " . implode(', ', $emails_validos));
            }
        }

        return $headers;
    }

    /**
     * ========================================
     * NOVO: Confirmação de pedido para CLIENTE (em inglês)
     * ========================================
     * ✅ COM SUPORTE A CC
     */
    public static function enviar_confirmacao_pedido_cliente($pedido_id) {
        global $wpdb;
        
        // Buscar dados do pedido
        $pedido = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, c.display_name as cliente_nome, c.user_email as cliente_email
             FROM pedidos_basicos p
             LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
             WHERE p.id = %d",
            $pedido_id
        ));
        
        if (!$pedido || empty($pedido->cliente_email)) {
            error_log("❌ Não foi possível enviar confirmação: pedido #{$pedido_id} não encontrado ou sem email");
            return false;
        }
        
        $para = $pedido->cliente_email;
        $assunto = 'Order Received - #' . $pedido_id . ' - ' . $pedido->nome_bordado;
        
        // Formatar tamanho
        $tamanho = '';
        if (!empty($pedido->largura) && !empty($pedido->altura)) {
            $unidade = !empty($pedido->unidade_medida) ? $pedido->unidade_medida : 'cm';
            $tamanho = $pedido->largura . ' x ' . $pedido->altura . ' ' . $unidade;
        }
        
        // Template em inglês
        $mensagem = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0;'>✅ Order Received!</h1>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border: 1px solid #e0e0e0;'>
                <p style='font-size: 16px;'>Hello <strong>{$pedido->cliente_nome}</strong>,</p>
                
                <p>Thank you for your order! We have received your embroidery digitizing request and it is now in our queue.</p>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e0e0e0;'>
                    <h3 style='margin: 0 0 15px 0; color: #333;'>📋 Order Details</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Order Number:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong style='color: #667eea;'>#{$pedido_id}</strong></td>
                        </tr>
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Design Name:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$pedido->nome_bordado}</td>
                        </tr>
                        " . (!empty($tamanho) ? "
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Size:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$tamanho}</td>
                        </tr>
                        " : "") . "
                        " . (!empty($pedido->local_bordado) ? "
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Placement:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$pedido->local_bordado}</td>
                        </tr>
                        " : "") . "
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Status:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'>
                                <span style='background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 15px; font-size: 13px;'>🆕 Received</span>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding: 10px; color: #666;'><strong>Date:</strong></td>
                            <td style='padding: 10px;'>" . date('F j, Y - g:i A', strtotime($pedido->data_criacao)) . "</td>
                        </tr>
                    </table>
                </div>
                
                <div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin: 0 0 10px 0; color: #1565c0;'>📌 What happens next?</h4>
                    <ul style='margin: 0; padding-left: 20px; color: #333;'>
                        <li>Your order will be assigned to one of our expert digitizers</li>
                        <li>You will receive an email when production begins</li>
                        <li>Once completed, you'll receive your files by email</li>
                    </ul>
                </div>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . site_url('/meus-pedidos/') . "' 
                       style='background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                       👀 Track Your Order
                    </a>
                </p>
                
                <p style='color: #666; font-size: 14px;'>If you have any questions, simply reply to this email.</p>
                
                <p>Best regards,<br><strong>Puncher Digitizing Team</strong></p>
            </div>
            
            <div style='background: #333; color: #999; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; font-size: 12px;'>
                © " . date('Y') . " Puncher Embroidery Digitizing<br>
                <a href='https://puncher.com' style='color: #667eea;'>www.puncher.com</a>
            </div>
        </div>
        ";
        
        // Headers COM CC para emails secundários
        $headers = self::get_headers_com_cc($pedido->cliente_id, 'Puncher Digitizing', 'noreply@puncher.com');
        
        // Enviar email
        $enviado = wp_mail($para, $assunto, $mensagem, $headers);
        
        if ($enviado) {
            error_log("✅ Email de confirmação enviado para cliente {$para} (pedido #{$pedido_id})");
        } else {
            error_log("❌ Falha ao enviar email de confirmação para {$para}");
        }
        
        return $enviado;
    }

    /**
     * ========================================
     * NOVO: Notificar ADMIN sobre novo pedido (em português)
     * ========================================
     */
    public static function notificar_admin_novo_pedido($pedido_id) {
        global $wpdb;
        
        // Buscar dados do pedido
        $pedido = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, c.display_name as cliente_nome, c.user_email as cliente_email
             FROM pedidos_basicos p
             LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
             WHERE p.id = %d",
            $pedido_id
        ));
        
        if (!$pedido) {
            error_log("❌ Não foi possível notificar admin: pedido #{$pedido_id} não encontrado");
            return false;
        }
        
        // Email do administrador
        $para = 'puncher@puncher.com';
        $assunto = '🆕 Novo Pedido #' . $pedido_id . ' - ' . $pedido->nome_bordado;
        
        // Formatar tamanho
        $tamanho = '';
        if (!empty($pedido->largura) && !empty($pedido->altura)) {
            $unidade = !empty($pedido->unidade_medida) ? $pedido->unidade_medida : 'cm';
            $tamanho = $pedido->largura . ' x ' . $pedido->altura . ' ' . $unidade;
        }
        
        // Verificar status de atribuição
        $status_atribuicao = '';
        if (!empty($pedido->programador_id)) {
            $programador = get_userdata($pedido->programador_id);
            $prog_nome = $programador ? $programador->display_name : 'ID: ' . $pedido->programador_id;
            $status_atribuicao = "<span style='background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 15px;'>✅ Atribuído para {$prog_nome}</span>";
        } else {
            $status_atribuicao = "<span style='background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 15px;'>⏳ Aguardando atribuição</span>";
        }
        
        // Template em português
        $mensagem = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0;'>🆕 Novo Pedido Recebido!</h1>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border: 1px solid #e0e0e0;'>
                <p style='font-size: 16px;'>Um novo pedido de bordado foi recebido no sistema.</p>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e0e0e0;'>
                    <h3 style='margin: 0 0 15px 0; color: #333;'>📋 Detalhes do Pedido</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666; width: 40%;'><strong>Número:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong style='color: #28a745; font-size: 18px;'>#{$pedido_id}</strong></td>
                        </tr>
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Cliente:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$pedido->cliente_nome}<br><small style='color: #999;'>{$pedido->cliente_email}</small></td>
                        </tr>
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Nome do Bordado:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$pedido->nome_bordado}</td>
                        </tr>
                        " . (!empty($tamanho) ? "
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Tamanho:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$tamanho}</td>
                        </tr>
                        " : "") . "
                        " . (!empty($pedido->local_bordado) ? "
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Local:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$pedido->local_bordado}</td>
                        </tr>
                        " : "") . "
                        " . (!empty($pedido->tipo_tecido) ? "
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Tecido:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$pedido->tipo_tecido}</td>
                        </tr>
                        " : "") . "
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Prazo:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$pedido->prazo_entrega}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Status:</strong></td>
                            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$status_atribuicao}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px; color: #666;'><strong>Data/Hora:</strong></td>
                            <td style='padding: 10px;'>" . date('d/m/Y H:i', strtotime($pedido->data_criacao)) . "</td>
                        </tr>
                    </table>
                </div>
                
                " . (!empty($pedido->observacoes) ? "
                <div style='background: #fff3e0; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin: 0 0 10px 0; color: #e65100;'>📝 Observações do Cliente:</h4>
                    <p style='margin: 0; color: #333;'>{$pedido->observacoes}</p>
                </div>
                " : "") . "
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . site_url('/painel-assistente/') . "' 
                       style='background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; margin: 5px;'>
                       👩‍💼 Painel Assistente
                    </a>
                    <a href='" . site_url('/admin-pedidos/') . "' 
                       style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; margin: 5px;'>
                       📋 Admin Pedidos
                    </a>
                </p>
            </div>
            
            <div style='background: #333; color: #999; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; font-size: 12px;'>
                Sistema de Bordados - Magic Cap<br>
                Notificação automática
            </div>
        </div>
        ";
        
        // Headers para HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Sistema Bordados <noreply@puncher.com>'
        );
        
        // Enviar email
        $enviado = wp_mail($para, $assunto, $mensagem, $headers);
        
        if ($enviado) {
            error_log("✅ Notificação de novo pedido enviada para admin {$para} (pedido #{$pedido_id})");
        } else {
            error_log("❌ Falha ao enviar notificação para admin {$para}");
        }
        
        return $enviado;
    }

    /**
     * Enviar email para programador sobre novo trabalho
     * (Programador não precisa de CC - é email interno)
     */
    public static function enviar_novo_trabalho($programador_id, $pedido_id, $dados_pedido) {
        $programador = get_userdata($programador_id);
        $cliente = get_userdata($dados_pedido['cliente_id']);

        if (!$programador || !$cliente) {
            return false;
        }

        // Dados do email
        $para = $programador->user_email;
        $assunto = 'New Embroidery Work - Order #' . $pedido_id;

        // Montar mensagem
        $mensagem = self::template_novo_trabalho($programador, $cliente, $pedido_id, $dados_pedido);

        // Headers para HTML (sem CC - email interno)
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Sistema Bordados <noreply@' . $_SERVER['HTTP_HOST'] . '>'
        );

        // Enviar email
        return wp_mail($para, $assunto, $mensagem, $headers);
    }

    /**
     * Enviar email para cliente quando produção for iniciada
     * ✅ COM SUPORTE A CC
     */
    public static function enviar_producao_iniciada($pedido) {
        if (!$pedido || empty($pedido->cliente_email)) {
            return false;
        }

        // Dados do email
        $para = $pedido->cliente_email;
        $assunto = 'Your embroidery is in production - Order #' . $pedido->id;

        // Montar mensagem
        $mensagem = self::template_producao_iniciada($pedido);

        // ✅ Headers COM CC para emails secundários
        $headers = self::get_headers_com_cc($pedido->cliente_id, 'Puncher Digitizing');

        // Enviar email
        $enviado = wp_mail($para, $assunto, $mensagem, $headers);

        if ($enviado) {
            error_log("✅ Email 'produção iniciada' enviado para {$para} (pedido #{$pedido->id})");
        } else {
            error_log("❌ Falha ao enviar email 'produção iniciada' para {$para}");
        }

        return $enviado;
    }

    /**
     * Enviar email para cliente quando trabalho for concluído
     * ✅ COM SUPORTE A CC
     */
    public static function enviar_trabalho_concluido($pedido, $arquivos_finais) {
        $cliente = get_userdata($pedido->cliente_id);

        if (!$cliente) {
            return false;
        }

        // Dados do email
        $para = $cliente->user_email;
        $assunto = 'Your embroidery is ready! - Order #' . $pedido->id;

        // Montar mensagem
        $mensagem = self::template_trabalho_concluido($pedido, $cliente, $arquivos_finais);

        // ✅ Headers COM CC para emails secundários
        $headers = self::get_headers_com_cc($pedido->cliente_id, 'Puncher Digitizing');

        // Enviar email
        $enviado = wp_mail($para, $assunto, $mensagem, $headers);

        if ($enviado) {
            error_log("✅ Email 'trabalho concluído' enviado para {$para} (pedido #{$pedido->id})");
        } else {
            error_log("❌ Falha ao enviar email 'trabalho concluído' para {$para}");
        }

        return $enviado;
    }

    /**
     * ⭐ ETAPA 3: Enviar orçamento para cliente
     * ✅ COM SUPORTE A CC
     */
    public static function enviar_orcamento_cliente($pedido_id, $dados_orcamento) {
        $pedido = Bordados_Database::buscar_pedido_completo($pedido_id);

        if (!$pedido) {
            error_log("❌ Pedido #{$pedido_id} não encontrado para enviar orçamento");
            return false;
        }

        $cliente = get_userdata($pedido->cliente_id);
        if (!$cliente) {
            error_log("❌ Cliente não encontrado para pedido #{$pedido_id}");
            return false;
        }

        // Dados do email
        $para = $cliente->user_email;
        $assunto = 'Quote Ready - Order #' . $pedido_id . ' - ' . $pedido->nome_bordado;

        // Montar mensagem
        $mensagem = self::template_orcamento_cliente($pedido, $cliente, $dados_orcamento);

        // ✅ Headers COM CC para emails secundários
        $headers = self::get_headers_com_cc($pedido->cliente_id, 'Puncher Embroidery', 'noreply@puncher.com');

        // Enviar email
        $enviado = wp_mail($para, $assunto, $mensagem, $headers);

        if ($enviado) {
            error_log("✅ Email de orçamento enviado para {$cliente->user_email} (pedido #{$pedido_id})");
        } else {
            error_log("❌ Falha ao enviar email de orçamento para {$cliente->user_email}");
        }

        return $enviado;
    }

    /**
     * ⭐ ETAPA 3: Notificar admin quando orçamento for aprovado
     * (Sem CC - email para admin)
     */
    public static function notificar_orcamento_aprovado($pedido_id) {
        $pedido = Bordados_Database::buscar_pedido_completo($pedido_id);

        if (!$pedido) {
            return false;
        }

        $cliente = get_userdata($pedido->cliente_id);

        // Email do admin
        $para = get_option('admin_email');
        $assunto = '✅ Quote Approved - Order #' . $pedido_id;

        // Montar mensagem
        $mensagem = self::template_orcamento_aprovado($pedido, $cliente);

        // Headers para HTML (sem CC - email interno)
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Puncher System <noreply@puncher.com>'
        );

        // Enviar email
        $enviado = wp_mail($para, $assunto, $mensagem, $headers);

        if ($enviado) {
            error_log("✅ Notificação de orçamento aprovado enviada para admin");
        }

        return $enviado;
    }

    /**
     * Notificar programador sobre novo trabalho atribuído
     * (Sem CC - email interno)
     * ✅ CORRIGIDO v3.3.2: Cliente oculto do programador
     */
    public static function notificar_programador_novo_trabalho($pedido_id) {
        global $wpdb;

        $pedido = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, c.display_name as cliente_nome
             FROM pedidos_basicos p
             LEFT JOIN {$wpdb->users} c ON p.cliente_id = c.ID
             WHERE p.id = %d",
            $pedido_id
        ));

        if (!$pedido || empty($pedido->programador_id)) {
            return false;
        }

        $programador = get_userdata($pedido->programador_id);
        if (!$programador) {
            return false;
        }

        $para = $programador->user_email;
        $assunto = 'New Work Assigned - Order #' . $pedido_id;

        $mensagem = "
        <h2>🎉 New Work Assigned!</h2>
        <p>Hello <strong>{$programador->display_name}</strong>,</p>
        <p>A new embroidery order has been assigned to you:</p>
        <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Order:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>#{$pedido->id}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Customer:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>Puncher.com</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Design:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$pedido->nome_bordado}</td>
            </tr>
        </table>
        <p><a href='" . site_url('/painel-programador/') . "' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View My Work</a></p>
        <p>Best regards,<br>Puncher Digitizing System</p>
        ";

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Puncher System <noreply@puncher.com>'
        );

        return wp_mail($para, $assunto, $mensagem, $headers);
    }
/**
 * ============================================
 * ADICIONAR A class-emails.php
 * ============================================
 * 
 * Cole este método na classe Bordados_Emails
 * (após o método notificar_programador_novo_trabalho)
 * 
 * @package Sistema_Bordados
 * @since 3.3.3
 * @date 2026-01-16
 */

/**
 * NOVO: Notificar programador sobre edição no pedido
 * 
 * Envia email para o programador quando há alterações em um pedido
 * que já está atribuído a ele.
 * 
 * @param int $pedido_id ID do pedido
 * @param array $campos_alterados Lista de campos que foram modificados
 * @return bool Sucesso do envio
 */
public static function notificar_programador_edicao($pedido_id, $campos_alterados = array()) {
    global $wpdb;
    
    // Buscar dados do pedido com programador
    $pedido = $wpdb->get_row($wpdb->prepare("
        SELECT p.*, 
               prog.user_email as programador_email,
               prog.display_name as programador_nome
        FROM pedidos_basicos p
        LEFT JOIN {$wpdb->users} prog ON p.programador_id = prog.ID
        WHERE p.id = %d
    ", $pedido_id));
    
    // Verificar se tem programador atribuído
    if (!$pedido || empty($pedido->programador_id) || empty($pedido->programador_email)) {
        error_log("❌ Pedido #{$pedido_id} não tem programador atribuído - email de edição não enviado");
        return false;
    }
    
    // Dados do email
    $para = $pedido->programador_email;
    $assunto = '✏️ Order Updated - #' . $pedido_id . ' - ' . $pedido->nome_bordado;
    
    // Montar lista de alterações
    $lista_alteracoes = '';
    if (!empty($campos_alterados) && is_array($campos_alterados)) {
        $lista_alteracoes = '<ul style="margin: 10px 0; padding-left: 20px;">';
        foreach ($campos_alterados as $campo) {
            $lista_alteracoes .= '<li style="margin: 5px 0;">' . esc_html($campo) . '</li>';
        }
        $lista_alteracoes .= '</ul>';
    }
    
    // Template do email
    $mensagem = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); padding: 20px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0; font-size: 24px;'>✏️ Order Updated</h1>
        </div>
        
        <div style='background: #f9f9f9; padding: 30px; border: 1px solid #ddd;'>
            <p style='font-size: 16px; color: #333;'>
                Hello <strong>{$pedido->programador_nome}</strong>,
            </p>
            
            <p style='font-size: 16px; color: #333;'>
                An order assigned to you has been updated:
            </p>
            
            <table style='border-collapse: collapse; width: 100%; margin: 20px 0; background: white; border-radius: 8px; overflow: hidden;'>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 12px; background: #f8f9fa; width: 40%;'><strong>Order:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 12px;'><strong>#" . $pedido->id . "</strong></td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 12px; background: #f8f9fa;'><strong>Design:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 12px;'>" . esc_html($pedido->nome_bordado) . "</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 12px; background: #f8f9fa;'><strong>Status:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 12px;'>" . esc_html(ucfirst($pedido->status)) . "</td>
                </tr>
            </table>
            ";
    
    // Adicionar lista de alterações se houver
    if (!empty($lista_alteracoes)) {
        $mensagem .= "
            <div style='background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                <strong style='color: #856404;'>📝 Changes made:</strong>
                {$lista_alteracoes}
            </div>
        ";
    }
    
    $mensagem .= "
            <p style='font-size: 14px; color: #666;'>
                Please review the updated details in your panel.
            </p>
            
            <p style='text-align: center; margin: 30px 0;'>
                <a href='" . site_url('/painel-programador/') . "' 
                   style='background: #f39c12; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                   👁️ View Updated Order
                </a>
            </p>
        </div>
        
        <div style='background: #333; color: #999; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; font-size: 12px;'>
            Sistema de Bordados - Magic Cap<br>
            Automatic notification
        </div>
    </div>
    ";
    
    // Headers para HTML
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Puncher System <noreply@puncher.com>'
    );
    
    // Enviar email
    $enviado = wp_mail($para, $assunto, $mensagem, $headers);
    
    if ($enviado) {
        error_log("✅ Email de edição enviado para programador {$pedido->programador_nome} ({$para}) - Pedido #{$pedido_id}");
    } else {
        error_log("❌ Falha ao enviar email de edição para {$para} - Pedido #{$pedido_id}");
    }
    
    return $enviado;
}

    /**
     * Enviar notificação de atribuição (alias)
     */
    public static function enviar_notificacao_atribuicao($pedido_id, $programador_id) {
        return self::notificar_programador_novo_trabalho($pedido_id);
    }

    /**
     * Enviar email de novo trabalho (alias)
     * ✅ CORRIGIDO v3.3.2: Cliente oculto do programador
     */
    public static function enviar_email_novo_trabalho($email, $nome, $dados) {
        $assunto = 'New Work Assigned - Order #' . $dados['pedido_id'];

        $mensagem = "
        <h2>🎉 New Work Assigned!</h2>
        <p>Hello <strong>{$nome}</strong>,</p>
        <p>A new embroidery order has been assigned to you:</p>
        <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Order:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>#{$dados['pedido_id']}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Customer:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>Puncher.com</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Design:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$dados['nome_bordado']}</td>
            </tr>
        </table>
        <p><strong>Notes:</strong><br>{$dados['observacoes']}</p>
        <p><a href='" . site_url('/painel-programador/') . "' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View My Work</a></p>
        <p>Best regards,<br>Puncher Digitizing System</p>
        ";

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Puncher System <noreply@puncher.com>'
        );

        return wp_mail($email, $assunto, $mensagem, $headers);
    }

    // ========================================
    // TEMPLATES DE EMAIL
    // ========================================

    /**
     * Template: Email novo trabalho
     * ✅ CORRIGIDO v3.3.2: Cliente oculto do programador
     */
    private static function template_novo_trabalho($programador, $cliente, $pedido_id, $dados_pedido) {
        $cores = isset($dados_pedido['cores']) ? $dados_pedido['cores'] : '';
        $observacoes = isset($dados_pedido['observacoes']) ? $dados_pedido['observacoes'] : '';
        $tamanho = isset($dados_pedido['tamanho']) ? $dados_pedido['tamanho'] : '';

        return "
        <h2>New Work Assigned</h2>

        <p>Hello <strong>{$programador->display_name}</strong>,</p>

        <p>A new embroidery work has been assigned to you:</p>

        <table style='border-collapse: collapse; width: 100%;'>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px;'><strong>Order:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>#{$pedido_id}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px;'><strong>Customer:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>Puncher.com</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px;'><strong>Design:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$dados_pedido['nome_bordado']}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px;'><strong>Size:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$tamanho}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px;'><strong>Colors:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$cores}</td>
            </tr>
        </table>

        <p><strong>Notes:</strong><br>
        {$observacoes}</p>

        <p><a href='" . site_url('/painel-programador/') . "' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View My Work</a></p>

        <p>Best regards,<br>
        Puncher Digitizing System</p>
        ";
    }

    /**
     * Template: Email produção iniciada
     */
    private static function template_producao_iniciada($pedido) {
        $tamanho = '';
        if (!empty($pedido->largura) && !empty($pedido->altura)) {
            $unidade = !empty($pedido->unidade_medida) ? $pedido->unidade_medida : 'cm';
            $tamanho = $pedido->largura . ' x ' . $pedido->altura . ' ' . $unidade;
        } elseif (!empty($pedido->tamanho)) {
            $tamanho = $pedido->tamanho;
        }

        $programador_nome = !empty($pedido->programador_nome) ? $pedido->programador_nome : 'Our digitizer';

        return "
        <h2>🎉 Your embroidery is now in production!</h2>

        <p>Hello <strong>{$pedido->cliente_nome}</strong>,</p>

        <p>Great news! Your embroidery order is now in production.</p>

        <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Order:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>#{$pedido->id}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Design:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$pedido->nome_bordado}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Size:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$tamanho}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Status:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'><span style='background: #e8f5e8; color: #388e3c; padding: 4px 8px; border-radius: 5px;'>⚙️ In Production</span></td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Digitizer:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$programador_nome}</td>
            </tr>
        </table>

        <div style='background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <h3 style='margin: 0 0 10px 0; color: #1976d2;'>📋 What happens now?</h3>
            <ul style='margin: 0; padding-left: 20px;'>
                <li>Our digitizer is creating your custom embroidery design</li>
                <li>You will receive another email when it's ready</li>
                <li>You can track the status in real-time on your dashboard</li>
            </ul>
        </div>

        <p style='text-align: center; margin: 30px 0;'>
            <a href='" . site_url('/meus-pedidos/') . "'
               style='background: #0073aa; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
               👀 View Order Status
            </a>
        </p>

        <p>If you have any questions, please contact us.</p>

        <p>Best regards,<br>
        <strong>Puncher Digitizing Team</strong></p>
        ";
    }

    /**
     * Template: Email trabalho concluído
     * ✅ NOVO v3.3.2: Downloads categorizados por tipo de arquivo + imagem inline
     */
    private static function template_trabalho_concluido($pedido, $cliente, $arquivos_finais) {
        
        // Usar preco_final se disponível, senão usar preco_programador
        $preco_exibir = !empty($pedido->preco_final) ? $pedido->preco_final : (!empty($pedido->preco_programador) ? $pedido->preco_programador : 0);
        
        // Observações do programador
        $observacoes_prog = !empty($pedido->observacoes_programador) ? $pedido->observacoes_programador : '';
        
        // ============================================================
        // CATEGORIZAR ARQUIVOS POR TIPO
        // ============================================================
        
        $arquivo_emb = null;
        $arquivo_maquina = null;
        $arquivo_pdf = null;
        $arquivo_imagem = null;
        
        $extensoes_maquina = array('dst', 'pes', 'jef', 'exp', 'vp3', 'hus', 'pec', 'pcs', 'sew', 'xxx');
        $extensoes_imagem = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        
        if (is_array($arquivos_finais) && !empty($arquivos_finais)) {
            foreach ($arquivos_finais as $arquivo) {
                $extensao = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
                $arquivo_https = Bordados_Helpers::forcar_https($arquivo);
                
                if ($extensao === 'emb' && !$arquivo_emb) {
                    $arquivo_emb = $arquivo_https;
                } elseif (in_array($extensao, $extensoes_maquina) && !$arquivo_maquina) {
                    $arquivo_maquina = array('url' => $arquivo_https, 'extensao' => strtoupper($extensao));
                } elseif ($extensao === 'pdf' && !$arquivo_pdf) {
                    $arquivo_pdf = $arquivo_https;
                } elseif (in_array($extensao, $extensoes_imagem) && !$arquivo_imagem) {
                    $arquivo_imagem = $arquivo_https;
                }
            }
        }
        
        // ============================================================
        // MONTAR HTML DO EMAIL
        // ============================================================
        
        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 24px;'>🎉 Your embroidery is ready!</h1>
            </div>
            <div style='background: #f8f9fa; padding: 30px; border: 1px solid #e0e0e0;'>
                <p style='font-size: 16px;'>Hello <strong>{$cliente->display_name}</strong>,</p>
                <p>Great news! Your embroidery order has been completed and is now available for download.</p>
        ";
        
        // Imagem do bordado inline
        if ($arquivo_imagem) {
            $html .= "
                <div style='text-align: center; margin: 25px 0;'>
                    <img src='{$arquivo_imagem}' alt='Embroidery Preview' style='max-width: 300px; width: 100%; height: auto; border-radius: 10px; border: 3px solid #e0e0e0; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                </div>
            ";
        }
        
        // Tabela de detalhes
        $html .= "
                <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
                    <tr>
                        <td style='border: 1px solid #ddd; padding: 12px; background: #f8f9fa; width: 35%;'><strong>Order:</strong></td>
                        <td style='border: 1px solid #ddd; padding: 12px;'><strong style='color: #28a745;'>#{$pedido->id}</strong></td>
                    </tr>
                    <tr>
                        <td style='border: 1px solid #ddd; padding: 12px; background: #f8f9fa;'><strong>Design:</strong></td>
                        <td style='border: 1px solid #ddd; padding: 12px;'>{$pedido->nome_bordado}</td>
                    </tr>
                    <tr>
                        <td style='border: 1px solid #ddd; padding: 12px; background: #f8f9fa;'><strong>Status:</strong></td>
                        <td style='border: 1px solid #ddd; padding: 12px;'><span style='background: #d4edda; color: #155724; padding: 5px 12px; border-radius: 15px; font-size: 13px;'>🎉 Ready</span></td>
                    </tr>
                    <tr>
                        <td style='border: 1px solid #ddd; padding: 12px; background: #f8f9fa;'><strong>Price:</strong></td>
                        <td style='border: 1px solid #ddd; padding: 12px;'><strong style='font-size: 18px; color: #28a745;'>$ " . number_format($preco_exibir, 2) . "</strong></td>
                    </tr>
                </table>
        ";
        
        // Seção de downloads
        $html .= "
                <div style='background: #e8f5e9; padding: 25px; border-radius: 10px; margin: 25px 0;'>
                    <h3 style='margin: 0 0 20px 0; color: #2e7d32; font-size: 18px;'>📥 Download Your Files:</h3>
        ";
        
        $btn_style = "display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 13px;";
        
        // EMB
        if ($arquivo_emb) {
            $html .= "
                    <div style='background: white; padding: 15px; border-radius: 8px; margin-bottom: 12px; border: 1px solid #c8e6c9;'>
                        <table style='width: 100%;'><tr>
                            <td style='vertical-align: middle;'>
                                <span style='font-size: 24px; margin-right: 10px;'>🧵</span>
                                <strong style='color: #333;'>Native Design File</strong>
                                <br><small style='color: #666; margin-left: 34px;'>Wilcom EMB - Editable source file</small>
                            </td>
                            <td style='text-align: right; vertical-align: middle;'>
                                <a href='{$arquivo_emb}' target='_blank' style='{$btn_style}'>⬇️ Download EMB</a>
                            </td>
                        </tr></table>
                    </div>
            ";
        }
        
        // Machine file (DST, PES, etc.)
        if ($arquivo_maquina) {
            $html .= "
                    <div style='background: white; padding: 15px; border-radius: 8px; margin-bottom: 12px; border: 1px solid #c8e6c9;'>
                        <table style='width: 100%;'><tr>
                            <td style='vertical-align: middle;'>
                                <span style='font-size: 24px; margin-right: 10px;'>🪡</span>
                                <strong style='color: #333;'>Machine File</strong>
                                <br><small style='color: #666; margin-left: 34px;'>{$arquivo_maquina['extensao']} format - Ready for your machine</small>
                            </td>
                            <td style='text-align: right; vertical-align: middle;'>
                                <a href='{$arquivo_maquina['url']}' target='_blank' style='{$btn_style}'>⬇️ Download {$arquivo_maquina['extensao']}</a>
                            </td>
                        </tr></table>
                    </div>
            ";
        }
        
        // PDF
        if ($arquivo_pdf) {
            $html .= "
                    <div style='background: white; padding: 15px; border-radius: 8px; margin-bottom: 12px; border: 1px solid #c8e6c9;'>
                        <table style='width: 100%;'><tr>
                            <td style='vertical-align: middle;'>
                                <span style='font-size: 24px; margin-right: 10px;'>📄</span>
                                <strong style='color: #333;'>Production Sheet</strong>
                                <br><small style='color: #666; margin-left: 34px;'>PDF with thread colors and specifications</small>
                            </td>
                            <td style='text-align: right; vertical-align: middle;'>
                                <a href='{$arquivo_pdf}' target='_blank' style='{$btn_style}'>⬇️ Download PDF</a>
                            </td>
                        </tr></table>
                    </div>
            ";
        }
        
        // Image
        if ($arquivo_imagem) {
            $html .= "
                    <div style='background: white; padding: 15px; border-radius: 8px; margin-bottom: 0; border: 1px solid #c8e6c9;'>
                        <table style='width: 100%;'><tr>
                            <td style='vertical-align: middle;'>
                                <span style='font-size: 24px; margin-right: 10px;'>🖼️</span>
                                <strong style='color: #333;'>Preview Image</strong>
                                <br><small style='color: #666; margin-left: 34px;'>High resolution preview</small>
                            </td>
                            <td style='text-align: right; vertical-align: middle;'>
                                <a href='{$arquivo_imagem}' target='_blank' style='{$btn_style}'>⬇️ Download Image</a>
                            </td>
                        </tr></table>
                    </div>
            ";
        }
        
        if (!$arquivo_emb && !$arquivo_maquina && !$arquivo_pdf && !$arquivo_imagem) {
            $html .= "<div style='background: white; padding: 20px; border-radius: 8px; text-align: center; color: #666;'><p style='margin: 0;'>📭 No files available. Please contact support.</p></div>";
        }
        
        $html .= "</div>";
        
        // Observações do programador
        if (!empty($observacoes_prog)) {
            $html .= "
                <div style='background: #fff3e0; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ff9800;'>
                    <h4 style='margin: 0 0 10px 0; color: #e65100;'>💬 Digitizer Notes:</h4>
                    <p style='margin: 0; color: #333;'>{$observacoes_prog}</p>
                </div>
            ";
        }
        
        // Rodapé
        $html .= "
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . site_url('/meus-pedidos/') . "' style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 15px 35px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px; display: inline-block;'>📥 Go to My Orders</a>
                </p>
                <p style='color: #666; font-size: 14px;'>If you have any questions about your files, simply reply to this email.</p>
                <p>Thank you for choosing Puncher Digitizing!</p>
                <p>Best regards,<br><strong>Puncher Digitizing Team</strong></p>
            </div>
            <div style='background: #333; color: #999; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; font-size: 12px;'>
                © " . date('Y') . " Puncher Embroidery Digitizing<br>
                <a href='https://puncher.com' style='color: #667eea;'>www.puncher.com</a>
            </div>
        </div>
        ";
        
        return $html;
    }

    /**
     * Template: Email orçamento para cliente
     */
    private static function template_orcamento_cliente($pedido, $cliente, $dados_orcamento) {
        $preco = isset($dados_orcamento['preco']) ? number_format($dados_orcamento['preco'], 2) : '0.00';
        $pontos = isset($dados_orcamento['pontos']) ? number_format($dados_orcamento['pontos']) : '0';
        $observacoes = isset($dados_orcamento['observacoes']) ? $dados_orcamento['observacoes'] : '';

        return "
        <h2>💰 Quote Ready for Your Order</h2>

        <p>Hello <strong>{$cliente->display_name}</strong>,</p>

        <p>We have prepared a quote for your embroidery order:</p>

        <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Order:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>#{$pedido->id}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Design:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$pedido->nome_bordado}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Stitch Count:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$pontos} stitches</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 12px; background: #e8f5e9;'><strong style='font-size: 18px;'>Price:</strong></td>
                <td style='border: 1px solid #ddd; padding: 12px; background: #e8f5e9;'><strong style='font-size: 18px; color: #2e7d32;'>$ {$preco}</strong></td>
            </tr>
        </table>

        " . (!empty($observacoes) ? "
        <div style='background: #fff3e0; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <h3 style='margin: 0 0 10px 0; color: #e65100;'>💬 Notes:</h3>
            <p style='margin: 0;'>{$observacoes}</p>
        </div>
        " : "") . "

        <p style='text-align: center; margin: 30px 0;'>
            <a href='" . site_url('/meus-pedidos/') . "'
               style='background: #4caf50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
               ✅ View & Approve Quote
            </a>
        </p>

        <p>If you have any questions about this quote, please contact us.</p>

        <p>Best regards,<br>
        <strong>Puncher Digitizing Team</strong></p>
        ";
    }

    /**
     * Template: Notificação de orçamento aprovado (para admin)
     */
    private static function template_orcamento_aprovado($pedido, $cliente) {
        $cliente_nome = $cliente ? $cliente->display_name : 'Cliente';
        $cliente_email = $cliente ? $cliente->user_email : 'N/A';

        return "
        <h2>✅ Quote Approved!</h2>

        <p>The client has approved the quote for the following order:</p>

        <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Order:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>#{$pedido->id}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Client:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$cliente_nome} ({$cliente_email})</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Design:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$pedido->nome_bordado}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #e8f5e9;'><strong>Approved Price:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px; background: #e8f5e9;'><strong>$ " . number_format($pedido->preco_programador, 2) . "</strong></td>
            </tr>
        </table>

        <p>The work can now proceed to production.</p>

        <p><a href='" . admin_url('admin.php?page=bordados-pedidos') . "' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Orders</a></p>
        ";
    }
}
