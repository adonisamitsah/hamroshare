<?php
// Force plain text output for a clean, unstyled report
header('Content-Type: text/plain');

$target_extensions = ['php', 'js', 'html'];
$audit_file_name = basename(__FILE__);

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
$valid_files = [];

// Collect only the target files
foreach ($files as $file) {
    if ($file->isFile() && $file->getFilename() !== $audit_file_name) {
        $ext = strtolower($file->getExtension());
        if (in_array($ext, $target_extensions)) {
            // Store relative path for cleaner output
            $valid_files[] = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }
}

$functions = [];

// ==========================================
// PASS 1: DISCOVER ALL DEFINITIONS
// ==========================================
foreach ($valid_files as $relative_path) {
    $absolute_path = __DIR__ . DIRECTORY_SEPARATOR . $relative_path;
    $lines = file($absolute_path);
    
    foreach ($lines as $line_index => $line) {
        // Look for "function myFunc(" or "function myFunc ("
        if (preg_match('/function\s+([a-zA-Z0-9_]+)\s*\(/i', $line, $matches)) {
            $func_name = $matches[1];
            
            if (!isset($functions[$func_name])) {
                $functions[$func_name] = [
                    'definitions' => [],
                    'usages' => []
                ];
            }
            // Save the exact file and line number (1-indexed)
            $functions[$func_name]['definitions'][] = [
                'file' => $relative_path,
                'line' => $line_index + 1
            ];
        }
    }
}

// ==========================================
// PASS 2: TRACK ALL USAGES
// ==========================================
foreach ($valid_files as $relative_path) {
    $absolute_path = __DIR__ . DIRECTORY_SEPARATOR . $relative_path;
    $lines = file($absolute_path);
    
    foreach ($lines as $line_index => $line) {
        $current_line = $line_index + 1;
        
        // Extract all whole words from the line to check against our function list
        if (preg_match_all('/\b([a-zA-Z0-9_]+)\b/', $line, $matches)) {
            $words_in_line = array_unique($matches[1]);
            
            foreach ($words_in_line as $word) {
                // If the word matches a known function...
                if (isset($functions[$word])) {
                    
                    // Verify this line isn't the definition line itself
                    $is_definition = false;
                    foreach ($functions[$word]['definitions'] as $def) {
                        if ($def['file'] === $relative_path && $def['line'] === $current_line) {
                            $is_definition = true;
                            break;
                        }
                    }
                    
                    if (!$is_definition) {
                        // Store the usage
                        if (!isset($functions[$word]['usages'][$relative_path])) {
                            $functions[$word]['usages'][$relative_path] = [];
                        }
                        $functions[$word]['usages'][$relative_path][] = $current_line;
                    }
                }
            }
        }
    }
}

// ==========================================
// GENERATE ADVANCED REPORT
// ==========================================
ksort($functions); // Alphabetize for readability

$unused_functions = [];
$used_functions = [];

foreach ($functions as $name => $data) {
    $total_usages = 0;
    foreach ($data['usages'] as $file => $lines) {
        $total_usages += count($lines);
    }
    
    if ($total_usages === 0) {
        $unused_functions[$name] = $data;
    } else {
        $used_functions[$name] = $data;
    }
}

echo "================================================================================\n";
echo "                   ADVANCED DEAD CODE AUDIT REPORT\n";
echo "================================================================================\n";
echo "Files Scanned : " . count($valid_files) . " (.php, .js, .html)\n";
echo "Total Functions Found : " . count($functions) . "\n";
echo "Used Functions  : " . count($used_functions) . "\n";
echo "Unused Functions: " . count($unused_functions) . "\n";
echo "================================================================================\n\n";


echo "--------------------------------------------------------------------------------\n";
echo "[ UNUSED FUNCTIONS ] - Safe to delete?\n";
echo "--------------------------------------------------------------------------------\n";
if (empty($unused_functions)) {
    echo "  No unused functions found! Great job.\n";
} else {
    foreach ($unused_functions as $name => $data) {
        echo "❌ $name\n";
        foreach ($data['definitions'] as $def) {
            echo "     Defined in: " . $def['file'] . " (Line " . $def['line'] . ")\n";
        }
        echo "\n";
    }
}

echo "\n--------------------------------------------------------------------------------\n";
echo "[ USED FUNCTIONS ] - Detailed Trace\n";
echo "--------------------------------------------------------------------------------\n";
foreach ($used_functions as $name => $data) {
    // Calculate total
    $total = 0;
    foreach ($data['usages'] as $lines) $total += count($lines);
    
    echo "✅ $name (Used $total times)\n";
    
    // Print Definitions
    foreach ($data['definitions'] as $def) {
        echo "     [Defined] " . $def['file'] . " : Line " . $def['line'] . "\n";
    }
    
    // Print Usages Grouped by File
    foreach ($data['usages'] as $file => $lines) {
        echo "     [Used In] $file : Lines " . implode(', ', $lines) . "\n";
    }
    echo "\n";
}

echo "================================================================================\n";
echo "                           END OF REPORT\n";
echo "================================================================================\n";
?>