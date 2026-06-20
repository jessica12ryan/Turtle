<?php

namespace App\Enums;

enum UserRole: string
{
    case Landlord = 'landlord';
    case PropertyManager = 'property_manager';
    case Maintenance = 'maintenance';
    case Tenant = 'tenant';
}
