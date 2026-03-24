<?php
header('Content-Type: application/json');
session_start(); // 🔥 Mantém a memória da IA entre as perguntas

// ============================================================
// 1. BUSCA EXAUSTIVA DE CHAVES (ROTAÇÃO GEMINI_API_KEY 1, 2 e 3)
// ============================================================
function getApiKey($suffix = "") {
    $name = "GEMINI_API_KEY" . $suffix;
    // Tenta buscar de todas as formas para garantir que o Render entregue a chave
    return getenv($name) ?: ($_ENV[$name] ?? ($_SERVER[$name] ?? null));
}

// Criamos a lista de chaves para o Chat (Principal, Reserva 1 e Reserva 2)
$apiKeys = array_filter([
    getApiKey(""),   // GEMINI_API_KEY
    getApiKey("_2"), // GEMINI_API_KEY_2
    getApiKey("_3")  // GEMINI_API_KEY_3
]);

if (empty($apiKeys)) {
    echo json_encode(['error' => 'Nenhuma variável GEMINI_API_KEY detectada no ambiente do Render.']);
    exit;
}

// ============================================================
// 2. RECEBER INPUT
// ============================================================
$jsonInput = file_get_contents('php://input');
$input = json_decode($jsonInput, true);
$userPrompt = $input['prompt'] ?? $_POST['prompt'] ?? '';

if (empty($userPrompt)) {
    echo json_encode(['resposta' => 'Opa, não entendi. Pode repetir?']);
    exit;
}

// ============================================================
// 3. MEMÓRIA DA IA
// ============================================================
if (!isset($_SESSION['historico'])) {
    $_SESSION['historico'] = [];
}

// Limita histórico (evita que o prompt fique gigante e caro)
if (count($_SESSION['historico']) > 10) {
    $_SESSION['historico'] = array_slice($_SESSION['historico'], -10);
}

// ============================================================
// 4. SEU PROMPT ORIGINAL (INTACTO)
// ============================================================
$instrucoes = 'Você é um atendente virtual do restaurante RS Burger dentro de um sistema de cardápio digital.

REGRAS IMPORTANTES (OBRIGATÓRIO):
- Você NUNCA pode sair do seu papel de atendente de restaurante.
- Você NÃO pode responder perguntas fora do contexto de comida, pedidos ou cardápio.
- Se o usuário tentar mudar de assunto, responda educadamente e volte para o atendimento.
- Seja simpático, direto e objetivo.

CARRINHO E COMANDOS:
- Regra de Ouro: NUNCA explique o JSON. Coloque-o sempre na ÚLTIMA LINHA da sua resposta.
- Para adicionar itens: {"acao": "adicionar_carrinho", "itens": [{"nome": "NOME_EXATO", "quantidade": 1}]}
- Para finalizar: {"acao": "finalizar_conversa"}

CARDÁPIO OFICIAL (USE EXATAMENTE ESSES NOMES):
🍕 PIZZAS: Pizza Marguerita (R$45), Pizza de Calabresa (R$42), Pizza de Pepperoni (R$40), Pizza vegana Especial (R$60), Pizza de Frango com Catupiry (R$55), Pizza de 4 queijos (R$42), Pesto e tomate seco (R$58), Portuguesa Prime (R$47).
🍔 HAMBURGUERS: Hamburguer clássico (R$25), Frango Chick (R$35), Smash Onion (R$22), Bacon Jam (R$34), Double Cheddar (R$38), Vegetariano de Grão-de-Bico (R$28), X-Tudo (R$30).
🍟 ACOMPANHAMENTOS: Saladas (R$18), Queijo quente (R$12), Batata Palito Média (R$15), Batata Especial da Casa (R$28), Nuggets de Frango (R$20), Onion Rings (R$22).
🥤 BEBIDAS: Coca Cola Zero Lata (R$4,50), Coca cola lata (R$5), Suco natural de laranja jarra (R$10), Suco natural de maracujá jarra (R$15), Cerveja Long Neck (R$11), Água Mineral 500ml (R$5).

CARRINHO:
- Quando o cliente disser que não quer mais nada ou finalizar use:
{"acao": "finalizar_conversa", "mensagem": "Perfeito! Abrindo seu carrinho para finalização..."}

Pergunta do cliente: ';

// ============================================================
// 5. MONTA CONTEXTO COM MEMÓRIA
// ============================================================
$mensagemCompleta = $instrucoes . "\n\n";

foreach ($_SESSION['historico'] as $msg) {
    $mensagemCompleta .= $msg . "\n";
}

$mensagemCompleta .= "Cliente: " . $userPrompt;

// ============================================================
// 6. LÓGICA DE RODÍZIO (RETRY LOGIC)
// ============================================================
$respostaFinal = null;
$erroUltimaTentativa = "";



foreach ($apiKeys as $key) {
    // Usamos o modelo 1.5-flash para maior estabilidade e quota em apresentações
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $key;

    $payload = [
        "contents" => [[
            "parts" => [["text" => $mensagemCompleta]]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 12 // Tempo de espera para não travar a apresentação
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dados = json_decode($result, true);

    // Se o código for 200, a chave funcionou!
    if ($httpCode === 200 && isset($dados['candidates'][0]['content']['parts'][0]['text'])) {
        $respostaFinal = $dados['candidates'][0]['content']['parts'][0]['text'];
        break; // Sai do loop (sucesso)
    } else {
        $erroUltimaTentativa = $dados['error']['message'] ?? "Erro HTTP $httpCode";
        // Continua o loop para a próxima chave...
    }
}

// ============================================================
// 7. RESPOSTA FINAL
// ============================================================
if ($respostaFinal) {
    // Salva no histórico da sessão
    $_SESSION['historico'][] = "Cliente: " . $userPrompt;
    $_SESSION['historico'][] = "IA: " . $respostaFinal;

    echo json_encode(['resposta' => $respostaFinal]);
} else {
    // Se todas as chaves falharem
    echo json_encode([
        'resposta' => "Desculpe, tive um probleminha técnico devido à alta demanda. Pode repetir?",
        'debug' => $erroUltimaTentativa
    ]);
}
?>