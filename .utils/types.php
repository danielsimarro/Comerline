<?php
$types = [];

$txt = fopen("types.txt", "w");

if (($file = fopen("Productos.csv", "r")) !== false) {
    echo "Executing script...." . "\n";

    $count = 0;
    while (($data = fgetcsv($file, 5000, ";", "\"")) !== false) {
        $count++;
        if ($count == 1) {
            continue; // If the counter equals 1 that means it's the first line, so we skip it
        } else {
            if ($data[81]) {
                $types[] = $data[81];
            }
        }
    }
}

$uniqueTypes = array_unique($types);
foreach ($uniqueTypes as $type) {
    fwrite($txt, $type . "\n");
}
