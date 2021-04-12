<?php
$fila = 2;
if (($gestor = fopen("Sin título 2.csv", "r")) !== FALSE) {
    echo "Executing script...."."\n";

    $new = fopen('prueba.csv', 'w');

    $header = array('sku,store_view_code,attribute_set_code,product_type,categories,product_websites,name,description,short_description,weight,product_online,tax_class_name,visibility,price,special_price,special_price_from_date,special_price_to_date,url_key,meta_title,meta_keywords,meta_description,created_at,updated_at,new_from_date,new_to_date,display_product_options_in,map_price,msrp_price,map_enabled,gift_message_available,custom_design,custom_design_from,custom_design_to,custom_layout_update,page_layout,product_options_container,msrp_display_actual_price_type,country_of_manufacture,additional_attributes,qty,out_of_stock_qty,use_config_min_qty,is_qty_decimal,allow_backorders,use_config_backorders,min_cart_qty,use_config_min_sale_qty,max_cart_qty,use_config_max_sale_qty,is_in_stock,notify_on_stock_below,use_config_notify_stock_qty,manage_stock,use_config_manage_stock,use_config_qty_increments,qty_increments,use_config_enable_qty_inc,enable_qty_increments,is_decimal_divided,website_id,deferred_stock_update,use_config_deferred_stock_update,related_skus,crosssell_skus,upsell_skus,hide_from_product_page,custom_options,bundle_price_type,bundle_sku_type,bundle_price_view,bundle_weight_type,bundle_values,associated_skus');
    fputs($new, implode(',',$header)."\n"); // Añadimos todas las columnas al nuevo archivo

    while(($datos = fgetcsv($gestor, 1000, ",")) !== FALSE){
        echo $datos[0];
    }
    echo "Finished execution."."\n";
}
?>
