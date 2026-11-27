<?php

namespace App\Enums;

enum NotificationType: string
{
    case PriceChange = 'PRICE_CHANGE';
    case NewStation = 'NEW_STATION';
    case SystemAlert = 'SYSTEM_ALERT';
    case Maintenance = 'MAINTENANCE';
    case News = 'NEWS';
    case Marketing = 'MARKETING';
    case Emergency = 'EMERGENCY';
    case Custom = 'CUSTOM';
}
