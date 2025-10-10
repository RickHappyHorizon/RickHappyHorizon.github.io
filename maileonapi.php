<?php
// fetch_as_text.php
// Doel: JSON ophalen en serveren als text/plain zodat NotebookLM het accepteert.

declare(strict_types=1);

// Zet hier je Zapier Store URL of geef hem mee via ?url=
$defaultUrl = 'https://store.zapier.com/api/records?secret=6251511b-743b-4031-83b3-b71e9929aee4';
$url = isset($_GET['url']) && filter_var($_GET['url'], FILTER_VALIDATE_URL) ? $_GET['url'] : $defaultUrl;

// Optionele pretty print toggles via query parameters
$pretty = isset($_GET['pretty']) ? (bool)$_GET['pretty'] : true;
$indent = isset($_GET['indent']) ? max(0, (int)$_GET['indent']) : 2;

// Veiligheid
if (stripos($url, 'store.zapier.com/api/records') === false) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Ongeldige bron url";
    exit;
}

// Haal de bron op met cURL
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'User-Agent: text-proxy-php'
    ],
]);
$body = curl_exec($ch);
$err  = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

// Antwoord headers
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: s-maxage=60, max-age=60');

if ($body === false || $code < 200 || $code >= 300) {
    http_response_code(502);
    echo "Fout bij ophalen bron\nStatus: {$code}\nError: {$err}";
    exit;
}

// Probeer JSON te decoderen en weer als nette tekst uit te schrijven
$data = json_decode($body, true);

if (json_last_error() === JSON_ERROR_NONE) {
    if ($pretty) {
        // Mooie inspringing
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT;
        $json = json_encode($data, $flags);
        if ($indent !== 2) {
            // Pas inspringing aan als gevraagd
            $json = preg_replace('/^(\s+)/m', function ($m) use ($indent) {
                $spaces = strlen($m[1]);
                $new = str_repeat(' ', $indent * (int)floor($spaces / 2));
                return $new;
            }, $json);
        }
        echo $json;
    } else {
        // Platte enkele regel
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION;
        echo json_encode($data, $flags);
    }
} else {
    // Niet valide JSON teruggekregen, stuur de ruwe body door als tekst
    echo $body;
}
