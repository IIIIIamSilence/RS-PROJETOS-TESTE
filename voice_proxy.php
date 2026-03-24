<?php
// Configurações de exibição de erro para debug (opcional para teste)
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Permite chamadas de outros domínios se necessário

// ============================================================
// 1. BUSCA EXAUSTIVA DE CHAVES (FORÇAR RENDER A ENCONTRAR)
// ============================================================
function getApiKey($suffix = "") {
    $name = "GEMINI_API_KEY" . $suffix;
    // Tenta buscar em todas as camadas de ambiente possíveis do Render
    return getenv($name) ?: ($_ENV[$name] ?? ($_SERVER[$name] ?? null));
}

// Criamos a lista de chaves (Principal, Reserva 1 e Reserva 2)
$apiKeys = [
    getApiKey(""),   // Busca GEMINI_API_KEY
    getApiKey("_2"), // Busca GEMINI_API_KEY_2
    getApiKey("_3")  // Busca GEMINI_API_KEY_3
];

// Remove valores nulos ou vazios da lista
$apiKeys = array_filter($apiKeys);

if (empty($apiKeys)) {
    echo json_encode([
        'resposta' => 'Erro: Nenhuma chave API detectada no ambiente do servidor.',
        'debug_info' => 'Verifique a aba Environment no painel do Render.'
    ]);
    exit;
}

// ============================================================
// 2. RECEBER INPUT (ACEITA JSON OU FORM-DATA)
// ============================================================
$jsonInput = file_get_contents('php://input');
$inputData = json_decode($jsonInput, true);

// Tenta pegar o prompt de qualquer origem para evitar erro de "vazio"
$userVoiceText = $inputData['prompt'] ?? $_POST['prompt'] ?? $_GET['prompt'] ?? '';

if (empty($userVoiceText)) {
    echo json_encode(['resposta' => "Não consegui capturar sua voz. Pode tentar de novo?"]);
    exit;
}

// ============================================================
// 3. CONFIGURAÇÃO DA IA (CARDÁPIO E REGRAS)
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
// 4. LÓGICA DE RODÍZIO (RETRY LOGIC)
// ============================================================
$textoFinal = null;

// O loop percorre cada chave até uma funcionar (HTTP 200)
foreach ($apiKeys as $currentKey) {
    // Usamos o modelo 2.5-flash para maior estabilidade e limites de quota melhores
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $currentKey;
    
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
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 segundos de limite por tentativa

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dadosRes = json_decode($res, true);

    // Verificação: Se o código for 200 e houver resposta de texto, sucesso!
    if ($httpCode === 200 && isset($dadosRes['candidates'][0]['content']['parts'][0]['text'])) {
        $textoFinal = $dadosRes['candidates'][0]['content']['parts'][0]['text'];
        break; // Interrompe o loop pois já temos a resposta
    }
    
    // Se caiu aqui (erro 429, 500, etc), o 'foreach' pula automaticamente para a próxima chave
}

// ============================================================
// 5. RESPOSTA FINAL PARA O JAVASCRIPT
// ============================================================
if ($textoFinal) {
    echo json_encode(['resposta' => $textoFinal]);
} else {
    // Caso TODAS as chaves falhem (raro com 3 chaves)
    echo json_encode([
        'resposta' => "Desculpe, tive um probleminha técnico devido à alta demanda. Pode repetir, por favor?",
        'error_log' => "Todas as chaves falharam ou o Google está instável."
    ]);
}
?>