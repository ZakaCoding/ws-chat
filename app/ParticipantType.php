<?php

namespace App;

enum ParticipantType: string
{
    case Guest = 'guest';
    case Operator = 'operator';
}
