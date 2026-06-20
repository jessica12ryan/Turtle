<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case HVAC = 'hvac';
    case Appliances = 'appliances';
    case Structural = 'structural';
    case PestControl = 'pest_control';
    case GeneralRepair = 'general_repair';
    case Other = 'other';
}
