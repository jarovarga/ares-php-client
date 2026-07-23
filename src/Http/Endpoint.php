<?php

declare(strict_types=1);

namespace AresApi\Http;

/**
 * Represents the endpoints for the ARES API.
 */
enum Endpoint: string
{
    case Companies = '/ekonomicke-subjekty';
    case SearchCompanies = '/ekonomicke-subjekty/vyhledat';
}
