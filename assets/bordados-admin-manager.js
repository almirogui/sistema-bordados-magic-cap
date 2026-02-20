/**
 * Sistema de Bordados - Gerenciador Admin
 * Extraído de bordados-admin-manager.php
 * 
 * Dependências:
 * - jQuery
 * - bordados_manager_ajax (wp_localize_script)
 */

console.log('🚀 JavaScript carregado - Gerenciador Admin Bordados v2.0');

// ===================================
// VARIÁVEIS GLOBAIS
// ===================================
var pedidoParaDeletar = null;
var pedidosParaDeletar = [];

// ===================================
// PROTEÇÃO CSS PARA BOTÕES
// ===================================
function aplicarProtecaoBotoes() {
    var style = document.createElement('style');
    style.textContent = '\
        .action-btn {\
            pointer-events: auto !important;\
            cursor: pointer !important;\
            z-index: 9999 !important;\
            position: relative !important;\
            display: inline-block !important;\
            min-width: 36px !important;\
            min-height: 36px !important;\
            touch-action: manipulation !important;\
        }\
        .action-btn:hover {\
            transform: scale(1.1) !important;\
            transition: transform 0.2s !important;\
        }\
        .col-acoes {\
            pointer-events: auto !important;\
            z-index: 1000 !important;\
        }\
        .acoes-grupo {\
            pointer-events: auto !important;\
            display: flex !important;\
            gap: 5px !important;\
            justify-content: center !important;\
        }\
    ';
    document.head.appendChild(style);
    console.log('✅ Proteção CSS aplicada aos botões');
}

// ===================================
// FUNÇÃO: CONFIRMAR EXCLUSÃO INDIVIDUAL
// ===================================
function confirmarDelete(pedidoId, nomeBordado) {
    console.log('🗑️ Confirmar exclusão individual:', pedidoId, nomeBordado);
    
    pedidoParaDeletar = pedidoId;
    
    document.getElementById('delete-pedido-nome').textContent = nomeBordado;
    document.getElementById('delete-pedido-id').textContent = '#' + pedidoId;
    document.getElementById('modal-confirmar-delete').style.display = 'flex';
    
    // Focar no botão cancelar
    setTimeout(function() {
        var btnCancelar = document.querySelector('#modal-confirmar-delete .btn-outline');
        if (btnCancelar) btnCancelar.focus();
    }, 100);
}

// ===================================
// FUNÇÃO: FECHAR MODAL INDIVIDUAL
// ===================================
function fecharModalDelete() {
    console.log('❌ Fechando modal individual');
    document.getElementById('modal-confirmar-delete').style.display = 'none';
    pedidoParaDeletar = null;
}

// ===================================
// FUNÇÃO: EXECUTAR EXCLUSÃO INDIVIDUAL
// ===================================
function executarDelete() {
    if (!pedidoParaDeletar) {
        alert('Erro: Nenhum pedido selecionado');
        return;
    }
    
    console.log('🗑️ Executando exclusão individual:', pedidoParaDeletar);
    
    var btn = document.getElementById('btn-confirmar-delete');
    btn.disabled = true;
    btn.innerHTML = '⏳ Deletando...';
    
    realizarAjaxDelete('bordados_deletar_pedido', {
        pedido_id: pedidoParaDeletar
    }, function(response) {
        if (response.success) {
            var row = document.getElementById('pedido-row-' + pedidoParaDeletar);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(function() {
                    row.remove();
                    atualizarContadores();
                    
                    // Recarregar se não há mais pedidos
                    if (document.querySelectorAll('.pedido-row').length === 0) {
                        setTimeout(function() { location.reload(); }, 1000);
                    }
                }, 300);
            }
            
            mostrarMensagemManager('sucesso', '✅ ' + response.data.message);
            fecharModalDelete();
        } else {
            mostrarMensagemManager('erro', '❌ ' + (response.data || 'Erro desconhecido'));
            restaurarBotaoIndividual();
        }
    }, function(error) {
        console.error('❌ Erro na exclusão individual:', error);
        mostrarMensagemManager('erro', '❌ Erro de comunicação com o servidor');
        restaurarBotaoIndividual();
    });
}

function restaurarBotaoIndividual() {
    var btn = document.getElementById('btn-confirmar-delete');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '🗑️ Sim, Deletar';
    }
}

// ===================================
// FUNÇÃO: SELEÇÃO MÚLTIPLA
// ===================================
function toggleSelectAll(checkbox) {
    console.log('🔄 Toggle select all:', checkbox.checked);
    var checkboxes = document.querySelectorAll('.pedido-checkbox');
    
    checkboxes.forEach(function(cb) {
        cb.checked = checkbox.checked;
    });
    
    updateDeleteButton();
}

function selecionarTodos() {
    var selectAll = document.getElementById('select-all');
    var checkboxes = document.querySelectorAll('.pedido-checkbox');
    
    // Verificar se todos estão marcados
    var todosMarcados = Array.from(checkboxes).every(function(cb) { return cb.checked; });
    
    // Inverter seleção
    checkboxes.forEach(function(cb) {
        cb.checked = !todosMarcados;
    });
    
    if (selectAll) {
        selectAll.checked = !todosMarcados;
    }
    
    updateDeleteButton();
    console.log('☑️ Seleção alternada. Todos marcados agora:', !todosMarcados);
}

function updateDeleteButton() {
    var checkboxes = document.querySelectorAll('.pedido-checkbox:checked');
    var btn = document.getElementById('btn-deletar-multiplos');
    var contador = document.getElementById('contador-selecionados');
    
    if (checkboxes.length > 0) {
        btn.disabled = false;
        btn.classList.remove('btn-disabled');
        contador.textContent = checkboxes.length;
    } else {
        btn.disabled = true;
        btn.classList.add('btn-disabled');
        contador.textContent = '0';
    }
    
    console.log('📊 Botão deletar múltiplos atualizado:', checkboxes.length, 'selecionados');
}

// ===================================
// FUNÇÃO: DELETAR SELECIONADOS
// ===================================
function deletarSelecionados() {
    var checkboxes = document.querySelectorAll('.pedido-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('❌ Nenhum pedido selecionado!');
        return;
    }
    
    console.log('🗑️ Deletar múltiplos iniciado:', checkboxes.length, 'pedidos');
    
    // Coletar dados dos pedidos selecionados
    pedidosParaDeletar = [];
    var lista = document.getElementById('lista-pedidos-selecionados');
    lista.innerHTML = '';
    
    checkboxes.forEach(function(cb) {
        var id = cb.value;
        var nome = cb.getAttribute('data-nome');
        pedidosParaDeletar.push({id: id, nome: nome});
        
        var li = document.createElement('li');
        li.innerHTML = '<strong>#' + id + '</strong> - ' + nome;
        lista.appendChild(li);
    });
    
    // Mostrar modal
    document.getElementById('modal-confirmar-delete-multiplo').style.display = 'flex';
}

function fecharModalDeleteMultiplo() {
    document.getElementById('modal-confirmar-delete-multiplo').style.display = 'none';
    pedidosParaDeletar = [];
}

function executarDeleteMultiplo() {
    if (pedidosParaDeletar.length === 0) {
        alert('Erro: Nenhum pedido para deletar');
        return;
    }
    
    console.log('🗑️ Executando exclusão múltipla:', pedidosParaDeletar.length, 'pedidos');
    
    var btn = document.getElementById('btn-confirmar-delete-multiplo');
    btn.disabled = true;
    btn.innerHTML = '⏳ Deletando...';
    
    var ids = pedidosParaDeletar.map(function(p) { return p.id; });
    
    realizarAjaxDelete('bordados_deletar_multiplos', {
        pedidos_ids: ids
    }, function(response) {
        if (response.success) {
            // Remover linhas deletadas
            ids.forEach(function(id) {
                var row = document.getElementById('pedido-row-' + id);
                if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(function() { row.remove(); }, 300);
                }
            });
            
            // Atualizar interface
            setTimeout(function() {
                atualizarContadores();
                updateDeleteButton();
                
                // Desmarcar select all
                var selectAll = document.getElementById('select-all');
                if (selectAll) selectAll.checked = false;
                
                // Recarregar se não há mais pedidos
                if (document.querySelectorAll('.pedido-row').length === 0) {
                    setTimeout(function() { location.reload(); }, 1000);
                }
            }, 400);
            
            mostrarMensagemManager('sucesso', '✅ ' + ids.length + ' pedido(s) deletado(s) com sucesso!');
            fecharModalDeleteMultiplo();
        } else {
            mostrarMensagemManager('erro', '❌ ' + (response.data || 'Erro na exclusão múltipla'));
            restaurarBotaoMultiplo();
        }
    }, function(error) {
        console.error('❌ Erro na exclusão múltipla:', error);
        mostrarMensagemManager('erro', '❌ Erro de comunicação com o servidor');
        restaurarBotaoMultiplo();
    });
}

function restaurarBotaoMultiplo() {
    var btn = document.getElementById('btn-confirmar-delete-multiplo');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '🗑️ Sim, Deletar Todos';
    }
}

// ===================================
// FUNÇÃO: VISUALIZAR PEDIDO
// ===================================
function visualizarPedido(pedidoId, nomeBordado) {
    console.log('👁️ Visualizando pedido:', pedidoId, nomeBordado);
    
    document.getElementById('visual-pedido-id').textContent = '#' + pedidoId;
    
    // Buscar dados completos do pedido
    var conteudo = document.getElementById('conteudo-visualizacao');
    conteudo.innerHTML = '<div style="text-align: center; padding: 40px;"><div style="font-size: 20px;">⏳</div><p>Carregando detalhes...</p></div>';
    
    document.getElementById('modal-visualizar').style.display = 'flex';
    
    // Buscar dados reais via AJAX
    jQuery.ajax({
        url: bordados_manager_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'bordados_buscar_detalhes_pedido',
            nonce: bordados_manager_ajax.nonce,
            pedido_id: pedidoId
        },
        success: function(response) {
            console.log('✅ Dados recebidos:', response);
            
            if (response.success) {
                var pedido = response.data;
                
                // Montar HTML com dados reais
                var html = '\
                    <div class="pedido-detalhes-completo">\
                        <h4>📋 Informações Básicas</h4>\
                        <p><strong>Nome do Bordado:</strong> ' + pedido.nome_bordado + '</p>\
                        <p><strong>ID do Pedido:</strong> #' + pedido.id + '</p>\
                        <p><strong>Status:</strong> <span class="status-badge">' + pedido.status + '</span></p>\
                        <p><strong>Tamanho:</strong> ' + (pedido.tamanho || 'Não informado') + '</p>\
                        <p><strong>Cores:</strong> ' + (pedido.cores || 'Não informado') + '</p>\
                        \
                        <h4>👤 Cliente</h4>\
                        <p><strong>Nome:</strong> ' + pedido.cliente.nome + '</p>\
                        <p><strong>Email:</strong> ' + pedido.cliente.email + '</p>';
                
                if (pedido.programador.nome) {
                    html += '\
                        <h4>👨‍💻 Programador</h4>\
                        <p><strong>Nome:</strong> ' + pedido.programador.nome + '</p>\
                        <p><strong>Email:</strong> ' + pedido.programador.email + '</p>';
                }
                
                if (pedido.observacoes) {
                    html += '\
                        <h4>📝 Observações do Cliente</h4>\
                        <p>' + pedido.observacoes + '</p>';
                }
                
                if (pedido.observacoes_programador) {
                    html += '\
                        <h4>💬 Observações do Programador</h4>\
                        <p>' + pedido.observacoes_programador + '</p>';
                }
                
                if (pedido.preco_programador) {
                    html += '\
                        <h4>💰 Preço</h4>\
                        <p>R$ ' + pedido.preco_programador + '</p>';
                html += '<h4>📅 Datas (horário local)</h4>';
                html += '<p><strong>Criação:</strong> ' + fmtDataLocal(pedido.datas.criacao) + '</p>';
                
                if (pedido.datas.atribuicao) {
                    html += '<p><strong>Atribuição:</strong> ' + fmtDataLocal(pedido.datas.atribuicao) + '</p>';
                }
                if (pedido.datas.conclusao) {
                    html += '<p><strong>Conclusão:</strong> ' + fmtDataLocal(pedido.datas.conclusao) + '</p>';
                }
                
                // Arquivos do cliente
                if (pedido.arquivos_cliente && pedido.arquivos_cliente.length > 0) {
                    html += '<h4>📎 Arquivos do Cliente</h4><ul>';
                    pedido.arquivos_cliente.forEach(function(arquivo) {
                        html += '<li><a href="' + arquivo + '" target="_blank">' + arquivo.split('/').pop() + '</a></li>';
                    });
                    html += '</ul>';
                }
                
                // Arquivos finais
                if (pedido.arquivos_finais && pedido.arquivos_finais.length > 0) {
                    html += '<h4>✅ Arquivos Finais</h4><ul>';
                    pedido.arquivos_finais.forEach(function(arquivo) {
                        html += '<li><a href="' + arquivo + '" target="_blank">' + arquivo.split('/').pop() + '</a></li>';
                    });
                    html += '</ul>';
                }
                
                html += '</div>';
                conteudo.innerHTML = html;
            } else {
                conteudo.innerHTML = '<div style="color: red; padding: 20px;">❌ ' + (response.data || 'Erro ao carregar detalhes') + '</div>';
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Erro AJAX:', error);
            conteudo.innerHTML = '<div style="color: red; padding: 20px;">❌ Erro ao carregar detalhes do pedido</div>';
        }
    });
}

function fecharModalVisualizar() {
    document.getElementById('modal-visualizar').style.display = 'none';
}

// ===================================
// FUNÇÃO: AJAX GENÉRICA
// ===================================
function realizarAjaxDelete(action, data, successCallback, errorCallback) {
    var ajaxData = {
        action: action,
        nonce: bordados_manager_ajax.nonce
    };
    
    // Merge data
    for (var key in data) {
        if (data.hasOwnProperty(key)) {
            ajaxData[key] = data[key];
        }
    }
    
    console.log('📡 Enviando AJAX:', action, ajaxData);
    
    if (typeof jQuery !== 'undefined' && typeof bordados_manager_ajax !== 'undefined') {
        // Usar jQuery se disponível
        jQuery.ajax({
            url: bordados_manager_ajax.ajax_url,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                console.log('✅ Resposta AJAX (jQuery):', response);
                successCallback(response);
            },
            error: function(xhr, status, error) {
                console.error('❌ Erro AJAX (jQuery):', error, xhr.responseText);
                errorCallback(error);
            }
        });
    } else {
        // Fallback com fetch
        console.log('⚠️ Usando fetch como fallback');
        
        var formData = new FormData();
        Object.keys(ajaxData).forEach(function(key) {
            if (Array.isArray(ajaxData[key])) {
                ajaxData[key].forEach(function(item) {
                    formData.append(key + '[]', item);
                });
            } else {
                formData.append(key, ajaxData[key]);
            }
        });
        
        fetch(bordados_manager_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            console.log('✅ Resposta AJAX (fetch):', data);
            successCallback(data);
        })
        .catch(function(error) {
            console.error('❌ Erro AJAX (fetch):', error);
            errorCallback(error);
        });
    }
}

// ===================================
// FUNÇÃO: MOSTRAR MENSAGENS (Admin Manager)
// ===================================
function mostrarMensagemManager(tipo, texto) {
    var div = document.getElementById('manager-mensagem');
    if (div) {
        div.className = 'manager-mensagem ' + tipo;
        div.innerHTML = texto;
        div.style.display = 'block';
        
        setTimeout(function() {
            div.style.display = 'none';
        }, 5000);
        
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
}

// ===================================
// FUNÇÃO: ATUALIZAR CONTADORES
// ===================================
function atualizarContadores() {
    var rows = document.querySelectorAll('.pedido-row');
    var novos = 0, atribuidos = 0, producao = 0;
    
    rows.forEach(function(row) {
        var badge = row.querySelector('.status-badge');
        if (badge) {
            if (badge.classList.contains('status-novo')) novos++;
            else if (badge.classList.contains('status-atribuido')) atribuidos++;
            else if (badge.classList.contains('status-producao')) producao++;
        }
    });
    
    var statNumbers = document.querySelectorAll('.stat-number');
    if (statNumbers.length >= 4) {
        statNumbers[0].textContent = novos;
        statNumbers[1].textContent = atribuidos;
        statNumbers[2].textContent = producao;
        statNumbers[3].textContent = rows.length;
    }
    
    console.log('📊 Contadores atualizados:', {novos: novos, atribuidos: atribuidos, producao: producao, total: rows.length});
}

// ===================================
// EVENT LISTENERS
// ===================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalDelete();
        fecharModalDeleteMultiplo();
        fecharModalVisualizar();
    }
});

document.addEventListener('click', function(e) {
    // Fechar modals clicando fora
    if (e.target.classList.contains('modal-overlay')) {
        if (e.target.id === 'modal-confirmar-delete') fecharModalDelete();
        if (e.target.id === 'modal-confirmar-delete-multiplo') fecharModalDeleteMultiplo();
        if (e.target.id === 'modal-visualizar') fecharModalVisualizar();
    }
});

// ===================================
// INICIALIZAÇÃO
// ===================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 DOM carregado - inicializando Admin Manager...');
    aplicarProtecaoBotoes();
    updateDeleteButton();
    console.log('✅ Admin Manager completamente inicializado');
});

// Inicialização alternativa
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', aplicarProtecaoBotoes);
} else {
    aplicarProtecaoBotoes();
}

console.log('✅ bordados-admin-manager.js carregado - Todas as funções prontas');

// Converte data do servidor (New York) para horário local do cliente
function fmtDataLocal(dataStr) {
    if (!dataStr) return '';
    var isoNY = dataStr.replace(' ', 'T');
    var nyDate = new Date(new Date(isoNY).toLocaleString('en-US', {timeZone: 'America/New_York'}));
    var utcDate = new Date(new Date(isoNY).getTime() + (new Date(isoNY) - nyDate));
    var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    var formatado = utcDate.toLocaleString('en-GB', {
        timeZone: tz, day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false
    });
    var off = -new Date().getTimezoneOffset(); var tzLabel = 'UTC' + (off >= 0 ? '+' : '') + Math.floor(off/60) + (off%60 ? ':' + String(Math.abs(off%60)).padStart(2,'0') : '');
    return formatado + ' <span style="background:#e8f5e9;color:#2e7d32;padding:1px 6px;border-radius:8px;font-size:11px;font-weight:bold;margin-left:4px;" title="' + tz + '">' + tzLabel + '</span>';
}
