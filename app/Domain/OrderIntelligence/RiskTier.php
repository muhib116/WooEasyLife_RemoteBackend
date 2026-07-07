<?php

namespace App\Domain\OrderIntelligence;

enum RiskTier: string
{
    case Safe = 'safe';
    case Caution = 'caution';
    case Risky = 'risky';
    case Unknown = 'unknown';
}
