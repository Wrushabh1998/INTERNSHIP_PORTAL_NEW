<?php
$dir = new RecursiveDirectoryIterator(__DIR__);
$iterator = new RecursiveIteratorIterator($dir);
$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$errors = [];
foreach ($regex as $fileInfo) {
    $file = $fileInfo[0];
    if (basename($file) === 'verify_syntax.php') continue;
    
    $output = [];
    $return_var = 0;
    // Using the absolute path to php.exe
    exec('c:\xampp\php\php.exe -l "' . $file . '" 2>&1', $output, $return_var);
    if ($return_var !== 0) {
        $errors[$file] = implode("\n", $output);
    }
}

if (empty($errors)) {
    echo "NO SYNTAX ERRORS FOUND!\n";
} else {
    echo count($errors) . " SYNTAX ERRORS FOUND:\n\n";
    foreach ($errors as $file => $err) {
        echo "File: $file\nError: $err\n" . str_repeat('-', 40) . "\n";
    }
}
