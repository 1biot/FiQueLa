<?php

require __DIR__ . '/bootstrap.php';

use FQL\Enum\Operator;
use FQL\Query\Debugger;
use FQL\Results\InMemory;
use FQL\Results\Stream;
use FQL\Stream\Csv;

$googleSheetFile = __DIR__ . '/data/google-sheet.csv';
if (file_exists($googleSheetFile) === false) {
    $csv = file_get_contents('https://drive.google.com/uc?id=1g4wqEIsKyiBWeCAwd0wEkiC4Psc4zwFu&export=download');
    file_put_contents($googleSheetFile, $csv);
    Debugger::dump('Downloaded CSV from Google Sheets');
    die;
}

$googleSheet = Csv::open($googleSheetFile)
    ->useHeader(true);


$query = $googleSheet->query()
    ->selectAll();
dump(implode(' | ', array_keys($query->execute(Stream::class)->fetch())));

Debugger::memoryUsage();
Debugger::memoryPeakUsage();
Debugger::echoLine('==============================', 0);


$query = $googleSheet->query()
    ->select('Country')
    ->select('Founded')
    ->count('Country')->as('CountryCount')
    ->min('Founded')->as('minFounded')
    ->max('Founded')->as('maxFounded')
    ->groupBy('Country')
    ->having('CountryCount', Operator::GREATER_THAN_OR_EQUAL, 400)
    ->and('CountryCount', Operator::LESS_THAN_OR_EQUAL, 450);
dump($query->test());
$yearWhenMostCompaniesFounded = $query->execute(Stream::class);

Debugger::memoryUsage();
Debugger::memoryPeakUsage();
Debugger::echoLine('==============================', 0);

dump($yearWhenMostCompaniesFounded->count());
dump($yearWhenMostCompaniesFounded->sum('Founded'));
dump($yearWhenMostCompaniesFounded->avg('Founded'));
dump($yearWhenMostCompaniesFounded->max('Founded'));
dump($yearWhenMostCompaniesFounded->min('Founded'));

Debugger::memoryUsage();
Debugger::memoryPeakUsage();

foreach ($yearWhenMostCompaniesFounded->getIterator() as $row) {

}

Debugger::end();
die;

dump(
    sprintf(
        "Year when most companies created: %s (%d)",
        $yearWhenMostCompaniesFounded['Founded'],
        $yearWhenMostCompaniesFounded['createdCompanies']
    )
);

Debugger::memoryUsage();
Debugger::memoryPeakUsage();
Debugger::echoLine('==============================', 0);
die;


$mostEmployees = $googleSheet->query()
    ->select('Name')
    ->select('Number of employees')->as('employees')
    ->orderBy('employees')->desc()
    ->limit(1)
    ->execute(Stream::class);

Debugger::memoryUsage();
Debugger::memoryPeakUsage();
Debugger::echoLine('==============================', 0);

dump(
    sprintf(
        "Company with most of employees: %s (%d)",
        $mostEmployees->fetchSingle('Name'),
        $mostEmployees->fetchSingle('employees')
    )
);
unset($mostEmployees);

$oldestCompany = $googleSheet->query()
    ->select('Name')
    ->select('Founded')
    ->orderBy('Founded')->asc()
    ->limit(1)
    ->execute(Stream::class);

Debugger::memoryUsage();
Debugger::memoryPeakUsage();
Debugger::echoLine('==============================', 0);

dump(
    sprintf(
        "Oldest company: %s (%d)",
        $oldestCompany->fetchSingle('Name'),
        $oldestCompany->fetchSingle('Founded')
    )
);
unset($oldestCompany);


$sql = <<<SQL
SELECT Name, Founded
FROM *
ORDER BY Founded DESC
LIMIT 1
SQL;

$newestCompany = $googleSheet->sql($sql);

Debugger::memoryUsage();
Debugger::memoryPeakUsage();
Debugger::echoLine('==============================', 0);

dump(
    sprintf(
        "Newest company: %s (%d)",
        $newestCompany->fetchSingle('Name'),
        $newestCompany->fetchSingle('Founded')
    )
);

unset($newestCompany);

$yearWhenMostCompaniesFounded = $googleSheet->query()
    ->select('Founded')
    ->select('Country')
    ->count('Index')->as('createdCompanies')
    ->groupBy('Country')
    ->groupBy('Founded')
    ->orderBy('createdCompanies')->desc()
    ->execute(Stream::class)->fetch();

Debugger::memoryUsage();
Debugger::memoryPeakUsage();
Debugger::echoLine('==============================', 0);

dump(
    sprintf(
        "Year when most companies created: %s (%d)",
        $yearWhenMostCompaniesFounded['Founded'],
        $yearWhenMostCompaniesFounded['createdCompanies']
    )
);

unset($yearWhenMostCompaniesFounded);

Debugger::end();
die;

$utf8 = Csv::open(__DIR__ . '/data/products-utf-8.csv')
    ->useHeader(true);

$windows1250 = Csv::open(__DIR__ . '/data/products-w-1250.csv')
    ->setInputEncoding('windows-1250')
    ->setDelimiter(';')
    ->useHeader(true);

$query = $windows1250->query()
    ->select('ean')
    ->select('defaultCategory')
    ->explode('defaultCategory', ' > ')->as('categoryArray')
    ->select('price')
    ->round('price', 2)->as('price_rounded')
    ->modulo('price', 100)->as('modulo_100')
    ->modulo('price', 54)->as('modulo_54')
    ->groupBy('defaultCategory');

Debugger::inspectQuery($query);
Debugger::benchmarkQuery($query);

Debugger::end();
