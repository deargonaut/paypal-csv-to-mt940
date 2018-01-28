<?php

require __DIR__ . '/../../vendor/autoload.php';

$p = new Deargonaut\PaypalCsvToMt940\PaypalCsvToMt940('test.csv');
$p->setDialect(\Deargonaut\PaypalCsvToMt940\Dialects\AbnAmro::class, 'NL00PAYP0123456789');

die($p->save(true));