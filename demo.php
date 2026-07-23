<?php

declare(strict_types=1);

use AresApi\ClientFactory;
use AresApi\Company\DTO\Company;
use AresApi\Company\Query\CompanySearchQuery;
use AresApi\Company\Result\CompanySearchResult;
use AresApi\Configuration;
use AresApi\Exception\ApiException;
use AresApi\Exception\AresException;
use AresApi\Exception\RateLimitException;
use AresApi\Pagination\PageRequest;
use AresApi\ValueObject\CompanyRegistrationNumber;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;

require __DIR__ . '/vendor/autoload.php';

const EXAMPLE_PAGE_SIZE = 10;

/**
 * Escapes a string or integer value for safe output in HTML contexts.
 *
 * @param string|int|null $value The value to be escaped. Can be a string, integer, or null.
 *
 * @return string The HTML-escaped string.
 */
function escapeHtml(string|int|null $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8',
    );
}

/**
 * Generates the URL for a result page based on the search term and page number.
 *
 * @param string $searchTerm The term to be searched.
 * @param int $page The page number to generate the URL for.
 *
 * @return string The generated URL with the search term and page number as query parameters.
 */
function resultPageUrl(string $searchTerm, int $page): string
{
    return '?' . http_build_query([
        'query' => $searchTerm,
        'page' => $page,
    ]);
}

/**
 * Creates an HTML-safe view model for a company result card.
 *
 * @param Company $company The company represented by the card.
 *
 * @return array{
 *     id: string,
 *     name: string,
 *     address: string,
 *     registrationNumber: string,
 *     legalFormCode: string,
 *     establishedOn: string,
 *     dissolution: string
 * } The prepared company-card values.
 */
function createCompanyCardView(Company $company): array
{
    $registrationNumber = $company->registrationNumber()?->value();
    $legalForm = $company->legalForm();
    $legalFormCode = $legalForm?->code()
        ?? $legalForm?->rosCode()
        ?? 'Not provided';

    return [
        'id' => escapeHtml('ARES ID: ' . $company->aresId()),
        'name' => escapeHtml(
            $company->businessName() ?? 'Business name not provided',
        ),
        'address' => escapeHtml(
            $company->registeredOffice()?->formattedAddress()
                ?? 'Registered office not provided',
        ),
        'registrationNumber' => escapeHtml(
            $registrationNumber ?? 'Not assigned',
        ),
        'legalFormCode' => escapeHtml($legalFormCode),
        'establishedOn' => escapeHtml(
            $company->establishedOn()?->format('j M Y') ?? 'Not provided',
        ),
        'dissolution' => escapeHtml(
            $company->dissolvedOn()?->format('j M Y') ?? 'Not recorded',
        ),
    ];
}

/**
 * Creates an HTML-safe view model for the search-result section.
 *
 * @param CompanySearchResult $result The company search result.
 * @param string $searchTerm The original search term.
 *
 * @return array{
 *     totalItems: string,
 *     companyLabel: string,
 *     searchTerm: string,
 *     isEmpty: bool,
 *     companies: list<array{
 *         id: string,
 *         name: string,
 *         address: string,
 *         registrationNumber: string,
 *         legalFormCode: string,
 *         establishedOn: string,
 *         dissolution: string
 *     }>,
 *     pagination: array{
 *         previousUrl: string|null,
 *         nextUrl: string|null,
 *         currentPage: string,
 *         totalPages: string
 *     }|null
 * } The prepared search-result values.
 */
function createSearchResultsView(
    CompanySearchResult $result,
    string $searchTerm,
): array {
    $pageInfo = $result->pageInfo();
    $companies = [];

    foreach ($result as $company) {
        $companies[] = createCompanyCardView($company);
    }

    $pagination = null;
    if ($pageInfo->hasPreviousPage() || $pageInfo->hasNextPage()) {
        $pagination = [
            'previousUrl' => $pageInfo->hasPreviousPage()
                ? escapeHtml(resultPageUrl(
                    $searchTerm,
                    $pageInfo->currentPage() - 1,
                ))
                : null,
            'nextUrl' => $pageInfo->hasNextPage()
                ? escapeHtml(resultPageUrl(
                    $searchTerm,
                    $pageInfo->currentPage() + 1,
                ))
                : null,
            'currentPage' => escapeHtml($pageInfo->currentPage()),
            'totalPages' => escapeHtml($pageInfo->totalPages()),
        ];
    }

    return [
        'totalItems' => $pageInfo->totalItems()
                        |> number_format(...)
                        |> escapeHtml(...),
        'companyLabel' => $pageInfo->totalItems() === 1
            ? 'company'
            : 'companies',
        'searchTerm' => escapeHtml($searchTerm),
        'isEmpty' => $companies === [],
        'companies' => $companies,
        'pagination' => $pagination,
    ];
}

$queryParameter = $_GET['query'] ?? '';
$searchTerm = is_string($queryParameter) ? trim($queryParameter) : '';
$searchWasSubmitted = array_key_exists('query', $_GET);

$pageParameter = $_GET['page'] ?? 1;
$validatedPage = is_int($pageParameter) || is_string($pageParameter)
    ? filter_var(
        $pageParameter,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]],
    )
    : false;
$page = is_int($validatedPage) ? $validatedPage : 1;

/** @var CompanySearchResult|null $result */
$result = null;
$errorMessage = null;
$missingHttpImplementation = !class_exists(GuzzleClient::class)
    || !class_exists(HttpFactory::class);

if ($searchWasSubmitted) {
    if ($searchTerm === '') {
        $errorMessage = 'Enter a business name or an eight-digit company registration number.';
    } elseif ($missingHttpImplementation) {
        $errorMessage = 'The demo HTTP implementation is not installed.';
    } else {
        try {
            $compactSearchTerm = preg_replace('/[\s-]+/', '', $searchTerm);
            $isRegistrationNumber = is_string($compactSearchTerm)
                && preg_match('/^\d{8}$/D', $compactSearchTerm) === 1;

            $query = new CompanySearchQuery(
                registrationNumbers: $isRegistrationNumber
                    ? [new CompanyRegistrationNumber($compactSearchTerm)]
                    : [],
                businessName: $isRegistrationNumber ? null : $searchTerm,
                page: new PageRequest(
                    number: $page,
                    size: EXAMPLE_PAGE_SIZE,
                ),
            );

            $httpFactory = new HttpFactory();
            $client = new ClientFactory(
                new GuzzleClient([
                    'connect_timeout' => 5.0,
                    'timeout' => 15.0,
                ]),
                $httpFactory,
                $httpFactory,
            )->create(new Configuration(
                userAgent: 'ares-php-client-example/1.0',
            ));

            $result = $client->companies()->search($query);
        } catch (RateLimitException $exception) {
            $retryAfter = $exception->retryAfterSeconds();
            $errorMessage = $retryAfter === null
                ? 'ARES is receiving too many requests. Please try again shortly.'
                : sprintf('ARES is receiving too many requests. Please try again in %d seconds.', $retryAfter);
        } catch (ApiException $exception) {
            $errorMessage = sprintf('ARES could not complete the search (HTTP %d). Please try again.', $exception->statusCode());
        } catch (AresException $exception) {
            $errorMessage = $exception->getMessage();
        }
    }
}

$searchInputValue = escapeHtml($searchTerm);
$errorViewMessage = $errorMessage === null
    ? null
    : escapeHtml($errorMessage);
$showSetupNotice = $missingHttpImplementation;
$resultsView = $result === null
    ? null
    : createSearchResultsView($result, $searchTerm);
$paginationView = $resultsView['pagination'] ?? null;

?>
<!doctype html>
<html lang="en">
    <head>
        <title>ARES Company Search</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Search Czech companies in the official ARES register.">

        <style>:root{color-scheme:light;--background:#f4f7fb;--surface:#fff;--surface-soft:#f8fafc;--border:#dce4ee;--border-strong:#c4d0df;--text:#172033;--muted:#64748b;--primary:#175cd3;--primary-dark:#124aa8;--primary-soft:#eaf2ff;--danger:#b42318;--danger-soft:#fff0ee;--shadow:0 18px 50px #1f355217;--radius-large:24px;--radius-medium:16px;--radius-small:10px}*{box-sizing:border-box}.page{min-width:320px;margin:0;background:radial-gradient(circle at top left,#e5efff 0,transparent 34rem),var(--background);color:var(--text);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.5}a{color:inherit}button,input{font:inherit}.company-search__label--visually-hidden{position:absolute;width:1px;height:1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.page__container{width:min(1120px,calc(100% - 40px));margin:0 auto}.site-header__inner{display:flex;align-items:center;justify-content:space-between;min-height:82px}.brand{display:inline-flex;gap:12px;align-items:center;color:var(--text);font-size:16px;font-weight:750;letter-spacing:-.01em;text-decoration:none}.brand__mark{display:grid;width:38px;height:38px;place-items:center;border-radius:12px;background:var(--primary);color:#fff;box-shadow:0 8px 20px #175cd340;font-size:18px;font-weight:800}.registry-status{display:inline-flex;gap:7px;align-items:center;color:var(--muted);font-size:13px;font-weight:650}.registry-status__indicator{width:8px;height:8px;flex:0 0 auto;border-radius:50%;background:#12a579;box-shadow:0 0 0 4px #12a5791f}.page__main{padding:48px 0 72px}.intro{max-width:760px;margin-bottom:32px}.intro__overline{margin:0 0 12px;color:var(--primary);font-size:13px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.intro__title{max-width:700px;margin:0;font-size:clamp(38px,6vw,64px);line-height:1.03;letter-spacing:-.055em}.intro__copy{max-width:660px;margin:20px 0 0;color:var(--muted);font-size:18px}.company-search{padding:10px;border:1px solid #c4d0dfcc;border-radius:var(--radius-large);background:#ffffffeb;box-shadow:var(--shadow);backdrop-filter:blur(14px)}.company-search__form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px}.company-search__field{position:relative}.company-search__icon{position:absolute;top:50%;left:20px;width:15px;height:15px;border:2px solid currentcolor;border-radius:50%;color:#73839a;transform:translateY(-50%);pointer-events:none}.company-search__icon::after{position:absolute;right:-6px;bottom:-4px;width:7px;height:2px;border-radius:999px;background:currentcolor;content:"";transform:rotate(45deg);transform-origin:left center}.company-search__input{width:100%;min-height:58px;padding:0 18px 0 52px;border:1px solid transparent;border-radius:15px;outline:none;background:var(--surface-soft);color:var(--text);transition:border-color 160ms ease,box-shadow 160ms ease,background 160ms ease}.company-search__input::placeholder{color:#8795a8}.company-search__input:focus{border-color:var(--primary);background:#fff;box-shadow:0 0 0 4px #175cd31f}.company-search__submit{min-height:58px;padding:0 28px;border:0;border-radius:15px;background:var(--primary);color:#fff;cursor:pointer;font-weight:750;box-shadow:0 10px 24px #175cd333;transition:background 160ms ease,transform 160ms ease}.company-search__submit:hover{background:var(--primary-dark);transform:translateY(-1px)}.company-search__submit:focus-visible,.pagination__link:focus-visible,.company-search__example:focus-visible{outline:3px solid #175cd340;outline-offset:3px}.company-search__help{display:flex;flex-wrap:wrap;gap:8px 18px;align-items:center;padding:11px 12px 3px;color:var(--muted);font-size:13px}.company-search__example{color:var(--primary);font-weight:700;text-decoration:none}.company-search__example:hover{text-decoration:underline}.notice{display:flex;gap:12px;align-items:flex-start;margin-top:22px;padding:16px 18px;border-radius:var(--radius-medium)}.notice--error{border:1px solid #ffd1cc;background:var(--danger-soft);color:var(--danger)}.notice__icon{display:grid;flex:0 0 auto;width:21px;height:21px;margin-top:1px;place-items:center;border:2px solid currentcolor;border-radius:50%;font-size:13px;font-weight:850;line-height:1}.notice__text{margin:0}.notice--setup{border:1px solid var(--border);background:var(--surface);color:var(--muted);font-size:14px}.notice__title{color:var(--text)}.notice__code{padding:3px 6px;border-radius:6px;background:#edf2f7;color:#26354b;font-family:"SFMono-Regular",Consolas,monospace;font-size:.92em}.results{margin-top:42px}.results__header{display:flex;gap:20px;align-items:flex-end;justify-content:space-between;margin-bottom:18px}.results__title{margin:0;font-size:24px;letter-spacing:-.025em}.results__summary{margin:5px 0 0;color:var(--muted);font-size:14px}.results__clear{color:var(--primary);font-size:14px;font-weight:700;text-decoration:none}.results__clear:hover{text-decoration:underline}.results__list{display:grid;gap:14px}.company-card{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(330px,1fr);gap:26px;padding:24px;border:1px solid var(--border);border-radius:var(--radius-medium);background:var(--surface);box-shadow:0 5px 18px #1f355209;transition:border-color 160ms ease,box-shadow 160ms ease,transform 160ms ease}.company-card:hover{border-color:var(--border-strong);box-shadow:0 12px 28px #1f355212;transform:translateY(-1px)}.company-card__id{display:inline-flex;margin-bottom:12px;padding:5px 9px;border-radius:999px;background:var(--primary-soft);color:var(--primary);font-size:12px;font-weight:800;letter-spacing:.04em}.company-card__name{margin:0;font-size:21px;line-height:1.3;letter-spacing:-.02em}.company-card__address{max-width:620px;margin:10px 0 0;color:var(--muted);font-size:14px}.company-card__meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px 22px;margin:0}.company-card__meta-item{min-width:0}.company-card__meta-label{margin-bottom:4px;color:var(--muted);font-size:11px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.company-card__meta-value{margin:0;overflow-wrap:anywhere;font-size:14px;font-weight:650}.empty-state{padding:52px 24px;border:1px dashed var(--border-strong);border-radius:var(--radius-medium);background:#fff9;text-align:center}.empty-state__title{margin:0;font-size:21px}.empty-state__text{max-width:480px;margin:8px auto 0;color:var(--muted)}.pagination{display:grid;grid-template-columns:1fr auto 1fr;gap:14px;align-items:center;margin-top:24px}.pagination__position{color:var(--muted);font-size:13px;text-align:center}.pagination__link{display:inline-flex;gap:8px;align-items:center;width:max-content;min-height:42px;padding:0 15px;border:1px solid var(--border-strong);border-radius:var(--radius-small);background:var(--surface);color:var(--text);font-size:14px;font-weight:700;text-decoration:none}.pagination__link:hover{border-color:var(--primary);color:var(--primary)}.pagination__slot--next{display:flex;justify-content:flex-end}.site-footer{padding:26px 0 38px;border-top:1px solid #c4d0dfbf;color:var(--muted);font-size:13px}.site-footer__text{margin:0}@media (max-width: 820px){.page__main{padding-top:34px}.company-card{grid-template-columns:1fr}}@media (max-width: 620px){.page__container{width:min(100% - 24px,1120px)}.registry-status{display:none}.page__main{padding-bottom:52px}.intro__title{font-size:40px}.intro__copy{font-size:16px}.company-search__form{grid-template-columns:1fr}.company-search__submit{width:100%}.results__header{align-items:flex-start;flex-direction:column}.company-card{padding:20px}.company-card__meta{grid-template-columns:1fr}.pagination{grid-template-columns:1fr 1fr}.pagination__position{grid-column:1 / -1;grid-row:1}}@media (prefers-reduced-motion: reduce){*,::before,::after{scroll-behavior:auto!important;transition-duration:.01ms!important}}</style>
    </head>
    <body class="page">
        <header class="site-header">
            <div class="page__container site-header__inner">
                <a class="brand" href="?">
                    <span class="brand__mark" aria-hidden="true">A</span>
                    <span class="brand__name">ARES PHP Client</span>
                </a>
                <span class="registry-status">
                    <span class="registry-status__indicator" aria-hidden="true"></span>
                    <span class="registry-status__text">Official Czech registry data</span>
                </span>
            </div>
        </header>

        <main class="page__container page__main">
            <section class="intro" aria-labelledby="page-title">
                <p class="intro__overline">Company register</p>
                <h1 class="intro__title" id="page-title">Find a Czech company.</h1>
                <p class="intro__copy">Search the ARES register by business name or company registration number.
                    The results come directly from the Czech Ministry of Finance.</p>
            </section>

            <section class="company-search" aria-label="Company search">
                <form class="company-search__form" method="get" action="">
                    <div class="company-search__field">
                        <span class="company-search__icon" aria-hidden="true"></span>
                        <label class="company-search__label company-search__label--visually-hidden"
                               for="company-query">Business name or registration number</label>
                        <input class="company-search__input"
                               id="company-query"
                               name="query"
                               type="search"
                               value="<?= $searchInputValue ?>"
                               placeholder="Business name or registration number"
                               autocomplete="organization"
                               aria-describedby="search-help"
                               maxlength="2000"
                               enterkeyhint="search"
                               required>
                    </div>
                    <button class="company-search__submit" type="submit">Search ARES</button>
                </form>
                <div class="company-search__help" id="search-help">
                    <span>Try a company name or an eight-digit number.</span>
                    <a class="company-search__example" href="?query=Asseco">Example: Asseco</a>
                </div>
            </section>

            <?php if ($errorViewMessage !== null): ?>
                <div class="notice notice--error" role="alert">
                    <span class="notice__icon" aria-hidden="true">!</span>
                    <p class="notice__text"><?= $errorViewMessage ?></p>
                </div>
            <?php endif; ?>

            <?php if ($showSetupNotice): ?>
                <div class="notice notice--setup">
                    <p class="notice__text">
                        <strong class="notice__title">One-time setup:</strong> run
                        <code class="notice__code">composer require --dev guzzlehttp/guzzle</code>
                        to enable live searches in this example.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($resultsView !== null): ?>
                <section class="results" aria-labelledby="results-title" aria-live="polite">
                    <div class="results__header">
                        <div>
                            <h2 class="results__title" id="results-title">Search results</h2>
                            <p class="results__summary">
                                <?= $resultsView['totalItems'] ?>
                                <?= $resultsView['companyLabel'] ?>
                                found for “<?= $resultsView['searchTerm'] ?>”
                            </p>
                        </div>
                        <a class="results__clear" href="?">Clear search</a>
                    </div>

                    <?php if ($resultsView['isEmpty']): ?>
                        <div class="empty-state">
                            <h2 class="empty-state__title">No companies found</h2>
                            <p class="empty-state__text">Check the spelling or try a shorter business name.
                                Registration numbers must contain eight digits.</p>
                        </div>
                    <?php else: ?>
                        <div class="results__list">
                            <?php foreach ($resultsView['companies'] as $companyView): ?>
                                <article class="company-card">
                                    <div class="company-card__main">
                                        <span class="company-card__id"><?= $companyView['id'] ?></span>
                                        <h2 class="company-card__name"><?= $companyView['name'] ?></h2>
                                        <p class="company-card__address"><?= $companyView['address'] ?></p>
                                    </div>

                                    <dl class="company-card__meta">
                                        <div class="company-card__meta-item">
                                            <dt class="company-card__meta-label">Registration number</dt>
                                            <dd class="company-card__meta-value">
                                                <?= $companyView['registrationNumber'] ?>
                                            </dd>
                                        </div>
                                        <div class="company-card__meta-item">
                                            <dt class="company-card__meta-label">Legal form code</dt>
                                            <dd class="company-card__meta-value">
                                                <?= $companyView['legalFormCode'] ?>
                                            </dd>
                                        </div>
                                        <div class="company-card__meta-item">
                                            <dt class="company-card__meta-label">Established</dt>
                                            <dd class="company-card__meta-value">
                                                <?= $companyView['establishedOn'] ?>
                                            </dd>
                                        </div>
                                        <div class="company-card__meta-item">
                                            <dt class="company-card__meta-label">Dissolution</dt>
                                            <dd class="company-card__meta-value"><?= $companyView['dissolution'] ?></dd>
                                        </div>
                                    </dl>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($paginationView !== null): ?>
                        <nav class="pagination" aria-label="Search result pages">
                            <div class="pagination__slot">
                                <?php if ($paginationView['previousUrl'] !== null): ?>
                                    <a class="pagination__link" href="<?= $paginationView['previousUrl'] ?>" rel="prev">
                                        <span class="pagination__icon" aria-hidden="true">←</span> Previous
                                    </a>
                                <?php endif; ?>
                            </div>

                            <span class="pagination__position">
                                Page <?= $paginationView['currentPage'] ?>
                                of <?= $paginationView['totalPages'] ?>
                            </span>

                            <div class="pagination__slot pagination__slot--next">
                                <?php if ($paginationView['nextUrl'] !== null): ?>
                                    <a class="pagination__link" href="<?= $paginationView['nextUrl'] ?>" rel="next">
                                        Next <span class="pagination__icon" aria-hidden="true">→</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </nav>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>

        <footer class="site-footer">
            <div class="page__container site-footer__inner">
                <p class="site-footer__text">Demo powered by ARES PHP Client. Data is provided by the Czech ARES service.</p>
            </div>
        </footer>
    </body>
</html>
