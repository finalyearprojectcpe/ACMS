<?php

namespace App;

enum EmailDeliveryStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Delayed = 'delayed';
    case Failed = 'failed';
    case Bounced = 'bounced';
    case Suppressed = 'suppressed';
    case Complained = 'complained';
    case Skipped = 'skipped';
}
