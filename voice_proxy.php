<?php
header('Content-Type: application/json');

// Chave de API - Certifique-se de que esta chave está ativa e com faturamento/quota OK
// Tenta buscar de 3 formas diferentes para garantir que o Render entregue a chave
$apiKey = getenv('GEMINI_API_KEY') ?: $_ENV['GEMINI_API_KEY'] ?: $_SERVER['GEMINI_API_KEY'];

if (!$apiKey) {
    // Se ainda assim não achar, vamos avisar exatamente o que está faltando
    echo json_encode(['error' => 'A variavel GEMINI_API_KEY nao foi detectada no ambiente do Render.']);
    exit;
}

// O restante do seu código (curl, etc) continua igual abaixo...

$input = json_decode(file_get_contents('php://input'), true);
$userVoiceText = $input['prompt'] ?? '';

// Montagem do Prompt com o Cardápio embutido para a IA não errar os nomes
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

// Endpoint corrigido para o modelo estável gemini-2.5-flash
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
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$dadosRes = json_decode($res, true);

// Verificação de erro para evitar que o JS receba um vazio
if ($httpCode !== 200 || !isset($dadosRes['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode(['resposta' => "Desculpe, tive um probleminha técnico. Pode repetir?"]);
} else {
    $textoFinal = $dadosRes['candidates'][0]['content']['parts'][0]['text'];
    echo json_encode(['resposta' => $textoFinal]);
}
?>
