<?php
echo "Current working directory: " . getcwd() . "\n";
echo "__DIR__ from automation: " . dirname(__FILE__ . '/applicant/automation/dummy') . "\n";
echo "File exists check:\n";
echo "vendor/autoload.php exists: " . (file_exists('vendor/autoload.php') ? 'YES' : 'NO') . "\n";
echo "../vendor/autoload.php exists: " . (file_exists('../vendor/autoload.php') ? 'YES' : 'NO') . "\n";
echo "../../vendor/autoload.php exists: " . (file_exists('../../vendor/autoload.php') ? 'YES' : 'NO') . "\n";

echo "\nDirectory listing:\n";
if (is_dir('vendor')) {
    echo "vendor/ directory found in current location\n";
} else {
    echo "vendor/ directory NOT found in current location\n";
}

if (is_dir('../vendor')) {
    echo "vendor/ directory found one level up\n";
} else {
    echo "vendor/ directory NOT found one level up\n";
}

if (is_dir('../../vendor')) {
    echo "vendor/ directory found two levels up\n";
} else {
    echo "vendor/ directory NOT found two levels up\n";
}

echo "\nAbsolute path test:\n";
$abs_path = dirname(__FILE__) . '/vendor/autoload.php';
echo "Absolute path: $abs_path\n";
echo "Absolute path exists: " . (file_exists($abs_path) ? 'YES' : 'NO') . "\n";
?>
