<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spiggle\ApiInspector\Support\Settings;

final class RequestFilter
{
    private const STATIC_EXTENSIONS = [
        'css', 'js', 'mjs', 'map', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg',
        'ico', 'woff', 'woff2', 'ttf', 'eot', 'otf', 'mp4', 'webm', 'mp3', 'wav',
        'pdf', 'zip', 'gz', 'tar', 'rar', '7z',
    ];

    public function __construct(private readonly Settings $settings)
    {
    }

    public function shouldCapture(Request $request): bool
    {
        if (! $this->settings->isEnabled()) {
            return false;
        }

        if (ReplayContext::isRunning() || $request->headers->has('X-Api-Inspector-Replay')) {
            return false;
        }

        if (! in_array(strtoupper($request->method()), $this->settings->methods(), true)) {
            return false;
        }

        $path = ltrim($request->path(), '/');

        $only = $this->normalizePatterns($this->settings->onlyPaths());

        if ($only !== [] && ! Str::is($only, $path)) {
            return false;
        }

        if (Str::is($this->normalizePatterns($this->settings->ignorePaths()), $path)) {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension !== '' && in_array($extension, self::STATIC_EXTENSIONS, true)) {
            return false;
        }

        $sampleRate = $this->settings->sampleRate();

        return $sampleRate >= 100 || random_int(1, 100) <= $sampleRate;
    }

    /**
     * @param  list<string>  $patterns
     * @return list<string>
     */
    private function normalizePatterns(array $patterns): array
    {
        return array_values(array_filter(array_map(
            static fn (string $pattern): string => ltrim(trim($pattern), '/'),
            $patterns,
        )));
    }
}
