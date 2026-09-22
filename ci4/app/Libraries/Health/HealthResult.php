<?php namespace App\Libraries\Health;

readonly class HealthResult {

    /** Longer than any row can show; the column is 1023. */
    private const int MaxReason = 1000;

    public string $reason;

    public function __construct(
        public string $health,
        string $reason = '',
    ) {
        $this->reason = mb_strimwidth($reason, 0, self::MaxReason, '…');
    }

    /**
     * @param list<string> $reasons
     */
    public static function Because(string $health, array $reasons): HealthResult {
        return new HealthResult($health, implode('; ', array_values(array_unique($reasons))));
    }

}
