<?php
// Let's test the JSON decode
$items_json = '[{"title":"Test","content":"Test Content","url":"http://test.com"}]';
$items = json_decode($items_json, true);
var_dump($items);
