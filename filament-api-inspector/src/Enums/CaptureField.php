<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Enums;

enum CaptureField: string
{
    case Method = 'method';
    case Uri = 'uri';
    case Status = 'status';
    case Timestamp = 'timestamp';
    case Duration = 'duration';
    case Memory = 'memory';
    case Ip = 'ip';
    case Headers = 'headers';
    case Query = 'query';
    case Payload = 'payload';
    case ResponseHeaders = 'response_headers';
    case ResponseBody = 'response_body';
    case User = 'user';
    case UserAgent = 'user_agent';
    case Referer = 'referer';
    case Route = 'route';
    case Session = 'session';
    case Exception = 'exception';
    case Replay = 'replay';

    public function label(): string
    {
        return match ($this) {
            self::Method => 'HTTP method',
            self::Uri => 'URI & path',
            self::Status => 'Response status',
            self::Timestamp => 'Captured at',
            self::Duration => 'Duration',
            self::Memory => 'Peak memory',
            self::Ip => 'Client IP',
            self::Headers => 'Request headers',
            self::Query => 'Query string',
            self::Payload => 'Request payload',
            self::ResponseHeaders => 'Response headers',
            self::ResponseBody => 'Response body',
            self::User => 'Authenticated user',
            self::UserAgent => 'User agent',
            self::Referer => 'Referer',
            self::Route => 'Route & controller',
            self::Session => 'Session ID',
            self::Exception => 'Exception details',
            self::Replay => 'Replay history',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Method => 'GET, POST, PUT, PATCH, DELETE …',
            self::Uri => 'Full URL plus the matched path.',
            self::Status => 'Response status code and reason text.',
            self::Timestamp => 'Exact time the request was received.',
            self::Duration => 'Wall-clock time spent handling the request.',
            self::Memory => 'Peak memory consumed while handling it.',
            self::Ip => 'Client IP address (proxy aware).',
            self::Headers => 'Request headers, with sensitive values redacted.',
            self::Query => 'Parsed query string parameters.',
            self::Payload => 'JSON, form or raw request body (size capped).',
            self::ResponseHeaders => 'Headers returned by your application.',
            self::ResponseBody => 'JSON or text response (binary is skipped).',
            self::User => 'ID and a readable label for the caller.',
            self::UserAgent => 'Browser or HTTP client identification.',
            self::Referer => 'The page or client that triggered the call.',
            self::Route => 'Named route, controller action and middleware.',
            self::Session => 'Session identifier, useful for grouping visits.',
            self::Exception => 'Class, message and trace when a call fails.',
            self::Replay => 'Modes, status and results of replays.',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Method, self::Uri, self::Query, self::Payload, self::Headers, self::UserAgent, self::Referer => 'Request',
            self::Status, self::Duration, self::ResponseHeaders, self::ResponseBody, self::Exception => 'Response',
            self::Ip, self::User, self::Session, self::Timestamp, self::Replay, self::Memory, self::Route => 'Context & performance',
        };
    }

    public function isCore(): bool
    {
        return in_array($this, [self::Method, self::Uri, self::Status, self::Timestamp], true);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Method => 'heroicon-o-arrow-right-circle',
            self::Uri => 'heroicon-o-link',
            self::Status => 'heroicon-o-check-badge',
            self::Timestamp => 'heroicon-o-clock',
            self::Duration => 'heroicon-o-bolt',
            self::Memory => 'heroicon-o-cpu-chip',
            self::Ip => 'heroicon-o-globe-alt',
            self::Headers => 'heroicon-o-list-bullet',
            self::Query => 'heroicon-o-funnel',
            self::Payload => 'heroicon-o-code-bracket',
            self::ResponseHeaders => 'heroicon-o-arrows-up-down',
            self::ResponseBody => 'heroicon-o-document-text',
            self::User => 'heroicon-o-user-circle',
            self::UserAgent => 'heroicon-o-computer-desktop',
            self::Referer => 'heroicon-o-arrow-uturn-left',
            self::Route => 'heroicon-o-map',
            self::Session => 'heroicon-o-key',
            self::Exception => 'heroicon-o-exclamation-triangle',
            self::Replay => 'heroicon-o-arrow-path',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $field) {
            $options[$field->value] = $field->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        $descriptions = [];

        foreach (self::cases() as $field) {
            $descriptions[$field->value] = $field->description();
        }

        return $descriptions;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        $groups = [];

        foreach (self::cases() as $field) {
            $groups[$field->group()][$field->value] = $field->label();
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $field): string => $field->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function coreValues(): array
    {
        return array_values(array_map(
            fn (self $field): string => $field->value,
            array_filter(self::cases(), fn (self $field): bool => $field->isCore()),
        ));
    }

    /**
     * Normalise a user supplied list to known field names, always retaining
     * the core fields.
     *
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    public static function normalize(array $values): array
    {
        $valid = array_values(array_intersect(self::values(), array_map(
            fn (mixed $value): string => (string) $value,
            $values,
        )));

        return array_values(array_unique(array_merge(self::coreValues(), $valid)));
    }
}
