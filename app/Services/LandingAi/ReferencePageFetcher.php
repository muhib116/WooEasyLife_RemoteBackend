<?php

namespace App\Services\LandingAi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reads merchant-supplied reference pages for the landing generator.
 * Every hop must resolve to public IPs only, and the connection is pinned to the
 * checked IP so DNS cannot be swapped between the check and the request.
 */
class ReferencePageFetcher
{
    private const ALLOWED_PORTS = [80, 443, 8080, 8443];

    /**
     * Overall wall-clock budget for all reference pages in one generation.
     */
    private const TOTAL_BUDGET_SECONDS = 20;

    /**
     * @var (callable(string): list<string>)|null
     */
    private $resolver;

    /**
     * @param  (callable(string): list<string>)|null  $resolver  host → IPs (tests inject a fake)
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param  list<string>  $urls
     * @return list<array{url: string, ok: bool, error?: string, title?: string, description?: string, headings?: list<string>, text?: string}>
     */
    public function fetchMany(array $urls): array
    {
        $started = microtime(true);
        $pages = [];

        foreach ($urls as $url) {
            if (microtime(true) - $started > self::TOTAL_BUDGET_SECONDS) {
                $pages[] = ['url' => $url, 'ok' => false, 'error' => 'Skipped: time limit reached'];

                continue;
            }
            $pages[] = $this->fetch($url);
        }

        return $pages;
    }

    /**
     * @return array{url: string, ok: bool, error?: string, title?: string, description?: string, headings?: list<string>, text?: string}
     */
    public function fetch(string $url): array
    {
        $current = trim($url);
        $maxRedirects = (int) config('landing_ai.max_redirects', 3);

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $target = $this->checkTarget($current);
            if (isset($target['error'])) {
                return ['url' => $url, 'ok' => false, 'error' => $target['error']];
            }

            $curl = [CURLOPT_MAXFILESIZE => (int) config('landing_ai.max_html_bytes', 1_500_000)];
            if ($target['pin'] !== null) {
                $curl[CURLOPT_RESOLVE] = [$target['host'].':'.$target['port'].':'.$target['pin']];
            }

            try {
                $response = Http::timeout((int) config('landing_ai.fetch_timeout', 10))
                    ->connectTimeout((int) config('landing_ai.fetch_connect_timeout', 5))
                    ->withOptions([
                        'allow_redirects' => false,
                        'curl' => $curl,
                    ])
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; WooEasyLifeLandingBot/1.0; +https://wooeasylife.com)',
                        'Accept' => 'text/html,application/xhtml+xml',
                        'Accept-Language' => 'bn-BD,bn;q=0.9,en;q=0.7',
                    ])
                    ->get($current);
            } catch (\Throwable $e) {
                return ['url' => $url, 'ok' => false, 'error' => $this->describeException($e)];
            }

            $status = $response->status();

            if ($status >= 300 && $status < 400) {
                $location = trim((string) $response->header('Location'));
                $next = $location !== '' ? $this->resolveRedirect($current, $location) : null;
                if ($next === null) {
                    return ['url' => $url, 'ok' => false, 'error' => 'Redirect could not be followed'];
                }
                $current = $next;

                continue;
            }

            if ($status === 401 || $status === 403) {
                return ['url' => $url, 'ok' => false, 'error' => 'The site blocked reading (HTTP '.$status.')'];
            }

            if (! $response->successful()) {
                return ['url' => $url, 'ok' => false, 'error' => 'HTTP '.$status];
            }

            $type = strtolower((string) $response->header('Content-Type'));
            if ($type !== '' && ! str_contains($type, 'html') && ! str_contains($type, 'xml')) {
                return ['url' => $url, 'ok' => false, 'error' => 'Not a web page'];
            }

            $html = (string) $response->body();
            $max = (int) config('landing_ai.max_html_bytes', 1_500_000);
            if (strlen($html) > $max) {
                $html = substr($html, 0, $max);
            }

            return $this->parse($url, $this->toUtf8($html, $type));
        }

        return ['url' => $url, 'ok' => false, 'error' => 'Too many redirects'];
    }

    /**
     * pin is null for literal IP hosts (nothing to resolve).
     *
     * @return array{host: string, port: int, pin: string|null}|array{error: string}
     */
    public function checkTarget(string $url): array
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return ['error' => 'Not a valid URL'];
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return ['error' => 'Only http and https links can be read'];
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return ['error' => 'Links with a login part are not allowed'];
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')
            || preg_match('/\.(local|internal|lan|home|corp|test|invalid)$/', $host)) {
            return ['error' => 'Private addresses cannot be read'];
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, self::ALLOWED_PORTS, true)) {
            return ['error' => 'This port is not allowed'];
        }

        $literal = (bool) filter_var($host, FILTER_VALIDATE_IP);
        $ips = $literal ? [$host] : $this->resolve($host);
        if ($ips === []) {
            return ['error' => 'Site address not found'];
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return ['error' => 'Private addresses cannot be read'];
            }
        }

        $pin = $ips[0];

        return [
            'host' => $host,
            'port' => $port,
            'pin' => $literal ? null : (str_contains($pin, ':') ? '['.$pin.']' : $pin),
        ];
    }

    public static function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $lower = strtolower($ip);
            // IPv4-mapped / NAT64 forms can hide a private IPv4 address.
            if (preg_match('/^(::ffff:|64:ff9b::)(\d+\.\d+\.\d+\.\d+)$/', $lower, $m)) {
                return self::isPublicIp($m[2]);
            }
            if (str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd') || str_starts_with($lower, 'fe80')) {
                return false;
            }

            return true;
        }

        $long = ip2long($ip);
        foreach ([['100.64.0.0', 10], ['192.0.0.0', 24], ['198.18.0.0', 15]] as [$net, $bits]) {
            $mask = -1 << (32 - $bits);
            if (($long & $mask) === (ip2long($net) & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if ($this->resolver) {
            return array_values(array_filter(($this->resolver)($host), 'is_string'));
        }

        $ips = @gethostbynamel($host) ?: [];
        if (function_exists('dns_get_record')) {
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $row) {
                if (! empty($row['ipv6'])) {
                    $ips[] = (string) $row['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private function resolveRedirect(string $from, string $location): ?string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($from);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(! empty($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $dir = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/') ?: '/';

        return $origin.$dir.$location;
    }

    private function toUtf8(string $html, string $contentType): string
    {
        $charset = null;
        if (preg_match('/charset=["\']?([\w-]+)/i', $contentType, $m)) {
            $charset = $m[1];
        } elseif (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', substr($html, 0, 4096), $m)) {
            $charset = $m[1];
        }

        if ($charset && ! in_array(strtolower($charset), ['utf-8', 'utf8'], true)) {
            $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
            if (is_string($converted)) {
                $html = $converted;
            }
        }

        return mb_scrub($html, 'UTF-8');
    }

    /**
     * @return array{url: string, ok: bool, error?: string, title?: string, description?: string, headings?: list<string>, text?: string}
     */
    private function parse(string $url, string $html): array
    {
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = $this->clean($m[1]);
        }

        $description = $this->metaContent($html, 'description')
            ?: $this->metaContent($html, 'og:description');

        $headings = [];
        if (preg_match_all('/<h([1-3])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $text = $this->clean(strip_tags($match[2]));
                if ($text !== '' && mb_strlen($text) <= 160 && ! in_array($text, $headings, true)) {
                    $headings[] = $text;
                }
                if (count($headings) >= 30) {
                    break;
                }
            }
        }

        $body = preg_replace('/<(script|style|noscript|svg|iframe|template|head)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $body = preg_replace('/<!--.*?-->/s', ' ', $body) ?? $body;
        // Keep block boundaries so sentences do not run together.
        $body = preg_replace('/<\/(p|div|li|h[1-6]|section|article|tr|br)\s*>|<br\s*\/?>/i', "\n", $body) ?? $body;
        $text = $this->clean(strip_tags($body), keepLines: true);

        $maxChars = (int) config('landing_ai.max_page_chars', 6000);
        $text = Str::limit($text, $maxChars, '…');

        if ($text === '' && $title === '' && $description === '') {
            return ['url' => $url, 'ok' => false, 'error' => 'The page had no readable text (it may need JavaScript or a login)'];
        }

        $intel = $this->extractSellingIntel($headings, $text);

        return [
            'url' => $url,
            'ok' => true,
            'title' => Str::limit($title, 200, '…'),
            'description' => Str::limit($description, 400, '…'),
            'headings' => $headings,
            'text' => $text,
            'selling_angles' => $intel['angles'],
            'objections' => $intel['objections'],
        ];
    }

    /**
     * @param  list<string>  $headings
     * @return array{angles: list<string>, objections: list<string>}
     */
    private function extractSellingIntel(array $headings, string $text): array
    {
        $blob = mb_strtolower(implode("\n", $headings)."\n".$text);
        $angles = [];
        $objections = [];

        $angleRules = [
            '/cod|cash on delivery|ক্যাশ\s*অন|হাতে\s*প[eে]য়ে/u' => 'COD convenience and pay-on-delivery trust',
            '/free delivery|ফ্রি\s*ডেলিভারি|ডেলিভারি\s*ফ্রি/u' => 'Delivery ease (only if merchant facts match)',
            '/combo|pack|bundle|২\s*টি|২\s*টা|dual/u' => 'Multi-piece or combo value',
            '/original|authentic|100%|pure|অরিজিনাল|খাঁটি/u' => 'Authenticity and quality assurance',
            '/limited|stock|সীমিত|আজকের/u' => 'Urgency without fake scarcity',
            '/result|benefit|works|ফলাফল|সুবিধা/u' => 'Outcome-led promise',
        ];
        $objectionRules = [
            '/fake|নকল|copy|duplicate/u' => 'Is it original?',
            '/side effect|safe|নিরাপদ|পার্শ্ব/u' => 'Safety and side effects',
            '/return|refund|ফেরত/u' => 'Return or refund policy',
            '/delivery|shipping|ডেলিভারি|ঢাকা/u' => 'Delivery time and charges',
            '/how to use|ব্যবহার/u' => 'How to use correctly',
        ];

        foreach ($angleRules as $pattern => $label) {
            if (preg_match($pattern, $blob)) {
                $angles[] = $label;
            }
        }
        foreach ($objectionRules as $pattern => $label) {
            if (preg_match($pattern, $blob)) {
                $objections[] = $label;
            }
        }
        foreach (array_slice($headings, 0, 6) as $heading) {
            $h = trim($heading);
            if ($h !== '' && mb_strlen($h) <= 100) {
                $angles[] = 'Section theme: '.$h;
            }
        }

        return [
            'angles' => array_values(array_unique(array_slice($angles, 0, 8))),
            'objections' => array_values(array_unique(array_slice($objections, 0, 6))),
        ];
    }

    private function metaContent(string $html, string $name): string
    {
        $quoted = preg_quote($name, '/');
        if (preg_match('/<meta[^>]+(?:name|property)=["\']'.$quoted.'["\'][^>]*content=["\'](.*?)["\']/is', $html, $m)
            || preg_match('/<meta[^>]+content=["\'](.*?)["\'][^>]*(?:name|property)=["\']'.$quoted.'["\']/is', $html, $m)) {
            return $this->clean($m[1]);
        }

        return '';
    }

    private function clean(string $text, bool $keepLines = false): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (! $keepLines) {
            return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        }

        $lines = preg_split('/\R/u', $text) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/[^\S\n]+/u', ' ', $line) ?? $line);
            if ($line !== '' && (end($out) !== $line)) {
                $out[] = $line;
            }
        }

        return implode("\n", $out);
    }

    private function describeException(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'timed out') || str_contains($message, 'cURL error 28')) {
            return 'The site took too long to answer';
        }
        if (str_contains($message, 'cURL error 63') || str_contains($message, 'maximum file size')) {
            return 'The page is too large to read';
        }
        if (str_contains($message, 'cURL error 6') || str_contains($message, 'resolve host')) {
            return 'Site address not found';
        }
        if (str_contains($message, 'SSL') || str_contains($message, 'certificate')) {
            return 'The site has a broken HTTPS certificate';
        }

        return 'Could not open the page';
    }
}
