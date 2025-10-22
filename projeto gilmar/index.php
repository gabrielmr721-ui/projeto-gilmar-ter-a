<?php
// Define a chave da API e a URL da APi
// ALERTA: Em um projeto real, a chave de API NUNCA deve ser hardcoded no código!

// 1. Inclui o autoloader do Composer (necessita: composer install)
require __DIR__ . '/vendor/autoload.php';

// Variável de controle de erro de configuração
$config_error = false; 

// 2. Cria a instância do Dotenv e carrega o arquivo .env
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad(); 
} catch (Exception $e) {
    // Trata erro de permissão ou estrutura do .env
    $ia_response = "<div class='erro'>Erro ao carregar o arquivo .env: Verifique se o arquivo existe e as permissões.</div>";
    $config_error = true;
}

// Define a chave da API e a URL da API lendo as variáveis de ambiente
// MELHORIA: Simplificando a leitura da variável de ambiente CHAVE_API após o Dotenv ter carregado.
$api_key = $_ENV['CHAVE_API'] ?? null; 
$api_url = $_ENV['GROQ_API_URL'] ?? "https://api.groq.com/openai/v1/chat/completions"; 

// NOVA VERIFICAÇÃO DE ERRO: Garante que a chave de API foi carregada.
if (!$api_key && !$config_error) {
    $ia_response = "<div class='erro'>ERRO DE CONFIGURAÇÃO: A variável **CHAVE_API** não foi encontrada no seu arquivo **.env**.</div>";
    $config_error = true;
}

$ia_response = $ia_response ?? null; // Variável para armazenar a resposta final da IA (Garantindo que é null se não houver erro)
$termo = ''; // Inicializa o termo

// ------------------------------------------------------------------

// Verifica se o formulário foi submetido via POST E se não há erro de configuração
// CORREÇÃO LÓGICA: Usar $config_error para controle mais claro.
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$config_error) {

    $termo = htmlspecialchars($_POST['termo'] ?? ''); // Termo enviado pelo usuário

    // O resto do código permanece excelente e sem alterações

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
    if ($_SERVER["REQUEST_METHOD"] == "POST" || $config_error) { // Exibe erros de config mesmo sem POST
        
        // Garante que $ia_response não é nulo se houver erro de configuração
        if ($config_error && !isset($ia_response)) {
             // Caso de erro no .env mas a $ia_response já foi definida na seção de try/catch
        }
        
        if (isset($ia_response)) {
            echo "<div class='resposta'>";
            
            // Só exibe o termo se não for um erro de configuração inicial (antes do POST)
            if ($_SERVER["REQUEST_METHOD"] == "POST" && !$config_error) {
                echo "<h2>Termo: " . htmlspecialchars($termo) . "</h2>";
                echo "<h3>Resultado:</h3>";
            } elseif ($config_error) {
                // Se for um erro de configuração (antes mesmo de enviar), mostra o erro diretamente
                echo "<h2>Erro de Configuração</h2>";
            }


            // Se a resposta for uma string de erro (div), exibe o erro
            if (strpos($ia_response, '<div class=') !== false) {
                echo $ia_response; 
            } else {
                // Se for a resposta da IA, exibe o texto
                echo "<p>" . nl2br(htmlspecialchars($ia_response)) . "</p>";
            }

            echo "</div>";
        }
    }
    ?>
</div>

</body>
</html>