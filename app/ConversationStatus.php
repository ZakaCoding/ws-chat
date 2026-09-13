<?php

namespace App;

enum ConversationStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
