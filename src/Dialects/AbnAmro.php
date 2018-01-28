<?php
namespace Deargonaut\PaypalCsvToMt940\Dialects;

class AbnAmro extends Dialect
{
    
    public function __construct($iban, $reference = 'ABN AMRO BANK NV', $startBalance = 0, $startDate = null, $index = 0)
    {
        parent::__construct($iban, $reference, $startBalance, $startDate, $index);

        $this->template = "ABNANL2A\n940\nABNANL2A\n" . $this->template . "\n-\n"; 
    }
}