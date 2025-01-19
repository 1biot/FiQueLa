<?php

use FQL\Enum\Operator as Op;
use FQL\Query;

require __DIR__ . '/bootstrap.php';

try {
    $json = Query\Provider::fromFile('./examples/data/marketing-campaigns.json');

    $topFiveByRoi = $json->query()
        ->select('campaign_name, roi, revenue')
        ->orderBy('roi')->desc()
        ->orderBy('revenue')->desc()
        ->limit(5);

    Query\Debugger::echoSection('Top 5 marketing campaigns by ROI and revenue');
    Query\Debugger::inspectQuery($topFiveByRoi, true);

    $topRevenueChannel = $json->query()
        ->select('channel')
        ->sum('revenue')->as('total_revenue')
        ->round('total_revenue', 2)->as('total_revenue_rouded')
        ->groupBy('channel')
        ->orderBy('total_revenue')->desc()
        ->limit(1);

    Query\Debugger::echoSection('Top revenue channel');
    Query\Debugger::inspectQuery($topRevenueChannel);

    $conversionRatioByCampaign = $json->query()
        ->select('type')
        ->avg('conversion_rate')->as('avg_conversion_rate')
        ->round('avg_conversion_rate', 2)->as('conversion_rate_rounded')
        ->groupBy('type')
        ->orderBy('avg_conversion_rate')->desc();

    Query\Debugger::echoSection('Conversion ratio by campaign type');
    Query\Debugger::inspectQuery($conversionRatioByCampaign, true);

    $targetAudience = $json->query()
        ->select('target_audience')
        ->sum('revenue')->as('total_revenue')
        ->round('total_revenue')->as('total_revenue_rounded')
        ->groupBy('target_audience')
        ->orderBy('total_revenue')->desc();

    Query\Debugger::echoSection('Target audience by revenue');
    Query\Debugger::inspectQuery($targetAudience, true);

    $overallBudgetAndIncomeFromOrganic = $json->query()
        ->select('channel')
        ->sum('budget')->as('total_budget')
        ->sum('revenue')->as('total_revenue')
        ->groupBy('channel')
        ->where('channel', Op::EQUAL, 'organic');

    Query\Debugger::echoSection('Overall budget and income from organic channel');
    Query\Debugger::inspectQuery($overallBudgetAndIncomeFromOrganic);

    $campaignsWithMoreThanFiftyPercentConversionRate = $json->query()
        ->select('campaign_name, conversion_rate')
        ->orderBy('conversion_rate')->desc()
        ->where('conversion_rate', Op::GREATER_THAN, 0.5)
        ->and('conversion_rate', Op::LESS_THAN, 0.9)
        ->limit(5);

    Query\Debugger::echoSection('Campaigns with more than 50% conversion rate');
    Query\Debugger::inspectQuery($campaignsWithMoreThanFiftyPercentConversionRate, true);

    $mostSuccessfullyCampaignsByRevenueAndConversionRate = $json->query()
        ->select('campaign_name, revenue, conversion_rate')
        ->where('revenue', Op::GREATER_THAN, 500000)
        ->and('conversion_rate', Op::GREATER_THAN, 0.5)
        ->orderBy('revenue')->desc()
        ->limit(5);

    Query\Debugger::echoSection('Most successful campaigns by revenue and conversion rate');
    Query\Debugger::inspectQuery($mostSuccessfullyCampaignsByRevenueAndConversionRate, true);
    Query\Debugger::end();
} catch (\Exception $e) {
    Query\Debugger::echoSection($e::class);
    Query\Debugger::echoLine($e->getMessage());
    Query\Debugger::dump($e->getTraceAsString());
    Query\Debugger::split();
}
