<?php

use FQL\Enum\Operator;
use FQL\Query\Debugger;
use FQL\Stream\Json;

require __DIR__ . '/bootstrap.php';

$json = Json::open(__DIR__ . '/data/marketing-campaigns.json');

$topFieByRoi = $json->query()
    ->select('campaign_name, roi, revenue')
    ->orderBy('roi')->desc()
    ->orderBy('revenue')->desc()
    ->limit(5);

Debugger::echoSection('Top 5 marketing campaigns by ROI and revenue');
Debugger::inspectQuery($topFieByRoi, true);

$topRevenueChannel = $json->query()
    ->select('channel')
    ->sum('revenue')->as('total_revenue')
    ->round('total_revenue', 2)->as('total_revenue_rouded')
    ->groupBy('channel')
    ->orderBy('total_revenue')->desc()
    ->limit(1);

Debugger::echoSection('Top revenue channel');
Debugger::inspectQuery($topRevenueChannel);

$conversionRatioByCampaign = $json->query()
    ->select('type')
    ->avg('conversion_rate')->as('avg_conversion_rate')
    ->round('avg_conversion_rate', 2)->as('conversion_rate_rounded')
    ->groupBy('type')
    ->orderBy('avg_conversion_rate')->desc();

Debugger::echoSection('Conversion ratio by campaign type');
Debugger::inspectQuery($conversionRatioByCampaign, true);

$targetAudience = $json->query()
    ->select('target_audience')
    ->sum('revenue')->as('total_revenue')
    ->round('total_revenue')->as('total_revenue_rounded')
    ->groupBy('target_audience')
    ->orderBy('total_revenue')->desc();

Debugger::echoSection('Target audience by revenue');
Debugger::inspectQuery($targetAudience, true);

$overallBudgetAndIncomeFromOrganic = $json->query()
    ->select('channel')
    ->sum('budget')->as('total_budget')
    ->sum('revenue')->as('total_revenue')
    ->groupBy('channel')
    ->where('channel', Operator::EQUAL, 'organic');

Debugger::echoSection('Overall budget and income from organic channel');
Debugger::inspectQuery($overallBudgetAndIncomeFromOrganic);

$campaignsWithMoreThanFiftyPercentConversionRate = $json->query()
    ->select('campaign_name, conversion_rate')
    ->orderBy('conversion_rate')->desc()
    ->where('conversion_rate', Operator::GREATER_THAN, 0.5)
    ->and('conversion_rate', Operator::LESS_THAN, 0.9)
    ->limit(5);

Debugger::echoSection('Campaigns with more than 50% conversion rate');
Debugger::inspectQuery($campaignsWithMoreThanFiftyPercentConversionRate, true);

$mostSuccessfullyCampaignsByRevenueAndConversionRate = $json->query()
    ->select('campaign_name, revenue, conversion_rate')
    ->where('revenue', Operator::GREATER_THAN, 500000)
    ->and('conversion_rate', Operator::GREATER_THAN, 0.5)
    ->orderBy('revenue')->desc()
    ->limit(5);

Debugger::echoSection('Most successful campaigns by revenue and conversion rate');
Debugger::inspectQuery($mostSuccessfullyCampaignsByRevenueAndConversionRate, true);
Debugger::end();
