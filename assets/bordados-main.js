/**
 * JavaScript principal do Sistema de Bordados - VERSÃO COM TOAST INTEGRADO
 * Arquivo: bordados-main.js 
 * Local: wp-content/plugins/sistema-bordados-simples/assets/bordados-main.js
 * 
 * @updated 2025-01-11 - Modal de download melhorado com ícones (v3.3.0)
 */

// Definir variáveis globais
window.uploadCount = 1;
window.uploadFinalCount = 1;

console.log('=== Sistema Bordados Carregando COM TOAST ===');

// ======================================
// 🍞 FUNÇÕES AUXILIARES PARA TOAST
// ======================================

/**
 * Mostrar mensagem com Toast ou fallback para alert
 */
function mostrarMensagem(tipo, titulo, mensagem) {
    console.log(`📢 Mensagem ${tipo}:`, titulo, mensagem);
    
    // Tentar usar o sistema Toast
    if (typeof window.BordadosToast !== 'undefined' && window.BordadosToast[tipo]) {
        try {
            return window.BordadosToast[tipo](mensagem, titulo);
        } catch (error) {
            console.warn('⚠️ Erro no Toast, usando fallback:', error);
        }
    }
    
    // Fallback para alert tradicional
    const textoCompleto = titulo ? `${titulo}: ${mensagem}` : mensagem;
    alert(textoCompleto);
}

/**
 * Aguardar o Toast estar disponível
 */
function aguardarToast(callback, maxTentativas = 10) {
    let tentativas = 0;
    
    const verificar = () => {
        if (typeof window.BordadosToast !== 'undefined') {
            console.log('✅ Toast disponível, executando callback');
            callback();
        } else if (tentativas < maxTentativas) {
            tentativas++;
            console.log(`⏳ Aguardando Toast... tentativa ${tentativas}/${maxTentativas}`);
            setTimeout(verificar, 100);
        } else {
            console.warn('⚠️ Toast não carregou, usando fallback');
            callback();
        }
    };
    
    verificar();
}

// ======================================
// FUNÇÕES GLOBAIS - Upload de referência
// ======================================

window.adicionarUpload = function() {
    console.log('adicionarUpload chamada, uploadCount:', window.uploadCount);
    
    if (window.uploadCount >= 3) return;
    
    const items = document.querySelectorAll('.upload-item');
    console.log('Upload items encontrados:', items.length);
    
    if (window.uploadCount < items.length) {
        items[window.uploadCount].style.display = 'block';
        window.uploadCount++;
        console.log('Novo uploadCount:', window.uploadCount);
    }
    
    if (window.uploadCount >= 3) {
        const btn = document.getElementById('btn-add-upload');
        if (btn) btn.style.display = 'none';
    }
};

window.removerUpload = function(btn) {
    const item = btn.closest('.upload-item');
    item.style.display = 'none';
    item.querySelector('input').value = '';
    window.uploadCount--;
    
    const addBtn = document.getElementById('btn-add-upload');
    if (addBtn) addBtn.style.display = 'block';
};

// ======================================
// FUNÇÕES GLOBAIS - Upload final
// ======================================

window.adicionarUploadFinal = function() {
    if (window.uploadFinalCount >= 3) return;
    
    const items = document.querySelectorAll('.upload-final-item');
    if (window.uploadFinalCount < items.length) {
        items[window.uploadFinalCount].style.display = 'block';
        window.uploadFinalCount++;
    }
    
    if (window.uploadFinalCount >= 3) {
        const btn = document.getElementById('btn-add-upload-final');
        if (btn) btn.style.display = 'none';
    }
};

window.removerUploadFinal = function(btn) {
    const item = btn.closest('.upload-final-item');
    item.style.display = 'none';
    item.querySelector('input').value = '';
    window.uploadFinalCount--;
    
    const addBtn = document.getElementById('btn-add-upload-final');
    if (addBtn) addBtn.style.display = 'block';
};

// ======================================
// FUNÇÕES DO PROGRAMADOR - COM TOAST
// ======================================

window.iniciarProducao = function(pedidoId) {
    if (confirm('Tem certeza que deseja iniciar a produção deste pedido?')) {
        jQuery.ajax({
            url: bordados_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iniciar_producao',
                pedido_id: pedidoId,
                nonce: bordados_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // 🍞 Usar Toast em vez de alert
                    mostrarMensagem('success', 'Produção Iniciada!', response.data);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    mostrarMensagem('error', 'Erro ao Iniciar', response.data);
                }
            },
            error: function() {
                mostrarMensagem('error', 'Erro de Comunicação', 'Erro na comunicação com o servidor.');
            }
        });
    }
};

window.entregarTrabalho = function(pedidoId) {
    console.log('entregarTrabalho chamada para pedido:', pedidoId);
    
    const modal = document.getElementById('modal-entrega');
    if (modal) {
        document.getElementById('pedido-id-entrega').value = pedidoId;
        modal.style.display = 'block';
        
        // Reset upload counters
        window.uploadFinalCount = 1;
        const items = document.querySelectorAll('.upload-final-item');
        items.forEach((item, index) => {
            if (index === 0) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
                const input = item.querySelector('input');
                if (input) input.value = '';
            }
        });
        const addBtn = document.getElementById('btn-add-upload-final');
        if (addBtn) addBtn.style.display = 'block';
    } else {
        mostrarMensagem('error', 'Erro!', 'Modal de entrega não encontrado!');
    }
};

window.fecharModal = function() {
    const modal = document.getElementById('modal-entrega');
    if (modal) {
        modal.style.display = 'none';
        const form = document.getElementById('form-entrega');
        if (form) form.reset();
    }
};

// ======================================
// FUNÇÕES DO ADMIN - COM TOAST
// ======================================

window.atribuirPedido = function(pedidoId) {
    var programadorId = jQuery('#programador-' + pedidoId).val();
    var btnAtribuir = jQuery('#btn-atribuir-' + pedidoId);
    
    if (!programadorId) {
        mostrarMensagem('warning', 'Atenção!', 'Por favor, selecione um programador.');
        return;
    }
    
    if (confirm('Atribuir este pedido ao programador selecionado?')) {
        btnAtribuir.prop('disabled', true).text('⏳ Atribuindo...');
        
        jQuery.ajax({
            url: bordados_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'atribuir_pedido',
                pedido_id: pedidoId,
                programador_id: programadorId,
                nonce: bordados_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // 🍞 Usar Toast em vez de mostrarMensagemAdmin
                    mostrarMensagem('success', 'Pedido Atribuído!', response.data.message);
                    
                    setTimeout(function() {
                        btnAtribuir.closest('div[style*="background: #fff"]').fadeOut(500, function() {
                            jQuery(this).remove();
                            
                            if (jQuery('div[style*="background: #fff"]').length === 0) {
                                location.reload();
                            }
                        });
                    }, 2000);
                    
                } else {
                    mostrarMensagem('error', 'Erro na Atribuição', response.data);
                    btnAtribuir.prop('disabled', false).text('✅ Atribuir');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX:', xhr.responseText);
                mostrarMensagem('error', 'Erro de Comunicação', 'Erro na comunicação com o servidor.');
                btnAtribuir.prop('disabled', false).text('✅ Atribuir');
            }
        });
    }
};

// Manter função legacy para compatibilidade
window.mostrarMensagemAdmin = function(tipo, mensagem) {
    const tipoToast = tipo === 'sucesso' ? 'success' : 'error';
    mostrarMensagem(tipoToast, '', mensagem);
};

// ======================================
// FUNÇÕES DE IMAGEM (mantidas originais)
// ======================================

window.mostrarImagemGrande = function(url) {
    var modal = document.getElementById('modal-imagem');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modal-imagem';
        modal.style.cssText = 'display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999999; cursor: pointer;';
        modal.onclick = window.fecharModalImagem;
        
        var content = document.createElement('div');
        content.style.cssText = 'display: flex; justify-content: center; align-items: center; height: 100%; padding: 20px;';
        
        var img = document.createElement('img');
        img.id = 'imagem-ampliada';
        img.style.cssText = 'max-width: 90%; max-height: 90%; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.5);';
        
        var closeBtn = document.createElement('div');
        closeBtn.innerHTML = '×';
        closeBtn.style.cssText = 'position: absolute; top: 20px; right: 30px; color: white; font-size: 30px; cursor: pointer;';
        closeBtn.onclick = window.fecharModalImagem;
        
        content.appendChild(img);
        modal.appendChild(content);
        modal.appendChild(closeBtn);
        document.body.appendChild(modal);
    }
    
    document.getElementById('imagem-ampliada').src = url;
    modal.style.display = 'block';
};

window.fecharModalImagem = function() {
    var modal = document.getElementById('modal-imagem');
    if (modal) {
        modal.style.display = 'none';
    }
};

// ======================================
// FUNÇÕES DE DOWNLOAD - COM TOAST
// ======================================

/**
 * Botão Download agora abre o modal primeiro (v3.3.0)
 * Assim o cliente pode ver os arquivos antes de baixar
 */
window.baixarArquivos = function(pedidoId) {
    console.log('🚀 Abrindo modal de arquivos para pedido #' + pedidoId);
    // Agora abre o modal em vez de baixar direto
    mostrarArquivosFinais(pedidoId);
};

/**
 * Função que realmente faz o download de todos os arquivos
 * Chamada pelo botão "Download All" no modal
 */
window.executarDownloadArquivos = function(pedidoId) {
    console.log('⬇️ Executando download para pedido #' + pedidoId);
    
    if (typeof bordados_ajax === 'undefined') {
        console.error('❌ bordados_ajax não está definido');
        mostrarMensagem('error', 'Erro de Configuração', 'Recarregue a página.');
        return;
    }
    
    jQuery.ajax({
        url: bordados_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'buscar_arquivos_pedido',
            pedido_id: pedidoId,
            nonce: bordados_ajax.nonce
        },
        success: function(response) {
            console.log('📡 Resposta recebida:', response);
            
            if (response.success && response.data && response.data.arquivos) {
                const arquivos = response.data.arquivos;
                console.log('📁 Arquivos encontrados:', arquivos);
                
                if (arquivos.length > 0) {
                    baixarArquivosComDelay(arquivos);
                    
                    // 🍞 Usar Toast em vez de alert
                    mostrarMensagem('success', 'Download Started!', 
                        `${arquivos.length} file(s) being downloaded. Check your Downloads folder.`);
                } else {
                    mostrarMensagem('warning', 'No Files', 'No files available for download.');
                }
            } else {
                console.error('❌ Resposta inválida:', response);
                const erro = response.data || 'Unknown error';
                mostrarMensagem('error', 'Download Error', erro);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Erro AJAX:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            
            let mensagemErro = 'Erro de comunicação com o servidor.';
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse.data) {
                    mensagemErro = errorResponse.data;
                }
            } catch (e) {
                // Usar mensagem padrão
            }
            
            mostrarMensagem('error', 'Erro de Comunicação', mensagemErro + ' Tente novamente em alguns segundos.');
        }
    });
};

// ======================================
// DEMAIS FUNÇÕES DE DOWNLOAD (mantidas originais)
// ======================================

function baixarArquivosComDelay(arquivos) {
    arquivos.forEach(function(arquivo, index) {
        setTimeout(function() {
            console.log(`📥 Downloading file ${index + 1}/${arquivos.length}:`, arquivo);
            criarLinkDownload(arquivo, index + 1);
        }, index * 1500); // Aumentado para 1.5s entre downloads
    });
}

function criarLinkDownload(url, numero) {
    try {
        const nomeArquivo = extrairNomeArquivo(url) || `embroidery_file_${numero}`;
        
        // Método 1: Tentar fetch + blob (funciona melhor para cross-origin)
        fetch(url, {
            method: 'GET',
            mode: 'cors',
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.blob();
        })
        .then(blob => {
            // Criar URL do blob
            const blobUrl = window.URL.createObjectURL(blob);
            
            // Criar link de download
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = nomeArquivo;
            link.style.display = 'none';
            
            document.body.appendChild(link);
            link.click();
            
            // Limpar
            setTimeout(function() {
                document.body.removeChild(link);
                window.URL.revokeObjectURL(blobUrl);
            }, 1000);
            
            console.log(`✅ Download ${numero} completed: ${nomeArquivo}`);
        })
        .catch(error => {
            console.warn(`⚠️ Fetch failed, trying direct method:`, error);
            // Fallback: Abrir em nova aba (usuário pode salvar manualmente)
            window.open(url, '_blank');
        });
        
    } catch (error) {
        console.error(`❌ Error in download ${numero}:`, error);
        // Fallback final: abrir em nova aba
        window.open(url, '_blank');
    }
}

function extrairNomeArquivo(url) {
    try {
        const urlObj = new URL(url);
        const pathname = urlObj.pathname;
        const filename = pathname.split('/').pop();
        return filename && filename.length > 0 ? filename : null;
    } catch (e) {
        const partes = url.split('/');
        return partes[partes.length - 1] || null;
    }
}

window.mostrarArquivosFinais = function(pedidoId) {
    console.log('👁️ Mostrando arquivos finais para pedido #' + pedidoId);
    
    if (typeof bordados_ajax === 'undefined') {
        mostrarMensagem('error', 'Erro de Configuração', 'Recarregue a página.');
        return;
    }
    
    jQuery.ajax({
        url: bordados_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'buscar_arquivos_pedido',
            pedido_id: pedidoId,
            nonce: bordados_ajax.nonce
        },
        success: function(response) {
            if (response.success && response.data.arquivos) {
                mostrarModalArquivos(response.data);
            } else {
                const erro = response.data || 'Nenhum arquivo disponível';
                mostrarMensagem('error', 'Arquivos Indisponíveis', erro);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Erro ao buscar arquivos:', error);
            mostrarMensagem('error', 'Erro de Comunicação', 'Erro ao buscar arquivos. Tente novamente.');
        }
    });
};

// ======================================
// MODAL DE ARQUIVOS MELHORADO (v3.3.0)
// Com ícones por tipo de arquivo
// ======================================

/**
 * Obter ícone e cor por tipo de arquivo
 */
function getFileTypeInfo(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    
    const tipos = {
        // Arquivos de bordado
        'emb': { icone: '🧵', cor: '#9C27B0', nome: 'Wilcom EMB', categoria: 'bordado' },
        'dst': { icone: '🪡', cor: '#E91E63', nome: 'Tajima DST', categoria: 'bordado' },
        'exp': { icone: '🪡', cor: '#F44336', nome: 'Melco EXP', categoria: 'bordado' },
        'pes': { icone: '🪡', cor: '#2196F3', nome: 'Brother PES', categoria: 'bordado' },
        'vp3': { icone: '🪡', cor: '#00BCD4', nome: 'Pfaff VP3', categoria: 'bordado' },
        'jef': { icone: '🪡', cor: '#4CAF50', nome: 'Janome JEF', categoria: 'bordado' },
        'hus': { icone: '🪡', cor: '#FF9800', nome: 'Husqvarna HUS', categoria: 'bordado' },
        'pec': { icone: '🪡', cor: '#795548', nome: 'Brother PEC', categoria: 'bordado' },
        'pcs': { icone: '🪡', cor: '#607D8B', nome: 'Pfaff PCS', categoria: 'bordado' },
        'sew': { icone: '🪡', cor: '#9E9E9E', nome: 'Janome SEW', categoria: 'bordado' },
        'xxx': { icone: '🪡', cor: '#FF5722', nome: 'Singer XXX', categoria: 'bordado' },
        
        // Imagens
        'jpg':  { icone: '🖼️', cor: '#607D8B', nome: 'JPEG Image', categoria: 'imagem' },
        'jpeg': { icone: '🖼️', cor: '#607D8B', nome: 'JPEG Image', categoria: 'imagem' },
        'png':  { icone: '🖼️', cor: '#607D8B', nome: 'PNG Image', categoria: 'imagem' },
        'gif':  { icone: '🖼️', cor: '#607D8B', nome: 'GIF Image', categoria: 'imagem' },
        
        // Documentos
        'pdf': { icone: '📄', cor: '#D32F2F', nome: 'PDF Document', categoria: 'documento' },
        'txt': { icone: '📝', cor: '#757575', nome: 'Text File', categoria: 'documento' },
    };
    
    return tipos[ext] || { icone: '📁', cor: '#9E9E9E', nome: ext.toUpperCase(), categoria: 'outro' };
}

/**
 * Modal de Arquivos Melhorado com Ícones
 */
function mostrarModalArquivos(dados) {
    console.log('📂 Mostrando modal de arquivos (v3.3.0):', dados);
    
    // Remover modal existente se houver
    const modalExistente = document.getElementById('modal-arquivos-finais');
    if (modalExistente) {
        modalExistente.remove();
    }
    
    // Agrupar arquivos por categoria
    const arquivosPorCategoria = {
        bordado: [],
        imagem: [],
        documento: [],
        outro: []
    };
    
    if (dados.arquivos && dados.arquivos.length > 0) {
        dados.arquivos.forEach(function(arquivo) {
            const nomeArquivo = arquivo.split('/').pop();
            const info = getFileTypeInfo(nomeArquivo);
            arquivosPorCategoria[info.categoria].push({
                url: arquivo,
                nome: nomeArquivo,
                info: info
            });
        });
    }
    
    // Criar HTML do modal
    let html = `
    <div id="modal-arquivos-finais" style="
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(0,0,0,0.85); 
        z-index: 10002; 
        display: flex; 
        align-items: center; 
        justify-content: center;
    ">
        <div style="
            background: white; 
            padding: 0; 
            border-radius: 16px; 
            max-width: 550px; 
            width: 95%; 
            max-height: 85vh; 
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        ">
            <!-- Header -->
            <div style="
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px 25px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            ">
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 600;">
                        📦 Order #${dados.pedido_id}
                    </h3>
                    <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;">
                        ${dados.nome_bordado || 'Design files'}
                    </p>
                </div>
                <button onclick="fecharModalArquivos()" style="
                    background: rgba(255,255,255,0.2); 
                    border: none; 
                    font-size: 20px; 
                    cursor: pointer; 
                    color: white; 
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                ">&times;</button>
            </div>
            
            <!-- Conteúdo -->
            <div style="padding: 20px 25px; max-height: 50vh; overflow-y: auto;">
    `;
    
    // Função para renderizar seção de arquivos
    function renderizarSecao(titulo, icone, arquivos, corFundo) {
        if (arquivos.length === 0) return '';
        
        let secaoHtml = `
            <div style="margin-bottom: 20px;">
                <h4 style="
                    margin: 0 0 12px 0; 
                    font-size: 13px; 
                    color: #666;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                ">${icone} ${titulo} (${arquivos.length})</h4>
        `;
        
        arquivos.forEach(function(arq) {
            const extensao = arq.nome.split('.').pop().toUpperCase();
            secaoHtml += `
                <div style="
                    display: flex; 
                    align-items: center; 
                    justify-content: space-between; 
                    padding: 12px 15px; 
                    background: ${corFundo}; 
                    border-radius: 10px; 
                    margin-bottom: 8px;
                    border: 1px solid #e9ecef;
                ">
                    <div style="flex: 1; min-width: 0; display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 24px;">${arq.info.icone}</span>
                        <div style="min-width: 0;">
                            <span style="
                                font-size: 14px; 
                                color: #2c3e50; 
                                display: block; 
                                overflow: hidden; 
                                text-overflow: ellipsis; 
                                white-space: nowrap;
                                font-weight: 500;
                            " title="${arq.nome}">${arq.nome}</span>
                            <span style="
                                font-size: 11px; 
                                color: white; 
                                background: ${arq.info.cor}; 
                                padding: 2px 8px; 
                                border-radius: 4px; 
                                display: inline-block; 
                                margin-top: 4px;
                            ">${extensao}</span>
                        </div>
                    </div>
                    <a href="${arq.url}" target="_blank" download style="
                        background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
                        color: white; 
                        padding: 8px 16px; 
                        border-radius: 8px; 
                        text-decoration: none; 
                        font-size: 12px; 
                        font-weight: 600; 
                        margin-left: 12px;
                    ">⬇️ Download</a>
                </div>
            `;
        });
        
        secaoHtml += '</div>';
        return secaoHtml;
    }
    
    // Renderizar seções na ordem de importância
    html += renderizarSecao('Embroidery Files', '🧵', arquivosPorCategoria.bordado, '#f8f4ff');
    html += renderizarSecao('Documents', '📄', arquivosPorCategoria.documento, '#fff8f0');
    html += renderizarSecao('Images', '🖼️', arquivosPorCategoria.imagem, '#f0f8ff');
    html += renderizarSecao('Other Files', '📁', arquivosPorCategoria.outro, '#f5f5f5');
    
    // Mensagem se não houver arquivos
    if (!dados.arquivos || dados.arquivos.length === 0) {
        html += `
            <div style="text-align: center; padding: 40px 20px; color: #666;">
                <span style="font-size: 48px; display: block; margin-bottom: 15px;">📭</span>
                <p style="margin: 0; font-size: 15px;">No files available for download.</p>
            </div>
        `;
    }
    
    html += '</div>'; // Fecha conteúdo
    
    // Footer com botões
    html += `
        <div style="
            padding: 20px 25px; 
            border-top: 1px solid #e9ecef; 
            display: flex; 
            gap: 12px; 
            justify-content: center;
            background: #fafafa;
        ">
    `;
    
    if (dados.arquivos && dados.arquivos.length > 0) {
        html += `
            <button onclick="baixarTodosArquivos(${dados.pedido_id})" style="
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
                color: white; 
                border: none; 
                padding: 12px 24px; 
                border-radius: 10px; 
                cursor: pointer; 
                font-weight: 600; 
                font-size: 14px;
            ">⬇️ Download All</button>
        `;
    }
    
    html += `
            <button onclick="fecharModalArquivos()" style="
                background: #6c757d; 
                color: white; 
                border: none; 
                padding: 12px 24px; 
                border-radius: 10px; 
                cursor: pointer; 
                font-weight: 500; 
                font-size: 14px;
            ">✕ Close</button>
        </div>
    </div>
    </div>`;
    
    // Inserir modal no DOM
    document.body.insertAdjacentHTML('beforeend', html);
    
    // Fechar ao clicar fora
    document.getElementById('modal-arquivos-finais').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalArquivos();
        }
    });
}

window.fecharModalArquivos = function() {
    const modal = document.getElementById('modal-arquivos-finais');
    if (modal) {
        modal.remove();
    }
};

window.baixarTodosArquivos = function(pedidoId) {
    fecharModalArquivos();
    executarDownloadArquivos(pedidoId);
};

// Exportar para uso global
window.mostrarModalArquivos = mostrarModalArquivos;
window.getFileTypeInfo = getFileTypeInfo;

// ======================================
// 🍞 INICIALIZAÇÃO COM INTEGRAÇÃO TOAST
// ======================================

jQuery(document).ready(function($) {
    console.log('=== Sistema de Bordados Carregado COM TOAST ===');
    console.log('jQuery:', typeof $);
    console.log('bordados_ajax:', window.bordados_ajax);
    
    // Aguardar Toast estar disponível antes de configurar eventos críticos
    aguardarToast(function() {
        console.log('🍞 Toast disponível, configurando eventos...');
        
        // SUBMISSÃO DO FORMULÁRIO DE NOVO PEDIDO - COM TOAST
        $('#form-novo-pedido').on('submit', function(e) {
            e.preventDefault();
            console.log('Form novo pedido submetido');
            
            const formData = new FormData(this);
            formData.append('action', 'criar_pedido');
            
            // 🍞 Limpar mensagens anteriores e mostrar loading
            if (typeof window.BordadosToast !== 'undefined') {
                window.BordadosToast.clear();
                window.BordadosToast.info('Enviando pedido...', 'Aguarde', { duration: 0 });
            }
            
            $.ajax({
                url: bordados_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // 🍞 Limpar loading toast
                    if (typeof window.BordadosToast !== 'undefined') {
                        window.BordadosToast.clear();
                    }
                    
                    if (response.success) {
                        // 🍞 Toast de sucesso
                        mostrarMensagem('success', 'Order Created!', response.data.message);
                        
                        // Reset do formulário
                        document.getElementById('form-novo-pedido').reset();
                        
                        // Reset upload counters
                        window.uploadCount = 1;
                        document.querySelectorAll('.upload-item').forEach((item, index) => {
                            if (index === 0) {
                                item.style.display = 'block';
                            } else {
                                item.style.display = 'none';
                                item.querySelector('input').value = '';
                            }
                        });
                        const addBtn = document.getElementById('btn-add-upload');
                        if (addBtn) addBtn.style.display = 'block';
                        
                        // Scroll para o topo para ver o toast
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        
                    } else {
                        // 🍞 Toast de erro
                        mostrarMensagem('error', 'Erro ao Criar Pedido', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX:', xhr.responseText);
                    
                    // 🍞 Limpar loading toast
                    if (typeof window.BordadosToast !== 'undefined') {
                        window.BordadosToast.clear();
                    }
                    
                    mostrarMensagem('error', 'Erro de Comunicação', 'Erro de conexão com o servidor');
                }
            });
        });
    });
    
    // SUBMISSÃO DO FORMULÁRIO DE ENTREGA - COM TOAST
    $('#form-entrega').on('submit', function(e) {
        e.preventDefault();
        console.log('Form entrega submetido');
        
        var pedidoId = $('#pedido-id-entrega').val();
        var precoProgr = $('#preco-programador').val();
        var numeroPontos = $('#numero-pontos').val();
        var obsProgr = $('#obs-programador').val();
        
        if (!precoProgr || precoProgr <= 0) {
            mostrarMensagem('warning', 'Price Required', 'Please enter the price.');
            return;
        }
        
        if (!numeroPontos || numeroPontos <= 0) {
            mostrarMensagem('warning', 'Stitch Count Required', 'Please enter the stitch count.');
            return;
        }
        
        var temArquivo = false;
        $('input[name="arquivos_finais[]"]').each(function() {
            if (this.files && this.files.length > 0) {
                temArquivo = true;
            }
        });
        
        if (!temArquivo) {
            mostrarMensagem('warning', 'File Required', 'Please select at least one final file.');
            return;
        }
        
        var formData = new FormData(this);
        formData.append('action', 'finalizar_trabalho');
        formData.append('pedido_id', pedidoId);
        formData.append('preco_programador', precoProgr);
        formData.append('numero_pontos', numeroPontos);
        formData.append('observacoes_programador', obsProgr);
        formData.append('nonce', bordados_ajax.nonce);
        
        $('#form-entrega button[type="submit"]').prop('disabled', true).text('📤 Finishing...');
        
        $.ajax({
            url: bordados_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // 🍞 Toast de sucesso
                    mostrarMensagem('success', 'Work Finished!', response.data);
                    window.fecharModal();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    mostrarMensagem('error', 'Finishing Error', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro AJAX:', xhr.responseText);
                mostrarMensagem('error', 'Communication Error', 'Error communicating with server.');
            },
            complete: function() {
                $('#form-entrega button[type="submit"]').prop('disabled', false).text('✅ Finish and Deliver');
            }
        });
    });
    
    // EVENTOS GLOBAIS
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) { // ESC
            window.fecharModal();
            window.fecharModalImagem();
            window.fecharModalArquivos();
        }
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).is('#modal-entrega')) {
            window.fecharModal();
        }
        if ($(e.target).is('#modal-arquivos-finais')) {
            window.fecharModalArquivos();
        }
    });
    
    // Corrigir botões após carregar
    setTimeout(function() {
        corrigirBotoesAcoes();
    }, 500);
});

// ======================================
// CORREÇÃO PARA BOTÕES NÃO CLICÁVEIS (mantida original)
// ======================================

function corrigirBotoesAcoes() {
    console.log('🔧 Corrigindo botões da coluna ações...');
    
    document.querySelectorAll('a[onclick*="baixarArquivos"]').forEach(function(btn, index) {
        btn.style.pointerEvents = 'auto';
        btn.style.cursor = 'pointer';
        btn.style.zIndex = '9999';
        btn.style.position = 'relative';
        btn.style.display = 'inline-block';
        console.log('✅ Botão baixar', index + 1, 'corrigido');
    });
    
    document.querySelectorAll('a[onclick*="mostrarArquivos"]').forEach(function(btn, index) {
        btn.style.pointerEvents = 'auto';
        btn.style.cursor = 'pointer';
        btn.style.zIndex = '9999';
        btn.style.position = 'relative';
        btn.style.display = 'inline-block';
        console.log('✅ Botão ver', index + 1, 'corrigido');
    });
    
    console.log('🎉 Correção de botões concluída!');
}

document.addEventListener('DOMContentLoaded', corrigirBotoesAcoes);

if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(corrigirBotoesAcoes);
}

setTimeout(corrigirBotoesAcoes, 1000);

// Log quando a página termina de carregar
window.addEventListener('load', function() {
    console.log('=== Página totalmente carregada COM TOAST ===');
    console.log('Toast disponível:', typeof window.BordadosToast !== 'undefined');
    
    // Teste do Toast (apenas para debug - DESABILITADO por padrão)
    if (typeof window.BordadosToast !== 'undefined' && false) { // Mudar para true para testar
        setTimeout(() => {
            window.BordadosToast.info('Sistema carregado com sucesso!', '🍞 Toast Ativo');
        }, 1000);
    }
});

console.log('=== JavaScript carregado COM INTEGRAÇÃO TOAST ===');
