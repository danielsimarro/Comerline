<?php
if (($file = fopen("Productos.csv", "r")) !== FALSE) {
    echo "Executing script...."."\n";

    $new = fopen('prueba.csv', 'w');

    $header = [
        'sku',
        'attribute_set_code',
        'product_type',
        'categories',
        'name',
        'description',
        'weight',
        'product_online',
        'price',
        'special_price',
        'url_key',
        'meta_keywords',
        'additional_attributes',
        'qty',
        'out_of_stock_qty',
        'is_in_stock',
        'related_skus',
        'configurable_variations',
        'configurable_variation_labels'
    ];
    fputcsv($new, $header, ";"); // We add the header to the new CSV
    $count = 0; // Counter what we will use to skip the first line always
    $products = [];
    while (($data = fgetcsv($file, 5000, ";", "\"")) !== false) {
        $count++;
        if ($count == 1) {
            continue; // If the counter equals 1 that means it's the first line, so we skip it
        }
        $product = [];
        $product['sku'] = $data[1];
        $product['attribute_set_code'] = $data[0];
        $product['product_type'] = 'simple';
        $product['categories'] = 'Default Category/' . $data[0];
        $product['name'] = $data[33] . '"';
        $product['description'] = $data[39];
        $product['weight'] = $data[28];
        $product['product_online'] = $data[3];
        $product['price'] = $data[7];
        $product['special_price'] = $data[6];
        $product['url_key'] = $data[37];
        $product['meta_keywords'] = $data[43];
        if ($data[0] === 'Llantas') {
            $product['additional_attributes'] = 'code=' . $data[23] . '|size=' . $data[74] . '|color=' . $data[75] . '|mounting=' . $data[76] . '|diameter=' . $data[77];
        } elseif ($data[0] === 'Neumaticos') {
            $product['additional_attributes'] = 'code=' . $data[23] . '|size=' . $data[78];
        } elseif ($data[0] === 'Kit muelles') {
            $product['additional_attributes'] = 'code=' . $data[23];
        } elseif ($data[0] === 'Suspensiones') {
            $product['additional_attributes'] = 'code=' . $data[23] . '|type=' . $data[81];
        } elseif ($data[0] === 'Separadores') {
            $product['additional_attributes'] = 'code=' . $data[23] . '|thickness=' . $data[79] . '|mounting=' . $data[80];
        }
        $product['qty'] = $data[24];
        $product['out_of_stock_qty'] = $data[25];
        $product['is_in_stock'] = $data[49];

        if ($product) { // If the product is not empty, we add it to the CSV
            fputcsv($new, $product, ";");
        }
    }

    fclose($file);

    $groups = [];

    $handle = fopen("prueba.csv", "r");
    if ($handle) {
        $lineNum = 1;
        while (($column = fgetcsv($handle,5000, ";")) !== false) { // We read the CSV line by line
            $attributes = $column[12]; // In the column 12 is the info we need, the attributes
            $code = substr($attributes, 5); // We remove the first part of the attribute string
            $code = before('|', $code); // We use this function to get the code without the rest of the attributes
            echo $code . "\n";

            $groups[$code] = [];
            if (in_array($code, array_keys($groups))) {
                $groups[$code][] = $lineNum;
            }

            $lineNum++;

            //Filtramos los indices vacíos [].
        }


        fclose($handle);
    } else {
        // Error opening the file.
    }

    echo "Finished execution." . "\n";
}
function before ($char, $string)
{
    return substr($string, 0, strpos($string, $char));
};

?>
