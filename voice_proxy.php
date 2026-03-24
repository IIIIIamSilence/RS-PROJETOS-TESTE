<?php
// Desabilita exibição de erros brutos para manter o JSON limpo
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

// ============================================================
// 1. BUSCA DA CHAVE EXCLUSIVA (GEMINI_API_KEY_4)
// ============================================================
function getApiKey4() {
    $name = "GEMINI_API_KEY_4";
    // Tenta buscar em todas as camadas de ambiente possíveis do Render
    return getenv($name) ?: ($_ENV[$name] ?? ($_SERVER[$name] ?? null));
}

$apiKey = getApiKey4();

if (!$apiKey) {
    echo json_encode([
        'resposta' => 'Erro: A chave GEMINI_API_KEY_4 não foi configurada no Render.',
        'debug' => 'Verifique se o nome da variável na aba Environment está correto.'
    ]);
    exit;
}

// ============================================================
// 2. RECEBER INPUT (VOZ TRANSRITA)
// ============================================================
$jsonInput = file_get_contents('php://input');
$inputData = json_decode($jsonInput, true);

// Captura o prompt vindo do JS (aceita JSON ou POST tradicional)
$userVoiceText = $inputData['prompt'] ?? $_POST['prompt'] ?? '';

if (empty($userVoiceText)) {
    echo json_encode(['resposta' => "Não consegui capturar sua voz. Pode tentar de novo?"]);
    exit;
}

// ============================================================
// 3. CONFIGURAÇÃO DA IA (REGRAS PARA VOZ)
// ============================================================
$instrucoes = "Você é o atendente virtual do RS Burger. Sua voz será sintetizada.

REGRAS DE HUMANIZAÇÃO:
- Use frases curtas e naturais. 
- Em vez de 'R$ 45,00', escreva 'quarenta e cinco reais'.
- Use exclamações e emojis para ser simpático.
- NUNCA leia o JSON em voz alta. O JSON deve ficar sempre na última linha.

CARDÁPIO OFICIAL (USE NOMES EXATOS):
PIZZAS: Pizza Marguerita (45), Pizza de Calabresa (42), Pizza de Pepperoni (40), Pizza vegana Especial (60), Pizza de Frango com Catupiry (55), Pizza de 4 queijos (42), Pesto e tomate seco (58), Portuguesa Prime (47).
BURGER: Hamburguer clássico (25), Frango Chick (35), Smash Onion (22), Bacon Jam (34), Double Cheddar (38), Vegetariano de Grão-de-Bico (28), X-Tudo (30).
EXTRAS: Saladas (18), Queijo quente (12), Batata Palito Média (15), Batata Especial da Casa (28), Nuggets de Frango (20), Onion Rings (22).
BEBIDAS: Coca Cola Zero Lata (4.5), Coca cola lata (5), Suco natural de laranja jarra (10), Suco natural de maracujá jarra (15), Cerveja Long Neck (11), Água Mineral 500ml (5).

COMANDOS JSON:
- Adicionar: {\"acao\": \"adicionar_carrinho\", \"itens\": [{\"nome\": \"NOME_EXATO\", \"quantidade\": 1}]}
- Finalizar: {\"acao\": \"finalizar_conversa\"}

CUPONS: RS10, RS25, RS50.

Pergunta do cliente: ";
// ============================================================
// 4. CHAMADA ÚNICA PARA O GEMINI (CHAVE 4)
// ============================================================
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $instrucoes . $userVoiceText]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 15 // Tempo extra para conexões de voz
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$dados = json_decode($response, true);

// ============================================================
// 5. RESPOSTA FINAL
// ============================================================
if ($httpCode === 200 && isset($dados['candidates'][0]['content']['parts'][0]['text'])) {
    $textoFinal = $dados['candidates'][0]['content']['parts'][0]['text'];
    echo json_encode(['resposta' => $textoFinal]);
} else {
    $erroGoogle = $dados['error']['message'] ?? "Erro HTTP $httpCode";
    echo json_encode([
        'resposta' => "Tive um problema com o reconhecimento de voz. Tente de novo, por favor!",
        'debug' => $erroGoogle
    ]);
}
?>