<?php


namespace App\Services;


use Illuminate\Support\Arr;


class QuotationCalculator
{
/**
* $payload:
* - lines: [ ['qty','unit_price','discount_type','discount_value','name','description','unit'], ... ]
* - tax_percent
* - total_discount_type, total_discount_value
*/
public function compute(array $payload): array
{
$lines = Arr::get($payload, 'lines', []);
$taxPercent = (float) Arr::get($payload, 'tax_percent', 0);
$totalDiscType = Arr::get($payload, 'total_discount_type', 'amount');
$totalDiscValue = (float) Arr::get($payload, 'total_discount_value', 0);


$linesSubtotal = 0;
$computedLines = [];


foreach ($lines as $line) {
$qty = (float) Arr::get($line, 'qty', 0);
$price = (float) Arr::get($line, 'unit_price', 0);
$dt = Arr::get($line, 'discount_type', 'amount');
$dv = (float) Arr::get($line, 'discount_value', 0);


$lineSubtotal = $qty * $price;


$lineDiscAmount = $dt === 'percent'
? round($lineSubtotal * max(min($dv, 100), 0) / 100, 2)
: min(max($dv, 0), $lineSubtotal);


$lineTotal = max($lineSubtotal - $lineDiscAmount, 0);


$computedLines[] = array_merge($line, [
'line_subtotal' => $lineSubtotal,
'discount_amount' => $lineDiscAmount,
'line_total' => $lineTotal,
]);


$linesSubtotal += $lineTotal;
}


$totalDiscAmount = $totalDiscType === 'percent'
? round($linesSubtotal * max(min($totalDiscValue, 100), 0) / 100, 2)
: min(max($totalDiscValue, 0), $linesSubtotal);


$taxableBase = max($linesSubtotal - $totalDiscAmount, 0);
$taxAmount = round($taxableBase * max($taxPercent, 0) / 100, 2);
$grandTotal = $taxableBase + $taxAmount;


return [
'lines' => $computedLines,
'lines_subtotal' => $linesSubtotal,
'total_discount_type' => $totalDiscType,
'total_discount_value' => $totalDiscValue,
'total_discount_amount' => $totalDiscAmount,
'taxable_base' => $taxableBase,
'tax_percent' => $taxPercent,
'tax_amount' => $taxAmount,
'total' => $grandTotal,
];
}
}