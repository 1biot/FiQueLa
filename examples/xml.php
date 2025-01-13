<?php

use FQL\Enum\Operator;
use FQL\Query\Debugger;
use FQL\Stream\Xml;

require __DIR__ . '/bootstrap.php';

$xml = Xml::open(__DIR__ . '/data/customers.xml');

$topTenCustomersBySpending = $xml->query()
    ->select('first_name.value')->as('firstName')
    ->select('last_name.value')->as('lastName')
    ->select('spent.value')->as('spent')
    ->from('customers.customer')
    ->orderBy('spent')->desc()
    ->limit(10);

Debugger::echoSection('Top 10 customers by spending');
Debugger::inspectQuery($topTenCustomersBySpending, true);

$customers = $xml->query()
    ->select('age.value')->as('age')
    ->from('customers.customer');

$averageCustomerAge = $customers->execute()->avg('age');

Debugger::echoSection('Average customer age');
Debugger::echoLineNameValue('Average age', $averageCustomerAge);

$customersCountByGender = $xml->query()
    ->select('gender.value')->as('gender')
    ->count()->as('count')
    ->from('customers.customer')
    ->groupBy('gender.value')
    ->sortBy('count')->asc();

Debugger::echoSection('Customers count by gender');
Debugger::inspectQuery($customersCountByGender, true);

$ageVsSpent = $xml->query()
    ->select('age.value')->as('customerAge')
    ->avg('spent.value')->as('avgSpent')
    ->round('avgSpent', 2)->as('avgSpentRounded')
    ->from('customers.customer')
    ->groupBy('age.value')
    ->orderBy('avgSpent')->desc()
    ->limit(10);

Debugger::echoSection('Age vs spent');
Debugger::inspectQuery($ageVsSpent, true);

Debugger::end();
