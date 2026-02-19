/**
 * Sistema de Bordados - Funções do Revisor e Edição
 * Extraído de sistema-bordados-simples.php
 * 
 * Dependências:
 * - jQuery
 * - bordados_ajax (wp_localize_script)
 * - BordadosToast (bordados-toast.js)
 */

console.log('🔥 CARREGANDO bordados-revisor.js v1.0');

// ===============================
// SISTEMA TOAST COMPLETO
// ===============================

window.BordadosToast = {
    init: function() {
        if (document.getElementById('bordados-toast-container')) return true;

        try {
            const container = document.createElement('div');
            container.id = 'bordados-toast-container';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;pointer-events:none;font-family:sans-serif;max-width:400px;';
            document.body.appendChild(container);

            // CSS completo inline
            if (!document.getElementById('toast-styles-complete')) {
                const style = document.createElement('style');
                style.id = 'toast-styles-complete';
                style.textContent = `
                    .bordados-toast{background:white !important;border-radius:12px !important;box-shadow:0 8px 25px rgba(0,0,0,0.15) !important;margin-bottom:15px !important;padding:16px 20px !important;min-height:60px !important;display:flex !important;align-items:center !important;position:relative !important;overflow:hidden !important;opacity:0 !important;transform:translateX(100%) !important;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275) !important;pointer-events:auto !important;border-left:4px solid #007cba !important;cursor:pointer !important;}
                    .bordados-toast.show{opacity:1 !important;transform:translateX(0) !important;}
                    .bordados-toast.hide{opacity:0 !important;transform:translateX(100%) !important;margin-bottom:0 !important;min-height:0 !important;}
                    .bordados-toast.success{border-left-color:#28a745 !important;}
                    .bordados-toast.error{border-left-color:#dc3545 !important;}
                    .bordados-toast.warning{border-left-color:#ffc107 !important;}
                    .bordados-toast.info{border-left-color:#17a2b8 !important;}
                    .toast-icon{font-size:24px !important;margin-right:12px !important;flex-shrink:0 !important;}
                    .toast-content{flex:1 !important;line-height:1.4 !important;}
                    .toast-title{font-weight:600 !important;color:#333 !important;margin:0 0 4px 0 !important;font-size:15px !important;}
                    .toast-message{color:#666 !important;margin:0 !important;font-size:14px !important;}
                    .toast-close{background:none !important;border:none !important;color:#999 !important;font-size:18px !important;cursor:pointer !important;padding:4px !important;margin-left:8px !important;border-radius:4px !important;flex-shrink:0 !important;}
                    .toast-close:hover{background:#f5f5f5 !important;color:#333 !important;}
                    .toast-progress{position:absolute !important;bottom:0 !important;left:0 !important;height:3px !important;background:rgba(0,0,0,0.1) !important;width:100% !important;animation:toastProgress linear !important;}
                    .bordados-toast.success .toast-progress{background:#28a745 !important;}
                    .bordados-toast.error .toast-progress{background:#dc3545 !important;}
                    .bordados-toast.warning .toast-progress{background:#ffc107 !important;}
                    .bordados-toast.info .toast-progress{background:#17a2b8 !important;}
                    @keyframes toastProgress{from{width:100%;}to{width:0%;}}
                    @media (max-width:768px){#bordados-toast-container{top:10px !important;right:10px !important;left:10px !important;max-width:none !important;}}
                `;
                document.head.appendChild(style);
            }

            console.log('✅ Toast container criado');
            return true;
        } catch (error) {
            console.error('❌ Erro no Toast:', error);
            return false;
        }
    },

    show: function(options) {
        try {
            if (!this.init()) throw new Error('Falha ao inicializar');

            const config = {
                type: options.type || 'info',
                title: options.title || '',
                message: options.message || 'Mensagem',
                duration: options.duration || 5000,
                ...options
            };

            const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };

            const toast = document.createElement('div');
            toast.className = `bordados-toast ${config.type}`;

            let html = `<div class="toast-icon">${icons[config.type]}</div><div class="toast-content">`;
            if (config.title) html += `<div class="toast-title">${config.title}</div>`;
            html += `<div class="toast-message">${config.message}</div></div>`;
            html += `<button class="toast-close" onclick="this.parentNode.remove()">×</button>`;

            if (config.duration > 0) {
                html += `<div class="toast-progress" style="animation-duration:${config.duration}ms"></div>`;
            }

            toast.innerHTML = html;

            document.getElementById('bordados-toast-container').appendChild(toast);

            setTimeout(() => toast.classList.add('show'), 50);

            if (config.duration > 0) {
                setTimeout(() => {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, config.duration);
            }

            console.log('✅ Toast criado:', config.type, config.message);
            return toast;

        } catch (error) {
            console.error('❌ Erro ao criar Toast:', error);
            alert((options.title ? options.title + ': ' : '') + options.message);
            return null;
        }
    },

    success: function(message, title) {
        title = title || 'Sucesso!';
        return this.show({ type: 'success', title: title, message: message, duration: 6000 });
    },

    error: function(message, title) {
        title = title || 'Erro!';
        return this.show({ type: 'error', title: title, message: message, duration: 8000 });
    },

    warning: function(message, title) {
        title = title || 'Atenção!';
        return this.show({ type: 'warning', title: title, message: message, duration: 7000 });
    },

    info: function(message, title) {
        title = title || 'Informação';
        return this.show({ type: 'info', title: title, message: message, duration: 5000 });
    },

    clear: function() {
        const container = document.getElementById('bordados-toast-container');
        if (container) {
            const toasts = container.querySelectorAll('.bordados-toast');
            toasts.forEach(toast => toast.remove());
        }
    }
};

// ===============================
// FUNÇÃO AUXILIAR GLOBAL
// ===============================

window.mostrarMensagem = function(tipo, titulo, mensagem) {
    console.log('📢 Mensagem ' + tipo + ':', titulo, mensagem);

    if (typeof window.BordadosToast !== 'undefined' && window.BordadosToast[tipo]) {
        try {
            return window.BordadosToast[tipo](mensagem, titulo);
        } catch (error) {
            console.warn('⚠️ Erro no Toast, usando fallback:', error);
        }
    }

    alert(titulo ? titulo + ': ' + mensagem : mensagem);
};

// ===============================
// EVENTOS DO FORMULÁRIO NOVO PEDIDO
// ===============================

function configurarEventosFormulario() {
    console.log('🔧 Configurando eventos do formulário...');

    var formNovoPedido = document.getElementById('form-novo-pedido');
    if (formNovoPedido) {
        console.log('✅ Formulário novo pedido encontrado');

        // Remover eventos anteriores
        var novoForm = formNovoPedido.cloneNode(true);
        formNovoPedido.parentNode.replaceChild(novoForm, formNovoPedido);

        // Adicionar evento novo
        novoForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('🚀 Form novo pedido submetido COM TOAST');

            var formData = new FormData(this);
            formData.append('action', 'criar_pedido');

            // Mostrar loading
            window.BordadosToast.clear();
            window.BordadosToast.info('Enviando pedido...', 'Aguarde', { duration: 0 });

            // AJAX usando jQuery
            if (typeof jQuery !== 'undefined' && typeof bordados_ajax !== 'undefined') {
                jQuery.ajax({
                    url: bordados_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        window.BordadosToast.clear();

                        if (response.success) {
                            window.mostrarMensagem('success', 'Order Created!', response.data.message);

                            // Reset do formulário
                            novoForm.reset();

                            // Scroll para o topo
                            window.scrollTo({ top: 0, behavior: 'smooth' });

                        } else {
                            window.mostrarMensagem('error', 'Erro ao Criar Pedido', response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro AJAX:', xhr.responseText);
                        window.BordadosToast.clear();
                        window.mostrarMensagem('error', 'Erro de Comunicação', 'Erro de conexão com o servidor');
                    }
                });
            } else {
                // Fallback com fetch
                fetch(bordados_ajax.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    window.BordadosToast.clear();

                    if (data.success) {
                        window.mostrarMensagem('success', 'Order Created!', data.data.message);
                        novoForm.reset();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    } else {
                        window.mostrarMensagem('error', 'Erro ao Criar Pedido', data.data);
                    }
                })
                .catch(function(error) {
                    console.error('Erro fetch:', error);
                    window.BordadosToast.clear();
                    window.mostrarMensagem('error', 'Erro de Comunicação', 'Erro de conexão com o servidor');
                });
            }
        });

        console.log('✅ Evento de submit configurado');
    } else {
        console.log('⚠️ Formulário novo pedido não encontrado nesta página');
    }
}

// ===============================
// FUNÇÕES DO REVISOR
// ===============================

function iniciarRevisao(pedidoId) {
    if (!confirm('Deseja iniciar a revisão deste trabalho?')) return;
    
    jQuery.ajax({
        url: bordados_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'iniciar_revisao',
            nonce: bordados_ajax.nonce,
            pedido_id: pedidoId
        },
        success: function(response) {
            if (response.success) {
                window.mostrarMensagem('success', 'Sucesso!', response.data);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                window.mostrarMensagem('error', 'Erro!', response.data);
            }
        },
        error: function() {
            window.mostrarMensagem('error', 'Erro!', 'Erro de comunicação com o servidor');
        }
    });
}

function aprovarTrabalho(pedidoId) {
    if (!confirm('Tem certeza que deseja APROVAR este trabalho?\n\nO cliente será notificado e poderá baixar os arquivos.')) return;
    
    // Verificar se há arquivos revisados para upload
    var container = document.getElementById('upload-revisao-' + pedidoId);
    var inputs = container.querySelectorAll('input[type="file"]');
    var temArquivos = false;
    
    inputs.forEach(function(input) {
        if (input.files && input.files.length > 0) {
            temArquivos = true;
        }
    });
    
    if (temArquivos) {
        // Aprovar COM upload de arquivos revisados
        aprovarComArquivosRevisados(pedidoId);
    } else {
        // Aprovar SEM modificações (arquivos originais)
        aprovarSemModificacoes(pedidoId);
    }
}

function aprovarSemModificacoes(pedidoId) {
    jQuery.ajax({
        url: bordados_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'aprovar_trabalho',
            nonce: bordados_ajax.nonce,
            pedido_id: pedidoId
        },
        success: function(response) {
            if (response.success) {
                window.mostrarMensagem('success', 'Aprovado!', response.data);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                window.mostrarMensagem('error', 'Erro!', response.data);
            }
        },
        error: function() {
            window.mostrarMensagem('error', 'Erro!', 'Erro de comunicação com o servidor');
        }
    });
}

function aprovarComArquivosRevisados(pedidoId) {
    var formData = new FormData();
    formData.append('action', 'aprovar_trabalho_com_arquivos');
    formData.append('nonce', bordados_ajax.nonce);
    formData.append('pedido_id', pedidoId);
    
    // Adicionar todos os arquivos selecionados
    var inputs = document.querySelectorAll('input[name="arquivos_revisados_' + pedidoId + '[]"]');
    var totalArquivos = 0;
    
    inputs.forEach(function(input) {
        if (input.files && input.files.length > 0) {
            formData.append('arquivos_revisados[]', input.files[0]);
            totalArquivos++;
        }
    });
    
    if (totalArquivos === 0) {
        window.mostrarMensagem('error', 'Erro!', 'Nenhum arquivo selecionado');
        return;
    }
    
    // Mostrar loading
    window.BordadosToast.clear();
    window.BordadosToast.info('Enviando ' + totalArquivos + ' arquivo(s) revisado(s)...', 'Aguarde', { duration: 0 });
    
    jQuery.ajax({
        url: bordados_ajax.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            window.BordadosToast.clear();
            
            if (response.success) {
                window.mostrarMensagem('success', 'Aprovado!', response.data);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                window.mostrarMensagem('error', 'Erro!', response.data);
            }
        },
        error: function() {
            window.BordadosToast.clear();
            window.mostrarMensagem('error', 'Erro!', 'Erro de comunicação com o servidor');
        }
    });
}

function adicionarUploadRevisao(pedidoId) {
    var container = document.getElementById('uploads-revisao-container-' + pedidoId);
    var items = container.querySelectorAll('.upload-revisao-item');
    
    for (var i = 0; i < items.length; i++) {
        if (items[i].style.display === 'none') {
            items[i].style.display = 'block';
            
            // Se chegou no último, esconder botão
            if (i === items.length - 1) {
                document.getElementById('btn-add-upload-revisao-' + pedidoId).style.display = 'none';
            }
            break;
        }
    }
}

function removerUploadRevisao(btn, pedidoId) {
    var item = btn.closest('.upload-revisao-item');
    var input = item.querySelector('input[type="file"]');
    input.value = '';
    item.style.display = 'none';
    
    // Mostrar botão de adicionar novamente
    document.getElementById('btn-add-upload-revisao-' + pedidoId).style.display = 'inline-block';
}

function solicitarAcertos(pedidoId) {
    // Versão modal (substitui a versão antiga com prompt)
    var modalAcertos = document.getElementById('modal-acertos');
    if (modalAcertos) {
        document.getElementById('acertos-pedido-id').value = pedidoId;
        document.getElementById('acertos-info').innerHTML = 
            '<strong>Pedido #' + pedidoId + '</strong><br>Descreva os problemas encontrados e anexe imagens se necessário.';
        document.getElementById('acertos-descricao').value = '';
        
        // Reset uploads
        var items = document.querySelectorAll('#uploads-acertos-container .upload-acertos-item');
        items.forEach(function(item, index) {
            var input = item.querySelector('input[type="file"]');
            if (input) input.value = '';
            if (index > 0) item.style.display = 'none';
        });
        var btnAdd = document.getElementById('btn-add-upload-acertos');
        if (btnAdd) btnAdd.style.display = 'inline-block';
        
        modalAcertos.style.display = 'flex';
    } else {
        // Fallback: prompt se modal não existir
        var observacoes = prompt('Descreva os acertos necessários:');
        if (!observacoes) return;
        
        jQuery.ajax({
            url: bordados_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'solicitar_acertos_revisor',
                nonce: bordados_ajax.nonce,
                pedido_id: pedidoId,
                obs_revisor: observacoes
            },
            success: function(response) {
                if (response.success) {
                    window.mostrarMensagem('warning', 'Acertos Solicitados!', response.data);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    window.mostrarMensagem('error', 'Erro!', response.data);
                }
            },
            error: function() {
                window.mostrarMensagem('error', 'Erro!', 'Erro de comunicação com o servidor');
            }
        });
    }
}

// ===============================
// FUNÇÕES DO MODAL DE ACERTOS
// ===============================

window.fecharModalAcertos = function() {
    var modal = document.getElementById('modal-acertos');
    if (modal) modal.style.display = 'none';
};

window.adicionarUploadAcertos = function() {
    var items = document.querySelectorAll('#uploads-acertos-container .upload-acertos-item');
    for (var i = 0; i < items.length; i++) {
        if (items[i].style.display === 'none') {
            items[i].style.display = 'block';
            if (i === items.length - 1) {
                var btnAdd = document.getElementById('btn-add-upload-acertos');
                if (btnAdd) btnAdd.style.display = 'none';
            }
            break;
        }
    }
};

window.removerUploadAcertos = function(btn) {
    var item = btn.parentNode;
    var input = item.querySelector('input[type="file"]');
    if (input) input.value = '';
    item.style.display = 'none';
    var btnAdd = document.getElementById('btn-add-upload-acertos');
    if (btnAdd) btnAdd.style.display = 'inline-block';
};

// ===============================
// MODAL DE APROVAÇÃO DO REVISOR
// ===============================

window.abrirModalAprovacao = function(pedidoId, nomeBordado, clienteNome, precoProgramador, clienteId, numeroPontos, precoFinalExistente) {
    console.log('✅ abrirModalAprovacao chamada para pedido #' + pedidoId);

    var modalAprovacao = document.getElementById('modal-aprovacao');
    if (!modalAprovacao) {
        console.error('❌ modal-aprovacao não encontrado no DOM');
        alert('Erro: modal de aprovação não encontrado. Recarregue a página.');
        return;
    }

    document.getElementById('aprovacao-pedido-id').value = pedidoId;

    // Guardar clienteId globalmente para o event listener de recálculo
    window.aprovacaoClienteId = clienteId;
    var campoClienteId = document.getElementById('aprovacao-cliente-id');
    if (campoClienteId) campoClienteId.value = clienteId;

    var campoPontos = document.getElementById('aprovacao-numero-pontos');
    if (campoPontos) campoPontos.value = numeroPontos || '';

    var campoInfo = document.getElementById('aprovacao-info');
    if (campoInfo) {
        campoInfo.innerHTML =
            '<strong>Pedido #' + pedidoId + ':</strong> ' + nomeBordado + '<br>' +
            '<strong>Cliente:</strong> ' + clienteNome;
    }

    // Campo preço do programador (só existe para administrators)
    var campoPrecoProgramador = document.getElementById('aprovacao-preco-programador');
    if (campoPrecoProgramador) {
        campoPrecoProgramador.value = precoProgramador
            ? 'R$ ' + parseFloat(precoProgramador).toFixed(2)
            : 'Não definido';
    }

    // Limpar campos
    var campoObs = document.getElementById('aprovacao-obs');
    if (campoObs) campoObs.value = '';

    var inputArquivos = document.getElementById('arquivos-revisados-input');
    if (inputArquivos) inputArquivos.value = '';

    var listaArquivos = document.getElementById('arquivos-selecionados');
    if (listaArquivos) listaArquivos.innerHTML = '';

    // Abrir modal
    modalAprovacao.style.display = 'flex';

    // Se já existe preço final definido, usar ele (não recalcular)
    var campoPrecoFinal = document.getElementById('aprovacao-preco-final');
    if (precoFinalExistente && parseFloat(precoFinalExistente) > 0) {
        if (campoPrecoFinal) {
            campoPrecoFinal.value = parseFloat(precoFinalExistente).toFixed(2);
            campoPrecoFinal.placeholder = 'Preço já definido anteriormente';
        }
        console.log('Usando preço final existente: ' + precoFinalExistente);
        return;
    }

    // Calcular preço automaticamente se tiver pontos
    if (clienteId && numeroPontos && numeroPontos > 0) {
        if (campoPrecoFinal) {
            campoPrecoFinal.value = '';
            campoPrecoFinal.placeholder = 'Calculando...';
        }

        jQuery.ajax({
            url: bordados_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'calcular_preco_orcamento',
                nonce: bordados_ajax.nonce,
                cliente_id: clienteId,
                pontos: numeroPontos,
                preco_programador: precoProgramador
            },
            success: function(response) {
                if (response.success && response.data.preco_final) {
                    if (campoPrecoFinal) {
                        campoPrecoFinal.value = response.data.preco_final;
                        campoPrecoFinal.placeholder = 'Calculado: R$ ' + response.data.preco_final;
                    }
                } else {
                    if (campoPrecoFinal) campoPrecoFinal.value = precoProgramador || '';
                }
            },
            error: function() {
                if (campoPrecoFinal) campoPrecoFinal.value = precoProgramador || '';
            }
        });
    } else {
        if (campoPrecoFinal) campoPrecoFinal.value = precoProgramador || '';
    }
};

window.fecharModalAprovacao = function() {
    var modal = document.getElementById('modal-aprovacao');
    if (modal) modal.style.display = 'none';
};

// ===============================
// FUNÇÃO PARA REENVIAR TRABALHO CORRIGIDO
// ===============================

function reenviarTrabalho(pedidoId) {
    // Abrir o modal de entrega (reutilizar o mesmo modal)
    document.getElementById('pedido-id-entrega').value = pedidoId;
    document.getElementById('modal-entrega').style.display = 'block';
}

// ===============================
// EVENT LISTENERS DO MODAL APROVAÇÃO
// ===============================

jQuery(document).ready(function($) {

    // Recálculo de preço quando revisor altera pontos
    $(document).on('input change', '#aprovacao-numero-pontos', function() {
        var pontos = parseInt($(this).val());
        if (!pontos || pontos <= 0) {
            $('#aprovacao-preco-final').val('').attr('placeholder', 'Enter stitch count first');
            return;
        }
        var clienteId = window.aprovacaoClienteId || $('#aprovacao-cliente-id').val();
        if (!clienteId) return;
        $('#aprovacao-preco-final').val('').attr('placeholder', 'Recalculating...');
        $.ajax({
            url: bordados_ajax.ajax_url,
            type: 'POST',
            data: { action: 'calcular_preco_orcamento', nonce: bordados_ajax.nonce, cliente_id: clienteId, pontos: pontos },
            success: function(response) {
                if (response.success && response.data.preco_final) {
                    var precoNovo = parseFloat(response.data.preco_final).toFixed(2);
                    $('#aprovacao-preco-final').val(precoNovo).attr('placeholder', 'Calculated: $' + precoNovo);
                } else {
                    $('#aprovacao-preco-final').attr('placeholder', 'Enter price manually');
                }
            },
            error: function() { $('#aprovacao-preco-final').attr('placeholder', 'Enter price manually'); }
        });
    });

    // Mostrar arquivos selecionados no upload múltiplo
    $(document).on('change', '#arquivos-revisados-input', function() {
        var files = this.files;
        var lista = document.getElementById('arquivos-selecionados');
        if (!lista) return;
        if (files.length > 0) {
            var html = '<strong>' + files.length + ' arquivo(s) selecionado(s):</strong><ul style="margin:5px 0;padding-left:20px;">';
            for (var i = 0; i < Math.min(files.length, 5); i++) { html += '<li>' + files[i].name + '</li>'; }
            if (files.length > 5) { html += '<li>... e mais ' + (files.length - 5) + ' arquivo(s)</li>'; }
            html += '</ul>';
            lista.innerHTML = html;
        } else { lista.innerHTML = ''; }
    });

    // Submit do form de aprovação
    $(document).on('submit', '#form-aprovacao', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('action', 'aprovar_trabalho_revisor');
        formData.append('nonce', bordados_ajax.nonce);
        var btnSubmit = this.querySelector('button[type="submit"]');
        var textoOriginal = btnSubmit.innerHTML;
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '⏳ Processando...';
        $.ajax({
            url: bordados_ajax.ajax_url, type: 'POST', data: formData, processData: false, contentType: false,
            success: function(response) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = textoOriginal;
                if (response.success) {
                    window.mostrarMensagem('success', 'Aprovado!', response.data);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    window.mostrarMensagem('error', 'Erro!', response.data);
                }
            },
            error: function() {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = textoOriginal;
                window.mostrarMensagem('error', 'Erro!', 'Erro de conexão. Tente novamente.');
            }
        });
    });

    // Submit do form de acertos
    $(document).on('submit', '#form-acertos', function(e) {
        e.preventDefault();
        var descricao = $('#acertos-descricao').val().trim();
        if (!descricao) { alert('Por favor, descreva os acertos necessários.'); return; }
        var formData = new FormData(this);
        formData.append('action', 'solicitar_acertos_revisor');
        formData.append('nonce', bordados_ajax.nonce);
        var btnSubmit = this.querySelector('button[type="submit"]');
        var textoOriginal = btnSubmit.innerHTML;
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '⏳ Enviando...';
        $.ajax({
            url: bordados_ajax.ajax_url, type: 'POST', data: formData, processData: false, contentType: false,
            success: function(response) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = textoOriginal;
                if (response.success) {
                    window.mostrarMensagem('warning', 'Acertos Solicitados!', response.data);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    window.mostrarMensagem('error', 'Erro!', response.data);
                }
            },
            error: function() {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = textoOriginal;
                window.mostrarMensagem('error', 'Erro!', 'Erro de comunicação com o servidor');
            }
        });
    });

});

// ===============================
// SUBMIT DO FORMULÁRIO DE EDIÇÃO
// ===============================

jQuery(document).ready(function($) {
    $('#form-edicao').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'solicitar_edicao');
        formData.append('nonce', bordados_ajax.nonce);
        
        // Mostrar loading
        window.BordadosToast.clear();
        window.BordadosToast.info('Processando solicitação de edição...', 'Aguarde', { duration: 0 });
        
        $.ajax({
            url: bordados_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                window.BordadosToast.clear();
                
                if (response.success) {
                    window.mostrarMensagem('success', 'Edição Solicitada!', response.data.message);
                    fecharModalEdicao();
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    window.mostrarMensagem('error', 'Erro!', response.data);
                }
            },
            error: function() {
                window.BordadosToast.clear();
                window.mostrarMensagem('error', 'Erro!', 'Erro de comunicação com o servidor');
            }
        });
    });
});

// ===============================
// INICIALIZAÇÃO
// ===============================

// Inicializar Toast
window.BordadosToast.init();

// Configurar eventos
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', configurarEventosFormulario);
} else {
    configurarEventosFormulario();
}

// Backup para garantir
setTimeout(configurarEventosFormulario, 1000);

console.log('🎉 bordados-revisor.js carregado com sucesso');

// TESTE AUTOMÁTICO (remova depois de confirmar que funciona)
setTimeout(function() {
    if (document.getElementById('form-novo-pedido')) {
        console.log('🧪 TESTE: Formulário encontrado, sistema pronto');
    }
}, 2000);
