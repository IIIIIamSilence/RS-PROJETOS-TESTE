<?php
// Desabilita exibição de erros brutos para manter o JSON limpo
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
session_start(); // Mantém a memória para a IA saber o que foi dito antes

// ============================================================
// 1. BUSCA DE CHAVES (RODÍZIO 1, 2 e 3)
// ============================================================
function getApiKey($suffix = "") {
    $name = "GEMINI_API_KEY" . $suffix;
    return getenv($name) ?: ($_ENV[$name] ?? ($_SERVER[$name] ?? null));
}

$apiKeys = array_filter([
    getApiKey(""),   // GEMINI_API_KEY
    getApiKey("_2"), // GEMINI_API_KEY_2
    getApiKey("_3")  // GEMINI_API_KEY_3
]);

if (empty($apiKeys)) {
    echo json_encode(['resposta' => 'Erro: Chaves de API não configuradas no ambiente.']);
    exit;
}

// ============================================================
// 2. RECEBER INPUT DO CLIENTE
// ============================================================
$jsonInput = file_get_contents('php://input');
$input = json_decode($jsonInput, true);
$userPrompt = $input['prompt'] ?? $_POST['prompt'] ?? '';

if (empty($userPrompt)) {
    echo json_encode(['resposta' => 'Opa, não entendi. Pode repetir?']);
    exit;
}

// ============================================================
// 3. MEMÓRIA DA SESSÃO
// ============================================================
if (!isset($_SESSION['historico'])) {
    $_SESSION['historico'] = [];
}
// Mantém apenas as últimas 10 mensagens para não estourar o limite do prompt
if (count($_SESSION['historico']) > 10) {
    $_SESSION['historico'] = array_slice($_SESSION['historico'], -10);
}

// ============================================================
// 4. PROMPT E REGRAS DO RS BURGER (SEU TEXTO ADAPTADO)
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

EXEMPLO DE RESPOSTA CORRETA:
"Com certeza! Adicionei uma Pizza de Calabresa na sua sacola. Deseja algo mais? 🍕
{"acao": "adicionar_carrinho", "itens": [{"nome": "Pizza de Calabresa", "quantidade": 1}]}"

FUNÇÃO:
- Ajudar o cliente a escolher pratos
- Sugerir combinações (prato + acompanhamento + bebida)
- Recomendar pratos com base no gosto do cliente
- Incentivar o fechamento do pedido

CARDÁPIO OFICIAL:
🍕 PIZZAS: Pizza Marguerita (R$45), Pizza de Calabresa (R$42), Pizza de Pepperoni (R$40), Pizza vegana Especial (R$60), Pizza de Frango com Catupiry (R$55), Pizza de 4 queijos (R$42), Pesto e tomate seco (R$58), Portuguesa Prime (R$47).
🍔 HAMBURGUERS: Hamburguer clássico (R$25), Frango Chick (R$35), Smash Onion (R$22), Bacon Jam (R$34), Double Cheddar (R$38), Vegetariano de Grão-de-Bico (R$28), X-Tudo (R$30).
🍟 ACOMPANHAMENTOS: Saladas (R$18), Queijo quente (R$12), Batata Palito Média (R$15), Batata Especial da Casa (R$28), Nuggets de Frango (R$20), Onion Rings (R$22).
🥤 BEBIDAS: Coca Cola Zero Lata (R$4,50), Coca cola lata (R$5), Suco natural de laranja jarra (R$10), Suco natural de maracujá jarra (R$15), Cerveja Long Neck (R$11), Água Mineral 500ml (R$5).

CUPONS: RS10, RS25, RS50.

ESTILO: Amigável, natural, use emojis 🍔🍕🍟🥤 e respostas curtas. Ao confirmar, pergunte se deseja algo mais.

Pergunta do cliente: ';

// Monta o contexto final (Instruções + Histórico + Pergunta Atual)
$mensagemCompleta = $instrucoes . "\n\n";
foreach ($_SESSION['historico'] as $msg) {
    $mensagemCompleta .= $msg . "\n";
}
$mensagemCompleta .= "Cliente: " . $userPrompt;

// ============================================================
// 5. LÓGICA DE RODÍZIO (TENTATIVA ENTRE AS CHAVES)
// ============================================================
$respostaFinal = null;

foreach ($apiKeys as $key) {
    // Usamos gemini-1.5-flash pela rapidez e limites de uso melhores para demonstrações
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $key;

    $payload = [
        "contents" => [["parts" => [["text" => $mensagemCompleta]]]]
    ];

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
    // Se deu erro, o loop continua para a próxima chave (GEMINI_API_KEY_2, etc)
}

// ============================================================
// 6. RETORNO PARA O FRONT-END
// ============================================================
if ($respostaFinal) {
    $_SESSION['historico'][] = "Cliente: " . $userPrompt;
    $_SESSION['historico'][] = "IA: " . $respostaFinal;
    echo json_encode(['resposta' => $respostaFinal]);
} else {
    echo json_encode(['resposta' => "Desculpe, tive um probleminha técnico. Pode repetir?"]);
}
?>