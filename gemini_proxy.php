<?php
header('Content-Type: application/json');
session_start(); // 🔥 memória da IA

// ===============================
// 1. API KEYS (ROTAÇÃO)
// ===============================

// Tenta buscar de 3 formas diferentes para garantir que o Render entregue a chave
$apiKey = getenv('GEMINI_API_KEY') ?: $_ENV['GEMINI_API_KEY'] ?: $_SERVER['GEMINI_API_KEY'];

if (!$apiKey) {
    // Se ainda assim não achar, vamos avisar exatamente o que está faltando
    echo json_encode(['error' => 'A variavel GEMINI_API_KEY nao foi detectada no ambiente do Render.']);
    exit;
}

// O restante do seu código (curl, etc) continua igual abaixo...

// ===============================
// 2. RECEBER INPUT (CORRIGIDO)
// ===============================
$jsonInput = file_get_contents('php://input');
$input = json_decode($jsonInput, true);
$userPrompt = $input['prompt'] ?? '';

if (empty($userPrompt)) {
    echo json_encode(['resposta' => 'Opa, não entendi. Pode repetir?']);
    exit;
}

// ===============================
// 3. MEMÓRIA DA IA
// ===============================
if (!isset($_SESSION['historico'])) {
    $_SESSION['historico'] = [];
}

// Limita histórico (evita ficar gigante)
if (count($_SESSION['historico']) > 10) {
    $_SESSION['historico'] = array_slice($_SESSION['historico'], -10);
}

// ===============================
// 4. SEU PROMPT ORIGINAL (INTACTO)
// ===============================
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

CARDÁPIO OFICIAL (USE EXATAMENTE ESSES NOMES):

🍕 PIZZAS:
- Pizza Marguerita (R$45)
- Pizza de Calabresa (R$42)
- Pizza de Pepperoni (R$40)
- Pizza vegana Especial (R$60)
- Pizza de Frango com Catupiry (R$55)
- Pizza de 4 queijos (R$42)
- Pesto e tomate seco (R$58)
- Portuguesa Prime (R$47)

🍔 HAMBURGUERS:
- Hamburguer clássico (R$25)
- Frango Chick (R$35)
- Smash Onion (R$22)
- Bacon Jam (R$34)
- Double Cheddar (R$38)
- Vegetariano de Grão-de-Bico (R$28)
- X-Tudo (R$30) (acompanha batata frita e refrigerante)

🍟 ACOMPANHAMENTOS:
- Saladas (R$18)
- Queijo quente (R$12)
- Batata Palito Média (R$15)
- Batata Especial da Casa (R$28)
- Nuggets de Frango (R$20)
- Onion Rings (R$22)

🥤 BEBIDAS:
- Coca Cola Zero Lata (R$4,50)
- Coca cola lata (R$5)
- Suco natural de laranja jarra (R$10)
- Suco natural de maracujá jarra (R$15)
- Cerveja Long Neck (R$11)
- Água Mineral 500ml (R$5)

🔥 POPULARES:
- Pizza Marguerita
- Pizza de Calabresa
- Pizza de Pepperoni
- X-Tudo
- Nuggets de Frango
- Batata Especial da Casa

COMPORTAMENTO:
- Sempre sugira itens do cardápio acima
- Nunca invente pratos que não existem
- Use exatamente os nomes do cardápio
- Se o cliente estiver indeciso, sugira itens populares

REGRAS INTELIGENTES:
- Se o cliente disser que está com muita fome → sugira X-Tudo, pizzas ou combos completos
- Se disser que quer algo leve → sugira Saladas ou Vegetariano de Grão-de-Bico
- Sugira sempre acompanhamentos 

CARRINHO:
- Quando o cliente decidir um item: use "acao": "adicionar_carrinho".
- NOVIDADE: Quando o cliente disser que não quer mais nada, que pode fechar, finalizar ou que é só isso, você deve confirmar e enviar este JSON:

{
 "acao": "finalizar_conversa",
 "mensagem": "Perfeito! Abrindo seu carrinho para finalização..."
}

- Se tiver mais de um item, inclua todos no JSON
- Nunca explique o JSON

CUPONS:
- Em momentos aleatórios, você pode oferecer um cupom
Cupons disponíveis: RS10, RS25, RS50.
Formato: 🎁 Cupom disponível: CODIGO

ESTILO:
- Amigável e natural
- Use emojis 🍔🍕🍟🥤
- Respostas curtas e diretas

- Ao confirmar um pedido, diga ao cliente que os itens já foram adicionados à sacola e pergunte se ele deseja algo mais para acompanhar

Pergunta do cliente: ';

// ===============================
// 5. MONTA CONTEXTO COM MEMÓRIA
// ===============================
$mensagemCompleta = $instrucoes . "\n\n";

// adiciona histórico anterior
foreach ($_SESSION['historico'] as $msg) {
    $mensagemCompleta .= $msg . "\n";
}

// adiciona nova pergunta
$mensagemCompleta .= "Cliente: " . $userPrompt;

// ===============================
// 6. CHAMADA GEMINI
// ===============================
// ===============================
// 6. CHAMADA GEMINI (CORRIGIDO)
// ===============================

// Usamos a $apiKey que pegamos lá no topo do arquivo via getenv
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

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
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$dados = json_decode($response, true);

// ===============================
// 7. RESPOSTA FINAL (AJUSTADO)
// ===============================

if ($httpCode === 200 && isset($dados['candidates'][0]['content']['parts'][0]['text'])) {
    $resposta = $dados['candidates'][0]['content']['parts'][0]['text'];

    // Salva no histórico
    $_SESSION['historico'][] = "Cliente: " . $userPrompt;
    $_SESSION['historico'][] = "IA: " . $resposta;

    echo json_encode(['resposta' => $resposta]);
} else {
    // Se der erro, vamos mostrar o que o Google respondeu para facilitar o conserto
    $msgErro = $dados['error']['message'] ?? "Erro desconhecido (HTTP $httpCode)";
    echo json_encode(['resposta' => "Erro Técnico: " . $msgErro]);
}
