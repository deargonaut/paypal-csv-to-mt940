<?php
namespace Deargonaut\PaypalCsvToMt940\Dialects;

abstract class Dialect implements DialectInterface
{
    public $iban;
    public $reference = "PAYPAL TO MT940";
    public $index = 00000;
    private $previousBalance = 0;
    private $calcStartBalance = 0;
    public $startBalance = 0;
    public $balance = 0;
    public $mutations = [];
    public $currency = 'EUR';

    public $template = <<<EOF
:20:{{reference}}
:25:{{iban}}
:28:{{index}}/1
:60F:{{startBalance}}
{{mutations}}
:62F:{{balance}}
EOF;

    public function __construct($iban, $reference, $startBalance = 0, $startDate = null, $index = 0)
    {
        $this->setStartBalance($startBalance, $startDate);
        $this->setIban($iban);
        $this->setIndex($index);
        $this->setReference($reference);
    }

    /**
     * Add reference for this transaction export.
     * 
     * @param string $reference The reference max 16 characters.
     * @return  $this
     */
    public function setReference($reference)
    {
        if(!is_string($reference) || strlen($reference) > 16)
            throw new \Exception("Reference is no string or too long (max. 16)");

        $this->reference = $reference;

        return $this;
    }

    /**
     * Set IBAN.
     * 
     * @param string    $iban               The IBAN.
     * @param bool      $ignoreIbanFormat   No sanity checks just add the value
     * 
     * @return          $this 
     */
    public function setIban($iban, $ignoreIbanFormat = false)
    {
        if($ignoreIbanFormat)
        {
            $this->iban = $iban;
            return $this;
        }

        if(strlen($iban) > 18)
            throw new \Exception("IBAN [{$iban}] has 18 alpha numeric characters (AA 00 AAAA 0000 0000 00).");

        if(strlen($iban) < 18)
            $iban .= \str_repeat('0', 18 - strlen($iban));

        $iban = strtoupper(str_replace(' ', '', $iban));
        $regex = "/[A-Z]{2}[0-9]{2}[A-Z]{4}[0-9]{10}/";
        if(\preg_match($regex, $iban) == FALSE)
            throw new \Exception ("IBAN [{$iban}] isn't valid.");

        $this->iban = $iban;
        return $this;
    }

    /**
     * Set the index of the transaction
     * 
     * @param int $index   The index
     * @return $this
     */
    public function setIndex($index)
    {
        if(!is_numeric($index))
            throw new \Exception("Index needs to be numeric.");
        if($index < 0 || $index > 99999)
            throw new \Exception("Index need to be between 0 - 9999");

        $this->index = sprintf("%05d", $index);

        return $this;
    }

    /**
     * Set the currency
     * 
     * @param string $currency Three letter currency (i.e. EUR)
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = substr(strtoupper($currency), 0, 3);

        return $this;
    }

    /**
     * Set the balance to start from
     * 
     * @param float $balance The amount to start with
     * @param string $date Computer readable string from which date (default today)
     * 
     * @return $this
     */
    public function setStartBalance($balance = 0, $date = null)
    {
        $balance = (float) str_replace(',', '.', $balance);
        
        if(is_null($date)) $date = time();
        else $date = strtotime($date);

       $this->startBalance = ($balance > 0 ? "C" : "D") . date('ymd', $date) . $this->makeAmount($balance, $this->currency);
       return $this;
    }


    /**
     * Create a MT940 amount with or without currency
     * 
     * @param float|string $amount The amount
     * @param string        $currency The three letters of the currency (i.e. EUR)
     * 
     * @return string The amount 
     */
    private function makeAmount($amount, $currency = '')
    {
        $currency   = \strtoupper($currency);
        $amount     = (float) str_replace(',', '.', $amount);
        return $currency . \number_format($amount, 2, '.', '');
    }

    /**
     * Add an mutation
     * 
     * @param string $date Computerparsable string of the date
     * @param float  $amount The amount of the mutation
     * @param 
     */
    public function addMutation($date, $amount, $balance, $description)
    {
        $time       = strtotime($date);
        $add        = (bool)($amount > 0);
        
        $mutation           = [];
        $mutation["_61"]    = date('ymd', $time) . date('md', $time) . ($add ? 'C' : 'D') . $this->makeAmount($amount) . "N526NOREF";
        $mutation["_86"]    = substr($description, 0, 386);

        $this->mutations[]  = $mutation;
        $this->balance      = $balance;

        return $this;
    }

    private function parseMutations()
    {
        $return = "";
        $first = true;
        foreach($this->mutations as $mutation)
        {
            $return .= (!$first ? "\n" : '') . ":61:{$mutation['_61']}\n";
            $return .= implode("\n", str_split(":86:{$mutation['_86']}", 65));
	        if($first) { $first = false; }
        }

        return $return;
    }

    /**
     * Generate the MT940
     */
    public function generate()
    {
        $template = $this->template;

        preg_match_all("/\{\{([a-zA-Z]*)\}\}/", $template, $matches);

        foreach($matches[1] as $match)
        {
            $result = '';
            $func = 'parse' . ucfirst($match);
            if(is_callable([$this, $func]))
                $result = $this->{$func}();

            else if(isset($this->$match))
                $result = $this->$match;


            $template = preg_replace("/\{\{" . $match . "\}\}/m", $result, $template);
        }

        return $template;
    }

    
}