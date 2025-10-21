<?php
// Define a chave da API e a URL da APi
// ALERTA: Em um projeto real, a chave de API NUNCA deve ser hardcoded no código!

// 1. Inclui o autoloader do Composer (necessita: composer install)
require __DIR__ . '/vendor/autoload.php';

// 2. Cria a instância do Dotenv e carrega o arquivo .env
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad(); 
} catch (Exception $e) {
    // Trata erro de permissão ou estrutura do .env
    $ia_response = "<div class='erro'>Erro ao carregar o arquivo .env: Verifique se o arquivo existe e as permissões.</div>";
}

// Define a chave da API e a URL da API lendo as variáveis de ambiente
$api_key = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY'); 
$api_url = $_ENV['GROQ_API_URL'] ?? "https://api.groq.com/openai/v1/chat/completions"; 

$ia_response = null; // Variável para armazenar a resposta final da IA
$termo = ''; // Inicializa o termo

// --- VERIFICAÇÃO CRÍTICA DA CHAVE DE API ---
if (empty($api_key) || $api_key === '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') {
    $ia_response = "<div class='erro'>
        ERRO DE CONFIGURAÇÃO: Chave de API Groq não encontrada ou é um placeholder. 
        Por favor, substitua a chave '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' 
        no seu arquivo **.env** pela sua chave real e ativa.
    </div>";
}
// ------------------------------------------

// Verifica se o formulário foi submetido via POST E se não há erro de configuração
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $termo = htmlspecialchars($_POST['termo'] ?? ''); // Termo enviado pelo usuário

    if (!$ia_response) { // Só prossegue com a requisição se não houver erro prévio de chave

        // Prompt de instrução para a IA (Requisito: Explicador de termos acadêmicos)
        $prompt_content = "Explique o termo acadêmico '{$termo}' de forma clara e concisa para um aluno iniciante. Limite a resposta a um parágrafo.";

        // --- Configuração da Requisição cURL ---
        $payload = json_encode([
            "model" => "llama-3.1-8b-instant", 
            "messages" => [
                ["role" => "system", "content" => "Você é um professor prestativo e conciso."],
                ["role" => "user", "content" => $prompt_content]
            ],
            "temperature" => 0.7 
        ]);

        $ch = curl_init($api_url);

        // Configuração das opções do cURL (Consumo via cURL)
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
            } 
            // Tratamento de erro da API (como o "Invalid API Key")
            elseif (isset($data['error']['message'])) {
                $ia_response = "<div class='erro'>
                    Erro da API Groq: " . htmlspecialchars($data['error']['message']) . 
                    "<br>Código: " . htmlspecialchars($data['error']['code'] ?? 'N/A') . 
                    "<br>Verifique sua chave no arquivo **.env**!
                </div>";
            }
            else {
                // Diagnóstico de erro: Se falhar, mostra o JSON completo.
                $ia_response = "<div class='erro'>Erro na Extração da Resposta. JSON bruto de retorno da API:<pre style='background: #fee; border: 1px solid red; padding: 10px; overflow-x: auto;'>" . htmlspecialchars($response) . "</pre></div>";
            }
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
    if ($_SERVER["REQUEST_METHOD"] == "POST" && $ia_response) {
        
        echo "<div class='resposta'>";
        echo "<h2>Termo: " . htmlspecialchars($termo) . "</h2>";
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
