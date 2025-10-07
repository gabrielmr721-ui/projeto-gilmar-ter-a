<?php
// Define a chave da API e a URL da API
// ALERTA: Em um projeto real, a chave de API NUNCA deve ser hardcoded no código!
$api_key = "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"; // Sua chave de API
// Usamos o endpoint de chat/completions, que é o padrão atual para LLMs
$api_url = "https://api.groq.com/openai/v1/chat/completions"; 

$ia_response = null; // Variável para armazenar a resposta final da IA

// Verifica se o formulário foi submetido via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $termo = htmlspecialchars($_POST['termo'] ?? ''); // Termo enviado pelo usuário
    
    // Prompt de instrução para a IA (Requisito: Explicador de termos acadêmicos)
    $prompt_content = "Explique o termo acadêmico '{$termo}' de forma clara e concisa para um aluno iniciante. Limite a resposta a um parágrafo.";

    // --- Configuração da Requisição cURL ---
    // CORREÇÃO: Modelo trocado para 'llama-3.1-8b-instant'
    $payload = json_encode([
        "model" => "llama-3.1-8b-instant", 
        "messages" => [
            ["role" => "system", "content" => "Você é um professor prestativo e conciso."],
            ["role" => "user", "content" => $prompt_content]
        ],
        "temperature" => 0.7 
    ]);

    $ch = curl_init($api_url);

    // Configuração das opções do cURL (Requisito: Consumo via cURL)
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Configuração dos Headers: Content-Type e Autorização (API Key)
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $api_key
    ]);

    // Executa a requisição
    $response = curl_exec($ch);

    // Verifica por erros no cURL
    if (curl_errno($ch)) {
        $ia_response = "<div class='erro'>Erro cURL: " . curl_error($ch) . "</div>";
    }
    curl_close($ch);

    // --- Processamento da Resposta da IA ---
    if (!$ia_response) {
        $data = json_decode($response, true);
        
        // Caminho de extração para o formato 'chat/completions'
        if (isset($data['choices'][0]['message']['content'])) {
            $ia_response = $data['choices'][0]['message']['content'];
        } else {
            // Diagnóstico de erro: Se falhar, mostra o JSON completo.
            $ia_response = "<div class='erro'>Erro na Extração da Resposta. JSON bruto de retorno da API:<pre style='background: #fee; border: 1px solid red; padding: 10px; overflow-x: auto;'>" . htmlspecialchars($response) . "</pre></div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Projeto IA PHP - Explicador de Termos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <h1>Explicador de Termos Acadêmicos (IA + PHP)</h1>

    <form method="post" action="index.php">
        <label for="termo">Digite um Termo Acadêmico para Explicação:</label>
        <input type="text" id="termo" name="termo" required placeholder="Ex: Algoritmo Genético, Paradigma Funcional..." 
               value="<?php echo isset($termo) ? htmlspecialchars($termo) : ''; ?>">
        <button type="submit">Explicar Termo com IA</button>
    </form>

    <?php
    // --- Exibe o Resultado ---
    // Requisito: Exibir a resposta gerada pela IA na mesma página (Funcionamento básico)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && $ia_response) {
        
        echo "<div class='resposta'>";
        echo "<h2>Termo: " . $termo . "</h2>";
        echo "<h3>Resultado:</h3>";

        // Se a resposta for uma string de erro (div), exibe o erro
        if (strpos($ia_response, '<div class=') !== false) {
            echo $ia_response; 
        } else {
            // Se for a resposta da IA, exibe o texto
            echo "<p>" . nl2br(htmlspecialchars($ia_response)) . "</p>";
        }

        echo "</div>";
    }
    ?>
</div>

</body>
</html>