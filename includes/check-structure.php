<?php
require_once('../../../../../wp-load.php');
global $wpdb;

echo "=== ESTRUTURA TABELA pedidos_basicos ===\n\n";
$results = $wpdb->get_results("DESCRIBE pedidos_basicos");

printf("%-30s %-25s %-10s %-10s %-20s\n", "CAMPO", "TIPO", "NULL", "KEY", "DEFAULT");
echo str_repeat("-", 95) . "\n";

foreach($results as $row) {
    printf("%-30s %-25s %-10s %-10s %-20s\n", 
        $row->Field, 
        $row->Type, 
        $row->Null, 
        $row->Key,
        $row->Default
    );
}

echo "\n=== TOTAL DE CAMPOS: " . count($results) . " ===\n";
?>
