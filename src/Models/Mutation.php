<?php
namespace Deargonaut\PaypalCsvToMt940\Models;

class Mutation
{
    public  $amount = 0.00,
            $currency,
            $date,
            $description,
            $balance = 0.00;


    public function getDate()
    {
        return strftime('r', strtotime($this->date ?: time()));
    }

    public function getDescription()
    {
        return $this->description ?: '';
    }

    public function parseToFloat($amount)
    {
        if(strstr($amount, ','))
        {
            if(strstr($amount, '.'))
            {
                $exp = explode(',', $amount);
                if(strstr($exp[0], '.'))
                {
                    $amount = (float) str_replace(',', '.', $amount);
                    $amount = (float) str_replace('.', '', $amount);
                }
                else if(strstr($exp[1], '.'))
                {
                    $amount = (float) str_replace(',', '', $amount);
                }
                else if(count($exp) > 2)
                {
                    $amount = (float) str_replace(',', '', $amount);
                }
            }
            else {
                $amount = (float) str_replace(',', '.', $amount);
            }
        }

        return $amount;
    }

    public function setAmount($amount)
    {
        $this->amount = $this->parseToFloat($amount);
    }

    public function setBalance($amount)
    {
        $this->balance = $this->parseToFloat($amount);
    }
}