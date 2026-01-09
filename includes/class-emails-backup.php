<?php
/**
 * Classe para gerenciar envio de emails
 * 
 * ATUALIZADO: Funções para orçamentos (Etapa 3)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Emails {
    
    /**
     * Enviar email para programador sobre novo trabalho
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
        
        // Headers para HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Sistema Bordados <noreply@' . $_SERVER['HTTP_HOST'] . '>'
        );
        
        // Enviar email
        return wp_mail($para, $assunto, $mensagem, $headers);
    }
    
    /**
     * Enviar email para cliente quando produção for iniciada
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
        
        // Headers para HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Puncher Digitizing <noreply@' . $_SERVER['HTTP_HOST'] . '>'
        );
        
        // Enviar email
        return wp_mail($para, $assunto, $mensagem, $headers);
    }
    
    /**
     * Enviar email para cliente quando trabalho for concluído
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
        
        // Headers para HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Puncher Digitizing <noreply@' . $_SERVER['HTTP_HOST'] . '>'
        );
        
        // Enviar email
        return wp_mail($para, $assunto, $mensagem, $headers);
    }
    
    /**
     * ⭐ ETAPA 3: Enviar orçamento para cliente
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
        
        // Headers para HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Puncher Embroidery <noreply@puncher.com>'
        );
        
        // Enviar email
        $enviado = wp_mail($para, $assunto, $mensagem, $headers);
        
        if ($enviado) {
            error_log("✅ Email de orçamento enviado para {$cliente->user_email}");
        } else {
            error_log("❌ Falha ao enviar email de orçamento para {$cliente->user_email}");
        }
        
        return $enviado;
    }
    
    /**
     * ⭐ ETAPA 3: Notificar admin quando orçamento for aprovado
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
        
        // Headers para HTML
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
     * Template: Email novo trabalho
     */
    private static function template_novo_trabalho($programador, $cliente, $pedido_id, $dados_pedido) {
        $cores = isset($dados_pedido['cores']) ? $dados_pedido['cores'] : '';
        $observacoes = isset($dados_pedido['observacoes']) ? $dados_pedido['observacoes'] : '';
        
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
                <td style='border: 1px solid #ddd; padding: 8px;'>{$cliente->display_name}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px;'><strong>Design:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$dados_pedido['nome_bordado']}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px;'><strong>Size:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$dados_pedido['tamanho']}</td>
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
                <td style='border: 1px solid #ddd; padding: 8px;'>{$pedido->tamanho}</td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Status:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'><span style='background: #e8f5e8; color: #388e3c; padding: 4px 8px; border-radius: 5px;'>⚙️ In Production</span></td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Digitizer:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>{$pedido->programador_nome}</td>
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
     */
    private static function template_trabalho_concluido($pedido, $cliente, $arquivos_finais) {
        // Lista de arquivos para download
        $lista_arquivos = '';
        foreach ($arquivos_finais as $index => $arquivo) {
            $nome_arquivo = basename($arquivo);
            $arquivo_https = Bordados_Helpers::forcar_https($arquivo);
            $lista_arquivos .= '<li><a href="' . esc_url($arquivo_https) . '" target="_blank">📎 ' . $nome_arquivo . '</a></li>';
        }
        
        $observacoes_prog = !empty($pedido->observacoes_programador) ? $pedido->observacoes_programador : '';
        
        // Usar preco_final se disponível, senão usar preco_programador
        $preco_exibir = !empty($pedido->preco_final) ? $pedido->preco_final : $pedido->preco_programador;
        
        return "
        <h2>🎉 Your embroidery is ready!</h2>
        
        <p>Hello <strong>{$cliente->display_name}</strong>,</p>
        
        <p>Great news! Your embroidery order has been completed and is now available for download.</p>
        
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
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Status:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'><span style='background: #e1f5fe; color: #0277bd; padding: 4px 8px; border-radius: 5px;'>🎉 Ready</span></td>
            </tr>
            <tr>
                <td style='border: 1px solid #ddd; padding: 8px; background: #f8f9fa;'><strong>Price:</strong></td>
                <td style='border: 1px solid #ddd; padding: 8px;'>$ " . number_format($preco_exibir, 2) . "</td>
            </tr>
        </table>
        
        <div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <h3 style='margin: 0 0 10px 0; color: #388e3c;'>📁 Files for Download:</h3>
            <ul style='margin: 0; padding-left: 20px;'>
                {$lista_arquivos}
            </ul>
        </div>
        
        " . (!empty($observacoes_prog) ? "
        <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <h4 style='margin: 0 0 10px 0;'>💬 Digitizer Notes:</h4>
            <p style='margin: 0;'>{$observacoes_prog}</p>
        </div>
        " : "") . "
        
        <p style='text-align: center; margin: 30px 0;'>
            <a href='" . site_url('/meus-pedidos/') . "' 
               style='background: #0073aa; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
               📥 View All My Orders
            </a>
        </p>
        
        <p>Thank you for your business!</p>
        
        <p>Best regards,<br>
        <strong>Puncher Digitizing Team</strong></p>
        ";
    }
    
    /**
     * ⭐ ETAPA 3: Template - Orçamento enviado para cliente
     */
    private static function template_orcamento_cliente($pedido, $cliente, $dados_orcamento) {
        $numero_pontos = isset($dados_orcamento['numero_pontos']) ? number_format($dados_orcamento['numero_pontos']) : '0';
        $preco_final = isset($dados_orcamento['preco_final']) ? number_format($dados_orcamento['preco_final'], 2) : '0.00';
        $obs_revisor = isset($dados_orcamento['obs_revisor']) ? $dados_orcamento['obs_revisor'] : '';
        
        $tipo_produto = $pedido->tipo_produto == 'vetor' ? 'Vector Art' : 'Embroidery Digitizing';
        
        // Formatar dimensões
        $dimensoes = '';
        if (!empty($pedido->largura) || !empty($pedido->altura)) {
            $largura = !empty($pedido->largura) ? $pedido->largura : '?';
            $altura = !empty($pedido->altura) ? $pedido->altura : '?';
            $unidade = !empty($pedido->unidade_medida) ? $pedido->unidade_medida : 'cm';
            $dimensoes = "{$largura} x {$altura} {$unidade}";
        }
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>💰 Your Quote is Ready!</h2>
            
            <p>Hello <strong>{$cliente->display_name}</strong>,</p>
            
            <p>We have reviewed your order and prepared a quote for you:</p>
            
            <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 10px; background: #f8f9fa;'><strong>Order:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 10px;'>#{$pedido->id}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 10px; background: #f8f9fa;'><strong>Design Name:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 10px;'>{$pedido->nome_bordado}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 10px; background: #f8f9fa;'><strong>Service Type:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 10px;'>{$tipo_produto}</td>
                </tr>
                " . (!empty($dimensoes) ? "
                <tr>
                    <td style='border: 1px solid #ddd; padding: 10px; background: #f8f9fa;'><strong>Dimensions:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 10px;'>{$dimensoes}</td>
                </tr>
                " : "") . "
                <tr>
                    <td style='border: 1px solid #ddd; padding: 10px; background: #f8f9fa;'><strong>Estimated Stitches:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 10px;'>{$numero_pontos}</td>
                </tr>
            </table>
            
            <div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 25px 0; text-align: center;'>
                <p style='margin: 0 0 5px 0; font-size: 14px; color: #155724;'>YOUR QUOTE</p>
                <p style='margin: 0; font-size: 36px; font-weight: bold; color: #28a745;'>\${$preco_final}</p>
            </div>
            
            " . (!empty($obs_revisor) ? "
            <div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                <h4 style='margin: 0 0 8px 0; color: #856404;'>📝 Notes from our team:</h4>
                <p style='margin: 0; color: #856404;'>{$obs_revisor}</p>
            </div>
            " : "") . "
            
            <p style='text-align: center; margin: 30px 0;'>
                <a href='" . site_url('/meus-pedidos/') . "' 
                   style='background: #28a745; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;'>
                   ✅ Approve Quote
                </a>
            </p>
            
            <p style='color: #666; font-size: 13px; text-align: center;'>
                Click the button above to approve this quote and start production.<br>
                If you have questions, simply reply to this email.
            </p>
            
            <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
            
            <p>Thank you for choosing Puncher!</p>
            
            <p>Best regards,<br>
            <strong>Puncher Embroidery Team</strong></p>
        </div>
        ";
    }
    
    /**
     * ⭐ ETAPA 3: Template - Notificação de orçamento aprovado (para admin)
     */
    private static function template_orcamento_aprovado($pedido, $cliente) {
        $preco_final = !empty($pedido->preco_final) ? number_format($pedido->preco_final, 2) : '0.00';
        $numero_pontos = !empty($pedido->numero_pontos) ? number_format($pedido->numero_pontos) : '0';
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #28a745;'>✅ Quote Approved!</h2>
            
            <p>Good news! A customer has approved their quote:</p>
            
            <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 10px; background: #f8f9fa;'><strong>Order:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 10px;'>#{$pedido->id}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 10px; background: #f8f9fa;'><strong>Customer:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 10px;'>{$cliente->display_name} ({$cliente->user_email})</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 10px; background: #f8f9fa;'><strong>Design:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 10px;'>{$pedido->nome_bordado}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 10px; background: #f8f9fa;'><strong>Stitches:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 10px;'>{$numero_pontos}</td>
                </tr>
                <tr>
                    <td style='border: 1px solid #ddd; padding: 10px; background: #d4edda;'><strong>Approved Price:</strong></td>
                    <td style='border: 1px solid #ddd; padding: 10px; background: #d4edda; font-weight: bold; color: #28a745;'>\${$preco_final}</td>
                </tr>
            </table>
            
            <div style='background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p style='margin: 0;'>
                    <strong>📋 Next Step:</strong> This order is now in the queue with status <strong>'novo'</strong>. 
                    Please assign a digitizer to start production.
                </p>
            </div>
            
            <p style='text-align: center; margin: 30px 0;'>
                <a href='" . admin_url('admin.php?page=bordados-dashboard') . "' 
                   style='background: #0073aa; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                   👨‍💻 Go to Dashboard
                </a>
            </p>
            
            <p>— Puncher System</p>
        </div>
        ";
    }
}

?>
