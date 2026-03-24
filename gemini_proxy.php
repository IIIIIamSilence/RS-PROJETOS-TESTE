<?php
// Desabilita exibição de erros brutos para não quebrar o JSON de saída
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
session_start(); // 🔥 Mantém a memória da IA entre as perguntas

// ============================================================
// 1. BUSCA EXAUSTIVA DE CHAVES (ROTAÇÃO 1, 2 e 3)
// ============================================================
function getApiKey($suffix = "") {
    $name = "GEMINI_API_KEY" . $suffix;
    // Tenta buscar em todas as camadas de ambiente possíveis do Render
    return getenv($name) ?: ($_ENV[$name] ?? ($_SERVER[$name] ?? null));
}

// Lista de chaves para o Chat de Texto (Rodízio)
$apiKeys = array_filter([
    getApiKey(""),   // GEMINI_API_KEY
    getApiKey("_2"), // GEMINI_API_KEY_2
    getApiKey("_3")  // GEMINI_API_KEY_3
]);

if (empty($apiKeys)) {
    echo json_encode(['resposta' => 'Erro Técnico: Chaves de API não detectadas no Render.']);
    exit;
}

// ============================================================
// 2. RECEBER INPUT (CORRIGIDO)
// ============================================================
$jsonInput = file_get_contents('php://input');
$input = json_decode($jsonInput, true);
$userPrompt = $input['prompt'] ?? $_POST['prompt'] ?? '';

if (empty($userPrompt)) {
    echo json_encode(['resposta' => 'Opa, não entendi. Pode repetir?']);
    exit;
}

// ============================================================
// 3. MEMÓRIA DA IA (SESSÃO)
// ============================================================
if (!isset($_SESSION['historico'])) {
    $_SESSION['historico'] = [];
}

// Limita histórico para não sobrecarregar o prompt (últimas 10 interações)
if (count($_SESSION['historico']) > 10) {
    $_SESSION['historico'] = array_slice($_SESSION['historico'], -10);
}

// ============================================================
// 4. SEU PROMPT ORIGINAL E CARDÁPIO (INTACTO)
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

CARRINHO FINALIZAÇÃO:
Quando o cliente disser que não quer mais nada ou finalizar:
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
// 6. CHAMADA GEMINI COM RODÍZIO (LÓGICA DE TENTATIVAS)
// ============================================================
$respostaFinal = null;

foreach ($apiKeys as $currentKey) {
    // Usamos gemini-2.5-flash para maior estabilidade em apresentações
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $currentKey;

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
        CURLOPT_TIMEOUT => 10 // Pula para a próxima chave se demorar mais de 10s
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dados = json_decode($response, true);

    // Se a chave atual funcionou (HTTP 200), pegamos a resposta e saímos do loop
    if ($httpCode === 200 && isset($dados['candidates'][0]['content']['parts'][0]['text'])) {
        $respostaFinal = $dados['candidates'][0]['content']['parts'][0]['text'];
        break;
    }
    // Se deu erro (como 429 de limite), o loop continua para a próxima chave
}

// ============================================================
// 7. RESPOSTA FINAL E ATUALIZAÇÃO DA MEMÓRIA
// ============================================================
if ($respostaFinal) {
    // Salva no histórico da sessão
    $_SESSION['historico'][] = "Cliente: " . $userPrompt;
    $_SESSION['historico'][] = "IA: " . $respostaFinal;

    echo json_encode(['resposta' => $respostaFinal]);
} else {
    // Se TODAS as chaves falharem
    echo json_encode(['resposta' => "Desculpe, tive um probleminha técnico devido à alta demanda. Pode repetir?"]);
}