<?php
// Desabilita exibição de erros brutos para manter o JSON limpo
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
session_start(); // 🔥 Mantém a memória da IA entre as perguntas

// ============================================================
// 1. BUSCA DE CHAVES (GEMINI_API_KEY 1, 2 e 3)
// ============================================================
function getApiKey($suffix = "") {
    $name = "GEMINI_API_KEY" . $suffix;
    return getenv($name) ?: ($_ENV[$name] ?? ($_SERVER[$name] ?? null));
}

// Lista de chaves para o Chat (Rodízio)
$apiKeys = array_filter([
    getApiKey(""),   // GEMINI_API_KEY
    getApiKey("_2"), // GEMINI_API_KEY_2
    getApiKey("_3")  // GEMINI_API_KEY_3
]);

if (empty($apiKeys)) {
    echo json_encode(['error' => 'Variaveis de API nao detectadas no Render.']);
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
// 3. MEMÓRIA DA IA (HISTÓRICO)
// ============================================================
if (!isset($_SESSION['historico'])) {
    $_SESSION['historico'] = [];
}
if (count($_SESSION['historico']) > 10) {
    $_SESSION['historico'] = array_slice($_SESSION['historico'], -10);
}

// ============================================================
// 4. SEU PROMPT ADAPTADO (O CORAÇÃO DA IA)
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
- Para finalizar: {"acao": "finalizar_conversa", "mensagem": "Perfeito! Abrindo seu carrinho..."}

EXEMPLO DE RESPOSTA CORRETA:
"Com certeza! Adicionei uma Pizza de Calabresa na sua sacola. Deseja algo mais? 🍕
{\"acao\": \"adicionar_carrinho\", \"itens\": [{\"nome\": \"Pizza de Calabresa\", \"quantidade\": 1}]}"

CARDÁPIO OFICIAL (USE ESSES NOMES):
🍕 PIZZAS: Pizza Marguerita (45), Pizza de Calabresa (42), Pizza de Pepperoni (40), Pizza vegana Especial (60), Pizza de Frango com Catupiry (55), Pizza de 4 queijos (42), Pesto e tomate seco (58), Portuguesa Prime (47).
🍔 HAMBURGUERS: Hamburguer clássico (25), Frango Chick (35), Smash Onion (22), Bacon Jam (34), Double Cheddar (38), Vegetariano de Grão-de-Bico (28), X-Tudo (30).
🍟 ACOMPANHAMENTOS: Saladas (18), Queijo quente (12), Batata Palito Média (15), Batata Especial da Casa (28), Nuggets de Frango (20), Onion Rings (22).
🥤 BEBIDAS: Coca Cola Zero Lata (4.5), Coca cola lata (5), Suco natural de laranja jarra (10), Suco natural de maracujá jarra (15), Cerveja Long Neck (11), Água Mineral 500ml (5).

CUPONS: RS10, RS25, RS50.
ESTILO: Amigável, natural, use emojis 🍔🍕🍟🥤. Respostas curtas e diretas.

Pergunta do cliente: ';

// Montagem do contexto final
$mensagemCompleta = $instrucoes . "\n\n";
foreach ($_SESSION['historico'] as $msg) {
    $mensagemCompleta .= $msg . "\n";
}
$mensagemCompleta .= "Cliente: " . $userPrompt;

// ============================================================
// 5. LÓGICA DE RODÍZIO (RETRY LOGIC)
// ============================================================
$respostaFinal = null;

foreach ($apiKeys as $key) {
    // Usamos o modelo 1.5-flash para garantir estabilidade e velocidade na apresentação
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $key;

    $payload = ["contents" => [["parts" => [["text" => $mensagemCompleta]]]]];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 12
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dados = json_decode($result, true);

    if ($httpCode === 200 && isset($dados['candidates'][0]['content']['parts'][0]['text'])) {
        $respostaFinal = $dados['candidates'][0]['content']['parts'][0]['text'];
        break; // Sucesso! Sai do loop.
    }
}

// ============================================================
// 6. RESPOSTA FINAL
// ============================================================
if ($respostaFinal) {
    $_SESSION['historico'][] = "Cliente: " . $userPrompt;
    $_SESSION['historico'][] = "IA: " . $respostaFinal;
    echo json_encode(['resposta' => $respostaFinal]);
} else {
    echo json_encode(['resposta' => "Desculpe, tive um probleminha técnico. Pode repetir?"]);
}
?>