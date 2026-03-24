/**
 * 1. CONFIGURAÇÃO DE PREÇOS E SONS
 */
const cardapioPrecos = {
    "Pizza Marguerita": 45, "Pizza de Calabresa": 42, "Pizza de Pepperoni": 40, "Pizza vegana Especial": 60,
    "Pizza de Frango com Catupiry": 55, "Pizza de 4 queijos": 42, "Pesto e tomate seco": 58, "Portuguesa Prime": 47,
    "Hamburguer clássico": 25, "Frango Chick": 35, "Smash Onion": 22, "Bacon Jam": 34, "Double Cheddar": 38,
    "Vegetariano de Grão-de-Bico": 28, "X-Tudo": 30, "Saladas": 18, "Queijo quente": 12, "Batata Palito Média": 15,
    "Batata Especial da Casa": 28, "Nuggets de Frango": 20, "Onion Rings": 22, "Coca Cola Zero Lata": 4.5,
    "Coca cola lata": 5, "Suco natural de laranja jarra": 10, "Suco natural de maracujá jarra": 15,
    "Cerveja Long Neck": 11, "Água Mineral 500ml": 5
};

const somCaixa = new Audio('sons/caixa.mp3'); 
somCaixa.volume = 0.5;

document.addEventListener('click', () => {
    somCaixa.play().then(() => {
        somCaixa.pause();
        somCaixa.currentTime = 0;
    }).catch(() => {});
}, { once: true });

/**
 * 2. SELEÇÃO DE ELEMENTOS
 */
const elementos = {
    fundo: document.getElementById('fundoEscuro'),
    carrinho: {
        painel: document.getElementById('painelCarrinho'),
        corpo: document.getElementById('corpoCarrinho'),
        total: document.getElementById('totalCarrinho'),
        contador: document.getElementById('contadorCarrinho'),
        cupom: document.getElementById('cupomInput'),
        btnFinalizar: document.getElementById('finalizarPedido'),
        btnCupom: document.getElementById('aplicarCupom'),
        cupomAplicadoTxt: document.getElementById('cupomAplicado'),
        detalhamento: document.getElementById('detalhamentoValores') 
    },
    chat: {
        painel: document.getElementById('painelChat'),
        corpo: document.getElementById('corpoChat'),
        entrada: document.getElementById('entradaChat'),
        form: document.getElementById('formularioChat')
    },
    ia: {
        painel: document.getElementById('painelIAPedido'),
        corpo: document.getElementById('corpoIAPedido'),
        entrada: document.getElementById('entradaIAPedido'),
        form: document.getElementById('formularioIAPedido')
    },
    ajuda: {
        painel: document.getElementById('painelAjuda')
    }
};

let carrinho = [];
let descontoCupom = 0;
let modoConversaAtivo = false;
let iaEstaFalando = false; // TRAVA ANTI-LOOP

/**
 * 3. GERENCIAMENTO DE INTERFACE (UI)
 */
function togglePainel(tipo, abrir = true) {
    const p = elementos[tipo]?.painel;
    if (!p) return;

    if (abrir) {
        Object.keys(elementos).forEach(k => {
            if (elementos[k].painel && k !== tipo) {
                elementos[k].painel.classList.remove('aberto');
                elementos[k].painel.style.display = 'none';
                elementos[k].painel.inert = true;
            }
        });
        
        p.style.display = 'flex';
        p.inert = false;
        p.removeAttribute('aria-hidden');
        void p.offsetWidth; 

        setTimeout(() => {
            p.classList.add('aberto');
            elementos.fundo.classList.add('visivel');
            if (elementos[tipo]?.entrada) elementos[tipo].entrada.focus();
        }, 50);
    } else {
        p.classList.remove('aberto');
        p.inert = true;
        setTimeout(() => {
            const algumPainelAberto = document.querySelector('.painel-lateral.aberto'); 
            if (!algumPainelAberto) {
                p.style.display = 'none';
                elementos.fundo.classList.remove('visivel');
            } else {
                p.style.display = 'none';
            }
        }, 300);
    }
}

function fecharTodosOsPaineis() {
    Object.keys(elementos).forEach(tipo => {
        if (elementos[tipo].painel) togglePainel(tipo, false);
    });
}

/**
 * 4. LÓGICA DO CARRINHO
 */
function atualizarCarrinhoUI() {
    const totalItens = carrinho.reduce((sum, item) => sum + item.quantidade, 0);
    const subtotal = carrinho.reduce((total, item) => total + (item.preco * item.quantidade), 0);
    const valorDesconto = subtotal * descontoCupom;
    const totalFinal = subtotal - valorDesconto;
    
    if(elementos.carrinho.contador) elementos.carrinho.contador.textContent = totalItens;

    if (carrinho.length === 0) {
        elementos.carrinho.corpo.innerHTML = '<div class="mensagem-chat robo">Seu carrinho está vazio.</div>';
        if(elementos.carrinho.detalhamento) elementos.carrinho.detalhamento.innerHTML = '';
        elementos.carrinho.total.textContent = "R$ 0,00";
        return;
    }

    elementos.carrinho.corpo.innerHTML = carrinho.map((item, index) => `
        <div style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:0.6rem; border-radius:12px; border:1px solid rgba(0,0,0,0.08); margin-bottom:0.4rem;">
            <span style="font-size:.9rem;">${item.nome} x${item.quantidade}</span>
            <div style="display:flex; align-items:center; gap:12px;">
                <strong style="font-size:.9rem;">R$ ${(item.preco * item.quantidade).toFixed(2)}</strong>
                <button onclick="removerDoCarrinho(${index})" 
                    style="background:#fdeaea; color:#d72f2f; border:none; border-radius:8px; padding:5px 10px; cursor:pointer; font-size:0.7rem; font-weight:bold;">
                    Remover
                </button>
            </div>
        </div>
    `).join('');

    if (elementos.carrinho.detalhamento) {
        elementos.carrinho.detalhamento.innerHTML = `
            <div style="display:flex; justify-content:space-between; font-size: 0.85rem; color: #666; margin-top: 10px;">
                <span>Subtotal:</span>
                <span>R$ ${subtotal.toFixed(2)}</span>
            </div>
            ${descontoCupom > 0 ? `
            <div style="display:flex; justify-content:space-between; font-size: 0.85rem; color: #d72f2f; font-weight: bold;">
                <span>Desconto (${(descontoCupom * 100).toFixed(0)}%):</span>
                <span>- R$ ${valorDesconto.toFixed(2)}</span>
            </div>` : ''}
        `;
    }
    elementos.carrinho.total.textContent = `R$ ${totalFinal.toFixed(2)}`;
}

function adicionarAoCarrinho(nome, preco) {
    const itemExistente = carrinho.find(i => i.nome === nome);
    if (itemExistente) {
        itemExistente.quantidade += 1;
    } else {
        carrinho.push({ nome, preco: parseFloat(preco), quantidade: 1 });
    }
    somCaixa.currentTime = 0;
    somCaixa.play().catch(() => {});
    atualizarCarrinhoUI();
}

function removerDoCarrinho(index) {
    if (carrinho[index].quantidade > 1) {
        carrinho[index].quantidade -= 1;
    } else {
        carrinho.splice(index, 1);
    }
    if (carrinho.length === 0) {
        descontoCupom = 0;
        if(elementos.carrinho.cupomAplicadoTxt) elementos.carrinho.cupomAplicadoTxt.textContent = 'nenhum';
    }
    atualizarCarrinhoUI();
}

/**
 * 5. SISTEMA DE VOZ COM TRAVA ANTI-LOOP
 */
function falar(texto) {
    window.speechSynthesis.cancel();
    iaEstaFalando = true; // Ativa a trava antes de começar a falar
    
    let textoLimpo = texto.replace(/\{[\s\S]*\}/g, '');
    textoLimpo = textoLimpo.replace(/([\u2700-\u27BF]|[\uE000-\uF8FF]|\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDFFF]|[\u2011-\u26FF]|\uD83E[\uDD00-\uDDFF])/g, '');

    const mensagem = new SpeechSynthesisUtterance(textoLimpo);
    const vozes = window.speechSynthesis.getVoices();
    mensagem.voice = vozes.find(v => (v.name.includes('Google') || v.name.includes('Maria')) && v.lang.includes('pt-BR')) || vozes.find(v => v.lang.includes('pt-BR'));
    mensagem.lang = 'pt-BR';
    mensagem.rate = 1.0;

    // Quando terminar de falar, libera o microfone
    mensagem.onend = () => {
        iaEstaFalando = false; 
    };

    window.speechSynthesis.speak(mensagem);
}

const recognition = new (window.webkitSpeechRecognition || window.SpeechRecognition)();
recognition.lang = 'pt-BR';
recognition.continuous = false;
recognition.interimResults = false;

function iniciarIAVoz() {
    if (modoConversaAtivo) return;
    modoConversaAtivo = true;
    try { recognition.start(); } catch(e) {}
    
    const btn = document.getElementById('btnVoz');
    const box = document.getElementById('transcricao-voz');
    if(btn) btn.style.background = '#2ed573';
    if(box) {
        box.style.display = 'block';
        box.innerText = "Estou te ouvindo...";
    }
}

recognition.onresult = async (event) => {
    // SE A IA ESTIVER FALANDO, IGNORA O QUE O MICROFONE CAPTOU
    if (iaEstaFalando) return;

    const textoCapturado = event.results[0][0].transcript;
    const box = document.getElementById('transcricao-voz');
    if(box) box.innerText = `Você: "${textoCapturado}"`;

    try {
        const res = await fetch('voice_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt: textoCapturado })
        });

        const data = await res.json();
        let respostaIA = data.resposta || "Não consegui te ouvir bem.";

        const jsonMatch = respostaIA.match(/\{[\s\S]*\}/);
        if (jsonMatch) {
            const comando = JSON.parse(jsonMatch[0]);
            if (comando.acao === "adicionar_carrinho") {
                comando.itens.forEach(item => {
                    adicionarAoCarrinho(item.nome, cardapioPrecos[item.nome] || 0);
                });
            }
            if (comando.acao === "finalizar_conversa") {
                modoConversaAtivo = false;
                let msgFinal = respostaIA.replace(jsonMatch[0], "").trim();
                falar(msgFinal);
                if(box) box.innerText = msgFinal;
                setTimeout(() => togglePainel('carrinho', true), 1500);
                return;
            }
            respostaIA = respostaIA.replace(jsonMatch[0], "").trim();
        }

        if(box) box.innerText = respostaIA;
        falar(respostaIA);

    } catch (e) {
        console.error("Erro na IA:", e);
    }
};

recognition.onend = () => {
    // Só reinicia o reconhecimento se o modo conversa estiver ON e a IA NÃO estiver falando
    if (modoConversaAtivo) {
        const checkCycle = setInterval(() => {
            if (!iaEstaFalando && !window.speechSynthesis.speaking) {
                try { recognition.start(); } catch(e) {}
                clearInterval(checkCycle);
            }
        }, 400);
    }
};

/**
 * 6. LÓGICA DE CONVERSA E IA (TEXTO)
 */
function adicionarMensagem(texto, tipo, destino) {
    const corpo = elementos[destino]?.corpo;
    if (!corpo) return;
    const msg = document.createElement('div');
    msg.className = `mensagem-chat ${tipo}`;
    msg.textContent = texto;
    corpo.appendChild(msg);
    corpo.scrollTop = corpo.scrollHeight;
    return msg;
}

async function enviarParaIA(texto, destino) {
    if (!texto.trim()) return;

    adicionarMensagem(texto, 'usuario', destino);
    const loading = adicionarMensagem('Digitando...', 'robo', destino);

    try {
        const response = await fetch('gemini_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt: texto })
        });

        const data = await response.json();
        let respostaIA = data.resposta || "Tive um problema técnico.";
        const jsonMatch = respostaIA.match(/\{[\s\S]*\}/);

        if (jsonMatch) {
            const dadosPedido = JSON.parse(jsonMatch[0]);
            respostaIA = respostaIA.replace(jsonMatch[0], "").trim();

            if (dadosPedido.acao === "adicionar_carrinho") {
                dadosPedido.itens.forEach(item => {
                    adicionarAoCarrinho(item.nome, cardapioPrecos[item.nome] || 0);
                });
            }

            if (dadosPedido.acao === "finalizar_conversa") {
                loading.textContent = respostaIA;
                setTimeout(() => {
                    togglePainel(destino, false);
                    setTimeout(() => togglePainel('carrinho', true), 500);
                }, 2000);
                return;
            }
        }
        loading.textContent = respostaIA;

    } catch (err) {
        loading.textContent = "Erro de conexão.";
    }
}

/**
 * 7. EVENT LISTENERS
 */
document.addEventListener('click', (e) => {
    const el = e.target;
    const id = el.id;

    if (id === 'abrirChat') togglePainel('ajuda', true);
    if (id === 'fecharAjuda') togglePainel('ajuda', false);
    if (id === 'abrirChatLateral') togglePainel('chat', true);
    if (id === 'fecharChat') togglePainel('chat', false);
    if (id === 'abrirIaPedidoTopo') togglePainel('ia', true);
    if (id === 'fecharIAPedido') togglePainel('ia', false);
    if (id === 'abrirCarrinho') togglePainel('carrinho', true);
    if (id === 'fecharCarrinho') togglePainel('carrinho', false);
    if (id === 'fundoEscuro') fecharTodosOsPaineis();

    if (el.classList.contains('botao-adicionar')) {
        const card = el.closest('.cartao');
        const nome = card?.querySelector('h3')?.innerText || 'Item';
        const precoTxt = card?.querySelector('.preco')?.innerText || "0";
        const preco = parseFloat(precoTxt.replace('R$', '').replace('.', '').replace(',', '.')) || 0;
        adicionarAoCarrinho(nome, preco);
    }
});

elementos.chat.form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const val = elementos.chat.entrada.value;
    elementos.chat.entrada.value = '';
    enviarParaIA(val, 'chat');
});

elementos.ia.form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const val = elementos.ia.entrada.value;
    elementos.ia.entrada.value = '';
    enviarParaIA(val, 'ia');
});

elementos.carrinho.btnCupom?.addEventListener('click', () => {
    const cupom = elementos.carrinho.cupom.value.trim().toUpperCase();
    const cuponsValidos = { 'RS10': 0.1, 'RS25': 0.25, 'RS50': 0.5 };

    if (cuponsValidos[cupom]) { 
        descontoCupom = cuponsValidos[cupom];
        elementos.carrinho.cupomAplicadoTxt.textContent = `${cupom} (${(descontoCupom * 100).toFixed(0)}%)`;
        elementos.carrinho.cupomAplicadoTxt.style.color = "green";
    } else { 
        alert('Cupom inválido'); 
        descontoCupom = 0;
        elementos.carrinho.cupomAplicadoTxt.textContent = 'nenhum';
    }
    atualizarCarrinhoUI();
});

elementos.carrinho.btnFinalizar?.addEventListener('click', () => {
    if (!carrinho.length) return alert('Seu carrinho está vazio!');
    alert('Pedido finalizado com sucesso!');
    carrinho = [];
    descontoCupom = 0;
    atualizarCarrinhoUI();
    fecharTodosOsPaineis();
});

window.speechSynthesis.onvoiceschanged = () => { window.speechSynthesis.getVoices(); };

atualizarCarrinhoUI();  