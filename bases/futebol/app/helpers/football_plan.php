<?php
declare(strict_types=1);

function footballPlanIsFree(): bool
{
    global $project;
    $name=(string)($project['plan_name']??'');
    $cycle=strtolower((string)($project['billing_cycle']??''));
    if($name===''&&$cycle===''&&function_exists('projectPlanName'))$name=projectPlanName();
    if($cycle==='free'||preg_match('/gr(?:a|\x{00E1})tis|free/iu',$name))return true;
    return !preg_match('/\b(start|plus)\b/i',$name)&&!in_array($cycle,['monthly','annual'],true);
}

function footballPlanModuleVisible(string $slug): bool
{
    return $slug!=='classificacao'||!footballPlanIsFree();
}
