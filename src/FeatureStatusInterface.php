<?php

declare(strict_types = 1);

namespace StasPiv\FeatureFlags;

interface FeatureStatusInterface
{
    public const STATUS_ENABLED = 'enabled';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_NOT_AVAILABLE = 'not available';
}
