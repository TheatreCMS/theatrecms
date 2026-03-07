<?php

namespace TheatreCMS\Models;

enum RoleType: string
{
    case Cast = 'cast';
    case ProductionTeam = 'production_team';
    case Orchestra = 'orchestra';
    case Creative = 'creative';
}
