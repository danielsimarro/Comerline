<?php
$brands = [
    'Alfa Romeo','Audi','BMW','Chevrolet','Chrysler','Citroen','Dacia','Fiat','Ford','Dodge','Honda','Hyundai','Kia','Jaguar','Lancia','Jeep',
    'Land Rover','Lexus','Mazda','Mercedes','Mini','Mitsubishi','Nissan','Opel','Peugeot','Porsche','Renault','Seat','Saab','Skoda','Smart','Subaru',
    'Toyota','Volkswagen','Volvo'
];
if (($file = fopen("Productos.csv", "r")) !== false) {
    echo "Executing script...." . "\n";

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
        'configurable_variations',
        'visibility',
        'product_websites',
        'base_image',
        'small_image',
        'thumbnail_image',
        'swatch_image',
    ];
    fputcsv($new, $header, ";"); // We add the header to the new CSV
    $count = 0; // Counter what we will use to skip the first line always
    $skus = [];
    while (($data = fgetcsv($file, 5000, ";", "\"")) !== false) {
        $count++;
        if ($count == 1) {
            continue; // If the counter equals 1 that means it's the first line, so we skip it
        }
        $product = [];
        if (in_array($data[1], $skus)) {
            $product['sku'] = $data[1] . '-' . $count;
        } else {
            $skus[] = $data[1];
            $product['sku'] = $data[1];
        }
        $product['attribute_set_code'] = $data[0];
        $product['product_type'] = 'simple';
        $product['categories'] = 'Default Category/' . $data[0];
        foreach ($brands as $brand){
            if(strpos(strtolower($data[33]), strtolower($brand)) !== false){
                $product['categories'] .= '$#$Default Category/' . $brand;
            }
        }
        $product['name'] = $data[33];
        $product['description'] = $data[39];
        $product['weight'] = $data[28];
        $product['product_online'] = $data[3];
        if ($data[7]) {
            if (strpos($data[7], ',') !== false) {
                $product['price'] = str_replace(',', '.', $data[7]);
            } else {
                $product['price'] = $data[7] . '.00';
            }
        } else {
            if (strpos($data[6], ',') !== false) {
                $product['price'] = str_replace(',', '.', $data[6]);
            } else {
                $product['price'] = $data[6] . '.00';
            }
        }
        if (substr_count($product['price'], '.') > 1) {
            $pos = strpos($product['price'], '.');
            if ($pos !== false) {
                $newstring = substr_replace($product['price'], '', $pos, strlen('.'));
                $product['price'] = $newstring;
            }
        }
        if ($data[6]) {
            if (strpos($data[6], ',') !== false) {
                $product['special_price'] = str_replace(',', '.', $data[6]);
            } else {
                $product['special_price'] = $data[6] . '.00';
            }
        }
        if (substr_count($product['special_price'], '.') > 1) {
            $pos = strpos($product['special_price'], '.');
            if ($pos !== false) {
                $newstring = substr_replace($product['special_price'], '', $pos, strlen('.'));
                $product['special_price'] = $newstring;
            }
        }
        $product['url_key'] = str_replace(' ', '-', $data[33]) . "-" . $count;
        $product['meta_keywords'] = $data[43];
        if ($data[0] === 'Llantas') {
            $product['additional_attributes'] = '';
            if ($data[23]) {
                $product['additional_attributes'] .= 'code=' . $data[23];
            }
            if ($data[74]) {
                $product['additional_attributes'] .=  '$#$size=' . $data[74];
            }
            if ($data[75]) {
                $product['additional_attributes'] .=  '$#$color=' . $data[75];
            }
            if ($data[76]) {
                $product['additional_attributes'] .=  '$#$mounting=' . $data[76];
            }
            if ($data[77]) {
                $product['additional_attributes'] .=  '$#$diameter=' . $data[77];
            }
            if ($data[21]) {
                $product['additional_attributes'] .=  '$#$manufacturer=' . $data[21];
            }
        } elseif ($data[0] === 'Neumaticos') {
            $product['additional_attributes'] = '';
            if ($data[23]) {
                $product['additional_attributes'] .= 'code=' . $data[23];
            }
            if ($data[78]) {
                $product['additional_attributes'] .=  '$#$size=' . $data[78];
                $diameter = explode('R', $data[78]);
                $product['additional_attributes'] .=  '$#$diameter=' . substr($diameter[1], 0, 2) . '"';
            }
            if ($data[21]) {
                $product['additional_attributes'] .=  '$#$manufacturer=' . $data[21];
            }
        } elseif ($data[0] === 'Kit muelles') {
            $product['additional_attributes'] = '';
            if ($data[23]) {
                $product['additional_attributes'] .= 'code=' . $data[23];
            }
            if ($data[21]) {
                $product['additional_attributes'] .=  '$#$manufacturer=' . $data[21];
            }
        } elseif ($data[0] === 'Suspensiones') {
            $product['additional_attributes'] = '';
            if ($data[23]) {
                $product['additional_attributes'] .= 'code=' . $data[23];
            }
            if ($data[81]) {
                $product['additional_attributes'] .=  '$#$type=' . $data[81];
            }
            if ($data[21]) {
                $product['additional_attributes'] .=  '$#$manufacturer=' . $data[21];
            }
        } elseif ($data[0] === 'Separadores') {
            $product['additional_attributes'] = '';
            if ($data[23]) {
                $product['additional_attributes'] .= 'code=' . $data[23];
            }
            if ($data[79]) {
                $product['additional_attributes'] .=  '$#$thickness=' . $data[79];
            }
            if ($data[80]) {
                $product['additional_attributes'] .=  '$#$mounting=' . $data[80];
            }
            if ($data[21]) {
                $product['additional_attributes'] .=  '$#$manufacturer=' . $data[21];
            }
        }
        $product['qty'] = $data[24];
        $product['out_of_stock_qty'] = $data[25];
        $product['is_in_stock'] = $data[49];

        if ($product && $product['name']) { // If the product is not empty as has a name, we add it to the CSV
            fputcsv($new, $product, ";");
        }
        if (file_exists('../var/import/images/' . clean($product['name']) . '.jpg') === false) {
            if ($data[5]) {
                save_image($data[5], clean($product['name']) . '.jpg');
                echo 'Image ' . clean($product['name']) . '.jpg saved!' . "\n";
            }
        }
    }

    fclose($file);

    $groups = [];

    $handle = fopen("prueba.csv", "r+");
    if ($handle) {
        $lineNum = 1;
        while (($column = fgetcsv($handle, 5000, ";")) !== false) { // We read the CSV line by line
            $attributes = $column[12]; // In the column 12 is the info we need, the attributes
            $code = before('$#$', $column[12]); // We use this function to get the code without the rest of the attributes
            $groups[substr($code, 5)];
            if ($code === before('$#$', $column[12])) {
                $groups[substr($code, 5)][] = $lineNum . ";sku=" . $column[0] . "$#$" . after('$#$', $column[12]) . ";Catalog, Search"; // With this, we save the product code on the groups array and it's SKU
            }
            $lineNum++;
        }
        fclose($handle);
    } else {
        // Error opening the file.
    }

    // Now with the code and the SKU, for each product we have saved on the array, we have to change
    // it's product type to configurable and add to the configurable variations the SKUs
    $productsUpdate = [];
    foreach ($groups as $group) {
        if ($group[0] === '2;sku=1$#$;Catalog, Search' || $group[0] === '1;sku=sku$#$;Catalog, Search') {
            echo "Skipping line..." . "\n"; // We skip the first two lines because they are not correct
        } else {
            $firstProduct = explode(';', $group[0]);
            $lineProduct = $firstProduct[0]; //We pick the line where the first product has the same code
            $variations = '';
            for ($i = 1; $i < count($group); $i++) {
                $explode = explode(';', $group[$i]); // We pick the variations of the rest of the products
                $variationsRaw = explode('$#$', $explode[1]);
                for ($j = 0; $j < count($variationsRaw) - 1; $j++) {
                    $variations .= $variationsRaw[$j] . "$#$"; // Adding the variations to the same variable to use it later
                }
                $variations = substr($variations, 0, -3);
                $variations .= '|';
                $visibility = $explode[2]; // Adding the visibility
            }
            $handle = fopen("prueba.csv", "r"); // We open the last CSV we created
            $lineNum = 1;
            while (($column = fgetcsv($handle, 5000, ";")) !== false) {
                if ($lineNum !== intval($lineProduct)) {
                    $lineNum++; // If the line is not the one we want to modify, we skip it
                } elseif ($column[1] === 'Neumaticos' || $column[1] === "Kit muelles") {
                    $lineNum++; // If the line is a wheel or a springs kit, we skip the line and still make it simple
                } else {
                    $updated = [];
                    $updated['sku'] = $column[0];
                    $updated['attribute_set_code'] = $column[1];
                    $updated['product_type'] =  'configurable';
                    $updated['categories'] = $column[3];
                    $updated['name'] = $column[4];
                    $updated['description'] = $column[5];
                    $updated['weight'] = $column[6];
                    $updated['product_online'] = $column[7];
                    $updated['price'] = $column[8];
                    $updated['special_price'] = $column[9];
                    $updated['url_key'] = $column[10];
                    $updated['meta_keywords'] = $column[11];
                    $updated['additional_attributes'] = "";
                    $updated['qty'] = $column[13];
                    $updated['out_of_stock_qty'] = $column[14];
                    $updated['is_in_stock'] = $column[15];
                    $updated['configurable_variations'] = substr($variations, 0, -1);
                    $updated['visibility'] = $visibility;
                    if ($updated) { // If the product is not empty, we add it to the CSV
                        $productsUpdate[$lineNum] = $updated;
                    }
                    echo "Line " . $lineNum . " modified succesfully!\n";
                    $lineNum++;
                }
            }
            fclose($handle);
        }
    }

    $simpleProductsNotVisible = [];

    foreach ($productsUpdate as $productUpdate) {
        $productExplode = explode('|', $productUpdate['configurable_variations']);
        foreach ($productExplode as $pe) {
            $skuExplode = explode('$', $pe);
            $sku = substr($skuExplode[0], 4);
            if ($sku) {
                $simpleProductsNotVisible[] = $sku;
            }
        }
    }

    $handle = fopen("prueba.csv", "r+"); // We open the last CSV we created
    $updatedCSV = fopen('Productos Magento.csv', 'w'); // We create a new CSV with the info that we will update
    $lineNum = 1;
    while (($column = fgetcsv($handle, 5000, ";")) !== false) {
        $additionalAttributes = $column[12];
        if ($lineNum != 1){
            $column[12] = after('$#$', $additionalAttributes);
        }
        if ($productsUpdate[$lineNum]) {
            $column[2] = $productsUpdate[$lineNum]['product_type'];
            $column[12] = $productsUpdate[$lineNum]['additional_attributes'];
            $column[16] = $productsUpdate[$lineNum]['configurable_variations'];
            $column[17] = $productsUpdate[$lineNum]['visibility'];
            $column[18] = 'base';
            $column[19] = clean($column[4]) . '.jpg';
            $column[20] = $column[19];
            $column[21] = $column[19];
            $column[22] = '';
            fputcsv($updatedCSV, $column, ";");
            $lineNum++;
        } elseif ($lineNum === 1) {
            fputcsv($updatedCSV, $column, ";");
            $lineNum++;
        } else {
            $column[16] = "";
            foreach ($simpleProductsNotVisible as $notVisible) {
                if ($column[0] === $notVisible) {
                    $column[17] = "Not Visible Individually";
                    break;
                } else {
                    $column[17] = "Catalog, Search";
                }
            }
            $column[18] = 'base';
            $column[19] = clean($column[4]) . '.jpg';
            $column[20] = $column[19];
            $column[21] = $column[19];
            $column[22] = $column[19];
            fputcsv($updatedCSV, $column, ";");
            $lineNum++;
        }
    }
    fclose($updatedCSV);
    fclose($handle);
    unlink('prueba.csv');

    echo "Finished execution." . "\n";
}

function before($char, $string)
{
    return substr($string, 0, strpos($string, $char));
}

function after($char, $string)
{
    if (!is_bool(strpos($string, $char))) {
        return substr($string, strpos($string, $char)+strlen($char));
    }
}

function save_image($img, $fullpath)
{
    $ch = curl_init($img);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
    $rawdata=curl_exec($ch);
    curl_close($ch);
    if (file_exists($fullpath)) {
        unlink($fullpath);
    }
    $fp = fopen('../var/import/images/' . $fullpath, 'x');
    fwrite($fp, $rawdata);
    fclose($fp);
}

function clean($string) {
    $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.

    return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
}

function str_replace_first($from, $to, $content)
{
    $from = '/'.preg_quote($from, '/').'/';

    return preg_replace($from, $to, $content, 1);
}
