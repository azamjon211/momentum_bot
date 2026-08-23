<?php
namespace App\Pipelines\Order;

class GeneratingInvoice
{
    public function handle(array $order, \Closure $next ){
        echo "Generating invoice <br/> ";
        $order['invoice_id'] = rand(1000, 9999);
        return $next($order);
    }
}
