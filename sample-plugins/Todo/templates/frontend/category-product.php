<?php
get_header();
$category = get_query_var('category_slug');
$product = get_query_var('product_slug');

// Your logic here
echo "<h1>Product: {$product} in Category: {$category}</h1>";
get_footer();