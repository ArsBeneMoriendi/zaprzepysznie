<?php
require_once __DIR__ . '/config.php';

function scrape_recipe_from_url(string $url): array
{
    $url = trim($url);

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Podany adres URL jest nieprawidłowy.');
    }

    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

    if (!in_array($host, ALLOWED_SCRAPER_DOMAINS, true)) {
        throw new RuntimeException('Ten serwis nie jest obsługiwany. Obsługiwane strony: aniagotuje.pl, kwestiasmaku.com, poprostupycha.com.pl.');
    }

    $html = scraper_fetch_html($url);

    if (!$html) {
        throw new RuntimeException('Nie udało się pobrać strony z przepisem.');
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    $data = [
        'title' => '',
        'ingredients' => '',
        'instructions' => '',
        'image_url' => '',
        'source_url' => $url,
    ];

    $jsonRecipe = scraper_find_jsonld_recipe($xpath);
    if ($jsonRecipe) {
        $data['title'] = scraper_to_text($jsonRecipe['name'] ?? '');
        $data['ingredients'] = scraper_ingredients_from_jsonld($jsonRecipe['recipeIngredient'] ?? []);
        $data['instructions'] = scraper_instructions_from_jsonld($jsonRecipe['recipeInstructions'] ?? []);
        $data['image_url'] = scraper_image_from_jsonld($jsonRecipe['image'] ?? '');
    }

    $data['title'] = $data['title'] ?: scraper_first_text($xpath, ['//h1', '//meta[@property="og:title"]/@content', '//title']);
    $data['image_url'] = $data['image_url'] ?: scraper_first_text($xpath, ['//meta[@property="og:image"]/@content', '//meta[@name="twitter:image"]/@content']);

    if (!$data['ingredients'] || !$data['instructions']) {
        $fallback = scraper_site_fallback($host, $xpath);
        $data['ingredients'] = $data['ingredients'] ?: ($fallback['ingredients'] ?? '');
        $data['instructions'] = $data['instructions'] ?: ($fallback['instructions'] ?? '');
    }

    foreach ($data as $key => $value) {
        $data[$key] = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    if (!$data['title']) {
        throw new RuntimeException('Nie udało się rozpoznać tytułu przepisu.');
    }

    return $data;
}

function scraper_fetch_html(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 18,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Zaprzepysznie/1.0)',
        CURLOPT_HTTPHEADER => ['Accept-Language: pl-PL,pl;q=0.9,en;q=0.7'],
    ]);

    $html = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($html === false || $status < 200 || $status >= 300) {
        return '';
    }

    return $html;
}

function scraper_find_jsonld_recipe(DOMXPath $xpath): ?array
{
    $scripts = $xpath->query('//script[@type="application/ld+json"]');

    foreach ($scripts as $script) {
        $raw = trim($script->textContent);
        if ($raw === '') {
            continue;
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            continue;
        }

        $recipe = scraper_search_recipe_object($json);
        if ($recipe) {
            return $recipe;
        }
    }

    return null;
}

function scraper_search_recipe_object(mixed $item): ?array
{
    if (!is_array($item)) {
        return null;
    }

    $type = $item['@type'] ?? null;
    $types = is_array($type) ? $type : [$type];

    foreach ($types as $singleType) {
        if (is_string($singleType) && strtolower($singleType) === 'recipe') {
            return $item;
        }
    }

    foreach (['@graph', 'itemListElement'] as $key) {
        if (!empty($item[$key]) && is_array($item[$key])) {
            foreach ($item[$key] as $child) {
                $found = scraper_search_recipe_object($child);
                if ($found) {
                    return $found;
                }
            }
        }
    }

    foreach ($item as $child) {
        if (is_array($child)) {
            $found = scraper_search_recipe_object($child);
            if ($found) {
                return $found;
            }
        }
    }

    return null;
}

function scraper_ingredients_from_jsonld(mixed $ingredients): string
{
    if (is_string($ingredients)) {
        return $ingredients;
    }

    if (!is_array($ingredients)) {
        return '';
    }

    $lines = [];
    foreach ($ingredients as $ingredient) {
        $text = scraper_to_text($ingredient);
        if ($text !== '') {
            $lines[] = '- ' . $text;
        }
    }

    return implode("\n", $lines);
}

function scraper_instructions_from_jsonld(mixed $instructions): string
{
    if (is_string($instructions)) {
        return $instructions;
    }

    if (!is_array($instructions)) {
        return '';
    }

    $lines = [];
    $step = 1;

    foreach ($instructions as $instruction) {
        if (is_string($instruction)) {
            $text = trim($instruction);
        } elseif (is_array($instruction)) {
            if (($instruction['@type'] ?? '') === 'HowToSection' && !empty($instruction['itemListElement'])) {
                foreach ($instruction['itemListElement'] as $sectionStep) {
                    $text = scraper_to_text($sectionStep['text'] ?? $sectionStep['name'] ?? $sectionStep);
                    if ($text !== '') {
                        $lines[] = ($step++) . '. ' . $text;
                    }
                }
                continue;
            }

            $text = scraper_to_text($instruction['text'] ?? $instruction['name'] ?? $instruction);
        } else {
            $text = '';
        }

        if ($text !== '') {
            $lines[] = ($step++) . '. ' . $text;
        }
    }

    return implode("\n", $lines);
}

function scraper_image_from_jsonld(mixed $image): string
{
    if (is_string($image)) {
        return $image;
    }

    if (is_array($image)) {
        if (!empty($image['url'])) {
            return scraper_to_text($image['url']);
        }

        foreach ($image as $value) {
            $found = scraper_image_from_jsonld($value);
            if ($found) {
                return $found;
            }
        }
    }

    return '';
}

function scraper_to_text(mixed $value): string
{
    if (is_string($value) || is_numeric($value)) {
        return trim((string) $value);
    }

    if (is_array($value)) {
        if (isset($value['text'])) {
            return scraper_to_text($value['text']);
        }
        if (isset($value['name'])) {
            return scraper_to_text($value['name']);
        }

        $parts = [];
        foreach ($value as $child) {
            $text = scraper_to_text($child);
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        return trim(implode(' ', $parts));
    }

    return '';
}

function scraper_first_text(DOMXPath $xpath, array $queries): string
{
    foreach ($queries as $query) {
        $nodes = $xpath->query($query);
        if (!$nodes || $nodes->length === 0) {
            continue;
        }

        $text = trim($nodes->item(0)->textContent);
        if ($text !== '') {
            return $text;
        }
    }

    return '';
}

function scraper_site_fallback(string $host, DOMXPath $xpath): array
{
    $ingredients = '';
    $instructions = '';

    // poprostupycha.com.pl
    if (str_contains($host, 'poprostupycha.com.pl')) {
        $nodes = $xpath->query('//*[@itemprop="recipeIngredient"]');
        $ingredients = scraper_nodes_to_lines($nodes, '- ');

        $stepNodes = $xpath->query('//*[contains(@class,"steps-columns") and contains(@class,"last")][@itemprop="text"]');
        $lines = [];
        $step = 1;
        foreach ($stepNodes as $stepNode) {
            $paragraphs = $xpath->query('.//p', $stepNode);
            $parts = [];
            foreach ($paragraphs as $p) {
                $text = trim(preg_replace('/\s+/', ' ', $p->textContent));
                if ($text !== '' && mb_strlen($text) >= 3) {
                    $parts[] = $text;
                }
            }
            $combined = implode(' ', $parts);
            if ($combined !== '') {
                $lines[] = ($step++) . '. ' . $combined;
            }
        }
        $instructions = implode("\n", $lines);

        return ['ingredients' => $ingredients, 'instructions' => $instructions];
    }

    $ingredientQueries = [
        '//*[contains(@class,"wprm-recipe-ingredient")]//li | //*[contains(@class,"wprm-recipe-ingredient")]',
        '//*[contains(@class,"recipe-ingredients")]//li | //*[contains(@class,"ingredients")]//li',
        '//*[contains(@class,"field-name-field-skladniki")]//li | //*[contains(@class,"field-name-field-skladniki")]//*[self::p or self::div]',
        '//*[contains(@class,"skladniki")]//li | //*[contains(@id,"skladniki")]//li',
    ];

    $instructionQueries = [
        '//*[contains(@class,"wprm-recipe-instruction")]//li | //*[contains(@class,"wprm-recipe-instruction-text")]',
        '//*[contains(@class,"recipe-instructions")]//li | //*[contains(@class,"instructions")]//li',
        '//*[contains(@class,"field-name-field-przygotowanie")]//li | //*[contains(@class,"field-name-field-przygotowanie")]//*[self::p or self::div]',
        '//*[contains(@class,"przygotowanie")]//li | //*[contains(@id,"przygotowanie")]//li',
    ];

    foreach ($ingredientQueries as $query) {
        $ingredients = scraper_nodes_to_lines($xpath->query($query), '- ');
        if ($ingredients !== '') {
            break;
        }
    }

    foreach ($instructionQueries as $query) {
        $instructions = scraper_nodes_to_lines($xpath->query($query), '', true);
        if ($instructions !== '') {
            break;
        }
    }

    if ($ingredients === '') {
        $ingredients = scraper_text_between_headings($xpath, ['składniki'], ['przygotowanie', 'wykonanie', 'sposób przygotowania'], '- ');
    }

    if ($instructions === '') {
        $instructions = scraper_text_between_headings($xpath, ['przygotowanie', 'wykonanie', 'jak zrobić'], ['smacznego', 'zobacz również', 'komentarze'], '', true);
    }

    return ['ingredients' => $ingredients, 'instructions' => $instructions];
}

function scraper_nodes_to_lines(?DOMNodeList $nodes, string $prefix = '', bool $numbered = false): string
{
    if (!$nodes || $nodes->length === 0) {
        return '';
    }

    $lines = [];
    $i = 1;

    foreach ($nodes as $node) {
        $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
        $text = preg_replace('/^(Składniki|Przygotowanie|Kopiuj|Wyślij emailem)\s*:?/iu', '', $text);
        $text = trim($text);

        if ($text === '' || mb_strlen($text) < 3) {
            continue;
        }

        $lines[] = $numbered ? ($i++ . '. ' . $text) : ($prefix . $text);
    }

    return implode("\n", array_unique($lines));
}

function scraper_text_between_headings(DOMXPath $xpath, array $startWords, array $endWords, string $prefix = '', bool $numbered = false): string
{
    $bodyText = $xpath->query('//body')->item(0)?->textContent ?? '';
    $bodyText = trim(preg_replace('/\s+/', "\n", $bodyText));
    $lines = preg_split('/\R+/', $bodyText) ?: [];

    $collecting = false;
    $result = [];
    $step = 1;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $lower = mb_strtolower($line);

        if (!$collecting) {
            foreach ($startWords as $word) {
                if (str_contains($lower, mb_strtolower($word))) {
                    $collecting = true;
                    break;
                }
            }
            continue;
        }

        foreach ($endWords as $word) {
            if (str_contains($lower, mb_strtolower($word))) {
                $collecting = false;
                break 2;
            }
        }

        if (mb_strlen($line) < 3 || in_array($line, ['Kopiuj', 'Drukuj', 'Wyślij emailem'], true)) {
            continue;
        }

        $result[] = $numbered ? ($step++ . '. ' . $line) : ($prefix . $line);

        if (count($result) >= 60) {
            break;
        }
    }

    return implode("\n", array_unique($result));
}
