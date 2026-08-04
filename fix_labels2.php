<?php
// Fix missing for attributes in labels across PHP files
// This script adds for="id" attributes to <label> tags that have corresponding inputs

$files = [
    'blotter.php',
    'documents.php',
    'health.php',
    'households.php',
    'officials.php',
    'reports.php',
    'residents.php',
    'welfare.php',
    'login.php',
    'register.php',
];

foreach ($files as $file) {
    $path = "C:/Xampp/htdocs/BARANGAY_MANAGEMENT/$file";
    if (!file_exists($path)) continue;
    
    $content = file_get_contents($path);
    $original = $content;
    
    // Fix pattern: <label>Text</label><input ... id="xxx"> 
    // This handles inline label+input on same line
    $content = preg_replace_callback(
        '/<label>([^<]+)<\/label>\s*(<input[^>]*\bid="([^"]+)")/i',
        function($matches) {
            $labelText = trim($matches[1]);
            $inputTag = $matches[2];
            $id = $matches[3];
            // Check if input already has autocomplete
            if (!preg_match('/\bautocomplete\s*=/i', $inputTag)) {
                // Add autocomplete="off" for fields that shouldn't be autofilled
                $inputTag = preg_replace('/(\s+)(required|autocomplete="[^"]*")?\s*>/i', '$1autocomplete="off" $2>', $inputTag, 1);
            }
            return "<label for=\"$id\">$labelText</label> $inputTag";
        },
        $content
    );
    
    // Pattern: <label>Text</label><textarea ... id="xxx">
    $content = preg_replace_callback(
        '/<label>([^<]+)<\/label>\s*(<textarea[^>]*\bid="([^"]+)")/i',
        function($matches) {
            $labelText = trim($matches[1]);
            $textareaTag = $matches[2];
            $id = $matches[3];
            return "<label for=\"$id\">" . trim($matches[1]) . "</label> $textareaTag";
        },
        $content
    );
    
    // Pattern: <label>Text</label><select ... id="xxx">
    $content = preg_replace_callback(
        '/<label>([^<]+)<\/label>\s*(<select[^>]*\bid="([^"]+)")/i',
        function($matches) {
            $labelText = trim($matches[1]);
            $selectTag = $matches[2];
            $id = $matches[3];
            return "<label for=\"$id\">" . trim($matches[1]) . "</label> $selectTag";
        },
        $content
    );
    
    // Pattern: <label>Text</label>\n<input ... id="xxx">
    $content = preg_replace_callback(
        '/<label>([^<]+)<\/label>\s*\n\s*(<input[^>]*\bid="([^"]+)")/i',
        function($matches) {
            $labelText = trim($matches[1]);
            $inputTag = $matches[2];
            $id = $matches[3];
            return "<label for=\"$id\">" . trim($matches[1]) . "</label>\n    $inputTag";
        },
        $content
    );
    
    // Pattern: <label>Text</label>\n<textarea ... id="xxx">
    $content = preg_replace_callback(
        '/<label>([^<]+)<\/label>\s*\n\s*(<textarea[^>]*\bid="([^"]+)")/i',
        function($matches) {
            $labelText = trim($matches[1]);
            $textareaTag = $matches[2];
            $id = $matches[3];
            return "<label for=\"$id\">" . trim($matches[1]) . "</label>\n    $textareaTag";
        },
        $content
    );
    
    // Pattern: <label>Text</label>\n<select ... id="xxx">
    $content = preg_replace_callback(
        '/<label>([^<]+)<\/label>\s*\n\s*(<select[^>]*\bid="([^"]+)")/i',
        function($matches) {
            $labelText = trim($matches[1]);
            $selectTag = $matches[2];
            $id = $matches[3];
            return "<label for=\"$id\">" . trim($matches[1]) . "</label>\n    $selectTag";
        },
        $content
    );
    
    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Fixed: $file\n";
    } else {
        echo "No changes: $file\n";
    }
}

echo "Done!\n";