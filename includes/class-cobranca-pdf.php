<?php
/**
 * Classe para geracao de PDFs de cobranca
 * Usa TCPDF para geracao profissional de PDFs
 * 
 * @package Sistema_Bordados
 * @since 3.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bordados_Cobranca_PDF {
    
    private $cobranca;
    private $upload_dir;
    private $upload_url;
    
    public function __construct() {
        $this->cobranca = Bordados_Cobranca::get_instance();
        
        $upload = wp_upload_dir();
        $this->upload_dir = $upload['basedir'] . '/bordados-invoices/';
        $this->upload_url = $upload['baseurl'] . '/bordados-invoices/';
        
        // Criar diretorio se nao existir
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
    }
    
    /**
     * Gerar Invoices para os servicos selecionados
     */
    public function gerar_invoices($servicos_ids) {
        global $wpdb;
        
        // Buscar servicos agrupados por cliente
        $ids_placeholder = implode(',', array_map('intval', $servicos_ids));
        
        $servicos = $wpdb->get_results("
            SELECT p.*, 
                   u.display_name as cliente_nome,
                   u.user_email as cliente_email
            FROM pedidos_basicos p
            JOIN {$wpdb->users} u ON p.cliente_id = u.ID
            WHERE p.id IN ($ids_placeholder)
            ORDER BY p.cliente_id, p.data_conclusao ASC
        ");
        
        if (empty($servicos)) {
            return array('success' => false, 'error' => 'Nenhum servico encontrado');
        }
        
        // Agrupar por cliente
        $clientes = array();
        foreach ($servicos as $servico) {
            $cliente_id = $servico->cliente_id;
            if (!isset($clientes[$cliente_id])) {
                $clientes[$cliente_id] = array(
                    'dados' => $this->cobranca->get_dados_cliente($cliente_id),
                    'servicos' => array()
                );
            }
            $clientes[$cliente_id]['servicos'][] = $servico;
        }
        
        // Se apenas um cliente, gerar PDF unico
        if (count($clientes) === 1) {
            $cliente_id = array_key_first($clientes);
            $cliente = $clientes[$cliente_id];
            $invoice_number = $this->cobranca->get_proximo_invoice();
            
            $pdf_path = $this->gerar_invoice_cliente($cliente, $invoice_number);
            
            if ($pdf_path) {
                return array(
                    'success' => true,
                    'pdf_url' => $this->upload_url . basename($pdf_path)
                );
            }
        }
        
        // Multiplos clientes - gerar ZIP
        $pdfs = array();
        $invoice_number = $this->cobranca->get_proximo_invoice();
        
        foreach ($clientes as $cliente_id => $cliente) {
            $pdf_path = $this->gerar_invoice_cliente($cliente, $invoice_number);
            if ($pdf_path) {
                $pdfs[] = $pdf_path;
            }
            $invoice_number++;
        }
        
        if (empty($pdfs)) {
            return array('success' => false, 'error' => 'Erro ao gerar PDFs');
        }
        
        // Criar ZIP
        $zip_name = 'invoices-' . date('Y-m-d-His') . '.zip';
        $zip_path = $this->upload_dir . $zip_name;
        
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
            foreach ($pdfs as $pdf) {
                $zip->addFile($pdf, basename($pdf));
            }
            $zip->close();
            
            return array(
                'success' => true,
                'zip_url' => $this->upload_url . $zip_name
            );
        }
        
        // Se falhar o ZIP, retornar o primeiro PDF
        return array(
            'success' => true,
            'pdf_url' => $this->upload_url . basename($pdfs[0])
        );
    }
    
    /**
     * Gerar Invoice para um cliente especifico
     */
    private function gerar_invoice_cliente($cliente, $invoice_number) {
        $dados = $cliente['dados'];
        $servicos = $cliente['servicos'];
        $empresa = $this->cobranca->get_empresa_dados();
        
        // Calcular totais
        $total = 0;
        $total_pontos = 0;
        foreach ($servicos as $servico) {
            $preco = !empty($servico->preco_final) ? floatval($servico->preco_final) : floatval($servico->preco_programador);
            $total += $preco;
            $total_pontos += intval($servico->numero_pontos ?? 0);
        }
        
        $avg_price = count($servicos) > 0 ? $total / count($servicos) : 0;
        $avg_rate = $total_pontos > 0 ? ($total / ($total_pontos / 1000)) : 0;
        
        // Nome do arquivo
        $nome_limpo = preg_replace('/[^a-zA-Z0-9]/', '-', $dados['razao_social'] ?: $dados['display_name']);
        $nome_limpo = preg_replace('/-+/', '-', $nome_limpo);
        $filename = "invoice-{$invoice_number}-{$nome_limpo}.pdf";
        $filepath = $this->upload_dir . $filename;
        
        // Gerar HTML do Invoice
        $html = $this->gerar_html_invoice($dados, $servicos, $invoice_number, $empresa, $total, $avg_price, $avg_rate);
        
        // Converter HTML para PDF
        return $this->html_to_pdf($html, $filepath);
    }
    
    /**
     * Gerar HTML do Invoice
     */
    private function gerar_html_invoice($dados, $servicos, $invoice_number, $empresa, $total, $avg_price, $avg_rate) {
        $data_atual = date('l, F j, Y');
        $hora_atual = date('g:i:s A');
        
        // Montar endereco do cliente
        $endereco_cliente = array();
        if (!empty($dados['endereco_rua'])) {
            $rua = $dados['endereco_rua'];
            if (!empty($dados['endereco_numero'])) {
                $rua .= ' ' . $dados['endereco_numero'];
            }
            $endereco_cliente[] = $rua;
        }
        
        $cidade_estado = array();
        if (!empty($dados['endereco_cidade'])) $cidade_estado[] = $dados['endereco_cidade'];
        if (!empty($dados['endereco_estado'])) $cidade_estado[] = $dados['endereco_estado'];
        if (!empty($dados['cep'])) $cidade_estado[] = $dados['cep'];
        if (!empty($cidade_estado)) {
            $endereco_cliente[] = implode(' ', $cidade_estado);
        }
        
        if (!empty($dados['pais'])) {
            $paises = $this->get_paises();
            $endereco_cliente[] = $paises[$dados['pais']] ?? $dados['pais'];
        }
        
        // Emails
        $emails = array();
        if (!empty($dados['email_invoice'])) $emails[] = $dados['email_invoice'];
        if (!empty($dados['email_secundario'])) $emails[] = $dados['email_secundario'];
        if (empty($emails) && !empty($dados['user_email'])) $emails[] = $dados['user_email'];
        
        // Metodo de pagamento
        $metodo = $dados['metodo_pagamento'] ?? '';
        $metodo_display = '';
        if ($metodo === 'credit_card') {
            $metodo_display = ucfirst($dados['card_brand'] ?? 'Credit Card');
        } elseif ($metodo === 'paypal') {
            $metodo_display = 'PayPal';
        } elseif ($metodo === 'bank_transfer') {
            $metodo_display = 'Bank Transfer';
        }
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-title {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .header-date {
            font-size: 12pt;
            font-weight: bold;
            text-align: right;
        }
        .addresses {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .address-from, .address-to {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .address-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .invoice-info {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #f5f5f5;
        }
        .invoice-number {
            font-size: 16pt;
            font-weight: bold;
        }
        .invoice-paid {
            color: #28a745;
            font-weight: bold;
            font-size: 12pt;
        }
        .payment-method {
            margin: 10px 0;
        }
        .separator {
            border-bottom: 1px solid #ccc;
            margin: 15px 0;
        }
        .item {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .item-header {
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }
        .item-content {
            display: table;
            width: 100%;
        }
        .item-details {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }
        .item-image {
            display: table-cell;
            width: 40%;
            text-align: right;
            vertical-align: top;
        }
        .item-image img {
            max-width: 180px;
            max-height: 180px;
            border: 1px solid #ddd;
        }
        .detail-row {
            margin-bottom: 3px;
        }
        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
        }
        .price-row {
            margin-top: 10px;
        }
        .price-value {
            font-weight: bold;
            font-size: 12pt;
        }
        .totals {
            margin-top: 30px;
            padding: 15px;
            background: #f5f5f5;
            text-align: center;
        }
        .total-main {
            font-size: 18pt;
            font-weight: bold;
            color: #28a745;
        }
        .statistics {
            margin-top: 10px;
            font-size: 10pt;
            color: #666;
        }
        .notes {
            margin-top: 30px;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 15px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10pt;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <table width="100%">
            <tr>
                <td width="60%">
                    <div class="header-title">Invoice from Puncher Embroidery Digitizing</div>
                </td>
                <td width="40%" style="text-align: right;">
                    <div class="header-date">' . $data_atual . '</div>
                    <div>' . $hora_atual . '</div>
                </td>
            </tr>
        </table>
    </div>
    
    <table width="100%" style="margin-bottom: 20px;">
        <tr>
            <td width="50%" valign="top">
                <div class="address-label">From: ' . htmlspecialchars($empresa['nome']) . '</div>
                <div>' . htmlspecialchars($empresa['endereco']) . '</div>
                <div>' . htmlspecialchars($empresa['cidade']) . '</div>
                <div>Email: ' . htmlspecialchars($empresa['email']) . '</div>
            </td>
            <td width="50%" valign="top">
                <div class="address-label">Sold to: ' . htmlspecialchars($dados['razao_social'] ?: $dados['display_name']) . '</div>
                <div>' . htmlspecialchars($dados['first_name'] . ' ' . $dados['last_name']) . '</div>';
        
        foreach ($endereco_cliente as $linha) {
            $html .= '<div>' . htmlspecialchars($linha) . '</div>';
        }
        
        if (!empty($emails)) {
            $html .= '<div>' . htmlspecialchars(implode(' , ', $emails)) . '</div>';
        }
        
        $html .= '
            </td>
        </tr>
    </table>
    
    <div class="invoice-info">
        <div class="invoice-number">Invoice number: ' . $invoice_number . '</div>
        <div class="invoice-paid">* * * * * Invoice paid in full, thanks * * * * *</div>
    </div>';
        
        if (!empty($metodo_display)) {
            $html .= '<div class="payment-method">Payment method: <strong>' . $metodo_display . '</strong></div>';
        }
        
        $html .= '<div class="separator" style="border-bottom: 2px solid #333;"></div>';
        
        // Items
        $item_number = 0;
        foreach ($servicos as $servico) {
            $item_number++;
            $preco = !empty($servico->preco_final) ? floatval($servico->preco_final) : floatval($servico->preco_programador);
            $imagem_url = $this->cobranca->get_imagem_servico($servico);
            $arquivo_ref = $this->cobranca->get_nome_arquivo_referencia($servico);
            
            // Determinar dimensao
            $dimensao = '';
            if (!empty($servico->largura) && $servico->largura > 0) {
                $dimensao = 'Width: ' . number_format($servico->largura, 2) . ' inches';
            } elseif (!empty($servico->altura) && $servico->altura > 0) {
                $dimensao = 'Height: ' . number_format($servico->altura, 2) . ' inches';
            }
            
            $html .= '
    <div class="item">
        <div class="item-header">Item number: ' . $item_number . '</div>
        <table width="100%">
            <tr>
                <td width="60%" valign="top">
                    <div class="detail-row"><strong>Embroidery file</strong></div>
                    <div class="detail-row"><span class="detail-label">Order date:</span> ' . date('l, F j, Y', strtotime($servico->data_criacao)) . '</div>
                    <div class="detail-row"><span class="detail-label">Delivery date:</span> ' . ($servico->data_conclusao ? date('l, F j, Y', strtotime($servico->data_conclusao)) : '-') . '</div>
                    <div class="detail-row"><span class="detail-label">Design Name</span> <strong>' . htmlspecialchars($servico->nome_bordado) . '</strong></div>';
            
            if (!empty($arquivo_ref)) {
                $html .= '<div class="detail-row"><span class="detail-label">Image file sent:</span> ' . htmlspecialchars($arquivo_ref) . '</div>';
            }
            
            if (!empty($servico->numero_pontos)) {
                $html .= '<div class="detail-row"><span class="detail-label">Stitch count:</span> ' . number_format($servico->numero_pontos) . '</div>';
            }
            
            if (!empty($dimensao)) {
                $html .= '<div class="detail-row">' . $dimensao . '</div>';
            }
            
            $html .= '
                    <div class="price-row"><span class="detail-label">Price</span> <span class="price-value">$' . number_format($preco, 2) . '</span> by quote</div>
                </td>
                <td width="40%" valign="top" style="text-align: right;">';
            
            if (!empty($imagem_url)) {
                // Converter URL para caminho local para embed
                $upload = wp_upload_dir();
                $local_path = '';
                
                // Normalizar URL (remover escapes do JSON)
                $imagem_url = stripslashes($imagem_url);
                
                // Metodo 1: Substituicao direta baseurl -> basedir
                $local_path = str_replace($upload['baseurl'], $upload['basedir'], $imagem_url);
                
                // Se nao encontrou, tentar outras variacoes
                if (!file_exists($local_path)) {
                    // Metodo 2: Extrair apenas o path relativo a wp-content/uploads
                    if (preg_match('/wp-content\/uploads\/(.+)$/', $imagem_url, $matches)) {
                        $local_path = $upload['basedir'] . '/' . $matches[1];
                    }
                }
                
                // Metodo 3: Tentar com ABSPATH
                if (!file_exists($local_path)) {
                    if (preg_match('/\/wp-content\/uploads\/(.+)$/', $imagem_url, $matches)) {
                        $local_path = ABSPATH . 'wp-content/uploads/' . $matches[1];
                    }
                }
                
                // Metodo 4: Caminho direto do SiteGround
                if (!file_exists($local_path)) {
                    if (preg_match('/\/app\/wp-content\/uploads\/(.+)$/', $imagem_url, $matches)) {
                        // Tentar path absoluto tipico do SiteGround
                        $possible_paths = array(
                            '/home/customer/www/puncher.com/public_html/app/wp-content/uploads/' . $matches[1],
                            dirname(ABSPATH) . '/app/wp-content/uploads/' . $matches[1],
                            ABSPATH . 'wp-content/uploads/' . $matches[1],
                        );
                        foreach ($possible_paths as $test_path) {
                            if (file_exists($test_path)) {
                                $local_path = $test_path;
                                break;
                            }
                        }
                    }
                }
                
                if (!empty($local_path) && file_exists($local_path)) {
                    $image_data = base64_encode(file_get_contents($local_path));
                    $image_type = strtolower(pathinfo($local_path, PATHINFO_EXTENSION));
                    if ($image_type === 'jpg') $image_type = 'jpeg';
                    $html .= '<img src="data:image/' . $image_type . ';base64,' . $image_data . '" style="max-width: 180px; max-height: 180px; border: 1px solid #ddd;">';
                }
            }
            
            $html .= '
                </td>
            </tr>
        </table>
        <div class="separator"></div>
    </div>';
        }
        
        // Totais
        $html .= '
    <div class="totals">
        <div class="total-main">Total Invoice (US Dollar): $' . number_format($total, 2) . '</div>
        <div class="statistics">
            Statistics: Average job price: $' . number_format($avg_price, 2) . ' &nbsp;&nbsp;&nbsp; 
            Jobs on this invoice: ' . count($servicos) . ' &nbsp;&nbsp;&nbsp; 
            Average rate: $' . number_format($avg_rate, 2) . ' / k stitches
        </div>
    </div>
    
    <div class="notes">
        <strong>PLEASE NOTE THE FOLLOWINGS:</strong><br>
        Our main company name is Magic Cap Puncher, so when you receive your credit card statement, that\'s the name it\'s going to be under.<br>
        - When invoice is charged to your credit card it may not be the exactly value as assigned above. It can be a small amount higher or lower due Dollar fluctuation over the Brazilian currency (Real).<br>
        - Magic Cap - Puncher is a company owned and directed by Mr. Almiro Almeida Guimaraes (aag@puncher.com) since 1993.
    </div>
    
    <div class="footer">
        ' . htmlspecialchars($empresa['rodape']) . '
    </div>
    
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Gerar Cobranca Resumo PDF
     */
    public function gerar_cobranca_resumo($servicos_ids, $cotacao) {
        global $wpdb;
        
        $ids_placeholder = implode(',', array_map('intval', $servicos_ids));
        
        $servicos = $wpdb->get_results("
            SELECT p.*, 
                   u.display_name as cliente_nome,
                   u.user_email as cliente_email
            FROM pedidos_basicos p
            JOIN {$wpdb->users} u ON p.cliente_id = u.ID
            WHERE p.id IN ($ids_placeholder)
            ORDER BY p.cliente_id, p.data_conclusao ASC
        ");
        
        if (empty($servicos)) {
            return array('success' => false, 'error' => 'Nenhum servico encontrado');
        }
        
        // Agrupar por cliente
        $clientes = array();
        foreach ($servicos as $servico) {
            $cliente_id = $servico->cliente_id;
            if (!isset($clientes[$cliente_id])) {
                $dados = $this->cobranca->get_dados_cliente($cliente_id);
                $clientes[$cliente_id] = array(
                    'dados' => $dados,
                    'servicos' => array(),
                    'total_usd' => 0
                );
            }
            
            $preco = !empty($servico->preco_final) ? floatval($servico->preco_final) : floatval($servico->preco_programador);
            $clientes[$cliente_id]['servicos'][] = $servico;
            $clientes[$cliente_id]['total_usd'] += $preco;
        }
        
        // Agrupar por bandeira de cartao
        $por_bandeira = array();
        foreach ($clientes as $cliente_id => $cliente) {
            $bandeira = strtolower($cliente['dados']['card_brand'] ?? 'outro');
            if (!isset($por_bandeira[$bandeira])) {
                $por_bandeira[$bandeira] = array(
                    'clientes' => array(),
                    'total_usd' => 0,
                    'total_brl' => 0
                );
            }
            
            $cliente['total_brl'] = $cliente['total_usd'] * $cotacao;
            $por_bandeira[$bandeira]['clientes'][$cliente_id] = $cliente;
            $por_bandeira[$bandeira]['total_usd'] += $cliente['total_usd'];
            $por_bandeira[$bandeira]['total_brl'] += $cliente['total_brl'];
        }
        
        // Gerar HTML
        $html = $this->gerar_html_cobranca_resumo($por_bandeira, $cotacao);
        
        // Nome do arquivo
        $filename = 'cobranca-resumo-' . date('Y-m-d-His') . '.pdf';
        $filepath = $this->upload_dir . $filename;
        
        // Converter para PDF
        $result = $this->html_to_pdf($html, $filepath);
        
        if ($result) {
            return array(
                'success' => true,
                'pdf_url' => $this->upload_url . $filename
            );
        }
        
        return array('success' => false, 'error' => 'Erro ao gerar PDF');
    }
    
    /**
     * Gerar HTML da Cobranca Resumo
     */
    private function gerar_html_cobranca_resumo($por_bandeira, $cotacao) {
        $data_atual = date('l, F j, Y');
        $empresa = $this->cobranca->get_empresa_dados();
        
        $total_geral_usd = 0;
        $total_geral_brl = 0;
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header-title {
            font-size: 16pt;
            font-weight: bold;
        }
        .header-subtitle {
            font-size: 11pt;
            color: #666;
        }
        .info-box {
            background: #f5f5f5;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 10pt;
        }
        .obs-text {
            font-style: italic;
            color: #666;
            margin-bottom: 10px;
        }
        .cobranca-date {
            font-weight: bold;
        }
        .bandeira-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .cliente-box {
            border: 1px solid #ddd;
            padding: 12px;
            margin-bottom: 15px;
            background: #fafafa;
        }
        .cliente-card {
            font-family: "Courier New", monospace;
            font-size: 12pt;
            margin-bottom: 5px;
        }
        .cliente-nome {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .cliente-cvv {
            color: #666;
        }
        .cliente-valores {
            text-align: right;
            margin-top: 10px;
        }
        .valor-usd {
            font-weight: bold;
        }
        .valor-brl {
            font-weight: bold;
            color: #28a745;
        }
        .bandeira-total {
            background: #e9ecef;
            padding: 10px 15px;
            margin-top: 10px;
            font-weight: bold;
        }
        .total-geral {
            background: #28a745;
            color: white;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }
        .total-geral-valor {
            font-size: 16pt;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10pt;
            color: #666;
        }
        .page-footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9pt;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">Cobranca Mensal da</div>
        <div class="header-title">* * Resumo * *</div>
        <div class="header-subtitle">' . htmlspecialchars($empresa['nome']) . '</div>
    </div>
    
    <div class="info-box">
        <div class="obs-text">Obs: se nao passar na primeira tentativa repetir os que nao passaram quando chegar ao final da lista.</div>
        <div class="cobranca-date">Cobranca ' . $data_atual . ' &nbsp;&nbsp;&nbsp; 1 US Dollar = ' . number_format($cotacao, 2) . '</div>
    </div>';
        
        // Por bandeira
        $bandeiras_ordem = array('visa', 'mastercard', 'amex', 'discover', 'outro');
        
        foreach ($bandeiras_ordem as $bandeira) {
            if (!isset($por_bandeira[$bandeira]) || empty($por_bandeira[$bandeira]['clientes'])) {
                continue;
            }
            
            $dados_bandeira = $por_bandeira[$bandeira];
            $bandeira_nome = ucfirst($bandeira);
            if ($bandeira === 'amex') $bandeira_nome = 'American Express';
            
            $html .= '<div class="bandeira-section">';
            
            foreach ($dados_bandeira['clientes'] as $cliente_id => $cliente) {
                $dados = $cliente['dados'];
                
                // Descriptografar dados do cartao
                $card_number = $this->cobranca->decrypt_card_data($dados['card_number']);
                $card_expiry = $this->cobranca->decrypt_card_data($dados['card_expiry']);
                $card_cvv = $this->cobranca->decrypt_card_data($dados['card_cvv']);
                
                // Formatar numero do cartao (adicionar espacos)
                $card_number_formatted = wordwrap($card_number, 4, ' ', true);
                
                $html .= '
    <div class="cliente-box">
        <div class="cliente-card">' . $bandeira_nome . ' ' . $card_number_formatted . ' Val: ' . $card_expiry . '</div>
        <div class="cliente-nome">' . htmlspecialchars($dados['razao_social'] ?: $dados['display_name']) . ' - ' . htmlspecialchars($dados['first_name'] . ' ' . $dados['last_name']) . '</div>
        <div class="cliente-cvv">seg: ' . $card_cvv . '</div>
        <div class="cliente-valores">
            <span class="valor-usd">$' . number_format($cliente['total_usd'], 2) . '</span> &nbsp;&nbsp;
            <span class="valor-brl">R$' . number_format($cliente['total_brl'], 2, ',', '.') . '</span>
        </div>
    </div>';
            }
            
            $html .= '
    <div class="bandeira-total">
        Total do Cartao ' . $bandeira_nome . ':<br>
        Total US dolar: ' . number_format($dados_bandeira['total_usd'], 2) . '<br>
        Total R$: R$' . number_format($dados_bandeira['total_brl'], 2, ',', '.') . '
    </div>
</div>';
            
            $total_geral_usd += $dados_bandeira['total_usd'];
            $total_geral_brl += $dados_bandeira['total_brl'];
        }
        
        $html .= '
    <div class="total-geral">
        <div class="total-geral-valor">Total geral - US dollar: $' . number_format($total_geral_usd, 2) . '</div>
        <div class="total-geral-valor">Total Geral em Reais: R$' . number_format($total_geral_brl, 2, ',', '.') . '</div>
    </div>
    
    <div class="footer">
        Obrigado pela atencao<br>
        * * * Confira bem os dados, por favor * * *
    </div>
    
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Converter HTML para PDF
     */
    private function html_to_pdf($html, $filepath) {
        // Tentar usar TCPDF se disponivel
        if (class_exists('TCPDF')) {
            return $this->html_to_pdf_tcpdf($html, $filepath);
        }
        
        // Tentar usar DOMPDF se disponivel
        if (class_exists('Dompdf\\Dompdf')) {
            return $this->html_to_pdf_dompdf($html, $filepath);
        }
        
        // Tentar usar mPDF se disponivel
        if (class_exists('\\Mpdf\\Mpdf')) {
            return $this->html_to_pdf_mpdf($html, $filepath);
        }
        
        // Fallback: usar wkhtmltopdf via linha de comando se disponivel
        $wkhtmltopdf = $this->find_wkhtmltopdf();
        if ($wkhtmltopdf) {
            return $this->html_to_pdf_wkhtmltopdf($html, $filepath, $wkhtmltopdf);
        }
        
        // Ultimo recurso: tentar instalar/usar biblioteca simples
        return $this->html_to_pdf_simple($html, $filepath);
    }
    
    /**
     * Converter usando TCPDF
     */
    private function html_to_pdf_tcpdf($html, $filepath) {
        $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8');
        $pdf->SetCreator('Puncher.com');
        $pdf->SetAuthor('Magic Cap Embroidery');
        $pdf->SetTitle('Invoice');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filepath, 'F');
        
        return file_exists($filepath) ? $filepath : false;
    }
    
    /**
     * Converter usando DOMPDF
     */
    private function html_to_pdf_dompdf($html, $filepath) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        
        file_put_contents($filepath, $dompdf->output());
        
        return file_exists($filepath) ? $filepath : false;
    }
    
    /**
     * Converter usando mPDF
     */
    private function html_to_pdf_mpdf($html, $filepath) {
        $mpdf = new \Mpdf\Mpdf([
            'format' => 'Letter',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);
        
        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, 'F');
        
        return file_exists($filepath) ? $filepath : false;
    }
    
    /**
     * Encontrar wkhtmltopdf
     */
    private function find_wkhtmltopdf() {
        $paths = array(
            '/usr/bin/wkhtmltopdf',
            '/usr/local/bin/wkhtmltopdf',
            'wkhtmltopdf'
        );
        
        foreach ($paths as $path) {
            if (is_executable($path) || @exec("which $path 2>/dev/null", $output)) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Converter usando wkhtmltopdf
     */
    private function html_to_pdf_wkhtmltopdf($html, $filepath, $wkhtmltopdf) {
        $html_file = tempnam(sys_get_temp_dir(), 'invoice_') . '.html';
        file_put_contents($html_file, $html);
        
        $cmd = escapeshellcmd($wkhtmltopdf) . ' --page-size Letter --margin-top 15mm --margin-bottom 15mm --margin-left 15mm --margin-right 15mm --encoding utf-8 ' . escapeshellarg($html_file) . ' ' . escapeshellarg($filepath) . ' 2>&1';
        
        exec($cmd, $output, $return_var);
        
        @unlink($html_file);
        
        return file_exists($filepath) ? $filepath : false;
    }
    
    /**
     * Metodo simples usando HTML direto
     * Gera um HTML que pode ser impresso como PDF pelo navegador
     */
    private function html_to_pdf_simple($html, $filepath) {
        // Salvar como HTML com instrucoes de impressao
        $html_with_print = str_replace('</head>', '
<style>
@media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>', $html);
        
        // Mudar extensao para .html para abrir no navegador
        $html_filepath = preg_replace('/\.pdf$/', '.html', $filepath);
        file_put_contents($html_filepath, $html_with_print);
        
        // Tentar converter com outras ferramentas disponiveis
        // Verificar se existe o chromium/chrome para conversao headless
        $chrome_paths = array(
            '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium'
        );
        
        foreach ($chrome_paths as $chrome) {
            if (is_executable($chrome)) {
                $cmd = escapeshellcmd($chrome) . ' --headless --disable-gpu --print-to-pdf=' . escapeshellarg($filepath) . ' ' . escapeshellarg('file://' . $html_filepath) . ' 2>&1';
                exec($cmd, $output, $return_var);
                
                if (file_exists($filepath)) {
                    @unlink($html_filepath);
                    return $filepath;
                }
            }
        }
        
        // Se nada funcionou, retornar o HTML mesmo
        // O usuario pode abrir e imprimir/salvar como PDF
        return $html_filepath;
    }
    
    /**
     * Lista de paises
     */
    private function get_paises() {
        return array(
            'US' => 'United States',
            'CA' => 'Canada',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'PT' => 'Portugal',
            'AU' => 'Australia',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'PE' => 'Peru'
        );
    }
}
