<?php

namespace Paymob\Laravel\Enums;

enum PlanFrequency: int
{
    case WEEKLY = 7;
    case BIWEEKLY = 15;
    case MONTHLY = 30;
    case BIMONTHLY = 60;
    case QUARTERLY = 90;
    case SEMIANNUAL = 180;
    case ANNUAL = 360;
}
