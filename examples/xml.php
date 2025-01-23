<?php

use FQL\Query;
use FQL\Stream;

require __DIR__ . '/bootstrap.php';

try {
    $xml = Stream\Provider::fromFile('./examples/data/customers.xml');

    $topTenCustomersBySpending = $xml->query()
        ->select('first_name.value')->as('firstName')
        ->select('last_name.value')->as('lastName')
        ->select('spent.value')->as('spent')
        ->from('customers.customer')
        ->orderBy('spent')->desc()
        ->limit(10);

    Query\Debugger::echoSection('Top 10 customers by spending');
    Query\Debugger::inspectQuery($topTenCustomersBySpending, true);

    $customers = $xml->query()
        ->select('age.value')->as('age')
        ->from('customers.customer');

    $averageCustomerAge = $customers->execute()->avg('age');

    Query\Debugger::echoSection('Average customer age');
    Query\Debugger::echoLineNameValue('Average age', $averageCustomerAge);

    $customersCountByGender = $xml->query()
        ->select('gender.value')->as('gender')
        ->count()->as('count')
        ->from('customers.customer')
        ->groupBy('gender.value')
        ->sortBy('count')->asc();

    Query\Debugger::echoSection('Customers count by gender');
    Query\Debugger::inspectQuery($customersCountByGender, true);

    $ageVsSpent = $xml->query()
        ->select('age.value')->as('customerAge')
        ->avg('spent.value')->as('avgSpent')
        ->round('avgSpent', 2)->as('avgSpentRounded')
        ->from('customers.customer')
        ->groupBy('age.value')
        ->orderBy('avgSpent')->desc()
        ->limit(10);

    Query\Debugger::echoSection('Age vs spent');
    Query\Debugger::inspectQuery($ageVsSpent, true);

    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoException($e);
}
