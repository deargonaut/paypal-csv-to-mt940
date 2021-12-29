<?php
namespace Deargonaut\PaypalCsvToMt940;

use Deargonaut\PaypalCsvToMt940\Dialects\AbnAmro;
use Deargonaut\PaypalCsvToMt940\Models\Mutation;

class PaypalCsvToMt940
{
    private $csv_file_location;
    private $csv_array = [];
    private $options = [
        'filename'  => 'export.sta',
        'overwrite' => true,
        'location'  => './',
        'language'  => 'nl'
    ];

    private $parser;

    private $mutations = [

    ];


    public function __construct($csv, $language = 'NL', $options = [])
    {
	    $this->setOptions($options);
        $this->loadFile($csv);
    }


    public function setDialect($dialect, ...$args)
    {
        $this->parser = new $dialect(...$args);
    }


    public function loadFile($csv)
    {
        if(!file_exists($csv))
            throw new \Exception("File [{$csv}] doesn't exist.");

        if(!\is_readable($csv))
            throw new \Exception("Can't open file. Check permissions.");

        $this->csv_file_location = $csv;

        // Add a trimming newline... Don't know why. TODO
        file_put_contents($this->csv_file_location, "\n" .  trim(file_get_contents($this->csv_file_location)));
        $csv_array = array_map('str_getcsv', file($this->csv_file_location));

        array_shift($csv_array);
        array_walk($csv_array, function(&$a) use ($csv_array) {
            $a = array_combine($csv_array[0], $a);
        });
        array_shift($csv_array); # remove column header

        $this->csv_array = $csv_array;
        $this->parse();

    }


    private function parse()
    {
        $f = include __DIR__ . '/HeaderTranslations.php';
        if(!isset($f[$this->options['language']]))
            $f = $f['nl'];
        else
            $f = $f[$this->options['language']];

        foreach($this->csv_array as $m)
        {
            // TODO: Make 'currency' an option
            if($m[$f['currency']] != 'EUR') continue;
//            if($m[$f['status']] != $f['completed']) continue;

            $mutation = new Mutation();
            $mutation->setAmount($m[$f['net']]);
            $mutation->date = (\DateTime::createFromFormat("d-m-Y H:i:s e", $m[$f['date']] . ' ' . $m[$f['time']] . ' ' . $m[$f['timezone']]))->format('Y-m-d H:i:s e');
            $mutation->description = $m[$f['item_title']] . ' ' . $m[$f['name']] . ' ' . $m[$f['type']] .  ' ' . $m[$f['transaction_reference']] . ' ' . $m[$f['object_reference']] . ' ' . $m[$f['invoice_number']] . ' ' . $m[$f['subject']];
            $mutation->setBalance($m[$f['balance']]);

            $this->mutations[] = $mutation;
        }
    }

    public function setOptions($options = [])
    {
        if(isset($options['filename']))
        {
            if(!is_string($options['filename']))
                throw new \Exception("Filename needs to be a string.");
            
            $fn = explode(DIRECTORY_SEPARATOR, $options['filename']);
            $this->options['filename'] = array_pop($fn);
        }

        if(isset($options['overwrite']))
        {
            if(!is_bool($options['overwrite']))
                throw new \Exception("Overwrite needs to be boolean");
            
            $this->options['overwrite'] = $options['overwrite'];
        }

        if(isset($options['location']))
        {
            if(!is_dir($options['location']))
                throw new \Exception("Location [{$options['location']}] is not a directory.");
            if(!\is_writeable($options['location']))
                throw new \Exception("Location is not writeable.");

            $this->options['location'] = $options['location'];
        }
        if(isset($options['language']))
        {
            $this->options['language'] = substr(strtolower($options['language']), 0, 2);
        }
    }

    public function save($outputAsWell = false, $options = [])
    {
        $this->setOptions($options);

        if(file_exists($this->options['location'] . $this->options['filename']) && !$this->options['overwrite'])
            throw new \Exception('File exists already');

        foreach($this->mutations as $m)
        {
            $this->parser->addMutation($m->getDate(), $m->amount, $m->balance, $m->getDescription());
        }

        $mt940 = $this->parser->generate();
        \file_put_contents($this->options['location'] . $this->options['filename'], $mt940);

        if($outputAsWell)
            return $mt940;
        else
        	return true;
        
    }
}
