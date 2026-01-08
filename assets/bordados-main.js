/**
 * JavaScript principal do Sistema de Bordados - VERSÃO COM TOAST INTEGRADO
 * Arquivo: bordados-main.js 
 * Local: wp-content/plugins/sistema-bordados-simples/assets/bordados-main.js
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

window.baixarArquivos = function(pedidoId) {
    console.log('🚀 Iniciando download para pedido #' + pedidoId);
    
    let btn = document.querySelector(`button[onclick*="baixarArquivos(${pedidoId})"]`) ||
              document.querySelector(`a[onclick*="baixarArquivos(${pedidoId})"]`) ||
              document.querySelector(`[onclick*="baixarArquivos(${pedidoId})"]`);
    
    let textoOriginal = '';
    
    if (btn) {
        textoOriginal = btn.innerHTML;
        btn.innerHTML = '⏳ Carregando...';
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.6';
        console.log('✅ Botão encontrado e bloqueado');
    } else {
        console.warn('⚠️ Botão não encontrado');
    }
    
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
        },
        complete: function() {
            if (btn) {
                btn.innerHTML = textoOriginal;
                btn.style.pointerEvents = 'auto';
                btn.style.opacity = '1';
                console.log('✅ Botão restaurado');
            }
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

function mostrarModalArquivos(dados) {
    console.log('📂 Mostrando modal de arquivos:', dados);
    
    // Remover modal existente se houver
    const modalExistente = document.getElementById('modal-arquivos-finais');
    if (modalExistente) {
        modalExistente.remove();
    }
    
    // Criar HTML do modal
    let html = '<div id="modal-arquivos-finais" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10002; display: flex; align-items: center; justify-content: center;">';
    html += '<div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">';
    
    // Header
    html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e9ecef;">';
    html += '<h3 style="margin: 0; color: #2c3e50; font-size: 18px;">📦 Order #' + dados.pedido_id + ' Files</h3>';
    html += '<button onclick="fecharModalArquivos()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6c757d; padding: 0; line-height: 1;">&times;</button>';
    html += '</div>';
    
    // Nome do bordado
    html += '<p style="margin: 0 0 20px 0; color: #666; font-size: 14px;"><strong>Design:</strong> ' + (dados.nome_bordado || 'N/A') + '</p>';
    
    // Lista de arquivos
    html += '<div style="margin-bottom: 20px;">';
    html += '<p style="margin: 0 0 10px 0; font-weight: 600; color: #495057;">📎 Available Files (' + dados.total_arquivos + '):</p>';
    
    if (dados.arquivos && dados.arquivos.length > 0) {
        dados.arquivos.forEach(function(arquivo, index) {
            // Extrair nome do arquivo da URL
            const nomeArquivo = arquivo.split('/').pop() || 'File ' + (index + 1);
            const extensao = nomeArquivo.split('.').pop().toUpperCase();
            
            html += '<div class="arquivo-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 8px; border: 1px solid #e9ecef;">';
            html += '<div style="flex: 1; min-width: 0;">';
            html += '<span style="font-size: 13px; color: #2c3e50; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' + nomeArquivo + '">' + nomeArquivo + '</span>';
            html += '<span style="font-size: 11px; color: #6c757d; background: #e9ecef; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px;">' + extensao + '</span>';
            html += '</div>';
            html += '<a href="' + arquivo + '" target="_blank" download style="background: #007bff; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 500; margin-left: 10px; white-space: nowrap;">⬇️ Download</a>';
            html += '</div>';
        });
    } else {
        html += '<p style="color: #dc3545; font-style: italic;">No files available.</p>';
    }
    
    html += '</div>';
    
    // Botões de ação
    html += '<div style="display: flex; gap: 10px; justify-content: center; padding-top: 15px; border-top: 1px solid #e9ecef;">';
    if (dados.arquivos && dados.arquivos.length > 0) {
        html += '<button onclick="baixarTodosArquivos(' + dados.pedido_id + ')" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">⬇️ Download All</button>';
    }
    html += '<button onclick="fecharModalArquivos()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px;">✕ Close</button>';
    html += '</div>';
    
    html += '</div></div>';
    
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
    baixarArquivos(pedidoId);
};

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